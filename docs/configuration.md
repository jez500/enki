# Configuration

All configuration is done via environment variables passed to the Docker container (or set in `.env` for local development). Copy `.env.example` for a full reference.

## Core

| Variable | Default | Description |
|---|---|---|
| `APP_URL` | `http://localhost` | Public URL of the app — used in emails and OAuth redirect URIs |
| `APP_KEY` | *(auto-generated)* | 32-byte encryption key. Auto-generated on first start; see below for production |

## Database

| Variable | Default | Description |
|---|---|---|
| `DB_CONNECTION` | `sqlite` | Database driver: `sqlite` or `mysql` |
| `DB_DATABASE` | `/var/www/database/database.sqlite` | SQLite path, or MySQL database name |
| `DB_HOST` | — | MySQL host |
| `DB_PORT` | `3306` | MySQL port |
| `DB_USERNAME` | — | MySQL username |
| `DB_PASSWORD` | — | MySQL password |

## Integrations

| Variable | Default | Description |
|---|---|---|
| `GITHUB_TOKEN` | *(unset)* | Personal access token with `repo` scope — enables importing from private GitHub repositories. Public repos work without a token. |
| `GOOGLE_CLIENT_ID` | *(unset)* | Google OAuth client ID. When set, enables SSO and disables email/password auth. |
| `GOOGLE_CLIENT_SECRET` | *(unset)* | Google OAuth client secret |
| `GOOGLE_REDIRECT_URI` | `/auth/google/callback` | Full callback URL registered in Google Cloud Console |
| `GOOGLE_ALLOWED_DOMAINS` | *(unset)* | Comma-separated list of allowed email domains (e.g. `acme.com`). Leave unset to allow any Google account. |

---

## Persisting APP_KEY in production

On first start the container auto-generates an `APP_KEY`. If the container is replaced (update, redeploy) without persisting this key, existing sessions and encrypted values break.

Generate once and keep it:

```bash
docker run --rm jez500/enki:latest php artisan key:generate --show
```

Then set it explicitly in your `docker-compose.yml`:

```yaml
environment:
  APP_KEY: base64:your-generated-key-here==
```

---

## MySQL / MariaDB

SQLite is used by default. To switch to MySQL or MariaDB, add a database service and configure the connection:

```yaml
services:
  app:
    image: jez500/enki:latest
    ports:
      - "8080:8080"
    volumes:
      - enki_storage:/var/www/storage
    environment:
      APP_URL: http://localhost:8080
      DB_CONNECTION: mysql
      DB_HOST: db
      DB_PORT: 3306
      DB_DATABASE: enki
      DB_USERNAME: enki
      DB_PASSWORD: secret
    depends_on:
      db:
        condition: service_healthy

  db:
    image: mariadb:11
    environment:
      MARIADB_DATABASE: enki
      MARIADB_USER: enki
      MARIADB_PASSWORD: secret
      MARIADB_ROOT_PASSWORD: rootsecret
    volumes:
      - enki_db:/var/lib/mysql
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 5s
      retries: 10

volumes:
  enki_storage:
  enki_db:
```
