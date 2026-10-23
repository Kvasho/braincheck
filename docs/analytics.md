# Results & analytics

What students see of their results, and what every analytics number means (in simple words, also used for the UI tooltips). **Every statistic uses real attempts only** (`Attempt::real()`): teacher previews never count.

## What a student sees (`ResultsVisibilityPolicy`)

| Test setting  | The score is visible…                                                                    |
| ------------- | ---------------------------------------------------------------------------------------- |
| `immediately` | as soon as the attempt is graded                                                         |
| `after_close` | once graded **and** the test's closing time has passed (a test without one never closes) |
| `manual`      | once graded **and** the teacher pressed "Release results" (this can't be undone)         |
| `never`       | never; the student only sees "submitted"                                                 |

- **Correct answers and explanations:** shown only if the score is visible **and** "Show correct answers" is on.
- **Awaiting review:** while a teacher still has to grade free-text answers, the student sees "awaiting teacher review" and no provisional score.
- **The charts on the results page** (progress, strengths) use only results the student may see.

## Test analytics

All numbers come from **graded** attempts (optionally one group, optionally a date range).

### Overview

- **Average:** the mean score %.
- **Median:** the middle score. Half the attempts scored less, half more; with an even number of attempts, the mean of the two middle ones.
- **Standard deviation (σ):** how spread out the scores are, in percentage points (population σ = √(Σ(x − mean)² / n)). Small: most students scored close to the average. Large: very different results.
- **Pass rate:** the share of graded attempts that reached the pass mark (equality passes).
- **Average duration:** the mean time from start to submission.
- **Score distribution:** how many attempts fall in each 10 % band: 0–10, 10–20, …, 90–100 (100 belongs to the last band).
- **Groups:** the average score % of each group the test is assigned to (its members' attempts).

### Per question

**Difficulty index (p)** is the share of the question's points students got, from 0 to 1 (shown as %).

- For right-or-wrong questions: the share of students who got it right.
- For partial-credit questions: the average fraction of points.
- High p = easy, low p = hard. Around 0.3–0.8 is usually informative.

**Discrimination index (D)** asks: do strong students do better on this question than weak ones?

- Rank the attempts by total score. Take the top 27 % and the bottom 27 % (rounded, at least one attempt each).
- D = p(top group) − p(bottom group), from −1 to 1.
    - Near 1: the question separates strong from weak students well.
    - Near 0: it doesn't tell them apart.
    - Negative: weak students do _better_, so something is wrong (unclear wording or a wrong key).
- **D < 0.2 → "Review this question".** It needs at least 2 graded attempts.

**Distractor analysis** (single and multiple choice): how many attempts picked each option, overall, in the top 27 % and in the bottom 27 %. Option IDs are stable across snapshots, so picks map back to options.

- **"Nobody picks it":** a wrong option nobody chose. It distracts no one; consider replacing it.
- **"Strong students pick it":** more top students than bottom students chose a wrong option. Check its wording, or the key.

**Average points and manual grades:** the average awarded points, and how many answers a teacher graded by hand.

### Attempts and integrity events

- **Attempts list:** each real attempt with score, status, duration and the number of integrity events (stored rows plus the overflow counters):
    - 0: no badge;
    - 1–5: a few (warning);
    - more than 5: many (danger).
- **Attempt detail:** every answer with the correct answer revealed, and a timeline of integrity events. Times are the server's, counted from the start. **Events never change a score**; they are information for the teacher.

### How the numbers stay fresh

- **Per-question stats** are precomputed into `test_question_stats` by `RecomputeTestQuestionStats`:
    - queued on `AttemptGraded` / `AttemptRegraded`;
    - unique per test, so a burst of submissions results in one recompute;
    - also started by the "Recompute" button.
- **Overview and group numbers** are cached per test. A version number in the cache is bumped on the same events, which invalidates them at once.

## Dashboards

- **Teacher:** open tests, submissions this week, answers waiting for grading, average pass rate, tests closing in 7 days, recent submissions, questions flagged for review.
- **Student:** exams available now, the exam in progress, latest visible results, a progress sparkline, and XP and level (filled by Stage 11).
- **Admin:**
    - totals: tests, submitted attempts, average pass rate;
    - the most active teachers and tests (30 days);
    - submissions per day (30 days, cached 5 minutes).

## CSV export

- **Columns:** student, email, groups, score, max score, percent, passed (1 / 0 / empty while not graded), submitted at, integrity events.
- **Access:** the owner or an admin; the file is streamed.
- **Safety:** every cell goes through `App\Support\SafeCsv`:
    - a cell starting with `=`, `+`, `-`, `@`, tab or CR gets a leading `'`, so spreadsheet programs show it as text instead of running it;
    - the file is UTF-8 with a BOM, so Excel shows Georgian correctly.
