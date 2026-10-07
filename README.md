# Judibass

Laravel, Vue 3, TypeScript, and PostgreSQL company portal with in-house product access and Communication.

## First setup after cloning

Install PHP 8.2+ with `pdo_pgsql`, Composer, Node.js, and PostgreSQL. The portable database binaries and development database are local-only and are not included in Git.

```powershell
composer install
npm ci
Copy-Item .env.example .env
php artisan key:generate
```

Create a PostgreSQL database and role, then set the database connection values in `.env`. Configure SMTP there if invitations should reach inboxes.

```powershell
php artisan migrate
php artisan judibas:admin-password
npm run build
php artisan serve
```

Set the Super Admin password with the interactive command above. Start `npm run dev` in another terminal when developing the frontend.

## Run locally

From the project folder:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/start-database.ps1
php artisan migrate
php artisan serve
```

In another terminal, run npm run dev. Open http://127.0.0.1:8000.
For compiled frontend assets, use npm run build instead of the development server.

The working portable PostgreSQL version is 17 under .local/postgresql17/pgsql; the cluster is .local/pgdata. It is a local process, not an automatic Windows service. Use scripts/stop-database.ps1 to stop it. Laragon PHP has pgsql and pdo_pgsql enabled.

.env, .local, .preview, and private uploaded files contain local configuration/data and must not be published. Database administrator credentials are in .local/postgres-admin.json.

## Login and administration

Employees and Super Admin share /login. Administrator email and the hashed password are configured by JUDIBAS_ADMIN_EMAIL and JUDIBAS_ADMIN_PASSWORD_HASH in .env. Use php artisan judibas:admin-password to change the Super Admin password.

Super Admin sections:
- Blogs: create/edit drafts, publish, and delete.
- Website: homepage announcement and company-page content.
- Products: display details, icons, ordering, and visibility for developer-registered products.
- Users: company assignment, account activation, passwords, product permissions, and Communication Admin roles.
- Communication management is inside Products → Communication, separate from website administration.
- Branding: project name, introduction, and logo upload.

This portal is for in-house use. Billing, Plans, pricing, and trials are removed.

## Communication

Open /communication. Members see only their company community and conversations they belong to. Personal contacts must share a community or group. Delegated Communication Admins manage onboarding/groups within their company. Only Super Admin publishes community announcements, deletes content, and reviews every employee's chats.

Invitation acceptance uses expiring one-time links. New members set a password; existing members verify their current password. Accepted members sign in through /login and open Communication.

Brevo SMTP settings live only in .env. Theme and Sign out controls are in the header account dropdown.

See COMMUNICATION.md for setup, permissions, announcement alerts, delivery behavior, and browser checks.

## Products and website

Register developer-built products in config/judibas.php. Registrations appear in the homepage directory, /products, and /products/{slug}. Registration alone does not build an application. Communication is implemented; the other product pages describe applications still to be developed.

Company content defaults live in config/pages.php and can be overridden through Administration. Published plain-text pages/articles are safely rendered. Logos are served through /branding/{file}.

## Verification

- npm run build
- php artisan test
- npm run test:browser
- php scripts/setup-communication-check.php
- node scripts/check-communication.mjs
- php scripts/cleanup-communication-check.php

Browser checks use Microsoft Edge and the local server. Screenshots go into .preview. Feature tests use PostgreSQL transactions and roll back their records. Do not run RefreshDatabase against the development database.

## Communication Status

The Status tab has a WhatsApp-style directory with author photos, My updates, Recent updates, and Today/Yesterday timestamps. Super Admin and delegated Communication Admins can publish updates. Delegated updates remain company-scoped. The viewer shows full media, remaining expiry time, a lifetime indicator, previous/next navigation, and authorized viewer details. All updates expire after 24 hours.

## Product-specific Communication management

Super Admin opens /products/communication for the product overview: chats, Communication users, companies and communities, invitations, status and announcements, conversation review, and review logs. No Communication item is added to the portal sidebar. The product introduction page is retained. Start Communication opens the management overview for Super Admin and /communication chats for employees. Delegated admins keep their company-scoped onboarding tools in chat.

Website Administration manages shared employee accounts and other product permissions. Communication access and admin roles are managed within the product. Removing access there preserves the global employee account and other product permissions. A company is required when granting Communication access; global accounts without Communication can remain unassigned.

### Live Communication (Laravel Reverb)

Communication uses private WebSocket notifications instead of periodic chat or status API polling. New messages and announcements are delivered directly to currently authorized recipients and rendered immediately; storage paths are excluded. Other changes trigger a fetch of scoped data, and background refreshes reconcile unread state. Messages, reactions, edits, pins, communities and status updates refresh on events; reconnect and returning to the tab synchronize missed changes. A local timer only controls status playback and expiry.

Run these from the project directory in separate terminals:

```powershell
php artisan serve
npm run dev
php artisan reverb:start
# If php is not on PATH:
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/start-reverb.ps1
```

Reverb needs no Pusher account or credentials. The pusher-js dependency implements the compatible wire protocol. Local Reverb credentials are generated in .env, which remains ignored by Git. For another machine, set a random REVERB_APP_SECRET, an application key and matching VITE_REVERB_APP_KEY. Restart Vite after changing VITE variables, and restart Reverb after server configuration changes.

Production: keep Reverb running under a service supervisor, use a TLS reverse proxy for WebSockets, set REVERB_ALLOWED_ORIGINS to the site hostname, and configure REVERB_HOST/PORT/SCHEME plus matching VITE_REVERB variables for that host. REVERB_SERVER_HOST/PORT control the bind address. Broadcasting runs immediately; no queue worker is required for Communication updates. If Reverb is unavailable, message saves still succeed and clients show a reconnecting indicator.
