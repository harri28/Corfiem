<?php
// ============================================================
//  index.php — Login / Autenticación
//  Ruta: C:\xampp\htdocs\Corfiem_Cesar\index.php
// ============================================================
require_once __DIR__ . '/config/db.php';

// Iniciar sesión segura
session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path'     => '/',
    'secure'   => false,   // true en HTTPS/producción
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

// Si ya está autenticado, redirigir al dashboard
if (!empty($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error   = '';
$success = '';

// ── Procesar formulario de login ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Ingresa tu correo y contraseña.';
    } else {

        $user = db_fetch_one(
        "SELECT * FROM usuarios WHERE email = ? AND activo = TRUE",
        [$email]
        );

        if ($user && password_verify($password, $user['password_hash'])) {
            // Regenerar ID de sesión por seguridad
            session_regenerate_id(true);

            $_SESSION['usuario_id']     = $user['id'];
            $_SESSION['usuario_nombre'] = $user['nombre'] . ' ' . $user['apellido'];
            $_SESSION['usuario_email']  = $user['email'];
            $_SESSION['usuario_rol']    = $user['rol'];
            $_SESSION['avatar_initials']= $user['avatar_initials'] ?? strtoupper(substr($user['nombre'], 0, 1) . substr($user['apellido'], 0, 1));

            // Actualizar último acceso
            db_execute(
                "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?",
                [$user['id']]
            );

            // Log de auditoría
            audit_log($user['id'], 'LOGIN', 'auth', $user['id']);

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Credenciales incorrectas. Verifica tu correo y contraseña.';
            // Log intento fallido
            error_log("[CORFIEM] Login fallido para: $email");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — Acceso al Sistema</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* ── Login-specific styles ────────────────────── */
        body.login-page {
            background: #F5F5F5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .login-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            max-width: 900px;
            width: 95%;
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 2px 24px rgba(0,0,0,0.10);
            overflow: hidden;
        }

        /* Panel izquierdo — marca corporativa */
        .login-brand {
            background: #0D1B2A;
            color: #fff;
            padding: 60px 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-logo-icon {
            width: 48px;
            height: 48px;
            background: #1B3A6B;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-logo-icon svg {
            width: 28px;
            height: 28px;
            fill: #fff;
        }

        .brand-name {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .brand-tagline {
            font-size: 12px;
            color: #8FA3BF;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .brand-headline {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px 0;
        }

        .brand-headline h2 {
            font-size: 28px;
            font-weight: 600;
            line-height: 1.35;
            margin-bottom: 16px;
        }

        .brand-headline p {
            font-size: 14px;
            color: #8FA3BF;
            line-height: 1.6;
        }

        .brand-modules {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .brand-module-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #A0B4C8;
        }

        .brand-module-item::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #1B3A6B;
            flex-shrink: 0;
        }

        /* Panel derecho — formulario */
        .login-form-panel {
            padding: 60px 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-form-panel h3 {
            font-size: 24px;
            font-weight: 700;
            color: #0D1B2A;
            margin-bottom: 6px;
        }

        .login-form-panel p.subtitle {
            font-size: 14px;
            color: #6B7280;
            margin-bottom: 36px;
        }

        .form-field {
            margin-bottom: 20px;
        }

        .form-field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
            letter-spacing: 0.02em;
        }

        .form-field input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #D1D5DB;
            border-radius: 4px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: #0D1B2A;
            background: #FAFAFA;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }

        .form-field input:focus {
            outline: none;
            border-color: #1B3A6B;
            box-shadow: 0 0 0 3px rgba(27,58,107,0.08);
            background: #fff;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: #0D1B2A;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            letter-spacing: 0.04em;
            transition: background 0.2s, transform 0.1s;
            margin-top: 8px;
        }

        .btn-login:hover  { background: #1B3A6B; }
        .btn-login:active { transform: scale(0.99); }

        .alert-error {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #991B1B;
            padding: 10px 14px;
            border-radius: 4px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            color: #15803D;
            padding: 10px 14px;
            border-radius: 4px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .login-footer {
            margin-top: 32px;
            font-size: 12px;
            color: #9CA3AF;
            text-align: center;
        }

        @media (max-width: 640px) {
            .login-wrapper       { grid-template-columns: 1fr; }
            .login-brand         { display: none; }
            .login-form-panel    { padding: 40px 28px; }
        }
    </style>
</head>
<body class="login-page">

<div class="login-wrapper">

    <!-- Marca corporativa -->
    <div class="login-brand">
        <div class="brand-logo">
            <div class="brand-logo-icon">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 3h7v7H3V3zm11 0h7v7h-7V3zM3 14h7v7H3v-7zm14 0v2h-3v2h3v2h2v-2h3v-2h-3v-2h-2z"/>
                </svg>
            </div>
            <div>
                <div class="brand-name">CORFIEM</div>
                <div class="brand-tagline">Sistema ERP</div>
            </div>
        </div>

        <div class="brand-headline">
            <h2>Gestión Integral de Proyectos</h2>
            <p>Plataforma corporativa para el control, seguimiento y análisis de proyectos de consultoría.</p>
        </div>

        <div class="brand-modules">
            <div class="brand-module-item">Gestión de Proyectos con IA</div>
            <div class="brand-module-item">CRM y Relación con Clientes</div>
            <div class="brand-module-item">Reportes Analíticos (BI)</div>
            <div class="brand-module-item">Capacitación y Auditoría</div>
        </div>
    </div>

    <!-- Formulario de login -->
    <div class="login-form-panel">
        <h3>Iniciar Sesión</h3>
        <p class="subtitle">Ingresa tus credenciales corporativas</p>

        <?php if ($error): ?>
            <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($_GET['bye'])): ?>
            <div class="alert-success">Sesión cerrada correctamente.</div>
        <?php endif; ?>
        <?php if (!empty($_GET['reset'])): ?>
            <div class="alert-success">✓ Contraseña actualizada. Ya puedes ingresar.</div>
        <?php endif; ?>

        <form method="POST" action="index.php" autocomplete="off" novalidate>
            <div class="form-field">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email"
                       placeholder="usuario@corfiem.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Contraseña</label>
                <div style="position:relative;">
                    <input class="form-control" type="password" name="password" 
                        id="password" placeholder="••••••••" required
                        style="padding-right:45px;">
                    <button type="button" 
                            onclick="togglePassword()" 
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                                background:none;border:none;cursor:pointer;padding:5px;
                                color:var(--c-text-3);transition:color 0.2s;"
                            onmouseover="this.style.color='var(--c-text-1)'"
                            onmouseout="this.style.color='var(--c-text-3)'">
                        <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" 
                            stroke="currentColor" stroke-width="2" 
                            width="20" height="20">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">INGRESAR AL SISTEMA</button>
        </form>

        <div style="text-align:center; margin-top:18px;">
            <a href="forgot_password.php"
               style="font-size:13px; color:#6B7280; text-decoration:none; transition:color .2s;"
               onmouseover="this.style.color='#1B3A6B'"
               onmouseout="this.style.color='#6B7280'">
                ¿Olvidaste tu contraseña?
            </a>
        </div>

        <div class="login-footer">
            <?= APP_NAME ?> v<?= APP_VERSION ?> &nbsp;·&nbsp;
            &copy; <?= date('Y') ?> CORFIEM. Todos los derechos reservados.
        </div>
    </div>
</div>
<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    
    if (passwordInput.type === 'password') {
        // Mostrar contraseña
        passwordInput.type = 'text';
        eyeIcon.innerHTML = `
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
            <line x1="1" y1="1" x2="23" y2="23"/>
        `;
    } else {
        // Ocultar contraseña
        passwordInput.type = 'password';
        eyeIcon.innerHTML = `
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
        `;
    }
}
</script>


</body>
</html>