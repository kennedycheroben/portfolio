# DevPilot project connector

The connector protocol pairs a project from its authenticated administrator dashboard, registers that project in the owner-scoped DevPilot registry, and fetches a sanitized read-only inspection. No remote execution operation is defined.

## Connection flow

1. An administrator submits `POST admin/api/devpilot-connector.php` with a valid session CSRF token. The server derives the project ID from `APP_URL` and the application root, and captures the authenticated administrator ID.
2. DevPilot creates a random 192-bit pairing code. Only its SHA-256 hash, project identity, approved scope, owner ID, and five-minute expiry are stored.
3. DevPilot calls the project's fixed HTTPS endpoint `POST /api/devpilot-connector.php?action=authorize` with `{"pairing_code":"…"}`. The project endpoint derives its own identity from server configuration and atomically consumes the code. The response contains one bearer token.
4. DevPilot immediately calls `POST /api/devpilot-connector.php?action=inspect` with `Authorization: Bearer dvp_…`. The project checks the token, owner-bound connection, project ID, origin, expiry and `project.inspect.read` scope.
5. DevPilot encrypts the token with the existing sodium credential vault, upserts the owner/project record, and stores the allow-listed inspection JSON. Plaintext token material is returned only in the authorize response and held in memory while the workflow runs.

## Project endpoints

All endpoints accept POST only and return JSON with `success`. The authorize endpoint accepts an 8 KiB maximum JSON body and requires a valid, unexpired, unused pairing code. Failed authorization attempts are counted per source address for five minutes (maximum ten). Pairing codes are consumed transactionally; failed identity or scope checks cannot be replayed.

Inspection and revocation require a bearer token. Inspection returns project ID/name/origin, PHP technology and runtime, framework label, connector capability and scope, timestamps, and health. It never reads `.env`, credentials, database configuration, session state, or arbitrary project files. Revocation marks the credential inactive at the project endpoint immediately.

## Network controls

The DevPilot HTTP client accepts only three fixed endpoint paths over HTTPS port 443. It resolves the configured hostname immediately before each request, rejects any DNS answer in private, loopback, link-local, documentation, multicast, reserved or otherwise prohibited ranges, and pins the validated DNS answers to the TLS request. Peer and hostname verification are enabled, the connect timeout is four seconds, the total timeout is ten seconds, and response bodies are capped at 1 MiB. Redirect following is disabled: redirect responses fail closed, so deployments must serve the connector endpoint directly at the canonical HTTPS origin.

## Local and staging configuration

- Use a public staging hostname with a valid HTTPS certificate; loopback/private project origins are intentionally rejected by outbound transport validation.
- Apply `migrations/004_devpilot_connector.sql` followed by `migrations/005_devpilot_connected_projects.sql` to a development/staging database. Do not apply these migrations to production as part of this implementation task.
- Configure `APP_URL` to the canonical HTTPS origin.
- Enable PHP cURL and sodium.
- Configure `DEVPILOT_VAULT_KEYRING_FILE` to an absolute path outside the web root. The JSON file shape is `{"active_version":"v1","keys":{"v1":"<base64-encoded-32-byte-key>"}}`; protect the file from other users. Do not put key material in the repository or web root.
- Keep `DEVPILOT_CONNECTOR_SECURITY_VERIFIED` unset. Production connect and refresh actions remain disabled until a separate security review has actually passed. Disconnect remains available for revocation.

## Migration rollback

To roll back only this connector registry migration, first stop using the new dashboard actions, then apply `migrations/005_devpilot_connected_projects.rollback.sql`. To remove the original connector schema as well, apply `migrations/004_devpilot_connector.rollback.sql` afterward. The rollback deletes connector records and inspection history; back up staging data first if it must be retained.

Future Laravel, Node.js, Python and other project adapters can implement the same fixed endpoints, server-derived identity, one-time pairing, token hashing, `project.inspect.read` authorization, sanitized response, and immediate revocation behavior.
