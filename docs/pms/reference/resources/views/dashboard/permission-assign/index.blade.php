@extends('layouts.app')
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="col-md-3 mb-3 col-12">
                        <label for="RoleId" class="form-label">Role<span class="text-danger fs-6 ms-1">*</span></label>
                        <select name="role_id" id="RoleId"class="selectpicker role-select w-100" data-style="btn-default" data-live-search="true">
                            @foreach ($roles as $index => $role)
                                <option value="{{ $role->id }}" @if ($index == 0) selected @endif>
                                    {{ $role->role_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="Permission-action pt-3">
                        <div class="row">
                            <h6>Permissions</h6>
                            <div class="col-md-12 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="all-permissions">
                                    <label class="form-check-label" for="all-permissions">All Permissions</label>
                                </div>
                            </div>
                            @foreach ($permissions as $item)
                                <div class="col-md-4 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input permission-checkbox" type="checkbox"
                                            id="{{ $item->permission_key }}" value="{{ $item->id }}">
                                        <label class="form-check-label"
                                            for="{{ $item->permission_key }}">{{ $item->permission_name }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('page-scripts')
    @include('dashboard.permission-assign.permission-script')
@endpush
