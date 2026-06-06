# Security Review & Remediation Checklist

Findings from a multi-agent code review (2026-06-06) of the File Bridge / e-File app.
Sorted by real-world risk. Status legend: ✅ done · 🔶 partially mitigated · ⬜ outstanding.

> **Note:** This project is not under version control. Make backups before destructive edits.

---

## 🔴 Critical

### 1. Secrets committed in source — ✅ DONE (rotation still recommended)
- ✅ `config.php` no longer contains any password. Credentials load from a secrets file **outside
  the web root** (`getenv('EFILE_SECRETS_FILE')` → `/home/lerumaen/efile_secrets.php` →
  `dirname(project)/efile_secrets.php`), with per-value env-var overrides (`EFILE_DB_PASS`, etc.).
  Missing DB password now fails loudly (HTTP 500) instead of connecting blank.
- ✅ `api/v1/endpoints/uploads.php` and `pages/settings_synchronization.php` use a `SYNC_PASSWORD`
  constant (from secrets) with `hash_equals()`; an unset secret denies (503) instead of matching.
- ✅ Template committed: `efile_secrets.example.php` (placeholders only).
- ✅ Stale duplicate `config2.php` (held the prod password, unreferenced) → quarantined to
  `backups/config2.php.disabled`; whole `backups/` dir now denied via `backups/.htaccess`.
- ✅ Web-server deny extended to `secrets.*\.php`; dev router blocks `secrets`/`.disabled`/`/backups/`.

**⚠️ DEPLOY STEP (required):** production `config.php` has NO password — before/at deploy, create
`/home/lerumaen/efile_secrets.php` with the `production` block (see the example). Until then the
live site will return the 500 config error.

**Still recommended:** rotate the DB password and sync secret (they were exposed in source history),
and **delete `backups/config2.php.disabled`** once the production secrets file is confirmed working
(it is the last copy of the prod password in the tree).

### 2. Unauthenticated diagnostic endpoints — 🔶 MITIGATED (blocked), deletion still recommended
- ~18 `test_*` / `debug_* `/ `check_*` files under `api/v1/` and `api/v1/endpoints/` were reachable
  by direct URL with no auth: dumped DB records, `scandir()` of the archive dir, `DOCUMENT_ROOT`,
  and `test_view_api.php?file_id=N` streamed any document. `test_auth.php` was SQL-injectable via
  the `Authorization` header.
- **Done:** `api/v1/.htaccess` now denies direct access to `^(test|debug|check)[_.].*\.php$`
  (covers the `endpoints/` subdir too); `router.dev.php` enforces the same locally.
- **Still to do:** delete these files entirely before/at deployment — they have no production use
  (they are not routed through `index.php`).

### 3. Broken authorization in the API — ⬜ OUTSTANDING
- **IDOR** in `api/v1/endpoints/files.php`: `getSingleFile`/`downloadFile`/`viewFile`/`updateFile`/
  `deleteFile` look up `WHERE id = ?` with no ownership/group check. Any authenticated user can
  read, download, **update, or delete any file** by ID. `getAllFiles` lists everything.
- **`api/v1/endpoints/users.php:214` `checkUserPermission()` ignores its `$permission` argument**
  and returns true for any valid user → every user has full user-management/admin rights.
- **Fix:** Join archives to the caller's group/sub-folder permissions; implement
  `checkUserPermission()` against the real role system (`helpers/role_checker.php::hasRole`).
  *(Confirm the intended permission model before changing — risk of locking out legit access.)*

### 4. SQL injection (pervasive) — 🔶 CORE FIXED, some sinks remain
**Fixed (verified by fan-out review + runtime test):**
- ✅ `models/Router.php` `validateAccess()` — `$_GET['p']` now bound; group IDs `intval`-cast; empty-group guard.
- ✅ `models/DB.php` — `insert`/`update`/`replace`/`delete`/`fetch`/`search` real-escape values
  (with `(string)` casts); `sanitize()` recursion + weak-`addslashes` fixed; debug `echo` removed.
- ✅ `models/User.php` `can()` — bound params (also fixed `$access` shadowing bug).
- ✅ `models/Entity.php` constructor — `id` bound.
- ✅ `models/Utility.php` audit-log INSERT — xcrud `escape()`.
- ✅ `login.php:862` — **pre-auth login bypass** now uses bound params (was the worst sink).
- ✅ `ajax.php` (no auth gate) — `delete`/`delete_user_from_group` table restricted to identifier
  chars + int-cast ids; `searchFile()` fully parameterized; `nextFile()` UPDATE bound.
- ✅ `models/Menu.php` — `IN()` group list `intval`-cast.

**Fixed via fan-out (8 pages, one agent each; all lint-clean, app still serves):**
- ✅ `pages/files.php`, `pages/folders.php`, `pages/sub_folders.php`, `pages/document_types.php` —
  `sub_folder_id`/`document_type_id`/`id` int-cast (agents also caught session/DB-row ids feeding
  the non-binding `Utility::query`).
- ✅ `pages/settings.users.manageaccess.php`, `pages/settings.users.folder_manage_access.php`,
  `pages/settings.users.users.php`, `pages/settings_edited_files.php` — `g_id`/`group_id`/`user_id`/
  `id`/`year` int-cast; free-text `description` escaped via xcrud `escape()`.
- ✅ `models/Utility.php` `prepareInsertQuery`/`prepareUpdateQuery` — column **keys** now restricted
  to an identifier pattern, `$id`/`$id_column` escaped/whitelisted. (Values were already escaped via
  `clearQuotes`.)

**Remaining:** none — SQL injection remediation complete.
- ✅ `processors/requests.ajax.php` (RCE-grade `case 'query'` running `$_POST['sql']`) — moved out of
  the live tree to `backups/requests.ajax.php.disabled` (renamed so it can't execute as PHP). It was
  unreferenced and unreachable. Restore from there only if a legitimate use is found; otherwise
  delete permanently.
- Note: `api/v1/endpoints/*` are clean (already parameterized).

### 5. Path traversal — 🔶 PARTIAL
- `api/v1/endpoints/file_serve.php:23` — strips `../` with `str_replace` (defeatable by `....//`);
  leaks full filesystem paths in 404 responses. **Fix:** canonicalize with `realpath()` then
  enforce a prefix check against the base dir; remove path details from errors.
- `api/v1/endpoints/cleanup.php:131` — `unlink()` after a `strpos` containment check a symlink can
  bypass. **Fix:** `realpath()` both sides before comparing.
- `router.dev.php` (dev-only) — ✅ containment + deny-list added.

---

## 🟠 Important

### 6. MD5 password hashing — ⬜
`auth.php` / `users.php` use unsalted `md5()`. **Fix:** `password_hash()` / `password_verify()`,
migrate on next login.

### 7. Information disclosure — 🔶 PARTIAL
- ✅ `DB::log()` no longer echoes MySQL errors (now `error_log`).
- ✅ `display_errors` is now environment-aware (off in production) via `config.php`.
- ⬜ `files.php:314` returns `DOCUMENT_ROOT` / DB path / attempted path in 404s — strip these.
- ⬜ `DB::search()` has a stray `echo $this->result->num_rows;`.

### 8. File serving has no MIME allowlist — ⬜
`files.php` `viewFile` serves the detected MIME verbatim → a stored non-PDF (sync allows images)
could be served as HTML = stored XSS via the CORS-open API. **Fix:** allowlist
`application/pdf` + image types; else 403.

### 9. `<base href>` host-header injection — ✅ DONE
`layouts/main.php` now `htmlspecialchars()`-escapes `BASE_URL`. (Production `BASE_URL` is also a
fixed constant, not request-derived.)

### 10. Case-sensitive `Bearer` stripping — ⬜
`api/v1/index.php:77` (and duplicated in `auth.php`) uses `str_replace('Bearer ', ...)`. **Fix:**
`preg_replace('/^bearer\s+/i', '', $token)`, centralized in `ApiAuth`.

---

## 🟡 Correctness (non-security)

- `models/DB.php` `query()` → `fetchQuery()` → `query()` **executes SELECTs twice**. — ⬜
- `models/User.php` `can()` overwrites its `$access` parameter with the query result. — ⬜
- `Router::validateAccess()` — empty group list yields `IN ()` (SQL error; currently fails closed).
  Guard with `if (empty($combined_groups)) return false;` and `array_map('intval', ...)`. — ⬜

---

## ✅ Completed in this pass
- Environment-aware error display (verbose local / silent production) — `config.php`, `index.php`.
- `DB::log()` writes to error log instead of echoing — `models/DB.php`.
- `<base href>` output escaped — `layouts/main.php`.
- Diagnostic endpoints blocked at the web server — `api/v1/.htaccess`.
- `router.dev.php` hardened (deny-list + web-root containment). Dev-only — do not deploy.
- SQL injection: core data layer + entry points fixed (see item 4) — `Router`, `DB`, `User`,
  `Entity`, `Utility` audit log, `login.php` (pre-auth bypass), `ajax.php`, `Menu`. Verified with a
  runtime test (a bound `' OR '1'='1` returned only the legit 3994 rows, not all 17,635).

- Secrets removed from source: passwords now load from a file outside the web root with env-var
  override + `hash_equals` for the sync secret; `config2.php` quarantined; `backups/` denied.

## ⚠️ Related authorization gap (not SQLi)
- `ajax.php` has `session_start()` but **no login gate** — its operations (delete, save, search)
  are callable unauthenticated. The SQLi is fixed, but add an auth check at the top. Tracked with
  the broader authorization work in item 3.
