<?php

namespace App\Modules\Daymark\Http\Controllers;

use App\Modules\Daymark\Models\Report;
use App\Modules\Daymark\Services\Access;
use App\Modules\Daymark\Services\ReportSpreadsheet;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DailyExportController
{
    public function __invoke(Request $request)
    {
        $values = $request->validate([
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'company' => 'nullable|integer|min:1',
            'department' => 'nullable|string|max:200',
            'format' => 'required|in:pdf,xlsx,html',
        ]);

        $reports = Report::visibleTo(Access::actor($request))
            ->where('status', '!=', 'draft')
            ->whereDate('date', $values['date'])
            ->with('user.company')
            ->when($request->filled('company'), fn ($query) => $query->whereHas('user', fn ($user) => $user->where('company_id', $values['company'])))
            ->when($request->filled('department'), fn ($query) => $query->whereHas('user', fn ($user) => $user->where('department', $values['department'])))
            ->get();

        abort_if($reports->isEmpty(), 422, 'No submitted reports are available for this date and selection.');

        // Group by company ID so identically named companies never merge.
        $companies = $reports->groupBy(fn ($report) => $report->user->company_id ?? 'platform')
            ->map(fn ($companyReports) => [
                'name' => $companyReports->first()->user->company?->name ?? 'Platform',
                'departments' => $companyReports->groupBy(fn ($report) => mb_strtolower(trim($report->user->department ?: 'Unassigned department')))
                    ->map(fn ($teamReports) => [
                        'name' => trim($teamReports->first()->user->department) ?: 'Unassigned department',
                        'reports' => $teamReports->sortBy(fn ($report) => mb_strtolower($report->user->name))->values(),
                    ])->sortBy(fn ($department) => mb_strtolower($department['name']))->values(),
            ])->sortBy(fn ($company) => mb_strtolower($company['name']))->values();

        $filename = 'daymark-daily-'.$values['date'];
        if ($values['format'] === 'xlsx') {
            $book = app(ReportSpreadsheet::class)->daily($companies, $values['date']);

            return response()->streamDownload(function () use ($book) {
                try {
                    (new Xlsx($book))->save('php://output');
                } finally {
                    $book->disconnectWorksheets();
                }
            }, $filename.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }

        $html = view('daymark.daily', [
            'companies' => $companies,
            'date' => Carbon::parse($values['date'])->format('l, d F Y'),
            'count' => $reports->count(),
            'hours' => $reports->sum('hours'),
            'generatedAt' => now()->format('d M Y, H:i').' '.config('app.timezone'),
            'viewer' => Access::actor($request),
        ])->render();

        if ($values['format'] === 'html') {
            return response($html);
        }

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setTempDir(storage_path('framework/cache'));
        $options->setFontCache(storage_path('framework/cache'));
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        $pdf->getCanvas()->page_text(470, 814, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 8, [0.4, 0.45, 0.42]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
        ]);
    }
}
