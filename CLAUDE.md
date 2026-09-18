# CLAUDE.md — Agent guidance for this repo

You (the agent) are working on a **reusable Laravel + Next.js monorepo starter**. The shipped baseline must always keep working: public `/` → `/login` or `/register` → authenticated `/dashboard` talking to a live Laravel API via bearer token.

## Read first

- `README.md` — quickstart, setup modes, auth modes, troubleshooting.
- `STRUCTURE.md` — directory layout and conventions.
- `scripts/setup.mjs` — the one-shot installer users invoke via `npm run setup`.
- `apps/api/routes/api.php` — every HTTP contract lives here.
- `apps/web/lib/auth/` — auth adapters; understand this before touching login/logout.
- `apps/web/components/auth-provider.tsx` — one source of truth for client-side auth state.

## Ground rules

- **No new runtime packages** unless truly necessary. Prefer existing deps (axios, zod, react-hook-form, SWR, sonner, shadcn/ui).
- **No new dev-only packages** inside `scripts/`. The setup console uses Node stdlib only (`readline/promises`, `fs`, `child_process`, etc.).
- **No secret commits.** Templates live in `.env.example` / `.env.local.example`. Real `.env` files are gitignored.
- **API is versioned.** New endpoints go under `/api/v1`. Only truly cross-version endpoints (like `/api/ping`) live outside the `v1` prefix.
- **One HTTP client on the web side.** Never import `axios` directly in pages or components — go through `@/lib/api`. The one exception is the cookie adapter calling `/sanctum/csrf-cookie`.
- **One auth provider.** Don't add another context. Extend the adapter interface (`apps/web/lib/auth/adapter.ts`) and add an entry in `apps/web/lib/auth/index.ts`.
- **Route group discipline.** Public routes go in `app/(public)/`. Authenticated routes go in `app/(app)/`. Add new protected prefixes to `middleware.ts`.

## When adding features

1. If it requires an env key, add it to `.env.example` (or `.env.local.example`) first, with a comment.
2. If it changes the auth contract, update **both** `apps/web/lib/auth/adapters/bearer.ts` and `apps/web/lib/auth/adapters/cookie.ts` — and the mock if relevant.
3. If it's a new API endpoint, add a feature test under `apps/api/tests/Feature/`.
4. If it touches the setup flow, make it idempotent. `npm run setup` must be safe to re-run.
5. If it prompts something, also support a non-interactive flag (`--my-option=...` + `--non-interactive`).

## CI and deploy

`.github/workflows/ci.yml` — pull requests and pushes to `main`. Three jobs:

| Job | Runs in | Steps |
|---|---|---|
| `api` | `apps/api` | `composer install` → `php artisan test` → `composer check-platform-reqs` → `composer audit` |
| `style` | `apps/api` | `./vendor/bin/pint --test` |
| `web` | `apps/web` | root `npm ci` → `npx tsc --noEmit` → `npm run lint` → `npm run build` → `npm audit --audit-level=critical` |

- There is **no** setup-script smoke job in CI. `npm run setup` is exercised by hand (see the smoke test below), so setup regressions do not show up as a red build — be extra careful when touching `scripts/`.
- PHP 8.2 is the floor. Write code that works there; `composer.json` pins `config.platform.php` to 8.2 and `check-platform-reqs` enforces it.
- The Laravel test runner is PHPUnit 11; phpunit.xml uses `DB_CONNECTION=sqlite` in-memory. CI writes no `.env` — `APP_KEY` is a throwaway env var per run.
- Web builds on Node 22 (matches the Vercel runtime). `npm ci` must run from the repo root — npm workspaces keeps the only lockfile there.
- Run `./vendor/bin/pint` before pushing API changes, or the `style` job fails on formatting alone.

`.github/workflows/backend-deploy.yml` — deploys `apps/api` to a VPS over SSH after CI goes green on `main`, gated behind the `DEPLOY_ENABLED` repository **variable** (not a secret). It `git reset --hard`s an existing clone on the server, then runs `composer install --no-dev`, `migrate --force`, the `config`/`route`/`view` caches, and `queue:restart`. It never writes `.env` and never provisions the box.

- Changing the remote deploy steps means updating **"What each run does on the server"** in `README.md` too — that list is the documented contract.
- New required secrets/variables go in the README's secrets table *and* the backend deployment checklist.
- `deploy/nginx/api.conf` is the reference vhost. The 308 redirect and the `include fastcgi_params;` line are load-bearing; both failure modes are documented in the file's comments. Its `fastcgi_pass` defaults to **php8.3-fpm.sock**, matching the `php8.3-*` packages the README installs — that is the server *runtime* and is deliberately not the same thing as the 8.2 language floor above. Don't "align" them: 8.2 is the oldest PHP the code must run on, 8.3 is what a current box actually runs. Change both the vhost socket and the README apt lines together, or new servers get a 502 on every request.
- **`npm run setup` is the documented server install path**, not just a laptop tool: the VPS guide runs it with `--mode=remote --api-url=<the box's own public URL>`. A mode is about *which API URL this checkout talks to*, never about where anything deploys — keep that distinction intact in both the script and the docs. If you change what a mode writes or bootstraps, update README §1b/1c and the "Setup modes — what each one actually does" table, which are written from verified behaviour: remote mode runs `key:generate`, `migrate` and `storage:link` (it skips only the SQLite file, the seed and the ping), and no mode writes `APP_ENV`, `APP_DEBUG` or `DB_*`.

## Things to avoid

- Don't quietly widen permissions in `CORS_ALLOWED_ORIGINS` or `SANCTUM_STATEFUL_DOMAINS` — those are security-sensitive.
- Don't reintroduce `react-hot-toast`. Sonner is the one toast library.
- Don't add Next.js `rewrites()`. The same-origin proxy at `app/api/[...path]/route.ts` is the server-side path.
- **Don't hardcode `connect-src` in `apps/web/next.config.ts`.** It is derived from `NEXT_PUBLIC_API_BASE_URL` on purpose: the client calls that origin directly, and a fixed `connect-src 'self'` makes the browser block every API call in the documented Vercel-plus-own-API deployment — `blocked:csp`, with empty backend logs. A relative base URL (same-origin proxy) yields `'self'` alone, which is correct. Adding another browser-reachable origin means adding it there too.
- Don't couple dashboard/auth code to domain-specific models (users is fine; any app-specific resource is not).
- Don't commit generated files from `bootstrap/cache/` or `storage/**/` — the nested `.gitignore` files there take care of that.
- **Don't add Blade views or a `resources/views` directory to `apps/api`.** The API renders no views — Next.js owns every pixel. `config/view.php` sets `'paths' => []` to say so, which is also what keeps `view:cache` and `optimize` working without a views directory to scan. Mail still renders: the password-reset and verification notifications use the framework's `mail::` templates, resolved through package hints rather than these paths. If a view is genuinely needed, add the directory *and* the path back together.

## Where to put new code

| Thing | Where |
|---|---|
| New public page | `apps/web/app/(public)/<slug>/page.tsx` |
| New authenticated page | `apps/web/app/(app)/<slug>/page.tsx` + update `PROTECTED_PREFIXES` and `config.matcher` in `middleware.ts` + add a `<Link>` in `app/(app)/layout.tsx` if it's a top-level destination |
| New API endpoint | `apps/api/routes/api.php` (inside `v1` prefix; add to `auth:sanctum` group if protected) |
| New controller | `apps/api/app/Http/Controllers/Api/V1/<Name>Controller.php` |
| New form request | `apps/api/app/Http/Requests/Api/V1/<Name>Request.php` |
| New auth method | `apps/web/lib/auth/adapters/<name>.ts` + wire in `lib/auth/index.ts` |
| New setup prompt | `scripts/setup.mjs` (prompt helper) + add the env key to `.env.example` |

## Copying the Notes example

`notes` is the canonical CRUD template in this repo. When building a new resource, copy that pattern end-to-end: migration with `foreignId('user_id')`, model with `$fillable` excluding `user_id`, controller that uses `$model->user()->associate($request->user())` to attach the owner, form request for validation, feature test covering 401 / index-scope / store / validation / delete-self / delete-other. On the frontend: a page in `(app)/<slug>/` using SWR for reads and `api` for writes, with optimistic deletes.

## Smoke test (for any PR you touch)

```bash
npm install
node scripts/setup.mjs --non-interactive --mode=local --auth-mode=bearer   # npm run setup would eat the flags
npm run -w apps/web lint && npm run -w apps/web typecheck && npm run -w apps/web build
cd apps/api && php artisan test
```

All must pass. CI enforces everything here except the setup-script line — that one is on you.
