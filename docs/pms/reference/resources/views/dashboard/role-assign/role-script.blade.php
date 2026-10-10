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
                url: "{{ route('role-permission.role-assign') }}",
                type: "GET",
                dataSrc: "data",
            },
            columns: [{
                    data: "id",
                },
                {
                    data: "name",
                },
                {
                    data: "roles",
                    render: function(data) {
                        return `<span class="badge bg-label-primary text-uppercase">${data[0].role_name}</span>`;
                    },
                },
                {
                    data: null,
                    className: "text-center no-export",
                    orderable: false,
                    render: function(data, type, row) {
                        return `
                        <div class="d-inline-flex align-items-center">
                            <button type="button" class="btn btn-sm btn-icon btn-label-primary modal-edit-btn" data-id="${row.id}" title="Change role" aria-label="Change role"><i class="fa fa-user-pen"></i></button>
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
                    text: '<i class="fa fa-plus-circle"></i> <span class="ms-1">Assign</span>',
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
        // Event delegation for dynamically generated Edit buttons
        $("#table").on("click", ".modal-edit-btn", function() {
            isEdit = true;
            const id = $(this).data("id");
            const url = "{{ route('employee.edit', ':id') }}".replace(":id", id);
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
                    $(".user_id").hide();
                }
            })
        });
        $(".modal-add-btn").on("click", function() {
            isEdit = false;
            $(".user_id").show();
            $("#basicModal").modal("show")
        });

        $(".submitBtn").on("click", function() {
            if (isEdit == true) {
                updateEmployee();
            } else {
                createEmployee();
            }
        });

        function createEmployee() {
            let user_id = $("#user_id").val().trim();
            let role_id = $("#role_id").val().trim()
            $(".form-select").removeClass("is-invalid");
            $(".invalid-feedback").fadeOut(200, function() {
                $(this).remove();
            });
            let hasError = false;

            if (user_id === "") {
                showError("#user_id", "User is required.");
                hasError = true;
            }
            if (role_id === "") {
                showError("#role_id", "Role is required.");
                hasError = true;
            }

            if (hasError) return;

            $.ajax({
                url: "{{ route('role-permission.assign') }}",
                type: "POST",
                data: {
                    user_id: user_id,
                    role_id: role_id,
                    _token: "{{ csrf_token() }}",
                },
                success: function(response) {
                    $("#basicModal").modal("hide");
                    toastr.success(response.message);
                    updateTable();
                    $("#basicModal input, #basicModal select").val("");
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function(key, value) {
                            showError(`#${key}`, value[0]);
                        });
                    } else {
                        alert("An unexpected error occurred.");
                    }
                },
            });
        }

        function updateEmployee() {
            const RecordId = localStorage.getItem('recordID');
            let role_id = $("#role_id").val().trim();
            $(".form-control, .form-select").removeClass("is-invalid");
            $(".invalid-feedback").fadeOut(200, function() {
                $(this).remove();
            });
            let hasError = false;

            if (role_id === "") {
                showError("#role_id", "Role is required.");
                hasError = true;
            }

            if (hasError) return;
            const url = "{{ route('role-permission.assign.update', ':id') }}".replace(":id", RecordId);
            $.ajax({
                url: url,
                type: "POST",
                data: {
                    role_id: role_id,
                    _token: "{{ csrf_token() }}",
                },
                success: function(response) {
                    $("#basicModal").modal("hide");
                    toastr.success(response.message);
                    updateTable();
                    $("#basicModal input, #basicModal select").val("");
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function(key, value) {
                            showError(`#${key}`, value[0]);
                        });
                    } else {
                        alert("An unexpected error occurred.");
                    }
                },
            });
        }

        function showError(selector, message) {
            $(selector).addClass("is-invalid");
            $('<div class="invalid-feedback" style="display: none;">' + message + "</div>")
                .insertAfter(selector)
                .fadeIn(300);
        }
    });
</script>
