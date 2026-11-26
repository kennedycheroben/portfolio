# Deployment and rollback

1. Rotate the database password in cPanel before uploading this build. The prior source contained an exposed production fallback.
2. Back up the current `public_html` files and database from cPanel.
3. Create a production environment file outside `public_html`, using `.env.example` as the key list. Set `APP_ENV=production`, `APP_URL`, rotated database values, and authenticated SMTP values.
4. Point `APP_ENV_FILE` at that external file using the hosting environment. If the host cannot set environment variables, place `.env` in the site root only after confirming this build's `.htaccess` rules are active and return HTTP 403 for `.env`.
5. Upload the reviewed project files. Keep `uploads/projects` writable by PHP but do not grant world-write access.
6. Run `migrations/001_security_indexes.sql` manually in phpMyAdmin after taking the database backup. It adds only query indexes and does not change record meaning.
7. Confirm HTTPS, login, logout, contact storage/email, project create/edit/delete, message actions, the 151-frame hero, and 404/500 handling.
8. Confirm direct HTTP requests to `.env`, diagnostics, migrations, logs, Git metadata and upload scripts are denied.

To roll back, restore the backed-up site files and database. If only the optional indexes must be removed, run `migrations/001_security_indexes.rollback.sql`. Do not restore the exposed password; keep the rotated credential.
