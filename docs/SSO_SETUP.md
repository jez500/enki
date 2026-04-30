# Google SSO Setup

This application supports Google Single Sign-On via Laravel Socialite.

## How It Works

- When Google credentials are configured, traditional login, registration, and password reset are **disabled** — users must authenticate via Google.
- When credentials are not configured, the standard email/password flow is available.
- On first sign-in, a new user account is created automatically.
- On subsequent sign-ins, the existing account is found by email and linked to the Google identity.
- Email addresses are marked as verified automatically on Google sign-in.

## Configuration

Add the following to your `.env` file:

```env
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=/auth/google/callback

# Optional: restrict sign-in to specific email domains (comma-separated)
# Leave empty to allow any Google account
GOOGLE_ALLOWED_DOMAINS=yourcompany.com,partner.com
```

## Creating Google OAuth Credentials

1. Go to the [Google Cloud Console](https://console.cloud.google.com/).
2. Create a new project or select an existing one.
3. Navigate to **APIs & Services → Credentials**.
4. Click **Create Credentials → OAuth client ID**.
5. Set the application type to **Web application**.
6. Add your authorised redirect URI:
   - Local: `http://localhost:8000/auth/google/callback`
   - Production: `https://yourdomain.com/auth/google/callback`
7. Copy the **Client ID** and **Client Secret** into your `.env`.

## Allowed Domains

Set `GOOGLE_ALLOWED_DOMAINS` to a comma-separated list of email domains to restrict access:

```env
GOOGLE_ALLOWED_DOMAINS=acme.com
```

Users with a Google account outside the allowed domains will be shown an error and redirected to the login page. Leave the value empty (or unset) to allow any Google account.

## Routes

| Method | URI | Description |
|--------|-----|-------------|
| `GET` | `/auth/google` | Redirects to Google for authentication |
| `GET` | `/auth/google/callback` | Handles the OAuth callback from Google |

Both routes are only accessible to guests (unauthenticated users).
