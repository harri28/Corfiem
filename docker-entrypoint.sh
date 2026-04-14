#!/bin/bash
set -e

# ── Generar config/config.php desde variables de entorno ──────────────────────
cat > /var/www/html/config/config.php <<PHP
<?php
// Generado automáticamente por docker-entrypoint.sh
define('APP_NAME',    'ERP CORFIEM');
define('APP_VERSION', '1.0.0');
define('APP_URL',     '${APP_URL:-http://localhost}');
define('APP_PATH',    dirname(__DIR__));

define('DB_HOST',     '${DB_HOST:-db}');
define('DB_PORT',     '${DB_PORT:-5432}');
define('DB_NAME',     '${DB_NAME:-corfiem_db}');
define('DB_USER',     '${DB_USER:-postgres}');
define('DB_PASS',     '${DB_PASS:-postgres}');

define('UPLOADS_PATH', APP_PATH . '/uploads');
define('PDF_PATH',     APP_PATH . '/uploads/proyectos');
define('DOCS_PATH',    APP_PATH . '/uploads/documentos');
define('CAP_PATH',     APP_PATH . '/uploads/capacitacion');

define('SESSION_NAME',     'CORFIEM_SESSION');
define('SESSION_LIFETIME', 1800);
define('BCRYPT_COST',      12);

define('AI_ENABLED',        ${AI_ENABLED:-false});
define('CLAUDE_API_KEY',   '${CLAUDE_API_KEY:-}');
define('CLAUDE_MODEL',     'claude-haiku-4-5-20251001');
define('CLAUDE_MAX_TOKENS', 1024);

define('MONEDA_SIMBOLO', 'S/');
date_default_timezone_set('America/Lima');
PHP

# ── Asegurar estructura de directorios en uploads/ ────────────────────────────
for subdir in capacitacion cv documentos entregables pagos proyectos; do
    mkdir -p "/var/www/html/uploads/${subdir}"
done
chown -R www-data:www-data /var/www/html/uploads/

exec "$@"
