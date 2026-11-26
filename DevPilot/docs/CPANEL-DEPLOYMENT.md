# DevPilot deployment on cPanel

This guide covers a conventional cPanel account using Apache and PHP-FPM. It does not deploy the site or run migrations for you. Confirm the hosting limits and PHP-FPM configuration with the provider before changing production.

## 1. Hosting requirements

- A cPanel account with HTTPS, a PHP version of at least **8.0**, and a MySQL/MariaDB database using InnoDB and JSON columns.
- A way to provide PHP environment variables and a way for the PHP account to read private files outside the document root.
- Permission to create the required tables and indexes through phpMyAdmin.
- No SSH, Composer, Node.js, or root access is required. File Manager and phpMyAdmin are sufficient for upload and schema setup. SSH and Composer are optional developer conveniences only.
- The project metadata permits PHP 8.0+, while the existing DevPilot deployment notes recommend PHP 8.3. Confirm the PHP-FPM version actually selected for the domain; do not infer it from a local CLI version.

## 2. Required PHP extensions

Enable and verify these for the domain's **web PHP-FPM** runtime (not only the CLI):

- `PDO` and `pdo_mysql` for the application database.
- `curl` and `openssl` for verified HTTPS connector calls.
- `sodium` for encrypted connector credential storage. Pairing/refresh must remain disabled if it is unavailable; the application fails closed.
- `json`, included with supported PHP versions, for configuration and API payloads.

`fileinfo` is needed for project-image uploads. `mbstring` is optional; the application has a fallback for text length handling.

## 3. Database creation and configuration

In cPanel, create a database and a dedicated database user, grant only the privileges required by the application, and use a unique strong password. Record the provider's exact database host and prefixed database/user names. The project and Hub must use separate databases and separate application instances.

The existing application reads `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` (or `DB_DSN`) from its process environment. In production, `DB_PASS` must not be empty. Do not put database credentials in PHP source, Git, a publicly served `.env`, or this guide.

## 4. Production environment

Create a private environment file outside `public_html`, for example `/home/ACCOUNT/private/portfolio.env`, and configure the PHP process to expose `APP_ENV_FILE` with that absolute path. Use the hosting provider's PHP-FPM/application environment settings. If the account cannot pass environment variables to PHP, ask the provider for a supported protected mechanism before deploying; do not move secrets into the document root as a workaround.

Set at least:

```text
APP_ENV=production
APP_URL=https://your-project.example
DB_HOST=localhost
DB_NAME=ACCOUNT_database
DB_USER=ACCOUNT_user
DB_PASS=replace-with-a-unique-secret
DEVPILOT_ROOT=/home/ACCOUNT/private/DevPilot
DEVPILOT_HUB_URL=https://your-hub.example
DEVPILOT_VAULT_KEYRING_FILE=/home/ACCOUNT/private/devpilot-vault-keyring.json
```

`DEVPILOT_ROOT` must be an absolute path outside the domain's document root. Project routes use it to load the private DevPilot code; production requests fail closed if it is missing, invalid, or inside the document root. Set `APP_URL` to this instance's canonical HTTPS origin. Set `DEVPILOT_HUB_URL` independently to the Hub's canonical HTTPS origin; for the Hub instance, this is its own origin. Do not set `DEVPILOT_CONNECTOR_SECURITY_VERIFIED`; production connect and refresh remain disabled pending the separate security review.

Create a distinct keyring for each instance, outside the document root and database. Follow [WEBSITE-CREDENTIAL-VAULT.md](WEBSITE-CREDENTIAL-VAULT.md) for key generation, file shape, rotation, and protected backups. Never copy a key into the environment file, database, or source tree.

## 5. Upload and document root

Keep the domain document root pointed at the existing public site files, normally `/home/ACCOUNT/public_html`. In cPanel File Manager:

1. Upload the site's public PHP entry points and required public folders (`admin`, `api`, `assets`, `forms`, `includes`, and `uploads`) into the document root.
2. Upload the **entire `DevPilot/` directory** to the configured private path (the example above is `/home/ACCOUNT/private/DevPilot`), not to `public_html`.
3. Keep `migrations/`, tests, docs, backups, `.git`, Composer files, and local configuration outside the document root. Do not upload `.env`, keyrings, database dumps, logs, or generated runtime data into `public_html`.
4. Preserve the repository's root and `uploads/.htaccess` protections. `uploads/projects` contains intended public image files only; it is not a location for secrets or application runtime state.

The PHP account needs read access to application code and write access only to the required upload and private runtime locations. DevPilot's `registry/` and `logs/` directories are created under `DEVPILOT_ROOT`; keep them outside the document root and restrict them to the PHP account.

## 6. Migrations

Back up the relevant files and database before applying SQL. First install the normal portfolio schema using the application's existing setup process. Apply any required portfolio migrations `001`–`003` once, in numeric order; migration `003_devpilot_websites.sql` is needed for the website-management pages.

Apply connector migrations separately in phpMyAdmin, to the correct database:

| Instance | Order |
| --- | --- |
| Customer/project database | `migrations/004_devpilot_connector.sql`, then `migrations/005_devpilot_project_owner.sql` |
| Hub database | `migrations/006_devpilot_hub_registry.sql` |

These are SQL files, not a Composer or application CLI command. Do not run them against the wrong database or rerun them as an upgrade. MySQL/MariaDB DDL auto-commits. Migration 005 invalidates legacy ownerless pairing codes and active tokens; affected projects must pair again. Keep the migration files private after applying them.

## 7. Cron and background work

No cron job, queue worker, or Node process is required. Connector inspections are refreshed by an authenticated administrator action. Do not add a cron job for pairing, token rotation, or migrations.

## 8. Permissions and security

- Keep the environment file and keyring outside `public_html`; set them to owner-only access (normally file mode `0600`) and keep their parent directory private (normally `0700`). Ensure the PHP-FPM account can read them.
- Keep `DEVPILOT_ROOT` and its runtime directories outside the document root. Grant write access only to `DevPilot/registry`, `DevPilot/logs`, and `uploads/projects` where needed; do not use world-writable permissions such as `0777`.
- Sessions default to the system temporary directory. If `SESSION_SAVE_PATH` is set, place it outside the document root and restrict access to the PHP account.
- `uploads/projects` is intentionally public media. Preserve its `.htaccess` rule that denies script execution; never place private runtime files there.
- Require HTTPS for the site and Hub. Keep TLS certificate/hostname verification enabled; do not use an HTTP Hub URL.
- Set `display_errors=Off` and `log_errors=On`. Store PHP/application logs outside the document root, review them through cPanel, and never log secrets or expose stack traces to visitors.
- Preserve `includes/config.php`'s external `APP_ENV_FILE` mechanism and use production environment settings. Never commit secrets or production configuration.

## 9. Post-upload verification

Before enabling connector use, verify in cPanel that the selected PHP-FPM version meets the minimum above and that the required extensions are enabled. Confirm the domain's document root does not contain `DevPilot/`, migration files, environment files, keyrings, logs, or runtime data; confirm the configured private paths are readable/writable only as needed.

With the existing production database and HTTPS configuration, verify the public site, administrator login, and the DevPilot dashboard. Confirm that missing private configuration gives an unavailable response without exposing paths or secrets, and review the private PHP error log. Check that direct HTTP requests to private files and upload scripts are denied. Do not run a production pairing, connector migration, or other write operation as a smoke test until it has been separately approved. The production connector review flag intentionally keeps connect and refresh disabled.

## 10. Rollback

Disable connector use, restore the previous application files from the verified backup, then restore the database backup if a migration or data change must be rolled back. If removing connector tables without restoring a database backup, run the project rollback in reverse order (`005_devpilot_project_owner.rollback.sql`, then `004_devpilot_connector.rollback.sql`) and run `006_devpilot_hub_registry.rollback.sql` on the Hub database. These rollbacks remove connector records; migration 005's invalidation of legacy ownerless credentials cannot be undone. Restore the matching protected keyring separately when restoring encrypted data. Never restore an exposed database password or deploy a backup into the public document root.
