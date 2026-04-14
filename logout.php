<?php
// ============================================================
//  logout.php — Cierre de sesión seguro
//  Ruta: C:\xampp\htdocs\Corfiem_Cesar\logout.php
// ============================================================
require_once __DIR__ . '/config/db.php';

session_name(SESSION_NAME);
session_start();

// Log de auditoría antes de destruir la sesión
if (!empty($_SESSION['usuario_id'])) {
    audit_log(
        (int)$_SESSION['usuario_id'],
        'LOGOUT',
        'auth',
        (int)$_SESSION['usuario_id']
    );
}

// Destruir sesión completamente
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(), '',
        time() - 42000,
        $p['path'],
        $p['domain'],
        $p['secure'],
        $p['httponly']
    );
}

session_destroy();

// Redirigir al login con mensaje de cierre
header('Location: index.php?bye=1');
exit;