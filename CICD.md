# CI/CD — auto-deploy to Hostgator (cPanel pull model)

## How it works

```
   git push                                       cPanel git pull
GitHub  ──────► GitHub Actions  ──── HTTPS:2083 ───────────────► GitHub
                      │                       │
                      │ POST UAPI              ▼
                      ▼                  cPanel clones / updates
              cPanel UAPI calls           /home/<user>/repos/LGTaskManager
                  • update                       │
                  • deployment/create            ▼
                                       runs .cpanel.yml
                                                │
                                                ▼
                                       rsync into docroot
                                  /home/<user>/thelittlegraduates.in/lgtaskmanager
```

No FTP. No credentials traverse the public internet *except* the cPanel API token (sent
over HTTPS in an `Authorization` header). cPanel itself fetches from GitHub.

## One-time setup (in cPanel)

### 1. Enable Git Version Control and clone the repo

1. cPanel → **Git Version Control** → **Create**.
2. **Clone a Repository** ON.
3. Clone URL: `https://github.com/vinodamz/LGTaskManager.git`
4. Repository path: `/home/ideyyfbn/repos/LGTaskManager`
   (use a path OUTSIDE the docroot, so `.git/` never gets web-served)
5. Repository Name: `LGTaskManager`. **Create**.

cPanel will git-clone for you. The first checkout takes a few seconds.

### 2. Create a cPanel API token

1. cPanel → top-right user menu → **Manage API Tokens** (sometimes shown as just **API Tokens**).
2. **Create**. Name: `gha-deploy`.
3. (Optional, if your cPanel supports scopes) Restrict to `VersionControl` and `VersionControlDeployment`.
4. **Create** → copy the token *immediately* (you cannot view it again).

### 3. Find the exact cPanel hostname

cPanel → top-right user menu → look for "Server Name" or check the URL you log in with.
It looks like `s3744.bom1.stableserver.net`. That's `CPANEL_HOST` below.

> **Why the server hostname, not your domain?** The SSL cert on the cPanel control panel
> (port 2083) is issued for the server hostname. Using your domain would fail TLS
> verification.

### 4. Add four secrets to the GitHub repo

GitHub → repo **Settings → Secrets and variables → Actions → New repository secret**.

| Name           | Value                                              |
|----------------|----------------------------------------------------|
| `CPANEL_HOST`  | `s3744.bom1.stableserver.net` (your actual server) |
| `CPANEL_USER`  | `ideyyfbn`                                         |
| `CPANEL_TOKEN` | the token from step 2                              |

The old `FTP_*` secrets can be deleted.

### 5. (Optional) Test the deploy manually

Before pushing code, click cPanel → **Git Version Control → Manage → Pull or Deploy** for
the repo. This runs `.cpanel.yml`. The first run populates the docroot with the latest files.

After that, every `git push` to `main` re-runs the same flow automatically.

## App bootstrap (still manual, one time)

The Action ships **code**, not databases. Once the first deploy lands files in the docroot:

1. cPanel → **MySQL Databases** → create DB + user + grant ALL.
2. cPanel → **phpMyAdmin** → pick the DB → **Import** → upload `sql/schema.sql`.
3. cPanel → **File Manager** → `/home/ideyyfbn/thelittlegraduates.in/lgtaskmanager/includes/`
   → create `config.php` from `config.example.php` with real DB credentials.
   This file is **excluded from rsync** in `.cpanel.yml`, so future deploys won't touch it.
4. Confirm subdomain `lgtaskmanager.thelittlegraduates.in` docroots to
   `/home/ideyyfbn/thelittlegraduates.in/lgtaskmanager`.
5. Visit `https://lgtaskmanager.thelittlegraduates.in/install.php` → create the first admin
   → **delete** `install.php` via File Manager.

## How to inspect a deploy

| Where | What you see |
|---|---|
| GitHub → **Actions** tab | Lint logs, UAPI HTTP responses (cPanel returns JSON with `status`, `messages`, `errors`) |
| cPanel → **Git Version Control → Manage → Pull or Deploy** | Most recent pull/deploy timestamps + log |
| cPanel → **Errors** | PHP runtime errors after deploy |

## Manual trigger

GitHub → **Actions** → **Deploy to Hostgator (cPanel pull)** → **Run workflow** → main → **Run workflow**.

## Forcing a clean slate

If the rsync gets confused:

1. cPanel → **File Manager** → delete everything under the docroot *except* `includes/config.php`.
2. cPanel → **Git Version Control → Manage → Pull or Deploy** → click **Deploy HEAD**.
   Re-runs `.cpanel.yml`, repopulates the docroot.

## Security notes

- The cPanel API token has the scopes you grant it. If your cPanel version doesn't support
  scopes, the token has **full account access** — treat it like a root password.
- Rotate the token at least every 90 days. cPanel → API Tokens → revoke + create new →
  update `CPANEL_TOKEN` in GitHub secrets.
- `.cpanel.yml` excludes `includes/config.php` from rsync. **Never** commit DB credentials.
- The repo clone at `/home/ideyyfbn/repos/LGTaskManager` is outside the docroot — `.git/`
  is never web-served. Verify by visiting `https://lgtaskmanager.thelittlegraduates.in/.git/`
  and confirming you get a 404.
