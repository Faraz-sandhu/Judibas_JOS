<script>
    let datatable;
    let isEdit = false;
    $(document).ready(function() {
        datatable = $("#table").DataTable({
            serverSide: false,
            processing: true,
            paging: true,
            responsive: true,
            ajax: {
                url: "{{ route('permissions.index') }}",
                type: "GET",
                dataSrc: "data",
            },
            columns: [{
                    data: "id",
                },
                {
                    data: "permission_name",
                },
                {
                    data: "permission_key",
                },
                {
                    data: null,
                    className: "text-center no-export",
                    orderable: false,
                    render: function(data, type, row) {
                        return `
                        <div class="d-inline-flex align-items-center gap-1">
                            <button type="button" class="btn btn-sm btn-icon btn-label-primary modal-edit-btn" data-id="${row.id}" title="Edit permission" aria-label="Edit permission"><i class="fa fa-pen-to-square"></i></button>
                            <button type="button" class="btn btn-sm btn-icon btn-label-danger delete-btn" data-id="${row.id}" title="Delete permission" aria-label="Delete permission"><i class="fa fa-trash"></i></button>
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
        // Event delegation for dynamically generated Edit buttons
        $("#table").on("click", ".modal-edit-btn", function() {
            isEdit = true;
            const id = $(this).data("id");
            const url = "{{ route('permissions.edit', ':id') }}".replace(":id", id);
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
                    $("#basicModal #permission_name").val(response.data.permission_name);
                    $("#basicModal #permission_key").val(response.data.permission_key).attr("readonly", true).addClass("bg-light");
                }
            })
        });
        $(".modal-add-btn").on("click", function() {
            isEdit = false;
            $("#basicModal input").val('').removeAttr("readonly").removeClass("bg-light");
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
                        url: '/permissions/' + id,
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
            let permission_name = $("#permission_name").val().trim();
            let permission_key = $("#permission_key").val().trim();
            $(".form-control").removeClass("is-invalid");
            $(".invalid-feedback").fadeOut(200, function() {
                $(this).remove();
            });
            let hasError = false;

            if (permission_key === "") {
                showError("#permission_name", "Permission name isrequired.");
                hasError = true;
            }
            if (permission_key === "") {
                showError("#permission_key", "Permission key name isrequired.");
                hasError = true;
            }

            if (hasError) return;

            $.ajax({
                url: "{{ route('permissions.store') }}",
                type: "POST",
                data: {
                    permission_name: permission_name,
                    permission_key: permission_key,
                    _token: "{{ csrf_token() }}",
                },
                success: function(response) {
                    $("#basicModal").modal("hide");
                    toastr.success(response.message);
                    updateTable();
                    $("#basicModal input").val("");
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
            let permission_name = $("#permission_name").val().trim();
            let permission_key = $("#permission_key").val().trim();
            $(".form-control").removeClass("is-invalid");
            $(".invalid-feedback").fadeOut(200, function() {
                $(this).remove();
            });
            let hasError = false;

            if (permission_name === "") {
                showError("#permission_name", "Permission name is required.");
                hasError = true;
            }
            if (permission_key === "") {
                showError("#permission_key", "Permission key is required.");
                hasError = true;
            }

            if (hasError) return;
            const url = "{{ route('permissions.update', ':id') }}".replace(":id", RecordId);
            $.ajax({
                url: url,
                type: "PUT",
                data: {
                    record_id: RecordId,
                    permission_name: permission_name,
                    permission_key: permission_key,
                    _token: "{{ csrf_token() }}",
                },
                success: function(response) {
                    $("#basicModal").modal("hide");
                    toastr.success(response.message);
                    updateTable();
                    $("#basicModal input").val("");
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
        $(".form-control").on("input", function() {
            $(this).removeClass("is-invalid");
            $(this)
                .next(".validation-error")
                .fadeOut(200, function() {
                    $(this).remove();
                });
        });
        $('#permission_name').on('input', function() {
            if(isEdit == true) return;
            var PermissionName = $(this).val();
            var PermissionKey = PermissionName.toLowerCase().replace(/ /g, '-').replace(/[^\w-]+/g, '');
            $('#permission_key').val(PermissionKey);
        });
    });
</script>
