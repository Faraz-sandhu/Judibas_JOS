# Shared Judibas settings

Open Administration ? Settings as Judibas Super Admin. SMTP and realtime settings apply to all products. Existing PMS settings and encrypted credentials are preserved; legacy table names are retained for compatibility.

Email credentials are encrypted and never returned to the browser. Blank password/secret inputs preserve saved values. Invitation email enablement applies to PMS and Communication. Sending limits currently count PMS deliveries.

Select Laravel Reverb to use the existing server environment settings, or Pusher Channels to save shared Pusher credentials. Switching to Reverb clears stored Pusher credentials. Reverb still needs its server process running; Cloudflare R2 attachment storage and TURN voice relays remain separate environment settings.

Refresh open tabs after saving realtime settings. Restart existing queue workers once after deploying this change (`php artisan queue:restart`) so they load the new shared settings hook. Future products use the standard Laravel mail and broadcast configuration and `App\Services\PlatformSettings::clientConfig()` for browser connection details.

PMS branding, email and realtime links are removed from its sidebar. Legacy email/realtime endpoints require Judibas Super Admin, regardless of PMS role permissions.
