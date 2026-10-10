@extends('layouts.app')
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="table" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Name</th>
                                    {{-- <th>Description</th>
                                    <th>Attachment</th>
                                    <th>Start Date</th>
                                    <th>End Date</th> --}}
                                    <th>Lead</th>
                                    <th>Tasks</th>
                                    <th>Unassigned</th>
                                    <th>Status</th>
                                    <th>Approval</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('dashboard.projects.modal')
    @include('dashboard.projects.assign-modal')
    @include('dashboard.projects.project-view-offcanvas')
    @include('dashboard.projects.submission')
    @include('dashboard.projects.view-submissions-modal')
    <x-project-chat-modal />
@endsection
@push('page-scripts')
    @include('dashboard.projects.script')
    @include('dashboard.projects.project-chat-script')
@endpush
