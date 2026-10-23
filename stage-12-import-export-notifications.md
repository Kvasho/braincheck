# BrainCheck — Stage 12: Import/Export, Notifications & Queues

> Instructions for Claude Code. Read this whole file before doing anything.
> Stages 0–11 are complete. All project guidelines still apply.

## Working rules (same as before)

- **One sub-step at a time.** After each one: report, suggest a commit message, and **wait for "continue"**.
- Ask before destructive dev-DB commands, `git push`, or `.env` edits. Never weaken tests or security.
- `composer check`, `npm run check` and `npm run build` must be green after every sub-step.
- Every new route goes into the access matrix. All UI text in ka + en.

---

## 1. Goal

- Teachers import questions from Excel/CSV, with a validated dry run, and export them in the same format (round-trip safe).
- Large exports run in the background with expiring download links.
- Users receive in-app and email notifications, according to their preferences.
- Admins can monitor failed jobs.

**Security focus:** uploaded spreadsheets are untrusted: formula injection, spoofed file types, zip bombs, huge row counts, malicious content in cells.

---

## 2. Sub-steps

### 12.1 Import format and template

**Format:** one row per question. Document it in `docs/import-format.md`.

| Column | Content |
|---|---|
| `type` | the `QuestionType` value |
| `body` | plain text; imported as a single paragraph of rich-text JSON; line breaks become `hardBreak` |
| `explanation` | |
| `difficulty` | |
| `points` | |
| `category_slug` | |
| `tags` | comma-separated, existing tags only |
| `options` | for choice types: separated by `\|`, with correct ones prefixed by `*` |
| `correct` | for `true_false` / `numeric` |
| `tolerance` | |
| `items` | ordering: `\|`-separated, in the correct order |
| `pairs` | matching: `left=>right` separated by `\|`, with `=>` and an empty left side for distractors |

**Template download:** `.xlsx` with
- a header row;
- an "Instructions" sheet in ka + en;
- one example row per type;
- data validation dropdowns for `type` and `difficulty`.

### 12.2 Import pipeline

**Migration `imports`:** `id`, `ulid`, `user_id`, `type` (`questions`), `status` (`uploaded` / `validating` / `validated` / `importing` / `completed` / `failed`), `file_path` (private), `total_rows`, `valid_rows`, `invalid_rows`, `imported_rows`, `error_report_path`, timestamps.

**Upload** (`/teacher/questions/import`, `questions.manage-own`)
- `.xlsx` / `.csv` only, ≤ 2 MB.
- Validate the extension **and** the detected MIME type **and** that the file opens as a spreadsheet/CSV (reject a PHP file renamed to `.xlsx`).
- Store on the private disk with a random name.
- Rate limit: 10 imports/hour per teacher.

**Dry run** (queued `ValidateImportJob`)
- Read with chunking; **max 1000 rows**.
- **Don't evaluate formulas.** Read raw cell values; a cell starting with `=` is treated as literal text.
- Guard against zip bombs: check the uncompressed size limits (configure PhpSpreadsheet/maatwebsite limits, or inspect the zip entries before loading).
- Map each row to the handler's `normalizeDefinition` + `definitionRules` (the **same** validation as the UI).
- Produce per-row results.

**Preview UI**
- Valid/invalid counts and a table of invalid rows with reasons, translated.
- Downloadable error report (CSV via `SafeCsv`).
- **Import valid rows** button, or **Cancel**.

**Import** (queued `ImportQuestionsJob`)
- Creates questions owned by the importer, in transactional chunks.
- Unknown tags/categories are reported, not auto-created (only admins manage taxonomy).

**Status page** polls with Inertia polling until the import completes; then a notification.

**Cleanup:** a scheduled job deletes import files and reports after 7 days.

### 12.3 Exports

**Question export:** the teacher's own questions, filtered (same filters as the bank), to `.xlsx` in **exactly** the import format. **Round-trip test:** export → import → identical definitions.

**Results export:** moves the Stage 9 CSV into the queue for large tests (> 500 rows).

**Delivery:** queued exports produce a file on the private disk plus a notification with a **temporary signed URL** (expires in 24 h).
- The download route checks the signature **and** that the requester is the export owner.
- Files are deleted after 24 h by a scheduled cleanup.

**All exported text cells** pass through formula-injection neutralisation (shared with `SafeCsv`; for `.xlsx`, set cells as explicit strings).

### 12.4 Notifications

**Channels:** `database` + `mail`. All notifications are queued, `afterCommit`, and in the recipient's locale.

**Notification types**

| Recipient | Notifications |
|---|---|
| Student | new test assigned to your group; test closes in 24 h and you haven't attempted it (scheduled hourly, **sent once** per student per test; track via a unique sent-log or notification uniqueness check); results released / graded (manual grading finished); certificate issued; badge earned (in-app only) |
| Teacher | daily digest of pending manual grading (only if > 0); import/export finished |
| Admin | new teacher access request (in-app), plus a daily digest of failed jobs (if any) |

**Preferences page** (settings): a per-type toggle for email (in-app always on).
- Migration: `notification_preferences` JSON on users, validated against an enum of notification types.

**In-app bell**
- Unread count as a **lazy/deferred** shared prop, plus a dropdown list.
- Mark one/all read; link to the target.
- Polling every 60 s (Inertia polling) on non-exam pages only.

**Mail template:** Synapse-styled Markdown mail theme with the logo, footer and unsubscribe link to preferences.

### 12.5 Queue operations

**Config**
- Database queue driver.
- Separate queues: `default`, `grading`, `notifications`, `imports`.
- Jobs define `tries`, `backoff`, `timeout`, and `failed()` handlers where meaningful.
- **No sensitive data in job payloads** (pass IDs, not models with loaded secrets).

**Admin page "System jobs"** (`/admin/system/jobs`)
- Failed jobs list (class, queue, failed at, exception summary — truncated, escaped).
- Retry and forget actions, with confirm + audit.
- New permission `system.jobs.manage`, admin only, via the enum + sync.

**Local:** document running `php artisan queue:work --queue=grading,default,notifications,imports` alongside `composer run dev` (or add it to the dev script).

### 12.6 Tests

**Import**
- Happy path for all 7 types.
- Each invalid case is reported per row.
- 1001 rows → rejected.
- A formula cell is stored as literal text.
- Spoofed file → rejected.
- Oversize → rejected.
- Imported questions belong to the importer; another teacher can't see the import or its report.
- Rate limit.

**Export**
- Round trip.
- Formula neutralisation.
- Signed URL: expired → 403; another user with a valid signature → 403.
- Cleanup deletes the files.

**Notifications**
- Each type is dispatched exactly when expected, in the right locale, to the right users.
- The closing reminder is sent only once.
- Preferences are respected for mail.
- Bell count and mark-read.

**Jobs page:** authz and audit.

---

## 3. Definition of done

- [ ] A teacher downloads the template, fills it, uploads it, reviews the dry run, imports, and exports again (identical round trip).
- [ ] Notifications arrive in-app and by email (log/mail catcher).
- [ ] Failed jobs are visible to admins.
- [ ] All checks are green.

Finish with a short report.
