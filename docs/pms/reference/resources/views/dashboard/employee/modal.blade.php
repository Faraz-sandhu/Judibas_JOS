<div class="modal fade" id="basicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel1">Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 col-12 mb-4">
                        <label for="name" class="form-label">Name <span class="ms-1 text-danger">*</span></label>
                        <input type="text" id="name" class="form-control" placeholder="Enter name" maxlength="255" autocomplete="given-name">
                    </div>
                    <div class="col-md-6 col-12 mb-4">
                        <label for="surname" class="form-label">Surname <span class="ms-1 text-danger"></span></label>
                        <input type="text" id="surname" class="form-control" placeholder="Enter surname" maxlength="255" autocomplete="family-name">
                    </div>
                    <div class="col-md-6 col-12 mb-4">
                        <label for="email" class="form-label">Email <span class="ms-1 text-danger">*</span></label>
                        <input type="email" id="email" class="form-control" placeholder="Enter email">
                    </div>
                    <div class="col-md-6 col-12 mb-4 password-field">
                        <label for="password" class="form-label">Password <span class="ms-1 text-danger">*</span></label>
                        <input type="password" id="password" class="form-control" placeholder="Enter Password">
                    </div>
                    <div class="col-md-6 col-12 mb-4">
                        <label for="designation" class="form-label">Designation <span class="ms-1 text-danger">*</span></label>
                        <input type="text" id="designation" class="form-control" placeholder="Enter designation">
                    </div>
                    
                    <div class="col-md-6 col-12 mb-4">
                        <label for="role_id" class="form-label">Role <span class="ms-1 text-danger">*</span></label>
                        <select id="role_id" name="role_id" class="form-select employee-modal-select w-100">
                            <option value="">Select role</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->role_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 col-12 mb-4">
                        <label for="dept_id" class="form-label">Department <span class="ms-1 text-danger">*</span></label>
                        <select id="dept_id" name="dept_id" class="form-select employee-modal-select w-100">
                            <option value="">Select department</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->dept_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 col-12 mb-4">
                        <label for="status" class="form-label">Status <span class="ms-1 text-danger">*</span></label>
                        <select id="status" name="status" class="form-select">
                            <option value="1" selected>Active</option>
                            <option value="2">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary submitBtn">Create</button>
            </div>
        </div>
    </div>
</div>
