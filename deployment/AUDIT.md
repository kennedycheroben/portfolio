# Portfolio codebase audit — 18 August 2026

## Verdict

**PASS for local/staging use.** The public site, database-backed portfolio, administrator screens, contact storage, redirects, error states, and static assets were exercised from the real `/portfolio` subdirectory mount. Production deployment still requires production credentials, SMTP verification, and staging browser/performance checks.

## Defects fixed

- Asset and redirect URLs now detect a subdirectory install instead of incorrectly requesting `/assets/...` from the server root.
- Public portfolio listing falls back to verified static projects if MySQL is unavailable; project details and login show controlled outage states instead of raw failures.
- Contact enquiries can be stored or delivered by SMTP, accept either `SMTP_PASS` or `SMTP_PASSWORD`, and fail safely when neither route is available.
- Fixed the SMTP multiline-response loop, response-code checks, TLS negotiation checks, and SMTP dot escaping.
- Administrator authorization now runs before database access, so unauthenticated requests redirect consistently even during a database outage.
- Added bounded pagination to project management; message pagination was verified at out-of-range page values.
- Cloud-hosted project images now render correctly in public and administrator views.
- Added defensive scalar input handling, strict ISO date validation, safer upload validation, CSRF handling for malformed input, and SameSite preservation when logging out.
- Added the missing WebP preload type and removed a dead JavaScript frame variable.
- Replaced the CSP-blocked CDN animation dependency with a local, dependency-free sticky canvas sequence: the 151 frames now scrub forward and backward with scroll, use bounded directional preloading, and fall back to a static poster for reduced-motion or constrained devices.
- Replaced the unrelated Unify Studios header artwork and 1×1 placeholder icons with a text/monogram identity and SVG favicon.
- Portable `.htaccess` missing-route handling now works whether the app is installed at the domain root or in `/portfolio`.
- Reworked the index migration to avoid stored procedures, fixing MariaDB compatibility with upgraded XAMPP data directories.

## Cleanup

Removed eight inert diagnostic/update stubs, seven unused frontend libraries and their empty directories, unused Bootstrap JavaScript, an unreferenced icon stylesheet, obsolete form-validation JavaScript, old testimonial/service imagery, three unused portfolio placeholders, and obsolete branding/icon files.

Removed two additional unreferenced, byte-identical copies of the 151-frame desktop hero sequence and the unused `requireLogin()` helper. The live responsive sequence remains under `assets/img/hero-sequence/desktop` and `assets/img/hero-sequence/mobile`.

Compatibility redirects (`resume.php`, `coming-soon.php`, and `login-signup.php`) and retired API endpoints remain intentionally: they prevent old links from becoming unexplained 404s. Original project PNGs remain because the local database references those exact paths.

## Database verification

A full local database backup was created at `/tmp/portfolio-db-pre-index-20260818.sql`. Project and contact create/read/update/delete queries were executed inside a rolled-back transaction. All administrator query shapes matched the real schema. The labeled contact-form test row was removed and a final query confirmed no test contacts remained.

The corrected `migrations/001_security_indexes.sql` was applied locally. These indexes were verified:

- `projects(featured, created_at)`
- `contacts(is_read, created_at)`

The rollback file removes only those indexes and is also idempotent.

## Regression evidence

- Every PHP file passed `php -l`; `assets/js/main.js` passed `node --check`.
- Public content routes returned HTTP 200 under `/portfolio`.
- Database-backed `portfolio.php` and `portfolio-details.php?id=1` returned HTTP 200.
- All authenticated administrator pages returned HTTP 200 using an isolated temporary session; unauthenticated requests returned 303 to `/portfolio/login.php`.
- Registration returns 404, retired APIs return 410, and compatibility routes return 303 to their current destinations.
- Generated HTML passed checks for duplicate IDs, missing image alt attributes, and missing local `href`, `src`, or form-action targets.
- CSS, JavaScript, icon font, favicon, project images, and hero frames returned HTTP 200 with appropriate MIME types.
- Headless Chromium verified the hero at frames 1, 76, and 151 from start to end, then frame 38 after reverse scrolling; a mobile viewport similarly advanced to frame 91 and reversed to frame 31 with no console or network errors.
- Rollback-only CRUD checks passed, both indexes were verified in the real schema, and simulated database-outage fallbacks passed.
- The temporary HTTP server logs contained no PHP warnings, notices, fatal errors, or uncaught exceptions.

## Preserved safeguards

Environment-only credentials, strict PDO behavior, hardened session cookies, CSRF protection, login/contact rate limits, honeypots, input limits, MIME/dimension-checked image uploads, POST-only destructive actions, output escaping, external URL validation, security headers, upload execution denial, and source/config denial remain in place.

## Production follow-up

1. Rotate any password that was previously exposed and keep production configuration outside the public directory via `APP_ENV_FILE`.
2. Back up production files and data, then apply the corrected index migration.
3. Verify authenticated SMTP delivery, not only database storage.
4. Test login/logout, CRUD, uploads, contact delivery, custom errors, the mobile menu, and the hero sequence on staging.
5. Run Lighthouse/PageSpeed and real-device accessibility checks on the deployed HTTPS origin.
6. Keep user-uploaded files when replacing the application directory.
