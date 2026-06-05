# Pest Browser Tests + CI

**Date:** 2026-06-05
**Status:** Approved design — ready for implementation plan

## Goal

Add Playwright-based browser testing to the project, a small set of basic
browser tests, and run them in CI — without forcing the existing fast unit
suite or developers-without-Playwright to run browser tests by default.

## Approach

Use **Pest 4's first-class browser testing** (`pestphp/pest-plugin-browser`),
which is built on Playwright. This is the idiomatic choice for a Pest-based
project: browser tests are written as Pest tests (`visit()`, `assertSee()`,
etc.) and run through the same `php artisan test` tooling, rather than
introducing a second, parallel Node test stack.

### Isolation from the default suite

Browser tests live in `tests/Browser/`. That directory is **not** registered
as a phpunit testsuite in `phpunit.xml` (which currently registers only `Unit`
and `Feature`). Consequences:

- `php artisan test` with no arguments runs only Unit + Feature — it does not
  discover `tests/Browser/`. So `composer test` and `composer ci:check` (which
  call `php artisan test --parallel`) are unchanged and never require Playwright.
- Browser tests are run explicitly by path: `php artisan test tests/Browser`.

This keeps the fast unit path and every developer's default workflow free of a
Playwright dependency, while still making browser tests a first-class,
explicitly-runnable suite.

## Components

1. **Dependency:** `composer require --dev pestphp/pest-plugin-browser`.
   Locally, the Playwright browser binary is installed with `npx playwright
   install` (chromium). Document this in the spec/plan; no change to
   `composer setup` is required for the default workflow.

2. **Pest binding (`tests/Pest.php`):** bind the application `TestCase` to the
   new directory so browser tests get the framework integration:
   `pest()->extend(Tests\TestCase::class)->use(RefreshDatabase::class)->in('Browser')`.
   The exact database-sharing configuration is resolved in the plan (see Open
   Items) — Pest 4 browser tests exercise the app through a real server, which
   has implications for the `:memory:` SQLite used by the existing suites.

3. **Tests (`tests/Browser/SkillsPageTest.php`):** the authenticated
   skills-page smoke test, expressed as a few tight cases:
   - An authenticated (factory) user visiting `/enki` sees the page render
     (the skills library UI / brand element is present).
   - The brand text reflects the configured app name (read from config, so the
     assertion stays correct regardless of the configured value).
   - No JavaScript console errors occur on load.

4. **CI (`.github/workflows/ci.yml`):** add a new `browser-tests` job that runs
   in parallel with the existing `tests` job. It mirrors the `tests` job setup
   — checkout, PHP 8.3 (`shivammathur/setup-php`), Node 22 (`actions/setup-node`,
   npm cache), `npm ci`, `composer install`, copy `.env.example` → `.env`,
   `php artisan key:generate`, `npm run build`, `php artisan wayfinder:generate
   --with-form` — plus `npx playwright install --with-deps chromium`, then
   `php artisan test tests/Browser`. Both `tests` and `browser-tests` must
   pass. The existing `tests` job is unchanged; the `docker` job continues to
   depend only on `tests`.

## Open Items (resolved during planning, not blockers)

- **Exact Pest browser assertion API:** the precise method names for "renders /
  sees text" and "no console errors" (e.g. `assertSee`, `assertNoJavascriptErrors`
  / `assertNoConsoleLogs`) are confirmed against the `pest-testing` skill and
  Pest 4 docs in the plan.
- **Database sharing for browser tests:** Pest 4 browser tests hit a real
  server, so the test process and the served app must share a database. The
  plan determines the correct configuration for the Browser run (likely a
  file-based SQLite for browser tests instead of the `:memory:` connection the
  Unit/Feature suites use), and where that override is applied (e.g. a
  `.env`/phpunit-style override scoped to the browser run) so it does not
  affect the existing suites.

## Non-Goals

- No standalone `@playwright/test` runner or `.spec.ts` files.
- No change to the default `php artisan test` / `composer test` / `ci:check`
  command behavior.
- The other candidate browser tests (guest login page, login flow, explicit
  APP_NAME-override assertion) are out of scope for this change.

## Success Criteria

1. `composer require --dev pestphp/pest-plugin-browser` is installed and
   `php artisan test tests/Browser` runs the browser suite locally (with
   Playwright installed) and passes.
2. `php artisan test` (no args), `composer test`, and `composer ci:check` still
   run only Unit + Feature and do not require Playwright.
3. The `tests/Browser/SkillsPageTest.php` cases pass: authenticated `/enki`
   renders, brand reflects the configured app name, and no JS console errors.
4. CI has a `browser-tests` job that installs the Playwright browser and runs
   the Browser suite; it passes on the PR. The existing `tests` job is
   unchanged.
