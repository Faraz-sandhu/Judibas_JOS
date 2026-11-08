# Protection inside normal Communication chats

## Use

1. Super Admin opens **My chats**, selects a direct conversation, and enables message protection. The browser generates the chat key and a separate recovery key. **Download the recovery key and keep it offline.** It is not the recipient's key.
2. Communication registers the employee browser's public delivery key automatically. Its non-extractable private key remains in that browser's IndexedDB. When the recipient is registered, Super Admin's unlocked browser wraps and delivers the chat key. The server never receives the original chat key.
3. When the recipient opens the chat, a compact **Save your access key** dialog displays the locally decrypted chat key with Copy/Download actions. Closing the dialog acknowledges delivery and removes its wrapped envelope from subsequent API responses. The modal does not automatically reappear for that delivery. The unlocked key remains in page memory until locking, navigation, or reloading.
4. If the recipient has not opened Communication before, their public key is not yet available. Opening the protected chat registers it and creates a pending access request. Super Admin sees **Key access requested** in the personal chat list. An unlocked admin browser can make the first delivery automatically; otherwise the admin must open/unlock the chat and approve delivery.
5. A recipient who loses the saved key selects **Request access again**. Super Admin sees a persistent pending request and must approve redelivery. If Super Admin has also forgotten the chat key, they unlock with **Use recovery key** and their downloaded recovery key, then approve. Only the chat key is delivered to the recipient; the recovery key stays with Super Admin.
6. Reloading or locking clears page-memory chat keys. Enter a saved chat key to unlock again. Clearing the recipient's browser storage creates a different delivery key and requires Super Admin's approval before that browser receives existing chat access.
7. Existing protected conversations are preserved. Super Admin must unlock one with the original chat key and choose **Create recovery backup** to enroll recovery. Already-lost keys cannot be recovered retrospectively.

## Key delivery and recovery implementation

- RSA-OAEP with 3072-bit recipient public keys and SHA-256 wraps the random 32-byte chat key in the admin browser. The OAEP label binds protocol, conversation, chat-key ID, recipient device ID and unique delivery ID. A different browser or altered context cannot unwrap the same envelope.
- Registration accepts public JWK fields only. Existing registered keys cannot be replaced. Only active Communication members of the direct conversation are delivery targets, and only the actual Super Admin in personal mode can approve delivery.
- Initial delivery is allowed once. A later delivery requires a pending recipient access request. Requests are deduplicated, rate limited, stored persistently, and advertised using existing private WebSocket workspace events. No polling was added.
- Only the authenticated recipient can retrieve a delivery for their own registered browser. Review mode cannot access key-delivery or recovery APIs. Closing the recipient dialog clears the server's current delivery ciphertext; acknowledgement is idempotent.
- Recovery uses a separate random AES-256-GCM key to wrap the raw chat key with a fresh IV and conversation/key-specific authenticated data. Only the wrapped backup is stored on the server; the plaintext recovery key is downloaded by Super Admin. Recovery data is returned exclusively in the Super Admin's own chat context.
- The public-key directory is authenticated by the application and HTTPS. This implementation does not claim protection against an actively malicious application server substituting browser code or public keys. Browser compromise, access-key sharing, and recovery-key compromise are separate risks; no device fingerprint or user-visible comparison-code workflow is added here.
- One-time display means the application no longer returns a consumed delivery. It does not prevent an authorized recipient from retaining copied keys, screenshots, browser debugging data or their offline download.

## Scope and storage

- Only the actual Super Admin session can enable protection or change the future-message policy, and only in Super Admin's own direct conversation. Review mode is read-only.
- Normal timeline, realtime delivery, reply, pin, reactions, deletion and calls continue through their existing paths. Protected content cannot be forwarded or edited through plaintext endpoints. Download/open uses the encrypted-message component.
- AES-256-GCM is performed through browser Web Crypto, using a fresh random 96-bit IV and a 128-bit authentication tag for each message and each file. Additional authenticated data includes protocol version, conversation ID, unique message UUID and content purpose. UUID uniqueness prevents replay into a second message.
- The encrypted message contains its text and attachment filename/MIME metadata. Files are encrypted separately before upload. Database bodies, previews and pinned summaries contain only `Protected message`; original text and files are never stored as a second plaintext field.
- The server stores the ciphertext, key identifier and encrypted key-check envelope. The plaintext key is never transmitted to it. File uploads contain only binary ciphertext, are kept in private attachment storage, and use authenticated downloads with no-store headers. Existing R2 mirroring stores ciphertext as well.
- Conversation reviewers see grouped ciphertext in the locked message card. They cannot use protected attachment download endpoints. Shared-media/link searches exclude protected content. Search cannot search the encrypted original text.
- Future protection is checked again before sending in the client and enforced on normal sends/forwarding by the server. Policy changes use existing WebSocket workspace events; no polling was added.
- Existing separate encrypted conversations are hidden from normal navigation. Their previous history remains accessible explicitly at `/communication?legacy_history=1&section=Encrypted`. Their stored ciphertext and device keys were not migrated or deleted; new creation controls are hidden in the normal workspace.
- Voice notes and images follow the encrypted-file path. Live calls retain the existing WebRTC transport; this change does not replace their call protocol or add access-key gating to live calls.
- Requires HTTPS or localhost for Web Crypto. No device fingerprint restrictions, server-readable key escrow, key rotation or retrospective encryption are included in this update.

## Verification

- `php artisan test --compact --filter=CommunicationProtectionTest`: authorization, key replacement prevention, ciphertext-only persistence, review masking, plaintext/forward/edit rejection, malformed envelopes, file permissions and shared-media exclusion.
- `node scripts/check-chat-protection.mjs`: real browser cryptography, correct/incorrect key, context tampering, unique IVs, encrypted-file roundtrip, review masking despite a local key, lock cleanup, future-message protection.
- `php artisan test --compact --filter=CommunicationKeyAccessTest` checks authorization, registration, delivery, acknowledgement, recovery and request notifications.
- `node scripts/check-key-delivery.mjs` exercises browser public-key delivery, one-time dialogs, recovery, redelivery and new-browser isolation.
- Full Laravel suite and TypeScript/production build provide broader regression coverage; results are reported in the implementation response.

## Cryptographic references

- [W3C Web Cryptography API: RSA-OAEP](https://www.w3.org/TR/webcrypto/#rsa-oaep)
- [MDN Web Crypto key wrapping](https://developer.mozilla.org/en-US/docs/Web/API/SubtleCrypto/wrapKey)
- [OWASP key management and recovery](https://cheatsheetseries.owasp.org/cheatsheets/Key_Management_Cheat_Sheet.html)

## Lost keys and continuing future messages

- Protecting all future messages is a policy, not an unlock operation. A locked sender now gets the unlock dialog when attempting to send; the draft stays intact and no message request is sent.
- If both saved chat and recovery keys are unavailable, only Super Admin can select **Forgot key? Start new protection**. An explicit confirmation explains that the new key only protects future messages. The new key is generated in the browser, future protection stays enabled, and a new recovery backup and recipient key delivery are offered.
- Rotation requires the current key identifier to prevent concurrent resets. Old message ciphertext is never rewritten or deleted. Old verification metadata and encrypted recovery backup are archived per conversation/key version.
- Earlier messages can be unlocked individually with their original chat/recovery key. Key versions have separate browser-memory slots so unlocking an old message cannot become the sender key for new messages. Previously pending encrypted deliveries remain available to their original recipient browser when opening an earlier message.
- Old messages whose original key, recovery key and usable recipient delivery are all unavailable remain unreadable. Creating a new key does not recover their contents.
- `node scripts/check-chat-key-rotation.mjs` covers locked send, explicit confirmation, old/new message separation, historical key/recovery unlock, and employee reset restrictions.
