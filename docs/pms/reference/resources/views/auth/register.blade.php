@extends('layouts.auth')
@section('content')
    <div>
        <div class="authentication-wrapper authentication-cover authentication-bg">
            <div class="authentication-inner row">
                <div class="d-none d-lg-flex col-lg-7 p-0">
                    <div class="auth-cover-bg auth-cover-bg-color d-flex justify-content-center align-items-center">
                        <img
                            src="{{ asset('assets/img/illustrations/auth-login-illustration-light.png') }}"
                            alt="auth-login-cover"
                            class="img-fluid my-5 auth-illustration"
                            data-app-light-img="{{ asset('assets/img/illustrations/auth-login-illustration-light.png') }}"
                            data-app-dark-img="{{ asset('assets/img/illustrations/auth-login-illustration-dark.png') }}" />

                        <img
                            src="{{ asset('assets/img/illustrations/bg-shape-image-light.png') }}"
                            alt="auth-login-cover"
                            class="platform-bg"
                            data-app-light-img="{{ asset('assets/img/illustrations/bg-shape-image-light.png') }}"
                            data-app-dark-img="{{ asset('assets/img/illustrations/bg-shape-image-dark.png') }}" />
                    </div>
                </div>
                <div class="d-flex col-12 col-lg-5 align-items-center p-sm-5 p-4">
                    <div class="w-px-600 mx-auto">
                        <div class="app-brand mb-4">
                            <a href="javascript:void(0);" class="app-brand-link gap-2">
                            <span class="app-brand-logo">
                                <img src="{{ asset('assets/img/logo-text.png') }}" alt="logo" width="350" />
                            </span>
                            </a>
                        </div>
                        <p class="mb-4">Please sign in to your account and start the adventure</p>
                        <form method="POST" action="{{ route('userRegister') }}">
                            @csrf

                            <div class="row mb-3">
                                <label for="name" class="col-md-4 col-form-label text-md-end">{{ __('Name') }}</label>

                                <div class="col-md-6">
                                    <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>

                                    @error('name')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="email" class="col-md-4 col-form-label text-md-end">{{ __('Email Address') }}</label>

                                <div class="col-md-6">
                                    <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">

                                    @error('email')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="password" class="col-md-4 col-form-label text-md-end">{{ __('Password') }}</label>

                                <div class="col-md-6">
                                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">

                                    @error('password')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="password-confirm" class="col-md-4 col-form-label text-md-end">{{ __('Confirm Password') }}</label>

                                <div class="col-md-6">
                                    <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                                </div>
                            </div>

                            <div class="row mb-0">
                                <div class="col-md-6 offset-md-4">
                                    <button type="submit" class="btn btn-primary">
                                        {{ __('Register') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
