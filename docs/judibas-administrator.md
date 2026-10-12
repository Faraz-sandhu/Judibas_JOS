# Judibas Admin

As Super Admin, open Administration ? Users ? edit or create an employee ? enable **Judibas Admin**. The account uses the normal employee login and its own password. No account is automatically promoted by the migration.

Judibas Admin manages employee accounts, product access, invitations, website content, Communication companies/groups/announcements and all PMS departments/projects/tasks/roles. The role automatically grants all registered products and bypasses PMS department/project restrictions. Ordinary product roles retain their existing behavior.

Communication continues to use the administrator's actual user identity. Private/group message access requires participation; they cannot review other users, inspect their call history, open audit logs or subscribe to Super Admin channels. Announcements and public status publishing are available without granting chat review. Phone-number restrictions still apply to delegates.

Only Super Admin can grant/revoke Judibas Admin, modify or delete administrator accounts, or change shared mail/realtime credentials and protected branding settings. Delegates cannot invite existing administrators through an alternate onboarding route. Disabling the account removes its administrative access.

Future product controllers must use `App\Services\PlatformAccess::product($request, $slug)` and apply their product-specific authorization afterward. This shared helper grants Judibas Admin access without treating them as Super Admin. Do not use the role to bypass private chat or sensitive-data checks.

Deploy with `php artisan migrate --force` and build frontend assets. Existing product assignments, accounts and PMS roles are preserved.
