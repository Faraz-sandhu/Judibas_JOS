<div class="modal fade department-modal" id="basicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div><h5 class="modal-title" id="departmentModalTitle">Create department</h5><small class="text-muted">Add a department for employees and project workflows.</small></div>
                <button type="button" class="btn btn-icon btn-sm btn-label-secondary modal-close-btn" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ti ti-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 col-12 mb-4">
                        <label for="dept_name" class="form-label">Name <span class="ms-1 text-danger">*</span></label>
                        <input type="text" id="dept_name" class="form-control" placeholder="Enter Name">
                    </div>
                    <div class="col-md-6 col-12 mb-4">
                        <label for="status" class="form-label">Status <span class="ms-1 text-danger">*</span></label>
                        <select id="status" name="status" class="form-select">
                            <option value="1" selected>Active</option>
                            <option value="2">Inactive</option>
                        </select>
                    </div>
                    <div class="col-12 mb-4">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" rows="3" placeholder="Enter Description here..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary submitBtn">Create department</button>
            </div>
        </div>
    </div>
</div>
