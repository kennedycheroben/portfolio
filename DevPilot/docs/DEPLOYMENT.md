# DevPilot deployment notes

For cPanel, deploy the public portfolio files to the domain document root and keep `DevPilot/`, migrations, configuration, secrets, and runtime state outside it. Set `DEVPILOT_ROOT` to the absolute private `DevPilot/` path and `APP_ENV_FILE` to the private environment file. The runtime has a dependency-free PSR-4 autoloader; Composer is not required on the host.

See [CPANEL-DEPLOYMENT.md](CPANEL-DEPLOYMENT.md) for PHP/extension requirements, independent Hub/project setup, migrations, permissions, verification, and rollback. See [WEBSITE-CREDENTIAL-VAULT.md](WEBSITE-CREDENTIAL-VAULT.md) for keyring creation, encryption, and recovery.
