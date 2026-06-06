# Pest Browser Tests + CI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Pest 4 Playwright-based browser testing with a basic authenticated skills-page smoke test, runnable explicitly and wired into CI, without affecting the default unit/feature test path.

**Architecture:** Install `pestphp/pest-plugin-browser` (Playwright under the hood). Browser tests live in `tests/Browser/`, which is deliberately NOT a registered phpunit testsuite — so `php artisan test` / `composer test` / `composer ci:check` keep running only Unit + Feature and never need Playwright. Browser tests run explicitly via `php artisan test tests/Browser`, and a dedicated parallel `browser-tests` CI job installs the Playwright browser and runs them.

**Tech Stack:** Pest 4.6, pest-plugin-browser, Playwright (chromium), Laravel 13, GitHub Actions.

**Key facts (verified against Pest 4 docs):**
- Pest 4 browser testing supports `RefreshDatabase` **even with in-memory SQLite** — no database/phpunit changes are needed; the existing `DB_DATABASE=:memory:` works.
- Browser assertions used here: `assertSee()`, `assertNoJavaScriptErrors()`, `assertNoConsoleLogs()`.
- Install requires both the composer plugin AND the npm `playwright` package + browser binary.
- The app must have built front-end assets for the served page to work (`npm run build`); CI already builds assets.

**Conventions:** Run `vendor/bin/pint --dirty --format agent` after PHP edits. The project uses `test()`-style Pest tests (match existing files). Commit after each task.

---

## File Structure

- **Modify** `composer.json` / `composer.lock` — add `pestphp/pest-plugin-browser` (dev).
- **Modify** `package.json` / `package-lock.json` — add `playwright` (dev).
- **Modify** `.gitignore` — ignore `tests/Browser/Screenshots`.
- **Modify** `tests/Pest.php` — bind `Tests\TestCase` + `RefreshDatabase` to the `Browser` dir.
- **Create** `tests/Browser/SkillsPageTest.php` — the authenticated skills-page browser tests.
- **Modify** `.github/workflows/ci.yml` — add a parallel `browser-tests` job.

---

## Task 1: Install the Pest browser plugin and wire up the Browser directory

**Files:**
- Modify: `composer.json`, `composer.lock` (via composer)
- Modify: `package.json`, `package-lock.json` (via npm)
- Modify: `.gitignore`
- Modify: `tests/Pest.php`

- [ ] **Step 1: Install the composer plugin (dev)**

Run:
```bash
composer require pestphp/pest-plugin-browser --dev
```
Expected: composer adds the package to `require-dev` and updates `composer.lock` without errors.

- [ ] **Step 2: Install the Playwright npm package and browser binary**

Run:
```bash
npm install --save-dev playwright@latest
npx playwright install chromium
```
Expected: `playwright` added to `devDependencies` in `package.json` (and `package-lock.json` updated); `npx playwright install chromium` downloads the chromium browser without errors.

- [ ] **Step 3: Ignore browser screenshots**

Add this line to the end of `.gitignore`:
```
/tests/Browser/Screenshots
```

- [ ] **Step 4: Bind the app TestCase to the Browser directory**

In `tests/Pest.php`, find the existing binding block:

```php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
```

Add a second binding directly below it for the Browser directory (browser tests need the same app `TestCase` + `RefreshDatabase`):

```php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Browser');
```

(Do not register `tests/Browser` as a testsuite in `phpunit.xml` — leaving it out is what keeps it excluded from the default `php artisan test` run.)

- [ ] **Step 5: Create a temporary throwaway test to verify the toolchain**

Create `tests/Browser/InstallSmokeTest.php`:

```php
<?php

test('the login page loads in a real browser', function (): void {
    visit('/login')->assertNoJavaScriptErrors();
});
```

- [ ] **Step 6: Build assets and run the throwaway browser test**

Run:
```bash
npm run build
php artisan test tests/Browser/InstallSmokeTest.php --compact
```
Expected: PASS (a real chromium browser launches, loads `/login`, no JS errors). This proves the plugin, browser binary, Pest binding, and served assets all work.

If this fails because the browser binary or system deps are missing, that is the blocker to resolve here (re-run `npx playwright install --with-deps chromium`). Do not proceed until this passes.

- [ ] **Step 7: Confirm the default suite is unaffected**

Run:
```bash
php artisan test --compact
```
Expected: PASS, and the output's test count is the SAME as before this task (the throwaway browser test is NOT picked up because `tests/Browser` is not a registered testsuite). Confirm 176 passed / 1 skipped (the pre-existing baseline).

- [ ] **Step 8: Delete the throwaway test**

Run:
```bash
rm tests/Browser/InstallSmokeTest.php
```

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add composer.json composer.lock package.json package-lock.json .gitignore tests/Pest.php
git commit -m "chore: install pest-plugin-browser and wire up tests/Browser"
```

---

## Task 2: Authenticated skills-page browser tests

**Files:**
- Create: `tests/Browser/SkillsPageTest.php`

**Context:** `/enki` (route name `enki`) is the authenticated skills library. It is behind auth middleware, so the test logs in a factory user with `actingAs`. With `RefreshDatabase` the DB is empty (no seeders run), but the virtual platform skill always renders, and the TopBar shows the brand name (`page.props.name`, which equals `config('app.name')`, default `enki`) plus a literal `skills` sub-label (see `resources/js/pages/enki/TopBar.vue`). These are the stable anchors to assert against. `assertSee(config('app.name'))` ties the brand rendering to the configurable-app-name feature and stays correct whatever the configured name is.

- [ ] **Step 1: Write the browser tests**

Create `tests/Browser/SkillsPageTest.php`:

```php
<?php

use App\Models\User;

test('an authenticated user can view the skills library', function (): void {
    $this->actingAs(User::factory()->create());

    visit('/enki')
        ->assertSee(config('app.name'))
        ->assertSee('skills');
});

test('the skills library loads without JavaScript errors', function (): void {
    $this->actingAs(User::factory()->create());

    visit('/enki')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
```

- [ ] **Step 2: Run the browser tests**

Run (assets must be built — run `npm run build` first if not already built this session):
```bash
php artisan test tests/Browser/SkillsPageTest.php --compact
```
Expected: PASS (2 tests). A chromium browser loads `/enki` as the authenticated user, sees the brand name (`enki`) and the `skills` label, and reports no JS errors or console logs.

- [ ] **Step 3: If `assertNoConsoleLogs()` fails on a benign log**

Only if Step 2 fails specifically on `assertNoConsoleLogs()` (not on `assertNoJavaScriptErrors()`): the app emitted a non-error console message. In that case remove the `->assertNoConsoleLogs()` line (keep `->assertNoJavaScriptErrors()`, which is the meaningful error check the user asked for) and re-run Step 2 until green. Do not loosen `assertNoJavaScriptErrors()`.

- [ ] **Step 4: Confirm the default suite still excludes browser tests**

Run:
```bash
php artisan test --compact
```
Expected: PASS with the same baseline count as before (176 passed / 1 skipped) — the new browser tests are not in the default run.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/Browser/SkillsPageTest.php
git commit -m "test: add authenticated skills-page browser smoke tests"
```

---

## Task 3: Add a parallel `browser-tests` CI job

**Files:**
- Modify: `.github/workflows/ci.yml`

**Context:** The existing `tests` job runs `composer run ci:check` (Unit + Feature, no Playwright). The new `browser-tests` job mirrors that job's setup but adds the Playwright browser install and runs only the Browser directory. Both jobs run in parallel on push/PR; the `docker` job still depends only on `tests` (leave its `needs: tests` unchanged).

- [ ] **Step 1: Add the `browser-tests` job**

In `.github/workflows/ci.yml`, insert the following job between the `tests` job and the `docker` job (i.e. after the `tests` job's final `Run CI checks` step block, and before `  docker:`). Keep two-space indentation consistent with the existing file:

```yaml
  browser-tests:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          tools: composer:v2
          coverage: none

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: 'npm'

      - name: Install Node dependencies
        run: npm ci

      - name: Install PHP dependencies
        run: composer install --no-interaction --prefer-dist --optimize-autoloader

      - name: Copy environment file
        run: cp .env.example .env

      - name: Generate application key
        run: php artisan key:generate

      - name: Build assets
        run: npm run build

      - name: Generate Wayfinder routes and actions
        run: php artisan wayfinder:generate --with-form

      - name: Install Playwright browser
        run: npx playwright install --with-deps chromium

      - name: Run browser tests
        run: php artisan test tests/Browser
```

- [ ] **Step 2: Validate the workflow YAML parses**

Run:
```bash
python3 -c "import yaml,sys; d=yaml.safe_load(open('.github/workflows/ci.yml')); print('jobs:', list(d['jobs'].keys()))"
```
Expected: prints `jobs: ['tests', 'browser-tests', 'docker']` — confirming valid YAML and that all three jobs are present.

- [ ] **Step 3: Confirm the docker job dependency is unchanged**

Run:
```bash
grep -n "needs:" .github/workflows/ci.yml
```
Expected: shows `needs: tests` (the docker job still depends only on `tests`, not on `browser-tests`).

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "ci: add parallel browser-tests job running the Browser suite"
```

---

## Self-Review Notes

- **Spec coverage:** install pest-plugin-browser + Playwright (Task 1) ✓; `tests/Browser` not a testsuite, run by path, default suite unaffected (Task 1 Steps 4/7, Task 2 Step 4) ✓; Pest binding (Task 1 Step 4) ✓; authenticated skills-page smoke with brand + no-JS-errors (Task 2) ✓; parallel `browser-tests` CI job mirroring `tests` + Playwright install (Task 3) ✓; `.gitignore` screenshots (Task 1 Step 3) ✓.
- **Open items from spec resolved:** DB sharing — `:memory:` works per Pest 4 docs, no phpunit changes; assertion API confirmed (`assertSee`/`assertNoJavaScriptErrors`/`assertNoConsoleLogs`).
- **Verification limits:** the CI job cannot be executed locally (no GitHub Actions runner); it is verified by YAML-parse + structural checks. The browser tests themselves ARE run locally in Tasks 1–2. Browser tests require built assets (`npm run build`) before running locally.
- **Consistency:** `tests/Browser/` directory, the `Browser` Pest binding, and `php artisan test tests/Browser` are referenced consistently across all three tasks and the CI job.
