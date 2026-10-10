@extends('layouts.auth')
@section('content')
    <div>
        <div class="authentication-wrapper authentication-cover authentication-bg">
            <div class="authentication-inner row">
                <div class="d-none d-lg-flex col-lg-7 p-0">
                    <div class="auth-cover-bg auth-cover-bg-color d-flex justify-content-center align-items-center">
                        <img
                            src="{{ $branding->assetUrl('login_cover', 'assets/img/illustrations/auth-login-illustration-light.png') }}"
                            alt="auth-login-cover"
                            class="img-fluid my-5 auth-illustration"
                            data-app-light-img="{{ $branding->assetUrl('login_cover', 'assets/img/illustrations/auth-login-illustration-light.png') }}"
                            data-app-dark-img="{{ $branding->variantUrl('login_cover_dark', 'login_cover', 'assets/img/illustrations/auth-login-illustration-dark.png') }}" />

                        <img
                            src="{{ asset('assets/img/illustrations/bg-shape-image-light.png') }}"
                            alt="auth-login-cover"
                            class="platform-bg"
                            data-app-light-img="{{ asset('assets/img/illustrations/bg-shape-image-light.png') }}"
                            data-app-dark-img="{{ asset('assets/img/illustrations/bg-shape-image-dark.png') }}" />
                    </div>
                </div>

                <div class="d-flex col-12 col-lg-5 align-items-center p-sm-5 p-4">
                    <div class="w-px-400 mx-auto">
                        <!-- Logo -->
                        <div class="app-brand mb-4 mx-auto">
                            <a href="javascript:void(0);" class="app-brand-link gap-2 mx-auto">
                                <span class="app-brand-logo">
                                    <img src="{{ $branding->assetUrl('login_logo', 'assets/img/logo.png') }}" alt="logo" width="250"
                                        data-app-light-img="{{ $branding->assetUrl('login_logo', 'assets/img/logo.png') }}"
                                        data-app-dark-img="{{ $branding->variantUrl('login_logo_dark', 'login_logo', 'assets/img/logo.png') }}" />
                                </span>
                            </a>
                        </div>
                        <!-- /Logo -->
                        <p class="mb-4">Please sign in to your account and start the adventure</p>

                        <form action="{{ route('userLogin') }}" method="POST" class="mb-3">
                            @csrf
                            <div class="mb-3">
                                <label for="email" class="form-label">email</label>
                                <input type="text" class="form-control" id="email"name="email" autofocus />
                                <small class="form-control-feedback text-danger">@error('email') {{ $message }} @enderror</small>
                            </div>
                            <div class="mb-3 form-password-toggle">
                                <label class="form-label" for="password">Password</label>
                                <div class="input-group input-group-merge">
                                    <input type="password" id="password" class="form-control" name="password" aria-describedby="basic-default-password" />
                                </div>
                                <small class="form-control-feedback text-danger">@error('password') {{ $message }} @enderror</small>
                            </div>
                            <button type="submit" class="btn btn-primary d-grid w-100">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
