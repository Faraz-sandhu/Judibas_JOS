<html
    lang="en"
    dir="ltr"
    data-theme="theme-default"
    data-assets-path="{{ config('app.url') }}/assets/"
    data-template="horizontal-menu-template"
    class="light-style customizer-hide"
>
<head>
    <script>
        (() => {
            const selected = localStorage.getItem('pms-theme') || 'system';
            const dark = selected === 'dark' || (selected === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark-style', dark);
            document.documentElement.classList.toggle('light-style', !dark);
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>PMS</title>

    <link rel="icon" href="{{ $branding->assetUrl('favicon', 'assets/img/sm-logo.png') }}">

    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/tabler-icons.css') }}" />

    <link id="pms-auth-core-theme" rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/core.css') }}"
        data-light-href="{{ asset('assets/vendor/css/rtl/core.css') }}"
        data-dark-href="{{ asset('assets/vendor/css/rtl/core-dark.css') }}" />
    <link id="pms-auth-app-theme" rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/theme-default.css') }}"
        data-light-href="{{ asset('assets/vendor/css/rtl/theme-default.css') }}"
        data-dark-href="{{ asset('assets/vendor/css/rtl/theme-default-dark.css') }}" />
    <script>
        (() => {
            const media = window.matchMedia('(prefers-color-scheme: dark)');
            const applyTheme = () => {
                const selected = localStorage.getItem('pms-theme') || 'system';
                const dark = selected === 'dark' || (selected === 'system' && media.matches);
                document.documentElement.classList.toggle('dark-style', dark);
                document.documentElement.classList.toggle('light-style', !dark);
                ['pms-auth-core-theme', 'pms-auth-app-theme'].forEach(id => {
                    const link = document.getElementById(id);
                    if (link) link.href = dark ? link.dataset.darkHref : link.dataset.lightHref;
                });
                document.querySelectorAll('[data-app-light-img][data-app-dark-img]').forEach(image => {
                    image.src = dark ? image.dataset.appDarkImg : image.dataset.appLightImg;
                });
            };
            applyTheme();
            document.addEventListener('DOMContentLoaded', applyTheme);
            media.addEventListener?.('change', applyTheme);
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />

    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/node-waves/node-waves.css') }}" />

    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}" />

    <script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('assets/js/config.js') }}"></script>

    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/node-waves/node-waves.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/menu.js') }}"></script>

    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}?v={{ filemtime(public_path('assets/css/custom.css')) }}" />
</head>
<body>
    @yield('content')
</body>
</html>
