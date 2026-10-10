@extends('layouts.app')

@section('content')
<div class="mail-settings-page">
    <div class="mail-settings-hero">
        <div><span>Email delivery</span><h2>SMTP settings</h2><p>Configure the email server used for task invitations and application notifications.</p></div>
        <div class="mail-security"><i class="ti ti-lock"></i><div><strong>Encrypted credentials</strong><small>The SMTP password is encrypted at rest.</small></div></div>
    </div>

    @if(session('success'))<div class="alert alert-success"><i class="ti ti-circle-check me-2"></i>{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger"><i class="ti ti-alert-circle me-2"></i>{{ session('error') }}</div>@endif

    <div class="mail-settings-grid">
        <form method="POST" action="{{ route('settings.mail.update') }}" class="mail-panel">
            @csrf @method('PUT')
            <div class="mail-panel-heading"><i class="ti ti-server"></i><div><h4>Mail server</h4><p>Credentials provided by your email hosting provider.</p></div></div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="mail-switch h-100">
                        <span><strong>Notification emails</strong><small>Assignment and task activity emails.</small></span>
                        <span class="form-check form-switch mb-0"><input type="hidden" name="notification_emails_enabled" value="0"><input class="form-check-input" type="checkbox" name="notification_emails_enabled" value="1" @checked(old('notification_emails_enabled', $settings->notification_emails_enabled))></span>
                    </label>
                </div>
                <div class="col-md-6">
                    <label class="mail-switch h-100">
                        <span><strong>Invitation emails</strong><small>Send secure invitation links by email.</small></span>
                        <span class="form-check form-switch mb-0"><input type="hidden" name="invitation_emails_enabled" value="0"><input class="form-check-input" type="checkbox" name="invitation_emails_enabled" value="1" @checked(old('invitation_emails_enabled', $settings->invitation_emails_enabled))></span>
                    </label>
                </div>
                <div class="col-md-6"><label class="form-label">Daily sending limit</label><input class="form-control" type="number" min="0" name="daily_limit" value="{{ old('daily_limit', $settings->daily_limit ?? 300) }}"><small class="text-muted">Use 0 for no application limit.</small></div>
                <div class="col-md-6"><label class="form-label">Warning threshold</label><input class="form-control" type="number" min="0" name="warning_threshold" value="{{ old('warning_threshold', $settings->warning_threshold ?? 270) }}"><small class="text-muted">Show a warning when usage reaches this number.</small>@error('warning_threshold')<div class="text-danger small">{{ $message }}</div>@enderror</div>
            </div>

            <div class="row g-3">
                <div class="col-md-8"><label class="form-label">SMTP host <span class="text-danger">*</span></label><input class="form-control" name="host" value="{{ old('host', $settings->host) }}" placeholder="smtp.example.com">@error('host')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-md-4"><label class="form-label">Port <span class="text-danger">*</span></label><input class="form-control" type="number" name="port" value="{{ old('port', $settings->port ?: 587) }}">@error('port')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-md-6"><label class="form-label">Encryption</label><select class="form-select" name="scheme"><option value="smtp" @selected(old('scheme', $settings->scheme ?: 'smtp')==='smtp')>TLS / STARTTLS</option><option value="smtps" @selected(old('scheme', $settings->scheme)==='smtps')>SSL</option></select></div>
                <div class="col-md-6"><label class="form-label">Username</label><input class="form-control" name="username" value="{{ old('username', $settings->username) }}" autocomplete="off" placeholder="mailbox@example.com"></div>
                <div class="col-12"><label class="form-label">Password <span class="text-danger">*</span></label><div class="input-group"><input id="smtp-password" class="form-control" type="password" name="password" autocomplete="new-password" placeholder="{{ $settings->password ? 'Saved — leave blank to keep it' : 'SMTP password' }}"><button class="btn btn-outline-secondary" type="button" onclick="const i=document.getElementById('smtp-password');i.type=i.type==='password'?'text':'password'"><i class="ti ti-eye"></i></button></div><small class="text-muted">For security, the saved password is never displayed.</small>@error('password')<div><small class="text-danger">{{ $message }}</small></div>@enderror</div>
            </div>

            <div class="mail-panel-heading mt-4"><i class="ti ti-send"></i><div><h4>Sender identity</h4><p>The name and address recipients see in their inbox.</p></div></div>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">From address <span class="text-danger">*</span></label><input class="form-control" type="email" name="from_address" value="{{ old('from_address', $settings->from_address) }}" placeholder="no-reply@example.com">@error('from_address')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-md-6"><label class="form-label">From name <span class="text-danger">*</span></label><input class="form-control" name="from_name" value="{{ old('from_name', $settings->from_name) }}" placeholder="{{ config('app.name') }}">@error('from_name')<small class="text-danger">{{ $message }}</small>@enderror</div>
            </div>
            <div class="d-flex justify-content-end mt-4"><button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save email settings</button></div>
        </form>

        <aside class="mail-panel mail-test-panel">
            <div class="mail-panel-heading"><i class="fa-solid fa-envelope-circle-check"></i><div><h4>Test delivery</h4><p>Send a real message using the saved configuration.</p></div></div>
            <div class="mail-status {{ $settings->host ? 'is-enabled' : '' }}"><span></span><div><strong>{{ $settings->host ? 'SMTP configured' : 'SMTP setup required' }}</strong><small>{{ $settings->host ? $settings->host.':'.$settings->port : 'Complete and save the mail server fields.' }}</small></div></div>
            <div class="mail-usage mb-4">
                <div class="d-flex justify-content-between"><strong>Today's usage</strong><span>{{ $usageToday }} / {{ $settings->daily_limit ?: 'Unlimited' }}</span></div>
                @php($percent = $settings->daily_limit ? min(100, round(($usageToday / $settings->daily_limit) * 100)) : 0)
                <div class="progress mt-2" style="height:8px"><div class="progress-bar {{ $usageToday >= ($settings->warning_threshold ?? 270) ? 'bg-warning' : '' }}" style="width:{{ $percent }}%"></div></div>
                <small class="text-muted d-block mt-2">{{ $availability['message'] }}</small>
            </div>
            <form method="POST" action="{{ route('settings.mail.test') }}">
                @csrf
                <label class="form-label">Send test to</label>
                <input class="form-control mb-3" type="email" name="test_email" value="{{ old('test_email', auth()->user()->email) }}" required>
                @error('test_email')<small class="text-danger d-block mb-2">{{ $message }}</small>@enderror
                <button class="btn btn-outline-primary w-100" @disabled(!$settings->host)><i class="ti ti-send me-1"></i>Send test email</button>
            </form>
            <div class="mail-help"><i class="ti ti-info-circle"></i><p><strong>Common settings</strong><br>TLS normally uses port 587. SSL normally uses port 465. Your provider may require the From address to match the authenticated mailbox.</p></div>
        </aside>
    </div>
</div>
@endsection

@push('css-after')
<style>
.mail-settings-page{padding:1.5rem 0 2.5rem}.mail-settings-hero{display:flex;align-items:center;justify-content:space-between;gap:2rem;margin-bottom:1.25rem;padding:1.6rem 1.75rem;border:1px solid var(--pms-border);border-radius:1rem;background:linear-gradient(135deg,var(--pms-surface),color-mix(in srgb,var(--pms-primary) 10%,var(--pms-surface)));box-shadow:var(--pms-shadow)}.mail-settings-hero>div:first-child>span{color:var(--pms-primary);font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.mail-settings-hero h2{margin:.3rem 0;font-size:1.65rem}.mail-settings-hero p,.mail-panel-heading p{margin:0;color:var(--pms-muted)}.mail-security{display:flex;align-items:center;gap:.75rem;padding:.8rem 1rem;border:1px solid var(--pms-border);border-radius:.8rem;background:var(--pms-surface)}.mail-security>i,.mail-panel-heading>i{display:grid;place-items:center;width:40px;height:40px;border-radius:.7rem;background:var(--pms-primary-soft);color:var(--pms-primary);font-size:1.2rem}.mail-security div{display:flex;flex-direction:column}.mail-security small{color:var(--pms-muted)}.mail-settings-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(290px,1fr);gap:1.25rem;align-items:start}.mail-panel{padding:1.35rem;border:1px solid var(--pms-border);border-radius:1rem;background:var(--pms-surface);box-shadow:var(--pms-shadow)}.mail-panel-heading{display:flex;align-items:center;gap:.8rem;margin-bottom:1.2rem;padding-bottom:1rem;border-bottom:1px solid var(--pms-border)}.mail-panel-heading h4{margin:0 0 .15rem;font-size:1.05rem}.mail-panel-heading p{font-size:.8rem}.mail-switch{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.9rem 1rem;border-radius:.75rem;background:var(--pms-page)}.mail-switch>span:first-child{display:flex;flex-direction:column}.mail-switch small,.mail-status small{color:var(--pms-muted)}.mail-status{display:flex;align-items:center;gap:.7rem;margin-bottom:1.25rem;padding:.85rem;border:1px solid var(--pms-border);border-radius:.75rem}.mail-status>span{width:10px;height:10px;border-radius:50%;background:var(--bs-secondary)}.mail-status.is-enabled>span{background:var(--bs-success);box-shadow:0 0 0 5px color-mix(in srgb,var(--bs-success) 15%,transparent)}.mail-status>div{display:flex;flex-direction:column}.mail-help{display:flex;gap:.6rem;margin-top:1.5rem;padding:1rem;border-radius:.75rem;background:var(--pms-primary-soft);color:var(--pms-muted)}.mail-help i{color:var(--pms-primary);font-size:1.1rem}.mail-help p{margin:0;font-size:.78rem}@media(max-width:991.98px){.mail-settings-grid{grid-template-columns:1fr}.mail-security{display:none}}@media(max-width:575.98px){.mail-settings-hero{padding:1.2rem}}
</style>
@endpush
