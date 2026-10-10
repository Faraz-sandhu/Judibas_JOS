<div class="offcanvas offcanvas-end" tabindex="-1" id="submissionOffcanvas" aria-labelledby="submissionOffcanvasLabel">
    <div class="offcanvas-header">
        <h6 id="submissionOffcanvasLabel" class="offcanvas-title"></h6>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body task-details  mx-auto flex-grow-0">
        <h3 id="taskTitle"></h3>
        <div class="row g-2">
            <div class="col-12">
                <form id="submissionform" enctype="multipart/form-data">
                    <input type="hidden" id="main_id" name="main_id">
                    <input type="hidden" id="type" name="type">
                    @csrf
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" id="title" class="form-control" placeholder="Enter Title"
                            name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" class="form-control" placeholder="Enter Description" rows="10" name="description"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="file" class="form-label">Attachment</label>
                        <div class="form-text mb-1"><i class="fa fa-circle-info me-1"></i>PDF, Word, ZIP, RAR, JPG, JPEG or PNG · Maximum 10 MB.</div>
                        <input class="form-control" type="file" id="file" name="file" accept=".pdf,.doc,.docx,.zip,.rar,.jpg,.jpeg,.png">
                    </div>
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary"> <i
                        class="fa fa-paper-plane me-2"></i>Submit</button>
                    </div>
                </form>
            </div>
            <div class="col-12">
                <div class="alert alert-warning" role="alert">
                    <div class="d-flex gap-2">
                        <div><span><i class="fa fa-warning text-warning fs-3"></i></span></div>
                        <div><span id="messages"></span></div>
                    </div>
                </div>
            </div>
            
            <div class="col-12">
                <button class="btn btn-primary viewSubmissions w-100">View submissions <span class="badge bg-label-primary ms-2" id="submissionsCount"> 0</span></button>
            </div>
           
        </div>
    </div>
</div>
