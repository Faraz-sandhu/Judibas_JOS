<div class="modal fade" id="basicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel1">Role assign</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 col-12 mb-4 user_id">
                        <label for="user_id" class="form-label">Name <span class="ms-1 text-danger">*</span></label>
                        <select id="user_id" name="user_id" class="selectpicker role-select w-100" data-style="btn-default" data-live-search="true">
                            @foreach ($users as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 col-12 mb-4">
                        <label for="role_id" class="form-label">Role <span class="ms-1 text-danger">*</span></label>
                        <select id="role_id" name="role_id" class="selectpicker role-select w-100" data-style="btn-default" data-live-search="true">
                            @foreach ($roles as $item)
                                <option value="{{ $item->id }}">{{ $item->role_name }}</option>
                            @endforeach
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
