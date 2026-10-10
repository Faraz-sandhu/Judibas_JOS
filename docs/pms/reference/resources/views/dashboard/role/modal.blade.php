<div class="modal fade" id="basicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel1">Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-12 col-md-6 mb-4">
                        <label for="role_name" class="form-label">Role Name<span class="ms-1 text-danger">*</span></label>
                        <input type="text" id="role_name" class="form-control" placeholder="Enter Role Name">
                    </div>
                    <div class="col-12 col-md-6 mb-4">
                        <label for="role_key" class="form-label">Role Key<span class="ms-1 text-danger">*</span></label>
                        <input type="text" id="role_key" class="form-control" placeholder="Enter Role Key">
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label mb-0">Permissions <span class="text-danger">*</span></label>
                    <div><button type="button" class="btn btn-sm btn-label-primary select-all-permissions">Select all</button><button type="button" class="btn btn-sm btn-label-secondary ms-1 clear-permissions">Clear</button></div>
                </div>
                <div id="permission_ids" class="row g-2 border rounded p-2">
                    @foreach($permissions as $permission)
                        <div class="col-md-6"><label class="form-check border rounded p-2 w-100 mb-0"><input class="form-check-input permission-check" type="checkbox" value="{{ $permission->id }}"><span class="form-check-label ms-1">{{ $permission->permission_name }}<small class="d-block text-muted">{{ $permission->permission_key }}</small></span></label></div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary submitBtn">Create</button>
            </div>
        </div>
    </div>
</div>
