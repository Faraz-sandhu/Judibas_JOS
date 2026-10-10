@extends('layouts.auth')

@section('content')
<div class="authentication-wrapper authentication-basic px-4">
    <div class="authentication-inner py-5" style="max-width:520px">
        <div class="card shadow-lg border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <img src="{{ $branding->assetUrl('login_logo', 'assets/img/logo.png') }}" alt="Logo" style="max-width:210px;max-height:70px">
                </div>
                @if($invitation->isEmployeeOnboarding())
                    <span class="badge bg-label-primary mb-3"><i class="ti ti-user-plus me-1"></i>Employee invitation</span>
                    <h3 class="mb-2">Join the team</h3>
                    <p class="text-muted mb-4">
                        {{ $invitation->sender->name }} invited <strong>{{ $invitation->invitee_email }}</strong>
                        to join as <strong>{{ $invitation->assignedRole?->role_name }}</strong>@if($invitation->department), in {{ $invitation->department->dept_name }}@endif.
                        Choose your own password to activate the account.
                    </p>
                @else
                    <span class="badge bg-label-primary mb-3"><i class="ti ti-mail-opened me-1"></i>Task invitation</span>
                    <h3 class="mb-2">Join {{ $invitation->invitable->project->name }}</h3>
                    <p class="text-muted mb-4">
                        {{ $invitation->sender->name }} invited <strong>{{ $invitation->invitee_email }}</strong>
                        to work on "{{ $invitation->invitable->title }}". Create your secure login to accept it.
                    </p>
                @endif
                <form method="POST" action="{{ route('invitations.join.complete', $invitation->token) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="invite-name">Your name</label>
                        <input id="invite-name" name="name" class="form-control" value="{{ old('name', $invitation->invitee_name) }}" required autofocus>
                        @error('name')<small class="text-danger">{{ $message }}</small>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="invite-password">Password</label>
                        <input id="invite-password" type="password" name="password" class="form-control" required autocomplete="new-password">
                        <small class="text-muted">At least 8 characters, including letters and numbers.</small>
                        @error('password')<div><small class="text-danger">{{ $message }}</small></div>@enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="invite-password-confirmation">Confirm password</label>
                        <input id="invite-password-confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                    </div>
                    @error('email')<div class="alert alert-danger">{{ $message }}</div>@enderror
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="ti ti-user-check me-1"></i>{{ $invitation->isEmployeeOnboarding() ? 'Create employee account' : 'Accept and join task' }}
                    </button>
                </form>
                <p class="text-muted small text-center mt-4 mb-0">This invitation expires {{ $invitation->expires_at->diffForHumans() }}.</p>
            </div>
        </div>
    </div>
</div>
@endsection
