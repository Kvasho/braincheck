# BrainCheck — Stage 5: Test Builder

> Instructions for Claude Code. Read this whole file before doing anything.
> Stages 0–4 are complete. All project guidelines still apply.

## Working rules (same as before)

- **One sub-step at a time.** After each one: report, suggest a commit message, and **wait for "continue"**.
- Ask before destructive dev-DB commands, `git push`, or `.env` edits. Never weaken tests or security.
- `composer check`, `npm run check` and `npm run build` must be green after every sub-step.
- Every new route goes into the access matrix. All UI text in ka + en.

---

## 1. Goal

Teachers assemble tests from their question bank, configure them, preview them exactly as students will see them, and publish. Once a test has real attempts, its grading-relevant content is **locked**, so results stay fair and comparable.

---

## 2. Sub-steps

### 5.1 Migration additions

Add to `tests`:

- `issues_certificate` (bool, default false), used in Stage 10;
- `locked_at` (nullable): set when the first real attempt starts.

Add to `attempts`:

- `is_preview` (bool, default false, indexed).

Preview attempts must be excluded from **every** statistic, leaderboard, XP award, certificate and attempt count. Add a global scope or explicit scope `Attempt::real()`, and an arch/feature test that analytics queries use it.

Update `docs/erd.md`.

### 5.2 Builder UI

Route: `/teacher/tests`, with `tests.view-own` / `tests.manage-own` permissions and policies.

**Index (`DataTable`)**

- Columns: title, status badge, question count, groups assigned, window (opens/closes), attempts count.
- Filters: status, category; search.

**Builder:** one page with tabs (or a stepper) and a sticky summary sidebar. The sidebar shows question count, total points, estimated duration and validation warnings.

1. **Basics:** title (3–150 characters), description (plain text, ≤ 2000), category.
2. **Questions**
    - **Fixed mode:** a picker dialog reusing the question bank `DataTable` with filters (only the teacher's own, non-archived questions). Selected questions show as a reorderable list (`@dnd-kit`), each with an optional points override and preview.
    - **Random pool mode:** same picker for the pool, plus a "draw N questions" input (1 ≤ N ≤ pool size). Show a difficulty mix summary.
    - Warn about archived questions still attached.
3. **Settings**
    - time limit (none, or 1–600 minutes);
    - max attempts (unlimited, or 1–20);
    - pass percent (0–100);
    - shuffle questions, shuffle options;
    - results visibility;
    - show correct answers;
    - issues certificate.
4. **Schedule**
    - `opens_at` / `closes_at`, each optional.
    - Pickers show and accept times in the app timezone **Asia/Tbilisi**; store them in UTC.
    - Show a human-readable summary, e.g. "opens in 3 days, open for 2 hours".
5. **Review & publish:** a checklist of all validations, then a "Preview as student" button, then a "Publish" button.

**Autosave the draft** (debounced) or use an explicit save. Pick one and keep it consistent; explicit save with a "dirty" indicator is simpler and safer.

### 5.3 Actions and rules

**Actions:** `CreateTest`, `UpdateTest`, `SyncTestQuestions`, `PublishTest`, `UnpublishTest`, `ArchiveTest`, `DuplicateTest`, `ExtendTestWindow`. All are `final` and transactional.

**`SyncTestQuestions`**

- Every question ULID must belong to the test owner and not be archived (archived questions can stay if already attached, but can't be newly added).
- Positions are contiguous from 1. Points overrides are > 0.

**`PublishTest`** validates:

- at least 1 question;
- random-pool N ≤ pool size;
- all attached questions have valid definitions (re-validate through their handlers);
- `closes_at` is in the future if set;
- `closes_at > opens_at`;
- for a timed test, a closing window shorter than the time limit gives a warning, not an error.

It then sets `status = published` and `published_at`.

**Locking:** once `locked_at` is set (the first real attempt started, in Stage 7):

- **Allowed:** title, description, extending `closes_at` (only later), toggling `issues_certificate` on, results visibility changes that reveal _more_ (e.g. `never` → `immediately` is allowed; `immediately` → `never` is not, because students may already have seen results).
- **Not allowed:** questions, points, pool size, time limit, max attempts (except increasing), pass percent, shuffle settings, `opens_at`.
- Enforce this in the Actions **and** in the Form Requests. The UI disables locked fields and explains why, with a "Duplicate as new version" button.

**`UnpublishTest`:** allowed only while not locked. A locked test can only be archived.

**`ArchiveTest`:**

- archived tests disappear from student lists;
- in-progress attempts may still finish until their deadline;
- results stay visible according to their settings.

**`DuplicateTest`:** copies settings and question links; status `draft`, no schedule, title suffixed with "(copy)".

### 5.4 Preview as student

- `StartPreviewAttempt` creates an `is_preview` attempt for the owner teacher, using the **same** snapshot logic Stage 7 will use. Build the snapshot service here and reuse it in Stage 7: `App\Domain\Attempts\SnapshotBuilder`.
- It opens the real player (`ExamLayout`). It may be a simplified version until Stage 7 finishes the full player; if so, note it and complete it in Stage 7.
- Preview attempts are deleted automatically after 24 hours (scheduled command) and never lock the test.

### 5.5 Tests

- Access matrix; IDOR (another teacher's tests, including attaching foreign questions → 403 for the whole request).
- `SyncTestQuestions` validation: archived questions, positions, overrides.
- Publish validation: every failing rule and the happy path.
- **Lock rules:** for every locked field, an attempted change → 422; every allowed change succeeds; `closes_at` can only move later; visibility can only become more revealing.
- Timezone: an input in Asia/Tbilisi is stored as correct UTC and displayed back correctly. Use `travelTo` around a date boundary.
- Preview attempts never count: they don't lock the test, aren't in attempt counts, and are cleaned up after 24h.
- Duplicate produces an independent draft.
- Browser test: build a test with 5 questions, configure it, preview it, publish it.

---

## 3. Definition of done

- [ ] A teacher can build, configure, preview, publish, unpublish, duplicate and archive tests in the browser.
- [ ] Lock rules are enforced and explained in the UI.
- [ ] All checks are green. Guidelines record: locked tests are immutable for grading-relevant fields; preview attempts are excluded everywhere via `Attempt::real()`.

Finish with a short report.
