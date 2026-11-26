# DevPilot project connector protocol

The connector is a server-to-server authorization protocol. An administrator clicks **Connect DevPilot** in the project dashboard; the dashboard derives `project_id`, `project_name`, and its HTTPS origin from server configuration. No project URL is accepted from a form.

1. DevPilot creates a random pairing code valid for five minutes and stores only its SHA-256 hash.
2. The project connector submits the code together with its server-side identity and requested scopes over HTTPS.
3. DevPilot consumes the code in a transaction before issuing a token. A code can never be replayed.
4. The project stores the returned token in its secret manager. DevPilot stores only the token hash. The token is sent as `Authorization: Bearer …` and is never logged or returned again.
5. Every request checks token status, expiry, project identity, and the requested scope. Missing scopes deny by default.

The first operation is `project.inspect.read`; it returns only non-secret project identity and connection metadata. PHP projects can use `adapters/php/ConnectorClient.php`. Other stacks should implement the same identity, pairing, bearer-token, rotation, revocation, and scope rules. Production projects remain disabled until HTTPS, DNS/IP validation, redirect validation, and an independent security review are complete.
