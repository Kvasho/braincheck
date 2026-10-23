# BrainCheck — Stage 6: Groups

> Instructions for Claude Code. Read this whole file before doing anything.
> Stages 0–5 are complete. All project guidelines still apply.

## Working rules (same as before)

- **One sub-step at a time.** After each one: report, suggest a commit message, and **wait for "continue"**.
- Ask before destructive dev-DB commands, `git push`, or `.env` edits. Never weaken tests or security.
- `composer check`, `npm run check` and `npm run build` must be green after every sub-step.
- Every new route goes into the access matrix. All UI text in ka + en.

---

## 1. Goal

Teachers create groups (classes), invite students with a code, and assign published tests to groups. Students join with a code, see their groups, and can leave.

**Security focus**

- Invite codes can't be brute-forced or enumerated.
- Students never see other students' personal data.
- Teachers only manage their own groups.

---

## 2. Sub-steps

### 6.1 Teacher: group management

Route: `/teacher/groups`, with `groups.manage-own` and policies (owner only).

**Index:** name, member count, assigned tests count, joining status, invite expiry.

**Create / edit:** name (3–100), description (≤ 1000), joining enabled, invite expiry (none, 1 day, 7 days, 30 days, or a custom date).

**Group page, with tabs:**

**Overview**

- Stats: members, assigned tests, recent activity.

**Invite**

- The invite code is **hidden by default**, with a "Reveal" button (it's `$hidden` on the model; returned only by a dedicated endpoint, owner only).
- Copy code; copy join link (`/join?code=XXXX`); QR code of the join link (SVG via `bacon/bacon-qr-code` v3, rendered as a React-safe SVG component or an `<img>` with a data URI; no `dangerouslySetInnerHTML`).
- **Regenerate code** (the old code stops working immediately; confirm dialog).
- Toggle joining.

**Members**

- `DataTable` with name, email (visible to the owner teacher only), joined date, attempts in this group's tests.
- Remove a member (confirm).

**Tests**

- Assign the teacher's own **published** tests (picker).
- Unassign.
- Shows each test's window and status.

**Actions:** `CreateGroup`, `UpdateGroup`, `RegenerateInviteCode`, `RemoveGroupMember`, `AssignTestToGroup`, `UnassignTestFromGroup`, `ArchiveGroup`.

**Invite code generator**

- CSPRNG, 10 characters from an unambiguous alphabet (no 0/O/1/I/L).
- Retry on collision.
- Unit-test the alphabet and length.

**Removing a member**

- They can no longer see or start the group's tests.
- An **in-progress attempt may still be finished** until its deadline (document this).
- Past results stay.

**Unassigning a test:** same rule as removal.

### 6.2 Student: joining and groups

Routes:

- `/student/groups` (my groups)
- `/student/groups/join` (form)
- `/join?code=` (pre-fills the form; requires login; after login, the user is redirected back with the code preserved)

**Joining:** `POST /student/groups/join`, with the `join-group` rate limiter (5/min per user+IP) **plus** a stricter daily cap per user (e.g. 30/day).

**`JoinGroup` Action**

- Looks up the code case-insensitively after normalising (trim, uppercase).
- Checks that joining is enabled, the code isn't expired, and the group isn't archived.
- Joining is **idempotent**: if already a member, show a success message.
- **The same generic error message** for wrong, expired or disabled codes, so attackers learn nothing.
- Log repeated failures as an audit event `group.join_failed` (sanitized: no full code stored; store the first 2 characters + length).

**My groups:** cards with group name, teacher name, assigned tests count, and a link to the exams list filtered by group.

**Leave group:** confirm dialog; same consequences as removal.

**Privacy:** students see the teacher's name, but **never** the list of other members or their emails.

### 6.3 Visibility rule (used by Stage 7)

Create a single query/service `App\Domain\Tests\AvailableTests::forStudent(User $u)`. A test is visible when:

- it's **published** (not archived);
- it's assigned to **at least one group the student is currently a member of**;
- the group isn't archived.

Every student-facing test lookup must go through this service. **Never** look up tests by ULID alone.

**Tests:** a matrix of membership × assignment × status × group archived.

### 6.4 Tests

- Access matrix; IDOR (another teacher's groups: view, reveal code, regenerate, remove member, assign).
- Assigning another teacher's test, or a draft test → 403/422.
- **Brute force:** 6 quick attempts → 429; the daily cap works; all failure types return an identical response body and status.
- Expired and disabled codes; regeneration invalidates the old code.
- Idempotent join.
- Removed/left members lose visibility of tests but can finish an in-progress attempt (completed in Stage 7; add a pending test or skip with a reason).
- Students can't see member lists or emails, checked through the props.
- `/join?code=` → login → back to the join form with the code pre-filled.

---

## 3. Definition of done

- [ ] Demo teacher: creates a group, reveals and copies the code, shows the QR, assigns a test.
- [ ] Demo student: joins with the code, sees the group, leaves.
- [ ] Brute-force protection is verified.
- [ ] All checks are green. Guidelines record: student test visibility goes only through `AvailableTests`.

Finish with a short report.
