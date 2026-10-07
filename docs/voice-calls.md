# Browser voice calls

Set COMMUNICATION_CALLS_ENABLED=true in .env, then run php artisan config:clear and reload the browser. Setting false hides call buttons, incoming-call UI and call history, and blocks every calling API. History records remain in the database. New installations default to false; the current development environment has been enabled for testing.

Voice calls are one-to-one between employees with Communication access in an existing personal chat. Open a personal chat and press the phone icon. The receiver gets an incoming call panel while Communication is open. Both can mute/unmute, end the call and minimize it while chatting. The Calls icon shows incoming/outgoing, missed, declined and completed calls with times and durations. Video/group calls and recording are not included. Super Admin review cannot answer or join employee calls.

On a new deployment, run php artisan migrate --force first. Run the existing Reverb server (php artisan reverb:start) and the Laravel scheduler (php artisan schedule:work locally). Production should supervise both services and use HTTPS/WSS; microphone access works on localhost during development. The attachment queue worker does not handle calls.

## Cloudflare TURN for calls across different networks

The app tries a direct WebRTC connection first. Without TURN, calls may work on a shared network but can fail behind restrictive networks. Cloudflare R2 credentials cannot be used for TURN.

Create a TURN key in Cloudflare Realtime > TURN, then add its key ID and API token directly to .env:

```dotenv
COMMUNICATION_CALLS_ENABLED=true
CLOUDFLARE_TURN_KEY_ID=your-turn-key-id
CLOUDFLARE_TURN_API_TOKEN=your-turn-api-token
```

Run php artisan config:clear. The Laravel backend requests expiring two-hour ICE credentials and returns only those credentials to the browser; the long-term API token stays server-side. TURN failure returns a clear error before microphone capture. Credentials are cached per employee for five minutes. Calls longer than two hours may need credential refresh; this release does not refresh them during a call.

Official setup: https://developers.cloudflare.com/realtime/turn/generate-credentials/
Current pricing: https://developers.cloudflare.com/realtime/sfu/platform/pricing/

## Reliability and history

Signaling uses private Reverb user channels; audio is sent by WebRTC, not Reverb or R2. Unanswered calls expire after 60 seconds. Busy users cannot receive a second call. While connected, a 25-second call-only heartbeat detects abandoned sessions; the scheduler closes stale calls after 90 seconds. This heartbeat does not poll chat/sidebar content. Closing or navigating away releases the microphone and attempts to end the call. Refreshing an active call ends that session; opening Communication can recover a still-ringing incoming call.

Employees must keep Communication open to receive calls. Incoming ring sounds depend on browser autoplay permissions; the visual call prompt remains available. Call history stores metadata only, not audio, offers, answers or ICE candidates. If both Reverb/network connections fail, callers see a connection error; late packets cannot revive an ended call.
