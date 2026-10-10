<script>
    $(document).ready(function() {
        $(".selectpicker").selectpicker();
        function fetchSummary(url, data) {
            $.ajax({
                url: url,
                type: "GET",
                data: data,
                beforeSend: function() {
                    $("#summaryContainer").html("<p class='text-center'>Loading...</p>");
                },
                success: function(response) {
                    if (response.success) {
                        $("#summaryContainer").html(response.html);
                        toastr.success(response.message);
                    } else {
                        $("#summaryContainer").html("<p class='text-center text-danger'>No results found.</p>");
                        toastr.error('No results found.');
                    }
                },
                error: function(xhr) {
                    $("#summaryContainer").html("<p class='text-center text-danger'>No results found.</p>");
                    toastr.error('No results found.');
                }
            });
        }
        // Filter by User
        $("#filterUser").on("change", function() {
            let selectedUser = $(this).val();
            fetchSummary("{{ route('summary.user.filter') }}", {
                user_id: selectedUser
            });
        });
        // Filter by Date
        $("#filterDate").on("change", function() {
            let selectedDate = $(this).val();
            fetchSummary("{{ route('summary.date.filter') }}", {
                date: selectedDate
            });
        });
    });
</script>
