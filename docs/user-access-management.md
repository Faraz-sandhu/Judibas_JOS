# Central user and product access

Judibas Super Admin manages employees in **Administration ? Users**, PMS role assignments in the user editor, and onboarding in **Invitations**. Existing accounts, role/department links, project/task assignments and old acceptance links are preserved. PMS employee and invitation sidebar links are removed; Roles & permissions is managed inside PMS; old administrative bookmarks redirect to Judibas Administration.

## Invite once

Select products, companies, Communication administrator/groups and PMS roles/departments. Optional PMS tasks add both task and parent project membership without removing current assignees. Create a manual link or send using shared email settings. The link expires after seven days; reissuing to the same email revokes the previous central link. Invitations can also be revoked explicitly.

New users set one password. Existing users sign in with their current account and accept added access; their password and other products are preserved. Roles and memberships are validated again at acceptance. Tokens are stored as hashes and can be accepted only once. Previously sent PMS invitations appear under Previous PMS invitations in the central screen and remain usable.

## Manage access

The shared Users editor assigns all registered products, multiple companies and separate PMS roles/departments. Removing a product blocks its backend immediately while retaining historical work and PMS role/department links for later reactivation. Account inactive blocks all products. Deleting an account retains the previous global-delete behavior; use inactive or product access removal to preserve account history.

Only Judibas Super Admin can manage employees or issue new PMS onboarding invitations. PMS role and permission management uses the original product-specific role-view/add/edit/trash and permission-view/add/edit/trash checks. Existing project/task assignment rules and recipient acceptance remain intact. Communication administrators retain their existing company-scoped group and invitation workflows; Communication administrator and PMS administrator never grant Judibas Super Admin.

Apply migrations and build assets when deploying. Future products register in `config/judibas.php` and use the same `users` and `user_product_access` identity/access tables. Detailed roles stay product-specific; the central editor currently supports PMS roles and Communication administrator access. Cross-product HR delegation is not introduced by this change.
