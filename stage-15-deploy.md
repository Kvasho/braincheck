# BrainCheck — Stage 15: Deployment & Operations

> Instructions for Claude Code. Read this whole file before doing anything.
> Stages 0–14 are complete. All project guidelines still apply.
> **Claude Code prepares scripts, configs and documentation. I (George) perform the actual server actions, DNS changes and first deploy myself, guided step by step.** Never connect to or modify a server without my explicit instruction.

## Working rules (same as before)

- **One sub-step at a time.** After each one: report, suggest a commit message, and **wait for "continue"**.
- Ask before destructive dev-DB commands, `git push`, or `.env` edits. Never weaken tests or security.
- `composer check`, `npm run check` and `npm run build` must be green after every sub-step.

---

## 1. Hosting decision (do this first, together with me)

BrainCheck needs **a queue worker** (grading, notifications, imports) and **the scheduler every minute** (attempt expiry, reminders, cleanups). Write `docs/deploy/hosting-options.md` comparing:

**A) cPanel shared hosting** (what I already use)
- Cron runs `schedule:run` every minute.
- The queue is processed by a scheduled `queue:work --stop-when-empty --max-time=50` each minute.
- No Node on the server: assets are built in CI and uploaded.
- Document the limitations: up to ~1 minute latency for queued work, PHP version/extension availability (bcmath / intl / gd / zip / fileinfo), memory and execution limits, no Supervisor.

**B) A small VPS** (e.g. Hetzner / DigitalOcean) **+ Laravel Forge or Ploi**, or a manual setup
- Supervisor-managed workers, proper cron, Redis optional.
- Zero-downtime deploys, easier SSL and firewall management.
- Cost estimate.

Give a recommendation for an exam platform. Real-time auto-expiry and grading favour option B. **Then stop and let me choose.** Prepare the remaining sub-steps for the chosen option.

---

## 2. Sub-steps

### 15.1 Production configuration

**`.env.production.example`** (no secrets), covering:
- `APP_ENV=production`, `APP_DEBUG=false`, a fresh `APP_KEY` (generated on the server);
- `APP_URL=https://…`;
- `SESSION_SECURE_COOKIE=true`, `SESSION_DOMAIN`;
- `TRUSTED_PROXIES` if a CDN/proxy is used;
- `braincheck.enforce_two_factor` forced on (already enforced in code);
- mail SMTP settings;
- `LOG_CHANNEL=daily` with retention;
- `QUEUE_CONNECTION=database`;
- `CACHE_STORE`.

**Production checklist** (`docs/deploy/checklist.md`):
- document root points to `/public` only;
- `storage` and `bootstrap/cache` writable; everything else read-only for the web user;
- `.env` permissions `600`;
- PHP extensions verified;
- `opcache` enabled.

**Database**
- A dedicated MySQL user with privileges only on the `braincheck` database.
- Document optionally separating a migration user (DDL) from the runtime user (DML only).

**Required production commands**, in order:
- `composer install --no-dev --optimize-autoloader` (so Telescope and other dev packages aren't installed);
- `php artisan migrate --force`;
- `php artisan braincheck:sync-permissions`;
- `php artisan optimize` (config/route/view/event cache);
- `php artisan storage:link` only if needed (private files must **not** be linked).

### 15.2 Build and deploy pipeline

**GitHub Actions `deploy.yml`**, triggered manually (`workflow_dispatch`) or on tag, **only after CI is green**:
1. Build assets (`npm ci && npm run build`).
2. `composer install --no-dev`.
3. Create an artifact (exclude `.git`, tests, `node_modules`, docs).
4. Deploy via SSH/rsync:
   - **(A) cPanel:** if SSH is available; otherwise document an FTP/zip fallback;
   - **(B) VPS:** Forge/Ploi deploy hook, or a zero-downtime release-folder strategy with a `current` symlink.
5. On the server: `php artisan down --secret=…` → migrate → sync-permissions → optimize → `queue:restart` → `up`.

**Secrets** live in GitHub environment secrets (SSH key, host). Never in the repo.

**Rollback procedure** documented: the previous release folder/artifact, plus the database restore steps.

### 15.3 Scheduler and queues in production

- **(A)** The cron line, and the scheduled queue-processing command definitions, with `withoutOverlapping()`.
- **(B)** A Supervisor config for workers per queue (`grading` with higher priority), with `--max-time` / `--max-jobs` recycling.

**Health check:** a scheduled heartbeat (e.g. the scheduler writes a timestamp; `/up` plus a custom health endpoint protected by a token reports scheduler/queue freshness and failed job count).

### 15.4 HTTPS, mail and domain

- **TLS:** AutoSSL (cPanel) or Let's Encrypt. HTTP → HTTPS redirect; HSTS already enabled (consider preload only after it's stable).
- **Mail:** SMTP provider; **SPF, DKIM and DMARC** DNS records documented; a test email to Gmail/Outlook must land in the inbox.
- **Domain:** document the DNS records for the chosen domain/subdomain.

### 15.5 Backups

- Add `spatie/laravel-backup` (check compatibility).
- **Daily** DB dump + `storage/app/private` (question images, certificates).
- **Encrypted** archives (zip password from env), stored **off-server** (S3-compatible storage, e.g. Backblaze B2 / Cloudflare R2).
- Retention: 7 daily, 4 weekly, 6 monthly.
- Backup health monitoring with notification on failure.
- **A restore drill**, documented and actually performed once on a local copy.

### 15.6 Monitoring and logging

- **Error tracking:** Sentry (free tier) or Flare. Scrub PII and secrets (configure `before_send` / data scrubbing); never send request bodies containing answers or passwords.
- **Uptime monitoring** (e.g. UptimeRobot / Better Stack) on the landing page and the health endpoint.
- **Logs:** daily rotation; security events are already in the audit log; alert (email to admin) on spikes in failed logins (a scheduled check).

### 15.7 Post-deploy verification

**Smoke test checklist:**
- register, verify email, login with 2FA;
- create the admin via `braincheck:make-admin` on the server;
- teacher flow (create a test), student flow (take the exam), auto-expiry (a short test with a 1-minute limit), certificate PDF with Georgian text, emails delivered, queue processed, scheduler heartbeat fresh.

**External checks (my own domain only):**
- SSL Labs: target A+;
- securityheaders.com: target A+;
- a ZAP baseline against production (low intensity, own site only).

**Go-live sign-off:** a `docs/deploy/go-live.md` record with date, versions and the checklist results.

---

## 3. Definition of done

- [ ] The site is live over HTTPS with workers and the scheduler running, backups verified by a restore drill, and monitoring alerting.
- [ ] The deploy and rollback procedures are documented and tested once.
- [ ] Security header and SSL grades are A/A+.

Finish with a short report and a list of recommended next steps (e.g. a professional pentest before real exams with real stakes).
