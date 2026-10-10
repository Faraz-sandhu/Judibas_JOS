<?php

namespace App\Modules\Pms\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Pms\Services\PmsAccess;

use App\Modules\Pms\Models\Department;
use App\Modules\Pms\Models\Workflow;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        PmsAccess::requirePermission(request(), 'department-view');

        return response()->json([
            'workflows' => Workflow::with(['department:id,dept_name', 'columns'])->orderBy('name')->get(),
            'departments' => Department::where('status', 1)->orderBy('dept_name')->get(['id', 'dept_name']),
        ]);
    }

    public function store(Request $request)
    {
        PmsAccess::requirePermission(request(), 'department-edit');
        $validated = $this->validateWorkflow($request);
        DB::transaction(fn () => $this->persist(new Workflow, $validated));

        return response()->json(['message'=>'Workflow created successfully.']);
    }

  
    public function update(Request $request, Workflow $workflow)
    {
        PmsAccess::requirePermission(request(), 'department-edit');
        $validated = $this->validateWorkflow($request);
        DB::transaction(fn () => $this->persist($workflow, $validated));

        return response()->json(['message'=>'Workflow updated successfully.']);
    }

    public function destroy(Workflow $workflow)
    {
        PmsAccess::requirePermission(request(), 'department-trash');
        abort_if($workflow->tasks()->exists(), 422, 'A workflow in use cannot be deleted.');
        $workflow->delete();

        return response()->json(['message'=>'Workflow deleted successfully.']);
    }

    private function validateWorkflow(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:pms_departments,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'transition_mode' => ['required', Rule::in(['any', 'adjacent'])],
            'columns' => ['required', 'array', 'min:2', 'max:20'],
            'columns.*.id' => ['nullable', 'integer', 'exists:pms_workflow_columns,id'],
            'columns.*.name' => ['required', 'string', 'max:100'],
            'columns.*.color' => ['required', Rule::in(['secondary', 'primary', 'info', 'warning', 'danger', 'success'])],
            'initial_column' => ['required', 'integer', 'min:0'],
            'completed_column' => ['required', 'integer', 'min:0'],
        ]);
    }

    private function persist(Workflow $workflow, array $data): void
    {
        $workflow->fill([
            'name' => $data['name'],
            'department_id' => $data['department_id'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? false,
            'transition_mode' => $data['transition_mode'],
        ])->save();
        $keptIds = [];
        foreach ($data['columns'] as $position => $column) {
            $record = ! empty($column['id'])
                ? $workflow->columns()->whereKey($column['id'])->firstOrFail()
                : $workflow->columns()->make();
            $record->fill([
                'name' => $column['name'],
                'color' => $column['color'],
                'position' => $position,
                'is_initial' => $position === (int) $data['initial_column'],
                'is_completed' => $position === (int) $data['completed_column'],
            ])->save();
            $keptIds[] = $record->id;
        }
        $removed = $workflow->columns()->whereNotIn('id', $keptIds);
        abort_if((clone $removed)->whereHas('pms_tasks')->exists(), 422, 'Move tasks out of a column before removing it.');
        $removed->delete();
    }
}
