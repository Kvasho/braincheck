# BrainCheck — Stage 1: Roles, Permissions & Access Control

> Instructions for Claude Code. Read this whole file before doing anything.
> Stage 0 is complete. All project guidelines from Stage 0 still apply: Synapse concept, architecture rules, security rules and testing expectations (see the project guideline file / `CLAUDE.md`).

---

## 1. Goal

Build the access-control foundation that every later stage relies on:

- Three roles: **Admin, Teacher, Student**.
- Granular **permissions**. Code checks permissions and policies, never role names directly.
- **Exactly one role per user.**
- Safe ways to create admins and demo users.
- Blocked users.
- A role-specific dashboard and navigation.
- An **access matrix test** that covers every route in the application, and fails when a new route is added without being classified.

**Security principle for this stage:** the UI only _hides_ things. The backend _enforces_ everything. Nothing the client sends can ever grant or change a role.

---

## 2. Working rules (same as Stage 0)

1. **One sub-step at a time** (1.1, 1.2, …). After each sub-step:
    - Stop and report: what you did, files changed, commands run, check results.
    - Note anything I must do manually.
    - Suggest a commit message.
    - **Wait for "continue".**
2. **Never** do any of the following without asking me first:
    - run `migrate:fresh`, `db:wipe`, or delete dev data;
    - `git push`;
    - edit `.env`;
    - weaken or skip a test or a security feature.
3. Run `composer check` and `npm run check` after every sub-step. Fix causes, not tests.
4. Use Boost's docs search for `spatie/laravel-permission`, Fortify and Inertia APIs matching the installed versions. Do not invent APIs.
5. **All new UI text in both `ka` and `en`**; the parity test must stay green.
6. All new UI follows the Synapse design system (tokens, layouts, `motion` with reduced-motion support).

---

## 3. Sub-steps

### 1.1 Roles and permissions as enums

**`App\Enums\Role`** (string-backed): `admin`, `teacher`, `student`. Add `label()` returning the translated name.

**`App\Enums\Permission`** (string-backed). Use `area.action` naming:

| Area         | Permissions                                                        |
| ------------ | ------------------------------------------------------------------ |
| Users        | `users.view`, `users.manage-roles`, `users.block`                  |
| Taxonomy     | `categories.manage`, `tags.manage`                                 |
| Audit        | `audit-log.view`                                                   |
| Questions    | `questions.view-own`, `questions.manage-own`, `questions.view-any` |
| Tests        | `tests.view-own`, `tests.manage-own`, `tests.view-any`             |
| Groups       | `groups.manage-own`, `groups.view-any`, `groups.join`              |
| Grading      | `attempts.grade-own-tests`                                         |
| Analytics    | `analytics.view-own-tests`, `analytics.view-any`                   |
| Exams        | `exams.take`, `results.view-own`                                   |
| Certificates | `certificates.view-own`                                            |
| Leaderboard  | `leaderboard.view`                                                 |

**Single source of truth for the role → permission mapping:** a static method `Role::permissions(): array`.

| Role        | Permissions                                                                                                          |
| ----------- | -------------------------------------------------------------------------------------------------------------------- |
| **Admin**   | All management, `*-any`, users and audit permissions. **Not** `exams.take` (admins don't take exams).                |
| **Teacher** | `*-own` question/test/group permissions, `attempts.grade-own-tests`, `analytics.view-own-tests`, `leaderboard.view`. |
| **Student** | `groups.join`, `exams.take`, `results.view-own`, `certificates.view-own`, `leaderboard.view`.                        |

Then:

- Set the spatie config so permission caching is on and the cache is reset whenever roles or permissions are synced.
- Use the `web` guard.

**Decision: no `Gate::before` super-admin bypass.**
A global bypass would skip ownership rules and business constraints (e.g. "admins can't take exams"). Admin rights come only from explicit permissions and from policies that handle admin cases deliberately. Add a short comment explaining this where policies are registered.

**Tests**

- `Role::permissions()` covers every `Permission` case at least once (no orphan permissions).
- Snapshot-style test of the full role → permission matrix, so any change is a visible, deliberate diff.

### 1.2 Syncing roles and permissions to the database

**`RolesAndPermissionsSeeder`**

- **Idempotent and safe to run in production.** It only creates or syncs roles and permissions from the enums. It never creates users.
- It removes permissions that are no longer in the enum, and resets the permission cache.
- Call it from `DatabaseSeeder`.
- Add `php artisan braincheck:sync-permissions`, a command that runs the same logic (it will be used during deploys).

**Tests**

- Running the seeder twice produces the same state.
- The database matches the enums exactly.
- A removed permission gets deleted.

### 1.3 User model: one role, blocked state

**`User` model**

- Use the spatie `HasRoles` trait.
- Add helpers:
    - `role(): Role` returns the single role as an enum;
    - `isAdmin()`, `isTeacher()`, `isStudent()`.

**`App\Actions\Users\ChangeUserRole`** (`final`, `handle(User $user, Role $role, User $actor)`)

- Uses `syncRoles()`, so the user always ends up with exactly one role.
- Wrapped in a DB transaction.
- Dispatches a `UserRoleChanged` event (actor, old role, new role) for the Stage 3 audit log.
- Guards:
    - an admin can't demote themselves;
    - the **last remaining admin** can never be demoted.

**Blocked users**

- Migration: nullable `blocked_at` timestamp on `users`, plus a `blocked_reason` nullable string.
- Blocked users can't log in. Hook into Fortify's authentication pipeline and return the same generic failure message as wrong credentials, so account state isn't revealed.
- `EnsureUserIsNotBlocked` middleware on all authenticated routes: if the user becomes blocked mid-session, log them out, invalidate the session, regenerate the CSRF token and redirect to login with a neutral flash message.
- Blocking itself (the admin UI) is Stage 3. Here, only the column, the login check, the middleware and tests.

**Tests**

- `ChangeUserRole` leaves exactly one role.
- The last admin can't be demoted, and an admin can't demote themselves.
- The event is dispatched.
- A blocked user can't log in, and the error message matches the wrong-password message.
- A user blocked mid-session is logged out on their next request.

### 1.4 Registration and admin creation

**Registration**

- Every self-registered user gets **Student**, assigned inside Fortify's `CreateNewUser` action.
- The role is **never** read from the request.
- Teacher accounts come from admins (Stage 3 adds a "request teacher access" flow).

**Admin creation:** `php artisan braincheck:make-admin`

- Interactive prompts (Laravel Prompts) for name, email and password; the password input is hidden and confirmed.
- Validates email uniqueness and `Password::defaults()`.
- Marks the email as verified.
- **No web route exists that can create an admin.**

**Demo users:** `DemoUsersSeeder`

- One admin, two teachers, five students. Known emails like `admin@braincheck.test`, all with one documented local-only password.
- **Refuses to run unless the environment is `local` or `testing`**: it throws with a clear message otherwise.
- Not called from `DatabaseSeeder` by default. It's run explicitly.

**Tests**

- Registering with an injected `role=admin` (and `roles[]=admin`) still creates a Student.
- The `make-admin` command creates a verified admin and rejects a weak password and a duplicate email.
- `DemoUsersSeeder` throws in `production`.

### 1.5 Route structure and middleware

- Register spatie's `role`, `permission` and `role_or_permission` middleware aliases in `bootstrap/app.php`.
- Every authenticated route uses `auth`, `verified` and `EnsureUserIsNotBlocked`.

**Route groups:**

| Prefix     | Name prefix | Middleware     |
| ---------- | ----------- | -------------- |
| `/admin`   | `admin.`    | `role:admin`   |
| `/teacher` | `teacher.`  | `role:teacher` |
| `/student` | `student.`  | `role:student` |

Inside each group, individual routes additionally use `permission:` middleware or policies in later stages.

- Keep the routes in separate files (`routes/admin.php`, `routes/teacher.php`, `routes/student.php`) loaded from `bootstrap/app.php`.
- Add **placeholder pages** (simple "coming soon" in Synapse style, translated) for each future section, so navigation and the matrix test have real routes:
    - Admin: `admin.users.index`, `admin.categories.index`, `admin.audit-log.index`
    - Teacher: `teacher.questions.index`, `teacher.tests.index`, `teacher.groups.index`, `teacher.grading.index`
    - Student: `student.groups.index`, `student.exams.index`, `student.results.index`
- **Landing redirect:** an authenticated user visiting `/` is redirected to `/dashboard`.

### 1.6 Role-specific dashboard and navigation

**Dashboard**

- `DashboardController` (single action) renders one of three Inertia pages, based on the user's role: `Dashboard/Admin`, `Dashboard/Teacher`, `Dashboard/Student`.
- Each page is a styled placeholder with welcome text and empty-state cards for the sections the role will have. Use the Synapse look.
- No real data yet; nothing leaks between roles.

**Inertia shared data** (extend `HandleInertiaRequests`)

- `auth.user.role`: the single role value.
- `auth.permissions`: a flat array of permission names.
- Keep the existing exact-keys test for `auth.user`, and extend it with the new key.

**Frontend**

- TypeScript types for `Role` and `Permission`, generated or written to match the PHP enums.
- A test or script that fails if the PHP and TS enums drift apart.
- A `usePermissions()` hook with `can(permission)` and `hasRole(role)`.
- Sidebar navigation built from a typed config, where each item declares the permission it needs. Items the user can't access aren't rendered.
- A comment in the hook states clearly that this is UI-only and the backend enforces access.
- Show the role as a badge in the user menu.

### 1.7 Access matrix test (the most important test of this stage)

Create `tests/Feature/Security/AccessMatrixTest.php`.

**The matrix**

- Define it as one dataset: every named route → expected HTTP outcome for each actor.
- Actors:
    - guest
    - unverified student
    - blocked student
    - student
    - teacher
    - admin
- Expected outcomes: `200`, `302→login`, `302→verify`, `403`, `404`, etc.
- Include POST/PUT/DELETE routes with the correct method and a valid CSRF setup.

**Completeness test**

- Iterate over `Route::getRoutes()` and assert that **every** application route appears in the matrix.
- Keep an explicit, commented allow-list of excluded routes: vendor routes (Telescope in local, Boost, Sanctum CSRF cookie, Fortify routes covered elsewhere, health check, local-only styleguide).
- Adding a new route without classifying it must make the test fail.

**Other checks**

- `/admin/*` returns 403 for teacher and student.
- `/teacher/*` returns 403 for student and admin.
- `/student/*` returns 403 for teacher and admin.
- The dashboard renders the right component per role.
- Shared `auth.permissions` per role equals exactly `Role::permissions()`.

### 1.8 Guidelines update

Add these rules to the project guideline file (and regenerate `CLAUDE.md` via Boost):

- Authorization checks use **permissions and policies**. Never `hasRole('...')` or role-name strings in controllers, actions or React components (`role()` enum helpers only for dashboard selection and display).
- Every new route must be added to the access matrix.
- Roles change only through `ChangeUserRole`.
- There is no `Gate::before` bypass.
- The frontend `can()` is cosmetic only.

---

## 4. Definition of done for Stage 1

- [ ] `composer check`, `npm run check` and `npm run build` are green.
- [ ] The access matrix covers 100% of application routes, and the completeness test passes.
- [ ] `php artisan braincheck:sync-permissions` and `braincheck:make-admin` work.
- [ ] `php artisan db:seed --class=DemoUsersSeeder` works locally.
- [ ] In the browser, logging in as each demo role shows the correct dashboard and navigation. Visiting another role's URL directly shows a styled 403 page (translated, Synapse style).
- [ ] A new registration is a Student.
- [ ] Blocking a user in the database (`blocked_at`) logs them out on their next request.
- [ ] Guidelines are updated.

Finish with a short report: what was done, any decisions or deviations, and anything I should test manually.
