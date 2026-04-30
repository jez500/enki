# Enki — Internal Skills Library

Enki is a self-hosted library for managing and sharing AI agent skills within a team. It gives your team a single, searchable place to discover, import, and curate Claude skills — with version tracking, categorisation, starring, and role-based administration.

## Features

- **Browse & search** — full-text search across skill names, slugs, summaries, and tags with keyboard navigation
- **Categories** — organise skills into filterable categories with custom icons and colours
- **Starring** — bookmark favourite skills per user
- **Detail pane** — view README, usage guide, changelog, file manifest, and activity log for each skill
- **Submit skills** — upload a skill archive (`.zip`) directly or import from a public GitHub repository
- **GitHub sync** — keep externally-hosted skills in sync with their source repository
- **Public / private visibility** — mark skills as private so only their creator can see them
- **Admin panel** — manage users and assign roles from a dedicated admin interface
- **Google SSO** — optional sign-in via Google with optional domain restriction; falls back to email/password when unconfigured
- **Dark mode & accent colour** — per-user theme preferences stored in the browser

## Quick start with Docker

The quickest way to run Enki is with Docker Compose.

```bash
# Clone the repository
git clone <repo-url> enki
cd enki

# Start the application (builds the image on first run)
docker compose up -d

# Open in your browser
open http://localhost:8080
```

Data is persisted in two named Docker volumes (`enki_database` and `enki_storage`) so your skills and users survive container restarts.

### First admin

After the app starts, register an account then promote it to admin via the container shell:

```bash
docker compose exec app php artisan user:assign-admin your@email.com
```

Once you are an admin, an **Admin** link appears in the avatar dropdown at the top-right of the Enki page. From there you can manage users and assign roles.

### Resetting everything

```bash
docker compose down -v   # removes containers and volumes
docker compose up -d     # fresh start
```

## Configuration

All configuration is done via environment variables. Copy `.env.example` for a reference of every available option — the most important ones are below.

| Variable | Default | Description |
|---|---|---|
| `APP_URL` | `http://localhost` | Public URL of the app (used in emails and OAuth redirects) |
| `APP_KEY` | *(auto-generated)* | 32-byte encryption key — auto-generated on first start; persist it in production |
| `GITHUB_TOKEN` | *(unset)* | Personal access token for importing skills from private GitHub repos |
| `GOOGLE_CLIENT_ID` | *(unset)* | Google OAuth client ID; when set, enables SSO and disables email/password auth |
| `GOOGLE_CLIENT_SECRET` | *(unset)* | Google OAuth client secret |
| `GOOGLE_REDIRECT_URI` | `/auth/google/callback` | Full callback URL registered in Google Cloud Console |
| `GOOGLE_ALLOWED_DOMAINS` | *(unset)* | Comma-separated list of allowed email domains (e.g. `acme.com`); leave unset to allow any Google account |

### Persisting APP_KEY in production

On first start the entrypoint generates an `APP_KEY` and writes it to `.env` inside the container. If the container is replaced this key is lost, breaking existing sessions and encrypted values.

For production, generate a key once and pass it explicitly:

```bash
# Generate a key
docker run --rm enki-app php artisan key:generate --show

# Add it to your docker-compose.yml or secrets manager
APP_KEY=base64:...
```

### Google SSO

Enki supports Google Single Sign-On. When `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` are configured:

- Traditional email/password login, registration, and password reset are **disabled**
- New users are provisioned automatically on first Google sign-in
- Set `GOOGLE_ALLOWED_DOMAINS` to restrict access to specific email domains

**Setting up Google OAuth credentials:**

1. Go to [Google Cloud Console](https://console.cloud.google.com/) → APIs & Services → Credentials
2. Create an OAuth 2.0 client ID (Web application)
3. Add the authorised redirect URI: `https://yourdomain.com/auth/google/callback`
4. Copy the client ID and secret into your environment

### GitHub skill import

Set `GITHUB_TOKEN` to a personal access token with `repo` scope to enable importing skills from private GitHub repositories. Public repositories work without a token.

## Running without Docker

### Requirements

- PHP 8.3+
- Node.js 20+
- Composer 2

### Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
composer run dev     # starts PHP, queue worker, and Vite dev server together
```

The `composer run dev` command starts everything concurrently. Visit `http://localhost:8000`.

### Running tests

```bash
php artisan test --compact
```

## Admin panel

The admin panel lives at `/enki/admin/users` and is accessible to users with the `admin` role.

From the admin panel you can:

- **View all users** — name, email, role, and join date
- **Change a user's role** — toggle between `member` and `admin` using the inline dropdown; takes effect immediately
- **Delete a user** — permanently removes the account (admins cannot delete themselves)

The **Admin** link in the avatar dropdown is only shown to admin users.

## Tech stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.3 |
| Frontend | Vue 3, Inertia.js v3, Tailwind CSS v4 |
| Database | SQLite (default) |
| Auth | Laravel Fortify, Laravel Socialite (Google) |
| Testing | Pest v4 |
| Container | PHP 8.3-FPM, Nginx, Supervisor |
