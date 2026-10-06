# Judibass

Laravel, Vue 3, TypeScript, and PostgreSQL company portal with in-house product access and Communication.

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
- Communication: company communities, email invitations, group membership, and onboarding.
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
