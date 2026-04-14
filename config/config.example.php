<?php
// ============================================================
//  config/config.php — Constantes globales del sistema
// ============================================================

// --- Aplicación ---
define('APP_NAME',    'ERP CORFIEM');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/Corfiem_Cesar');
define('APP_PATH',    dirname(__DIR__));

// --- Base de datos ---
define('DB_HOST',     'localhost');
define('DB_PORT',     '5432');
define('DB_NAME',     'corfiem_db');
define('DB_USER',     'postgres');
define('DB_PASS',     'TU_CONTRASEÑA_AQUI');

// --- Rutas de archivos ---
define('UPLOADS_PATH', APP_PATH . '/uploads');
define('PDF_PATH',     APP_PATH . '/uploads/proyectos');
define('DOCS_PATH',    APP_PATH . '/uploads/documentos');
define('CAP_PATH',     APP_PATH . '/uploads/capacitacion');

// --- Seguridad ---
define('SESSION_NAME',    'CORFIEM_SESSION');
define('SESSION_LIFETIME', 1800);
define('BCRYPT_COST',      12);

// --- Claude API (desactivada por ahora) ---
define('AI_ENABLED',       false);
define('CLAUDE_API_KEY',   'TU_API_KEY_AQUI');
define('CLAUDE_MODEL',     'claude-haiku-4-5-20251001');
define('CLAUDE_MAX_TOKENS', 1024);

// --- Zona horaria ---
date_default_timezone_set('America/Lima');

// ============================================================
//  config/config.php — Configuración general
// ============================================================

// Moneda
define('MONEDA_SIMBOLO', 'S/');  // ← AGREGA ESTA LÍNEA
