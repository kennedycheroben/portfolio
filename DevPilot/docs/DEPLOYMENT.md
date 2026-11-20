# DevPilot deployment notes

DevPilot's authenticated portfolio pages load classes through `DevPilot/autoload.php`. The dashboard does not require `vendor/` or a Composer autoloader on Truehost/cPanel.

## Hosting requirements

- PHP 8.3 with the PDO extension enabled.
- Keep the complete `DevPilot/` directory in the portfolio root.
- The PHP account must be able to create and write `DevPilot/registry/` and `DevPilot/logs/`. Runtime directories are created with mode `0700`; runtime files are written with restrictive permissions.
- Keep `DevPilot/.htaccess` active so runtime data, source, and configuration cannot be fetched directly over HTTP.
- Keep the portfolio root `registry/.htaccess` and `Files "devpilot_devices.json"` rule active to protect legacy DevPilot runtime files during migration.
- Do not upload `.env`, secrets, logs, runtime JSON data, or a `vendor/` tree as part of deployment.

## Composer

No Composer installation is needed to serve the dashboard/API or run the included `DevPilot/cli/run-tests` suite. Composer metadata remains for standard PSR-4 consumers and PHPUnit tooling; install development dependencies from `DevPilot/` with `composer install` only when working with those tools. Do not deploy the resulting `vendor/` directory.

After uploading the application files, sign in as an administrator and open `/admin/devpilot.php`. The page reports an administrator-safe availability message if required source files, PDO, or writable runtime storage are unavailable. Check the cPanel PHP error log and confirm the PHP account owns the two runtime directories if initialization fails.
