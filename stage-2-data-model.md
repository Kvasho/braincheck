# BrainCheck — Stage 2: Data Model

> Instructions for Claude Code. Read this whole file before doing anything.
> Stages 0 and 1 are complete. All project guidelines still apply (Synapse concept, architecture, security, testing, access matrix, permissions-not-roles).

---

## 1. Goal

Design and build the **complete database layer** for all future stages:

- migrations
- enums
- models, relations and casts
- value objects
- factories
- a local-only demo content seeder
- **deny-by-default policies** for every model

No UI and no controllers in this stage, except what's needed to keep existing tests green.

### Integrity principles (apply everywhere)

1. **The database enforces the rules it can.**
    - Foreign keys with explicit `ON DELETE` behaviour.
    - Unique constraints.
    - `CHECK` constraints (MySQL 8.0.16+; verify the version).
    - NOT NULL wherever possible.
    - Application validation is the second layer, not the only one.
2. **Public identifiers are ULIDs.**
    - Every model exposed in URLs keeps an internal auto-increment `id` plus a unique `ulid` column, used as the route key.
    - Numeric IDs never appear in URLs or Inertia props, which blocks ID enumeration.
3. **Correct answers are secret by default.**
    - Columns containing correct answers are in the model's `$hidden`, so they never serialize accidentally.
    - Only dedicated, explicit code paths read them.
4. **Attempts are immutable history.**
    - When an attempt starts, each question is **snapshotted** into the attempt.
    - Editing or deleting a question later never changes past attempts, scores or certificates.
5. **Nothing important is hard-deleted.**
    - Questions, tests and groups use soft deletes.
    - Hard deletes are restricted by foreign keys when history depends on the row.
6. **Idempotency at the DB level** for things that must happen once: XP awards, badges, certificates, one active attempt.

---

## 2. Working rules (same as before)

1. **One sub-step at a time** (2.1, 2.2, …). After each sub-step:
    - Stop and report: what you did, files changed, commands run, check results.
    - Suggest a commit message.
    - **Wait for "continue".**
2. **Development database:**
    - Run normal `php artisan migrate` only.
    - **Ask before** any `migrate:fresh`, rollback or wipe on the `braincheck` dev database.
3. **Testing database:**
    - You may run `migrate:fresh` / `migrate:rollback` freely on the `braincheck_testing` database (`--env=testing` or via the test suite).
    - Double-check the connection before any destructive command.
4. Every migration must have a working `down()`.
5. `composer check`, `npm run check` and `npm run build` must be green after every sub-step.
6. Use Boost's docs search for version-specific Eloquent APIs (ULIDs, casts, enum casts, soft deletes). Don't invent APIs.

---

## 3. Sub-steps

### 2.1 Enums

Create these in `App\Enums`. All are string-backed with a translated `label()` (ka + en).

| Enum                    | Cases                                                                                                                                                                                                                                                           |
| ----------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `QuestionType`          | `single_choice`, `multiple_choice`, `true_false`, `text`, `numeric`, `ordering`, `matching`                                                                                                                                                                     |
| `Difficulty`            | `easy`, `medium`, `hard`                                                                                                                                                                                                                                        |
| `TestStatus`            | `draft`, `published`, `archived`                                                                                                                                                                                                                                |
| `QuestionSelectionMode` | `fixed` (questions attached manually), `random_pool` (N random from attached pool)                                                                                                                                                                              |
| `ResultsVisibility`     | `immediately`, `after_close`, `manual` (teacher releases), `never`                                                                                                                                                                                              |
| `AttemptStatus`         | `in_progress`, `submitted`, `timed_out` (auto-finished by the server)                                                                                                                                                                                           |
| `GradingStatus`         | `pending_auto`, `pending_manual`, `graded`                                                                                                                                                                                                                      |
| `AntiCheatEvent`        | `tab_hidden`, `window_blur`, `copy`, `paste`, `fullscreen_exit`, `devtools_suspected`                                                                                                                                                                           |
| `XpReason`              | `attempt_passed`, `perfect_score`, `first_attempt_pass`, `badge_awarded`, `streak`                                                                                                                                                                              |
| `Badge`                 | Start with ~8 meaningful badges, e.g. `first_test`, `perfect_score`, `ten_tests`, `speed_runner`, `comeback`, `streak_7`, `top_10_group`, `all_types`. Each has: translated name and description, icon name (lucide), XP reward. Award logic comes in Stage 11. |
| `TeacherRequestStatus`  | `pending`, `approved`, `rejected`                                                                                                                                                                                                                               |

Additional helpers:

- `QuestionType` gets `isAutoGradable(): bool` (`text` → false).
- **TS mirror:** add TypeScript equivalents for every enum used by the frontend, and extend the existing PHP↔TS drift test to cover them.

### 2.2 Question definition shapes (documentation + value objects)

Each question stores its type-specific content in a JSON `definition` column. Document the exact shapes in `docs/question-types.md`.

- Choice/item IDs are **random short strings** (e.g. 8 characters from an unambiguous alphabet), never indexes or sequential numbers. This way the order or ID of an option reveals nothing.
- The full grading logic is Stage 4, but write the shapes now.

| Type              | `definition`                                                                                            | Student `answer` shape       |
| ----------------- | ------------------------------------------------------------------------------------------------------- | ---------------------------- |
| `single_choice`   | `{ options: [{id, text}], correct: id }`                                                                | `{ selected: id }`           |
| `multiple_choice` | `{ options: [{id, text}], correct: [ids], scoring: "all_or_nothing" \| "partial" }`                     | `{ selected: [ids] }`        |
| `true_false`      | `{ correct: bool }`                                                                                     | `{ value: bool }`            |
| `text`            | `{ max_length: int, reference_answer: string \| null }` (reference is for the teacher only)             | `{ text: string }`           |
| `numeric`         | `{ correct: number, tolerance: number ≥ 0, unit: string \| null }`                                      | `{ value: number }`          |
| `ordering`        | `{ items: [{id, text}], correct_order: [ids] }`                                                         | `{ order: [ids] }`           |
| `matching`        | `{ left: [{id, text}], right: [{id, text}], pairs: [{left, right}] }` (`right` may contain distractors) | `{ pairs: [{left, right}] }` |

Create small readonly value objects / DTOs for these shapes under `App\Domain\Questions\Definitions` (or a structure that fits the project).

- Use a custom cast so `$question->definition` returns the right object for the question's type.
- Invalid JSON shapes throw.
- Add unit tests for the cast: round-trip, and rejecting malformed data.

### 2.3 Migrations: taxonomy and questions

**`categories`**

- `id`, `ulid` (unique), `parent_id` (nullable, self-FK, `ON DELETE RESTRICT`)
- `name` JSON `{ka, en}`, both required, enforced in app validation and the value object
- `slug` (unique), `sort_order` (unsigned small int, default 0), timestamps
- Maximum depth is 2 (enforced in the app later; document it).

**`tags`**

- `id`, `name` (unique), `slug` (unique), timestamps

**`questions`**

- `id`, `ulid` (unique)
- `owner_id` (FK users, `RESTRICT`)
- `category_id` (nullable FK, `SET NULL`)
- `type` (string enum), `difficulty` (string enum)
- `body` (text; rich text will be sanitized via Purifier on write in Stage 4)
- `explanation` (nullable text; shown after results if allowed)
- `definition` (JSON, **hidden**)
- `points` (decimal 6,2, default 1, `CHECK > 0`)
- `version` (unsigned int, default 1; incremented on content edits)
- timestamps, soft deletes
- Indexes: `(owner_id, deleted_at)`, `category_id`, `type`, `difficulty`

**`question_tag`**

- `question_id` (`CASCADE`), `tag_id` (`CASCADE`)
- primary key on both columns

### 2.4 Migrations: tests and groups

**`tests`**

- `id`, `ulid`
- `owner_id` (FK users, `RESTRICT`)
- `category_id` (nullable, `SET NULL`)
- `title`, `description` (nullable text)
- `status` (enum, default `draft`)
- `time_limit_minutes` (nullable unsigned small int, `CHECK` 1–600 when not null)
- `max_attempts` (nullable unsigned tiny int; null = unlimited; `CHECK ≥ 1` when not null)
- `pass_percent` (unsigned tiny int, `CHECK` 0–100)
- `shuffle_questions` (bool), `shuffle_options` (bool)
- `selection_mode` (enum), `random_question_count` (nullable unsigned small int, `CHECK ≥ 1`)
- `opens_at`, `closes_at` (nullable datetimes; `CHECK closes_at > opens_at` when both are set)
- `results_visibility` (enum), `show_correct_answers` (bool)
- `published_at` (nullable), timestamps, soft deletes
- Indexes: `(owner_id, status)`, `(status, opens_at, closes_at)`

**`test_question`**

- `test_id` (`CASCADE`), `question_id` (`RESTRICT`: a question used in a test can't be hard-deleted)
- `position` (unsigned small int)
- `points_override` (nullable decimal 6,2, `CHECK > 0`)
- Unique `(test_id, question_id)` and unique `(test_id, position)`

**`groups`**

- `id`, `ulid`
- `owner_id` (FK users, `RESTRICT`)
- `name`, `description` (nullable)
- `invite_code` (unique, 10 characters from an unambiguous uppercase alphabet, generated with a CSPRNG)
- `invite_code_expires_at` (nullable), `joining_enabled` (bool, default true)
- timestamps, soft deletes

**`group_user`**

- `group_id` (`CASCADE`), `user_id` (`CASCADE`), `joined_at`
- Primary key `(group_id, user_id)`, index `user_id`

**`group_test`** (assignment of tests to groups)

- `group_id` (`CASCADE`), `test_id` (`CASCADE`), `assigned_at`
- Primary key `(group_id, test_id)`

**`teacher_access_requests`**

- `id`, `user_id` (`CASCADE`), `message` (text, max length enforced in app)
- `status` (enum), `reviewed_by` (nullable FK users, `SET NULL`), `reviewed_at` (nullable)
- timestamps
- Only one pending request per user. Implement with a generated column + unique index, like the active-attempt lock below.

### 2.5 Migrations: attempts (the most sensitive part)

**`attempts`**

- `id`, `ulid`
- `test_id` (`RESTRICT`), `user_id` (`RESTRICT`)
- `attempt_number` (unsigned tiny int)
- `status` (enum), `grading_status` (enum)
- `started_at`, `deadline_at` (nullable: null when there's no time limit; **computed server-side only**), `submitted_at` (nullable), `graded_at` (nullable)
- `score`, `max_score` (nullable decimals 8,2), `score_percent` (nullable decimal 5,2), `passed` (nullable bool)
- `ip_address` (nullable, 45), `user_agent` (nullable, truncated to 255)
- timestamps

Constraints and indexes:

- Unique `(test_id, user_id, attempt_number)`. Prevents duplicate attempts under race conditions.
- **Only one `in_progress` attempt per user per test.** Add a stored generated column `active_lock` = `CASE WHEN status = 'in_progress' THEN 1 ELSE NULL END`, plus a unique index `(test_id, user_id, active_lock)`. MySQL allows multiple NULLs in a unique index, so this behaves like a partial unique index.
- Index `(status, deadline_at)` for the auto-finish job.
- Index `(user_id, created_at)`.

**`attempt_questions`**

- `id`
- `attempt_id` (`CASCADE`)
- `question_id` (nullable FK, `SET NULL`: history survives even if a question is somehow removed)
- `position` (unsigned small int)
- `snapshot` (JSON, **hidden**): the question's type, body, explanation, definition, points and version at start time, with option order already shuffled if enabled
- `points` (decimal 6,2)
- Unique `(attempt_id, position)`
- Unique `(attempt_id, question_id)`

**`attempt_answers`**

- `id`
- `attempt_question_id` (unique FK, `CASCADE`): exactly one answer row per question, updated by autosave
- `answer` (JSON, nullable)
- `answered_at` (nullable)
- `is_correct` (nullable bool), `awarded_points` (nullable decimal 6,2, `CHECK ≥ 0`)
- `needs_manual_grading` (bool)
- `graded_by` (nullable FK users, `SET NULL`), `graded_at` (nullable)
- `feedback` (nullable text)
- timestamps

**`attempt_events`** (anti-cheat log)

- `id`, `attempt_id` (`CASCADE`)
- `type` (enum), `occurred_at` (server time)
- `meta` (nullable JSON, size-limited in the app)
- Index `(attempt_id, occurred_at)`

### 2.6 Migrations: certificates, gamification, audit

**`certificates`**

- `id`, `ulid`
- `attempt_id` (**unique**, `RESTRICT`), `user_id` (`RESTRICT`), `test_id` (`RESTRICT`)
- `verification_code` (unique, 32 characters, CSPRNG)
- `holder_name`, `test_title`, `score_percent`: snapshots at issue time
- `issued_at`, `revoked_at` (nullable), `revoked_reason` (nullable)
- timestamps

**`user_badges`**

- `user_id` (`CASCADE`), `badge` (enum string), `awarded_at`
- Primary key `(user_id, badge)`, which makes the same badge impossible to award twice

**`xp_transactions`**

- `id`, `user_id` (`CASCADE`)
- `amount` (int, `CHECK <> 0`), `reason` (enum)
- `source_type` / `source_id` (nullable morph)
- `created_at`
- Unique `(user_id, reason, source_type, source_id)`. Makes awarding idempotent.

**`users`**: add

- `xp_total` (unsigned int, default 0; a cache maintained only by an Action inside a transaction)
- index on `xp_total` for the leaderboard

**`audit_logs`** (append-only)

- `id`, `actor_id` (nullable FK users, `SET NULL`)
- `action` (string, e.g. `user.role_changed`)
- `subject_type` / `subject_id` (nullable morph)
- `properties` (JSON)
- `ip_address`, `user_agent`
- `created_at` only
- The model **throws on update and delete**.
- Index `(subject_type, subject_id)`, `(actor_id, created_at)`, `action`

Morph map:

- Register `Relation::enforceMorphMap()` with short aliases for all morphable models, so class names are never stored in the DB.

### 2.7 Models

For every table, create a model with the following.

**`HasPublicUlid` trait** (or Laravel's `HasUlids` with `uniqueIds()` returning `['ulid']`; verify it keeps the integer primary key):

- generates the `ulid`;
- sets the route key name to `ulid`.

**Relations** with return types. Main ones:

- `User` → `questions`, `tests`, `ownedGroups`, `groups` (member), `attempts`, `certificates`, `badges`, `xpTransactions`
- `Question` → `owner`, `category`, `tags`, `tests`
- `Test` → `owner`, `category`, `questions` (ordered by pivot `position`), `groups`, `attempts`
- `Group` → `owner`, `members`, `tests`
- `Attempt` → `test`, `user`, `questions` (`AttemptQuestion` ordered by `position`), `events`, `certificate`
- `AttemptQuestion` → `attempt`, `question`, `answer`
- and so on for the rest.

**Casts**

- enums
- the definition cast
- `datetime` / `immutable_datetime`
- `decimal:2`
- the `LocalizedName` value object for category names

**Mass assignment**

- Explicit `$fillable`.
- **Never fillable:** `owner_id`, `user_id`, `status`, `score`, `xp_total`, `ulid`, `verification_code`, `invite_code`. These are set only inside Actions.

**`$hidden`**

- `Question::definition`, `AttemptQuestion::snapshot`, `Group::invite_code`, `Certificate::id`

**Query scopes** where obvious:

- `Test::published()`, `Test::openAt($time)`
- `Question::ownedBy($user)`
- `Attempt::inProgress()`, `Attempt::expired($now)`

Attribute rules:

- No model contains business logic beyond relations, casts, scopes and tiny helpers. Logic goes into Actions in later stages.
- `Model::shouldBeStrict` is already on, so make sure relations are eager-loaded in tests.

### 2.8 Policies (deny by default)

Create one Policy per model and register it (auto-discovery or explicit).

- **Every method returns `false` for now.** Only add rules that are trivially and safely defined now:
    - an owner can `view` / `update` their own draft question, test and group, if they have the matching `*-own` permission;
    - users with `*-any` permissions can `view` (admins);
    - a student can `view` their own attempt and certificate.
- Everything else stays `false` until its stage implements and tests it.
- **Enable the "every model has a Policy" arch test** that was skipped in Stage 0.

**Tests:** for every policy method, an unrelated user with no special permissions is denied.

### 2.9 Factories

Create one factory per model, with meaningful **states**:

| Factory              | States                                                                                                                           |
| -------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| `QuestionFactory`    | one per type, each producing a **valid** definition (random short IDs); `easy` / `medium` / `hard`                               |
| `TestFactory`        | `draft`, `published`, `archived`, `timed(minutes)`, `withAttemptLimit(n)`, `randomPool(n)`, `openWindow`, `closed`, `notYetOpen` |
| `GroupFactory`       | `withMembers(n)`, `expiredInvite`, `joiningDisabled`                                                                             |
| `AttemptFactory`     | `inProgress`, `submitted`, `timedOut`, `graded(passed: bool)`; must create consistent snapshot questions and answers             |
| `CertificateFactory` | `revoked`                                                                                                                        |

Factories respect ownership: the owner of a question or test is a user with the Teacher role.

### 2.10 Demo content seeder (local only)

`DemoContentSeeder`. Like `DemoUsersSeeder`, it **throws outside `local` / `testing`**. It depends on the demo users. It creates:

- **Categories:** ~6 bilingual categories with a couple of subcategories, e.g. მათემატიკა / Mathematics, ისტორია / History, პროგრამირება / Programming, ბიოლოგია / Biology, გეოგრაფია / Geography, ინგლისური / English.
- **Tags:** ~15.
- **Questions:** ~60 across both demo teachers, covering **every type and difficulty**. Real, sensible content mixing Georgian and English, not lorem ipsum.
- **Tests:** ~8 tests in different states and configurations (timed, untimed, random pool, limited attempts, open / closed / upcoming).
- **Groups:** ~3 groups with demo students, and tests assigned.
- **Attempts:** some finished, graded attempts with varied scores, so analytics in Stage 9 has data.

Document how to run it in the README:

```
php artisan db:seed --class=DemoUsersSeeder
php artisan db:seed --class=DemoContentSeeder
```

### 2.11 Data-model tests

- **Migrations:** on the testing DB, `migrate:fresh` followed by a full `migrate:rollback` succeeds.
- **Factories:** every factory and every state creates a valid record; key relations return the expected types and counts.
- **Secrets never serialize:** `toArray()` / `toJson()` of `Question`, `AttemptQuestion`, `Group` and `Certificate` contain no `definition`, `snapshot`, `invite_code` or internal `id` respectively.
- **DB constraints**, tested by direct inserts that must throw:
    - a second `in_progress` attempt for the same user and test;
    - a duplicate `attempt_number`;
    - a duplicate XP award for the same source;
    - a duplicate badge;
    - a second certificate for the same attempt;
    - `pass_percent` > 100;
    - `closes_at` before `opens_at`.
- **Foreign keys:** hard-deleting a question used in a test fails; deleting an attempt cascades its questions, answers and events; deleting a reviewer sets `reviewed_by` to null.
- **Route keys:** models resolve by `ulid`; a numeric id returns 404.
- **Audit log:** update and delete throw.
- **Morph map:** stored morph types are the short aliases, not class names.
- **Seeders:** `DemoContentSeeder` throws in production and runs cleanly in testing.
- **Mass assignment:** passing `owner_id`, `status`, `score` or `xp_total` through `fill()` / `create()` has no effect, or throws in strict mode; assert whichever matches the configuration.

### 2.12 ERD and guidelines

- Generate `docs/erd.md` with a **Mermaid ER diagram** of all tables and relations, plus one line per table describing its purpose.
- Add these rules to the project guidelines (and regenerate `CLAUDE.md` via Boost):
    - ULIDs in URLs and props; never numeric IDs.
    - Secret columns are `$hidden` and read only in dedicated code paths.
    - Attempts work from snapshots, never from live questions.
    - Ownership and status fields are set only inside Actions.
    - Every new table needs a Policy, a Factory, constraint tests and an ERD update.

---

## 4. Definition of done for Stage 2

- [ ] All migrations run and roll back cleanly on the testing DB.
- [ ] Normal `php artisan migrate` applies cleanly on the dev DB.
- [ ] `composer check`, `npm run check` and `npm run build` are green, including the now-enabled "every model has a Policy" arch test.
- [ ] Demo users + demo content seed locally, and the data looks realistic when browsed (Tinker or Herd's Adminer).
- [ ] `docs/question-types.md` and `docs/erd.md` exist and match the code.
- [ ] Guidelines are updated.

Finish with a short report: what was done, any deviations from this spec and why, and anything I should check manually.
