<?php

namespace App\Modules\Daymark\Http\Controllers;

use App\Modules\Daymark\Models\Company;
use App\Modules\Daymark\Models\CustomRole;
use App\Modules\Daymark\Models\Report;
use App\Modules\Daymark\Models\User;
use App\Modules\Daymark\Services\Access;
use App\Modules\Daymark\Services\ReportRichText;
use App\Modules\Daymark\Services\ReportSpreadsheet;
use App\Modules\Daymark\Services\RoleAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DaymarkController
{
    public function ownReport(Request $r)
    {
        $r->validate(['date' => 'required|date_format:Y-m-d']);

        return response()->json(['report' => Report::where('user_id', Access::actor($r)->id)->where('date', $r->input('date'))->first()]);
    }

    public function report(Request $r)
    {
        $v = $r->validate([
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'status' => ['required', Rule::in(['draft', 'submitted'])],
            'hours' => 'required|numeric|min:0|max:24',
            'content_format' => 'nullable|in:plain,html',
            'completed' => 'nullable|string|max:60000',
            'progress' => 'nullable|string|max:60000',
            'plan' => 'nullable|string|max:60000',
            'blockers' => 'nullable|string|max:60000',
        ]);
        $v['content_format'] = $v['content_format'] ?? 'plain';
        foreach (ReportRichText::FIELDS as $f) {
            $v[$f] = trim($v[$f] ?? '');
            if ($v['content_format'] === 'html') {
                $v[$f] = ReportRichText::sanitize($v[$f]);
            }
            $text = ReportRichText::text($v[$f], $v['content_format']);
            abort_if(mb_strlen($text) > 10000 || strlen($v[$f]) > 60000, 422, 'Each section can contain up to 10,000 text characters.');
            $meaningful = $v['content_format'] === 'html' ? html_entity_decode(strip_tags($v[$f]), ENT_QUOTES | ENT_HTML5, 'UTF-8') : $text;
            $meaningful = preg_replace('/[\x{00A0}\x{200B}-\x{200D}\x{FEFF}]/u', ' ', $meaningful);
            abort_if($v['status'] === 'submitted' && trim($meaningful) === '', 422, "Complete all four sections. Write 'None' where applicable.");
        }
        $report = DB::transaction(function () use ($r, $v) {
            User::whereKey(Access::actor($r)->id)->lockForUpdate()->firstOrFail();
            $old = Report::where('user_id', Access::actor($r)->id)->where('date', $v['date'])->lockForUpdate()->first();
            abort_if($old?->status === 'reviewed', 422, 'Reviewed reports are locked. Ask your manager to request changes.');
            abort_if($old && $old->status !== 'draft' && $v['status'] === 'draft', 422, 'A shared report cannot become a private draft. Submit your updated report instead.');
            $report = Report::updateOrCreate(['user_id' => Access::actor($r)->id, 'date' => $v['date']], $v);
            $this->audit(Access::actor($r), 'Report '.$v['status'], $v['date']);
            return $report;
        });
        \App\Modules\Daymark\Services\Realtime::publish($report, $v['status']);

        return ['ok' => true];
    }

    public function review(Request $r)
    {
        $v = $r->validate(['id' => 'required|integer', 'status' => ['required', Rule::in(['reviewed', 'changes_requested'])], 'feedback' => 'nullable|string|max:5000']);
        $u = Access::actor($r);
        abort_if($u->role === 'employee', 403, 'Managers only.');
        $report = DB::transaction(function () use ($u, $v) {
            $report = Report::visibleTo($u)->whereKey($v['id'])->lockForUpdate()->firstOrFail();
            abort_if($report->user_id === $u->id || $report->status === 'draft', 403, 'You cannot review this report.');
            abort_if($v['status'] === 'changes_requested' && ! trim($v['feedback'] ?? ''), 422, 'Explain the changes needed.');
            $report->update(['status' => $v['status'], 'feedback' => $v['feedback'] ?? '', 'reviewer_id' => $u->id]);
            $this->audit($u, 'Report '.$v['status'], $report->user->name.' / '.$report->date);
            return $report;
        });
        \App\Modules\Daymark\Services\Realtime::publish($report, $v['status']);

        return ['ok' => true];
    }

    private function reports(Request $r)
    {
        $r->validate(['date' => 'nullable|date_format:Y-m-d', 'month' => ['required_without:date', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        $month = $r->input('month', substr($r->input('date', ''), 0, 7));
        $query = Report::visibleTo(Access::actor($r))->with('user.company')->whereBetween('date', [$month.'-01', Carbon::createFromFormat('Y-m-d', $month.'-01')->endOfMonth()->toDateString()]);
        if ($r->filled('date')) {
            $query->where('date', $r->input('date'));
        }
        foreach (['employee' => 'user_id', 'status' => 'status'] as $key => $column) {
            if ($r->filled($key)) {
                $query->where($column, $r->input($key));
            }
        }
        if ($r->filled('company')) {
            $query->whereHas('user', fn ($q) => $q->where('company_id', $r->input('company')));
        }
        if ($r->filled('department')) {
            $query->whereHas('user', fn ($q) => $q->where('department', $r->input('department')));
        }

        return $query->orderByDesc('date')->orderByDesc('updated_at');
    }

    public function data(Request $r)
    {
        $u = Access::actor($r);
        $users = User::visibleTo($u)->get()->sortBy('name')->values();
        $companies = Company::when($u->role !== 'super_admin', fn ($q) => $q->whereKey($u->company_id))->get();
        $reports = $this->reports($r)->get()->map(fn ($x) => array_merge($x->toArray(), ['name' => $x->user->name, 'department' => $x->user->department, 'designation' => $x->user->designation, 'company_id' => $x->user->company_id]));
        $actionItems = Report::visibleTo($u)->with(['user:id', 'reviewer:id'])->where(function ($q) use ($u) {
            $q->where(fn ($own) => $own->where('user_id', $u->id)->where('status', 'changes_requested'));
            if (RoleAccess::allows($u, 'review_reports')) {
                $q->orWhere(fn ($requested) => $requested->where('reviewer_id', $u->id)->whereIn('status', ['changes_requested', 'submitted']));
            }
        })->orderByDesc('updated_at')->get()->map(fn ($x) => ['id' => $x->id, 'date' => $x->date, 'name' => $x->user->name, 'user_id' => $x->user_id, 'status' => $x->status, 'feedback' => $x->feedback, 'reviewer_name' => $x->reviewer?->name]);
        $assignments = Access::allows($r, 'manage_roles') ? DB::table('daymark_assignments')->whereIn('employee_id', $users->pluck('id'))->get() : [];
        $audit = Access::allows($r, 'view_audit') ? DB::table('daymark_audits')->latest('id')->limit(100)->get() : [];

        return response()->json(compact('users', 'companies', 'reports', 'assignments', 'audit') + ['action_items' => $actionItems, 'custom_roles' => $u->isAdmin() ? CustomRole::when($u->role !== 'super_admin', fn ($q) => $q->where('company_id', $u->company_id))->orderBy('name')->get() : [], 'departments' => DB::table('daymark_departments')->whereIn('company_id', $companies->pluck('id'))->orderBy('name')->get(), 'viewer' => $u, 'permissions' => Access::permissions($r), 'role_matrix' => Access::allows($r, 'view_roles') ? RoleAccess::matrix() : null]);
    }

    private function audit(User $u, string $action, $target): void
    {
        Access::audit(request(), $action, (string) $target);
    }

    public function export(Request $r)
    {
        $r->validate(['format' => 'nullable|in:csv,xlsx']);
        $rows = $this->reports($r)->get();
        if ($r->input('format') === 'xlsx') {
            $book = app(ReportSpreadsheet::class)->monthly($rows);

            return response()->streamDownload(function () use ($book) {
                try {
                    (new Xlsx($book))->save('php://output');
                } finally {
                    $book->disconnectWorksheets();
                }
            }, 'daymark-'.$r->input('month').'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        return response()->streamDownload(function () use ($rows) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, ['Date', 'Employee', 'Company', 'Department', 'Status', 'Hours', 'Completed', 'In progress', 'Next plan', 'Blockers', 'Feedback']);
            foreach ($rows as $x) {
                // CSV cannot retain widths. A text label prevents Excel date auto-formatting.
                $values = ['Date: '.Carbon::parse($x->date)->format('d M Y'), $x->user->name, $x->user->company?->name, $x->user->department, $x->status, $x->hours, $x->sectionText('completed'), $x->sectionText('progress'), $x->sectionText('plan'), $x->sectionText('blockers'), $x->feedback];
                fputcsv($f, array_map(fn ($v) => is_string($v) && preg_match('/^\s*[=+\-@]/', $v) ? "'".$v : $v, $values));
            }
            fclose($f);
        }, 'daymark-'.$r->input('month').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
