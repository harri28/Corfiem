# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**CORFIEM ERP** — A PHP/PostgreSQL business management system for a Peruvian company. Handles CRM, project management, sales pipeline (prospectos/cotizaciones), training (capacitación), incidents (puesta en marcha), and business intelligence.

- **Stack:** PHP 8.1+, PostgreSQL 15+, Apache (XAMPP), vanilla JS
- **URL:** `http://localhost/Corfiem_Cesar`
- **Default credentials:** admin@corfiem.com / Admin2025#

## Setup

1. Enable PHP extensions in `php.ini`: `pdo_pgsql`, `pgsql`, `curl`, `fileinfo`, `mbstring`
2. Create the database by running `schema.sql` in pgAdmin or psql
3. Configure DB credentials in `config/config.php`
4. Access `http://localhost/Corfiem_Cesar`

There are no build steps, test runners, or linters — this is a plain PHP application served directly by Apache.

## Architecture

### Request Flow
```
index.php (login) → session → dashboard.php
                                   ↓
                           modules/*.php  →  api/*_api.php  →  config/db.php  →  PostgreSQL
```

### Page Structure

Every protected page follows this exact pattern:

```php
require_once __DIR__ . '/../config/db.php';
$session     = require_auth();         // redirects to index.php if not logged in
$uid         = (int)$session['usuario_id'];
$page_title  = 'Page Name';            // used by header.php for <title>
$page_active = 'sidebar_key';          // highlights sidebar item

// DB queries here...

include __DIR__ . '/../includes/header.php';   // renders <head>, CSS, AND sidebar (do NOT include sidebar.php separately)
?>
<div class="main-content">
<?php render_topbar('Title', 'Subtitle'); ?>
<div class="page-body">
    <!-- content -->
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; // closes layout, loads main.js, toast container ?>
```

**Important:** `header.php` already calls `include __DIR__ . '/sidebar.php'` internally. Never include `sidebar.php` again after `header.php` — it will render the sidebar twice.

Valid `$page_active` values (from `includes/sidebar.php`):
`'dashboard'`, `'previas'`, `'clientes'`, `'proyectos'`, `'marcha'`, `'bi'`, `'capacitacion'`, `'auditoria'`

Note: the CRM module (`modules/crm.php`) must use `$page_active = 'clientes'` (not `'crm'`) to match what `sidebar.php` checks.

### Session Array

`require_auth()` returns `$_SESSION` which contains:

| Key | Value |
|---|---|
| `usuario_id` | int |
| `usuario_nombre` | string (first name only) |
| `usuario_email` | string |
| `usuario_rol` | `'Admin'` / `'Gerente'` / `'Usuario'` / `'Consultor'` |
| `avatar_initials` | string (e.g. `'AC'`) |

Role-gate pattern: `$es_admin = $session['usuario_rol'] === 'Admin';`

Login calls `session_regenerate_id(true)` and updates `usuarios.ultimo_acceso` on success.

### Database Layer (`config/db.php`)

All DB access goes through these helpers — never use PDO directly in modules:

| Function | Usage |
|---|---|
| `db_fetch_all($sql, $params)` | Returns array of rows |
| `db_fetch_one($sql, $params)` | Returns single row or `false` |
| `db_execute($sql, $params)` | INSERT/UPDATE/DELETE |
| `db_insert($sql, $params)` | INSERT with `RETURNING id`, returns new id |
| `audit_log($uid, $action, $modulo, $id, $before, $after)` | Audit trail (logs IP + UA) |
| `json_response($data, $status)` | Terminates with JSON output (calls `exit`) |
| `require_auth()` | Returns `$_SESSION` or redirects |
| `clean($str)` | XSS sanitization: `htmlspecialchars(strip_tags(trim()))` |
| `init_upload_dirs()` | Creates `uploads/` subdirs if missing — called automatically on `require` |

`db_insert()` requires a `RETURNING id` clause in the SQL — it calls `lastInsertId()` which reads from PostgreSQL's `RETURNING` clause, not a sequence directly.

PHP booleans passed to `db_execute`/`db_insert` are automatically bound as `PDO::PARAM_BOOL`.

### Database Views

Four materialized views are used directly in modules — query them like tables:

| View | Used in |
|---|---|
| `vw_kpi_dashboard` | `dashboard.php`, `modules/bi.php` |
| `vw_metricas_previas` | `modules/previas.php` |
| `vw_prospectos_resumen` | `modules/previas.php` |
| `vw_proyectos_completo` | `modules/proyectos.php` |

There is no `REFRESH MATERIALIZED VIEW` call anywhere in the PHP code — views reflect data at schema creation time. If adding features that depend on fresh view data, you must either add a refresh call or query base tables directly.

### Database Triggers

Three triggers defined in `schema.sql` run automatically:

| Trigger | Effect |
|---|---|
| `trigger_recalcular_totales_cotizacion` | After UPDATE on `cotizaciones` — recalculates `subtotal`, `igv` (18% if `aplica_igv=TRUE`), `total` |
| `trigger_actualizar_cotizacion_desde_items` | After INSERT/UPDATE/DELETE on `cotizacion_items` — recalculates parent `cotizaciones` totals |
| `actualizar_avance_proyecto` | After INSERT/UPDATE/DELETE on `entregables` — recalculates `proyectos.avance_porcentaje` |

### API Layer (`api/*_api.php`)

All APIs use the same pattern:

```php
require_once __DIR__ . '/../config/db.php';
$session = require_auth();
$uid     = (int)$session['usuario_id'];
$action  = $_REQUEST['action'] ?? '';
header('Content-Type: application/json; charset=utf-8');

try {
    match ($action) {
        'create' => createThing($uid),
        'update' => updateThing($uid),
        default  => json_response(['success'=>false,'message'=>'Acción no válida.'], 400),
    };
} catch (Throwable $e) {
    json_response(['success'=>false,'message'=>$e->getMessage()], 500);
}
```

Response format is always `{"success": true|false, "message": "...", "data": ...}`.

Frontend calls APIs via `fetch('../api/thing_api.php', { method: 'POST', body: formData })`.

### File Upload Handling

File uploads follow a consistent pattern across `entregables_api.php`, `pagos_api.php`, `capacitacion_api.php`, and `usuarios_api.php`:

```php
$ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['pdf', 'jpg', 'png', 'doc', 'docx', 'zip'];
$maxSize = ($ext === 'zip') ? 200 * 1024 * 1024 : 20 * 1024 * 1024;
$filename = "{$module}_{$id}_" . uniqid() . ".{$ext}";
move_uploaded_file($file['tmp_name'], UPLOADS_PATH . "/{$subdir}/{$filename}");
```

- Stored path in DB: relative string, e.g. `uploads/entregables/entregable_42_abc.pdf`
- File deletion: query DB path → `unlink()` → delete DB record
- Upload constants (`UPLOADS_PATH`, `PDF_PATH`, etc.) defined in `config/config.php`
- Default limit: 20 MB; ZIP files: 200 MB

### Key Data Model Relationships

```
prospectos → (converted to) → clientes → proyectos → entregables → entregables_archivos
                ↓                                  → incidencias
           cotizaciones                            → pagos
               ↓
         cotizacion_items

cursos → inscripciones_curso
      → materiales_curso
      → cuestionarios → preguntas → respuestas_intento
```

- `usuarios.rol`: Admin / Gerente / Usuario / Consultor
- Project progress is stored as `proyectos.avance_porcentaje` and driven by `entregables.porcentaje` (trigger)
- All CRUD is audit-logged in `auditoria_log` with JSON before/after snapshots
- `prospectos.estado`: `nuevo`, `contactado`, `en_evaluacion`, `propuesta_enviada`, `negociacion`, `aceptado`, `rechazado`, `archivado`
- `cotizaciones.estado`: `borrador`, `enviada`, `aceptada`, `rechazada`, `vencida`
- `incidencias.estado`: `abierta`, `en_proceso`, `resuelta`, `cerrada`
- `incidencias.severidad`: `Crítica`, `Alta`, `Media`, `Baja` — sorted 1–4 in queries (hardcoded CASE in ORDER BY)
- `cursos.estado`: `borrador`, `publicado`, `archivado`
- `estados_proyecto` hardcoded IDs used in raw SQL: 4=Completado, 5=Cancelado, 6=En Pausa (e.g. `WHERE estado_id NOT IN (4,5,6)` means active projects)
- `cotizaciones.aplica_igv` — boolean; DB trigger auto-recalculates `igv` (18%) and `total` on update

### PDF Generation

`includes/generar_cotizacion_pdf.php` generates quote PDFs using `vendor/fpdf` (FPDF library, no Composer autoload — included directly). It is `require_once`'d by `api/previas_api.php`. Generated files go to `uploads/proyectos/`.

### Scheduled Tasks

`cron/vencer_cotizaciones.php` — **currently a stub (empty file)**. Intended to auto-expire cotizaciones past their `fecha_vencimiento`. Run via Windows Task Scheduler or a cron job calling `php cron/vencer_cotizaciones.php`.

### Claude AI Proxy

`api/claude_proxy.php` proxies requests to the Anthropic API for PDF data extraction (auto-fills project forms from uploaded PDFs). Disabled by default — controlled by `AI_ENABLED` constant in `config/config.php`. The flow: JS extracts text from PDF → POST to `claude_proxy.php` → Anthropic API → returns structured JSON.

### Frontend

- Single stylesheet: `assets/css/style.css` with CSS variables (`--c-navy`, `--c-border`, `--c-text-3`, `--c-success`, `--c-danger`, `--c-warning`, `--c-accent`, `--sidebar-w`, etc.)
- Single global script: `assets/js/main.js` (~270 lines):
  - `openModal(id)` / `closeModal(id)` — toggles `.open` on `.modal-overlay`; ESC key closes all
  - `showToast(title, msg, type, duration)` — types: `success`, `error`, `warning`, `info`
  - `apiPost(url, data)` / `apiGet(url, params)` — async fetch helpers
  - `escHtml(str)` — JS-side XSS prevention
  - `formatCurrency(n, symbol)` — `es-PE` locale formatting (e.g. `S/ 1,200.00`)
  - `formatDate(iso)` / `timeAgo(iso)` — display helpers
  - `filterTable(input, tableId)` — client-side table search

**HTML attribute conventions in modules:**
- `data-confirm` — any button/link with this attribute triggers a confirm dialog before proceeding
- `data-flash` — elements auto-hide after 4 seconds (used for inline success/error messages)
- `data-timestamp` — ISO timestamp values are converted to relative strings ("Hace 2 horas") and refreshed every 60 s

- No frontend framework or bundler — all module-specific JS is inline in each `.php` file

## Docker / Deployment

A Cloud Run–ready setup exists alongside the XAMPP dev environment:

- **Dockerfile** — `php:8.1-apache`, extensions installed, Apache port changed to **8080**
- **docker-compose.yml** — `app` service + `postgres:15-alpine` with `schema.sql` auto-loaded; volumes `postgres_data` and `uploads_data` for persistence
- **docker-entrypoint.sh** — generates `config/config.php` at container start from environment variables (`APP_URL`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `CLAUDE_API_KEY`, etc.) and creates upload subdirectories owned by `www-data`
- **config/config.example.php** — template for manual deployment; copy to `config/config.php` and fill values

## Important Conventions

- **Queries use `ILIKE`** (case-insensitive) for PostgreSQL text search, not `LIKE`
- **String concatenation in SQL** uses `||` not `CONCAT()`: `u.nombre || ' ' || u.apellido`
- **Boolean filter in PHP** for PostgreSQL: pass `'true'`/`'false'` strings, not PHP booleans
- **INSERT statements must end with `RETURNING id`** when used with `db_insert()`
- **Currency:** Peruvian Sol (S/), formatted with `number_format($val, 2)`
- **Dates:** Display format `d/m/Y`, stored as `TIMESTAMP WITH TIME ZONE`; timezone set to `America/Lima`
- **Audit logging is mandatory** on all CREATE, UPDATE, DELETE operations
- **Never query `$_GET`/`$_POST` directly in SQL** — always cast integers with `(int)` and use prepared statements
- All sidebar `href` values use `<?= APP_URL ?>` — never hardcode paths
- The `test/` folder contains only `generar_hash.php` (password hash generator — bcrypt cost 12) — do not use for anything else
- `eliminar_DB.sql` drops the entire schema — never run unless doing a full reset
