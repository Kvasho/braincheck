# BrainCheck — Stage 3: Admin Panel

> Instructions for Claude Code. Read this whole file before doing anything.
> Stages 0–2 are complete. All project guidelines still apply: Synapse concept, architecture, security, access matrix, permissions-not-roles, ULIDs, deny-by-default policies.

---

## 1. Goal

Build the admin area and the shared infrastructure it needs:

- **audit logging** for security-relevant events;
- **mandatory 2FA** for admins and teachers;
- **user management**: list, search, change role, block/unblock;
- the **teacher access request** flow (student requests → admin approves or rejects);
- **categories** (bilingual, 2-level tree) and **tags**;
- **audit log viewer**;
- a real **admin dashboard**;
- **reusable UI building blocks** (data table, filters, confirm dialogs) that later stages will reuse.

**Security principle for this stage:** admin power is the most dangerous power in the system. Every admin action must be:

- explicitly authorized (permission + policy);
- protected against mistakes (confirmation, password re-entry for sensitive actions);
- **recorded in the audit log**.

---

## 2. Working rules (same as before)

1. **One sub-step at a time** (3.1, 3.2, …). After each sub-step:
    - Stop and report: what you did, files changed, commands run, check results.
    - Suggest a commit message.
    - **Wait for "continue".**
2. Ask before any destructive command on the dev DB, `git push`, or `.env` edits.
3. `composer check`, `npm run check` and `npm run build` must be green after every sub-step. Every new route goes into the **access matrix**.
4. All UI text in `ka` + `en`. Follow the Synapse design system; use `motion` with reduced-motion support.
5. Controllers stay thin:
    - **Form Request** for input;
    - **Policy** for authorization;
    - **`final` Action** for logic;
    - **API Resource** for output.
      No models passed to Inertia directly.

---

## 3. Sub-steps

### 3.1 Audit logging infrastructure

**Action**

- `App\Actions\Audit\RecordAuditEvent` (`final`, synchronous).
- Writes to `audit_logs` with: actor, action key, subject (morph), properties, IP and user agent (from the current request when available; null in console/queue).

**`App\Enums\AuditAction`**, string-backed with translated labels. Namespaced keys:

| Area             | Keys                                                                                                                                                                                       |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Auth             | `auth.login`, `auth.login_failed`, `auth.logout`, `auth.password_changed`, `auth.password_reset`, `auth.two_factor_enabled`, `auth.two_factor_disabled`, `auth.recovery_codes_regenerated` |
| Users            | `user.role_changed`, `user.blocked`, `user.unblocked`                                                                                                                                      |
| Teacher requests | `teacher_request.submitted`, `teacher_request.approved`, `teacher_request.rejected`                                                                                                        |
| Categories       | `category.created`, `category.updated`, `category.deleted`                                                                                                                                 |
| Tags             | `tag.created`, `tag.updated`, `tag.deleted`                                                                                                                                                |

**Listeners**

- Laravel auth events: `Login`, `Failed`, `Logout`, `PasswordReset`.
- Fortify events for 2FA enable/disable and recovery codes.
- The existing `UserRoleChanged` event from Stage 1.
- For failed logins, store only the **attempted email** and the IP, never the password.

**Property sanitizer**

- Before storing, recursively strip keys matching:
  `password*`, `*token*`, `*secret*`, `recovery*`, `remember*`, `two_factor*`, `code`.
- Cap the JSON size (e.g. 8 KB). Oversized data is truncated with a marker.

**Tests**

- each event produces exactly one audit row with the right action, actor and subject;
- a failed login stores no password;
- sensitive keys are stripped, including nested ones;
- an oversized payload is truncated;
- the audit log remains append-only (Stage 2 tests still pass).

### 3.2 Mandatory 2FA for admins and teachers

**Middleware `EnsureTwoFactorEnabled`**, applied to the `/admin` and `/teacher` route groups:

- If the user hasn't **confirmed** 2FA, redirect to the security settings page with a translated explanation.
- Settings, logout and 2FA setup routes must stay reachable (no redirect loop).

**Config `braincheck.enforce_two_factor`**

- Default `true`.
- It can be turned off **only** when the environment is `local` (for convenient demo logins).
- In any other environment the code forces `true` regardless of `.env`.
- Document this in `.env.example`.

**Demo users:** the demo admin and teachers work in local with enforcement off. Document how to test with enforcement on.

**Tests**

- an admin/teacher without 2FA is redirected from `/admin/*` and `/teacher/*`;
- with confirmed 2FA they get through;
- students are unaffected;
- the setting can't be disabled in production (set the env to production in the test);
- no redirect loop on the security settings page.

### 3.3 Reusable admin UI building blocks

Build these once, generically, in `resources/js/components/data/`, so later stages reuse them.

**`DataTable`**

- Server-driven: rows come from Inertia props.
- Sortable column headers.
- Loading state through Inertia partial reloads (`only`).
- Empty state, responsive (stacks into cards on mobile), keyboard accessible.

**`FilterBar`**

- Search input (debounced ~300 ms), select filters, date range.
- State lives in the **URL query string**, so back/forward and shared links work.
- Uses Inertia `router.get` with `preserveState` / `preserveScroll` / `replace`.

**`Pagination`**

- Works with Laravel's paginator output, via a small shared Resource or helper for consistent meta.

**`ConfirmDialog`**

- Normal confirm.
- **Type-to-confirm** variant for dangerous actions: the user types the target's name or email.

**`StatusBadge`**, **`EmptyState`**, **`PageHeader`** (title, description, actions slot).

**Toasts:** flash messages from the backend (`success`, `error`, `warning`) automatically show as `sonner` toasts.

**Backend counterpart:** an `App\Support\Listing` helper (or trait) that every index query uses.

- **Search:** escapes LIKE wildcards (`%`, `_`, `\`) in user input.
- **Sort:** accepts only a **whitelist** of sortable columns and `asc`/`desc`. Anything else falls back to the default and never reaches SQL.
- **Page size:** `per_page` is clamped (default 20, max 100).
- **Filters:** validated through the Form Request (enums via `Rule::enum`).

**Tests for `Listing`**

- LIKE wildcards are escaped;
- an invalid or malicious sort column (e.g. `id; DROP TABLE users`) is ignored;
- `per_page=100000` is clamped.

### 3.4 User management

**Routes** under `/admin/users`, all with permission middleware plus policies:

- `index`: permission `users.view`. Search by name/email; filter by role, blocked status, verified status; sort by name, created date or XP.
- `show`: profile summary.
    - Role, status, created/verified dates, 2FA enabled (yes/no only).
    - Counts of owned questions/tests/groups or attempts.
    - The user's latest audit events.
- `update-role`: permission `users.manage-roles`.
- `block` / `unblock`: permission `users.block`.

**`UserResource` for admin listings** returns only what the UI shows:

- `ulid`, name, email, role, verified, blocked, `has_two_factor` (boolean), `xp_total`, `created_at`.
- **Never** 2FA secrets, recovery codes, remember tokens or password hashes. Add an exact-keys test, like the shared-props test.

**Changing role**

- Uses the Stage 1 `ChangeUserRole` Action.
- Requires **password confirmation** (Laravel's `password.confirm` middleware, or Fortify's confirm flow).
- Confirm dialog in the UI.
- Writes an audit entry.

**Blocking: `BlockUser` / `UnblockUser` Actions**

- Requires a reason (stored in `blocked_reason`) and password confirmation.
- Type-to-confirm dialog in the UI.
- Can't block yourself; can't block the last active admin.
- **When blocking, kill all access immediately:**
    - delete all of the user's rows from the `sessions` table;
    - rotate `remember_token`;
    - (Stage 1 middleware already handles in-flight requests.)
- Audit entry for both block and unblock.

**Tests**

- access matrix entries;
- the role change requires password confirmation;
- self-demotion and last-admin protections still hold through the HTTP layer;
- blocking deletes sessions and rotates the remember token, so a "remember me" cookie no longer logs the user in;
- self-block is rejected; last-admin block is rejected;
- audit rows are written;
- the user listing's search, filter and sort work, and the injection cases are rejected (via `Listing`);
- the resource's exact keys.

### 3.5 Teacher access request flow

**Student side** (`/student/teacher-request`, permission `groups.join`, or add a dedicated `teacher-request.create` permission to the Student role via the enum + sync):

- A page explaining the teacher role, with a form containing a message (max 1000 characters).
- Shows the status of the current request (pending / rejected with date).
- **Rate limited:** 3 per day per user.
- DB unique pending constraint (Stage 2) + validation: one pending request at a time.
- After a rejection, a new request is allowed only after 7 days (configurable).

**Admin side** (`/admin/teacher-requests`, permission `users.manage-roles`):

- List with filter by status (pending first).
- Approve / reject with an optional note.

**`ApproveTeacherRequest` Action**

- In a transaction with `lockForUpdate` on the request row, to stop two admins from double-processing.
- Sets the status and reviewer, calls `ChangeUserRole` (→ Teacher).
- Audits.
- Notifies the user by email (**queued**; database queue driver).

**`RejectTeacherRequest` Action**: status, reviewer, note, audit, queued email.

**Emails**

- Translated, using the user's locale (Stage 0 locale preference).
- Simple Synapse-styled Markdown mail template.

**Navigation**

- Pending count badge in the admin sidebar.
- On the student side, a dashboard card linking to the request page.

**Tests**

- a student can submit; a duplicate pending request is rejected;
- the rate limit returns 429;
- the 7-day cooldown is enforced;
- teachers and admins can't submit;
- approve makes the user a Teacher (exactly one role) and writes an audit entry;
- reject writes an audit entry;
- concurrency: approving an already-processed request is a no-op or an error, never a double change;
- mails are queued to the right user in the right locale (`Mail::fake` / `Queue::fake`).

### 3.6 Categories

**Routes:** `/admin/categories`, permission `categories.manage`.

**UI**

- Tree view: parents with nested children, drag & drop reorder using `@dnd-kit` (persists `sort_order`).
- Create/edit in a dialog, with Georgian and English name fields (both required) and an optional parent.

**Rules**

- **Maximum depth 2:** a child can't have children; a category with children can't become a child.
- `slug` is auto-generated from the English name, unique, editable, validated with a slug regex.
- **Delete:**
    - blocked if the category has children;
    - if questions or tests use it, show the counts in a type-to-confirm dialog; on confirm, those references become null (FK `SET NULL`).
- Reordering accepts only ULIDs of existing categories at the same level. Validate the whole payload; nothing partial.

**Audit** create, update and delete.

**Cache** the category tree (it's used in many selects later). Invalidate on any change.

**Tests**

- depth rule (both directions);
- both locale names required;
- slug uniqueness and format;
- delete with children blocked;
- delete with questions nulls the references;
- reorder payload validation (a foreign ULID or a cross-level move is rejected);
- cache invalidation;
- audit entries;
- access matrix.

### 3.7 Tags

**Routes:** `/admin/tags`, permission `tags.manage`.

**UI**

- Table with name, slug and usage count (questions).
- Search; create/edit inline or in a dialog.

**Rules**

- Unique name and slug.
- **Merge tags** (an admin convenience): merge tag A into B moves all question links to B without duplicates, then deletes A.
    - One transaction.
    - Type-to-confirm.
    - Audit entry.
- Delete with the usage count shown and confirmation.

**Tests:** uniqueness; merge correctness (no duplicate pivot rows, A removed); audit entries; access matrix.

### 3.8 Audit log viewer

**Route:** `/admin/audit-log`, permission `audit-log.view`. Read-only.

**UI**

- `DataTable` with a translated action label, actor (link to the user page), subject, IP, time (with the full timestamp on hover).
- **Filters:** action (multi-select), actor (search), date range, subject type.
- **Details drawer** shows `properties` as a formatted, **escaped** key–value view. Never render raw HTML from properties.

Large table: always paginated, indexes from Stage 2 used.

**Tests**

- filters work;
- teachers and students get 403;
- no route exists to update or delete entries;
- rendering safety: a property value containing `<script>` comes back as plain text in the props (escaping by React is verified by the "no `dangerouslySetInnerHTML`" ESLint rule).

### 3.9 Admin dashboard

Replace the Stage 1 placeholder in `Dashboard/Admin` with real data.

**Stat cards**

- users by role;
- new users in the last 7 days;
- blocked users;
- pending teacher requests (links to the list).

**Recent activity:** the last 10 audit events.

**Security panel**

- failed logins in the last 24 hours (top IPs, top attempted emails);
- admins/teachers without 2FA (should be 0 when enforcement is on).

**Chart:** registrations over the last 30 days (`recharts`, Synapse colors, accessible labels).

**Performance:** all numbers in one service/query class, cached for 60 s. No N+1 queries (strict mode will catch it).

**Tests:** correct numbers from seeded data; cache works; only admins can see it.

### 3.10 Guidelines and docs

Add to the project guidelines (and regenerate `CLAUDE.md` via Boost):

- Every state-changing admin action goes through an Action that records an audit event.
- Sensitive admin actions require password confirmation and a confirm dialog.
- All index pages use `Listing` (escaped search, whitelisted sort, clamped page size) and the shared `DataTable` / `FilterBar` components.
- Never put secrets in audit properties; the sanitizer is a safety net, not a license.

Update `docs/erd.md` if anything changed.

---

## 4. Definition of done for Stage 3

- [ ] `composer check`, `npm run check` and `npm run build` are green. The access matrix covers all new routes.
- [ ] In the browser, as the demo admin:
    - [ ] search, filter and sort users;
    - [ ] change a role (password confirmation shown);
    - [ ] block a user, and confirm that their open session in another browser is logged out immediately.
- [ ] As a demo student, request teacher access. As admin, approve it. The email appears in the log/mail catcher, and the user now has the Teacher dashboard.
- [ ] Categories: create, nest, reorder by drag & drop, delete rules behave as specified.
- [ ] Tags: create, merge, delete.
- [ ] The audit log shows all of the above with correct actors, and no secrets in properties.
- [ ] With `enforce_two_factor=true`, admin/teacher areas require confirmed 2FA.
- [ ] Guidelines are updated.

Finish with a short report: what was done, any deviations and why, and anything I should test manually.
