# Communication: company communities

The product follows a WhatsApp-style structure: Chats (personal conversations and groups), Communities (company announcement spaces), and administrator stories/status updates.

## Administration and onboarding

1. Sign in through /login as Super Admin and open Administration > Communication.
2. Create or rename company communities, for example Skyinfinit and Cruxlo.
3. In Administration > Users, assign every employee a company and Communication product access.
4. Optionally grant Communication Admin to an employee. This is a company-scoped role, not global website/Super Admin access.
5. Use Communities & invitations to invite a colleague by email, choose the required company, and optionally select their groups.
6. The email contains a one-time acceptance link valid for 7 days. New users choose a password (minimum 12 characters); existing users verify their current password.
7. Acceptance assigns Communication access and group membership, then signs the employee in. Later email/password logins open Communication directly.

Invitations can be resent (old pending links are revoked) or revoked. Expired, accepted, and revoked links cannot be reused. If a delegated inviter loses admin/product access or changes company, their outstanding invitations stop working. Existing accounts in another company or inactive accounts cannot be taken over through an invite; Super Admin must update the account first.

Brevo SMTP is configured in the private .env file. Credentials are never included in the frontend or repository documentation. SMTP authentication was checked without sending email. Invitations are sent only when an authorized admin submits the invite form. Delivery status shows sent (submitted to the mail service), failed, or log mode. Inbox delivery still depends on the provider accepting the sender and recipient.

For remote onboarding, open the website through a company-reachable host/domain before generating invitations. Local 127.0.0.1 acceptance links work only on the server computer. Use HTTPS for hosted deployment.

## Membership and roles

Every company has a community announcement conversation. An employee can see only their assigned company's community and groups/chats they belong to. Their personal-chat contact picker contains active Communication users who share their company community or a group. The backend enforces these restrictions, including attachment downloads and direct API requests.

Delegated Communication Admins can invite members and manage groups for their own company. They cannot grant admin roles, create companies, publish community announcements, delete messages/groups, access global reviews, or read private chats unless they are members.

Super Admin can manage all companies/users/roles, publish announcements, delete content, and review any employee's conversations. Review stays in the Super Admin session, is labelled read-only, and is audited. No employee password or identity impersonation is used.

Removing a group member revokes group access immediately. Changing an employee's company through Administration removes old-company group memberships. Disabling an account or removing product access blocks Communication.

## Announcements and messages

Only Super Admin can publish community messages or attachments. The announcement form can target several communities; each receives an independent private attachment copy. Members see unread announcement alerts that open the corresponding community and can react with the available emoji. Reading clears the alert.

Employees can send messages and attachments, reply and edit their own messages in personal/group chats. They cannot delete messages, including their own. Deletion/moderation and group deletion are Super Admin-only.

Stories are published by Super Admin and expire after 24 hours. Stories currently support text.

## Appearance and delivery

The header account dropdown offers light, dark, and automatic themes plus Sign out. Themes persist on that browser. Desktop and mobile chat layouts are supported.

Messages refresh every 3 seconds while the tab is visible. Conversation summaries refresh every 15 seconds while a chat is open. This release uses the existing Laravel server, Vue, and PostgreSQL. WebSocket delivery, voice/video calls, typing/presence, message forwarding, and push notifications are not implemented. Conversations are company-accessible and do not use end-to-end encryption.

The portal is entirely for in-house use. Billing flags, Plans UI, pricing/trial APIs, and billing content have been removed. Historical migration tables are retained rather than deleting old database records.

## Verification

- php artisan test
- npm run build
- npm run test:browser
- php scripts/setup-communication-check.php
- node scripts/check-communication.mjs
- php scripts/cleanup-communication-check.php

Community browser fixtures create temporary companies, employees, and an invitation directly in the database; they send no test emails. The guarded cleanup removes only fixture records and files. Feature tests use database transactions and fake mail/storage.

## Chat details and shared content

Click a conversation name or the details button in its header to open the right panel. Contact info shows the colleague's name, email and company; group info lists members. Media, Docs and Links show paginated shared content from the conversation history. Image previews and file downloads use authenticated attachment routes.

Use the header search button or the Search tab in the panel to find messages without filtering the main chat. Selecting a result fetches surrounding messages and highlights the selected message. The panel becomes a full-width drawer on mobile.

Shared-content requests enforce conversation membership and Super Admin review scope. Super Admin shared-content views are recorded in the review log. No new public file URLs are created.

After preparing browser fixtures with scripts/setup-communication-check.php, run node scripts/check-communication.mjs and node scripts/check-communication-media.mjs. Clean up with scripts/cleanup-communication-check.php.

## Profiles and message actions

The profile button above the chat list lets employees update their display name, upload or remove a private profile photo, and change their password. Password changes require the current password and a confirmed new password of at least 12 characters. The open page receives the renewed session security token. Employee email, company and permissions remain managed by administrators.

Profile photos appear in the sidebar, personal chat list, contact header and contact/member details. Photo requests require Communication access and an eligible colleague relationship or Super Admin access.

The smiley button sits beside each message bubble. The message dropdown offers Reply, Forward, Copy, React, Pin/Unpin, and permitted Edit/Delete actions. Group messages from eligible colleagues also offer Message [name], which creates a personal conversation or reuses the existing one. Super Admin employee reviews remain read-only.

Up to three messages can be pinned per conversation. Members can pin in their personal/group chats; community pins are controlled by Super Admin. Pinned messages appear above the conversation and can be opened directly. Forwarding supports one existing accessible conversation per action. Community posting restrictions also apply to forwards, and attachments are copied independently.

Browser regression: prepare fresh fixtures, then run node scripts/check-communication-actions.mjs. The check changes only temporary test accounts and records new conversations for cleanup.

## Story attachments

Super Admin can publish a title with text, an attachment, or both. Supported attachments include PNG/JPEG/WebP images, MP4/WebM video, MP3/WAV/M4A audio, PDF, text/CSV, Word/Excel files and ZIP archives, up to 20 MB. Images, video and audio preview in the viewer; documents have download links.

Story files remain on the private disk. Viewing and downloading require active Communication access or Super Admin access, and stop after the 24-hour expiry. Removing a story also removes its file. The hourly Laravel scheduler runs communication:prune-stories to clear expired stories and their attachments. For local scheduled cleanup, run php artisan schedule:work; on deployment configure the Laravel scheduler.

Browser verification: prepare temporary communication fixtures and run node scripts/check-story-attachments.mjs, then run scripts/cleanup-communication-check.php.

## Story viewers and reaction people

Opening a story records an employee view; loading thumbnails or opening stories as Super Admin does not. Repeated employee opens update the last-viewed time without increasing the unique viewer count. Super Admin can click Viewers inside the story viewer to see names, photos and viewing times, refresh the list or load additional viewers. Viewer records expire with the story and cascade on deletion.

Click a message reaction count to open the list of people and emojis. Filter by emoji or All. Employees can remove their own reaction from this dialog. Reaction detail reads remain available to authorized Super Admin reviews without enabling employee actions.

Browser regression: fresh communication fixtures, node scripts/check-communication-viewers.mjs, then scripts/cleanup-communication-check.php.
