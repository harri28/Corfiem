<?php
// ============================================================
//  reset_password.php — Formulario para establecer nueva contraseña
// ============================================================
require_once __DIR__ . '/config/db.php';

session_name(SESSION_NAME);
session_start();
if (!empty($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$token  = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error        = '';
$token_valido = false;
$registro = null;

// Validar token
if ($token !== '') {
    $registro = db_fetch_one(
        "SELECT pr.*, u.id AS usuario_id, u.nombre, u.apellido
           FROM password_resets pr
           JOIN usuarios u ON u.email = pr.email AND u.activo = TRUE
          WHERE pr.token = ?
            AND pr.usado  = FALSE
            AND pr.expira_at > NOW()
          LIMIT 1",
        [$token]
    );
    $token_valido = (bool)$registro;
}

// Procesar nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido) {
    $nueva      = $_POST['password']  ?? '';
    $confirmar  = $_POST['password2'] ?? '';

    if (strlen($nueva) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($nueva !== $confirmar) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $hash = password_hash($nueva, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

        db_execute(
            "UPDATE usuarios SET password_hash = ?, updated_at = NOW() WHERE id = ?",
            [$hash, (int)$registro['usuario_id']]
        );

        db_execute(
            "UPDATE password_resets SET usado = TRUE WHERE token = ?",
            [$token]
        );

        audit_log(
            (int)$registro['usuario_id'],
            'UPDATE',
            'auth',
            (int)$registro['usuario_id'],
            ['accion' => 'password_reset_solicitado'],
            ['accion' => 'password_reset_completado']
        );

        header('Location: ' . APP_URL . '/index.php?reset=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Contraseña — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body.login-page {
            background:#F5F5F5; display:flex; align-items:center;
            justify-content:center; min-height:100vh; margin:0;
        }
        .recovery-card {
            background:#fff; border-radius:4px;
            box-shadow:0 2px 24px rgba(0,0,0,.10);
            padding:52px 48px; width:100%; max-width:440px;
        }
        .logo { display:flex; align-items:center; gap:12px; margin-bottom:36px; }
        .logo-icon {
            width:40px; height:40px; background:#0D1B2A; border-radius:6px;
            display:flex; align-items:center; justify-content:center;
        }
        .logo-icon svg { width:22px; height:22px; fill:#fff; }
        .logo-name { font-size:18px; font-weight:700; color:#0D1B2A; }
        .logo-sub  { font-size:11px; color:#6B7280; letter-spacing:.06em; text-transform:uppercase; }
        h3 { font-size:22px; font-weight:700; color:#0D1B2A; margin-bottom:6px; }
        p.sub { font-size:13px; color:#6B7280; margin-bottom:28px; line-height:1.5; }

        .form-field { margin-bottom:20px; }
        .form-field label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px; }
        .form-field input {
            width:100%; padding:11px 14px; border:1.5px solid #D1D5DB; border-radius:4px;
            font-size:14px; font-family:'Inter',sans-serif; color:#0D1B2A;
            background:#FAFAFA; transition:border-color .2s, box-shadow .2s; box-sizing:border-box;
        }
        .form-field input:focus {
            outline:none; border-color:#1B3A6B;
            box-shadow:0 0 0 3px rgba(27,58,107,.08); background:#fff;
        }
        .password-wrap { position:relative; }
        .password-wrap input { padding-right:44px; }
        .toggle-pw {
            position:absolute; right:10px; top:50%; transform:translateY(-50%);
            background:none; border:none; cursor:pointer; padding:4px;
            color:#9CA3AF; transition:color .2s;
        }
        .toggle-pw:hover { color:#374151; }
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
            padding:10px 14px; border-radius:4px; font-size:13px; margin-bottom:20px;
        }
        .alert-warning {
            background:#FFFBEB; border:1px solid #FDE68A; color:#92400E;
            padding:10px 14px; border-radius:4px; font-size:13px; margin-bottom:20px;
        }
        .hint { font-size:11px; color:#9CA3AF; margin-top:4px; }
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

    <h3>Nueva Contraseña</h3>

    <?php if (!$token_valido): ?>
        <div class="alert-warning">
            ⚠ El enlace de recuperación es inválido, ya fue usado o expiró.<br>
            Solicita uno nuevo desde la página de recuperación.
        </div>
        <div class="back-link" style="margin-top:0;">
            <a href="forgot_password.php">← Solicitar nuevo enlace</a>
        </div>

    <?php else: ?>
        <p class="sub">
            Hola, <strong><?= htmlspecialchars($registro['nombre'] . ' ' . $registro['apellido']) ?></strong>.
            Establece tu nueva contraseña.
        </p>

        <?php if ($error): ?>
            <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="reset_password.php" novalidate>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="form-field">
                <label for="password">Nueva Contraseña</label>
                <div class="password-wrap">
                    <input type="password" id="password" name="password"
                           placeholder="Mínimo 8 caracteres" required autofocus>
                    <button type="button" class="toggle-pw" onclick="togglePw('password','ico1')">
                        <svg id="ico1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                <p class="hint">Al menos 8 caracteres.</p>
            </div>

            <div class="form-field">
                <label for="password2">Confirmar Contraseña</label>
                <div class="password-wrap">
                    <input type="password" id="password2" name="password2"
                           placeholder="Repite la contraseña" required>
                    <button type="button" class="toggle-pw" onclick="togglePw('password2','ico2')">
                        <svg id="ico2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-primary">GUARDAR NUEVA CONTRASEÑA</button>
        </form>
    <?php endif; ?>

    <div class="back-link">
        <a href="index.php">← Volver al inicio de sesión</a>
    </div>
</div>

<script>
function togglePw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    icon.innerHTML = isHidden
        ? `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`
        : `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
}
</script>
</body>
</html>
