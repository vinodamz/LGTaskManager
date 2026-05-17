# Deploy to Hostgator cPanel

End-to-end steps to put LG Task Manager on a Hostgator subdomain.

## 1. Create the subdomain

1. Log in to cPanel (https://portal.hostgator.com → Hosting → **Launch cPanel**).
2. **Domains → Subdomains**.
3. Subdomain: `tasks`. Domain: pick your main domain. Document root: e.g. `public_html/tasks`.
4. Click **Create**. cPanel makes the folder for you.

## 2. Create the database

1. cPanel → **MySQL Databases**.
2. **Create New Database** → name: `lgtasks`. cPanel prefixes it with your account, so the
   real name becomes something like `cpaneluser_lgtasks`. Note it down.
3. **MySQL Users → Add New User** → pick a strong password. Note username + password.
4. **Add User To Database** → grant **ALL PRIVILEGES**.

## 3. Upload the code

Two options:

### Option A — Git (cleanest, if your plan has Git Version Control)

1. cPanel → **Git Version Control → Create**.
2. Clone URL: `https://github.com/vinodamz/LGTaskManager.git`
3. Repository path: `/home/<cpaneluser>/public_html/tasks` (or wherever your subdomain points)
4. Branch: `main`. Click **Create**.
5. To pull updates later: same panel → **Manage → Pull or Deploy**.

### Option B — File Manager / FTP

1. cPanel → **File Manager** → navigate to `public_html/tasks`.
2. Upload all files except `.git/` and `includes/config.php`.
   Easiest: zip the project locally (`git archive -o lgtm.zip HEAD`), upload, extract.

## 4. Create `includes/config.php` on the server

cPanel File Manager → `public_html/tasks/includes/` → **+ File** → `config.php`.

Paste the contents of `config.example.php` and fill in:

```php
'db' => [
    'host'     => 'localhost',
    'name'     => 'cpaneluser_lgtasks',   // exact name from step 2
    'user'     => 'cpaneluser_lgtasks',   // exact username from step 2
    'password' => 'the password you set',
    'charset'  => 'utf8mb4',
],
```

Save.

## 5. Import the schema

1. cPanel → **phpMyAdmin** → pick your `lgtasks` database in the left pane.
2. **Import** tab → choose `sql/schema.sql` → **Go**.

## 6. Create the first admin

1. Open `https://tasks.yourdomain.com/install.php` in your browser.
2. Enter your name and a 4–6 digit PIN. Submit.
3. **Delete `install.php`** from the server via File Manager. (The page also tells you to.)

## 7. Log in

1. Visit `https://tasks.yourdomain.com/`.
2. Enter your PIN. You're in.
3. Go to **Users** to add staff and assign them PINs.

## 8. (Recommended) Enable HTTPS

1. cPanel → **SSL/TLS Status**. Run **AutoSSL** if your subdomain shows as unsecured.
2. Once the green padlock works, edit `.htaccess` and uncomment the `RewriteCond %{HTTPS} off`
   block to force HTTPS.

## Troubleshooting

| Symptom | Fix |
|---|---|
| White page / 500 error | cPanel → **Errors** to read the PHP error log. Most common: bad DB credentials in `config.php`. |
| "Access denied for user" | The DB user wasn't added to the database in step 2. Re-run **Add User To Database**. |
| "Could not find driver" | PHP MySQL extension not enabled. cPanel → **Select PHP Version** → Extensions → tick `mysqli` and `pdo_mysql`. |
| Login spins / nothing happens | Sessions can't write. cPanel → **PHP Settings** → confirm `session.save_path` is writable, or use `/home/<cpaneluser>/tmp`. |
| `install.php` says "Already installed" but you can't log in | You created an admin earlier. Use phpMyAdmin → `users` table → check the row exists, then reset PIN via the `update users set pin_hash = ...` (use `php -r "echo password_hash('NEWPIN', PASSWORD_DEFAULT);"` to generate the hash). |
