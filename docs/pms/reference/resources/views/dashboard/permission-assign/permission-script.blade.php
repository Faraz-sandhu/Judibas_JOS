<script>
    $(document).ready(function() {
        $(".selectpicker .role-select").selectpicker();
        const firstRoleId = $('#RoleId option:first').val();
        if (firstRoleId) {
            fetchRolePermissions(firstRoleId);
        }
        $('#RoleId').on('change', function() {
            const roleId = $(this).val();
            if (roleId) {
                fetchRolePermissions(roleId);
            }
        });
        function fetchRolePermissions(roleId) {
            $('.permission-checkbox, #all-permissions').prop('checked', false);
            $.ajax({
                url: "{{ route('role-permission.get-role-permissions') }}",
                type: "POST",
                data: {
                    role_id: roleId,
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    response.forEach(permissionId => {
                        $(`.permission-checkbox[value="${permissionId}"]`).prop('checked',
                            true);
                    });
                    if ($('.permission-checkbox:checked').length === $('.permission-checkbox')
                        .length) {
                        $('#all-permissions').prop('checked', true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error fetching permissions:", error);
                }
            });
        }
        $('.permission-checkbox').on('change', function() {
            const roleId = $('#RoleId').val();
            const permissionId = $(this).val();
            const isChecked = $(this).is(':checked');

            if (!roleId) {
                $(this).prop('checked', !isChecked);
                return;
            }
            $.ajax({
                url: "{{ route('role-permission.assign-permission') }}",
                type: "POST",
                data: {
                    role_id: roleId,
                    permission_id: permissionId,
                    is_checked: isChecked,
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    if ($('.permission-checkbox:checked').length !== $(
                            '.permission-checkbox').length) {
                        $('#all-permissions').prop('checked', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error assigning permission:", error);
                }
            });
        });
        $('#all-permissions').on('change', function() {
            const isChecked = $(this).is(':checked');
            const roleId = $('#RoleId').val();
            if (!roleId) {
                alert('Please select a role first.');
                $(this).prop('checked', false);
                return;
            }

            const permissionIds = $('.permission-checkbox').map(function() {
                return $(this).val();
            }).get();

            $('.permission-checkbox').prop('checked', isChecked);
            $.ajax({
                url: "{{ route('role-permission.get-role-permissions') }}",
                type: "POST",
                data: {
                    role_id: roleId,
                    permission_ids: permissionIds,
                    assign_all: isChecked,
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    console.log(response.message);
                },
                error: function(xhr, status, error) {
                    console.error("Error assigning all permissions:", error);
                }
            });
        });
    });
</script>
