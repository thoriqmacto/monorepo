# monorepo — Laravel + Next.js starter

A reusable starter kit for new web apps. Laravel 12 API backend, Next.js 15 frontend, organized as a Turborepo.

```
apps/
├── api/   Laravel 12 REST API (Sanctum auth, SQLite default)
└── web/   Next.js 15 App Router (TypeScript, Tailwind 4, shadcn/ui)
```

After setup you get a working baseline:

1. `/` public landing page
2. `/register` and `/login` auth flow
3. `/dashboard` authenticated page that talks to the Laravel API

Deploying it:

| What | Goes where | Guide |
|---|---|---|
| `apps/api` | your own VPS, pushed by GitHub Actions over SSH (`.github/workflows/backend-deploy.yml`) | [Deploy the Laravel API to a VPS](#deploy-the-laravel-api-to-a-vps) |
| `apps/web` | Vercel, built from the monorepo root | [Deploy the Next.js frontend to Vercel](#deploy-the-nextjs-frontend-to-vercel) |
| both | the checks that gate every push (`.github/workflows/ci.yml`) | [Continuous integration](#continuous-integration) |

---

## Fresh install — local mode

The API runs on your machine. Requires **Node ≥ 20**, **PHP ≥ 8.2**, **Composer**.

```bash
# 1. Clone into the name you want for your project
git clone https://github.com/thoriqmacto/monorepo.git my-project
cd my-project

# 2. Start a fresh Git repository for this project
rm -rf .git
git init
git branch -M main

# 3. Install Node dependencies
npm install

# 4. Interactive setup — picks project name, mode, port, auth mode
npm run setup

# 5. Start everything
npm run dev
```

> **Windows PowerShell** — replace step 2 with:
> ```powershell
> Remove-Item -Recurse -Force .git
> git init
> git branch -M main
> ```

> **Why remove `.git`?** `git clone` creates a repository already linked to the original `monorepo` remote. Removing `.git` and running `git init` gives your project a clean history with no connection to the starter. If you'd rather keep the starter's history, skip step 2 and jump to [keeping the starter history](#keeping-the-starter-history) below.

What the setup wizard asks:

| Prompt | Notes |
|---|---|
| **Project name** | Sets `APP_NAME` (Laravel) and `NEXT_PUBLIC_APP_NAME` (browser title). Defaults to the directory name. |
| **Where will the API run?** | Pick "Local machine". |
| **Laravel Herd?** (macOS only) | If yes: asks for Herd parked root + project slug, symlinks `apps/api` there. API URL becomes `http://<slug>.test`. |
| **API port** (no Herd) | Default `8000`. API URL becomes `http://localhost:<port>`. |
| **Auth mode** | `bearer` (default) or `cookie`. |
| **Seed demo user?** | Creates `demo@example.com` / `password`. |

Visit **http://localhost:3000** → sign in or register → `/dashboard`.

**Demo credentials** (after seeding):

```
email    demo@example.com
password password
```

---

## Fresh install — remote mode

The API is hosted elsewhere; only the Next.js frontend runs locally.

```bash
# 1. Clone into the name you want
git clone https://github.com/thoriqmacto/monorepo.git my-project
cd my-project

# 2. Start a fresh Git repository for this project
rm -rf .git
git init
git branch -M main

# 3. Install Node dependencies
npm install

# 4. Interactive setup
npm run setup

# 5. Start the frontend only
npm run dev:web
```

When prompted:

| Prompt | Example value |
|---|---|
| **Project name** | `My App` |
| **Where will the API run?** | Pick "Remote backend". |
| **Backend API origin** | `https://api.example.com` (no path) |
| **Frontend origin** | `https://app.example.com` (for CORS) |
| **Auth mode** | `bearer` (default) |

Laravel bootstrap (migrate, key:generate) is skipped in remote mode — run those on the remote host.

---

## Connect the new project to GitHub

After running setup, create an **empty** GitHub repository (no README, no `.gitignore`, no license — adding those creates a commit that conflicts with your first push).

### Prerequisite: SSH key

GitHub no longer accepts account passwords for Git operations. **SSH is the recommended method.**

If you don't already have an SSH key registered with GitHub:

```bash
# Generate a key (skip if you already have one)
ssh-keygen -t ed25519 -C "your-email@example.com"

# Copy the public key
cat ~/.ssh/id_ed25519.pub
```

Paste the output into **GitHub → Settings → SSH and GPG keys → New SSH key**.

Then verify the connection:

```bash
ssh -T git@github.com
```

On the first connection Git may ask whether to trust GitHub's host key — type `yes`. A successful response looks like:

```
Hi <username>! You've successfully authenticated, but GitHub does not provide shell access.
```

### Push your project

```bash
git add .
git commit -m "Initial project setup"

git remote add origin git@github.com:<username>/<new-repository>.git
git remote -v   # confirm the remote is correct

git push -u origin main
```

### HTTPS alternative (requires a Personal Access Token)

If you prefer HTTPS, note that GitHub **no longer accepts your account password** for Git operations — you must use a [Personal Access Token (PAT)](https://github.com/settings/tokens) in its place:

```bash
git remote add origin https://github.com/<username>/<new-repository>.git
git push -u origin main
# When prompted for a password, enter your PAT, not your GitHub password.
```

---

## Keeping the starter history

If you want to preserve the starter's Git history and simply point the repository at your own remote, skip the `rm -rf .git` step and replace the remote instead:

```bash
git remote remove origin
git remote add origin git@github.com:<username>/<new-repository>.git
git push -u origin main
```

---

## Deploy the Laravel API to a VPS

Two workflows ship in `.github/workflows/`:

| File | Trigger | What it does |
|---|---|---|
| `ci.yml` | every pull request, every push to `main` | Tests, formatting, type-check, build, dependency audits. See [Continuous integration](#continuous-integration). |
| `backend-deploy.yml` | after **CI succeeds on `main`**, or a manual run | SSHes into your VPS and fast-forwards the clone that lives there to the commit CI just validated. |

```
push to main ──▶ CI (ci.yml) ──green──▶ Deploy API (backend-deploy.yml) ──ssh──▶ VPS
                                                                                 │
                     apps/web is deployed separately by Vercel ◀─────────────────┘ (not touched)
```

**The workflow deploys code; it does not provision the server.** It never writes `.env`,
never installs nginx/PHP/MySQL, never creates the database, and never touches `apps/web`.
Steps 1–3 below are one-time manual work on the box. Steps 4–5 are what make deploys
automatic afterwards.

### 1. Prepare the server (once)

The deploy works by running `git fetch` + `git reset --hard <sha>` inside an existing
clone, so the server needs a real, working checkout before the first deploy.

```bash
# On the VPS, as the user the deploy will log in as (e.g. "deploy")
sudo apt install -y php8.2-fpm php8.2-mbstring php8.2-xml php8.2-curl php8.2-sqlite3 \
                    php8.2-bcmath php8.2-intl composer nginx git

# Give the server read-only pull access to the repository
ssh-keygen -t ed25519 -C "deploy@myserver"
cat ~/.ssh/id_ed25519.pub
```

Add that public key to **GitHub → your repository → Settings → Deploy keys → Add deploy
key**, leaving "Allow write access" unchecked. A deploy key is scoped to this one
repository; adding the server key to your personal account instead would give the box
access to everything you can push to.

Verify and clone:

```bash
ssh -T git@github.com     # type "yes" at the host-key prompt; must succeed non-interactively later
cd /var/www
git clone git@github.com:<username>/<repository>.git my-project
cd my-project/apps/api

cp .env.example .env
# Edit .env: APP_ENV=production, APP_DEBUG=false, APP_URL, FRONTEND_URL,
# CORS_ALLOWED_ORIGINS, DB_*, MAIL_*  — see "Environment reference" below.

composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan storage:link

# Laravel must be able to write these; the deploy's `artisan down` needs it too
sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

`.env` is gitignored, so `git reset --hard` during a deploy never clobbers it. That also
means **the workflow cannot create it** — a missing `.env` on the server is a failed
deploy, not a self-healing one.

### 2. Install the nginx vhost (once)

`deploy/nginx/api.conf` is the reference vhost, committed so it is reviewable instead of
hand-edited on the box. Copy it and fill in the four placeholders it lists at the top:
`server_name` (both blocks), the `ssl_certificate` / `ssl_certificate_key` paths, the
`root` path (replace `YOUR_PROJECT` with your cloned directory name), and the PHP-FPM
socket if you are not on the packaged `php8.2-fpm`.

```bash
sudo cp deploy/nginx/api.conf /etc/nginx/sites-available/api
sudo ln -s /etc/nginx/sites-available/api /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Two things in that file are load-bearing and are explained in its comments — don't
"simplify" them away: the `:80 → :443` redirect returns **308** (a 301/302 turns a POST
into a GET and the API answers `405`), and the PHP `location` includes `fastcgi_params`
(without it Laravel sees no `REQUEST_URI`, routes everything to `/`, and returns `200`
for every endpoint). TLS certificates are yours to obtain, e.g. with
`sudo certbot --nginx -d api.example.com`.

### 3. Create the runner → VPS SSH key (once)

This is a **second, separate key**: step 1's key lets the VPS pull from GitHub; this one
lets the GitHub Actions runner log into the VPS. Generate it on a trusted machine, not on
the runner:

```bash
ssh-keygen -t ed25519 -f ./gh-deploy -C "github-actions@myproject" -N ""

# Authorize the public half on the VPS
ssh-copy-id -i ./gh-deploy.pub deploy@api.example.com

# Pin the VPS host key — pass the same port the deploy will use
ssh-keyscan -p 22 api.example.com
```

Keep `gh-deploy` (the private half, including the `-----BEGIN`/`-----END` lines) and the
`ssh-keyscan` output for the next step.

### 4. Configure GitHub secrets and variables

The job runs in the `production` [GitHub Environment](https://docs.github.com/en/actions/deployment/targeting-different-environments/using-environments-for-deployment),
so you can attach required reviewers or a wait timer to it. Create it under
**Settings → Environments → New environment → `production`**, then add the values below
either at repository level (**Settings → Secrets and variables → Actions**) or scoped to
that environment.

| Name | Kind | Required | Example | Notes |
|---|---|---|---|---|
| `DEPLOY_ENABLED` | **variable** | yes | `true` | Master switch. Lives on the **Variables** tab, *not* Secrets — a secret of this name does nothing and the job silently skips. Not defaulted on, so merging the workflow changes nothing until you set it. |
| `DEPLOY_HOST` | secret | yes | `api.example.com` | Must be spelled exactly as it was in the `ssh-keyscan` command — an entry for the IP does not match a hostname. |
| `DEPLOY_USER` | secret | yes | `deploy` | The account whose `authorized_keys` you appended to in step 3. |
| `DEPLOY_PORT` | secret | no | `2222` | Defaults to `22`. |
| `DEPLOY_PATH` | secret | yes | `/var/www/my-project` | Either the repository root or the `apps/api` directory inside it — the workflow detects which and `cd`s accordingly. |
| `DEPLOY_SSH_KEY` | secret | yes | contents of `gh-deploy` | The **private** half from step 3, whole file including header/footer lines. |
| `DEPLOY_KNOWN_HOSTS` | secret | recommended | output of `ssh-keyscan -p <port> <host>` | Pins the host key. Without it the job falls back to `ssh-keyscan` on the runner, which trusts whatever answers first — it warns and continues. |

Delete the local `gh-deploy` private key once it's pasted into the secret.

### 5. First deploy

`workflow_run` triggers only fire for the copy of a workflow that exists on the **default
branch**, so nothing runs automatically until `backend-deploy.yml` is merged into `main`.
For a first, supervised run use **Actions → Deploy API → Run workflow** — that path is
allowed by `workflow_dispatch` and deploys whichever ref you pick.

From then on, every push to `main` whose CI run goes green deploys automatically. Deploys
never run concurrently and a running deploy is never cancelled — interrupting migrations
is worse than queuing.

### What each run does on the server

In order, from the workflow's remote script:

1. `git fetch --prune origin` then `git reset --hard <sha>` — the exact commit CI
   validated, not whatever `main` happens to be by then.
2. `php artisan down --retry=15` — maintenance mode, armed with a trap that lifts it again
   even if a later step fails, so a broken deploy never leaves the site down.
3. `composer install --no-dev --optimize-autoloader`
4. `php artisan migrate --force`
5. `php artisan config:cache`, `route:cache`, `view:cache` — rebuilt, not just cleared.
6. `php artisan queue:restart` — running workers exit after their current job and pick up
   the new code.
7. `php artisan up` (via the trap).

Because step 5 caches config, **an `.env` edit on the server is not live until the config
cache is rebuilt** — either re-run the deploy or run `php artisan config:cache` by hand.

### Backend deployment checklist

```
[ ] Server has a clone of the repository at DEPLOY_PATH, pullable non-interactively
[ ] apps/api/.env exists on the server with APP_KEY set (APP_ENV=production, APP_DEBUG=false)
[ ] storage/ and bootstrap/cache/ writable by the deploy user and php-fpm
[ ] deploy/nginx/api.conf installed, placeholders filled, `nginx -t` passes
[ ] Runner → VPS key added to the deploy user's authorized_keys
[ ] DEPLOY_ENABLED set to "true" on the Variables tab (not Secrets)
[ ] DEPLOY_HOST / USER / PATH / SSH_KEY secrets set; KNOWN_HOSTS generated with the same port
[ ] production environment exists (required for the job to resolve its secrets)
[ ] backend-deploy.yml merged to main, first run triggered manually
```

### Deploy troubleshooting

- **The job is grey / "skipped".** `DEPLOY_ENABLED` isn't `true`, was added as a secret
  instead of a variable, or the CI run it was waiting on didn't conclude `success`.
- **`known_hosts has no entry matching the host and port…`** The workflow checks its own
  pin before connecting, because the raw failure is the five unhelpful words "Host key
  verification failed". Regenerate on a trusted machine with the **same port**
  (`ssh-keyscan -p <DEPLOY_PORT> <DEPLOY_HOST>`) and the **same host spelling** as the
  `DEPLOY_HOST` secret. OpenSSH stores a non-default port as `[host]:port`, so a portless
  keyscan will not match a connection to `2222`.
- **`Permission denied (publickey)`.** The public half of `DEPLOY_SSH_KEY` isn't in the
  deploy user's `~/.ssh/authorized_keys`, or the secret is missing its BEGIN/END lines.
- **`No artisan found at …`.** `DEPLOY_PATH` points somewhere that is neither the
  repository root nor `apps/api`.
- **`git fetch` fails on the server.** The deploy key from step 1 was removed, or the
  clone uses an HTTPS remote that now needs credentials. Fix with
  `git remote set-url origin git@github.com:<username>/<repository>.git`.
- **Deploy is green but the API serves old behaviour.** Config/route caches are rebuilt
  from the files on disk — check you edited `.env` on the server and not only locally.
- **Migrations failed midway.** The trap has already lifted maintenance mode, so the site
  is up on a half-applied schema. Fix forward with a new commit; don't roll back by hand
  while traffic is flowing.

---

## Deploy the Next.js frontend to Vercel

This repository is a monorepo. You import the **whole repository** into Vercel but configure it to deploy only `apps/web`. The Laravel API continues to run on its own server — deployed by [`backend-deploy.yml`](#deploy-the-laravel-api-to-a-vps), not by Vercel.

```
GitHub repository
       │
       ├── apps/api  → VPS / Laravel
       │
       └── apps/web  → Vercel / Next.js
```

The repository stays a single Git repository. Do **not** run `git init` inside `apps/web`:

```bash
# ✗ Wrong — creates a nested repository that Vercel cannot import correctly
cd apps/web
git init
```

### 1. Import the project into Vercel

1. Go to [vercel.com](https://vercel.com) and choose **Add New → Project**.
2. Import the GitHub repository you created from this starter.
3. Configure the project settings:

| Setting | Value |
|---|---|
| **Framework Preset** | `Next.js` |
| **Root Directory** | `apps/web` |
| **Node.js Version** | `22.x` — CI builds on Node 22, so matching it here means a green CI build compiles on Vercel too (20.x is the supported floor) |
| **Install Command** | *(leave as automatic)* |
| **Build Command** | `cd ../.. && npx turbo run build --filter=web` |
| **Output Directory** | *(framework default / `.next`)* |

The Root Directory must be exactly:

```
apps/web
```

Not `web`, not `/apps/web` — Vercel expects the path relative to the repository root, without a leading slash.

### 2. Why the custom build command

The root of the repository holds the npm workspace and Turborepo configuration, so the build must be run from there. The recommended build command navigates up from `apps/web` before invoking Turbo:

```bash
cd ../.. && npx turbo run build --filter=web
```

`apps/web/package.json` still defines `"build": "next build"` — Turbo calls it through the workspace. You do not need to change any package scripts.

### 3. Vercel environment variables

Add these under **Vercel → Project → Settings → Environment Variables**:

```env
NEXT_PUBLIC_APP_NAME=My App
NEXT_PUBLIC_API_BASE_URL=https://api.example.com/api/v1
NEXT_PUBLIC_AUTH_MODE=bearer
API_PROXY_TARGET=https://api.example.com
```

| Variable | Notes |
|---|---|
| `NEXT_PUBLIC_APP_NAME` | Browser tab title and UI branding. |
| `NEXT_PUBLIC_API_BASE_URL` | Full Laravel API URL — **must include `/api/v1`**. Example: `https://api.example.com/api/v1`. |
| `NEXT_PUBLIC_AUTH_MODE` | `bearer` for the default Sanctum token flow. |
| `API_PROXY_TARGET` | Laravel API origin — **no path, no `/api/v1`**. Example: `https://api.example.com`. |

> **Warning:** Do not leave `NEXT_PUBLIC_API_BASE_URL=http://localhost:8000/api/v1` in production. Inside a Vercel deployment, `localhost` refers to the Vercel runtime itself — not your VPS. The API will be unreachable and every authenticated request will fail.

### 4. Configure Laravel for the Vercel frontend

On your VPS, update `apps/api/.env` to reflect the Vercel URL (Vercel provides a `*.vercel.app` URL immediately after import):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
FRONTEND_URL=https://my-app.vercel.app
CORS_ALLOWED_ORIGINS=https://my-app.vercel.app
CORS_SUPPORTS_CREDENTIALS=false
```

This example assumes the default bearer-token auth mode. Then refresh Laravel's configuration cache:

```bash
cd apps/api
php artisan optimize:clear
php artisan config:cache
```

### 5. After adding a custom domain

Once you assign a production domain to the Vercel project (e.g. `https://app.example.com`), update the Laravel `.env`:

```env
FRONTEND_URL=https://app.example.com
CORS_ALLOWED_ORIGINS=https://app.example.com,https://my-app.vercel.app
```

Keeping the `*.vercel.app` origin in `CORS_ALLOWED_ORIGINS` is optional — it's useful if you want preview deployments to keep working against the production API.

Run `php artisan config:cache` again after every `.env` change.

### 6. Test the build locally before deploying

From the repository root:

```bash
npm install
npx turbo run build --filter=web
```

The output should show the `web` workspace running `next build`. A green local build means Vercel's build will succeed too.

### Deployment checklist

```
[ ] GitHub repository pushed and connected to Vercel
[ ] Root Directory = apps/web  (not "web" or "/apps/web")
[ ] Framework = Next.js
[ ] Node.js 22.x on Vercel (matches CI; 20 is the floor)
[ ] Build command uses Turbo  (cd ../.. && npx turbo run build --filter=web)
[ ] NEXT_PUBLIC_API_BASE_URL points to production Laravel API (includes /api/v1)
[ ] API_PROXY_TARGET points to API origin (no path)
[ ] NEXT_PUBLIC_API_BASE_URL does NOT contain localhost
[ ] Laravel CORS_ALLOWED_ORIGINS includes the Vercel/frontend domain
[ ] Laravel config cache refreshed after .env changes
[ ] Local build passes (npx turbo run build --filter=web)
```

---

## Continuous integration

`.github/workflows/ci.yml` runs on every pull request and every push to `main`. A newer
push to the same branch cancels the in-flight run. Three jobs, all required to be green
before `backend-deploy.yml` will deploy anything:

| Job | Runs in | Steps |
|---|---|---|
| **API — Laravel tests** | `apps/api` | `composer install` → `php artisan test` → `composer check-platform-reqs` → `composer audit` |
| **API — Pint** | `apps/api` | `./vendor/bin/pint --test` — reports formatting drift, never rewrites files |
| **Web — types, build, lint** | `apps/web` | `npm ci` (from the repo root) → `npx tsc --noEmit` → `npm run lint` → `npm run build` → `npm audit --audit-level=critical` |

Reproduce all of it locally before pushing:

```bash
npm install
npm run -w apps/web typecheck
npm run -w apps/web lint
npm run -w apps/web build

cd apps/api
composer install
php artisan test
./vendor/bin/pint --test        # add --dirty to fix only what you changed, without --test to write
```

Why the workflow looks the way it does:

- **PHP 8.2, not the newest.** 8.2 is the floor declared in `composer.json`, and
  `config.platform.php` pins lockfile resolution to it. `composer check-platform-reqs`
  is what stops a `composer update` run on a newer machine from silently pulling packages
  that cannot install on the floor.
- **Node 22.** It matches the Vercel production runtime, so a green build here means the
  Vercel build compiles too. `npm ci` runs from the repository root because npm workspaces
  keeps a single lockfile there — running it inside `apps/web` finds no lockfile.
- **No `.env` is written for the tests.** `phpunit.xml` already pins the test environment
  (SQLite in-memory, array cache/session); `APP_KEY` is generated as a throwaway env var
  per run so no key lives in the repository.
- **Audit strictness differs on purpose.** `composer audit` fails on any known advisory;
  `npm audit` only on **critical**, so that high advisories gated behind a semver-major
  upgrade don't leave CI permanently red and universally ignored.

---

## Non-interactive install

```bash
# Local
node scripts/setup.mjs \
  --non-interactive \
  --project-name="My App" \
  --mode=local \
  --auth-mode=bearer \
  --port=8000 \
  --seed

# Remote
node scripts/setup.mjs \
  --non-interactive \
  --project-name="My App" \
  --mode=remote \
  --api-url=https://api.example.com \
  --frontend-origin=https://app.example.com
```

---

## How project naming works

When you clone the repo as `my-project` and run setup, the setup wizard:

1. Prompts "Project name" — defaults to the directory name (e.g. `My Project` from `my-project`).
2. Writes `APP_NAME=My Project` into `apps/api/.env` — controls the Laravel app name, mail sender name, and log prefix.
3. Writes `NEXT_PUBLIC_APP_NAME=My Project` into `apps/web/.env.local` — used for the browser tab title and any UI branding.

To rename the project later without re-running full setup:

```bash
npm run setup:env   # reruns only the env-writing step
```

Or edit the two env files directly:

```bash
# apps/api/.env
APP_NAME=New Name

# apps/web/.env.local
NEXT_PUBLIC_APP_NAME=New Name
```

---

## Setup modes

### Local mode (default)

```bash
npm run setup           # pick "Local machine"
```

Setup writes `apps/api/.env` and `apps/web/.env.local`, runs `composer install`, creates `apps/api/database/database.sqlite`, runs `php artisan key:generate` and `php artisan migrate`.

### Remote mode

```bash
npm run setup           # pick "Remote backend"
```

Setup writes env files and installs Node dependencies. Laravel bootstrap is skipped.

---

## How the auth flow works

- **Default: Sanctum bearer token.**
  - `POST /api/v1/login` returns `{ user, token, expires_at }`.
  - The web app stores `{ token, user, expiresAt }` in `localStorage`.
  - Every request sends `Authorization: Bearer <token>`.
  - `POST /api/v1/logout` revokes the token.
  - On `401`, the client dispatches `auth:expired`, clears storage, and sends the user to `/login`.
- **Alternative: Sanctum SPA cookie.**
  - Set `NEXT_PUBLIC_AUTH_MODE=cookie`.
  - Set `CORS_SUPPORTS_CREDENTIALS=true` and include your web origin in `SANCTUM_STATEFUL_DOMAINS`.
  - The `cookie` adapter primes `/sanctum/csrf-cookie` before each mutating call.
- **Frontend-only dev: `mock`.**
  - Set `NEXT_PUBLIC_AUTH_MODE=mock`.
  - No HTTP calls are made. Login/register instantly "succeed" as a fixture user.
  - Useful when the Laravel API is intentionally offline and you only want to iterate on UI.

Adapters live in `apps/web/lib/auth/adapters/`. Adding a new auth method = implement one more adapter.

### Password reset

Shipped and enabled by default:

- `POST /api/v1/forgot-password` → emails a reset link to the user.
- `POST /api/v1/reset-password` → consumes a valid token to set a new password.
- The reset URL in the email points at `${FRONTEND_URL}/reset-password?token=…&email=…` (configured in `App\Providers\AppServiceProvider::boot`).
- In local dev the default mail driver is `log`, so the link appears in `apps/api/storage/logs/laravel.log`.
- Frontend pages: `/forgot-password`, `/reset-password`.

### Email verification

The `User` model implements `MustVerifyEmail`. After register, Laravel sends a signed verification link (TTL controlled by `VERIFICATION_LINK_TTL_MINUTES`, default 60).

- The link in the email points at the **backend** route `/api/v1/email/verify/{id}/{hash}`. The `signed` middleware verifies the URL hasn't been tampered with — no auth header required.
- On success the backend redirects to `${FRONTEND_URL}/verify-email?status=verified`. On a wrong hash → `?status=invalid`. On a tampered signature → 403.
- `/api/v1/email/verification-notification` (auth required, throttled) lets a signed-in user resend the email.
- Changing your email via `PATCH /api/v1/me` clears `email_verified_at` and triggers a new verification email automatically.
- The starter does **not** apply the `verified` middleware to any route — it just makes verification status available. Add `->middleware('verified')` to any route you want to gate.
- Frontend: `/verify-email` page (handles the redirect-back), plus a "Verify your email" card in `/settings` with a "Resend" button shown only when the user is unverified.

---

## API routing / base URL

- `NEXT_PUBLIC_API_BASE_URL` is the **fully-prefixed** base (e.g. `http://localhost:8000/api/v1`). Client code calls `/login`, `/me`, `/logout` — the axios instance prepends it.
- `apps/web/app/api/[...path]/route.ts` is a same-origin proxy handler for SSR or cross-origin-sensitive setups. It reads `API_PROXY_TARGET` (or derives it from `NEXT_PUBLIC_API_BASE_URL`).

Endpoints (all JSON):

| Method | Path | Auth | Notes |
|---|---|---|---|
| GET  | `/api/ping` | public | Health. |
| POST | `/api/v1/register` | public | Throttled. |
| POST | `/api/v1/login` | public | Throttled. |
| POST | `/api/v1/forgot-password` | public | Throttled. |
| POST | `/api/v1/reset-password` | public | Throttled. |
| GET  | `/api/v1/me` | bearer | Current user. |
| PATCH | `/api/v1/me` | bearer | Update name/email. |
| PATCH | `/api/v1/me/password` | bearer | Change password (requires current). Revokes other tokens. |
| POST | `/api/v1/email/verification-notification` | bearer | Re-send the verify-your-email link. Throttled. |
| GET  | `/api/v1/email/verify/{id}/{hash}` | signed URL | Email verification target. Marks user verified, redirects to `${FRONTEND_URL}/verify-email?status=verified`. |
| POST | `/api/v1/logout` | bearer | Revokes current token. |
| GET  | `/api/v1/notes` | bearer | Example resource — list. |
| POST | `/api/v1/notes` | bearer | Example resource — create. |
| DELETE | `/api/v1/notes/{id}` | bearer | Example resource — delete. |

Public auth endpoints are rate-limited to `AUTH_THROTTLE_PER_MINUTE` requests per minute (default `10`), keyed by authenticated user or IP. Exceed the limit and the API responds `429`.

---

## Scripts

From the repo root:

```bash
npm run dev         # Turbo: web + api in parallel
npm run dev:web     # just web
npm run dev:api     # just api (php artisan serve)
npm run build       # Turbo build
npm run lint        # Turbo lint
npm run typecheck   # Turbo typecheck (web only)
npm run test        # Turbo test (runs api tests)
npm run test:api    # apps/api php artisan test
npm run setup       # interactive setup
npm run setup:env   # rewrite env files only
npm run setup:check # preflight + ping smoke test
```

---

## Re-running setup safely

`npm run setup` is idempotent. Existing `.env` values are preserved; only keys you're actively changing get rewritten. A `.bak` copy is saved next to each env file before overwriting.

If you need to start over:

```bash
rm apps/api/.env apps/web/.env.local
npm run setup
```

---

## Environment reference

### `apps/api/.env`
See `apps/api/.env.example`. Key values the setup script manages:

- `APP_NAME` — project name used in mail sender, log prefix, and session cookie name.
- `APP_URL` — full URL the API is served at.
- `CORS_ALLOWED_ORIGINS` — comma-separated origins the browser may call from.
- `CORS_SUPPORTS_CREDENTIALS` — `true` only in SPA-cookie mode.
- `SANCTUM_STATEFUL_DOMAINS` — only matters in SPA-cookie mode.
- `SANCTUM_TOKEN_EXPIRATION_HOURS` — bearer token lifetime (default 8).

### `apps/web/.env.local`
See `apps/web/.env.local.example`.

- `NEXT_PUBLIC_APP_NAME` — shown in the browser tab and any UI branding spots.
- `NEXT_PUBLIC_API_BASE_URL` — includes `/api/v1`.
- `NEXT_PUBLIC_AUTH_MODE` — `bearer` (default) or `cookie`.
- `API_PROXY_TARGET` — server-side proxy target (origin only, no path).

---

## Troubleshooting

- **CORS errors in the browser.** Make sure your web origin is listed in `CORS_ALLOWED_ORIGINS` on the API. Re-run `npm run setup` and restart `php artisan serve`.
- **`401` on `/me` right after login.** You're probably in SPA-cookie mode without `CORS_SUPPORTS_CREDENTIALS=true` or with a missing `SANCTUM_STATEFUL_DOMAINS` entry. Or, in bearer mode, localStorage was cleared. Switch back to bearer (the default) with `npm run setup:env`.
- **`/dashboard` redirects to `/login`.** Middleware relies on the `auth_hint` cookie set at login time. If you cleared cookies, sign in again.
- **Herd link fails.** You're on Linux/Windows — Herd integration is macOS only. Answer "no" to the Herd prompt and use `php artisan serve`.

---

## Example resource

A small **Notes** demo (`/notes` in the web app, `/api/v1/notes` on the API) ships as the end-to-end CRUD template. It's deliberately domain-neutral — copy it when building a real resource, or delete it when you don't need it. Every Notes file has a header comment and `STRUCTURE.md` lists the full removal checklist.

---

See `STRUCTURE.md` for the layout map and where to put new code.
