<script>
    //declare toastr
    $(document).ready(function() {
        toastr.options = {
            "progressBar": true,
            "closeButton": true,
        }
    });

    // get session message
    $(function() {
        @if ($message = \Illuminate\Support\Facades\Session::get('success'))
            showAlert('{!! $message !!}', 'success');
        @endif

        @if ($error = \Illuminate\Support\Facades\Session::get('error'))
            showAlert('{!! $error !!}', 'error');
        @endif
    });

    // show the message
    function showAlert(message, icon) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-right',
            iconColor: 'white',
            customClass: {
                popup: 'colored-toast'
            },
            showConfirmButton: false,
            timer: 5000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        Toast.fire({
            icon: icon,
            title: message,
        });
    }
</script>
