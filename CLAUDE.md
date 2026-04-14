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
| `usuario_rol` | `'Admin'` / `'Gerente'` / `'Usuario'` / `'Consultor'` |
| `avatar_initials` | string (e.g. `'AC'`) |

Role-gate pattern: `$es_admin = $session['usuario_rol'] === 'Admin';`

### Database Layer (`config/db.php`)

All DB access goes through these helpers — never use PDO directly in modules:

| Function | Usage |
|---|---|
| `db_fetch_all($sql, $params)` | Returns array of rows |
| `db_fetch_one($sql, $params)` | Returns single row |
| `db_execute($sql, $params)` | INSERT/UPDATE/DELETE |
| `db_insert($sql, $params)` | INSERT, returns new id |
| `audit_log($uid, $action, $modulo, $id, $before, $after)` | Audit trail |
| `json_response($data, $status)` | Terminates with JSON output |
| `require_auth()` | Returns `$_SESSION` or redirects |
| `clean($str)` | XSS sanitization via htmlspecialchars |

### Database Views

Four materialized views are used directly in modules — query them like tables:

| View | Used in |
|---|---|
| `vw_kpi_dashboard` | `dashboard.php`, `modules/bi.php` |
| `vw_metricas_previas` | `modules/previas.php` |
| `vw_prospectos_resumen` | `modules/previas.php` |
| `vw_proyectos_completo` | `modules/proyectos.php` |

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

### Key Data Model Relationships

```
prospectos → (converted to) → clientes → proyectos → entregables
                ↓                                  → incidencias
           cotizaciones
               ↓
         cotizacion_items
```

- `usuarios.rol`: Admin / Gerente / Usuario / Consultor
- Project progress is stored as `proyectos.avance_porcentaje` and driven by `entregables.porcentaje`
- All CRUD is audit-logged in `auditoria_log` with JSON before/after snapshots
- `prospectos.estado`: `nuevo`, `contactado`, `en_evaluacion`, `propuesta_enviada`, `negociacion`, `aceptado`, `rechazado`, `archivado`
- `cotizaciones.estado`: `borrador`, `enviada`, `aceptada`, `rechazada`, `vencida`
- `incidencias.estado`: `abierta`, `en_proceso`, `resuelta`, `cerrada`
- `estados_proyecto` hardcoded IDs used in raw SQL: 4=Completado, 5=Cancelado, 6=En Pausa (e.g. `WHERE estado_id NOT IN (4,5,6)` means active projects)
- `cotizaciones.aplica_igv` — boolean; a DB trigger auto-recalculates `igv` (18%) and `total` on update

### PDF Generation

`includes/generar_cotizacion_pdf.php` generates quote PDFs using `vendor/fpdf` (FPDF library, no Composer autoload — included directly). It is `require_once`'d by `api/previas_api.php`. Generated files go to `uploads/proyectos/`.

### Scheduled Tasks

`cron/vencer_cotizaciones.php` — intended to auto-expire cotizaciones past their `fecha_vencimiento`. Run via Windows Task Scheduler or a cron job calling `php cron/vencer_cotizaciones.php`.

### Claude AI Proxy

`api/claude_proxy.php` proxies requests to the Anthropic API for PDF data extraction (auto-fills project forms from uploaded PDFs). Disabled by default — controlled by `AI_ENABLED` constant in `config/config.php`. The flow: JS extracts text from PDF → POST to `claude_proxy.php` → Anthropic API → returns structured JSON.

### Frontend

- Single stylesheet: `assets/css/style.css` with CSS variables (`--c-navy`, `--c-border`, `--c-text-3`, etc.)
- Single global script: `assets/js/main.js` — provides `showToast(title, msg, type)`, `openModal(id)`, `closeModal(id)`, `escHtml(str)`
- No frontend framework or bundler — all vanilla JS inline in modules or in main.js
- Modals use class `.modal-overlay` + `.open`; toggled via `openModal`/`closeModal`

## Important Conventions

- **Queries use `ILIKE`** (case-insensitive) for PostgreSQL text search, not `LIKE`
- **String concatenation in SQL** uses `||` not `CONCAT()`: `u.nombre || ' ' || u.apellido`
- **Boolean filter in PHP** for PostgreSQL: pass `'true'`/`'false'` strings, not PHP booleans
- **Currency:** Peruvian Sol (S/), formatted with `number_format($val, 2)`
- **Dates:** Display format `d/m/Y`, stored as `TIMESTAMP WITH TIME ZONE`; timezone set to `America/Lima`
- **Audit logging is mandatory** on all CREATE, UPDATE, DELETE operations
- **Never query `$_GET`/`$_POST` directly in SQL** — always cast integers with `(int)` and use prepared statements
- The `test/` folder contains only `generar_hash.php` (password hash generator) — do not use for anything else
- `eliminar_DB.sql` drops the entire schema — never run unless doing a full reset
