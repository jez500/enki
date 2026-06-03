# Configurable App Name via `APP_NAME`

**Date:** 2026-06-03
**Status:** Approved design — ready for implementation plan

## Goal

The app is named "enki". Any deployed instance must be able to override that
name with a single environment variable. The override applies everywhere "enki"
is displayed, and to the default platform skill's name and install command.
Test value: `skillhound`.

## Core Concept

One env var, `APP_NAME`, drives everything. It is consumed two ways:

- **Display label (verbatim):** logo/brand text, platform skill display name,
  HTML page title. Shown exactly as typed in `APP_NAME`.
- **Machine name (`Str::slug(APP_NAME)`):** the platform skill's slug and the
  CLI command prefix in install commands.

Default is `enki`. With `APP_NAME=skillhound`:
- Brand text reads "skillhound"
- Install commands read `skillhound add <slug>`
- The platform skill's slug becomes `skillhound`, display name "skillhound"

## Source of Truth

Two config keys. The machine name has its own env override so it can diverge
from the slug of the display name when needed; otherwise it defaults to that
slug:

```php
// config/app.php
'name' => env('APP_NAME', 'enki'),                                   // default changes 'Laravel' -> 'enki'
'machine_name' => env('APP_MACHINE_NAME') ?: Str::slug(env('APP_NAME', 'enki')),  // new key
```

Backend never reads these config keys directly for app-name purposes. Instead,
a single service wraps them:

```php
// app/Services/AppService.php
class AppService
{
    public function getAppName(): string
    {
        return config('app.name');
    }

    public function getAppSlug(): string
    {
        return config('app.machine_name');
    }
}
```

**Rule: throughout the app, use `AppService::getAppName()` /
`AppService::getAppSlug()` — never `config('app.name')` / `config('app.machine_name')`
directly.** Resolve via the container (`app(AppService::class)`), constructor
injection, or a thin facade/helper, following existing conventions.

The frontend cannot call the service, so `HandleInertiaRequests::share()`
calls it once and exposes the values as shared props: `name` (already shared —
switch its source to `AppService::getAppName()`) and a new `machineName`
(`AppService::getAppSlug()`). Frontend reads `page.props.name` /
`page.props.machineName`.

## Changes by Area

### Config / env
- `config/app.php`: change `name` default from `'Laravel'` to `'enki'`; add
  `'machine_name' => env('APP_MACHINE_NAME') ?: Str::slug(env('APP_NAME', 'enki'))`.
  Ensure `Str` is imported/usable in the config file.
- `app/Services/AppService.php`: new service with `getAppName(): string` and
  `getAppSlug(): string` wrapping the two config keys.
- `.env` and `.env.example`: set `APP_NAME=enki`. Document the optional
  `APP_MACHINE_NAME` override and the test value `skillhound`.
- `HandleInertiaRequests::share()`: source `name` from `AppService::getAppName()`
  and add `'machineName' => AppService::getAppSlug()`.

### Frontend — verbatim brand
Replace hardcoded "enki" with the shared `name` prop (via `usePage()`):
- `resources/js/layouts/EnkiMinimalLayout.vue:90`
- `resources/js/layouts/EnkiAdminLayout.vue:58`
- `resources/js/pages/enki/TopBar.vue:103` (the `enki` brand span; the
  `skills` subtext span stays as-is)

Install command:
- `resources/js/pages/enki/DetailPane.vue:78`: change
  `'enki add ${props.skill.slug}'` to use `${page.props.machineName} add ${props.skill.slug}`.

(`AuthSplitLayout.vue` already uses `page.props.name`; `app.blade.php` title
already uses `config('app.name')` — no change needed.)

### Backend — the platform skill (slug + name dynamic)
- `app/Services/VirtualEnkiSkill.php`: replace `const SLUG = 'enki'` with a
  dynamic slug sourced from `AppService::getAppSlug()` (e.g. an accessor
  method). Update all internal `self::SLUG` references (search filters,
  `toWebSummary()`, `toApiSummary()`). Change the display name default from
  `'Enki'` to `AppService::getAppName()` (lines ~72, ~119).
- `app/Http/Controllers/EnkiController.php`: lines ~237, ~312, ~339, ~464 —
  replace `$slug === VirtualEnkiSkill::SLUG` / static-const usage with the
  dynamic slug from the service / accessor.
- `database/seeders/SkillSeeder.php`: the platform skill row (lines ~114-129)
  keys/names off `AppService::getAppSlug()` (slug) and `AppService::getAppName()`
  (name); the usage generator (lines ~37-39) emits
  `{AppService::getAppSlug()} add {$slug}` instead of literal `enki add`.

### Backend — skill docs (Blade)
- `resources/views/skills/enki/readme.blade.php` and `usage.blade.php`:
  replace literal `/enki add` and `enki add` references with the command name
  from `AppService::getAppSlug()`. These Blade files are rendered by
  `VirtualEnkiSkill`, so the value is passed in to the view (preferred) or read
  via the service.

### Tests
- Update existing assertions referencing "Enki", "enki add", or slug `enki`.
- Add coverage proving the override: with `app.name` config set to
  `skillhound` in the test, assert `AppService` returns the right name/slug,
  the shared Inertia prop, the platform skill slug (`skillhound`), display
  name, and an install command (`skillhound add ...`).
- Add a case proving `APP_MACHINE_NAME` can override the slug independently of
  the display name.

## Decisions / Non-Goals

- **Routes stay `/enki/...` and `/enki/admin/...`.** URL paths are
  infrastructure, not displayed branding. Confirmed in scoping.
- CSS class names (`enki-app`, `enki-brand-name`, ...), PHP class names
  (`EnkiController`, `VirtualEnkiSkill`), localStorage keys (`enki-dark`,
  `enki-accent`), temp-file prefixes, and `resources/views/skills/enki/`
  directory path are unchanged.
- Re-seeding under a *new* `APP_NAME` creates a fresh skill row keyed on the
  new slug rather than renaming the existing one. Acceptable: a deployment
  sets its name once. (When switching names in dev, the old row remains;
  remove it manually if desired.)

## Success Criteria

1. With no env override, the app displays "enki" everywhere it did before and
   install commands read `enki add <slug>` (functionally unchanged).
2. Setting `APP_NAME=skillhound` changes: brand text, page title, platform
   skill display name, platform skill slug, and all install command prefixes
   to "skillhound" / `skillhound`.
3. Routes and internal identifiers (classes, CSS, storage keys) are unaffected.
4. Tests cover both the default and the overridden case and pass.
