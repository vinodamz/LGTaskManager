# CI/CD — auto-deploy to Hostgator

Every push to `main` runs `.github/workflows/deploy.yml`, which:

1. Lints every `.php` file with `php -l` (fast — fails the build on a syntax error).
2. Uploads changed files via FTPS to your Hostgator subdomain.

State is tracked in `.ftp-deploy-sync-state.json` on the server, so only changed files transfer.

## One-time setup

### 1. Create a dedicated FTP account in cPanel

You _can_ use your main cPanel FTP credentials, but a scoped account is cleaner.

1. cPanel → **FTP Accounts** → **Add FTP Account**.
2. Login: `lgtm_deploy` (becomes `lgtm_deploy@yourdomain.com`).
3. Password: generate a strong one. Copy it.
4. Directory: `public_html/tasks` (or wherever your subdomain docroot is).
5. Quota: Unlimited. Click **Create FTP Account**.

### 2. Find your FTP server hostname

cPanel → **FTP Accounts** → next to your new account, click **Configure FTP Client**.
You'll see something like:

| | |
|---|---|
| FTP Username  | `lgtm_deploy@yourdomain.com` |
| FTP server    | `ftp.yourdomain.com` (or `s3744.bom1.stableserver.net`) |
| FTP port      | `21` |

Hostgator supports **explicit FTPS** (port 21 with TLS). The workflow uses that.

### 3. Add four secrets to the GitHub repo

GitHub → your repo → **Settings → Secrets and variables → Actions → New repository secret**.

| Name              | Value                                        |
|-------------------|----------------------------------------------|
| `FTP_SERVER`      | `ftp.yourdomain.com` (hostname only, no `ftp://`) |
| `FTP_USERNAME`    | `lgtm_deploy@yourdomain.com`                 |
| `FTP_PASSWORD`    | the password you set in step 1               |
| `FTP_SERVER_DIR`  | `/public_html/tasks/` (note leading & trailing slashes) — or just `/` if the FTP account is already scoped to that folder |

> **Note:** If you scoped the FTP account directory to `public_html/tasks` in step 1, the
> account's "root" already IS that folder. In that case set `FTP_SERVER_DIR=/`.
> If you used a generic FTP account that lands in `public_html/`, use `/public_html/tasks/`.

### 4. First-run setup on the server

The Action only uploads files — it doesn't create databases or run SQL. Before the first deploy
finishes successfully you still need to (one time, manually):

1. Create the MySQL DB + user (cPanel → MySQL Databases) — see [DEPLOY.md](DEPLOY.md).
2. Import `sql/schema.sql` via phpMyAdmin.
3. Create `includes/config.php` on the server with your DB credentials.
   (The Action will never overwrite this file — it's in the `exclude` list.)
4. Visit `https://tasks.yourdomain.com/install.php`, create the first admin, then delete `install.php`.

After that, every `git push` deploys automatically.

## Triggering a deploy manually

GitHub → **Actions** tab → **Deploy to Hostgator** → **Run workflow** → pick branch `main` → **Run workflow**.

Useful for forcing a re-upload after fixing something directly on the server.

## What does NOT get uploaded

Listed in the `exclude:` block of `deploy.yml`:

- `.git/`, `.github/` — repo metadata
- `README.md`, `DEPLOY.md`, `CICD.md` — docs (avoid info disclosure)
- `.gitignore`
- `includes/config.php` — **production DB credentials are preserved**

Everything else (`.htaccess`, `*.php`, `assets/`, `sql/`, `includes/config.example.php`) IS uploaded.

`sql/` is kept so you can run new migrations via phpMyAdmin if the schema changes. `.htaccess`
already denies web access to `sql/` and `includes/`.

## Forcing a full re-sync

If the server state ever drifts, delete `.ftp-deploy-sync-state.json` from the server via
File Manager, then trigger a manual deploy. The Action will re-upload everything.

## Troubleshooting

| Symptom | Fix |
|---|---|
| Action fails at `php -l` | A syntax error landed in `main`. The job log shows the file + line. Push a fix. |
| `ECONNREFUSED` / `ETIMEDOUT` | Wrong `FTP_SERVER` (try the cPanel server hostname instead of `ftp.yourdomain.com`), or Hostgator's firewall is blocking GitHub's runners. Try `port: 990` and `protocol: ftps-legacy` in `deploy.yml`. |
| `530 Login authentication failed` | Wrong username or password. Double-check the full `user@domain` format. |
| Files upload but site shows 500 | `includes/config.php` doesn't exist on the server, or has wrong DB creds. Check cPanel → **Errors**. |
| Deploy says "0 files transferred" | Working as designed — nothing changed since the last sync. |
