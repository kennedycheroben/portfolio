# Independent DevPilot Hub and project connector

The Hub and each customer project are separate application instances with separate databases, administrator sessions, and keyrings. Customer-side code uses only `APP_URL` for its own identity and the explicit `DEVPILOT_HUB_URL` for the Hub. An empty Hub setting is an error; the project will not silently call itself.

## Pairing and approval

1. A signed-in project administrator submits the CSRF-protected Connect action. The project derives its ID from its canonical `APP_URL` and application root, binds the pairing to the administrator, and creates a 192-bit code with a five-minute lifetime. The project database stores only the code hash.
2. The project posts the code hash context, project identity, origin, fixed requested scope, and a constrained return URL to the configured Hub over verified HTTPS. The Hub stores the code hash and an encrypted copy needed to complete the callback. Hub approval requests expire after five minutes and are rate-limited by source address.
3. The Hub approval page requires a signed-in Hub administrator and CSRF validation. It shows the project origin and scope before approval.
4. On approval, the Hub decrypts the pending code and makes a pinned HTTPS request to the project connector. The project atomically consumes the code, verifies its own server-derived project ID and origin, then returns a one-time bearer token. The Hub performs an authenticated inspection before registering the project under the approving Hub administrator.
5. The customer project stores only the token hash. The Hub stores the bearer token encrypted with the sodium credential vault. Pairing-code ciphertext is erased after approval or rejection.

## Project connector protocol

- `POST /api/devpilot-connector.php?action=authorize` with JSON `{"pairing_code":"…"}` consumes the pairing request and returns `{success, connection}` once.
- `POST /api/devpilot-connector.php?action=inspect` with `Authorization: Bearer dvp_…` returns a minimal project overview and checks token state, project identity, origin, expiry, and `project.inspect.read` on every request.
- `POST /api/devpilot-connector.php?action=revoke` with the bearer invalidates it immediately.

Inspection includes the project ID/name/origin, PHP technology and runtime, a known framework label, available read-only capability, authorized scopes, timestamps, and health. The endpoint does not read `.env`, credentials, database configuration, sessions, keys, or arbitrary files. No remote execution capability is available.

Hub connection records are keyed by Hub owner and project ID. Refresh performs a new authenticated HTTPS inspection and persists the sanitized response. Disconnect calls the project revoke endpoint before marking the Hub record revoked. A failed revoke leaves the Hub record active so the administrator can retry.

## Network controls

The connector transport accepts only fixed Hub-request, authorize, inspect, and revoke paths over HTTPS. It resolves DNS immediately before each request, rejects any prohibited answer (including loopback, private, link-local, documentation, multicast, reserved, IPv4-mapped, and special-purpose ranges), pins the complete validated answer set using cURL resolve entries, verifies the TLS chain and hostname, times out after four seconds to connect and ten seconds overall, and caps responses at 1 MiB. Redirect following is disabled; a 3xx response fails closed. Connector URLs are not accepted from request input.

The only loopback exception is the isolated integration harness, which requires `DEVPILOT_CONNECTOR_TEST_MODE=1`, a non-production environment, two explicitly listed test ports, and a trusted test CA. It does not disable TLS verification and cannot enable in `APP_ENV=production`.

## Separate instance setup

For staging, deploy the Hub and customer project as separate HTTPS applications with separate MySQL databases and separate sodium keyrings.

- Configure the Hub instance's `APP_URL` and `DEVPILOT_HUB_URL` to the Hub's canonical HTTPS origin.
- Configure the customer instance's `APP_URL` to its own canonical origin and `DEVPILOT_HUB_URL` to the independently hosted Hub origin. These values must differ.
- On the customer database, apply `migrations/004_devpilot_connector.sql` followed by `migrations/005_devpilot_project_owner.sql`. Run each migration once in order. Migration 005 invalidates any legacy unowned pairing codes and active tokens; they cannot be safely assigned to an administrator and require re-pairing.
- On the Hub database, apply `migrations/006_devpilot_hub_registry.sql` only, in addition to the Hub application's existing base schema.
- Back up each database before migration. MariaDB/MySQL DDL auto-commits, so these migrations are ordered one-shot steps rather than one atomic transaction. The customer connector's owner/project index is intentionally non-unique to retain revoked history; connection IDs and token hashes are unique. Owner IDs have no foreign key because customer applications can use different administrator schemas; owner-scoped lookups and connection writes include the owner ID.
- Configure `DEVPILOT_VAULT_KEYRING_FILE` separately on each instance to an absolute path outside the web root. Its shape is `{"active_version":"v1","keys":{"v1":"<base64-encoded-32-byte-key>"}}`. Keep key material out of the repository and web root.
- Enable PHP cURL, OpenSSL, PDO MySQL and sodium on both instances. If sodium is unavailable, pairing secrets and Hub tokens cannot be persisted; the connector fails closed.
- Leave `DEVPILOT_CONNECTOR_SECURITY_VERIFIED` unset. Production connect and refresh remain disabled until a separate review has passed; disconnect remains available.

The repeatable local integration test creates two independent SQLite databases and two PHP application servers behind isolated TLS proxies with a temporary trusted CA. It exercises the real project and Hub HTTP endpoints, Hub administrator session and CSRF, encrypted Hub token storage, authenticated inspection, refresh, revocation, and rejection of the old token:

```powershell
& 'C:\xampp\php\php.exe' -d extension=php_sodium.dll DevPilot/tests/integration/independent-hub-connectivity.php
```

The test requires PHP cURL, OpenSSL and PDO SQLite. It creates temporary databases, keyring, certificate, sessions, and server processes under the system temporary directory, then removes them. It never uses production data.

The MariaDB concurrency test uses a local MariaDB/MySQL instance and requires a test account allowed to create and drop databases. It refuses remote hosts, requires the explicit `DEVPILOT_MARIADB_TEST_ALLOW=1` opt-in, creates two temporary databases named with the `devpilot_test_connector_` prefix, applies the actual project migrations 004/005 and Hub migration 006, and drops only those generated databases on exit. Run it from PowerShell with:

```powershell
$env:DEVPILOT_MARIADB_TEST_ALLOW='1'
$env:DEVPILOT_MARIADB_TEST_HOST='127.0.0.1'
$env:DEVPILOT_MARIADB_TEST_PORT='3306'
$env:DEVPILOT_MARIADB_TEST_USER='root'
$env:DEVPILOT_MARIADB_TEST_PASSWORD=''
& 'C:\xampp\php\php.exe' DevPilot/tests/integration/mariadb-connector-concurrency.php
```

Use a dedicated local test account where possible; do not point these variables at a production host. Set `DEVPILOT_MARIADB_TEST_ISOLATION=READ-COMMITTED` to exercise that isolation level as well as the default repeatable-read mode.

## Rollback

Stop connector use before rollback. On the customer project database, apply `migrations/005_devpilot_project_owner.rollback.sql`, then `migrations/004_devpilot_connector.rollback.sql` if removing all project-side connector tables. On the Hub database, apply `migrations/006_devpilot_hub_registry.rollback.sql`. Rollback deletes connector authorization and inspection records. Migration 005's invalidation of legacy ownerless tokens and pairings is intentionally irreversible; affected projects must pair again even if the owner columns are later removed.
