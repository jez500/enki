# Local Development

## Requirements

- PHP 8.3+
- Node.js 20+
- Composer 2

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
composer run dev
```

`composer run dev` starts the PHP dev server, queue worker, and Vite dev server concurrently. Visit **http://localhost:8000**.

## Running tests

```bash
php artisan test --compact
```

Filter to a specific test:

```bash
php artisan test --compact --filter=SkillTest
```

## Tech stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.3 |
| Frontend | Vue 3, Inertia.js v3, Tailwind CSS v4 |
| Database | SQLite (default), MySQL / MariaDB |
| Auth | Laravel Fortify, Laravel Socialite (Google) |
| Testing | Pest v4 |
| Container | PHP 8.3-FPM, Nginx, Supervisor |

## Docker base images

The production Docker image is built on top of `jez500/enki-base`, a set of pre-built images containing OS packages and PHP extensions. This keeps normal builds fast — only app-level steps run each time.

If you change `Dockerfile.base` (e.g. adding a new PHP extension), rebuild and push the base images:

```bash
# Builds linux/amd64 locally and pushes to Docker Hub
bash docker/build-base.sh

# Multi-platform (amd64 + arm64) builds run automatically via GitHub Actions
# when Dockerfile.base changes on the main branch.
```
