@extends('layouts.app')

@section('content')
<div class="realtime-settings-page">
    <div class="realtime-hero">
        <div><span>Live workspace</span><h2>Realtime / Pusher settings</h2><p>Manage instant notifications and live board updates without server access.</p></div>
        <div class="realtime-security"><i class="ti ti-shield-lock"></i><div><strong>Encrypted secret</strong><small>The App Secret is encrypted and never returned to the browser.</small></div></div>
    </div>

    @if(session('success'))<div class="alert alert-success"><i class="ti ti-circle-check me-2"></i>{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger"><i class="ti ti-alert-circle me-2"></i>{{ session('error') }}</div>@endif

    <div class="realtime-grid">
        <form method="POST" action="{{ route('settings.realtime.update') }}" class="realtime-panel">
            @csrf @method('PUT')
            <div class="realtime-panel-heading"><i class="ti ti-broadcast"></i><div><h4>Pusher Channels</h4><p>Blank credential fields continue using the server’s <code>.env</code> values.</p></div></div>

            <label class="realtime-switch mb-4">
                <span><strong>Enable realtime updates</strong><small>Deliver notifications and board changes instantly.</small></span>
                <span class="form-check form-switch mb-0"><input type="hidden" name="enabled" value="0"><input class="form-check-input" type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings->enabled))></span>
            </label>

            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">App ID</label><input class="form-control" name="app_id" value="{{ old('app_id', $settings->app_id) }}" placeholder="Uses .env when blank" autocomplete="off">@error('app_id')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-md-6"><label class="form-label">App Key</label><input class="form-control" name="app_key" value="{{ old('app_key', $settings->app_key) }}" placeholder="Uses .env when blank" autocomplete="off">@error('app_key')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-md-6"><label class="form-label">Cluster</label><input class="form-control" name="cluster" value="{{ old('cluster', $settings->cluster) }}" placeholder="For example: ap2" autocomplete="off">@error('cluster')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="col-md-6"><label class="form-label">App Secret</label><div class="input-group"><input id="pusher-secret" class="form-control" type="password" name="app_secret" autocomplete="new-password" placeholder="{{ $settings->app_secret ? 'Saved — leave blank to keep it' : 'Uses .env when blank' }}"><button class="btn btn-outline-secondary" type="button" onclick="const input=document.getElementById('pusher-secret');input.type=input.type==='password'?'text':'password'"><i class="ti ti-eye"></i></button></div><small class="text-muted">The saved value is never displayed.</small>@error('app_secret')<div><small class="text-danger">{{ $message }}</small></div>@enderror</div>
            </div>

            <div class="d-flex justify-content-end mt-4"><button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save realtime settings</button></div>
        </form>

        <aside class="realtime-panel">
            <div class="realtime-panel-heading"><i class="ti ti-plug-connected"></i><div><h4>Connection test</h4><p>Send a private live event to your account.</p></div></div>
            <div class="realtime-status {{ $settings->enabled && $settings->isComplete() ? 'is-enabled' : '' }}"><span></span><div><strong>{{ $settings->enabled ? ($settings->isComplete() ? 'Ready to connect' : 'Credentials required') : 'Realtime disabled' }}</strong><small>{{ $settings->effectiveCluster() ? 'Cluster: '.$settings->effectiveCluster() : 'No cluster configured' }}</small></div></div>
            <form method="POST" action="{{ route('settings.realtime.test') }}">@csrf<button class="btn btn-outline-primary w-100" @disabled(!$settings->enabled || !$settings->isComplete())><i class="ti ti-bolt me-1"></i>Send live test</button></form>
            <div class="realtime-help"><i class="ti ti-info-circle"></i><p>After changing credentials, refresh open browser tabs. If Pusher is unavailable, database notifications remain available after refresh.</p></div>
        </aside>
    </div>
</div>
@endsection

@push('css-after')
<style>
.realtime-settings-page{padding:1.5rem 0 2.5rem}.realtime-hero{display:flex;align-items:center;justify-content:space-between;gap:2rem;margin-bottom:1.25rem;padding:1.6rem 1.75rem;border:1px solid var(--pms-border);border-radius:1rem;background:linear-gradient(135deg,var(--pms-surface),color-mix(in srgb,var(--pms-primary) 10%,var(--pms-surface)));box-shadow:var(--pms-shadow)}.realtime-hero>div:first-child>span{color:var(--pms-primary);font-size:.72rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.realtime-hero h2{margin:.3rem 0;font-size:1.65rem}.realtime-hero p,.realtime-panel-heading p{margin:0;color:var(--pms-muted)}.realtime-security{display:flex;align-items:center;gap:.75rem;padding:.8rem 1rem;border:1px solid var(--pms-border);border-radius:.8rem;background:var(--pms-surface)}.realtime-security>i,.realtime-panel-heading>i{display:grid;place-items:center;width:40px;height:40px;border-radius:.7rem;background:var(--pms-primary-soft);color:var(--pms-primary);font-size:1.2rem}.realtime-security div{display:flex;flex-direction:column}.realtime-security small{color:var(--pms-muted)}.realtime-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(290px,1fr);gap:1.25rem;align-items:start}.realtime-panel{padding:1.35rem;border:1px solid var(--pms-border);border-radius:1rem;background:var(--pms-surface);box-shadow:var(--pms-shadow)}.realtime-panel-heading{display:flex;align-items:center;gap:.8rem;margin-bottom:1.2rem;padding-bottom:1rem;border-bottom:1px solid var(--pms-border)}.realtime-panel-heading h4{margin:0 0 .15rem;font-size:1.05rem}.realtime-panel-heading p{font-size:.8rem}.realtime-switch{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.9rem 1rem;border-radius:.75rem;background:var(--pms-page)}.realtime-switch>span:first-child,.realtime-status>div{display:flex;flex-direction:column}.realtime-switch small,.realtime-status small{color:var(--pms-muted)}.realtime-status{display:flex;align-items:center;gap:.7rem;margin-bottom:1.25rem;padding:.85rem;border:1px solid var(--pms-border);border-radius:.75rem}.realtime-status>span{width:10px;height:10px;border-radius:50%;background:var(--bs-secondary)}.realtime-status.is-enabled>span{background:var(--bs-success);box-shadow:0 0 0 5px color-mix(in srgb,var(--bs-success) 15%,transparent)}.realtime-help{display:flex;gap:.6rem;margin-top:1.5rem;padding:1rem;border-radius:.75rem;background:var(--pms-primary-soft);color:var(--pms-muted)}.realtime-help i{color:var(--pms-primary);font-size:1.1rem}.realtime-help p{margin:0;font-size:.78rem}@media(max-width:991.98px){.realtime-grid{grid-template-columns:1fr}.realtime-security{display:none}}@media(max-width:575.98px){.realtime-hero{padding:1.2rem}}
</style>
@endpush
