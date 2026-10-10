<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel1">Assign Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col mb-4">
                        <label for="selectTeamLeader" class="form-label">Select Employee</label>
                        <select id="selectTeamLeader" class="selectpicker project-assign w-100"
                            data-style="btn-default" multiple data-icon-base="ti" data-tick-icon="ti-check text-white"
                            data-live-search="true" data-live-search-placeholder="Search employee or role..."
                            data-none-selected-text="Choose employees" data-selected-text-format="count > 2"
                            data-size="6">
                            @foreach ($filteredUsers as $user)
                                @php
                                    $role = optional($user->roles->first())->role_key ?? 'employee';
                                    $roleLabel = ucfirst(str_replace('_', ' ', $role));
                                @endphp
                                <option value="{{ $user->id }}"
                                    data-tokens="{{ $user->name }} {{ $roleLabel }}"
                                    data-content="<span class='assign-employee-option'><span class='assign-employee-name'>{{ e($user->name) }}</span><span class='assign-employee-role'>{{ e($roleLabel) }}</span></span>">
                                    {{ $user->name }} — {{ $roleLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">Assign</button>
            </div>
        </div>
    </div>
</div>

<style>
    #assignModal .modal-dialog {
        max-width: 580px;
    }

    #assignModal .bootstrap-select > .dropdown-toggle {
        min-height: 44px;
        border-radius: .5rem;
    }

    #assignModal .bootstrap-select .dropdown-menu {
        max-width: 100%;
    }

    #assignModal .bootstrap-select .dropdown-menu li a {
        padding: .65rem .8rem;
    }

    #assignModal .bootstrap-select .dropdown-menu .no-results {
        margin: 0;
        padding: .75rem .8rem;
        background: transparent !important;
        color: var(--bs-secondary-color);
    }

    #assignModal .assign-employee-option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        gap: 1rem;
    }

    #assignModal .assign-employee-name {
        min-width: 0;
        overflow: hidden;
        font-weight: 500;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    #assignModal .assign-employee-role {
        flex: 0 0 auto;
        padding: .25rem .55rem;
        border-radius: 999px;
        background: rgba(var(--bs-primary-rgb), .12);
        color: var(--bs-primary);
        font-size: .75rem;
        font-weight: 600;
    }
</style>
