# BrainCheck — Stage 10: Certificates

> Instructions for Claude Code. Read this whole file before doing anything.
> Stages 0–9 are complete. All project guidelines still apply.

## Working rules (same as before)

- **One sub-step at a time.** After each one: report, suggest a commit message, and **wait for "continue"**.
- Ask before destructive dev-DB commands, `git push`, or `.env` edits. Never weaken tests or security.
- `composer check`, `npm run check` and `npm run build` must be green after every sub-step.
- Every new route goes into the access matrix. All UI text in ka + en.

---

## 1. Goal

Passing a certificate-enabled test issues a verifiable PDF certificate with a QR code. Anyone can verify a certificate publicly by code, without seeing private data. Certificates can be revoked, and regrades are handled.

---

## 2. Sub-steps

### 10.1 Rules and migration

**One certificate per user per test**, for the first passing real attempt.
- Add a unique index `(user_id, test_id)` on `certificates` (in addition to the unique `attempt_id`).

**Verification code**
- 32 characters, CSPRNG, URL-safe, unambiguous alphabet.
- Displayed in groups (`XXXX-XXXX-…`) for humans.
- Lookups normalise by removing dashes and uppercasing.

### 10.2 Issuing

**`IssueCertificate` Action**
- Listener on `AttemptGraded`, queued, after commit.
- Conditions: the test has `issues_certificate`, the attempt passed, it's real, and the user doesn't already have a certificate for this test.
- **Snapshot:** holder name, test title, teacher name, score %, issue date.
- **Idempotent:** a unique violation → no-op.
- Notify the student (Stage 12 hooks in; for now, a database flash or a plain notification).

**Regrades** (`AttemptRegraded` listener)
- Passed → failed: **revoke**, reason "regrade".
- Failed → passed: **issue**.
- Audited.

### 10.3 PDF generation

**Template**
- A dedicated Blade view used **only** for the PDF; this is acceptable, since dompdf needs HTML.
- Landscape A4, Synapse styling: indigo/cyan, node-network SVG ornament, BrainCheck logo.
- Content: holder name, test title, score, date, teacher, verification code, QR.
- **Escape every dynamic value** (`{{ }}` only, never `{!! !!}`).

**Georgian font**
- Register FiraGO **TTF** with dompdf so Georgian renders. Verify visually.
- Disable remote resources in the dompdf config (`isRemoteEnabled = false`), and enable the dompdf `chroot` to the font/assets directory.

**QR:** SVG via `bacon/bacon-qr-code` v3, encoding the **absolute verification URL** (`/verify/{code}`), embedded as a data URI.

**Caching:** generate on first download, store on the private disk (`certificates/{ulid}.pdf`), regenerate when revoked status or snapshot changes.

**Download route:** `/certificates/{certificate}/download`.
- Allowed: holder, test owner, admin.
- Revoked certificates download with a large "REVOKED" watermark, or are refused. Choose and document; refusing is simpler.
- Safe filename: `braincheck-certificate-{ulid}.pdf`.

### 10.4 Public verification

**Routes:** `/verify` (form) and `/verify/{code}` (result), in `PublicLayout`, with the `certificate-verify` rate limiter.

**Result shows only:** holder name, test title, issue date, score %, teacher name, status (valid / revoked + date).
- **No email**, no user ULID, no attempt details.

**Unknown codes:** a generic "not found" with the same response timing characteristics. Don't distinguish malformed from unknown.

**Headers / meta:** `noindex, nofollow`, `Referrer-Policy: no-referrer` on these pages.

**Visuals:** an animated "verified" checkmark in Synapse style. Add a Hero-style link on the landing page to the verify form.

### 10.5 Student and teacher UI

- **Student, "My certificates":** cards with preview thumbnail, test, date, score; buttons to download, copy the verification link, show the QR.
- **Teacher:** certificates issued for their tests (list), with **revoke** (reason required, password confirmation, audited).
- **Admin:** all certificates, with revoke.

### 10.6 Tests

- Issued once; not for a failed attempt, a preview, or a disabled test; idempotent under duplicate events.
- Regrade transitions revoke/issue correctly.
- Download authorization (holder / owner / admin allowed, others 403); revoked behaviour.
- Verification:
  - valid, revoked, unknown, malformed (same generic response for the last two);
  - rate limit 429;
  - response contains no email or ULIDs (scan the props);
  - dash/case normalisation works.
- The PDF generates (non-empty, `application/pdf`); the view renders holder name, test title and code (assert on the rendered Blade HTML); the QR payload equals the verification URL.
- Code length, alphabet and uniqueness.

---

## 3. Definition of done

- [ ] Passing a certificate-enabled test produces a correct Georgian/English PDF with a working QR.
- [ ] Public verification works and leaks nothing; revoke works.
- [ ] All checks are green.

Finish with a short report.
