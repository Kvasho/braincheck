# BrainCheck — Stage 0: Foundation

> Instructions for Claude Code. Read this whole file before doing anything.

---

## 1. Project context

**BrainCheck** is an online exam / testing platform. Teachers build tests from a question bank and assign them to groups. Students take timed exams that are auto-graded. Results come with analytics, certificates and gamification (XP, badges, leaderboard).

**Roles:** Admin, Teacher (test creator), Student.

**Stack**

- Laravel with the official React starter kit: Inertia, React, TypeScript, Tailwind, shadcn/ui, Fortify auth, Wayfinder, Pest, Laravel Boost.
- This is a single monolith, not a separate SPA + API. React lives in `resources/js`.
- MySQL.

**Environment**

- Windows, Laravel Herd, Git Bash. Site URL: `http://braincheck.test`.

**Before starting, read `composer.json`, `package.json`, `bootstrap/app.php`, `app/Providers/*`, `app/Http/Middleware/*`, `resources/views/app.blade.php`, `vite.config.*` and `tsconfig.json`.** Detect the exact installed versions and adapt to them. Do not assume versions. Use Laravel Boost's documentation search for version-specific APIs.

### Design + code concept: "Synapse"

The brain works through connections. Every correct answer is a "lit neuron". Every question type is an independent module that plugs into the system.

- **Visual:** deep indigo background (dark mode first), electric cyan as primary accent, lime for success, coral for errors. A subtle node-and-line "neural network" motif. FiraGO font (renders Georgian and Latin in one style), monospace font for timers and scores. Calm motion, with zero distraction on exam pages.
- **Code:**
    - Thin controllers. Business logic lives in `final` Action classes with a single `handle()` method.
    - Enums for all statuses and types.
    - A Form Request for every input.
    - A Policy for every model.
    - Data reaches the frontend only through API Resources or DTOs, never raw models.
    - Strict types everywhere.
    - (Later stages: each question type is a strategy class implementing a `QuestionType` interface, mirrored on the frontend by Editor / Player / Review components in a registry.)

---

## 2. Working rules (follow strictly)

1. **Work one sub-step at a time** (0.2, 0.3, …). After each sub-step:
    - Stop and report: what you did, files created/changed, commands run, check results.
    - Note anything I must do manually.
    - **Wait for me to say "continue".**
2. **Before installing any package:**
    - Verify it supports the installed Laravel/PHP/React versions.
    - If it doesn't, stop and propose an alternative. Never use `--ignore-platform-reqs`, `--force` or `--legacy-peer-deps` without asking.
3. **Never** do any of the following without asking me first:
    - run `migrate:fresh`, `migrate:reset`, `db:wipe`, or drop or delete anything in the development database;
    - `git push`, or create remote repositories;
    - edit or commit `.env` (`.env.example` is fine);
    - disable, skip or weaken a security feature or a test to make something pass.
4. **After every sub-step, run the relevant checks** (Pint, Larastan, Pest, `tsc`, ESLint, build) once they exist. If something fails, fix the cause, not the test.
5. **Do not invent APIs.** If unsure, search the docs (Boost) or read the vendor source.
6. **Keep the starter kit's auth flows working.** Existing auth tests must stay green.
7. **Security over convenience.** When a choice exists, pick the more secure option and explain the trade-off briefly.
8. Prefer small, readable commits. Suggest a commit message at the end of each sub-step (I'll commit).

---

## 3. Sub-steps

### 0.2 Database check

- Run `php artisan migrate:status` and confirm all starter-kit migrations ran.
- Clean up `.env.example`:
    - realistic placeholders, no secrets;
    - `APP_URL=http://braincheck.test`;
    - MySQL `DB_*` keys.
- **Testing database:** tests must run on a separate MySQL database `braincheck_testing`, not SQLite, because later stages rely on MySQL locking and transaction behaviour.
    - Configure it in `phpunit.xml`, and also add `.env.testing` (gitignored) if appropriate.
    - Ask me to create the database if you can't.
    - Make sure tests can **never** touch the `braincheck` dev database.
- Run the existing test suite. It must be green.

### 0.3 Packages

**Backend (composer)**

| Package                          | Purpose                                    | Notes                                                                                                                         |
| -------------------------------- | ------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------- |
| `spatie/laravel-permission`      | roles & permissions                        | Publish config and migration and run the migration. **Do not create roles yet** (Stage 1).                                    |
| `maatwebsite/excel`              | import/export                              | If it doesn't support the installed Laravel version, propose `spatie/simple-excel` instead.                                   |
| `barryvdh/laravel-dompdf`        | PDF certificates                           |                                                                                                                               |
| `simplesoftwareio/simple-qrcode` | QR on certificates                         | If incompatible, propose an alternative.                                                                                      |
| `mews/purifier`                  | sanitizing rich text later                 | Publish config; restrictive allow-list (basic formatting, lists, code, no links with `javascript:`, no `style`, no `iframe`). |
| `laravel-lang/common`            | Georgian translations of framework strings | Add `ka` and `en`.                                                                                                            |
| `larastan/larastan` (dev)        | static analysis                            |                                                                                                                               |
| `laravel/telescope` (dev)        | debugging                                  | See requirements below.                                                                                                       |

Telescope requirements:

- Must load **only in the `local` environment**.
- Exclude it from package auto-discovery and register it conditionally.
- Its gate must deny everyone outside local.
- Mask sensitive request parameters and headers (passwords, tokens, cookies, `Authorization`).

**Frontend (npm).** Check which are already present before installing.

| Package                                                    | Purpose                                                           |
| ---------------------------------------------------------- | ----------------------------------------------------------------- |
| `@dnd-kit/core`, `@dnd-kit/sortable`, `@dnd-kit/utilities` | drag & drop                                                       |
| `recharts`                                                 | charts (shadcn chart components are built on it)                  |
| `date-fns`                                                 | dates, timers                                                     |
| `canvas-confetti` + `@types/canvas-confetti`               | celebration effects                                               |
| `fontaine` (dev)                                           | font fallback optimization; it was requested by the build warning |
| `sonner`                                                   | toasts. Add via the shadcn CLI if the kit uses it, otherwise npm. |

Finish with:

- `composer audit` and `npm audit`. Report any high or critical findings.
- `npm run build` must succeed.

### 0.4 Code quality tooling

**Larastan**

- Create `phpstan.neon` analysing `app/`, `config/`, `database/`, `routes/`.
- Start at **level 7**, no baseline. Fix starter-kit issues properly.
- Tell me if anything really can't reach level 7.

**Pint**

- Create `pint.json` with the `laravel` preset plus these rules:
    - `declare_strict_types`
    - `strict_comparison`
    - `no_unused_imports`
    - `ordered_imports`
    - `fully_qualified_strict_types`
- Run it on the whole project, then run tests to confirm nothing broke.

**Pest architecture tests** in `tests/Arch/`:

- Use Pest's `php` and `security` presets, plus `laravel` if it fits the kit. Document any exception with a comment explaining why.
- Custom rules:
    - no `dd`, `dump`, `ray`, `var_dump`, `print_r`, `die`, `exit` anywhere in `app/`;
    - `env()` only used inside `config/`;
    - controllers have the `Controller` suffix and do not use the `DB` facade directly;
    - `App\Actions` classes are `final` and have a `handle` method;
    - `App\Enums` contains only enums;
    - models extend `Illuminate\Database\Eloquent\Model`, and the `User` model extends `Authenticatable`;
    - all classes in `app/` use strict types.
- Add a placeholder test asserting that **every model has a Policy**. Mark it to be enforced from Stage 2 (skip with a clear reason until then).

**TypeScript and ESLint**

- `tsconfig.json`: `"strict": true` and `"noUncheckedIndexedAccess": true`. Fix resulting errors.
- ESLint, in addition to what the kit has:
    - `react/no-danger: error`;
    - `@typescript-eslint/no-explicit-any: error`;
    - `no-eval: error`, `no-implied-eval: error`;
    - `no-console: warn` (allow `warn` and `error`).

**Scripts**

- `composer check` → `pint --test`, `phpstan analyse`, `pest`.
- `npm run check` → ESLint, `tsc --noEmit`, Prettier check (if the kit uses Prettier).

### 0.5 Git and CI

**Git**

- Verify `.gitignore` covers: `.env`, `.env.*` (except `.env.example`), `/vendor`, `/node_modules`, `/public/build`, `/public/hot`, `/storage/*.key`, test caches, IDE folders, and the Wayfinder-generated `resources/js/actions` and `resources/js/routes` (only if the kit regenerates them at build time; check first).
- Initialise git if not yet done. **Do not push.**

**CI:** create `.github/workflows/ci.yml` that runs on push and pull request:

- PHP version matching `composer.json`;
- a MySQL service container for tests;
- `composer install` and `npm ci`;
- `npm run build`;
- Pint (test mode), Larastan, Pest (parallel if supported);
- ESLint, `tsc`;
- `composer audit`;
- `npm audit --audit-level=high`;
- dependency caching.

Explain to me how to create the GitHub repo and push (I'll do it).

### 0.6 Security baseline

**a) Security headers middleware** (`App\Http\Middleware\SecurityHeaders`), registered globally in `bootstrap/app.php`. Headers:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy`: deny camera, microphone, geolocation, payment, usb
- `Cross-Origin-Opener-Policy: same-origin`
- `Strict-Transport-Security: max-age=31536000; includeSubDomains`, **only in production and only over HTTPS**

**b) Content Security Policy**, nonce-based:

- Use `Vite::useCspNonce()`.
- Every inline `<script>` in `resources/views/app.blade.php` (the kit has an inline appearance/theme script) must carry the nonce.
- Check how Inertia embeds initial page data and make sure it complies.
- Policy:
    - `default-src 'self'`
    - `script-src 'self' 'nonce-…'`
    - `style-src 'self' 'unsafe-inline'` (justify this, or use nonces if the libraries allow it)
    - `img-src 'self' data: blob:`
    - `font-src 'self'`
    - `connect-src 'self'`
    - `object-src 'none'`
    - `base-uri 'self'`
    - `form-action 'self'`
    - `frame-ancestors 'none'`
- In `local`, allow the Vite dev server (HTTP + WebSocket) and use **Report-Only** mode. In production, **enforce**.
- No external CDNs: fonts and assets are self-hosted.

**c) AppServiceProvider hardening**

- `Model::shouldBeStrict(! app()->isProduction())`, which covers lazy loading, silently discarded attributes and missing attributes.
- Never call `Model::unguard()`.
- `DB::prohibitDestructiveCommands(app()->isProduction())`.
- `URL::forceHttps()` in production.
- `Password::defaults()`:
    - minimum 12 characters, letters, mixed case, numbers;
    - `uncompromised()` in production.
    - Make sure registration, reset and change-password all use it.

**d) Rate limiters**, defined now and used in later stages:

- `login`: verify Fortify's limiter, per email + IP.
- `two-factor`: verify Fortify's limiter.
- `autosave`: 60/min per user.
- `join-group`: 5/min per user + IP.
- `certificate-verify`: 20/min per IP.
- `locale`: 10/min per IP.

**e) Session and cookies.** Document in `.env.example` and set sensible defaults:

- `SESSION_DRIVER=database`
- `SESSION_ENCRYPT=true`
- `SESSION_SECURE_COOKIE` true in production
- `SESSION_SAME_SITE=lax`
- `SESSION_HTTP_ONLY=true`
- Reasonable lifetime.

**f) Fortify features**

- Email verification enabled; `User` implements `MustVerifyEmail`.
- 2FA requires confirmation.
- Passkeys working (if the kit provides them).
- Dashboard routes protected by `auth` + `verified`.

**g) Inertia shared data** (`HandleInertiaRequests`):

- Share **only** an explicit, minimal user payload: `id`, `name`, `email`, `email_verified_at`, avatar if any, and later roles/permissions.
- Never share the full model; no 2FA secrets, recovery codes or remember tokens.
- Share flash messages and the locale.

**h) Error pages:** production errors must never show stack traces or environment info.

**Tests for 0.6** (feature tests, all must pass):

- every header is present on a normal page response;
- HSTS is absent in non-production;
- CSP is present and contains a nonce that matches the nonce on the page's script tags;
- weak passwords are rejected on register, reset and change; a strong one is accepted;
- repeated login attempts return 429;
- strict model mode is active in tests (lazy loading throws);
- Inertia shared `auth.user` contains exactly the allowed keys, so the test fails if a new key appears;
- unverified users can't reach the dashboard.

### 0.7 Design system "Synapse"

**Tokens**

- Define all theme tokens (shadcn CSS variables, OKLCH) in the main CSS file, in both dark and light themes. Starting palette (adjust for **WCAG AA contrast**):

| Token               | Dark                                                       |
| ------------------- | ---------------------------------------------------------- |
| background          | deep indigo `oklch(0.17 0.04 275)`                         |
| card / popover      | `oklch(0.21 0.045 275)`                                    |
| primary             | electric cyan `oklch(0.80 0.14 200)`, with dark foreground |
| success             | lime `oklch(0.86 0.19 128)`                                |
| destructive         | coral `oklch(0.70 0.17 25)`                                |
| warning             | amber                                                      |
| muted, border, ring | derived from the indigo                                    |

- Add `success` and `warning` as proper tokens usable from Tailwind.
- No hard-coded colours in components.
- **Dark theme is the default.** Keep the kit's appearance toggle (light / dark / system).

**Fonts**

- **FiraGO** for UI text: self-hosted, woff2, with the OFL licence file included. Use an npm `@fontsource` package if one exists; otherwise download the official release from the bBox Type GitHub repository into `resources/fonts`.
- **JetBrains Mono** (via `@fontsource`) for timers and scores; use `tabular-nums`.
- Replace the kit's default font everywhere, including the Vite fonts plugin config.
- Load only the weights we use.

**Components**

- `SynapseBackground`: subtle SVG node-and-line network, low opacity. Respects `prefers-reduced-motion`. Pure decoration (`aria-hidden`).
- `Logo`: an original, simple SVG wordmark "BrainCheck" with a node motif.

**Layouts**

- `AppLayout`: the kit's sidebar layout, restyled, with a navigation structure ready for role-based menus (placeholder items for now).
- `AuthLayout`: restyled, with the Synapse background.
- `ExamLayout`: minimal and distraction-free. No sidebar, no background animation. Header slot for the timer.
- `PublicLayout`: for the landing page and certificate verification.

**Pages**

- Restyle the welcome page as a simple BrainCheck landing (placeholder copy is fine).
- Style guide page `/dev/styleguide` showing colours, typography, buttons, inputs, cards, badges, alerts and toasts.
    - Registered **only when `app()->isLocal()`**.
    - Add a test that it returns 404 outside local.

### 0.8 Internationalization (ka / en)

**Locales**

- Supported: `ka` (default) and `en`; fallback `en`.
- Define an enum `App\Enums\Locale`. It is the single source of truth.

**Choosing the locale** (`SetLocale` middleware), first match wins:

1. authenticated user's saved preference;
2. session;
3. `Accept-Language`;
4. default.

Only enum values are ever accepted. Raw input must never reach `App::setLocale()` or any file path.

**User preference**

- Migration: nullable `locale` column on `users`, cast to the enum.

**Switching**

- `POST /locale` route: Form Request with `Rule::enum(Locale::class)`, `locale` rate limiter, CSRF-protected.
- Saves to session, and to the user if authenticated.

**Translations**

- Framework strings: `lang/ka/*.php` (from laravel-lang).
- UI strings: `lang/ka.json` and `lang/en.json`.

**Frontend**

- Share the current locale's UI translations via Inertia, cached server-side.
- Small `useTranslation()` hook with `t('key', { placeholder })`, plus a typed key helper if practical.
- No extra i18n library unless there's a strong reason (explain first).
- Translate **every** existing starter-kit UI string (auth pages, settings, dashboard, sidebar) into both languages.
- Language switcher in the header and on auth pages.
- `date-fns` formatting uses the active locale (`ka`/`enUS`).
- Set `<html lang>` correctly.

**Tests**

- an invalid locale returns 422;
- an injected value like `../../etc` is rejected;
- the preference persists for an authenticated user;
- the middleware picks the right locale in priority order;
- **parity test:** every key in `en.json` exists in `ka.json` and vice versa.

---

## 4. Project guidelines file

At the end of Stage 0, record the permanent project conventions so they apply in every future session:

- the Synapse concept;
- architecture rules;
- security rules (sections 1, 2 and 0.6);
- testing expectations.

Put them in a custom Laravel Boost guideline (check Boost's docs for the custom guidelines location, e.g. `.ai/guidelines/`, and regenerate `CLAUDE.md` with Boost's command). If Boost in this version doesn't support that, append a clearly marked section to `CLAUDE.md` that Boost won't overwrite.

---

## 5. Definition of done for Stage 0

- [ ] `composer check` is green (Pint, Larastan level 7, Pest including arch and security tests).
- [ ] `npm run check` and `npm run build` are green.
- [ ] `composer audit` / `npm audit` show no high or critical issues (or each is explained).
- [ ] All starter-kit auth flows work in the browser: register, verify email, login, 2FA, password reset, profile, appearance.
- [ ] The site renders in Georgian by default, switches to English, and dark theme is the default.
- [ ] No CSP violations in the browser console in production mode (test with a local production build).
- [ ] CI workflow file exists and is valid.
- [ ] Project guidelines file is in place.

Finish with a short report: what was done, anything deferred, and any security decisions I should know about.
