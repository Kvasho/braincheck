# BrainCheck

Online exam / testing platform: teachers build tests from a question bank and assign them to
groups; students take timed, auto-graded exams; results come with analytics, certificates and
gamification. Laravel + Inertia + React (TypeScript), MySQL.

Project conventions for contributors and AI agents: `.ai/guidelines/braincheck.md`
(compiled into `CLAUDE.md` by `php artisan boost:update`).

## Local setup (Laravel Herd)

```bash
composer install
npm ci
cp .env.example .env        # then set DB_* for your local MySQL
php artisan key:generate
php artisan migrate
php artisan braincheck:sync-permissions
npm run dev                 # http://braincheck.test
```

Tests use a separate database, `braincheck_testing` (see `phpunit.xml`); create it once.

## Demo data (local only)

```bash
php artisan db:seed --class=DemoUsersSeeder
php artisan db:seed --class=DemoContentSeeder
```

- **DemoUsersSeeder**: one admin, two teachers and five students
  (`admin@braincheck.test`, `teacher1@…`, `teacher2@…`, `student1@…` … `student5@…`),
  all with the password `BrainCheck-Demo-2026!`.
- **DemoContentSeeder**: bilingual categories and tags, ~60 questions (every type and
  difficulty), 8 tests in different states (timed, random pool, limited attempts,
  open / closed / upcoming, draft, archived), 3 groups with members and assigned tests,
  and graded attempts with varied scores and certificates.

Both seeders refuse to run outside the `local` / `testing` environments, never run from
`DatabaseSeeder`, and are safe to run again (existing demo data is kept).

Create a real administrator with `php artisan braincheck:make-admin` (never via the web).

## Checks

```bash
composer check    # Pint, Larastan (level 7), Pest (incl. arch, security and access-matrix tests)
npm run check     # Oxlint, oxfmt, tsc, WCAG contrast check
npm run build
```

## Documentation

- `docs/erd.md`: entity-relationship diagram (Mermaid) and one line per table.
- `docs/question-types.md`: question `definition` shapes and student answer shapes.
