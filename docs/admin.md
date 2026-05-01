# Admin Panel

The admin panel is accessible at `/enki/admin/users` and is only visible to users with the `admin` role. An **Admin** link appears in the avatar dropdown at the top-right for admin users.

## Assigning the first admin

After registering your account through the app, promote it via the container shell:

```bash
docker compose exec app php artisan user:assign-admin your@email.com
```

## Managing users

From the admin panel you can:

- **View all users** — name, email, role, and join date
- **Change a user's role** — toggle between `member` and `admin` using the inline dropdown; takes effect immediately
- **Delete a user** — permanently removes the account (admins cannot delete themselves)

## Resetting everything

```bash
docker compose down -v   # removes containers and named volumes
docker compose up -d     # fresh start
```
