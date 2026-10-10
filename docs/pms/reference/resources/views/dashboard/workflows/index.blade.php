@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><div class="text-muted small">ADMINISTRATION</div><h3 class="mb-0">Department workflows</h3></div>
    @can('department-edit')<button class="btn btn-primary" id="new-workflow"><i class="fa fa-plus me-1"></i>New workflow</button>@endcan
</div>
<div class="row g-3">
@forelse($workflows as $workflow)
    <div class="col-xl-6"><div class="card h-100"><div class="card-body">
        <div class="d-flex justify-content-between"><div><h5 class="mb-1">{{ $workflow->name }}</h5><span class="badge bg-label-info">{{ $workflow->department?->dept_name ?? 'All departments' }}</span></div>
            <div class="d-flex gap-1">@can('department-edit')<button class="btn btn-sm btn-icon edit-workflow" data-workflow='@json($workflow)'><i class="fa fa-pen"></i></button>@endcan
            @can('department-trash')<form method="POST" action="{{ route('workflows.destroy',$workflow) }}" onsubmit="return confirm('Delete this workflow?')">@csrf @method('DELETE')<button class="btn btn-sm btn-icon text-danger"><i class="fa fa-trash"></i></button></form>@endcan</div>
        </div>
        <p class="text-muted small mt-2">{{ $workflow->description }}</p>
        <div class="small text-muted mb-2"><i class="fa fa-shuffle me-1"></i>{{ $workflow->transition_mode === 'adjacent' ? 'Step-by-step movement' : 'Move to any column' }}</div><div class="d-flex flex-wrap gap-2">@foreach($workflow->columns as $column)<span class="badge bg-label-{{ $column->color }}">{{ $column->name }} @if($column->is_initial) · Start @endif @if($column->is_completed) · Complete @endif</span>@endforeach</div>
    </div></div></div>
@empty<div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5">No workflows configured.</div></div></div>@endforelse
</div>

@can('department-edit')
<div class="modal fade" id="workflowModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<form id="workflow-form" method="POST" action="{{ route('workflows.store') }}">@csrf <div id="workflow-method"></div>
<div class="modal-header"><h5 class="modal-title">Workflow</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="row g-3">
    <div class="col-md-7"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
    <div class="col-md-5"><label class="form-label">Department</label><select name="department_id" class="form-select"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->dept_name }}</option>@endforeach</select></div>
    <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
    <div class="col-md-6"><label class="form-label">Movement rule</label><select name="transition_mode" class="form-select"><option value="any">Allow movement to any column</option><option value="adjacent">Only previous or next column</option></select></div>
    <div class="col-12 form-check ms-2"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="workflow-active" checked><label class="form-check-label" for="workflow-active">Active</label></div>
    <div class="col-12"><div class="d-flex justify-content-between"><label class="form-label">Columns</label><button type="button" class="btn btn-sm btn-outline-primary" id="add-workflow-column">Add column</button></div><div id="workflow-columns"></div><div class="form-text">Choose one starting column and one completion column.</div></div>
</div></div><div class="modal-footer"><button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save workflow</button></div>
</form></div></div></div>
@endcan
@endsection

@push('page-scripts')
<script>
(() => {
 const form=document.getElementById('workflow-form'); if(!form)return; const list=document.getElementById('workflow-columns'); let index=0;
 const row=(column={})=>{const i=index++;return `<div class="workflow-column-row row g-2 align-items-center mb-2"><input type="hidden" name="columns[${i}][id]" value="${column.id||''}"><div class="col"><input class="form-control" name="columns[${i}][name]" value="${column.name||''}" placeholder="Column name" required></div><div class="col-3"><select class="form-select" name="columns[${i}][color]">${['secondary','primary','info','warning','danger','success'].map(c=>`<option value="${c}" ${column.color===c?'selected':''}>${c}</option>`).join('')}</select></div><div class="col-auto form-check"><input class="form-check-input initial-radio" type="radio" name="initial_column" value="${i}" ${column.is_initial?'checked':''} required title="Starting column"></div><div class="col-auto form-check"><input class="form-check-input completed-radio" type="radio" name="completed_column" value="${i}" ${column.is_completed?'checked':''} required title="Completion column"></div><div class="col-auto"><button type="button" class="btn btn-sm btn-icon text-danger remove-column"><i class="fa fa-xmark"></i></button></div></div>`};
 const reset=(workflow=null)=>{index=0;list.innerHTML='';form.reset();form.action=workflow?`{{ url('workflows') }}/${workflow.id}`:@json(route('workflows.store'));document.getElementById('workflow-method').innerHTML=workflow?'@method('PUT')':'';if(workflow){form.name.value=workflow.name;form.department_id.value=workflow.department_id||'';form.description.value=workflow.description||'';form.transition_mode.value=workflow.transition_mode||'any';document.getElementById('workflow-active').checked=workflow.is_active;workflow.columns.forEach(c=>list.insertAdjacentHTML('beforeend',row(c)));}else{list.insertAdjacentHTML('beforeend',row({name:'To Do',color:'secondary',is_initial:true}));list.insertAdjacentHTML('beforeend',row({name:'Done',color:'success',is_completed:true}));}};
 document.getElementById('new-workflow').onclick=()=>{reset();bootstrap.Modal.getOrCreateInstance(document.getElementById('workflowModal')).show()};document.querySelectorAll('.edit-workflow').forEach(b=>b.onclick=()=>{reset(JSON.parse(b.dataset.workflow));bootstrap.Modal.getOrCreateInstance(document.getElementById('workflowModal')).show()});document.getElementById('add-workflow-column').onclick=()=>list.insertAdjacentHTML('beforeend',row());list.addEventListener('click',e=>{if(e.target.closest('.remove-column')&&list.children.length>2)e.target.closest('.workflow-column-row').remove()});
})();
</script>
@endpush
