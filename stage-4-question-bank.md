# BrainCheck — Stage 4: Question Bank

> Instructions for Claude Code. Read this whole file before doing anything.
> Stages 0–3 are complete. All project guidelines still apply: Synapse concept, architecture, security, access matrix, permissions-not-roles, ULIDs, deny-by-default policies, audit logging, `Listing` + `DataTable`.

## Working rules (same as before)

- **One sub-step at a time.** After each one: report, suggest a commit message, and **wait for "continue"**.
- Ask before destructive dev-DB commands, `git push`, or `.env` edits. Never weaken tests or security.
- `composer check`, `npm run check` and `npm run build` must be green after every sub-step.
- Every new route goes into the access matrix. All UI text in ka + en.
- Use Boost docs search; don't invent APIs.

---

## 1. Goal

Teachers create, edit, organise and preview questions of all 7 types. This stage builds the **"Synapse module" architecture**: every question type is a plug-in made of

- one backend handler class, and
- three frontend components (`Editor`, `Player`, `Review`).

Stages 7–9 (exam engine, grading, results) reuse exactly these modules.

**Security focus:** correct answers never leave the server in student-facing payloads; rich text can't carry scripts; uploaded images can't carry payloads; teachers can only touch their own questions.

---

## 2. Sub-steps

### 4.1 Backend: QuestionType handler contract and registry

Create `App\Domain\Questions\Types\QuestionTypeHandler`, an interface with these methods:

| Method                                                                       | Purpose                                                                                                                 |
| ---------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `type(): QuestionType`                                                       |                                                                                                                         |
| `definitionRules(): array`                                                   | Laravel validation rules for the `definition` payload                                                                   |
| `normalizeDefinition(array $input): Definition`                              | Generates random short IDs for new options/items, trims text, returns the value object from Stage 2                     |
| `answerRules(Definition $d): array`                                          | Validation rules for a student answer _against this specific definition_ (IDs must exist, no duplicates, bounded sizes) |
| `grade(Definition $d, ?array $answer, Points $points): GradeResult`          | Returns a `GradeResult` value object: `isCorrect: ?bool`, `awardedPoints`, `needsManualGrading: bool`                   |
| `toPlayerPayload(Definition $d, Randomizer $r, bool $shuffle): array`        | What a student sees while answering. **Must never contain correct-answer information.**                                 |
| `toReviewPayload(Definition $d, ?array $answer, bool $revealCorrect): array` | What is shown after grading                                                                                             |

Supporting pieces:

- `QuestionTypeRegistry` resolves a handler by enum. Register all handlers in a service provider.
- **Money-safe math:** add `brick/math` and implement a `Points` value object (`BigDecimal`, scale 2) for all point arithmetic. No floats for scores anywhere.
- **Randomness:** all shuffling uses `Random\Randomizer` with the `Secure` engine (or a seeded engine only where reproducibility is explicitly needed).
- **Arch test:** forbid `rand`, `mt_rand`, `shuffle`, `array_rand`, `str_shuffle` and `uniqid` in `app/`.

### 4.2 Implement the handlers, in this order

**1. `single_choice`**

- 2–10 options, exactly one correct, option text 1–500 characters.
- Grade: full points or 0.

**2. `multiple_choice`**

- 2–10 options, at least 1 correct.
- `scoring` is `all_or_nothing` or `partial`.
- Partial formula: `max(0, (correctSelected − wrongSelected) / totalCorrect) × points`, rounded to 2 decimals (half up).
- Document the formula in `docs/question-types.md`.

**3. `true_false`**

- Grade: full points or 0.

**4. `numeric`**

- `correct` and `tolerance` are finite decimals, stored as strings.
- Compare with `brick/math`: `|value − correct| ≤ tolerance`.
- Optional unit is display only.

**5. `text`**

- `max_length` 1–5000.
- Always `needsManualGrading = true`, awarded points 0 until graded.
- An empty answer is auto-graded as 0, with no manual grading needed.

**6. `ordering`**

- 2–10 items.
- `scoring` is `all_or_nothing` or `partial`; partial = share of items in the correct position.

**7. `matching`**

- 2–10 pairs, plus 0–5 distractors on the right side.
- Partial credit per correct pair.

**Answer validation for every type**

- unknown IDs → 422;
- duplicates → 422;
- wrong structure → 422;
- size limits enforced.

**Unit tests per handler**

- definition validation: valid and every invalid branch;
- normalization generates unique random IDs;
- grading: full, partial, zero, empty or null answer, boundary rounding;
- **leak test:** `toPlayerPayload()` contains no `correct`, `correct_order`, `pairs`, `is_correct`, `reference_answer` or `tolerance` keys, and no field from which the answer can be derived. Use a recursive key/value scan helper reused in Stage 7;
- with shuffle on, option order differs across seeds, while the IDs stay stable.

### 4.3 Rich text for question body and explanation

**Decision:** store the body as **ProseMirror JSON** (from a Tiptap editor), **not HTML**. Render it with a custom React renderer that maps allowed nodes to React elements. As a result, no HTML string is ever injected, and `react/no-danger` stays enforced.

**Frontend**

- Add `@tiptap/react`, `@tiptap/pm`, `@tiptap/starter-kit`, and only the extensions needed: subscript/superscript, code block.
- **Allowed nodes:** `doc`, `paragraph`, `text`, `heading` (levels 3–4), `bulletList`, `orderedList`, `listItem`, `codeBlock`, `hardBreak`, `blockquote`.
- **Allowed marks:** `bold`, `italic`, `underline`, `code`, `subscript`, `superscript`.
- **No links, no images inside text, no raw HTML.**
- A `RichText` React component renders the JSON via the allowlist. Unknown nodes render nothing, and dev mode logs a warning.

**Backend: `RichTextDocument` validator/value object**

- Recursively validates against the same allowlist (node types, marks, attributes).
- Rejects unknown nodes, attributes and marks.
- Limits: nesting depth ≤ 8, total text length ≤ 10,000 characters, JSON size ≤ 64 KB.
- Extracts plain text.

**Migration** on `questions`:

- `body` and `explanation` become JSON;
- add `body_text` (plain text, for search and listings), with an index suitable for LIKE prefix search.

**Tests**

- valid document accepted;
- script-like content stays inert text;
- unknown node/mark/attribute rejected;
- depth/size limits enforced;
- plain-text extraction is correct;
- a frontend renderer test (or TS-level guarantee) that only allowlisted nodes render.

### 4.4 Question images (optional per question, 1 image)

**Package:** add `intervention/image` (check compatibility).

**Upload rules**

- `image` validation: JPEG / PNG / WebP only, ≤ 4 MB, max 4000×4000 px.
- **No SVG** (SVG can contain scripts).
- **Re-encode every upload:** decode and re-encode to WebP, max width 1600. This strips EXIF/GPS metadata and destroys polyglot payloads.
- Store on a **private disk** under a random name.
- Never trust the client filename or MIME type.

**Serving**

- Through a controller route that authorizes access:
    - the owner teacher;
    - admin;
    - a student with an active or reviewable attempt containing that question (this rule is implemented in Stage 7; deny for now).
- Response headers: `Content-Type` from the stored file, `X-Content-Type-Options: nosniff`, `Cache-Control: private`.

**Snapshots:** the image reference is copied into attempt snapshots (Stage 7), so old attempts keep the image even if the question changes. Deleting an image only removes it once no snapshot references it, or keep images forever and document that.

**Tests**

- PHP code renamed to `.jpg` is rejected;
- SVG rejected;
- oversize rejected;
- EXIF stripped (check output metadata);
- output is WebP;
- another teacher gets 403;
- path traversal in the route parameter is impossible (ULID-based lookup).

### 4.5 Teacher question CRUD

**Routes:** under `/teacher/questions`, with `questions.view-own` / `questions.manage-own` permissions and policies. Policies now get real rules: **owner only**.

**Index (`DataTable`)**

- Search on `body_text`.
- Filters: category (tree select), tags, type, difficulty, archived.
- Sort: created, updated, difficulty.
- Columns: excerpt, type badge, difficulty, category, tags, used-in-tests count, updated.

**Create**

- Choose a type (cards with icon and description), then the type's `Editor` component, plus common fields: body (rich text), explanation, category, tags (only existing tags; Stage 3 manages tags), difficulty, points, image.

**Edit**

- Same editor.
- If content changes (body, definition or points), `version` increments.
- If the question is used in a **published test that already has attempts**, editing is still allowed (attempts use snapshots), but show a notice in the UI.

**Other actions**

- **Duplicate:** a new question with fresh option IDs.
- **Archive** (soft delete) / **restore**. A question used in a published test can be archived, but stays in that test until removed (Stage 5 shows a warning).
- **Preview as student:**
    - renders the `Player` component using `toPlayerPayload()`, exactly the code path students will use;
    - a "check answer" button calls a preview-grade endpoint that returns a `GradeResult` (owner only, rate limited).
- **Bulk actions:** add/remove tags, archive. Validate that every ULID belongs to the current teacher; if any doesn't, the whole request fails with 403 (no partial processing).

**Admin read-only browse** at `/admin/questions` (`questions.view-any`), for moderation: view and preview only, no editing.

**Actions:** `CreateQuestion`, `UpdateQuestion`, `DuplicateQuestion`, `ArchiveQuestion`, `RestoreQuestion`, `BulkTagQuestions`. Each is `final` and transactional. Definitions are normalized through the handler.

### 4.6 Frontend modules (the Synapse registry)

**Structure**

- `resources/js/question-types/{type}/` containing `Editor.tsx`, `Player.tsx`, `Review.tsx`, `types.ts`.
- `resources/js/question-types/registry.ts` is typed as `Record<QuestionType, QuestionTypeModule>`, so a missing type is a TypeScript compile error.

**Editors**

- Add/remove/reorder options with `@dnd-kit` and `auto-animate`.
- Mark correct answers; inline validation messages that mirror the backend rules.
- Keyboard accessible.

**Players**

- Controlled components, `value` + `onChange`. They know nothing about correctness.
- `ordering` and `matching`: `@dnd-kit` with pointer, touch and **keyboard sensors**. Matching also offers a select-based fallback for accessibility.

**Reviews**

- Show the student's answer, the correct answer (only if the payload includes it), and per-option correctness, using Synapse success/destructive tokens plus icons (never colour alone).
- Gentle `motion` "neuron pulse" on correct items, honouring reduced motion.

**Drift test:** the PHP enum, the TS enum and the registry keys all match.

### 4.7 Tests for this stage

- Access matrix for all new routes.
- **IDOR:**
    - teacher B can't view, edit, duplicate, archive, preview or bulk-edit teacher A's questions (403/404);
    - a bulk request mixing own and foreign ULIDs fails entirely.
- Students and admins can't reach the teacher routes; admins can reach the read-only admin routes.
- Validation for every type through the HTTP layer.
- Version increments only on content changes.
- The preview payload passes the leak scan.
- Audit is **not** needed for question CRUD (it's not a security event). Instead, rely on `updated_at` + `version`.
- Browser test: create one question of each type, preview it, answer it correctly, and see a full score.

---

## 3. Definition of done

- [ ] All 7 types can be created, edited, duplicated, archived, restored and previewed in the browser.
- [ ] Leak scan, IDOR, upload and rich-text tests are green. `composer check`, `npm run check` and `npm run build` are green.
- [ ] `docs/question-types.md` is updated with the scoring formulas and the rich-text allowlist.
- [ ] The guidelines (and `CLAUDE.md` via Boost) record:
    - new question types are added only via handler + registry modules;
    - all point math uses `Points`;
    - CSPRNG only;
    - rich text is JSON + allowlist, never HTML.

Finish with a short report: what was done, deviations, and anything to check manually.
