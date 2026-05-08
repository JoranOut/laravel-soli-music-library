# Laravel Soli Music Library — Implementation Plan

A separate Laravel application at `muzitheek.soli.nl` for secure music sheet
sharing. Replaces `wp-soli-music-library-plugin`.

## Stack

- Laravel 12 + React 19 + Inertia v2 + Tailwind v4 + shadcn/ui + Pest v4
- Laravel Sail (local dev, port **8001**)
- Own MySQL database
- Hetzner VPS now, shared hosting later — no external services assumed

## Architecture overview

```
admin.soli.nl (IdP)                  muzitheek.soli.nl
┌────────────────────┐               ┌─────────────────────┐
│ Passport (OIDC)    │◀── login ─────│ Socialite            │
│ /api/v1/orchestras │◀── API ───────│ catalog sync job     │
│ /api/v1/instruments│               │                     │
│ webhook on change  │──── push ────▶│ POST /webhooks/...  │
└────────────────────┘               └─────────────────────┘
```

All cross-app communication over public HTTPS URLs configured via `.env`.
No shared DB, no shared filesystem, no colocation assumptions in code.

---

## Implementation phases

Each phase results in something runnable and testable. Don't start the
next phase until the current one works end-to-end.

---

### Phase 1 — Scaffold + local dev

**Goal:** Empty Laravel app running on Sail at `localhost:8001` with a
working home page.

**Steps:**

1. Create fresh Laravel 12 app in this directory.
2. Configure Sail (`compose.yaml`) with MySQL on port 33061 (avoid
   collision with admin's 3306/33060), app on port **8001**.
3. Install Inertia v2 + React 19 + Tailwind v4 + shadcn/ui (match
   admin's setup).
4. Create a simple welcome page served via Inertia — confirms the full
   stack works.
5. `sail up -d`, open `localhost:8001`, see the page.

**Testable:** `localhost:8001` renders the welcome page.

---

### Phase 2 — Auth via admin (OIDC)

**Goal:** "Login with Soli" button → redirects to admin → comes back
logged in.

**Admin-side prep (in `laravel-soli-administration`):**

1. Register a Passport client for muziek
   (`php artisan passport:client`). Note the client ID and secret.
2. Add a Passport token enhancer that includes custom claims in the
   access/ID token:
   ```json
   {
     "roles": ["member"],
     "assignments": [
       { "onderdeel_id": 3, "instrument_id": 12 }
     ]
   }
   ```
3. Make sure Passport's `/oauth/authorize` and `/oauth/token` endpoints
   work (they should already).

**Muziek-side:**

1. Install Laravel Socialite + a generic OAuth2 provider.
2. Configure in `config/services.php`, all values from `.env`:
   ```php
   'soli_admin' => [
       'base_url'      => env('ADMIN_BASE_URL', 'http://localhost'),
       'client_id'     => env('ADMIN_OAUTH_CLIENT_ID'),
       'client_secret' => env('ADMIN_OAUTH_CLIENT_SECRET'),
       'redirect'      => env('ADMIN_OAUTH_REDIRECT', '/auth/callback'),
   ],
   ```
3. Create `AuthController` with `redirect()` and `callback()` methods.
4. On callback: create/update a local `users` row (`oidc_sub` as unique
   key), stash `assignments` + `roles` in the session, log in.
5. Simple "logged in as ..." dashboard page.

**Testable locally:** Both Sail containers running (admin on 80/8000,
muziek on 8001). Click login on muziek → redirected to admin login →
back to muziek logged in. Session contains assignments.

---

### Phase 3 — Domain models + migrations

**Goal:** Muziek's own database schema in place with seed data.

**Tables (all muziek-owned):**

| Table | Purpose |
|-------|---------|
| `users` | id, oidc_sub (unique), name, email, roles (json), last_synced_at |
| `orchestras` | id, external_id (admin's onderdeel_id), name, sort_order — cached |
| `instruments` | id, external_id, name, family, sort_order — cached |
| `pieces` | id, title, composer, arranger, publisher, difficulty, notes, timestamps |
| `parts` | id, piece_id (FK), instrument_id (FK), is_conductor, file_path, original_filename, timestamps |
| `piece_orchestra` | piece_id, orchestra_id — pivot |
| `download_logs` | id, user_id, part_id, downloaded_at, ip |

**Steps:**

1. Create migrations in order (no FK issues).
2. Create Eloquent models with relationships.
3. Create a seeder with realistic sample data (a few pieces, parts
   across orchestras/instruments).
4. `sail artisan migrate:fresh --seed` — verify schema.

**Testable:** `sail artisan migrate:fresh --seed` runs clean. Tinker
confirms relationships work.

---

### Phase 4 — Catalog sync from admin

**Goal:** Orchestras and instruments in muziek's DB are pulled from
admin's API automatically.

**Admin-side (in `laravel-soli-administration`):**

1. Create `GET /api/v1/orchestras` — returns id, name, sort_order.
   Protected with Passport client-credentials scope.
2. Create `GET /api/v1/instruments` — returns id, name, family,
   sort_order. Same scope.

**Muziek-side:**

1. Create `AdminApiService` — wraps HTTP client, uses
   client-credentials token from Passport.
2. Create `SyncCatalogJob` — pulls orchestras + instruments, upserts
   into local tables by `external_id`.
3. Register in scheduler (twice daily).
4. Artisan command `music:sync-catalog` for manual trigger.

**Testable:** `sail artisan music:sync-catalog` populates orchestras and
instruments from admin. Re-running is idempotent.

---

### Phase 5 — Piece + part management (admin CRUD)

**Goal:** Editors can create pieces, assign them to orchestras, and
upload PDF parts.

**Steps:**

1. Configure private `sheets` storage disk (`storage/app/sheets/`).
   Local filesystem only — no external storage. Must work on shared
   hosting.
2. Create `PieceController` — index, create, store, edit, update,
   destroy. Inertia pages.
3. Create `PartController` — upload + delete. Uploads are
   **synchronous**: validate PDF, store on `sheets` disk, create
   part row, done. Typical files are 2–10 page PDFs (~1–3 MB each).
   Bulk upload (up to ~30 files) is handled as multiple sequential
   stores in one request — still fast enough, no queue needed.
4. Piece form: title, composer, arranger, publisher, difficulty, notes,
   orchestra checkboxes.
5. Part upload: select instrument, toggle `is_conductor`, file input.
6. Basic role check: only users with `editor` role (from session) can
   access CRUD.

**Testable:** Log in as editor → create a piece → assign to orchestra →
upload a PDF part → see it listed. File exists on the `sheets` disk.

---

### Phase 6 — Access control + file serving

**Goal:** Members can browse pieces and download only the parts they're
allowed to see.

**Steps:**

1. Create `PartPolicy` with the strict-matching rule:
   - Editor → all parts.
   - Contributor → conductor parts.
   - Member → only parts for instruments they play in the piece's
     orchestra (from session assignments).
2. Create download route with **signed temporary URLs** (expire after
   24 hours):
   ```php
   // Route definition
   Route::get('/parts/{part}/download', [PartController::class, 'download'])
       ->name('parts.download')
       ->middleware(['auth', 'signed']);
   ```
   The `signed` middleware rejects expired or tampered URLs with 403
   automatically. No external services needed — uses Laravel's
   `APP_KEY` for signing.
3. Frontend generates signed links per part:
   ```php
   URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id])
   ```
   These links work for 24h, then stop. Users must revisit the page
   to get fresh links. Prevents link-sharing outside the app.
4. Download controller still checks the policy (signed URL proves
   freshness, policy proves authorization):
   ```php
   public function download(Part $part) {
       $this->authorize('view', $part);
       DownloadLog::record(auth()->user(), $part);
       return Storage::disk('sheets')->download($part->file_path, $part->original_filename);
   }
   ```
5. Frontend piece index page: grouped by orchestra, filtered by the
   user's visible parts. Each download link is a signed URL.
6. Test: log in as member with assignment (Harmonie, Flute) → see Flute
   parts for Harmonie pieces, not Clarinet parts.

**Testable:** Different users see different parts. Unauthorized download
returns 403. Authorized download serves the PDF. Expired signed URL
returns 403.

---

### Phase 7 — Tests

**Goal:** Pest test suite covering auth, access control, CRUD, file
serving.

**Test cases:**

- Guest → redirected to login (302).
- Member sees only their orchestra+instrument parts.
- Strict matching: member in Orchestra A with Flute does NOT see Flute
  parts for Orchestra B.
- Contributor sees conductor sheets.
- Editor sees everything + can CRUD.
- Download of unauthorized part → 403.
- Download of authorized part → 200 + correct PDF.
- Catalog sync job is idempotent.

---

### Phase 8 — Deploy pipeline + production

**Goal:** `muzitheek.soli.nl` live on the Hetzner VPS.

**VPS prep:**

1. `mkdir -p /var/www/muzitheek.soli.nl/{shared/storage/app/sheets,current}`
2. Write `shared/.env` (production credentials)
3. MySQL: `CREATE DATABASE muziek` + dedicated user
4. nginx vhost for `muzitheek.soli.nl` → `current/public`
5. TLS: `certbot --nginx -d muzitheek.soli.nl`
6. php-fpm pool (optional, recommended)
7. systemd queue worker + cron scheduler
8. Register production OAuth client in admin Passport

**GitHub:**

1. Create repo (`JoranOut/laravel-soli-music-library`)
2. Copy + adapt `deploy.yml` from admin (change paths, health-check URL)
3. Add SSH secrets
4. `gh workflow run deploy.yml --ref main`

**Testable:** `https://muzitheek.soli.nl` loads, login works via
`admin.soli.nl`, pieces display correctly.

---

### Phase 9 — Data migration from WordPress

**Goal:** All existing pieces, parts, and files moved from the WP plugin
to muziek.

**Steps:**

1. Create artisan command `music:import-from-wp`.
2. Temporary read-only DB connection to the WP database.
3. Map WP orchestra/instrument IDs → muziek's `external_id` (which are
   admin's onderdeel/instrument IDs).
4. Copy files from WP uploads dir into the `sheets` disk.
5. Verify: count pieces, count parts, spot-check downloads.
6. Remove the temporary WP connection.

Legacy `soli_user_assignments` in the WP DB is NOT imported — assignments
now live in admin's `RelatieInstrument` and travel in the OIDC token.

---

### Phase 10 — Webhook + polish (nice-to-have for v1)

**Goal:** Real-time catalog updates + admin assignment change handling.

1. Admin fires webhook on `RelatieInstrument` / `Onderdeel` /
   `Instrument` changes.
2. Muziek receives webhook → refreshes catalog cache / invalidates
   affected user sessions.
3. Download audit log UI for editors.
4. Search + filter on piece index.

---

## Hosting compatibility

The app must run on **shared hosting** (PHP + MySQL, no root access, no
Docker, no Redis, no queue daemon). This constrains some choices:

| Concern | Approach |
|---------|----------|
| File storage | Local disk only (`storage/app/sheets/`). No S3/external. |
| File serving | Signed temporary URLs (24h expiry) via `APP_KEY`. No `.htaccess` tricks. |
| Queue | `sync` driver default. `database` driver available if needed later. |
| Cache | `file` or `database` driver. No Redis. |
| Sessions | `file` or `database` driver. |
| Scheduler | Single cron entry: `* * * * * php artisan schedule:run` — also processes queued jobs (see below) |
| Deploy | rsync/SSH from GitHub Actions (same as admin). |

**Portability:** Moving servers is an `.env` change:

| What | Env vars |
|------|----------|
| App URL | `APP_URL` |
| Database | `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Admin connection | `ADMIN_BASE_URL`, `ADMIN_API_URL`, `ADMIN_OAUTH_CLIENT_ID`, `ADMIN_OAUTH_CLIENT_SECRET` |

**Codebase rules:**
- No hard-coded IPs or paths
- No cross-app file imports or symlinks
- No assumption that admin is on the same box
- No dependency on Redis, queue daemons, or anything beyond PHP + MySQL
- Muziek works during short admin outages (cached catalog + session
  claims)

## Open questions

- Webhook from admin on assignment changes — v1 can skip; "next login
  reflects changes" is fine for this domain.
- Download audit log UI — include from v1 or add later?
- Muziek never does orchestra/instrument CRUD — that's admin's domain.
  Muziek only reads the catalog.
