<div class="modal modal-lg fade" id="exLargeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="taskTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row response">
                    <div class="col-md-6 mb-3">
                        <strong>Status</strong>
                        <span class="badge bg-primary status"></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Due Date</strong>
                        <span class="badge bg-primary due_date"></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Priority</strong>
                        <span class="badge bg-primary priority"></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Invested Time</strong>
                        <span class="badge bg-primary invested_time"></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Task Stage</strong>
                        <span class="badge bg-primary stage"></span>
                    </div>
                </div>
                <div class="row mt-4 formData">
                    <label for="attachment">Attachment</label>
                    <div class="form-text mb-1"><i class="fa fa-circle-info me-1"></i>JPG, JPEG, PNG, WebP or GIF · Maximum 10 MB per image.</div>
                    <input id="attachment" name="attachment" type="file" multiple class="form-control" accept=".jpg,.jpeg,.png,.webp,.gif"/>
                </div>
                <div class="row mt-4 formData">
                    <div class="task-images"> </div>
                </div>
                <div class="row mt-4 formData">
                    <label for="description">Description</label>
                    <textarea id="description" class="form-control task-description-editor" placeholder="Type here..." @cannot('description-update') readonly @endcannot></textarea>
                </div>
            </div>
            @can('description-update')
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary submitDescription">Update</button>
                </div>
            @endcan
        </div>
    </div>
</div>
