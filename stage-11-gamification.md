# BrainCheck — Stage 11: Gamification (XP, Levels, Badges, Leaderboards)

> Instructions for Claude Code. Read this whole file before doing anything.
> Stages 0–10 are complete. All project guidelines still apply.

## Working rules (same as before)

- **One sub-step at a time.** After each one: report, suggest a commit message, and **wait for "continue"**.
- Ask before destructive dev-DB commands, `git push`, or `.env` edits. Never weaken tests or security.
- `composer check`, `npm run check` and `npm run build` must be green after every sub-step.
- Every new route goes into the access matrix. All UI text in ka + en.

---

## 1. Goal

Motivate students with XP, levels, badges and leaderboards, the "lit neurons" of Synapse. The system must be:
- **un-farmable:** no XP from retrying the same test over and over, and none from previews;
- **consistent:** `xp_total` always equals the sum of transactions;
- **privacy-respecting:** students can hide from public leaderboards.

---

## 2. Sub-steps

### 11.1 XP rules

Config: `config/gamification.php`. All values configurable.

**Awards** (only for real, fully graded attempts):

| Reason | Amount | Notes |
|---|---|---|
| `attempt_passed` | base 50 × difficulty multiplier (avg question difficulty: easy 1.0, medium 1.25, hard 1.5) | **Once per test per student.** The source is the test, not the attempt. |
| `perfect_score` | +25 | Once per test. |
| `first_attempt_pass` | +20 | Passed on attempt #1. |
| `streak` | +10 per day | Passed something on consecutive days, in Asia/Tbilisi day boundaries. |
| `badge_awarded` | badge's XP reward | |

**New enum case** `regrade_adjustment`: a regrade from pass → fail creates **negative** compensating transactions for the XP that the pass produced.

**Levels:** XP needed for level n = `round(100 × n^1.5)`, cumulative. A `Level` value object exposes level, progress % and XP to the next level.

### 11.2 `AwardXp` Action

- Transaction: insert the `xp_transactions` row, then `increment('xp_total', amount)` on the user (row lock).
- A unique violation (duplicate source/reason) → a silent no-op. This is the idempotency guarantee from Stage 2.
- Listeners on `AttemptGraded` / `AttemptRegraded`, queued and after commit, with `ShouldBeUnique` per attempt.

**Consistency command `xp:verify`:** recomputes sums, reports mismatches, and fixes them with `--fix` (audited). Schedule it daily.

### 11.3 Badges

**`BadgeRule` interface** with one class per `Badge` enum case (the strategy pattern again): `evaluate(User $u, ?Attempt $trigger): bool`.

**`EvaluateBadges` Action:** runs relevant rules after XP awards; awards new badges (PK prevents duplicates), plus the badge XP.

**Rules**, for the initial badges:

| Badge | Condition |
|---|---|
| `first_test` | first graded real attempt |
| `perfect_score` | any 100% result |
| `ten_tests` | 10 distinct tests passed |
| `speed_runner` | passed using ≤ 50% of the time limit |
| `comeback` | passed a test after failing it before |
| `streak_7` | 7-day streak |
| `top_10_group` | in the top 10 of a group's monthly leaderboard (evaluated by a scheduled job at month end) |
| `all_types` | correctly answered every question type at least once |

**Tests:** each rule's positive and negative cases.

### 11.4 Leaderboards

**Privacy setting:** a `show_on_leaderboard` boolean on users, **default true**, toggle in profile settings.
- When off, the user is hidden from global and group boards for others, but still sees their own rank privately.

**Boards**
- **Global:** all-time (`xp_total`), monthly and weekly (sum of transactions in the period, Asia/Tbilisi boundaries). Only Students appear.
- **Group:** only members of that group and its teacher can view it. Same periods, XP earned from that group's tests only (join through attempt → test → group).

**Display:** rank, display name (first name + last initial; **never** email), level, XP, top badges.
- Highlight the current user's row.
- Show "your rank: #N" even if outside the top 50.

**Performance:** cache each board for 5 minutes and invalidate on XP events (rate-limited invalidation).

### 11.5 UI

**Header widget:** level ring + XP bar (`motion` animated counter).

**Events**
- **XP gained:** a toast with an animated "+50 XP" number.
- **Level up:** a modal with confetti and the new level.
- **Badge unlocked:** a toast with the badge icon and a pulse animation.
- All respect reduced motion. **Never shown inside the exam player**; queue them for the result page.

**Badges page:** grid of all badges, locked ones greyed out with a hint of the requirement, unlocked ones with a date.

**Leaderboard page:** tabs (global / my groups) × period, with a podium animation for the top 3.

**Student dashboard:** fill the Stage 9 placeholders.

### 11.6 Tests

- **Farming:** passing the same test twice gives `attempt_passed` once; previews give nothing; duplicate events give nothing.
- **Consistency:** after random award sequences (property test), `xp_total == SUM(amount)`; the `xp:verify` command detects tampering.
- **Regrade:** the negative adjustment is applied once.
- **Streaks** across the Asia/Tbilisi midnight (`travelTo`), including a UTC/local mismatch case.
- **Level formula** boundaries.
- Every badge rule.
- **Leaderboard privacy:** an opted-out user is hidden for others but sees their own rank; a group board is visible only to members and the owner teacher; no emails in the props.
- **Periods:** weekly/monthly sums are correct at boundaries.

---

## 3. Definition of done

- [ ] Demo students earn XP, level up and unlock badges with animations.
- [ ] Leaderboards work with privacy rules; consistency is guaranteed.
- [ ] All checks are green. `docs/gamification.md` lists all rules and formulas.

Finish with a short report.
