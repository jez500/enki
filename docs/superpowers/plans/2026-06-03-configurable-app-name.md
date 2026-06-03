# Configurable App Name Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let any deployed instance override the app name "enki" via `APP_NAME`, applying everywhere it is displayed and to the platform skill's name/slug/install command.

**Architecture:** A single `AppService` exposes `getAppName()` (display label, verbatim) and `getAppSlug()` (machine name, `Str::slug` of the name, overridable via `APP_MACHINE_NAME`). Backend code always goes through the service; the frontend receives both values as Inertia shared props (`name`, `machineName`). The platform skill's readme/usage Blade templates are rendered with these values injected.

**Tech Stack:** Laravel 13, Inertia v3, Vue 3, Pest 4.

**Conventions reminder:** Run `vendor/bin/pint --dirty --format agent` after PHP changes. Run tests with `php artisan test --compact --filter=...`. Commit after each task.

---

## File Structure

- **Create** `app/Services/AppService.php` — single source for app name + slug.
- **Create** `tests/Feature/AppServiceTest.php` — covers the service.
- **Modify** `config/app.php` — `name` default → `enki`; add `machine_name`.
- **Modify** `.env`, `.env.example` — `APP_NAME=enki`, document `APP_MACHINE_NAME`.
- **Modify** `app/Http/Middleware/HandleInertiaRequests.php` — share via service, add `machineName`.
- **Modify** `resources/js/types/global.d.ts` — add `machineName` to shared props.
- **Modify** `app/Services/VirtualEnkiSkill.php` — dynamic slug/name, render Blade with vars.
- **Modify** `resources/views/skills/enki/readme.blade.php`, `usage.blade.php` — templatize.
- **Modify** `database/seeders/SkillSeeder.php` — pass vars to views, dynamic enki row + demo install cmd.
- **Modify** `app/Http/Controllers/EnkiController.php` — slug comparisons + download filename via service.
- **Modify** `app/Http/Controllers/AgentInstallController.php` — pass `appName`/`appSlug`.
- **Modify** `resources/js/pages/help/AgentInstall.vue` — consume new props in the prompt.
- **Modify** `resources/js/pages/enki/DetailPane.vue` — install command uses `machineName`.
- **Modify** `resources/js/layouts/EnkiMinimalLayout.vue`, `EnkiAdminLayout.vue`, `resources/js/pages/enki/TopBar.vue` — brand text uses `name`.

**Naming rule applied throughout:** paths, URLs, CLI commands, frontmatter `name:`, and backtick/code identifiers → **slug** (`getAppSlug()` / `machineName`). Prose product references, headings, and logos → **label** (`getAppName()` / `name`). Per the design decision, leave `ENKI_URL`/`ENKI_TOKEN` env var names and `/tmp/*.zip` temp paths unchanged.

---

## Task 1: AppService + config + env

**Files:**
- Create: `app/Services/AppService.php`
- Create: `tests/Feature/AppServiceTest.php`
- Modify: `config/app.php:16` (and add a `machine_name` key after it)
- Modify: `.env:1`, `.env.example:1`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AppServiceTest.php`:

```php
<?php

use App\Services\AppService;

test('app name comes from config and falls back to enki', function (): void {
    config(['app.name' => 'skillhound']);
    expect(app(AppService::class)->getAppName())->toBe('skillhound');

    config(['app.name' => null]);
    expect(app(AppService::class)->getAppName())->toBe('enki');
});

test('app slug defaults to the slug of the app name', function (): void {
    config(['app.name' => 'Skill Hound', 'app.machine_name' => null]);
    expect(app(AppService::class)->getAppSlug())->toBe('skill-hound');
});

test('app slug can be overridden independently of the name', function (): void {
    config(['app.name' => 'Skill Hound', 'app.machine_name' => 'sh']);
    expect(app(AppService::class)->getAppSlug())->toBe('sh');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=AppServiceTest`
Expected: FAIL with "Class App\Services\AppService not found".

- [ ] **Step 3: Create the service**

Create `app/Services/AppService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Str;

class AppService
{
    public function getAppName(): string
    {
        return config('app.name') ?: 'enki';
    }

    public function getAppSlug(): string
    {
        $machineName = config('app.machine_name');

        return $machineName ?: Str::slug($this->getAppName());
    }
}
```

- [ ] **Step 4: Update config/app.php**

Change line 16 from:

```php
    'name' => env('APP_NAME', 'Laravel'),
```

to:

```php
    'name' => env('APP_NAME', 'enki'),

    /*
    |--------------------------------------------------------------------------
    | Application Machine Name
    |--------------------------------------------------------------------------
    |
    | The slug form of the application name, used for machine-readable
    | identifiers (the platform skill slug and install commands). Defaults to
    | the slug of the application name; override with APP_MACHINE_NAME.
    |
    */

    'machine_name' => env('APP_MACHINE_NAME'),
```

- [ ] **Step 5: Update .env and .env.example**

In both `.env` and `.env.example`, change line 1 from `APP_NAME=Enki` to:

```
APP_NAME=enki
```

In `.env.example` only, add directly below line 1:

```
# Override the machine-readable slug (skill slug + install command prefix).
# Defaults to the slug of APP_NAME when unset.
# APP_MACHINE_NAME=
```

- [ ] **Step 6: Clear config cache and run the test**

Run: `php artisan config:clear && php artisan test --compact --filter=AppServiceTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/AppService.php tests/Feature/AppServiceTest.php config/app.php .env.example
git commit -m "feat: add AppService for configurable app name and slug"
```

(`.env` is gitignored — it is edited locally but not committed.)

---

## Task 2: Share `machineName` via Inertia + TS type

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php:40-48`
- Modify: `resources/js/types/global.d.ts:16-25`
- Test: `tests/Feature/EnkiTest.php` (add one test)

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/EnkiTest.php` (it already imports what it needs for `route('enki')` Inertia assertions; add `use App\Models\User;` at top only if not present):

```php
test('app name and machine name are shared with the frontend', function (): void {
    config(['app.name' => 'skillhound', 'app.machine_name' => null]);

    $this->actingAs(User::factory()->create())
        ->get(route('enki'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('name', 'skillhound')
            ->where('machineName', 'skillhound')
        );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="app name and machine name are shared"`
Expected: FAIL — `machineName` prop is missing (Inertia assertion fails).

- [ ] **Step 3: Update the middleware**

In `app/Http/Middleware/HandleInertiaRequests.php`, add the import after line 5:

```php
use App\Services\AppService;
```

Replace the `share` return array (lines 40-48) so `name` uses the service and `machineName` is added:

```php
        return [
            ...parent::share($request),
            'name' => app(AppService::class)->getAppName(),
            'machineName' => app(AppService::class)->getAppSlug(),
            'appVersion' => config('app.version'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
```

- [ ] **Step 4: Add `machineName` to the shared-props TS type**

In `resources/js/types/global.d.ts`, update the `sharedPageProps` block (lines 18-23) to add `machineName`:

```ts
        sharedPageProps: {
            name: string;
            machineName: string;
            auth: Auth;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
```

- [ ] **Step 5: Run the test**

Run: `php artisan test --compact --filter="app name and machine name are shared"`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Middleware/HandleInertiaRequests.php resources/js/types/global.d.ts tests/Feature/EnkiTest.php
git commit -m "feat: share app name and machine name as Inertia props"
```

---

## Task 3: Platform skill — dynamic slug/name, Blade-rendered docs, seeder, controller

This task changes the Blade templates and **all three** call sites that render or compare them together, so the suite stays green. Default `APP_NAME=enki` keeps the slug `enki`, so existing tests asserting `'enki'` continue to pass; a new test proves the override.

**Files:**
- Modify: `resources/views/skills/enki/readme.blade.php`
- Modify: `resources/views/skills/enki/usage.blade.php`
- Modify: `app/Services/VirtualEnkiSkill.php`
- Modify: `database/seeders/SkillSeeder.php`
- Modify: `app/Http/Controllers/EnkiController.php`
- Test: `tests/Feature/VirtualEnkiSkillTest.php` (add override test)

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/VirtualEnkiSkillTest.php` (file already imports `User`, `Author`, `Skill`, `Assert`):

```php
test('platform skill slug, name and install command follow the configured app name', function (): void {
    config(['app.name' => 'skillhound', 'app.machine_name' => null]);

    $this->actingAs(User::factory()->create());

    // Web skill page resolves under the new slug and exposes the new label.
    $this->get(route('enki.skill', ['slug' => 'skillhound']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('selectedSkill.slug', 'skillhound')
            ->where('selectedSkill.name', 'skillhound')
        );

    // Rendered readme uses the new command prefix and not the old one.
    $summary = app(\App\Services\VirtualEnkiSkill::class)->toWebFull();
    expect($summary['readmeHtml'])->toContain('/skillhound add');
    expect($summary['readmeHtml'])->not->toContain('/enki add');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="follow the configured app name"`
Expected: FAIL — slug is still `enki`, route `skillhound` 404s, readme still contains `/enki add`.

- [ ] **Step 3: Templatize `readme.blade.php`**

In `resources/views/skills/enki/readme.blade.php`, apply these exact replacements (leave `ENKI_URL`, `ENKI_TOKEN`, and `/tmp/enki-download.zip` untouched):

| Old | New |
|-----|-----|
| `name: enki` | `name: {{ $appSlug }}` |
| `description: "Install skills from the Enki library. Activate when the user runs `/enki add {slug}`, `/enki search`, `/enki list`, `/enki info`, or `/enki categories` — or asks to install, discover, or browse skills."` | `description: "Install skills from the {{ $appName }} library. Activate when the user runs `/{{ $appSlug }} add {slug}`, `/{{ $appSlug }} search`, `/{{ $appSlug }} list`, `/{{ $appSlug }} info`, or `/{{ $appSlug }} categories` — or asks to install, discover, or browse skills."` |
| `# Enki` | `# {{ $appName }}` |
| ``Use the `/enki` sub-commands below`` | ``Use the `/{{ $appSlug }}` sub-commands below`` |
| ``## /enki add `<slug>` `` | ``## /{{ $appSlug }} add `<slug>` `` |
| `## /enki list` | `## /{{ $appSlug }} list` |
| ``## /enki search `<query>` `` | ``## /{{ $appSlug }} search `<query>` `` |
| ``Shorthand for `/enki list` with a `q` parameter.`` | ``Shorthand for `/{{ $appSlug }} list` with a `q` parameter.`` |
| ``choose which to install with `/enki add <slug>`.`` | ``choose which to install with `/{{ $appSlug }} add <slug>`.`` |
| ``## /enki info `<slug>` `` | ``## /{{ $appSlug }} info `<slug>` `` |
| `## /enki categories` | `## /{{ $appSlug }} categories` |
| ``offer to run `/enki list` filtered`` | ``offer to run `/{{ $appSlug }} list` filtered`` |

Note: these are all the `/enki` command occurrences. `$ENKI_TOKEN`/`$ENKI_URL` (uppercase, `$`-prefixed) and `/tmp/enki-download.zip` (hyphen, not a command) are intentionally kept. Single `{ }` JSON braces in the file are fine — Blade only interprets `{{ }}`, `{!! !!}`, and `@`.

- [ ] **Step 4: Templatize `usage.blade.php`**

In `resources/views/skills/enki/usage.blade.php`, apply:

| Old | New |
|-----|-----|
| ``The `enki` skill is installed once via the [Agent Install](/help/agent-install) prompt. After that, it gives your agent the `/enki add` command for all future sessions.`` | ``The `{{ $appSlug }}` skill is installed once via the [Agent Install](/help/agent-install) prompt. After that, it gives your agent the `/{{ $appSlug }} add` command for all future sessions.`` |
| ``Once the enki skill is active, install any library skill by slug:`` | ``Once the {{ $appSlug }} skill is active, install any library skill by slug:`` |
| `/enki add coding/code-reviewer` | `/{{ $appSlug }} add coding/code-reviewer` |

- [ ] **Step 5: Update `VirtualEnkiSkill.php`**

Replace the top of the class and constructor. Change lines 7-23 from the current `const SLUG`/constructor to:

```php
class VirtualEnkiSkill
{
    private readonly string $readme;

    private readonly string $usage;

    /** @var array<string, mixed> */
    private array $meta;

    public function __construct(private readonly AppService $appService)
    {
        $vars = ['appName' => $appService->getAppName(), 'appSlug' => $appService->getAppSlug()];
        $this->readme = view('skills.enki.readme', $vars)->render();
        $this->usage = view('skills.enki.usage', $vars)->render();
        $this->meta = $this->parseFrontmatter($this->readme);
    }

    public function slug(): string
    {
        return $this->appService->getAppSlug();
    }
```

Add the import after line 3 (`namespace App\Services;`) — `AppService` is in the same namespace, so **no `use` needed**; reference it directly. (`Illuminate\Support\Str` is already imported.)

In `matchesBaseFilters`, replace the haystack block (lines 53-58) so it searches the slug + display label instead of the frontmatter name:

```php
            $haystack = strtolower(implode(' ', array_filter([
                $this->slug(),
                $this->appService->getAppName(),
                $this->meta['description'] ?? '',
                implode(' ', (array) ($this->meta['tags'] ?? [])),
            ])));
```

In `toWebSummary` (lines 70-72), change the `slug`/`name` entries:

```php
            'slug' => $this->slug(),
            'name' => $this->appService->getAppName(),
```

In `toApiSummary` (lines 118-119), change the same two entries:

```php
            'slug' => $this->slug(),
            'name' => $this->appService->getAppName(),
```

- [ ] **Step 6: Update `SkillSeeder.php`**

At the start of `run()` (after line 13 `public function run(): void {`), add:

```php
        $appName = app(\App\Services\AppService::class)->getAppName();
        $appSlug = app(\App\Services\AppService::class)->getAppSlug();
```

Change the `$usage` closure (line 39) to accept the slug and use it for the install command and package scope:

```php
        $usage = fn (string $slug, string $appSlug): string => "## Quick start\n\nDrop into your agent runtime:\n\n```bash\n{$appSlug} add {$slug}\n```\n\nThen call it from any agent definition:\n\n```yaml\nskills:\n  - {$slug}@latest\n```\n\n## Programmatic\n\n```ts\nimport { runSkill } from \"@{$appSlug}/runtime\";\n\nconst result = await runSkill(\"{$slug}\", {\n  goal: \"your goal here\",\n  context: [\"doc-ref-1\", \"doc-ref-2\"],\n});\n```\n\n## Pinning a version\n\nLock to a specific version when you need reproducibility:\n\n```yaml\nskills:\n  - {$slug}@2.4.1\n```\n";
```

Update the demo-skill usage call (line 92) from `'usage' => $usage($s['slug']),` to:

```php
                    'usage' => $usage($s['slug'], $appSlug),
```

Update the platform-skill rendering (lines 111-112):

```php
        $enkiSkillMd = view('skills.enki.readme', ['appName' => $appName, 'appSlug' => $appSlug])->render();
        $enkiUsage = view('skills.enki.usage', ['appName' => $appName, 'appSlug' => $appSlug])->render();
```

Update the platform-skill row (lines 115-118):

```php
            ['slug' => $appSlug],
            [
                'name' => $appName,
                'summary' => "Install skills from the library by name. Gives your agent the `/{$appSlug} add` command.",
```

- [ ] **Step 7: Update `EnkiController.php`**

Add the import after line 4 (`use App\Models\Author;` block) — alongside the other `use App\...` lines:

```php
use App\Services\AppService;
```

Replace the three slug comparisons:
- Line 237: `if ($slug === VirtualEnkiSkill::SLUG) {` → `if ($slug === app(AppService::class)->getAppSlug()) {`
- Line 312: `if ($slug === VirtualEnkiSkill::SLUG) {` → `if ($slug === app(AppService::class)->getAppSlug()) {`
- Line 339: `if ($slug === VirtualEnkiSkill::SLUG) {` → `if ($slug === app(AppService::class)->getAppSlug()) {`

Replace the virtual-skill download filename (line 240):

```php
            return response()->download($tmpPath, app(AppService::class)->getAppSlug().'.zip', [
```

(Line 464's `matchesBaseFilters` call has no slug literal — leave it.)

- [ ] **Step 8: Run the new test and the existing platform-skill suite**

Run: `php artisan test --compact --filter=VirtualEnkiSkillTest`
Expected: PASS — the new override test passes and all existing `'enki'` assertions still pass (default slug is `enki`).

- [ ] **Step 9: Run the broader suite to catch regressions**

Run: `php artisan test --compact --filter="EnkiTest|ApiSkillsTest|AgentInstallTest|VirtualEnkiSkillTest"`
Expected: PASS.

- [ ] **Step 10: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/VirtualEnkiSkill.php app/Http/Controllers/EnkiController.php database/seeders/SkillSeeder.php resources/views/skills/enki/readme.blade.php resources/views/skills/enki/usage.blade.php tests/Feature/VirtualEnkiSkillTest.php
git commit -m "feat: derive platform skill slug, name and install command from app name"
```

---

## Task 4: Agent Install page

**Files:**
- Modify: `app/Http/Controllers/AgentInstallController.php:16-21`
- Modify: `resources/js/pages/help/AgentInstall.vue:8-12,20-64,131-137`
- Test: `tests/Feature/AgentInstallTest.php` (extend existing "view" test + add override)

- [ ] **Step 1: Write the failing test**

In `tests/Feature/AgentInstallTest.php`, update the existing "authenticated users can view the agent install page" test's Inertia assertion to also expect the new props, and add a new override test below it:

```php
test('authenticated users can view the agent install page', function (): void {
    $this->actingAs($this->user)
        ->get(route('help.agent-install'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('help/AgentInstall')
            ->where('apiToken', null)
            ->has('docs')
            ->has('appUrl')
            ->where('appName', 'enki')
            ->where('appSlug', 'enki')
        );
});

test('agent install page reflects the configured app name', function (): void {
    config(['app.name' => 'skillhound', 'app.machine_name' => null]);

    $this->actingAs($this->user)
        ->get(route('help.agent-install'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('appName', 'skillhound')
            ->where('appSlug', 'skillhound')
        );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=AgentInstallTest`
Expected: FAIL — `appName`/`appSlug` props are missing.

- [ ] **Step 3: Update the controller**

In `app/Http/Controllers/AgentInstallController.php`, add the import after line 4:

```php
use App\Services\AppService;
```

Update the `show()` render props (lines 16-21):

```php
        return Inertia::render('help/AgentInstall', [
            'docs' => HelpController::listDocs(),
            'apiToken' => $user->api_token,
            'appUrl' => config('app.url'),
            'appName' => app(AppService::class)->getAppName(),
            'appSlug' => app(AppService::class)->getAppSlug(),
        ]);
```

- [ ] **Step 4: Update the Vue page**

In `resources/js/pages/help/AgentInstall.vue`, extend the props (lines 8-12):

```ts
const props = defineProps<{
    docs: { slug: string; title: string }[];
    apiToken: string | null;
    appUrl: string;
    appName: string;
    appSlug: string;
}>();
```

Update the `prompt` computed (lines 23-63) — replace the brand prose with `props.appName` and every install path/command with `props.appSlug` (keep `/tmp/enki.zip` as-is per design):

```ts
    return `\
You have been granted access to our team's ${props.appName} skills library — a curated collection of AI agent skills.

Your first task is to install the ${props.appSlug} skill. This skill acts as your gateway to the library: once installed, it will guide you through discovering and installing additional skills in future sessions.

Library URL: ${props.appUrl}
Your API key: ${key}

## Steps

1. Download the ${props.appSlug} skill

Run this command to download the skill archive:

\`\`\`bash
curl -fsSL \\
  -H "Authorization: Bearer ${key}" \\
  "${props.appUrl}/api/skills/${props.appSlug}/download" \\
  -o /tmp/enki.zip
\`\`\`

2. Install the skill

Extract the archive into your Claude skills directory:

\`\`\`bash
mkdir -p ~/.claude/skills/${props.appSlug}
unzip -o /tmp/enki.zip -d ~/.claude/skills/${props.appSlug}
rm /tmp/enki.zip
\`\`\`

3. Read and follow the skill

\`\`\`bash
cat ~/.claude/skills/${props.appSlug}/SKILL.md
\`\`\`

Follow the instructions in SKILL.md carefully. It will explain how to authenticate with the library at ${props.appUrl}, browse available skills, and install them as needed.

---
Keep this API key confidential — it provides access to the skills library on your behalf.`;
```

Update the visible intro paragraph (lines 131-137) so the bolded brand uses the label:

```html
                <p>
                    Copy the prompt below and give it to any AI agent. It will
                    download the
                    <strong>{{ props.appName }}</strong> skill, which in turn
                    allows the agent to discover and install additional skills
                    from this library in future sessions.
                </p>
```

- [ ] **Step 5: Run the test**

Run: `php artisan test --compact --filter=AgentInstallTest`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/AgentInstallController.php resources/js/pages/help/AgentInstall.vue tests/Feature/AgentInstallTest.php
git commit -m "feat: rebrand agent install prompt from app name"
```

---

## Task 5: Frontend brand text + install command

These are template-only bindings to already-shared props. They are verified by type-check + build (there are no browser tests in this project; the prop values themselves are covered by Task 2 and Task 4).

**Files:**
- Modify: `resources/js/layouts/EnkiMinimalLayout.vue:90`
- Modify: `resources/js/layouts/EnkiAdminLayout.vue:58`
- Modify: `resources/js/pages/enki/TopBar.vue:1-2 (add page const), 103`
- Modify: `resources/js/pages/enki/DetailPane.vue:1-2 (import usePage), 78`

- [ ] **Step 1: Update `EnkiMinimalLayout.vue`**

`const page = usePage()` already exists at line 52. Change line 90 from:

```html
                <span class="enki-brand-name">enki</span>
```

to:

```html
                <span class="enki-brand-name">{{ page.props.name }}</span>
```

- [ ] **Step 2: Update `EnkiAdminLayout.vue`**

`const page = usePage()` already exists at line 52. Change line 58 from:

```html
                <span class="enki-brand-name">enki</span>
```

to:

```html
                <span class="enki-brand-name">{{ page.props.name }}</span>
```

- [ ] **Step 3: Update `TopBar.vue`**

`usePage` is already imported (line 2). Add a `page` const after the `defineEmits` block (after line 30):

```ts
const page = usePage();
```

Change line 103 from:

```html
                <span class="enki-brand-name">enki</span>
```

to:

```html
                <span class="enki-brand-name">{{ page.props.name }}</span>
```

(Leave the adjacent `<span class="enki-brand-sub">skills</span>` unchanged.)

- [ ] **Step 4: Update `DetailPane.vue`**

Change the import (line 2) from:

```ts
import { computed, ref, watch } from 'vue';
```

Add `usePage` from Inertia by inserting after line 1's existing imports — add a new line below line 5:

```ts
import { usePage } from '@inertiajs/vue3';
```

Add a `page` const after the `emit` definition (after line 18):

```ts
const page = usePage();
```

Change the install command (line 78) from:

```ts
const installCmd = computed(() => `enki add ${props.skill.slug}`);
```

to:

```ts
const installCmd = computed(
    () => `${page.props.machineName} add ${props.skill.slug}`,
);
```

- [ ] **Step 5: Type-check and build**

Run: `npm run build`
Expected: builds with no TypeScript errors. (`page.props.name` and `page.props.machineName` are typed via `global.d.ts` from Task 2.)

If the project exposes a lint/type-check script, also run: `npm run lint` (skip if not present).

- [ ] **Step 6: Commit**

```bash
git add resources/js/layouts/EnkiMinimalLayout.vue resources/js/layouts/EnkiAdminLayout.vue resources/js/pages/enki/TopBar.vue resources/js/pages/enki/DetailPane.vue
git commit -m "feat: bind brand text and install command to configured app name"
```

---

## Task 6: Full verification

- [ ] **Step 1: Run the entire test suite**

Run: `php artisan test --compact`
Expected: all green.

- [ ] **Step 2: Manual override smoke check**

Temporarily set `APP_NAME=skillhound` in `.env`, then:

```bash
php artisan config:clear
php artisan migrate:fresh --seed
```

Confirm in the DB that the platform skill seeded with slug `skillhound` and name `skillhound`:

Run: `php artisan tinker --execute 'echo \App\Models\Skill::where("slug", "skillhound")->value("name");'`
Expected: prints `skillhound`.

Revert `.env` to `APP_NAME=enki`, then `php artisan config:clear && php artisan migrate:fresh --seed`.

- [ ] **Step 3: Final commit (if any seed/state files changed — usually none)**

No commit expected here unless verification surfaced a fix.

---

## Self-Review Notes

- **Spec coverage:** AppService + two config keys (Task 1) ✓; helper used everywhere, never raw config for naming (Tasks 2-5) ✓; `machine_name` override with slug default (Task 1, AppService fallback) ✓; frontend brand (Task 5) ✓; platform skill slug/name/install command + docs (Task 3) ✓; agent install flow (Task 4) ✓; routes left untouched ✓; tests for default + override (Tasks 1-4) ✓.
- **Deviation from spec:** the `Str::slug` fallback lives in `AppService::getAppSlug()` (config `machine_name` is the nullable override slot) rather than being computed inside `config/app.php`. This keeps the logic in the service (matching the "helper is the home" preference) and makes `config(['app.name' => ...])` overrides recompute the slug in tests.
- **Default casing:** `APP_NAME` default is lowercase `enki`, preserving the current lowercase brand logo. The HTML `<title>` and mail "from" name (which already used `config('app.name')`) shift from `Enki` to `enki` — intentional normalization.
- **Type consistency:** shared props `name`/`machineName` (Task 2 type) match `page.props.name`/`page.props.machineName` usage (Task 5); `VirtualEnkiSkill::slug()` replaces `::SLUG` consistently across the service and controller (Task 3).
