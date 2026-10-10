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
                url: "{{ route('departments.index') }}",
                type: "GET",
                dataSrc: "data",
            },
            columns: [{
                    data: "id",
                },
                {
                    data: "dept_name",
                },
                {
                    data: "description",
                },
                {
                    data: "status",
                    render: function(data) {
                        return data == 1 ?
                            `<span class="badge bg-label-success text-uppercase">active</span>` :
                            `<span class="badge bg-label-danger text-uppercase">inactive</span>`;
                    },
                },
                {
                    data: null,
                    className: "text-center no-export",
                    orderable: false,
                    render: function(data, type, row) {
                        return `
                        <div class="d-inline-flex align-items-center gap-1">
                            <button type="button" class="btn btn-sm btn-icon btn-label-primary modal-edit-btn" data-id="${row.id}" title="Edit department" aria-label="Edit department"><i class="fa fa-pen-to-square"></i></button>
                            <button type="button" class="btn btn-sm btn-icon btn-label-danger delete-btn" data-id="${row.id}" title="Delete department" aria-label="Delete department"><i class="fa fa-trash"></i></button>
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
            const url = "{{ route('departments.edit', ':id') }}".replace(":id", id);
            localStorage.setItem('recordID', id);
            $.ajax({
                url: url,
                type: "GET",
                data: {
                    id: id,
                    _token: "{{ csrf_token() }}",
                },
                success: function(response) {
                    $('#departmentModalTitle').text('Edit department');
                    $('#basicModal .submitBtn').text('Save changes');
                    $("#basicModal").modal("show");
                    $("#basicModal #dept_name").val(response.data.dept_name);
                    $("#basicModal #description").val(response.data.description);
                    if (response.data.status === 1) {
                        $("#basicModal #status").val("1");
                    } else if (response.data.status === 2) {
                        $("#basicModal #status").val("2");
                    }
                }
            })
        });
        $(".modal-add-btn").on("click", function() {
            isEdit = false;
            $("#basicModal input").val('');
            $("#basicModal textarea").val('');
            $("#basicModal #status").val('1');
            $('#departmentModalTitle').text('Create department');
            $('#basicModal .submitBtn').text('Create department');
            $('#basicModal .is-invalid').removeClass('is-invalid');
            $('#basicModal .invalid-feedback').remove();
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
                        url: '/departments/' + id,
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
                update();
            } else {
                create();
            }
        });

        function create() {
            let dept_name = $("#dept_name").val().trim();
            let description = $("#description").val().trim();
            let status = $("#status").val();
            $(".form-control, .form-select").removeClass("is-invalid");
            $(".invalid-feedback").fadeOut(200, function() {
                $(this).remove();
            });
            let hasError = false;

            if (dept_name === "") {
                showError("#dept_name", "Name is required.");
                hasError = true;
            }
            if (status === "") {
                showError("#status", "Status is required.");
                hasError = true;
            }

            if (hasError) return;

            $.ajax({
                url: "{{ route('departments.store') }}",
                type: "POST",
                data: {
                    dept_name: dept_name,
                    description: description,
                    status: status,
                    _token: "{{ csrf_token() }}",
                },
                success: function(response) {
                    $("#basicModal").modal("hide");
                    toastr.success(response.message);
                    updateTable();
                    $("#basicModal input, #basicModal textarea").val('');
                    $("#basicModal #status").val('1');
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function(key, value) {
                            showError(`#${key}`, value[0]);
                        });
                    } else {
                        toastr.error(xhr.responseJSON?.message || "Unable to create department.");
                    }
                },
            });
        }

        function update() {
            const RecordId = localStorage.getItem('recordID');
            let dept_name = $("#dept_name").val().trim();
            let description = $("#description").val().trim();
            let status = $("#status").val();
            $(".form-control, .form-select").removeClass("is-invalid");
            $(".invalid-feedback").fadeOut(200, function() {
                $(this).remove();
            });
            let hasError = false;

            if (dept_name === "") {
                showError("#dept_name", "Name is required.");
                hasError = true;
            }
            if (status === "") {
                showError("#status", "Status is required.");
                hasError = true;
            }

            if (hasError) return;
            const url = "{{ route('departments.update', ':id') }}".replace(":id", RecordId);
            $.ajax({
                url: url,
                type: "PUT",
                data: {
                    record_id: RecordId,
                    dept_name: dept_name,
                    description: description,
                    status: status,
                    _token: "{{ csrf_token() }}",
                },
                success: function(response) {
                    $("#basicModal").modal("hide");
                    toastr.success(response.message);
                    updateTable();
                    $("#basicModal input, #basicModal textarea").val('');
                    $("#basicModal #status").val('1');
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function(key, value) {
                            showError(`#${key}`, value[0]);
                        });
                    } else {
                        toastr.error(xhr.responseJSON?.message || "Unable to update department.");
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
        $(".form-control, .form-select").on("input change", function() {
            $(this).removeClass("is-invalid");
            $(this)
                .next(".invalid-feedback")
                .fadeOut(200, function() {
                    $(this).remove();
                });
        });
    });
</script>
