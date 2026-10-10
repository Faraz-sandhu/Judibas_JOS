@php
$checkRole = false;
if(@auth()->user()->hasRole('admin')){
    $checkRole = true;
}else{
    $checkRole = false;
}
@endphp
<script>
    let datatable;
    let isEdit = false;
    $(document).ready(function() {
        $('.employee-modal-select').select2({
            dropdownParent: $('#basicModal'),
            width: '100%',
            allowClear: true,
            placeholder: function() { return $(this).find('option[value=""]').text(); }
        });
        $('#basicModal').on('hide.bs.modal', function() {
            $('.employee-modal-select').select2('close');
        });
        datatable = $("#table").DataTable({
            serverSide: false,
            processing: true,
            paging: true,
            responsive: true,
            ajax: {
                url: "{{ route('employee.index') }}",
                type: "GET",
                dataSrc: "data",
            },
            columns: [{
                    data: "id",
                },
                {
                    data: "profile_img",
                    render: function(data) {
                        console.log(data);
                        return `<img src="${data}" class="rounded-circle" width="40" height="40"/>`;
                    },
                },
                {
                    data: "name",
                },
                {
                    data: "email",
                },
                {
                    data: "departments",
                    render: function(data) {
                        return data.length ? `<span class="badge bg-label-primary text-uppercase">${data[0].dept_name}</span>` : '<span class="text-muted">Not assigned</span>';
                    },
                },
                {
                    data: "roles",
                    render: function(data) {
                        return data.length ? `<span class="badge bg-label-info">${data[0].role_name}</span>` : '<span class="text-danger">No role</span>';
                    },
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
                            <button type="button" class="btn btn-sm btn-icon btn-label-primary modal-edit-btn" data-id="${row.id}" title="Edit employee" aria-label="Edit employee"><i class="fa fa-pen-to-square"></i></button>
                            <button type="button" class="btn btn-sm btn-icon btn-label-danger delete-btn" data-id="${row.id}" title="Delete employee" aria-label="Delete employee"><i class="fa fa-trash"></i></button>
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
            @if($checkRole)
            dom: '<"card-header d-flex justify-content-between align-items-center"<"head-label text-center"><"dt-action-buttons text-end"B>>' +
                '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-end"f>>' +
                "tr" +
                '<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',

                buttons: [{
                    text: '<i class="fa fa-plus-circle"></i> <span class="ms-1">Create</span>',
                    className: "btn btn-primary btn-sm modal-add-btn",
                }, ],
            @endif
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
                    $("#basicModal #name").val(response.data.name);
                    $("#basicModal #email").val(response.data.email);
                    $("#basicModal #designation").val(response.data.designation);
                    $("#basicModal #surname").val(response.data.surname);
                    $("#basicModal #role_id").val(response.data.roles[0]?.id || '').trigger('change');
                    $("#basicModal #dept_id").val(response.data.departments[0]?.id || '').trigger('change');
                    $("#basicModal .password-field").hide();
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
            $("#basicModal .password-field").show();
            $("#basicModal input").val('');
            $("#basicModal #role_id, #basicModal #dept_id").val('').trigger('change');
            $("#basicModal #status").val('1');
            $("#basicModal").modal("show");
            $("#preview-img").hide();
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
                        url: '/employee/' + id,
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
                $('#preview-img').show();
                updateEmployee();
            } else {
                $('#preview-img').hide();
                createEmployee();
            }
        });

        function createEmployee() {
            let name = $("#name").val().trim();
            let surname = $("#surname").val().trim();
            let designation = $("#designation").val().trim();
            let email = $("#email").val().trim();
            let password = $("#password").val().trim();
            let status = $("#status").val();
            let dept_id = $("#dept_id").val();
            let role_id = $("#role_id").val();

            $(".form-control, .form-select").removeClass("is-invalid");
            $(".invalid-feedback").fadeOut(200, function() {
                $(this).remove();
            });

            let hasError = false;
            const personNamePattern = /^[\p{L}\p{M}]+(?:[ '\-][\p{L}\p{M}]+)*$/u;

            if (name === "") {
                showError("#name", "Name is required.");
                hasError = true;
            } else if (!personNamePattern.test(name)) {
                showError("#name", "Name may only contain letters, spaces, apostrophes, and hyphens.");
                hasError = true;
            }
            if (surname !== "" && !personNamePattern.test(surname)) {
                showError("#surname", "Surname may only contain letters, spaces, apostrophes, and hyphens.");
                hasError = true;
            }
            if (email === "") {
                showError("#email", "Email is required.");
                hasError = true;
            }
            if (designation === "") {
                showError("#designation", "Designation is required.");
                hasError = true;
            }
            if (password === "") {
                showError("#password", "Password is required.");
                hasError = true;
            }
            if (status === "") {
                showError("#status", "Status is required.");
                hasError = true;
            }
            if (dept_id === "") {
                showError("#dept_id", "Department is required.");
                hasError = true;
            }
            if (role_id === "") {
                showError("#role_id", "Role is required.");
                hasError = true;
            }

            if (hasError) return;

            let formData = new FormData();
            formData.append("name", name);
            formData.append("email", email);
            formData.append("surname", surname);
            formData.append("designation", designation);
            formData.append("password", password);
            formData.append("status", status);
            formData.append("dept_id", dept_id);
            formData.append("role_id", role_id);
            formData.append("_token", "{{ csrf_token() }}");

            sendFormData(formData);
        }

        function sendFormData(formData) {
            $.ajax({
                url: "{{ route('employee.store') }}",
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $("#basicModal").modal("hide");
                    toastr.success(response.message);
                    updateTable();
                    $("#basicModal input").val('');
                    $("#basicModal #role_id, #basicModal #dept_id").val('').trigger('change');
                    $("#basicModal #status").val('1');
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function(key, value) {
                            showError(`#${key}`, value[0]);
                        });
                    } else {
                        toastr.error("An unexpected error occurred.");
                    }
                },
            });
        }



        function updateEmployee() {
            const RecordId = localStorage.getItem('recordID');
            let name = $("#name").val().trim();
            let surname = $("#surname").val().trim();
            let designation = $("#designation").val().trim();
            let email = $("#email").val().trim();
            let status = $("#status").val();
            let dept_id = $("#dept_id").val();
            let role_id = $("#role_id").val();

            $(".form-control, .form-select").removeClass("is-invalid");
            $(".invalid-feedback").fadeOut(200, function() {
                $(this).remove();
            });

            let hasError = false;
            const personNamePattern = /^[\p{L}\p{M}]+(?:[ '\-][\p{L}\p{M}]+)*$/u;

            if (name === "") {
                showError("#name", "Name is required.");
                hasError = true;
            } else if (!personNamePattern.test(name)) {
                showError("#name", "Name may only contain letters, spaces, apostrophes, and hyphens.");
                hasError = true;
            }
            if (surname !== "" && !personNamePattern.test(surname)) {
                showError("#surname", "Surname may only contain letters, spaces, apostrophes, and hyphens.");
                hasError = true;
            }
            if (email === "") {
                showError("#email", "Email is required.");
                hasError = true;
            }
            if (designation === "") {
                showError("#designation", "Designation is required.");
                hasError = true;
            }
            if (status === "") {
                showError("#status", "Status is required.");
                hasError = true;
            }
            if (dept_id === "") {
                showError("#dept_id", "Department is required.");
                hasError = true;
            }
            if (role_id === "") {
                showError("#role_id", "Role is required.");
                hasError = true;
            }
            if (hasError) return;

            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            const url = "{{ route('employee.update', ':id') }}".replace(":id", RecordId);

            // Use FormData to send files
            let formData = new FormData();
            formData.append("record_id", RecordId);
            formData.append("name", name);
            formData.append("surname", surname);
            formData.append("designation", designation);
            formData.append("email", email);
            formData.append("status", status);
            formData.append("dept_id", dept_id);
            formData.append("role_id", role_id);

            $.ajax({
                url: url,
                type: "POST", // Change from PUT to POST (Laravel doesn't support file uploads with PUT)
                data: formData,
                headers: {
                    "X-CSRF-TOKEN": csrfToken
                },
                processData: false, // Important: Prevent jQuery from processing FormData
                contentType: false, // Important: Let the browser set the correct content type
                success: function(response) {
                    $("#basicModal").modal("hide");
                    toastr.success(response.message);
                    updateTable();
                    $("#basicModal input").val('');
                    $("#basicModal #role_id, #basicModal #dept_id").val('').trigger('change');
                    $("#basicModal #status").val('1');
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
            $(selector).next('.select2').find('.select2-selection').addClass('is-invalid');
            $('<div class="invalid-feedback" style="display: none;">' + message + "</div>")
                .insertAfter(selector)
                .fadeIn(300);
        }
        $(".form-control, .form-select").on("input change", function() {
            $(this).removeClass("is-invalid");
            $(this).next('.select2').find('.select2-selection').removeClass('is-invalid');
            $(this)
                .next(".validation-error")
                .fadeOut(200, function() {
                    $(this).remove();
                });
        });
    });
</script>
