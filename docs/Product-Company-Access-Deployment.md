# Product-specific company assignments

The invitation wizard saves company IDs separately for each selected product in
`user_product_access.company_ids`. Shared company memberships remain a central
directory; they no longer expand explicitly configured product scopes.

- Existing rows with a null scope retain their existing shared-company access.
- An explicit empty array does not fall back to shared memberships.
- Accepting another invitation adds access only to the products in that invitation.
- Before a scoped invitation adds shared memberships, existing legacy product scopes
  are captured so another product cannot gain those companies indirectly.
- PMS roles, departments and optional task assignments retain the existing PMS model.
- Daymark retains one reporting company per person.
- The original invitation form remains in source, without a user-facing launch path.

## Production deployment

Run these commands in the Ubuntu server's root SSH terminal, not Windows PowerShell.
Take a database backup before deployment:

```bash
sudo -u postgres pg_dump -Fc judibas > /root/judibas-before-product-scope-$(date +%Y%m%d-%H%M%S).dump
sudo -u devmaster -H bash -lc 'cd /var/www/judibas && php artisan down && git pull --ff-only origin main && php artisan migrate --force && npm run build && php artisan optimize:clear'
systemctl reload php8.3-fpm
systemctl restart judibas-queue judibas-reverb
sudo -u devmaster -H bash -lc 'cd /var/www/judibas && php artisan up'
```

If a command fails, keep maintenance mode enabled and resolve that failure before
running `php artisan up`. The schema migration is required before using this code.

Verify a test invitation with PMS assigned one company and Communication assigned
several. After acceptance, check PMS/CV Bank/Accounting company lists and a forbidden
direct company URL using the invited account. Do not use a Super Admin session to
verify employee restrictions.
