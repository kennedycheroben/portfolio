# DevPilot Phase 0 Audit

**Date:** 20 November 2026  
**Scope:** Architecture, compatibility, threats, resource constraints, phased implementation plan, and acceptance criteria. No application code, production files, or production data were changed.

## Executive assessment

The existing DevPilot is a PHP project/discovery and operation-management feature embedded in the portfolio administrator. It is not currently a website demo administrator. It provides useful foundations—authenticated admin pages, project registry, adapter contracts, device authentication, a persistent operation queue, logging, and an included test harness—but lacks the website registration, credential storage, browser execution, capability model, data ownership, and verification lifecycle required by the requested product.

**Recommendation:** retain the portfolio admin, project/device records, operation queue, and compatible APIs. Add the demo-administration domain as separately versioned modules behind existing authentication and authorization. Do not reinterpret project paths or generic operation approvals as website credentials or demo authority. No phase beyond Phase 0 should begin until this report is reviewed.

## Evidence and environment

- Workspace: `/opt/lampp/htdocs/portfolio`; working tree was clean at inspection.
- Checked-out branch: `feature/devpilot-production-repair` at `ed976df`; this is not `main`.
- Local `main`: `b4ae4ae` (8 November 2026). `origin/main`: `b4ae4ae`.
- `origin/main` contains no `DevPilot/` source or DevPilot admin pages. The working feature branch adds the DevPilot implementation and associated UI/API (38 files, about 6,929 insertions compared with `main`). Thus the request to inspect the “actual current main” exposes a release/branch divergence that must be resolved before implementation planning is treated as based on main.
- Production `https://kennedycheroben.co.ke/` responded HTTP 200 on 20 November 2026, with HTTPS, HSTS, CSP, `HttpOnly`, `Secure`, `SameSite=Lax` session cookie, and common security headers. Unauthenticated DevPilot page/API URLs redirected to `/login.php` (303). Direct requests for DevPilot source and runtime registry paths returned 403. This confirms routing/access controls, not authenticated feature behavior or production file identity.
- Repository deployment notes describe PHP 8.3, PDO, Truehost/cPanel, runtime JSON storage under protected directories, and no Composer/vendor requirement for the current admin pages. The current code uses JSON files for project, device, and operation state, with file locks; tests include queue concurrency and HTTP smoke checks.
- `DevPilot/docs/ARCHITECTURE.md` still describes a universal development-operations assistant. It describes LiteLLM as external and AI integration as future work. No website-inspection or browser execution integration was found in the inspected source.
- The SOFA endpoint `/skill.md` returned HTTP 403 and this session exposes no SOFA connector, so the required SOFA skill digest/session declaration and attention-feed check could not be completed. No SOFA advice informed this report.

## Current architecture and compatibility

### Existing components worth preserving

- Portfolio admin authentication and shared PHP bootstrap.
- DevPilot project registry and discovery, adapter contracts, CLI, and local test harness.
- `DeviceAuth` registration/revocation and the operation queue, including locking, state transitions, and current approval workflow.
- Structured logger with key-name/message redaction and current runtime-directory access protections.
- LiteLLM as the configured AI gateway boundary (currently documented as external; credentials and provider configuration are not part of this inspected DevPilot feature).

### Gaps and compatibility constraints

1. **Release divergence:** DevPilot exists on the checked-out feature branch but not local or remote `main`; production has DevPilot routes based on unauthenticated redirects, but live source/build identity cannot be determined externally. Establish the intended release baseline before coding.
2. **Storage:** file-backed JSON persistence is viable for the existing low-volume project/queue records but is a poor fit for concurrent multi-website task history, credential metadata, ownership links, verification evidence, and cleanup transactions. Evaluate the existing portfolio database/schema and cPanel backups before choosing incremental relational tables. Do not put ciphertext or tokens in ordinary project/operation JSON.
3. **Execution runtime:** PHP shared hosting can serve registration and task views, but sustained worker processes and Playwright browser binaries are not established. cPanel may impose process, memory, storage, and timeout limits. Keep the existing queue contract; determine whether execution can run within current hosting or whether an already available central runner is suitable. No new paid infrastructure is allowed.
4. **Adapter boundary:** existing adapters describe project technologies and demo-data commands, not remote website UI/API capabilities. Add a versioned website integration contract with explicit read/create/update/verify/cleanup capabilities and per-site evidence. Avoid assuming shared form or database structures.
5. **AI:** no AI task planner, prompt policy, or LiteLLM client is evidenced in current DevPilot. Keep model calls limited to planning/synthetic content; use deterministic validators and allowlisted action schemas for execution. A model must never receive credentials or invent executable actions.
6. **Verification:** current queue operation status cannot be treated as proof that a remote write persisted. Add independent post-action reads, relationship checks, and clear partial/failed outcomes.

### Proposed modular architecture

1. **Website Registry and authorization policy:** site metadata, exact admin origin, website type if known, approved demo scope, environment classification, active/revoked state, and owner.
2. **Credential Vault:** encrypted credential payloads with a key kept outside the database/web root and separate from ciphertext; restrictive access, rotation/version metadata, explicit revocation, and no credential serialization to model, logs, operation arguments, or reports. Store only a vault reference in task/site records.
3. **Inspector and capability model:** isolated authenticated session; user-assisted MFA; inventory of reachable modules, roles, forms, validation, relationships, existing content, public routes, and safe/blocked actions. Persist structured observations with timestamps and evidence, including unknowns.
4. **Planner and synthetic-data generator:** produce a dependency graph of typed actions inside the site's configured scope. Generate clearly fictional records and suitable assets; deterministic run/record markers make deduplication and ownership possible.
5. **Execution adapters:** prefer documented, authorized site APIs; otherwise isolated Playwright sessions in a central runtime. Expose narrow action primitives, idempotency keys, timeouts, bounded retries, screenshots where useful, and stop conditions. No direct production DB writes.
6. **Verification engine:** re-read records in fresh navigation/session where possible; verify IDs, relationships, role access, persistence, and relevant public/dashboard experience; classify each action verified, failed, or partial.
7. **Demo Data Registry and cleanup:** record website/run/type/remote identifier/creation time/dependencies/verification/cleanup eligibility. Cleanup acts only on positively identified records owned by a DevPilot run and re-verifies deletion; unknown ownership stops cleanup.
8. **Orchestrator, queue, and audit:** retain the existing queue interface where compatible; add durable task lifecycle, cancellation/timeouts, quotas, resumability, operator-visible evidence, and append-only security audit events. Separate task state from sensitive secrets.

## Threat model

| Threat | Impact | Required controls / Phase 0 design decision |
|---|---|---|
| Credential disclosure through logs, prompts, queue payloads, errors, screenshots, or reports | Compromise of registered websites | Vault references only outside vault; never send secrets to AI; redact at source and sink; restrict screenshot capture/redaction and access; audit vault reads. |
| Server-side request forgery via user-supplied admin URL, redirects, DNS changes, or browser navigation | Access to localhost, cPanel, cloud metadata, or other tenants | HTTPS and origin policy; resolve/check public IPs at connect time; block private, loopback, link-local, reserved, and metadata ranges; re-check redirects/subresources and DNS; egress restrictions; bind session to registered origin. |
| Prompt injection in admin pages, records, or uploaded files | Out-of-scope mutations or data exfiltration | Treat page content as untrusted data; no open-ended tool access; typed allowlisted actions; policy check on every operation; no secret/model context; require scope and prohibited-action classifiers with deterministic enforcement. |
| Cross-site session or credential confusion | Wrong site modified or session reused | Isolated browser context per site/run, origin pinning, no shared cookies/storage, short-lived sessions, teardown on completion/revocation. |
| Overbroad authorization or privilege escalation | Genuine users/configuration changed | Explicit per-site scopes and roles; least privilege credentials; protected-action deny list; no admin privilege changes; stop on ambiguous role or authorization. |
| Unsafe cleanup or mistaken ownership | Deletion of genuine content | Run-scoped ownership token/marker plus remote ID and type; deletion only for positively matched owned records; dependency order; preview/report and fresh confirmation policy for uncertain adapters; no broad search/delete. |
| Real side effects (email, SMS, payment, external integrations) | Customers contacted or money moved | Default-deny external side effects; recognize/disable sandbox controls where available; inspect before actions; block payment/billing/notification endpoints; abort if safe suppression cannot be proved. |
| Malicious or malformed sites/assets and browser exploit | Runner compromise or persistence | Ephemeral isolated browser workers, patched pinned browser, restricted filesystem/network, download restrictions, resource quotas, session teardown, no access to host secrets. |
| Queue replay, duplicate submission, worker crash, or race | Duplicate demo records or inconsistent state | Per-action idempotency markers, state machine, leases/locks, bounded retries, reconciliation reads before retry, timeout and cancellation semantics. |
| Cross-tenant/user access to sites, credentials, tasks, or evidence | Confidentiality and unauthorized operations | Enforce ownership and authorization at service boundary for every read/write; CSRF protections; session checks; audit access and revocation; test isolation. |
| Resource exhaustion or provider cost drift | Production admin unavailable or unexpected costs | Per-site/user/run quotas, global concurrency limits, token/browser/time budgets, cost reporting, no fallback provider, pause/queue when limits are reached. |

## Phased implementation plan and acceptance criteria

Proceed one phase at a time, with review at every gate. Do not start the next phase until evidence for the current gate is reviewed.

### Phase 0 — audit (this report)

Acceptance: current branch/production evidence, compatibility and threat analysis, architecture, resource constraints, test plan, and gates documented; user review received before implementation.

### Phase 1 — website registration, authentication, credential security

Build registry, scoped authorization, encrypted vault/key separation, protected credential interface, isolated-site metadata, revocation, and manual MFA handoff only. No autonomous inspection or writes yet.

Acceptance: data-flow and crypto/key-rotation review; credentials absent from DB plaintext, queue, logs, AI inputs, and ordinary UI; owner isolation, CSRF, URL/SSRF policy, revocation, backup/restore, and failure cases tested; manual MFA path documented; existing admin/queue compatibility proven.

### Phase 2 — inspection and capability model

Implement read-only inspection for one integration path first, with documented unknowns and evidence; do not create records.

Acceptance: only registered origins reached; MFA handled manually; isolated contexts; capability model covers modules/forms/roles/relationships/public pages and safe-action boundaries; no mutation across representative test sites; prompt injection and redirect/SSRF tests pass.

### Phase 3 — autonomous plan, accounts, synthetic data

Generate scoped dependency-aware plans, fictional content, demo identities, assets, and deterministic ownership markers. Implement approval policy as authorized by product scope, without per-record confirmation for safe in-scope work.

Acceptance: plans are explainable and validated against capability model; no genuine-record reuse or privilege escalation; no duplicate records on retry; no payment/email/SMS/external transaction; quota enforcement and adversarial content tests pass.

### Phase 4 — execution and verification

Implement API-first/site-specific adapter and isolated Playwright fallback, durable execution, recoverability, and post-action verification.

Acceptance: real persisted reads confirm each created record and relationships; dashboards/public pages and account role flows checked; partial/failure outcomes truthful; timeout, crash, cancellation, retry, and MFA scenarios validated; no unsafe side effect.

### Phase 5 — ownership registry and cleanup

Track every generated record/dependency and implement narrow cleanup.

Acceptance: cleanup only targets exact positively owned records; ownership ambiguity stops; deletion order and outcome independently verified; genuine fixture records remain untouched; dry-run and recovery evidence reviewed.

### Phase 6 — multi-site reliability and production readiness

Add scale controls, operational visibility, recovery, compatibility/versioning, and rollout practices.

Acceptance: multi-site isolation; quotas and worker limits; backup/restore and rollback rehearsed; observability excludes secrets; PHP/cPanel limits validated; security review, regression suite, production backup, explicit deployment authorization, and post-deployment verification completed.

## Test and review plan

No tests were run during this Phase 0 audit. For implementation, add tests as requested by the project brief and run them at the relevant gate:

- Unit: vault encryption/decryption and key rotation; redaction; scope policy; URL/IP/redirect checks; deterministic fictional data; idempotency; capability validation; ownership/cleanup eligibility; task transitions and quota math.
- Security: credential non-disclosure in prompts/logs/errors/reports; SSRF including redirects/DNS rebinding; prompt injection; CSRF/session/authorization; cross-site/tenant isolation; prohibited billing/notification/payment/admin actions; hostile files and downloads.
- Integration: DB migration/rollback; current admin authentication; existing queue/device APIs and CLI; LiteLLM unavailable/no-fallback behavior; encrypted backup/restore; site-specific API/browser adapters.
- End-to-end against disposable test websites: inspection only, manual MFA, plan review, create, fresh-session verification, retry/resume, partial failure, cleanup, and proof that genuine seeded records are unchanged.
- Operations: cPanel PHP version/extensions, request/worker limits, writable permissions, storage and backup footprint, queue concurrency, browser runtime availability, timeouts, log rotation, recovery and rollback.
- Production release: back up files and DB; deploy only after explicit authorization; run read-only health/smoke checks first; do not test by writing demo records to production.

## Resource and hosting assessment

- **Available now:** existing PHP 8.3/PDO/cPanel architecture and database are documented; the current app has no vendor runtime requirement and the operation queue is file-backed. No evidence in this checkout establishes Playwright or a long-running worker on Truehost.
- **Likely workable at low volume:** central PHP dashboard/API, registry/task metadata in the existing database (after schema and backup review), provider calls through configured LiteLLM, and bounded synchronous inspection or short jobs.
- **Unproven/high risk:** browser binaries/Playwright on shared hosting, durable workers beyond web request timeouts, parallel browser sessions, screenshot storage, and sustained AI/browser cost quotas. Measure on current plan before selecting a design.
- **Cost rule:** no new paid service or provider. Reuse current DB, LiteLLM configuration, and hosting only where limits support safe isolation and completion. If browser execution or worker quotas are inadequate, pause execution features rather than silently moving workloads to paid infrastructure.
- **Before implementation:** record cPanel PHP and extension versions, process/time/memory caps, disk quota, DB engine/version/backup size, existing scheduled-task capability, LiteLLM endpoint/provider policy and free quotas, and whether any centrally hosted browser runtime already exists and is authorized.

## Decisions requested at review gate

1. Confirm which branch/revision is the intended implementation baseline, given DevPilot is absent from `main` but present on the current feature branch and routes exist in production.
2. Confirm the acceptable production target model for demo runs (staging/demo sites preferred operationally; production scope requires explicit site-specific restrictions and external-side-effect protections).
3. Review this architecture and threat model before Phase 1 is authorized.

No source code, production files, production database, deployment, merge, or branch operation was performed as part of this audit.
