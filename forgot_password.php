<?php
// ============================================================
//  forgot_password.php — Solicitud de recuperación de contraseña
// ============================================================
require_once __DIR__ . '/config/db.php';

// Si ya está logueado, no tiene sentido estar aquí
session_name(SESSION_NAME);
session_start();
if (!empty($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Crear tabla si no existe (migración ligera)
try {
    Database::pdo()->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id         SERIAL PRIMARY KEY,
            email      VARCHAR(255) NOT NULL,
            token      VARCHAR(64)  NOT NULL UNIQUE,
            expira_at  TIMESTAMP WITH TIME ZONE NOT NULL,
            usado      BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
        )
    ");
    Database::pdo()->exec("CREATE INDEX IF NOT EXISTS idx_pwr_token ON password_resets(token)");
} catch (Throwable $e) {
    // tabla ya existe — continuar
}

$error   = '';
$success = '';
$reset_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresa un correo electrónico válido.';
    } else {
        $usuario = db_fetch_one(
            "SELECT id, nombre, apellido, email FROM usuarios WHERE email = ? AND activo = TRUE",
            [$email]
        );

        if ($usuario) {
            // Invalidar tokens anteriores del mismo email
            db_execute(
                "UPDATE password_resets SET usado = TRUE WHERE email = ? AND usado = FALSE",
                [$email]
            );

            // Generar token seguro
            $token = bin2hex(random_bytes(32)); // 64 caracteres hex
            $expira = date('Y-m-d H:i:sP', strtotime('+1 hour'));

            db_execute(
                "INSERT INTO password_resets (email, token, expira_at) VALUES (?, ?, ?)",
                [$email, $token, $expira]
            );

            $reset_url = APP_URL . '/reset_password.php?token=' . $token;
            $success   = 'Enlace de recuperación generado para: ' . htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']);
        } else {
            // No revelar si el email existe (seguridad)
            $success = 'Si el correo está registrado, el enlace aparecerá a continuación.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body.login-page {
            background: #F5F5F5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .recovery-card {
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 2px 24px rgba(0,0,0,0.10);
            padding: 52px 48px;
            width: 100%;
            max-width: 440px;
        }
        .recovery-card .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 36px;
        }
        .recovery-card .logo-icon {
            width: 40px; height: 40px;
            background: #0D1B2A;
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
        }
        .recovery-card .logo-icon svg { width:22px; height:22px; fill:#fff; }
        .recovery-card .logo-name { font-size:18px; font-weight:700; color:#0D1B2A; }
        .recovery-card .logo-sub  { font-size:11px; color:#6B7280; letter-spacing:.06em; text-transform:uppercase; }
        .recovery-card h3 { font-size:22px; font-weight:700; color:#0D1B2A; margin-bottom:6px; }
        .recovery-card p.sub { font-size:13px; color:#6B7280; margin-bottom:28px; line-height:1.5; }

        .form-field { margin-bottom:20px; }
        .form-field label {
            display:block; font-size:13px; font-weight:600; color:#374151;
            margin-bottom:6px; letter-spacing:.02em;
        }
        .form-field input {
            width:100%; padding:11px 14px;
            border:1.5px solid #D1D5DB; border-radius:4px;
            font-size:14px; font-family:'Inter',sans-serif; color:#0D1B2A;
            background:#FAFAFA; transition:border-color .2s, box-shadow .2s;
            box-sizing:border-box;
        }
        .form-field input:focus {
            outline:none; border-color:#1B3A6B;
            box-shadow:0 0 0 3px rgba(27,58,107,.08); background:#fff;
        }
        .btn-primary {
            width:100%; padding:12px; background:#0D1B2A; color:#fff;
            border:none; border-radius:4px; font-size:14px; font-weight:600;
            font-family:'Inter',sans-serif; cursor:pointer; letter-spacing:.04em;
            transition:background .2s; margin-top:4px;
        }
        .btn-primary:hover { background:#1B3A6B; }
        .alert-error {
            background:#FEF2F2; border:1px solid #FECACA; color:#991B1B;
            padding:10px 14px; border-radius:4px; font-size:13px; margin-bottom:20px;
        }
        .alert-success {
            background:#F0FDF4; border:1px solid #BBF7D0; color:#15803D;
            padding:10px 14px; border-radius:4px; font-size:13px; margin-bottom:16px;
        }
        .reset-link-box {
            background:#EFF6FF; border:1px solid #BFDBFE; border-radius:4px;
            padding:14px; margin-bottom:20px;
        }
        .reset-link-box p { font-size:12px; color:#1D4ED8; margin:0 0 8px; font-weight:600; }
        .reset-link-box a {
            font-size:13px; color:#1E40AF; word-break:break-all;
            font-weight:500; text-decoration:underline;
        }
        .reset-link-box .expiry {
            font-size:11px; color:#6B7280; margin-top:8px; margin-bottom:0;
        }
        .back-link { margin-top:24px; text-align:center; font-size:13px; color:#6B7280; }
        .back-link a { color:#1B3A6B; font-weight:500; text-decoration:none; }
        .back-link a:hover { text-decoration:underline; }

        @media(max-width:480px){ .recovery-card{ padding:36px 24px; } }
    </style>
</head>
<body class="login-page">
<div class="recovery-card">

    <div class="logo">
        <div class="logo-icon">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 3h7v7H3V3zm11 0h7v7h-7V3zM3 14h7v7H3v-7zm14 0v2h-3v2h3v2h2v-2h3v-2h-3v-2h-2z"/>
            </svg>
        </div>
        <div>
            <div class="logo-name">CORFIEM</div>
            <div class="logo-sub">Sistema ERP</div>
        </div>
    </div>

    <h3>Recuperar Contraseña</h3>
    <p class="sub">Ingresa tu correo corporativo y se generará un enlace de recuperación.</p>

    <?php if ($error): ?>
        <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-success">✓ <?= $success ?></div>
    <?php endif; ?>

    <?php if ($reset_url): ?>
        <div class="reset-link-box">
            <p>Enlace de recuperación (válido 1 hora):</p>
            <a href="<?= htmlspecialchars($reset_url) ?>">
                <?= htmlspecialchars($reset_url) ?>
            </a>
            <p class="expiry">⏱ Expira en 1 hora. Úsalo una sola vez.</p>
        </div>
    <?php endif; ?>

    <?php if (!$reset_url): ?>
    <form method="POST" action="forgot_password.php" novalidate>
        <div class="form-field">
            <label for="email">Correo Electrónico</label>
            <input type="email" id="email" name="email"
                   placeholder="usuario@corfiem.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   required autofocus>
        </div>
        <button type="submit" class="btn-primary">GENERAR ENLACE DE RECUPERACIÓN</button>
    </form>
    <?php endif; ?>

    <div class="back-link">
        <a href="index.php">← Volver al inicio de sesión</a>
    </div>
</div>
</body>
</html>
