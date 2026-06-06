# CLAUDE.md

Guidance for working in this repository.

## What this is

**File Bridge** (a.k.a. the "e-File system") — a PHP document-management / digital-archiving
application for **Leruma Enterprises** (Tanzania), built by **MoinfoTech**. Users log in,
organize documents into **folders → sub-folders → document types**, upload/synchronize files,
edit, search, summarize, and back them up.

Stored documents are called **"archives"** in the domain model (the `File` model maps to the
`archives` table, and the API exposes both `/files` and `/archives`).

There are **two front doors** to the same database:
1. A **server-rendered web UI** (PHP includes + jQuery/Bootstrap), session-authenticated.
2. A **REST API v1** under `api/v1/` for a mobile app, bearer-token authenticated.

## Stack & environment

- **PHP** with **mysqli** (procedural/OO mix). No framework, no Composer autoloader.
- **MySQL** database `lerumaen_filebridge` (schema dumps: `db.sql`, `filebridge.sql`).
- Front-end deps via **npm** (`node_modules/` is committed): jQuery, Bootstrap 4, SweetAlert2,
  select2, FontAwesome, bootstrap-table, Chart.js, pdfobject.
- **xcrud** (`xcrud/`) — a third-party PHP CRUD/grid library used for admin tables AND, importantly,
  as the underlying DB connection for `Utility::query()`.
- Apache with `mod_rewrite` (`.htaccess` files). No CI, no test runner — this is a cPanel-style
  shared-hosting deployment (`/home/lerumaen/public_html/`).

## How to run / deploy

There is no build step and no automated tests. To run locally you need PHP + MySQL.

**`config.php` auto-detects the environment** — no manual edits needed to switch between local and
production. Detection order: `APP_ENV` env var override (`local`/`production`) → presence of
`/home/lerumaen/public_html` (=> production) → `localhost`/`127.0.0.1` HTTP host (=> local) →
default local (safe for CLI). It then defines `APP_ENV` and the matching `DB_*`, `BASE_URL`, and
`FILES_PATH`. Locally `BASE_URL` is derived from the request host, so any port works.

Local DB: name `lerumaen_filebridges`, user `root`. Production DB: name `lerumaen_filebridge`,
user `lerumaen_muddy`.

**Secrets** (DB password, API `SYNC_PASSWORD`) are NOT in the codebase. `config.php` loads them
from a file outside the web root — `getenv('EFILE_SECRETS_FILE')`, else
`/home/lerumaen/efile_secrets.php` (prod), else `dirname(project)/efile_secrets.php` (local) — or
per-value env vars (`EFILE_DB_PASS`, `EFILE_DB_USER`, `EFILE_DB_NAME`, `EFILE_SYNC_PASSWORD`). See
`efile_secrets.example.php`. A missing DB password fails loudly (HTTP 500). **Deploying to a new
server requires creating that secrets file first.**

To run locally:
1. Import `db.sql` into a MySQL database named `lerumaen_filebridges`.
1b. Ensure the local secrets file exists at `dirname(project)/efile_secrets.php` (copy from the
    example) with the `local` block filled in.
2. (Optional) Serve via Apache at a `localhost` host so `.htaccess` rewrites apply, **or** use the
   bundled dev server: `php -S localhost:8000 router.dev.php` from the project root.
   `router.dev.php` emulates the Apache rewrites (static files, `/api/v1/*` routing with a `chdir`
   into `api/v1/`, fallback to `index.php`). It is **dev-only — do not deploy it**.

Uploaded files live **outside** the web root in `FILES_PATH` (`.../allfiles/`), served through
PHP (`serve_file.php`, `file_viewer.php`, `api/v1/endpoints/file_serve.php`) rather than directly.

## Architecture

```
index.php ─► session gate ─► layouts/main.php ─► pages/<p>.php      (web UI)
   └─ require_once all models, include xcrud, Autoload

api/v1/index.php ─► bearer-token auth ─► endpoints/<resource>.php   (REST API)

models/      domain layer — Entity base class + per-table subclasses
processors/  AJAX POST handlers (settings, workflow, users, requests)
xcrud/       third-party CRUD library + the DB layer behind Utility::query()
```

### Web routing
- Single entry point `index.php`. Routing is by query string: `index.php?p=editor` includes
  `pages/editor.php` into `layouts/main.php`.
- Two-stage gate: (1) session check redirects to `login.php`; (2) `Router::validateAccess($page,
  $user_id)` must pass before the page file is included (`index.php:47`). Some pages are
  whitelisted in `Router::validateAccess()` (dashboard, 404, profile, etc.).
- Session key is `SESSION_NAME` = `MD5("BRIDGE")`; logged-in user is
  `$_SESSION[SESSION_NAME]['user_id']`.

### API routing
- `api/v1/index.php` parses the path into `resource / id / action` and `switch`es to a
  `handle<Resource>()` function in `endpoints/<resource>.php`.
- Auth: `Authorization: Bearer <token>` validated against `user_api_tokens`
  (expiry + `is_active`). `api/v1/.htaccess` forwards the Authorization header to PHP and routes
  all non-file requests to `index.php`.
- Responses go through `ApiResponse::success()` / `ApiResponse::error()` (JSON envelope with
  `success`, `message`, `data`/`errors`, `timestamp`). CORS is wide open (`*`).
- See `api/v1/API_DOCUMENTATION.md` and `api/README.md`.

### Domain layer — the `Entity` Active Record (`models/Entity.php`)
- Base class for all models. The constructor runs `SHOW columns FROM <table>` to **introspect the
  schema at runtime**, auto-discovering columns, primary key, auto-increment, and mandatory
  (NOT NULL) fields. Passing an `$id` loads that row.
- Subclasses are tiny — usually just `var $table` and an `$ignore` list. Examples: `File` →
  `archives`, `User` → `users`, `DocumentType`, `ArchiveDocumentFolder`,
  `ArchiveDocumentSubFolder`, `Backup`, `Archive`.
- Inherited CRUD: `add()`, `update()`, `save()` (insert-or-update by PK), `delete()`, `get()`,
  `set()`, `patch($assoc)`, and static `all()`.
- Tradeoff to remember: every object construction = one extra `SHOW columns` query, and the code
  is tightly coupled to live DB structure.

### Access control (the genuinely complex part)
- Users belong to **groups** via `user_group_relation`.
- **Delegations** (`delegations` table) grant a user another group's access for a dated window
  (`status='ACTIVE'`, `start_date..end_date`) — used for covering colleagues. See
  `User::getActiveDelegatedGroups()` and `getAssociatedGroups()`.
- `Router::validateAccess()` joins `config_access_rights` ↔ `menu` against the user's combined
  (own + delegated) group IDs to decide page access.
- An emerging per-action role system exists in `User::can($keyword)` against `config_roles` /
  `config_role_access` — marked "still under development."
- `Menu::getUserMenu($user_id, $active)` renders the nav from the DB-driven menu the user can see.

### Two database access paths — IMPORTANT
There are **two** ways the code talks to MySQL; know which you're in:
1. **`models/DB.php`** — a hand-rolled wrapper (`new DB()`), used by `Entity` and the API.
   - It has a **safe prepared-statement path** (pass a params array) AND a legacy
     string-interpolation path. The API endpoints use the parameterized form.
2. **`Utility::query($sql, $type='SELECT', $one_row=null)`** (`models/Utility.php:481`) — used
   pervasively by the web side (User, Router, pages). This runs through **xcrud's** connection
   (`Xcrud_db::get_instance()`), NOT the `DB` class, and it **logs non-SELECT queries to an audit
   trail** (`activity_report`) when a session is active.

## Conventions

- **Naming:** classes/models PascalCase (`ArchiveDocumentFolder`); DB tables snake_case; web pages
  use dotted names mirroring hierarchy (`settings.users.manageaccess.php`,
  `settings.document_folders.php`); API endpoint files snake_case, resources kebab-case in URLs
  (`/sub-folders`, `/document-types`).
- **New web page:** add `pages/<name>.php`; link it via the DB `menu` table and grant access in
  `config_access_rights`, or whitelist it in `Router::validateAccess()` if it's public to all
  logged-in users.
- **New API endpoint:** add a `case` in `api/v1/index.php`, create
  `endpoints/<resource>.php` exporting `handle<Resource>($method, $id, $action, $input)`, and use
  `ApiResponse` for output and `ApiAuth::validateToken()` for protected routes.
- **New model:** extend `Entity`, set `$table` (and `$ignore` for columns like `updated_at`), and
  `require_once` it in `index.php` (and in any API endpoint that needs it).
- Prefer the **parameterized** `DB::query($sql, $type, [$params])` form for any new SQL touching
  user input.

## Gotchas & known issues

- **Secrets in `config.php`:** production DB credentials are hard-coded in plaintext. This is not a
  git repo and there's no `.gitignore`. `.htaccess` denies HTTP access to `config.php`/`*.sql`/
  `*.log`, but the file is still in the tree. Do **not** copy these credentials elsewhere; do not
  print them.
- **SQL injection:** the legacy `Utility::query` calls and `DB`'s non-prepared methods (`fetch`,
  `insert`, `update`, `search`) concatenate values directly into SQL. Much of `User.php` /
  `Router.php` interpolates `$user_id`, keywords, etc. Harden with prepared statements when editing.
- **Scaffolding / debris:** many throwaway files exist — `api/v1/test_*.php`, `*1.php`/`*2.php`
  duplicates, `old_dashboard.php`, `index2.php`, `filebridge copy.sql`, `__MACOSX/`, a 24 MB
  `.well-known.zip`. Don't assume a file is wired in just because it exists; check it's referenced.
- **`config2.php`** exists alongside `config.php` — verify which is actually included before editing.
- **Error display** is now environment-aware (set in `config.php` from `APP_ENV`: on locally, off
  in production). `error_log`/`debug.log` are committed and large.
- **See `SECURITY.md`** for the tracked security findings and remediation status (SQL injection,
  IDOR, secrets, etc.). Several Critical items are still outstanding.

## Key files

| Path | Purpose |
|------|---------|
| `index.php` | Web entry point, model includes, page router |
| `config.php` | DB credentials, `BASE_URL`, `FILES_PATH`, `SESSION_NAME` |
| `layouts/main.php` | HTML shell, asset includes, nav, page injection |
| `models/Entity.php` | Active-Record base class (runtime schema introspection) |
| `models/DB.php` | mysqli wrapper (prepared + legacy paths) |
| `models/Utility.php` | Static helpers; `query()` (xcrud-backed, audit-logging), `notify()`, `errorPage()` |
| `models/User.php` | Users, groups, delegations, role checks (`can()`) |
| `models/Router.php` | Page-level access validation |
| `models/Menu.php` | DB-driven nav rendering |
| `api/v1/index.php` | REST router + `ApiResponse` + `ApiAuth` |
| `api/v1/endpoints/` | One handler file per API resource |
| `api/v1/API_DOCUMENTATION.md` | API reference |
| `pages/` | Web UI pages (one per `?p=` value) |
| `processors/` | AJAX POST handlers |
| `db.sql` / `filebridge.sql` | Schema dumps |
