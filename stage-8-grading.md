# BrainCheck — Stage 8: Grading Engine

> Instructions for Claude Code. Read this whole file before doing anything.
> Stages 0–7 are complete. All project guidelines still apply.

## Working rules (same as before)

- **One sub-step at a time.** After each one: report, suggest a commit message, and **wait for "continue"**.
- Ask before destructive dev-DB commands, `git push`, or `.env` edits. Never weaken tests or security.
- `composer check`, `npm run check` and `npm run build` must be green after every sub-step.
- Every new route goes into the access matrix. All UI text in ka + en.

---

## 1. Goal

Grade attempts automatically and correctly; let teachers grade open (text) answers manually; allow controlled, audited score overrides and regrades. All maths uses `Points` (`brick/math`); no floats.

---

## 2. Sub-steps

### 8.1 `GradeAttempt` Action

Input: an attempt in status `submitted` or `timed_out`. Runs in a transaction with `lockForUpdate` on the attempt.

**For every attempt question:**

- resolve the handler from the **snapshot** type;
- call `grade(snapshotDefinition, answer, points)`;
- store `is_correct`, `awarded_points`, `needs_manual_grading` on the answer row.

**Totals**

- `max_score` = sum of snapshot points.
- `score` = sum of awarded points.
- `score_percent` = score / max × 100, scale 2, half-up.

**Status**

- If any answer needs manual grading → `grading_status = pending_manual`; the score is **provisional** and `passed = null`.
- Otherwise → `grading_status = graded`, `graded_at`, and `passed = score_percent ≥ pass_percent`. An exact equality passes.

**Afterwards**

- Dispatch the `AttemptGraded` event **after commit**, but only when it becomes fully graded.
- **Idempotent:** grading an already-graded attempt is a no-op unless invoked through `RegradeAttempt`.

**Wiring**

- Replace the Stage 7 placeholder.
- Grading runs synchronously on submit for small attempts. If it ever becomes heavy, it can move to a queued job with `ShouldBeUnique` per attempt; document this decision.

### 8.2 Manual grading queue

Route: `/teacher/grading` (`attempts.grade-own-tests`). Scope: only attempts of tests the teacher owns.

**List**

- Answers needing manual grading.
- Filters: test, group, oldest first.
- Counts per test.

**Grading screen**

- **Left:** question body + reference answer (from the snapshot).
- **Right:** the student's answer rendered as **plain text** (preserve line breaks; never HTML).
- Points input from 0 to max, step 0.25, plus an optional feedback field (≤ 2000 characters).
- "Save & next" with keyboard shortcuts.

**Anonymous grading mode** (toggle, on by default): hide the student's name and show "Student #N" to reduce bias. The identity can be revealed per answer, and the reveal is audited.

**`GradeAnswerManually` Action**

- Lock the answer row; validate the points range; set points, `is_correct` (true if full, false if 0, null if partial), `graded_by`, `graded_at`, feedback.
- Recompute the attempt totals.
- When no pending answers remain, finalize: `graded`, `passed`, and `AttemptGraded` after commit.

**Concurrency:** two teachers (or two tabs) grading the same answer → the second gets a conflict message. Use an `updated_at` check or a version column.

### 8.3 Overrides and regrades

**`OverrideAnswerScore`**

- The test owner can change an auto-graded answer's points, with a **mandatory reason**.
- Recompute the totals.
- Audit `grading.override` (old/new points, reason).

**`RegradeTestQuestion`**

- If a teacher discovers that a question's correct answer was wrong, they can fix the correct answer **for the snapshots of a specific test's attempts** and regrade all affected attempts.
- **Admin or test owner only.** Password confirmation, preview of affected attempts, and score deltas before confirming.
- Runs as a queued job in chunks.
- Audit `grading.regrade` with a summary.
- Fires `AttemptRegraded` events, used by Stage 10 (certificate revoke/issue) and Stage 11 (XP adjustments).
- This is the **only** code path allowed to modify snapshots. Make that explicit in code comments and the guidelines.

**Add to `AuditAction`:** `grading.override`, `grading.regrade`, `grading.identity_revealed`.

### 8.4 Tests

**Unit, per type, through `GradeAttempt`**

- full, partial, zero and empty answers;
- rounding boundaries (e.g. ⅓ of points);
- `pass_percent` equality passes; 0.01 below fails.

**Mixed attempts**

- With a text question → `pending_manual`; after manual grading → `graded` with correct totals.
- `AttemptGraded` fires exactly once, and only after commit (use `Event::fake` + transaction tests).
- Idempotency.

**Manual grading**

- Another teacher's attempt → 403/404.
- Points out of range → 422.
- A student's `<script>` text answer is delivered as a plain string, and the UI renders it as text.
- Concurrent grading conflict.
- Anonymous mode hides names in the props; reveal is audited.

**Overrides and regrade**

- An override without a reason → 422.
- Audit rows written.
- Regrade changes only the targeted test's snapshots; scores and `passed` flags are recomputed correctly; events are fired; password confirmation is required.

**Property test**

- For random generated attempts, `score == sum(awarded_points)` and `0 ≤ score ≤ max_score`, always.

---

## 3. Definition of done

- [ ] Auto-grading is correct for all types; the manual grading flow works end to end in the browser; overrides and regrades are audited.
- [ ] All checks are green. `docs/grading.md` documents every formula, rounding rule, status transition (Mermaid) and the regrade policy.

Finish with a short report.
