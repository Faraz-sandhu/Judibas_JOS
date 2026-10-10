<script>
    let datatable;
    $(document).ready(function() {
        datatable = $("#table").DataTable({
            serverSide: false,
            processing: true,
            paging: true,
            responsive: true,
            ajax: {
                url: "{{ route('departments.trash') }}",
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
                    data: null,
                    className: "text-center no-export",
                    orderable: false,
                    render: function(data, type, row) {
                        return `<div class="btn-group">
                            <button type="button" class="btn btn-primary btn-sm RestoreData" data-id="${data.id}">Restore</button>
                        </div>`;
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
        });
        toastr.options = {
            "progressBar": true,
            "closeButton": true,
        }

        function updateTable() {
            datatable.ajax.reload(null, false);
        }
        // Event delegation for dynamically generated restore buttons
        $("#table").on("click", ".RestoreData", function() {
            const id = $(this).data("id");
            Swal.fire({
                title: 'You want to restore?',
                text: 'You won\'t be able to revert this!',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, restore it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        type: 'PUT',
                        url: '/restore-departments/' + id,
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(data) {
                            Swal.fire('Restore!', 'Your record has been restore.',
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
    });
</script>
