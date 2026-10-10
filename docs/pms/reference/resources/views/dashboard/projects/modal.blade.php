<div class="modal fade project-modal" id="basicModal" tabindex="-1" aria-labelledby="projectModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content project-form-modal">
            <div class="modal-header">
                <div><h5 class="modal-title" id="projectModalTitle">Create project</h5><small class="text-muted" id="projectModalSubtitle">Set up the client, schedule, workflow, and project details.</small></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="project-form-section">
                    <div class="project-form-section-title">Project information</div>
                    <div class="row g-3">
                    <div class="col-lg-6">
                        <label for="name" class="form-label">Name <span class="ms-1 text-danger">*</span></label>
                        <input type="text" id="name" class="form-control" placeholder="Enter Name">
                    </div>
                    <div class="col-lg-6">
                        <label for="image" class="form-label">Project Logo</label>
                        <div class="form-text mb-1"><i class="fa fa-circle-info me-1"></i>JPG, JPEG, PNG, WebP or SVG · Maximum 2 MB.</div>
                        <input type="file" id="image" class="form-control" placeholder="Choose File"
                            accept=".jpg,.jpeg,.png,.webp,.svg">
                    </div>
                    <div class="col-lg-6">
                        <label for="company_name" class="form-label">Company / Client</label>
                        <input type="text" id="company_name" class="form-control" list="project-company-options"
                            placeholder="Select or enter a company">
                        <datalist id="project-company-options">
                            @foreach($companies as $company)<option value="{{ $company->name }}"></option>@endforeach
                        </datalist>
                        <div class="form-text">Choose an existing company or type a new name. A new company record will be created when you save.</div>
                    </div>
                    <div class="col-lg-6" id="project-department-field">
                        <label for="department_ids" class="form-label">Departments</label>
                        <select id="department_ids" class="form-select project-department-select" multiple data-placeholder="Search and select departments">
                            @foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->dept_name }}</option>@endforeach
                        </select>
                        <div class="form-text">Search and select one or more participating departments.</div>
                    </div>
                    <div class="col-lg-6">
                        <label for="attachment" class="form-label">Attachment</label>
                        <div class="form-text mb-1"><i class="fa fa-circle-info me-1"></i>PDF, Word, PowerPoint, Excel, CSV, TXT, JPG, JPEG or PNG · Maximum 10 MB.</div>
                        <input type="file" id="attachment" class="form-control" placeholder="Attachment"
                        accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.txt">
                    </div>

                    <div class="col-12">
                        <label for="url" class="form-label">URL</label>
                        <input type="text" id="url" class="form-control" placeholder="Enter URL">
                    </div>

                    </div>
                </div>

                <div class="project-form-section">
                    <div class="project-form-section-title">Project brief</div>
                    <div class="row g-3">
                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" class="form-control task-description-editor" data-editor-height="170" placeholder="Describe the scope, objectives, and important delivery notes."></textarea>
                    </div>
                    </div>
                </div>

                <div class="project-form-section mb-0">
                    <div class="project-form-section-title">Schedule and workflow</div>
                    <div class="row g-3">
                    <div class="col-md-6 password-field">
                        <label for="start_date" class="form-label">Start Date <span class="ms-1 text-danger">*</span></label>
                        <input type="date" id="start_date" class="form-control" required>
                    </div>
                    <div class="col-md-6 password-field">
                        <label for="end_date" class="form-label">End Date <span class="ms-1 text-danger"></span></label>
                        <input type="date" id="end_date" class="form-control">
                    </div>
                    <div class="col-12"><label class="form-label">Enabled workflows</label><div class="row g-2">
                        @foreach($availableWorkflows as $workflow)<div class="col-md-6"><label class="project-workflow-option"><input type="checkbox" class="form-check-input project-workflow" value="{{ $workflow->id }}" data-default="{{ $workflow->is_default ? '1' : '0' }}" @checked($workflow->is_default)><span><strong>{{ $workflow->name }}</strong>@if($workflow->department)<small>{{ $workflow->department->dept_name }}</small>@endif</span></label></div>@endforeach
                    </div></div>
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
<!-- Task Modal -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="taskModalLabel">Project Tasks</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table id="taskTable" class="table table-striped table-responsive">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            {{-- <th>Start Date</th>
                            <th>End Date</th> --}}
                            <th>Investment Time</th>
                            <th>Assignee</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
