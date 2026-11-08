# Private encrypted conversations

## Status and scope

Initial end-to-end encrypted one-to-one conversations, separate from normal Communication chats. Implemented on 6 November 2026. This implementation has automated functional/security checks but has **not had an independent cryptographic or penetration-testing audit**. Obtain that review before relying on it for sensitive financial communications. It is not described as unbreakable or equivalent to Signal.

Only the real Super Admin session (`judibas_admin`) can create a conversation and choose a recipient. HR, MD, Finance, Communication Admin and Judibas Admin roles cannot create one, even by calling the endpoint directly. A chosen recipient can register their own device, verify the other participant, read and reply. Registering a device does not grant authority to initiate a conversation or invite people.

## How to test

1. Super Admin opens Communication and selects the private-chat lock icon in the sidebar. No other role has this sidebar option.
2. Super Admin registers the trusted browser profile if necessary, selects any active Communication employee, and creates the private invitation. The employee need not have a device registered yet.
3. The employee sees a locked private invitation in the regular **Chats** list. Opening it presents device setup inside that conversation. Registration binds the invited employee's device to the pending conversation; messages and calls remain blocked until that binding exists.
4. Both participants now see the same **conversation security code** under the shield icon. Compare all groups in person or over an independently trusted phone call, then select **We compared both codes and every group matches** and **Confirm matching codes**. No physical fingerprint, private key or code typing is required. Do not confirm a code received only through the unverified conversation.
5. Send text, an image/document or a voice note. Voice notes have a microphone/stop/preview/send flow and are encrypted exactly like attachments. The recipient plays the decrypted audio locally. Ordinary chat review, search, normal media endpoints, notification previews and exports do not contain this content.
6. For calls, both participants must have verified keys and the existing Communication calling feature must be enabled. Choose **Private voice call**; the recipient gets an incoming private call, answers, and can mute or end the call. The browser must allow microphone access. Existing TURN configuration is used for connectivity across networks.
7. Review mode hides private invitations and the private sidebar option. Normal employees/HR/MD/Judibas Admin cannot create a private conversation through the UI or API. Invited participants can reply and call within the conversation Super Admin created.
8. Private activity can show a generic **New private message** notice/New indicator without exposing message text, attachment names or previews. Indicators are local to the open browser session.

## Cryptography and keys

- RFC 9180 authenticated HPKE using `@hpke/core` 1.10.0: DHKEM(P-256, HKDF-SHA256), HKDF-SHA256 and AES-256-GCM.
- The browser generates a non-extractable ECDH private `CryptoKey`. Its key pair and UUID persist in IndexedDB under the authenticated principal. Only the public key and device UUID are registered with Laravel. The server does not receive the private key.
- Each message is encrypted separately for each of the two devices using a fresh HPKE sender context. Auth mode binds it to the sending device's key.
- Both HPKE `info` and associated authenticated data bind the protocol/version, algorithm suite, thread UUID, message UUID, sender device UUID and recipient device UUID. This prevents accepting a ciphertext under substituted thread/message metadata.
- The comparison screen shows a symmetric SHA-256 hash of the versioned conversation UUID and both sorted device fingerprints, so both participants see the same full-strength 256-bit code. Confirmation pins the peer fingerprint locally; no automatic first-use trust or server-side decryption key is introduced. Previously verified device pins remain valid.
- Device fingerprints include the principal, device UUID and public key, hashed with SHA-256. Verified peer fingerprints are pinned in the local browser profile. Changed device keys block reading/sending until independently verified again.
- Database message UUID uniqueness and idempotent matching payload checks prevent a normal HTTP retry from inserting the same message twice.
- Attachments, filenames and MIME types are inside the encrypted payload. Decrypted attachment blob URLs are local to the participant's browser and revoked when replaced or the component closes. Only PNG/JPEG/WebP are rendered as images; other files are downloads.

## Storage, transport and authorization

- Tables: `communication_e2ee_devices`, `communication_e2ee_threads`, `communication_e2ee_messages`, `communication_e2ee_signals`.
- Endpoints: `/communication/api/encrypted`, `/devices`, `/threads`, `/threads/{uuid}/messages`, `/threads/{uuid}/signals` and `/threads/{uuid}/signals/{signal_uuid}`.
- No plaintext fallback, server-side decryption key, plaintext attachment upload, automatic conversion of regular chats, normal-chat forwarding, or normal-chat search indexing.
- Reverb/Pusher sends only the changed thread UUID and, for a call, its encrypted signal UUID on the actual Super Admin and selected recipient's private channels. Other roles cannot subscribe to those identities. Reconnect triggers resynchronization; there is no periodic polling fallback.
- Existing application CSRF/session authorization remains required. Role/participant checks run on each request. Encrypted API responses use `private, no-store`.
- These endpoints are intentionally separate from normal message/media/review APIs. Ciphertext cannot enter the existing review screen through an attachment URL.
- Users with encrypted history cannot be hard-deleted through Administration; deactivate them or remove Communication access instead. Revocation denies future encrypted API access; it cannot remove content a recipient already read/copied.

## Private voice calls

- Audio is participant-to-participant WebRTC DTLS-SRTP; no media server terminates/decrypts the stream. TURN relays encrypted packets where required.
- SDP offers/answers, including DTLS certificate fingerprints and ICE addresses/credentials, travel inside authenticated HPKE envelopes to the two verified devices. This is separate from normal-call signaling and normal call-history tables.
- Call envelopes use a separate `signal` protocol domain in HPKE context/AAD. A normal message cannot be accepted as a call signal, or vice versa.
- Signal rows have 90-second expiry, participant-only retrieval, ciphertext-only validation and bounded size. Expired signals are inaccessible and are purged when another signal is posted. There is no scheduled background purge yet.
- Incoming signals are deduplicated in the current browser session and validated for freshness, call ID and type. Accepting a call requires a verified peer key. Changing device trust terminates the private-call component and releases its media resources.
- Calls provide answer/decline, mute/unmute and hangup; microphone tracks and peer connections are released on hangup, errors, expiry or component teardown. Private and normal calls are guarded against simultaneous microphone use.
- Calls require both participants to have Communication open, working private-channel realtime, microphone permission, HTTPS (localhost for tests), and a network path using STUN/TURN. Browser tests verified local peer audio and DTLS state; production connectivity across specific firewalls/NATs still depends on the deployment's TURN configuration.
- There is no server-side plaintext recording or normal-history copy of private-call signaling. The service still observes encrypted traffic metadata and cannot stop a recipient recording their own audio.

## Explicit limitations

- One registered browser profile/device per account. Multi-device approval, key recovery, rotation and encrypted backup are not implemented. New browsers cannot overwrite an existing registered key. Do not delete the original browser profile or clear its site storage.
- Maximum attachment size: 1 MiB. Message text: 10,000 characters. Messages load in pages of 25. Recorded voice notes stop at three minutes; attachments remain limited to 1 MiB.
- HPKE does **not** provide forward secrecy against later compromise of a recipient's long-term private key. This is not a Double Ratchet implementation.
- Account/device compromise, XSS, browser extensions, shared browser profiles, malicious JavaScript delivered by a compromised host, screenshots and recipient copying are outside the protection supplied by message encryption. HTTPS and application security remain necessary.
- Participant identities, device public keys, conversation membership, timestamps and ciphertext sizes are visible to the service. Message contents and attachment filenames are encrypted.
- No E2EE group chats, server-side search, automatic finance-content detection, editing, forwarding or administrative recovery in this initial implementation.
- Existing normal chat messages remain normal messages; the lock area is a separate conversation.

## Verification

- `php artisan test --filter=CommunicationEncrypted`: Super-Admin-only creation, participant-only access, immutable device registration, rejection of private-key/plaintext fields, product revocation, idempotency and encrypted-history deletion protection.
- `scripts/check-encrypted-chats.mjs`: separate browser profiles, actual HPKE text/image/voice-note round trips, real microphone/MediaRecorder, actual WebRTC DTLS connection and inbound encrypted audio packets, mute/hangup cleanup, key verification/non-extractability, tamper rejection and mobile bounds. HTTP/realtime transport is mocked; cryptography and media connections are real.
- `scripts/check-encrypted-navigation.mjs`: actual built Communication workspace verifies Super-Admin-only sidebar access, pending invitations in regular employee Chats, inline device setup, manager restrictions, review isolation and mobile bounds using mocked APIs.
- The browser harness is under ignored `.preview/` and uses a separate localhost-only Vite server on 5177 (started and stopped by the browser-check script). It does not enroll real users or alter real encrypted records.

WebRTC security reference: https://www.rfc-editor.org/rfc/rfc8827.html

References: https://www.rfc-editor.org/rfc/rfc9180.html ; https://github.com/dajiaji/hpke-js ; https://cheatsheetseries.owasp.org/cheatsheets/Web_Frontend_Security_Cheat_Sheet.html

## Chat interface update

Private conversations now reuse the normal Communication layout and components: conversation list, chat header, message bubbles/timestamps, attachment preview, voice player, microphone recorder, emoji picker and Enter/Shift+Enter composer. The shield opens the one-time conversation-code comparison. The recipient can also select regular conversations from the same left-hand list. No encryption or participant permissions were removed for the design update.
