# LG Task Manager

Internal task tracker for Little Graduates staff. PHP + MySQL, designed to drop into
a Hostgator cPanel shared-hosting subdomain.

## Features

- **PIN-only login** — staff sign in with a 4–6 digit numeric PIN. PINs are bcrypt-hashed.
- **Task CRUD** — title, description, status (todo / in progress / done), priority, due date, assignee.
- **Filters & search** — by status, assignee, free-text.
- **Admin user management** — admins create/edit/deactivate staff and rotate PINs.
- **CSRF protection, rate-limited login, secure session cookies.**

## Tech

| | |
|---|---|
| Language | PHP 7.4+ / 8.x |
| Database | MySQL (InnoDB, utf8mb4) |
| Frontend | Server-rendered HTML + a single CSS file (no build step) |
| Hosting | Hostgator cPanel shared hosting (also works on any LAMP server) |

## File layout

```
LGTaskManager/
├── index.php          # Dashboard
├── login.php          # PIN entry
├── logout.php
├── tasks.php          # List / create / edit / delete tasks
├── admin.php          # User management (admins only)
├── install.php        # One-time bootstrap of the first admin (delete after install)
├── .htaccess          # Protects /includes and /sql, sets security headers
├── includes/
│   ├── config.example.php   # Copy to config.php and edit
│   ├── db.php               # PDO connection
│   ├── auth.php             # Session + PIN auth helpers
│   ├── functions.php        # View helpers
│   ├── header.php / footer.php
├── assets/css/style.css
└── sql/schema.sql     # Database schema
```

See [DEPLOY.md](DEPLOY.md) for cPanel deployment steps.

## Local development

Requires PHP 7.4+ and MySQL.

```bash
cp includes/config.example.php includes/config.php
# Edit includes/config.php with your local DB credentials.
mysql -u root -p < sql/schema.sql
php -S localhost:8000
# Open http://localhost:8000/install.php to create the first admin, then delete install.php.
```

## Security notes

- PINs are short. The app rate-limits login attempts (5 wrong PINs → 30 s lockout)
  but a 4-digit PIN has only 10 000 combinations. For internal staff use this is acceptable;
  if you ever open the app to a wider audience, switch to username + PIN or a real password.
- `install.php` MUST be deleted after first use — it stays disabled once an admin exists,
  but leaving it on the server is bad hygiene.
- `includes/config.php` contains DB credentials and is gitignored. Never commit it.
