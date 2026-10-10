@extends('layouts.app')

@section('title', $department->dept_name.' Team Space')

@push('page-styles')
<style>
    .department-empty-shell{min-height:calc(100vh - 13rem);display:grid;place-items:center;padding:2rem}
    .department-empty-card{width:min(680px,100%);text-align:center;border:1px solid var(--bs-border-color);border-radius:1rem;background:var(--bs-body-bg);padding:3rem 2rem;box-shadow:0 1rem 3rem rgba(0,0,0,.06)}
    .department-empty-icon{width:5rem;height:5rem;margin:0 auto 1.5rem;display:grid;place-items:center;border-radius:1.25rem;background:rgba(var(--bs-primary-rgb),.12);color:var(--bs-primary);font-size:2rem}
    .department-empty-card p{max-width:480px;margin:0 auto 1.75rem}
</style>
@endpush

@section('content')
<div class="department-empty-shell">
    <section class="department-empty-card" aria-labelledby="department-empty-title">
        <div class="department-empty-icon"><i class="ti ti-folders-off" aria-hidden="true"></i></div>
        <div class="text-uppercase text-primary fw-semibold small mb-2">Team Space / {{ $department->dept_name }}</div>
        <h3 id="department-empty-title" class="mb-2">No projects in this department yet</h3>
        <p class="text-muted">Create the first project for {{ $department->dept_name }}. Its sprints and tasks will then appear on this department board.</p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            @can('project-add')
            <button type="button" class="btn btn-primary sidebar-project-add"
                data-department-id="{{ $department->id }}" data-department-name="{{ $department->dept_name }}">
                <i class="ti ti-plus me-1"></i>Create project
            </button>
            @endcan
            <a href="{{ route('projects.index') }}" class="btn btn-label-secondary"><i class="ti ti-folders me-1"></i>View projects</a>
        </div>
    </section>
</div>
@endsection
