<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ str_replace('_', '-', app()->getLocale()) == 'ar' ? 'rtl' : 'ltr' }}" data-theme="theme-default"
    data-assets-path="{{ config('app.url') }}/assets/" data-template="vertical-menu-template"
    class="light-style layout-navbar-fixed layout-menu-fixed">

<head>
    <script>
        (() => {
            const selectedTheme = localStorage.getItem('pms-theme') || 'system';
            const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches;
            const dark = selectedTheme === 'dark' || (selectedTheme === 'system' && prefersDark);
            document.documentElement.classList.toggle('dark-style', dark);
            document.documentElement.classList.toggle('light-style', !dark);
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0d6efd">
    <title>{{ isset($pageTitle) ? $pageTitle . ' :: ' : '' }}PMS</title>
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="icon" href="{{ $branding->assetUrl('favicon', 'assets/img/sm-logo.png') }}">
    <link href="{{ asset('fonts/fa6-pro/css/all.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/tabler-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/flag-icons.css') }}" />
    <link id="pms-core-theme" rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/core.css') }}"
        data-light-href="{{ asset('assets/vendor/css/rtl/core.css') }}"
        data-dark-href="{{ asset('assets/vendor/css/rtl/core-dark.css') }}" class="template-customizer-core-css" />
    <link id="pms-app-theme" rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/theme-default.css') }}"
        data-light-href="{{ asset('assets/vendor/css/rtl/theme-default.css') }}"
        data-dark-href="{{ asset('assets/vendor/css/rtl/theme-default-dark.css') }}"
        class="template-customizer-theme-css" />
    <script>
        (() => {
            const media = window.matchMedia('(prefers-color-scheme: dark)');
            const selected = localStorage.getItem('pms-theme') || 'system';
            const resolveDark = theme => theme === 'dark' || (theme === 'system' && media.matches);

            window.PmsTheme = {
                selected,
                apply(theme, persist = true) {
                    this.selected = theme;
                    if (persist) localStorage.setItem('pms-theme', theme);
                    const dark = resolveDark(theme);
                    document.documentElement.classList.toggle('dark-style', dark);
                    document.documentElement.classList.toggle('light-style', !dark);
                    ['pms-core-theme', 'pms-app-theme'].forEach(id => {
                        const link = document.getElementById(id);
                        if (link) link.href = dark ? link.dataset.darkHref : link.dataset.lightHref;
                    });
                    document.querySelectorAll('[data-app-light-img][data-app-dark-img]').forEach(image => {
                        image.src = dark ? image.dataset.appDarkImg : image.dataset.appLightImg;
                    });
                    window.dispatchEvent(new CustomEvent('pms-theme-changed', {detail:{theme, dark}}));
                }
            };
            window.PmsTheme.apply(selected, false);
            document.addEventListener('DOMContentLoaded', () => window.PmsTheme.apply(window.PmsTheme.selected, false));
            media.addEventListener?.('change', () => {
                if (window.PmsTheme.selected === 'system') window.PmsTheme.apply('system', false);
            });
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/node-waves/node-waves.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/dropzone/dropzone.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs\dropify\dist\js\dropify.min.js') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/bootstrap-datepicker/bootstrap-datepicker.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />

    {{-- <link rel="stylesheet" href="{{asset('assets/vendor/libs/bootstrap-daterangepicker/bootstrap-daterangepicker.css')}}" /> --}}
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
    <link rel="stylesheet"
        href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }} " />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/bootstrap-select/bootstrap-select.css') }}">
    <link rel="stylesheet" href="{{ asset('dist/libs/toastr/toastr.css') }} " />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }} " />
    <script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('assets/js/config.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}" />

    @stack('css-before')
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}?v={{ filemtime(public_path('assets/css/custom.css')) }}" />
    @stack('css-after')
    <style>
        .bg-menu-theme.menu-vertical .menu-item.active>.menu-link:not(.menu-toggle) {
            background: var(--pms-primary-soft) !important;
            box-shadow: none !important;
            color: var(--pms-primary) !important;
        }
        #app-global-loader {
            position: fixed; inset: 0; z-index: 20000; display: none;
            align-items: center; justify-content: center;
            background: color-mix(in srgb, var(--pms-page) 72%, transparent); backdrop-filter: blur(2px);
        }
        #app-global-loader.is-visible { display: flex; }
        .app-global-loader-card {
            display: flex; align-items: center; gap: .75rem; min-width: 180px;
            padding: .9rem 1.15rem; border-radius: .75rem; background: var(--pms-surface);
            color: var(--pms-text); border: 1px solid var(--pms-border); font-weight: 600; box-shadow: var(--pms-shadow-hover);
        }
    </style>
    @if(app()->environment('production'))
        @laravelPWA
    @else
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', async () => {
                    const registrations = await navigator.serviceWorker.getRegistrations();
                    await Promise.all(registrations.map(registration => registration.unregister()));

                    if ('caches' in window) {
                        const cacheNames = await caches.keys();
                        await Promise.all(cacheNames
                            .filter(name => name.startsWith('pwa-'))
                            .map(name => caches.delete(name)));
                    }
                });
            }
        </script>
    @endif

</head>

<body>

    <div id="app-global-loader" role="status" aria-live="polite" aria-hidden="true">
        <div class="app-global-loader-card">
            <span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span>
            <span id="app-global-loader-text">Loading...</span>
        </div>
    </div>

    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <x-asidebar />

            <div class="layout-page">
                <x-header-comp />

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        @yield('content')
                    </div>
                    <x-footer />
                    <div class="content-backdrop fade"></div>
                </div>
            </div>
        </div>
        <div class="layout-overlay layout-menu-toggle"></div>
        <div class="drag-target"></div>
    </div>

    <div class="layout-overlay layout-menu-toggle"></div>
    <div class="drag-target"></div>
    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
    <script>
        (() => {
            const overlay = document.getElementById('app-global-loader');
            const label = document.getElementById('app-global-loader-text');
            let pending = 0;
            let showTimer = null;

            const render = () => {
                const visible = pending > 0;
                overlay.classList.toggle('is-visible', visible);
                overlay.setAttribute('aria-hidden', visible ? 'false' : 'true');
            };
            window.AppLoader = {
                show(message = 'Loading...') {
                    pending++;
                    label.textContent = message;
                    clearTimeout(showTimer);
                    showTimer = setTimeout(render, 100);
                },
                hide() {
                    pending = Math.max(0, pending - 1);
                    clearTimeout(showTimer);
                    if (!pending) render();
                },
                reset() {
                    pending = 0;
                    clearTimeout(showTimer);
                    render();
                }
            };

            document.addEventListener('click', event => {
                const trigger = event.target.closest('[data-bs-toggle="modal"]');
                if (trigger && !trigger.disabled) window.AppLoader.show('Opening...');
            }, true);
            document.addEventListener('shown.bs.modal', () => window.AppLoader.hide());

            const shouldTrackRequest = (input, options = {}) => {
                if (options.appLoader === false) return false;

                const url = typeof input === 'string'
                    ? input
                    : (input?.url || input?.toString?.() || '');

                return !url.includes('/socket.io/') && !url.includes('/check-session');
            };

            const nativeFetch = window.fetch.bind(window);
            window.fetch = (...args) => {
                if (!shouldTrackRequest(args[0], args[1])) return nativeFetch(...args);

                window.AppLoader.show('Please wait...');
                return nativeFetch(...args).finally(() => window.AppLoader.hide());
            };

            const trackedAjaxRequests = new WeakSet();
            $(document).ajaxSend((event, xhr, settings) => {
                if (!shouldTrackRequest(settings.url)) return;
                trackedAjaxRequests.add(xhr);
                window.AppLoader.show('Please wait...');
            });
            $(document).ajaxComplete((event, xhr) => {
                if (!trackedAjaxRequests.has(xhr)) return;
                trackedAjaxRequests.delete(xhr);
                window.AppLoader.hide();
            });

            window.addEventListener('pageshow', () => window.AppLoader.reset());
            window.addEventListener('beforeunload', () => {
                pending = 1;
                label.textContent = 'Loading page...';
                render();
            });
        })();
    </script>
    <script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/node-waves/node-waves.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/bootstrap-datepicker/bootstrap-datepicker.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/menu.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/bootstrap-select/bootstrap-select.js') }}"></script>
    <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/dropzone/dropzone.js') }}"></script>
    <script src="{{ asset('assets/js/ui-toasts.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/chartjs/chartjs.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
    <script>
        let taskEditorLoadPromise = null;
        const loadTaskEditor = () => {
            if (window.tinymce) return Promise.resolve(window.tinymce);
            if (taskEditorLoadPromise) return taskEditorLoadPromise;
            taskEditorLoadPromise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = @json(asset('assets/vendor/libs/tinymce/tinymce.min.js'));
                script.onload = () => resolve(window.tinymce);
                script.onerror = reject;
                document.head.appendChild(script);
            });
            return taskEditorLoadPromise;
        };
        window.TaskDescriptionEditor = {
            async init(root = document) {
                if (!root.querySelector('textarea.task-description-editor')) return;
                await loadTaskEditor();
                root.querySelectorAll('textarea.task-description-editor').forEach(textarea => {
                    if (tinymce.get(textarea.id)) return;
                    if (!textarea.id) textarea.id = `task-description-${Math.random().toString(36).slice(2)}`;
                    const darkEditor = document.documentElement.classList.contains('dark-style');
                    tinymce.init({
                        target: textarea,
                        height: Number(textarea.dataset.editorHeight || 260),
                        content_style: darkEditor
                            ? 'body{background:#171a24;color:#e7e9f2;font-family:Arial,sans-serif}a{color:#8173ff}'
                            : 'body{font-family:Arial,sans-serif}',
                        menubar: false,
                        branding: false,
                        promotion: false,
                        readonly: textarea.readOnly,
                        plugins: 'lists link preview',
                        toolbar: textarea.readOnly ? false : 'undo redo | blocks | bold italic underline | bullist numlist | blockquote | link | removeformat',
                        setup(editor) {
                            editor.on('change input undo redo', () => editor.save());
                        }
                    });
                });
            },
            sync() { window.tinymce?.triggerSave(); },
            set(id, content = '') {
                const editor = window.tinymce?.get(id);
                if (editor) editor.setContent(content || '');
                else if (document.getElementById(id)) document.getElementById(id).value = content || '';
            }
        };
        document.addEventListener('DOMContentLoaded', () => TaskDescriptionEditor.init());
        document.addEventListener('submit', () => TaskDescriptionEditor.sync(), true);
        document.addEventListener('click', event => {
            if (event.target.closest('button[type="submit"], #create-full-issue, #save-task, .submitDescription')) {
                TaskDescriptionEditor.sync();
            }
        }, true);
    </script>
    {{-- <script src="{{ asset('assets/js/createData.js') }}"></script> --}}
    <script src="{{ asset('assets\vendor\libs\parsley-js\parsley.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/js/invitation-sharing.js') }}?v={{ filemtime(public_path('assets/js/invitation-sharing.js')) }}"></script>
    {{-- <script src="{{asset('assets/vendor/libs/bootstrap-daterangepicker/bootstrap-daterangepicker.js')}}"></script> --}}


    @auth
        <script>
            window.laravel_echo_port = '{{ config('app.laravel_echo_port') }}';
            window.Laravel = {
                'csrfToken': '{{ csrf_token() }}',
                'user': '{{ \Illuminate\Support\Facades\Auth::id() }}'
            };


            window.Laravel = {
                userId: {{ auth()->id() ?? 'null' }},
                user: {{ auth()->id() ?? 'null' }} // Add this if needed
            };
        </script>
        <script src="{{ asset('js/current-task-header.js') }}?v={{ filemtime(public_path('js/current-task-header.js')) }}"></script>
        @if(config('broadcasting.default') === 'pusher')
            <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
            <script>
                window.PmsPusherConfig = {
                    key: @json(config('broadcasting.connections.pusher.key')),
                    cluster: @json(config('broadcasting.connections.pusher.options.cluster')),
                    authEndpoint: @json(url('/broadcasting/auth'))
                };
            </script>
            <script src="{{ asset('js/pusher-notifications.js') }}?v={{ filemtime(public_path('js/pusher-notifications.js')) }}"></script>
        @elseif(!in_array(config('broadcasting.default'), ['log', 'null'], true))
            <script src="{{ config('app.url') }}:{{ config('app.laravel_echo_port') }}/socket.io/socket.io.js"></script>
            <script src="{{ asset('js/laravel-echo-setup.js') }}" type="text/javascript"></script>
            <script src="{{ asset('js/socket-script.js') }}?v={{ filemtime(public_path('js/socket-script.js')) }}" type="text/javascript"></script>
        @endif
    @endauth
    @stack('page-js-files')
    @stack('page-scripts')
    @stack('time-tracking-dashboard-scripts')
    @if(!in_array(config('broadcasting.default'), ['log', 'null', 'pusher'], true))
        <script src="{{ asset('js/tracking-socket.js') }}?v={{ filemtime(public_path('js/tracking-socket.js')) }}"></script>
    @endif
    <x-server-message />
</body>

</html>
