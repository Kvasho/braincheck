# Question types

Each question stores its type-specific content in the JSON `definition` column
(`questions.definition`, copied into `attempt_questions.snapshot` when an attempt starts).

- The column is **secret**: it contains the correct answer. It is in the model's `$hidden`
  and is only read by dedicated code paths (grading, the teacher's editor).
- Reading or writing goes through `App\Casts\QuestionDefinitionCast`, which returns the value
  object for the question's `type` (`App\Domain\Questions\Definitions\*`). Malformed data, unknown
  keys or a shape that doesn't match the type throw `App\Exceptions\InvalidQuestionDefinition`.
- Each type is a **handler** (`App\Domain\Questions\Types\*Handler`, resolved through
  `QuestionTypeRegistry`, registered in `QuestionTypeServiceProvider`): editor validation and
  normalization, answer validation, grading, and the student (player) and review payloads.
  See "Handlers and scoring" below.

## IDs

Options and items use **random short IDs** (`ShortId`): 8 characters from the unambiguous
alphabet `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` (no `0/O`, `1/I`), generated with a CSPRNG.
They are never indexes or sequential numbers, so an option's id or position reveals nothing
about the answer. IDs are unique within a question (for `matching`, across both sides).

Item text: 1–1000 characters, not blank.

## Shapes

| Type              | `definition`                                                                        | Student `answer`             |
| ----------------- | ----------------------------------------------------------------------------------- | ---------------------------- |
| `single_choice`   | `{ options: [{id, text}], correct: id }`                                            | `{ selected: id }`           |
| `multiple_choice` | `{ options: [{id, text}], correct: [ids], scoring: "all_or_nothing" \| "partial" }` | `{ selected: [ids] }`        |
| `true_false`      | `{ correct: bool }`                                                                 | `{ value: bool }`            |
| `text`            | `{ max_length: int, reference_answer: string \| null }`                             | `{ text: string }`           |
| `numeric`         | `{ correct: "9.81", tolerance: "0.05", unit: string \| null }`                      | `{ value: "9.8" }`           |
| `ordering`        | `{ items: [{id, text}], correct_order: [ids], scoring }`                            | `{ order: [ids] }`           |
| `matching`        | `{ left: [{id, text}], right: [{id, text}], pairs: [{left, right}] }`               | `{ pairs: [{left, right}] }` |

### `single_choice` (`SingleChoiceDefinition`)

- At least 2 options, unique ids; `correct` is one of the option ids.

```json
{
    "options": [
        { "id": "K7QM2XWD", "text": "თბილისი" },
        { "id": "P3RT8ZNA", "text": "ბათუმი" }
    ],
    "correct": "K7QM2XWD"
}
```

### `multiple_choice` (`MultipleChoiceDefinition`)

- At least 2 options, unique ids; `correct` is a **non-empty** subset of the option ids (no repeats).
- `scoring` (`App\Enums\MultipleChoiceScoring`): `all_or_nothing` (full points only for the exact set)
  or `partial` (see "Handlers and scoring").

### `true_false` (`TrueFalseDefinition`)

- `correct` is a JSON boolean (not a string).

### `text` (`TextDefinition`)

- `max_length`: integer 1–10000, the limit for the student's answer.
- `reference_answer`: optional model answer **for the teacher only**; never shown to students.
- Not auto-gradable (`QuestionType::Text->isAutoGradable() === false`): graded manually.

### `numeric` (`NumericDefinition`)

- `correct`, `tolerance`: finite decimals **stored as strings** (`"9.81"`, never floats);
  `tolerance ≥ 0`. Compared with brick/math: correct when `|answer − correct| ≤ tolerance`.
  Definitions stored as JSON numbers before Stage 4 are read and converted to exact strings.
- `unit`: optional label (max 32 characters), e.g. `"m/s²"`.

### `ordering` (`OrderingDefinition`)

- At least 2 items, unique ids; `correct_order` contains **every** item id exactly once.
- `scoring`: `all_or_nothing` or `partial` (`MultipleChoiceScoring`); missing in definitions
  stored before Stage 4, which read as `all_or_nothing`.
- Items are stored shuffled, and students always see them shuffled.

### `matching` (`MatchingDefinition`)

- At least 2 `left` entries; `right` has at least as many entries as `left`.
  Extra `right` entries are **distractors**.
- Ids are unique across both sides.
- `pairs`: every `left` id appears exactly once, each `right` id at most once, and every
  referenced `right` id exists.

```json
{
    "left": [
        { "id": "A2B3C4D5", "text": "საქართველო" },
        { "id": "E6F7G8H9", "text": "France" }
    ],
    "right": [
        { "id": "J2K3L4M5", "text": "თბილისი" },
        { "id": "N6P7Q8R9", "text": "Paris" },
        { "id": "S2T3U4V5", "text": "Rome" }
    ],
    "pairs": [
        { "left": "A2B3C4D5", "right": "J2K3L4M5" },
        { "left": "E6F7G8H9", "right": "N6P7Q8R9" }
    ]
}
```

## Handlers and scoring

Points are `App\Domain\Questions\Points` (brick/math `BigDecimal`, scale 2): no floats anywhere.
Partial credit is computed exactly and rounded **once, half up, to 2 decimals**. A grade is a
`GradeResult` (`isCorrect: true | false | null`, `awardedPoints`, `needsManualGrading`).

| Type              | Editor input (validated by `definitionRules()`)                                    | Limits                                      | Grading                  |
| ----------------- | ---------------------------------------------------------------------------------- | ------------------------------------------- | ------------------------ |
| `single_choice`   | `{ options: [{id?, text, correct: bool}] }`                                        | 2–10 options, exactly 1 correct, text 1–500 | full or 0                |
| `multiple_choice` | `{ options: [{id?, text, correct: bool}], scoring }`                               | 2–10 options, ≥ 1 correct                   | see below                |
| `true_false`      | `{ correct: bool }`                                                                |                                             | full or 0                |
| `numeric`         | `{ correct: "9.81", tolerance: "0.05", unit? }`                                    | plain decimals (`,` accepted), unit ≤ 32    | full or 0                |
| `text`            | `{ max_length, reference_answer? }`                                                | `max_length` 1–5000                         | manual; empty answer = 0 |
| `ordering`        | `{ items: [{id?, text}] (in the correct order), scoring }`                         | 2–10 items                                  | see below                |
| `matching`        | `{ pairs: [{left: {id?, text}, right: {id?, text}}], distractors: [{id?, text}] }` | 2–10 pairs, 0–5 distractors                 | see below                |

Normalization trims text and keeps a valid, unused short id (editing keeps ids stable);
anything else gets a fresh CSPRNG id.

**Formulas** (`points` = the question's points):

- `multiple_choice`, `all_or_nothing`: `points` if the selection is exactly the correct set, else 0.
- `multiple_choice`, `partial`: `max(0, (correctSelected − wrongSelected) / totalCorrect) × points`.
  Example (3 correct, 1 point): 2 correct + 1 wrong → (2 − 1) / 3 = 0.333… → **0.33**.
- `ordering`, `all_or_nothing`: `points` only for the exact order.
- `ordering`, `partial`: `(items in their correct position / all items) × points`.
- `matching`: `(correct pairs / all pairs) × points` (always partial).
- `numeric`: `points` when `|value − correct| ≤ tolerance`, else 0.
- `text`: `needsManualGrading`, 0 points until a teacher grades it; a blank answer is 0 at once.

**Answers** are validated against the specific definition (`answerRules()`): unknown ids,
duplicates, a wrong structure or an oversized answer are a 422.

**Student payloads** (`toPlayerPayload()`) never contain correct-answer information: no
`correct`, `correct_order`, `pairs`, `is_correct`, `reference_answer` or `tolerance`, and no order
that mirrors the answer. Option order follows the test's shuffle setting; ordering items and the
matching right side are **always** shuffled. All shuffling uses `Random\Randomizer` (Secure engine;
a seeded engine only where reproducibility is explicitly needed). The text type's reference answer
is never sent to students, not even in the review. `tests/Support/PayloadLeakScanner.php` checks
this for every type.

## Rich text (body and explanation)

Question `body` and `explanation` are **ProseMirror / Tiptap JSON documents, never HTML**
(`App\Domain\Shared\RichTextDocument`, cast by `RichTextCast`, validated by the `RichText` rule).
The frontend renders them node by node (`components/rich-text.tsx`); no HTML string is ever
injected and `react/no-danger` stays enforced.

- Nodes: `doc`, `paragraph`, `text`, `heading` (levels 3–4), `bulletList`, `orderedList`
  (`start` attribute), `listItem`, `codeBlock` (`language` attribute), `hardBreak`, `blockquote`.
- Marks: `bold`, `italic`, `underline`, `code`, `subscript`, `superscript`.
- No links, no images in text, no raw HTML. Unknown nodes, marks, attributes or keys are
  **rejected**. Limits: depth ≤ 8, text ≤ 10,000 characters, JSON ≤ 64 KB.
- `body_text` (plain text) is derived on save for search and listings.
- The PHP and TS allowlists are compared by `tests/Arch/EnumParityTest.php`.

## Question images

One optional image per question: JPEG, PNG or WebP, ≤ 4 MB, ≤ 4000×4000 px (SVG never).
Every upload is decoded and **re-encoded to WebP** (max width 1600, GD), which strips EXIF/GPS
metadata and destroys polyglot payloads; the client's file name and MIME type are never trusted.
Files live on the private `local` disk under a random name and are served only by
`QuestionImageController` after `QuestionPolicy::viewImage` (owner, admins; students through their
attempts from Stage 7), with `nosniff` and `Cache-Control: private`.

Images are **kept** when replaced or removed, because attempt snapshots may reference them
(cleaning up unreferenced files is a later maintenance task).

## Adding a type

1. Add a case to `App\Enums\QuestionType` (label + `isAutoGradable()`), its TS mirror and translations.
   Add a migration that updates the `questions_type_valid` CHECK constraint (allowed values are
   frozen in the migration on purpose).
2. Add a `final readonly` definition class implementing `QuestionDefinition` and register it in
   `Definitions::classFor()`.
3. Add a handler (`extends BaseQuestionTypeHandler`) and list it in
   `QuestionTypeServiceProvider::HANDLERS`, with unit tests (validation branches, grading,
   leak scan) and the frontend module (Stage 4.6).
4. Document the shape here and add round-trip / rejection cases to
   `tests/Unit/QuestionDefinitionCastTest.php`.
