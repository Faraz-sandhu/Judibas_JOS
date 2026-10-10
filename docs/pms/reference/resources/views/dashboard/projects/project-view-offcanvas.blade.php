
<div class="offcanvas offcanvas-end" tabindex="-1" id="viewProjectOffcanvas" aria-labelledby="viewProjectOffcanvasLabel">
  <div class="offcanvas-header">
    <h6 id="viewProjectOffcanvasLabel" class="offcanvas-title"></h6>
    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body task-details  mx-auto flex-grow-0">
    <h3 id="taskTitle"></h3>
    <input type="hidden" id="project_id">
    <div class="row g-2">
      <div class="col-12">
        <div class="task-images"></div>
    </div>
        <div class="col-12">
            <table class="table table-border-bottom">
                <tr> <th>Attachment:</th> <td><span class="attachment"></span></td></tr>
                <tr> <th>Link:</th> <td><span class="link"></span></td></tr>
                <tr> <th>Status:</th> <td><span class="status"></span></td></tr>
                <tr> <th>Start Date:</th> <td><span class="start_date"></span></td></tr>
                <tr> <th>Due Date:</th> <td><span class="due_date"></span></td></tr>
            </table>
        </div>
        <div class="col-12">
            <label for="description">Description</label>
            <textarea  rows="10" class="form-control description " placeholder="Type here..." readonly></textarea>
        </div>
    </div>
</div>
</div>