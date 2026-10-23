# BrainCheck — Stage 14: Polish, Landing, Accessibility & Performance

> Instructions for Claude Code. Read this whole file before doing anything.
> Stages 0–13 are complete. All project guidelines still apply.

## Working rules (same as before)

- **One sub-step at a time.** After each one: report, suggest a commit message, and **wait for "continue"**.
- Ask before destructive dev-DB commands, `git push`, or `.env` edits. Never weaken tests or security.
- `composer check`, `npm run check` and `npm run build` must be green after every sub-step.
- Every new route goes into the access matrix. All UI text in ka + en.
- **Security from Stage 13 must not regress:** CSP stays enforced, no new external CDNs, no `dangerouslySetInnerHTML`.

---

## 2. Sub-steps

### 14.1 Full landing page

Keep the existing Hero, header and footer. Add these sections, each with `motion` scroll-reveal (reduced-motion safe):

1. **How it works:** 3 steps (create test → invite group → see results) with Synapse line connectors.
2. **Question types showcase:** small **live, interactive demos** of 3–4 question types, using the real `Player` components with demo data. A drag & drop ordering demo is a must. Grading is done client-side with hard-coded demo answers. This is the only place where client-side checking is allowed, because nothing secret is involved.
3. **For teachers / for students:** two columns of benefits.
4. **Analytics preview:** an animated chart using demo data.
5. **Gamification:** XP bar, badges and a podium animation.
6. **Certificate verification:** a code input that posts to `/verify`.
7. **FAQ** (accordion).

**SEO for public pages**
- Title/description meta per locale, Open Graph tags + an OG image (static, branded).
- `hreflang` ka/en.
- `sitemap.xml` (public pages only) and `robots.txt` that disallows the app areas.

**3D** (if the Stage 0 3D option was taken): lazy-loaded, paused when off-screen, a static fallback image for low-power devices and reduced motion. Check the bundle impact.

### 14.2 Responsive pass

Go through every page at 360px, 768px, 1024px and 1440px.

**Exam player on mobile**
- the navigator becomes a bottom sheet;
- touch sensors for drag & drop;
- large tap targets (≥ 44px);
- the timer is always visible.

Tables collapse into cards (`DataTable` mobile mode); dialogs become full-screen sheets on small screens.

### 14.3 Accessibility (WCAG 2.1 AA)

- Use Pest browser testing accessibility assertions (if available in the installed Pest version; otherwise integrate axe-core in browser tests) on all key pages. **Zero serious/critical violations.**
- **Keyboard-only walkthrough:** login → take an exam (all types, including keyboard drag & drop) → view the result. Focus is always visible (Synapse ring token); there are no focus traps except in dialogs.
- **Screen reader labels:** timer (`aria-live` polite announcements at 5 min and 1 min, not every second), save status, question navigator state, charts (text summaries / data tables as alternatives).
- Contrast re-check in both themes.
- `prefers-reduced-motion` is respected everywhere (audit all `motion` usages).

### 14.4 States and error pages

- Every list has an empty state (with a call to action); every async action has loading and disabled states; every form has an error summary.
- Skeleton loaders for Inertia deferred props.
- Custom pages for 403, 404, 419 (session expired, with a re-login button), 429, 500 and 503 (maintenance) in Synapse style, translated. The exam player shows a friendly 419 recovery that keeps the in-memory answers and retries after re-auth.

### 14.5 Performance

- **Backend:**
  - run `EXPLAIN` on the hot queries (exam list, player load, autosave, analytics, leaderboards) and add missing indexes;
  - confirm there's no N+1 anywhere (strict mode) in a full browser test run;
  - add caching where reads dominate.
- **Frontend:**
  - per-page code splitting (confirm the Inertia lazy page resolution);
  - check the bundle analysis; keep heavy libs (`recharts`, `three`, `tiptap`) out of pages that don't use them;
  - fonts: subset and preload the main weights; `fontaine` fallbacks.
  - **Lighthouse** on the landing page, login, dashboard and player: performance ≥ 90, accessibility ≥ 95, best practices ≥ 95, SEO ≥ 95 (public pages).
- **Load sanity test:** a k6 or simple script simulating 100 students autosaving every 5 s against local. Record the p95 latency in docs (local numbers are only indicative).

### 14.6 End-to-end journeys (browser tests)

- **Student:** register → verify → join group → take exam → result → certificate → leaderboard.
- **Teacher:** create questions (all types) → build test → assign → grade a text answer → analytics → export.
- **Admin:** approve a teacher request → block a user → audit log check.

These run in CI (headless).

### 14.7 Documentation and portfolio

- **README:** overview, screenshots/GIFs, feature list, tech stack, architecture summary (link the docs), security highlights, local setup (Herd + MySQL + seeders + queue worker), how to run the tests.
- `docs/architecture.md`: the Synapse module pattern, Actions, Policies, snapshots, the grading pipeline (Mermaid diagrams).
- **Demo script:** a short walkthrough for showing the project to employers (which demo users, what to click).

---

## 3. Definition of done

- [ ] The landing is complete with interactive demos; all pages are responsive.
- [ ] Accessibility checks have zero serious issues; Lighthouse targets are met.
- [ ] The E2E journeys are green in CI.
- [ ] README and docs are portfolio-ready.

Finish with a short report.
