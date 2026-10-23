# BrainCheck — Stage 7: Exam Engine

> Instructions for Claude Code. Read this whole file before doing anything.
> Stages 0–6 are complete. All project guidelines still apply.
> **This is the most security-critical stage of the project. Take extra care. Prefer correctness over speed.**

## Working rules (same as before)

- **One sub-step at a time.** After each one: report, suggest a commit message, and **wait for "continue"**.
- Ask before destructive dev-DB commands, `git push`, or `.env` edits. Never weaken tests or security.
- `composer check`, `npm run check` and `npm run build` must be green after every sub-step.
- Every new route goes into the access matrix. All UI text in ka + en.

---

## 1. Goal

Students start, take, autosave, resume and submit timed exams. The server is the **only** authority on:

- time;
- which questions are in an attempt;
- whether an answer is accepted.

**Threats this stage must defeat:**

- seeing correct answers in the page source or network traffic;
- extending time by manipulating the clock or requests;
- answering after the deadline;
- starting extra attempts with parallel requests;
- answering questions not in your attempt;
- touching someone else's attempt;
- predicting the question selection or order;
- sharing an account mid-exam.

---

## 2. Sub-steps

### 7.1 Migration additions

Add to `attempt_answers`:

- `flagged` (bool, default false): the student's "come back later" marker.

Add to `attempts`:

- `session_hash` (nullable string 64): SHA-256 of the session ID that started the attempt.
- `last_seen_at` (nullable).

Update the ERD.

### 7.2 Student exams list

Route: `/student/exams` (`exams.take`). It uses `AvailableTests::forStudent()` only.

**Sections**

- **Available now:** published, window open, attempts remaining.
- **In progress:** with a resume button and remaining time.
- **Upcoming:** with countdown.
- **Closed / completed:** best score, if results visibility allows.

**Each card shows:** title, teacher, group(s), time limit, question count, attempts used/max, window.

Filter by group (from the Stage 6 link).

### 7.3 Exam intro and `StartAttempt`

**Intro page** `/student/exams/{test}`:

- Lookup goes through `AvailableTests`; a non-visible test returns 404, not 403, to avoid confirming that it exists.
- Shows rules: time limit, attempts, pass mark, anti-cheat notice in plain language, "answers save automatically".
- **Start** button, via POST.

**`StartAttempt` Action**, inside a DB transaction:

1. **Lock:** `lockForUpdate` on the test row (serializes starts per test), **and** rely on the Stage 2 DB constraints (`active_lock` unique, `attempt_number` unique) as the final guard. Catch unique-violation exceptions and turn them into "resume the existing attempt".
2. **Re-check everything inside the lock:**
    - visible to this student;
    - published and not archived;
    - `opens_at ≤ now < closes_at`;
    - real attempts used < `max_attempts`;
    - no in-progress attempt (if one exists → return it, i.e. resume).
3. **Select questions:** fixed mode → all, in order; random pool → draw N with `Randomizer(new Secure())`.
4. **Order:** if `shuffle_questions`, shuffle with CSPRNG.
5. **Snapshot** each question through `SnapshotBuilder` (Stage 5):
    - type, body, explanation, definition, points (override applied), version, image reference;
    - if `shuffle_options`, shuffle option order **inside the snapshot**, so the order is fixed for this attempt and stable on resume.
6. **Deadline:**
    - `deadline_at = min(started_at + time_limit, closes_at)`, ignoring whichever part is null;
    - if neither exists, `deadline_at = null`;
    - **computed only here, never from client input.**
7. Create one empty `attempt_answers` row per question.
8. Store IP, user agent (truncated) and `session_hash`.
9. Set `tests.locked_at` if null (real attempts only).

The route is rate limited (e.g. 10/min per user). After starting, redirect to the player.

### 7.4 Player page

Route: `/student/attempts/{attempt}`. Policy: owner, attempt is real, status `in_progress`. Otherwise redirect to the result page (Stage 9) or 404.

**Lazy expiry:** if the deadline has passed (beyond grace), finish the attempt immediately (7.7) and redirect.

**Props: only through a dedicated `PlayerAttemptResource`**

- Attempt ULID, test title, `deadline_at` (ISO, UTC), **`server_now`** (ISO), question count.
- Questions: position, type, body, image URL, points, `toPlayerPayload()` built from the **snapshot**.
- Saved answers and flags.
- **Nothing else.** No snapshot, no definition, no question ULIDs needed by the client beyond what answering requires.

**Leak test (critical)**

- Render the player for each question type.
- Recursively scan the entire Inertia page JSON for forbidden keys (`correct`, `correct_order`, `pairs`, `is_correct`, `reference_answer`, `tolerance`, `snapshot`, `definition`).
- For choice types, assert that the correct option's text/ID isn't marked in any way.

**UI** (`ExamLayout`, distraction-free)

- **Header:** test title, timer (monospace, tabular numbers), save status indicator ("saved" / "saving…" / "offline – retrying"), submit button.
- **Question navigator:** numbered grid showing answered, flagged and current. Keyboard shortcuts: ← → to move, F to flag.
- **Question area:** body via `RichText`, image, the type's `Player` component.
- Previous / next buttons; "flag for review" toggle.

**Timer**

- Clock offset = `server_now − client_now` at load.
- Remaining = `deadline_at − (Date.now() + offset)`.
- Warnings at 5 min and 1 min (toast + subtle colour change).
- At 0 → call submit; the server decides anyway.
- **The client can't change the deadline.** No endpoint accepts time values from the client.

**Multiple tabs:** use `BroadcastChannel` to detect a second tab with the same attempt and show a warning overlay in the newer tab.

### 7.5 Autosave

Endpoint: `PUT /student/attempts/{attempt}/answers/{position}`. A JSON endpoint, not an Inertia visit; uses the XSRF cookie; rate limiter `autosave`.

**`SaveAnswer` Action**

- **Authorization:** owner and real attempt.
- **Status:** must be `in_progress`.
- **Time:** reject if `now > deadline_at + grace` (grace = 10 s, config) with **409** + code `attempt_expired`. The client then shows a message and goes to the result page.
- **Position** must exist in this attempt.
- **Validate** the answer with the handler's `answerRules()` against the **snapshot** definition (IDs must exist in the snapshot).
- Store answer + `answered_at`; update `flagged` if sent; update `last_seen_at`.
- **Session check:** if `session_hash` differs from the current session, still save the answer, but record an anti-cheat event `session_changed` (once per new session). Teachers decide; we don't silently block, because re-login is legitimate.
- Response: `{ saved_at }` only.

**Client**

- Debounce 800 ms per question.
- Save immediately on question change and before submit.
- Retry with exponential backoff on network errors.
- `beforeunload` warning if there are unsaved changes.
- Keep unsaved answers in memory only (no localStorage, so nothing persists on shared computers).

### 7.6 Submit

`POST /student/attempts/{attempt}/submit`.

- A confirm dialog shows the unanswered and flagged counts first.
- The client flushes pending saves before submitting.

**`SubmitAttempt` Action**

- Transaction + `lockForUpdate` on the attempt.
- If already submitted or timed out → return the current state (**idempotent**).
- Set `status = submitted`, `submitted_at`.
- Call grading:
    - the Stage 8 `GradeAttempt`;
    - until Stage 8 exists, set `grading_status = pending_auto` and dispatch a placeholder job, noted clearly.

Redirect to the result page (Stage 9; a simple "submitted" page until then).

### 7.7 Auto-finish (timed out)

**Command `attempts:expire`**, scheduled every minute with `withoutOverlapping()`:

- finds real `in_progress` attempts with `deadline_at < now − grace`;
- for each: transaction + lock, `status = timed_out`, `submitted_at = deadline_at`, then grading;
- processes in chunks.

Also called **lazily** from the player, autosave and submit paths when they detect expiry.

Document that the scheduler must run in production (Stage 15).

### 7.8 Anti-cheat events

**Client listeners:** `visibilitychange` (hidden), `blur`, `copy`, `paste`, `fullscreenchange` (exit, when the student opted into fullscreen).

- Batch events and send every 10 s, or immediately on submit.

**Endpoint:** `POST /student/attempts/{attempt}/events`, rate limited.

- Max 50 events per request, max 500 per attempt (beyond that, keep counting in a per-type counter instead of storing rows).
- **Timestamps are server-side.**
- Validate the event type against the enum; `meta` is limited to 1 KB with whitelisted keys.

**Optional fullscreen:** a "Take in fullscreen" button on the intro page. It's optional, never forced.

**Shown to teachers in Stage 9.** **Never** affects the score automatically.

Tell the student plainly on the intro page what is recorded (transparency).

### 7.9 Tests

- **Start conditions:** not visible → 404; draft, archived, not yet open, closed → refused; attempts exhausted → refused; in-progress exists → resume instead of a new attempt.
- **Concurrency:** call `StartAttempt` twice "simultaneously". At minimum, simulate the second call after the first inside its transaction, or use two DB connections. Exactly one attempt exists, and the unique violation is handled as a resume.
- **Deadline:**
    - computed correctly for every combination of time limit and `closes_at`;
    - autosave at deadline + grace − 1 s is accepted; at + grace + 1 s → 409;
    - submit after the deadline marks the attempt as timed out (expire runs first).
- **Expire command:** handles exactly the expired attempts, is idempotent, skips previews.
- **Autosave:** another user's attempt → 404/403; unknown position → 404; an ID not in the snapshot → 422; a submitted attempt → 409; rate limit → 429; any client-sent `deadline_at`/`score`/`status` fields are ignored.
- **Leak tests** for every question type (7.4).
- **Snapshots:** editing the question after the start doesn't change the player payload, and option order is stable across reloads.
- **Randomness:** the random pool picks N distinct questions from the pool; distribution sanity (run the selection many times; every pool question gets picked at least once).
- **Session change:** answers still saved; exactly one `session_changed` event.
- **Anti-cheat caps:** per request and per attempt.
- **Removed member** (Stage 6 pending test): can finish the in-progress attempt, can't start a new one.
- **Browser E2E:** student starts → answers all 7 types → reloads mid-exam (answers restored) → flags → submits. A second scenario: the time limit runs out → auto-submit → redirected.

---

## 3. Definition of done

- [ ] The full exam flow works in the browser for all question types, including resume, timer warnings and auto-submit.
- [ ] All leak, concurrency, deadline and IDOR tests are green; all checks are green.
- [ ] `docs/exam-engine.md` explains the lifecycle (state diagram in Mermaid: `in_progress → submitted | timed_out → graded`), the deadline formula, the grace period, and the anti-cheat data collected.
- [ ] Guidelines record: the server is the only time authority; player data goes only through `PlayerAttemptResource`; every attempt mutation is transactional with row locks.

Finish with a short report.
