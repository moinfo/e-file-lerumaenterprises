# e-File Integration Guide — Connecting External Systems

How any external application (e.g. the **Mainstore POS**, CodeIgniter) pushes
documents into **e-File** ("File Bridge"), keeps them linked to a source record,
and lets users view/edit them inside e-File.

This document covers **both sides** of the integration:

- **Provider** — the e-File **Ingest API** (`/api/v1/ingest/*`), this repo.
- **Consumer** — the external system that uploads files (reference
  implementation: the Mainstore POS `Efile_uploader` library).

---

## 1. Concept

```
┌────────────────────┐   1. POST file + external_ref_id (X-API-Key)   ┌───────────────────┐
│  External System   │ ─────────────────────────────────────────────▶ │      e-File       │
│  (e.g. POS)        │                                                 │   Ingest API      │
│                    │ ◀───────────  201 { efile_id }  ─────────────── │                   │
│  stores            │                                                 │  • archives row   │
│  efile_id +        │   2. (optional) e-File calls back for live      │  • external_file_ │
│  efile_ref         │ ◀───────────  source-record details ──────────  │    refs link row  │
└────────────────────┘      GET /efile_callback/expense/{id}           └───────────────────┘
```

1. The external system uploads a file with a **stable `external_ref_id`**
   (e.g. `expense-47`, `receiving-1280`). e-File stores it as an **archive** and
   creates a link row in **`external_file_refs`**, returning an **`efile_id`**.
2. The external system saves **`efile_id`** and **`efile_ref`** on its own record.
3. e-File users see the document under **Settings → Connected Systems →
   Received Files**, where they can categorise/edit it. e-File can optionally
   **call back** to the source system to display live record details.
4. The external system can later **view** the document directly
   (`view_by_ref.php?ref=…`), **update** its metadata (`PATCH`), or
   **retract** it (`DELETE`).

Key idea: the source system owns the *record*; e-File owns the *document*. They
are joined by the `(connected_system_id, external_ref_id)` pair.

---

## 2. One-time setup (e-File / provider)

### 2.1 Database tables

These two tables back the integration. If migrating to a new server, create
them first (column list is authoritative as used by `models/ConnectedSystem.php`
and `api/v1/endpoints/ingest.php`):

```sql
CREATE TABLE IF NOT EXISTS `connected_systems` (
  `id`                       INT AUTO_INCREMENT PRIMARY KEY,
  `name`                     VARCHAR(120) NOT NULL,
  `slug`                     VARCHAR(140) NOT NULL,
  `description`              TEXT NULL,
  `api_key`                  CHAR(64) NOT NULL,             -- 32 random bytes, hex
  `default_sub_folder_id`    INT NULL,                      -- archive_document_sub_folders.id
  `default_document_type_id` INT NULL,                      -- document_types.id
  `allowed_extensions`       VARCHAR(255) NOT NULL DEFAULT 'pdf,jpg,jpeg,png',
  `max_file_size_mb`         INT NOT NULL DEFAULT 25,
  `callback_url`             VARCHAR(255) NULL,             -- source system base URL for detail callbacks
  `is_active`                TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_api_key` (`api_key`),
  KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `external_file_refs` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `connected_system_id` INT NOT NULL,
  `external_ref_id`     VARCHAR(190) NOT NULL,             -- the source system's stable key
  `local_id`            VARCHAR(190) NULL,                 -- source record id (for callbacks)
  `archive_id`          INT NOT NULL,                      -- archives.id
  `status`              ENUM('active','deleted_by_source') NOT NULL DEFAULT 'active',
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NULL,
  UNIQUE KEY `uniq_system_ref` (`connected_system_id`, `external_ref_id`),
  KEY `idx_archive` (`archive_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

> No FK cascade is declared — `ConnectedSystem::delete()` removes child refs
> explicitly for shared-hosting compatibility.

### 2.2 Register the connected system

In the e-File web UI: **Settings → Connected Systems → Register**. Provide a
name, allowed extensions, max size, and (optionally) a default sub-folder /
document type and a **callback URL** (the source system's base URL). e-File
generates a **64-char API key** — copy it; it's shown for the consumer to use.

You can later **rotate the key**, **toggle active**, or **delete** the system
from the same page.

---

## 3. The Ingest API (provider reference)

Base path: `https://<efile-host>/api/v1/ingest`

**Auth:** every request requires header `X-API-Key: <key>` matching an
**active** `connected_systems` row. Missing/invalid key → `401`.

All responses are JSON via the standard envelope:
`{ "success": bool, "message": string, "data"|"errors": …, "timestamp": … }`.

| Method & path | Purpose | Success |
|---|---|---|
| `POST /ingest/upload` | Receive a file, create an archive + ref | `201` |
| `GET /ingest/file/{ref}` | Status of a ref | `200` |
| `PATCH /ingest/file/{ref}` | Update `local_id` / `description` | `200` |
| `DELETE /ingest/file/{ref}` | Retract (soft-delete) if not completed | `200` |
| `GET /ingest/detail/{ref}` | Proxy live record detail from the source | `200` |
| `GET /ingest/serve/{ref}` | Stream the stored file inline | `200` |
| `GET /ingest/stats` | Per-status counts for this system | `200` |

### 3.1 `POST /ingest/upload`

`multipart/form-data`:

| Field | Required | Notes |
|---|---|---|
| `file` | ✅ | the document (CURLFile / file part) |
| `external_ref_id` | ✅ | stable source key, e.g. `expense-47`. Must be unique per system |
| `description` | – | becomes the archive display name if set |
| `document_date` | – | `YYYY-MM-DD`; defaults to today |
| `local_id` | – | source record id, enables detail callbacks |

Validation: extension must be in the system's `allowed_extensions`; size ≤
`max_file_size_mb`; duplicate `external_ref_id` → `409`. The file is stored under
`FILES_PATH/pf-archives/` and an `archives` row is created with
`document_type`/`sub_folder_id` left **NULL** (the e-File editor categorises it).

**201 response:**

```json
{
  "success": true,
  "message": "File received and archived successfully",
  "data": {
    "efile_id": 412,
    "external_ref_id": "expense-47",
    "system": "Mainstore POS",
    "status": "active",
    "editable": true
  }
}
```

Store `data.efile_id` and your `external_ref_id` on the source record.

### 3.2 `GET /ingest/file/{ref}`

Returns `efile_id`, `status` (`active`/`deleted_by_source`), `completed`
(bool — set once an e-File user finalises it), `editable`, `name`,
`description`, `document_date`, timestamps.

### 3.3 `PATCH /ingest/file/{ref}`

JSON body, any of: `local_id`, `description`. Used e.g. to attach the real
record id after the source record is saved, or to set a friendlier description.

### 3.4 `DELETE /ingest/file/{ref}`

Soft-deletes the ref and removes the archive + file **only if not completed**
(`completed=1` → `403`). Already-deleted → `410`.

### 3.5 `GET /ingest/detail/{ref}` (reverse callback)

If the ref has a `local_id` and the system has a `callback_url`, e-File makes a
server-to-server `GET {callback_url}/efile_callback/expense/{local_id}` with the
same `X-API-Key`, and returns the source system's JSON. This is how the e-File
**Received Files** page shows live record details.

---

## 4. Consumer implementation (external system)

Reference implementation lives in the **Mainstore POS** (CodeIgniter 3).

### 4.1 Config — `application/config/efile.php`

```php
$config['efile_api_url'] = 'https://e-file.lerumaenterprises.co.tz/api/v1/ingest/upload';
$config['efile_web_url'] = 'https://e-file.lerumaenterprises.co.tz';   // for "View" links
$config['efile_api_key'] = '<64-char key from Connected Systems>';
$config['efile_timeout'] = 30;
// Folder/document-type IDs matching your e-File structure (0 = caller must send)
$config['efile_expenses_sub_folder_id']   = 76;
$config['efile_expenses_doc_type_id']     = 13;
$config['efile_receivings_sub_folder_id'] = 76;
$config['efile_receivings_doc_type_id']   = 7;
```

> URLs auto-switch local↔production by HTTP host, and every value can be
> overridden by an environment variable (`EFILE_API_URL`, `EFILE_API_KEY`,
> `EFILE_WEB_URL`, …). Keep the real API key out of source where possible.

### 4.2 Library — `application/libraries/Efile_uploader.php`

```php
$this->load->library('efile_uploader');

// Build a stable ref for a record (or a unique one for a new record)
$ref = $this->efile_uploader->efile_ref('expense', $expense_id);  // → "expense-47"

// Upload
$res = $this->efile_uploader->upload($absolute_file_path, $mime_type, [
    'external_ref_id' => $ref,
    'description'     => 'Fuel receipt Jan 2026',
    'document_date'   => '2026-01-15',
]);
// $res = ['success'=>true, 'efile_id'=>412, 'error'=>null]  on HTTP 201

if ($res['success']) {
    // persist on the local record
    $data['efile_id']  = $res['efile_id'];
    $data['efile_ref'] = $ref;
}

// Later: update metadata on an existing ref
$this->efile_uploader->patch_ref($ref, ['local_id' => $expense_id]);
```

The library only treats **HTTP 201 + `{success:true, data:{efile_id}}`** as
success; anything else returns `['success'=>false, 'error'=>…]`.

### 4.3 Local schema additions

Migration `20260607000000_add_efile_columns.php` adds:

- `ospos_expenses`: `file`, `efile_id` (INT), `efile_ref` (VARCHAR)
- `ospos_receivings`: `efile_id` (INT), `efile_ref` (VARCHAR)

### 4.4 Callback endpoint — `application/controllers/Efile_callback.php`

Session-free, **X-API-Key authenticated** (same key). Lets e-File fetch live
details for the Received Files page:

```
GET  /efile_callback/expense/{expense_id}
Header: X-API-Key: <key>
→ { "success": true, "data": { expense_id, date, amount, category, … , efile_id, efile_ref } }
```

The incoming key is compared against `efile_api_key` from config; mismatch → `401`.

### 4.5 Viewing a document from the source system

Link users straight to the e-File viewer (requires an e-File login; access is
scoped to the user's folder permissions, returns 404 on no-access to avoid ref
enumeration):

```php
$view_url = rtrim($this->config->item('efile_web_url','efile'),'/')
          . '/view_by_ref.php?ref=' . rawurlencode($efile_ref);
// <a href="$view_url" target="_blank">View</a>
```

`view_by_ref.php` resolves the ref → archive → `serve_file.php`, which streams
the file from `FILES_PATH` with the correct MIME type.

---

## 5. End-to-end flow (expense example)

1. User attaches a receipt to an expense in POS.
2. POS: `efile_ref('expense', $id)` → `expense-47`; `Efile_uploader::upload(...)`.
3. e-File stores the archive, creates the ref, returns `efile_id`.
4. POS saves `efile_id=412`, `efile_ref=expense-47` on the expense row.
5. An e-File user opens **Received Files**, sees the receipt, and (via the
   detail callback) the live expense data; categorises and marks it completed.
6. In POS, the expense's **View** link opens `view_by_ref.php?ref=expense-47`,
   rendering the PDF/image inline.
7. If the expense is deleted in POS, POS may `DELETE /ingest/file/expense-47`
   (succeeds only while the e-File side is not completed).

---

## 6. Operational notes & gotchas

- **PHP version:** the Ingest API must run on **PHP 7.1+** (uses nullable type
  hints / `void` returns). Avoid PHP 8-only syntax in endpoints — a `match()`
  on a PHP 7 host is a *parse error* that fatals before any JSON is sent,
  surfacing as **"HTTP 200, `application/json`, body = a single space."**
- **Clean JSON on failure:** `api/v1/index.php` buffers output, clears stray
  whitespace/BOM from includes, catches `\Throwable`, and has a shutdown handler
  that converts any fatal into a JSON `500` (detail only outside production).
  So a broken response should never again be a bare space.
- **Secrets file:** the e-File DB password / sync secret load from
  `efile_secrets.php` outside the web root. Save it as **UTF-8 without BOM**,
  `<?php` as the very first bytes, **no closing `?>`** — a stray leading byte
  there corrupts every API response body.
- **File storage:** documents live in `FILES_PATH/pf-archives/` (on production
  `/home/lerumaen/public_html/allfiles/pf-archives/`). Direct HTTP access to
  `/allfiles/` is blocked; files are served only through PHP.
- **Idempotency:** re-uploading the same `external_ref_id` returns `409`. Use
  `GET /ingest/file/{ref}` to check status before re-sending.
- **Auth keys are bearer-equivalent secrets** — transmit over HTTPS only; rotate
  from the Connected Systems page if leaked.

---

## 7. Quick reference (cURL)

```bash
# Upload
curl -X POST https://<efile-host>/api/v1/ingest/upload \
  -H "X-API-Key: <key>" \
  -F "file=@receipt.pdf" \
  -F "external_ref_id=expense-47" \
  -F "description=Fuel receipt" \
  -F "document_date=2026-01-15"

# Status
curl https://<efile-host>/api/v1/ingest/file/expense-47 -H "X-API-Key: <key>"

# Update metadata
curl -X PATCH https://<efile-host>/api/v1/ingest/file/expense-47 \
  -H "X-API-Key: <key>" -H "Content-Type: application/json" \
  -d '{"local_id":"47","description":"Fuel receipt (Jan)"}'

# Retract
curl -X DELETE https://<efile-host>/api/v1/ingest/file/expense-47 -H "X-API-Key: <key>"

# Health check the endpoint is alive (bad key should return JSON 401, not a space)
curl -i -X POST https://<efile-host>/api/v1/ingest/upload -H "X-API-Key: WRONG"
```

---

*Provider code:* `api/v1/endpoints/ingest.php`, `models/ConnectedSystem.php`,
`view_by_ref.php`, `serve_file.php`, `pages/settings.connected_systems.php`,
`pages/received_files.php`.
*Consumer code (Mainstore POS):* `application/libraries/Efile_uploader.php`,
`application/config/efile.php`, `application/controllers/Efile_callback.php`,
`application/migrations/20260607000000_add_efile_columns.php`.
