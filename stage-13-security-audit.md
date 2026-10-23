# BrainCheck — Stage 13: Security Audit & Hardening

> Instructions for Claude Code. Read this whole file before doing anything.
> Stages 0–12 are complete. All project guidelines still apply.
> Goal of this stage: **actively attack our own application**, document the evidence, fix every finding, and add a regression test for each fix.

## Working rules (same as before)

- **One sub-step at a time.** After each one: report, suggest a commit message, and **wait for "continue"**.
- Ask before destructive dev-DB commands, `git push`, or `.env` edits. Never weaken tests or security.
- `composer check`, `npm run check` and `npm run build` must be green after every sub-step.
- Every new route goes into the access matrix. All UI text in ka + en.
- **All scanning and attack scripts target only the local environment** (`braincheck.test`). Never point any tool at an external host.

---

## 2. Sub-steps

### 13.1 Threat model

Write `docs/security/threat-model.md`:
- **Assets:** correct answers, scores, certificates, user accounts, admin powers, personal data.
- **Actors:** anonymous visitor, student, cheating student, malicious teacher, compromised admin account.
- **Trust boundaries:** browser ↔ server, server ↔ DB, queue, file uploads, email.
- **STRIDE** for the critical flows: login/2FA, exam start/answer/submit, grading/regrade, certificate verify, import, admin user management.

For each threat: the mitigation and **where it lives** (file/class) plus **which test proves it**. Every "no test" entry becomes a task in 13.3.

### 13.2 OWASP Top 10 checklist with evidence

Write `docs/security/owasp-checklist.md`. For each category (Broken Access Control, Cryptographic Failures, Injection, Insecure Design, Security Misconfiguration, Vulnerable Components, Identification & Authentication Failures, Software & Data Integrity Failures, Logging & Monitoring Failures, SSRF): our controls, evidence (code + tests), and residual risk.

### 13.3 Code review sweep

Automate as much as possible as arch tests or scripts.

- **Routes**
  - every route is in the access matrix;
  - every state-changing route is POST/PUT/PATCH/DELETE (no GET with side effects);
  - every authenticated route has `verified` + `EnsureUserIsNotBlocked`.
- **Controllers:** every action that uses input has a Form Request or explicit validation; every action calls `authorize()` or is policy-protected via middleware. Write a test that reflects over controllers to check this, if feasible.
- **Models:** no `$guarded = []`; sensitive fields are in `$hidden` (re-verify).
- **Raw SQL:** grep for `DB::raw`, `whereRaw`, `selectRaw`, `orderByRaw`; each must use bindings. Add an arch rule to flag new ones.
- **Redirects:** any redirect target that comes from input (e.g. `intended`) must be same-origin. Test open redirects with `//evil.com`, `https://evil.com` and `/\evil.com`.
- **Logging:** no passwords, tokens, invite codes or verification codes in logs (search the log statements; add a log-sanitizing processor if needed).
- **Mass assignment:** re-run the Stage 2 tests across all models.
- **Larastan:** raise to **level 8** (or max if feasible) and fix the findings.
- **Semgrep:** add it to CI with the `p/php`, `p/laravel` (if available) and `p/react` / `p/typescript` rulesets. Triage every finding (fix or document the false positive).

### 13.4 Authentication hardening

- **User enumeration:**
  - password reset always returns the same generic message and timing;
  - login errors are generic (already done);
  - registration necessarily reveals that an email exists, so keep rate limits tight and document it.
- **Password reset tokens:** short expiry (60 min); tokens invalidated after a password change.
- **Sessions:**
  - regenerate on login and on privilege change (role change → invalidate that user's sessions);
  - security settings page shows **active sessions** (device/browser, IP, last activity) from the `sessions` table, with "log out other sessions" (requires the password) and per-session revoke;
  - audit these actions.
- **2FA:** recovery codes shown once; regenerating them requires the password; 2FA brute force rate-limited (verify).
- **Login alerts:** email the user on login from a new device/IP combination (optional, configurable).

### 13.5 Headers, CSP and cookies (production mode)

- Build and run locally with `APP_ENV=production` + `APP_DEBUG=false`. Fix everything in the browser console.
- **CSP:** enforce mode; try to eliminate `style-src 'unsafe-inline'` (nonces or hashes); add a `report-uri` endpoint that logs violations (rate-limited, size-limited).
- Verify all headers, and the cookie flags (`Secure`, `HttpOnly`, `SameSite`) for the session and XSRF cookies.
- Custom error pages (403/404/419/429/500/503) leak no stack traces or versions. Remove the `X-Powered-By` header if the server adds it (document the server config for production).

### 13.6 Dynamic testing (local only)

**OWASP ZAP** (Docker), against `braincheck.test`:
- a **baseline scan** (unauthenticated);
- an **authenticated full scan** for each role (configure a ZAP context with a login script, or session cookie replacement).
- Store the reports in `docs/security/reports/` (gitignored if large; keep a summary).

**Custom attack scripts** in `tests/Security/` (Pest), or a `scripts/attack/` folder:

| Attack | Expected result |
|---|---|
| **IDOR sweep:** for every ULID route, log in as user B and request user A's resources | 403/404 everywhere |
| **Parameter tampering on exam endpoints:** extra fields (`score`, `deadline_at`, `status`, `user_id`), wrong types, huge arrays, deeply nested JSON | ignored or 422 |
| **Race tests:** parallel requests (e.g. 20 concurrent `start`, `submit`, `join`, `approve` requests via `Http::pool` / curl in parallel against the local server) | invariants hold (one attempt, one approval, no double XP) |
| **XSS payload sweep:** seed every free-text field (names, titles, descriptions, question text, answers, feedback, category names, tag names, group names, teacher request message) with a payload list | renders inert on every page in a browser test; no alert dialogs, no script execution |
| **CSRF:** every state-changing route without the token | 419 |
| **Rate limits:** every limiter actually trips | 429 |

### 13.7 Dependencies and supply chain

- `composer audit` / `npm audit`: zero high or critical.
- Pin GitHub Actions to commit SHAs.
- Enable Dependabot (composer, npm, actions) with grouped weekly updates.
- Make sure the `package-lock.json` / `composer.lock` integrity checks run in CI (`npm ci`).

### 13.8 Data protection

- **Personal data inventory** in `docs/security/data-inventory.md`: what we store, why, and for how long.
- **Retention jobs:**
  - `attempts.ip_address` / `user_agent` nulled after 180 days;
  - audit logs kept for 1 year then pruned (configurable);
  - failed jobs pruned after 30 days.
- **Account deletion:** the user can request deletion from settings (password + confirm). Personal data is anonymised (name → "Deleted user", email → random unusable address); content they own as a teacher is retained but reassigned/anonymised (decide and document); certificates are revoked and their holder name is kept only if legally reasonable (document).
- **Data export:** a user can download their own data (JSON) via a queued job and a signed link.

### 13.9 Final report

Write `docs/security/report.md`:
- every finding (from 13.3–13.8): severity, description, fix, regression test reference;
- residual risks and recommendations (e.g. WAF, a professional external pentest before real usage, backups).

---

## 3. Definition of done

- [ ] Threat model and OWASP checklist with evidence.
- [ ] ZAP scans run with all medium+ findings fixed or justified.
- [ ] The attack suite is green and runs in CI (except ZAP, which is documented as a manual step).
- [ ] Larastan level 8+, Semgrep clean or triaged, audits clean.
- [ ] Active sessions management, retention jobs, account deletion and data export work.
- [ ] Guidelines updated with any new rules discovered.

Finish with a short report.
