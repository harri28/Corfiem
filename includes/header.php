<?php
// ============================================================
//  includes/header.php
//  Uso: include __DIR__ . '/../includes/header.php';
//
//  Variables que deben estar definidas antes de incluir:
//    $page_title  (string) — título de la pestaña del navegador
//    $page_active (string) — módulo activo para el sidebar
//                            'dashboard' | 'proyectos' | 'crm'
//                            'previas' | 'marcha' | 'bi'
//                            'capacitacion' | 'auditoria'
// ============================================================
$page_title  = $page_title  ?? APP_NAME;
$page_active = $page_active ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($page_title) ?> — <?= APP_NAME ?></title>

    <!-- Fuente corporativa -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
          rel="stylesheet">

    <!-- Estilos corporativos -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app-layout">

<?php
// El sidebar se incluye aquí automáticamente
include __DIR__ . '/sidebar.php';
?>

<!-- ============================================================
     includes/topbar.php (función inline — no requiere archivo
     separado, se llama como render_topbar() en cada módulo)
     ============================================================ -->
<?php
/**
 * Renderiza la barra superior fija de cada página
 *
 * @param string $title    — Título principal de la página
 * @param string $subtitle — Texto secundario (opcional)
 */
function render_topbar(string $title, string $subtitle = ''): void { ?>
    <header class="top-header">

        <!-- Botón hamburguesa (solo mobile) -->
        <button class="btn-icon" id="hamburgerBtn"
                style="display:none;" title="Menú"
                onclick="document.getElementById('sidebar').classList.toggle('open')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="6"  x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        <!-- Título -->
        <div class="top-header-title">
            <?= htmlspecialchars($title) ?>
            <?php if ($subtitle): ?>
                <span><?= htmlspecialchars($subtitle) ?></span>
            <?php endif; ?>
        </div>

        <!-- Acciones del header -->
        <div class="header-actions">

            <!-- Fecha actual -->
            <span style="font-size:12px;color:var(--c-text-3);margin-right:4px;">
                <?= date('d/m/Y') ?>
            </span>

            <!-- Botón de notificaciones -->
            <button class="btn-icon" title="Notificaciones">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
            </button>

            <!-- Separador -->
            <div style="width:1px;height:20px;background:var(--c-border);margin:0 4px;"></div>

            <!-- Avatar del usuario -->
            <div class="sidebar-avatar" style="width:30px;height:30px;font-size:11px;cursor:default;"
                 title="<?= htmlspecialchars($_SESSION['usuario_nombre'] ?? '') ?>">
                <?= htmlspecialchars($_SESSION['avatar_initials'] ?? 'U') ?>
            </div>

        </div>
    </header>

    <!-- Mostrar hamburguesa solo en mobile via media query -->
    <style>
        @media (max-width: 1024px) {
            #hamburgerBtn { display:flex !important; }
        }
    </style>
<?php }


// ============================================================
//  includes/footer.php (función inline)
//  Llamar render_footer() AL FINAL de cada módulo
// ============================================================
/**
 * Cierra el layout y carga los scripts globales
 */
function render_footer(): void { ?>
    </div><!-- /.main-content -->
</div><!-- /.app-layout -->

<!-- Contenedor de toasts -->
<div id="toast-container"></div>

<!-- Script global -->
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
<?php }