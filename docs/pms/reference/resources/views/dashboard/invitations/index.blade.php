@extends('layouts.app')
@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted text-uppercase">Sent</small><h3 class="mb-0">{{ $sent }}</h3></div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted text-uppercase">Pending</small><h3 class="mb-0 text-primary">{{ $pending }}</h3></div></div></div>
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><small class="text-muted text-uppercase">Accepted</small><h3 class="mb-0 text-success">{{ $accepted }}</h3></div></div></div>
</div>
<div class="row">
    <div class="col-12">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div><h4 class="mb-1">Invitations</h4><p class="text-muted mb-0">Track task invitations and employee onboarding from one place.</p></div>
            @if($canInviteEmployees)
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#inviteEmployeeModal"><i class="ti ti-user-plus me-1"></i>Invite employee</button>
            @endif
        </div>

        <div class="card">
            <div class="card-body">
                <table id="sent-table" class="table ">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Context</th>
                            <th>Role / Item</th>
                            <th>To</th>
                            <th>Email</th>
                            <th>Sent</th>
                            <th>Status</th>
                            <th>Delivery</th>
                            <th>Share</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

@if($canInviteEmployees)
<div class="modal fade" id="inviteEmployeeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div><h5 class="modal-title">Invite employee</h5><small class="text-muted">They will choose their own password from a secure link.</small></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="invite-employee-form">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" name="email" class="form-control" placeholder="employee@company.com" required></div>
                        <div class="col-12"><label class="form-label">Name <span class="text-muted">(optional)</span></label><input type="text" name="name" class="form-control" placeholder="Employee name"></div>
                        <div class="col-md-6"><label class="form-label">Role <span class="text-danger">*</span></label><select name="role_id" class="form-select" required><option value="">Select role</option>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->role_name }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label">Department</label><select name="department_id" class="form-select"><option value="">No department</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->dept_name }}</option>@endforeach</select></div>
                    </div>
                    <div class="alert alert-primary mt-3 mb-0"><i class="ti ti-shield-check me-1"></i>No temporary password is created. The invitation expires after 7 days.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delivery_method" value="manual" class="btn btn-outline-primary"><i class="ti ti-copy me-1"></i>Create & copy</button>
                    <button type="submit" name="delivery_method" value="email" class="btn btn-primary"><i class="ti ti-send me-1"></i>Send invitation</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('page-scripts')
<script>
$(function () {
    const table = $('#sent-table').DataTable({
        ajax: "{{ route('invitations.sent.list') }}",
        columns: [
            { data: 'invitable_type' },
            { data: 'project_name' },
            { data: 'invitable_name' },
            { data: 'recipient' },
            { data: 'recipient_email', defaultContent: '-' },
            { data: 'created_at' },
            {
                data: 'status',
                render: (data, type, row) => {
                    return `<span class="badge bg-${row.status_color}">${data}</span>`;
                }
            },
            {
                data: 'delivery_status',
                render: data => {
                    const colors = {sent:'success', manual:'primary', failed:'danger', quota_reached:'warning', email_disabled:'secondary', not_configured:'secondary', pending:'info'};
                    return `<span class="badge bg-label-${colors[data] || 'secondary'} text-capitalize">${String(data || 'pending').replaceAll('_', ' ')}</span>`;
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: (_data, _type, row) => `<button type="button" class="btn btn-sm btn-icon btn-label-primary copy-invitation" data-message="${encodeURIComponent(row.manual_message)}" title="Copy invitation"><i class="ti ti-copy"></i></button>`
            }
        ]
    });

    $('#sent-table').on('click', '.copy-invitation', async function () {
        await navigator.clipboard.writeText(decodeURIComponent(this.dataset.message));
        toastr.success('Invitation message copied.');
    });

    $('#invite-employee-form').on('click', 'button[type="submit"]', function () {
        $('#invite-employee-form').data('delivery', this.value);
    }).on('submit', function (event) {
        event.preventDefault();
        const form = this;
        const data = Object.fromEntries(new FormData(form).entries());
        data.delivery_method = $(form).data('delivery') || 'email';

        $.ajax({
            url: @json(route('invitations.employees.store')),
            method: 'POST',
            data,
            headers: {'X-CSRF-TOKEN': @json(csrf_token())},
            success: async response => {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('inviteEmployeeModal')).hide();
                form.reset();
                table.ajax.reload(null, false);
                if (window.showInvitationDeliveryResult) await window.showInvitationDeliveryResult(response);
                else toastr.success(response.message);
            },
            error: xhr => {
                const errors = xhr.responseJSON?.errors;
                const message = errors ? Object.values(errors).flat()[0] : (xhr.responseJSON?.message || 'Employee invitation could not be created.');
                toastr.error(message);
            }
        });
    });
});
</script>
@endpush
