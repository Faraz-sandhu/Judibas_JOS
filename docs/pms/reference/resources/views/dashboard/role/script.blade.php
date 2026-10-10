<script>
    let datatable;
    let isEdit = false;
    $(document).ready(function() {
        $(".selectpicker .role-select").selectpicker();
        datatable = $("#table").DataTable({
            serverSide: false,
            processing: true,
            paging: true,
            responsive: true,
            ajax: {
                url: "{{ route('roles.index') }}",
                type: "GET",
                dataSrc: "data",
            },
            columns: [{
                    data: "id",
                },
                {
                    data: "role_name",
                },
                {
                    data: "role_key"
                },
                {
                    data: "permissions",
                    render: data => `<span class="badge bg-label-primary">${data.length} permissions</span>`
                },
                {
                    data: null,
                    className: "text-center no-export",
                    orderable: false,
                    render: function(data, type, row) {
                        return `
                        <div class="d-inline-flex align-items-center gap-1">
                            <button type="button" class="btn btn-sm btn-icon btn-label-primary modal-edit-btn" data-id="${row.id}" title="Edit role" aria-label="Edit role"><i class="fa fa-pen-to-square"></i></button>
                            <button type="button" class="btn btn-sm btn-icon btn-label-danger delete-btn" data-id="${row.id}" title="Delete role" aria-label="Delete role"><i class="fa fa-trash"></i></button>
                        </div>
                    `;
                    },
                },
            ],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search...",
            },
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"],
            ],
            pageLength: 10,
            dom: '<"card-header d-flex justify-content-between align-items-center"<"head-label text-center"><"dt-action-buttons text-end"B>>' +
                '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-end"f>>' +
                "tr" +
                '<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            buttons: [
                {
                    text: '<i class="fa fa-plus-circle"></i> <span class="ms-1">Create</span>',
                    className: "btn btn-primary btn-sm modal-add-btn",
                },
            ],
        });
        toastr.options = {
            "progressBar": true,
            "closeButton": true,
        }

        function updateTable() {
            datatable.ajax.reload(null, false);
        }

        function clearRoleForm() {
            $("#basicModal #role_name, #basicModal #role_key").val("").removeClass("is-invalid");
            $("#basicModal .permission-check").prop("checked", false);
            $("#basicModal .invalid-feedback").remove();
            $("#basicModal #permission_ids").removeClass("is-invalid");
        }
        // Event delegation for dynamically generated Edit buttons
        $("#table").on("click", ".modal-edit-btn", function() {
            isEdit = true;
            const id = $(this).data("id");
            const url = "{{ route('roles.edit', ':id') }}".replace(":id", id);
            localStorage.setItem('recordID', id);
            $.ajax({
                url: url,
                type: "GET",
                data: {
                    id: id,
                    _token: "{{ csrf_token() }}",
                },
                success: function(response) {
                    $("#basicModal").modal("show");
                    $("#basicModal #role_name").val(response.data.role_name);
                    $("#basicModal #role_key").val(response.data.role_key);
                    const permissionIds = response.data.permissions.map(permission => String(permission.id));
                    $(".permission-check").each(function() { this.checked = permissionIds.includes(String(this.value)); });
                }
            })
        });
        $(".modal-add-btn").on("click", function() {
            isEdit = false;
            clearRoleForm();
            $("#basicModal").modal("show")
        });
        // Event delegation for dynamically generated Delete buttons
        $("#table").on("click", ".delete-btn", function() {
            const id = $(this).data("id");
            Swal.fire({
                title: 'Are you sure?',
                text: 'You won\'t be able to revert this!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        type: 'DELETE',
                        url: '/roles/' + id,
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(data) {
                            Swal.fire('Deleted!', 'Your record has been deleted.',
                                'success');
                            updateTable();
                        },
                        error: function(error) {
                            Swal.fire('Error!', 'Something went wrong.', 'error');
                            console.log('Error:', error);
                        }
                    });
                }
            });
        });

        $(".submitBtn").on("click", function() {
            if (isEdit == true) {
                updateEmployee();
            } else {
                createEmployee();
            }
        });

        function createEmployee() {
            let role_name = $("#role_name").val().trim();
            let role_key =  $("#role_key").val().trim();
            let permission_ids = $(".permission-check:checked").map(function() { return this.value; }).get();
            $(".form-control").removeClass("is-invalid");
            $(".invalid-feedback").fadeOut(200, function() {
                $(this).remove();
            });
            let hasError = false;

            if (role_name === "") {
                showError("#role_name", "Role is required.");
                hasError = true;
            }

            if (role_key === "") {
                showError("#role_key", "Role key is required.");
                hasError = true;
            }
            if (!permission_ids.length) { toastr.error('Select at least one permission.'); hasError = true; }

            if (hasError) return;

            $.ajax({
                url: "{{ route('roles.store') }}",
                type: "POST",
                data: {
                    role_name: role_name,
                    role_key: role_key,
                    permission_ids: permission_ids,
                    _token: "{{ csrf_token() }}",
                },
                success: function(response) {
                    $("#basicModal").modal("hide");
                    toastr.success(response.message);
                    updateTable();
                    clearRoleForm();
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function(key, value) {
                            const field = key.startsWith('permission_ids') ? 'permission_ids' : key;
                            showError(`#${field}`, value[0]);
                        });
                    } else {
                        toastr.error(xhr.responseJSON?.message || "Unable to create the role. Please try again.");
                    }
                },
            });
        }

        function updateEmployee() {
            const RecordId = localStorage.getItem('recordID');
            let role_name = $("#role_name").val().trim();
            let role_key = $("#role_key").val().trim();
            let permission_ids = $(".permission-check:checked").map(function() { return this.value; }).get();
            $(".form-control").removeClass("is-invalid");
            $(".invalid-feedback").fadeOut(200, function() {
                $(this).remove();
            });
            let hasError = false;

            if (role_name === "") {
                showError("#role_name", "Role is required.");
                hasError = true;
            }
            if (role_key === "") { showError("#role_key", "Role key is required."); hasError = true; }
            if (!permission_ids.length) { toastr.error('Select at least one permission.'); hasError = true; }

            if (hasError) return;
            const url = "{{ route('roles.update', ':id') }}".replace(":id", RecordId);
            $.ajax({
                url: url,
                type: "PUT",
                data: {
                    record_id: RecordId,
                    role_name: role_name,
                    role_key: role_key,
                    permission_ids: permission_ids,
                    _token: "{{ csrf_token() }}",
                },
                success: function(response) {
                    $("#basicModal").modal("hide");
                    toastr.success(response.message);
                    updateTable();
                    clearRoleForm();
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function(key, value) {
                            const field = key.startsWith('permission_ids') ? 'permission_ids' : key;
                            showError(`#${field}`, value[0]);
                        });
                    } else {
                        toastr.error(xhr.responseJSON?.message || "Unable to update the role. Please try again.");
                    }
                },
            });
        }

        function showError(selector, message) {
            const field = $(selector);
            field.addClass("is-invalid");
            field.next(".invalid-feedback").remove();
            $('<div class="invalid-feedback" style="display: none;"></div>')
                .text(message)
                .insertAfter(field)
                .fadeIn(300);
        }
        $(".form-control, .form-select").on("input", function() {
            $(this).removeClass("is-invalid");
            $(this)
                .next(".invalid-feedback")
                .fadeOut(200, function() {
                    $(this).remove();
                });
        });
        $(document).on('click', '.select-all-permissions', () => $('.permission-check').prop('checked', true));
        $(document).on('click', '.clear-permissions', () => $('.permission-check').prop('checked', false));
    });
</script>
