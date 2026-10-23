# BrainCheck — Stage 9: Results & Analytics

> Instructions for Claude Code. Read this whole file before doing anything.
> Stages 0–8 are complete. All project guidelines still apply.

## Working rules (same as before)

- **One sub-step at a time.** After each one: report, suggest a commit message, and **wait for "continue"**.
- Ask before destructive dev-DB commands, `git push`, or `.env` edits. Never weaken tests or security.
- `composer check`, `npm run check` and `npm run build` must be green after every sub-step.
- Every new route goes into the access matrix. All UI text in ka + en.

---

## 1. Goal

- Students see their results **only as the test settings allow**.
- Teachers get real psychometric analytics: question difficulty, discrimination, distractor analysis, score distribution, progress over time, anti-cheat summaries.
- Admins see platform-wide analytics.

All statistics use `Attempt::real()` only.

---

## 2. Sub-steps

### 9.1 Results visibility policy

**`ResultsVisibilityPolicy` service.** One place answers two questions:

- _Can this student see the score of this attempt now?_
- _Can they see the correct answers?_

| `results_visibility` | Score visible                              |
| -------------------- | ------------------------------------------ |
| `immediately`        | after grading                              |
| `after_close`        | after the test's `closes_at` (and grading) |
| `manual`             | after the teacher releases the results     |
| `never`              | never; the student sees only "submitted"   |

Correct answers are visible only if the score is visible **and** `show_correct_answers` is on. Explanations follow the same rule as correct answers.

**Release results for `manual` tests**

- Migration: `results_released_at` on `tests`.
- `ReleaseResults` Action, owner only, audited.
- Once released, it can't be un-released (the visibility monotonicity rule from Stage 5).

**Tests:** a full matrix of visibility setting × time (before/after close) × grading status × released × `show_correct_answers`.

### 9.2 Student result pages

**`/student/attempts/{attempt}/result`** (owner only)

- Header: score ring (animated with `motion`), percent, passed/failed, time taken, date.
- Pending manual grading → show "awaiting teacher review", with the provisional part hidden.
- Per question: the `Review` component via `toReviewPayload(snapshot, answer, revealCorrect)`, with `revealCorrect` from the policy. Teacher feedback is shown as plain text.
- Confetti on pass (respects reduced motion).
- Links: certificate (Stage 10, when present), retry (if attempts remain and the window is open).

**`/student/results`**

- History `DataTable`.
- **Progress chart:** score % over time (`recharts` line chart).
- **Strengths by category:** radar or bar chart of average % per category. Only from results the student is allowed to see.

**Leak test:** when the policy denies correct answers, the result page JSON contains no correct-answer keys (reuse the recursive scanner).

### 9.3 Teacher test analytics

Route: `/teacher/tests/{test}/analytics` (owner; admin via `analytics.view-any`).

**Overview**

- attempts, unique students, average, median, standard deviation, pass rate, average duration.
- Filter by group and date range.

**Score distribution:** histogram in 10% buckets.

**Per-question table**

- **Difficulty index** p = share of students who got full points, or average share of points for partial types.
- **Discrimination index** D = p(upper 27%) − p(lower 27%), ranked by total score. Flag questions with D < 0.2 as "review this question", with a tooltip explaining what it means.
- **Distractor analysis** for choice types: how many students chose each option. Map snapshot option IDs back; they're stable because snapshots copy the IDs. Flag distractors nobody picks and distractors that strong students pick.
- Average awarded points; count of manual grades.

**Attempts list**

- Student, score, status, duration, anti-cheat event counts (badge colour by severity).
- Clicking opens the **attempt detail**: answers with the `Review` component (full reveal for the teacher), plus an anti-cheat **timeline** (event type + server time relative to start).

**Group comparison:** average % per assigned group.

**Implementation**

- Pure computation lives in `App\Domain\Analytics\*` classes, unit-tested with known datasets (hand-calculated expected values for difficulty, discrimination and median/stddev).
- Heavy per-question stats are **precomputed** into a `test_question_stats` table by a queued job, triggered by `AttemptGraded` / `AttemptRegraded` (debounced per test with `ShouldBeUnique`), plus a "recompute" button.
- Overview numbers are cached and invalidated on the same events.

### 9.4 Teacher dashboard (replace the placeholder)

- **Cards:** active tests, attempts this week, pending manual grading (link), average pass rate.
- **Upcoming closing tests.**
- **Recent submissions.**
- **"Questions needing review"** (low discrimination).

### 9.5 Student dashboard (replace the placeholder)

- Available exams count, in-progress resume card, latest results, progress sparkline.
- XP/level placeholders, which Stage 11 fills.

### 9.6 Admin analytics

Add to the admin dashboard:

- platform totals (tests, attempts, average pass rate);
- most active teachers and tests;
- attempts per day (30 days).

### 9.7 Results export (CSV)

**Safe CSV writer** (`App\Support\SafeCsv`):

- Any cell starting with `=`, `+`, `-`, `@`, tab or CR gets a leading `'`.
- UTF-8 with BOM, so Excel shows Georgian correctly.
- Unit-tested.

**Export:** per-test results CSV for the owner (student name, email, group, score, %, passed, submitted at, anti-cheat counts).

- Streamed download, owner/admin only.
- Large exports are queued in Stage 12.

### 9.8 Tests

- Visibility matrix (9.1) through the HTTP layer.
- Leak tests on result pages.
- **IDOR:** a student viewing another student's result → 404; teacher B viewing teacher A's analytics → 403.
- Analytics maths on fixed datasets; stats recompute triggered by events; cache invalidation.
- Previews excluded from all numbers.
- CSV injection test with malicious names, e.g. `=HYPERLINK(...)`.
- **Performance:** analytics for a test with 500 seeded attempts runs within a reasonable query count. Assert the query count with `DB::listen` or the expectations helper.

---

## 3. Definition of done

- [ ] Students see results exactly as the settings allow; teachers see full analytics with charts; admins see platform analytics; CSV export works and is safe.
- [ ] All checks are green. `docs/analytics.md` explains each metric in simple words (also useful for the UI tooltips).

Finish with a short report.
