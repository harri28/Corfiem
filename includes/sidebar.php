<?php
// ============================================================
//  includes/sidebar.php
//  Uso: include __DIR__ . '/../includes/sidebar.php';
//  Requiere que $page_active esté definido en cada módulo
//  Ej: $page_active = 'proyectos';
// ============================================================
$page_active = $page_active ?? '';
$user        = $_SESSION ?? [];
$initials    = $user['avatar_initials'] ?? 'U';
$username    = $user['usuario_nombre']  ?? 'Usuario';
$rol         = $user['usuario_rol']     ?? '';
?>
<aside class="sidebar" id="sidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
        <div class="sidebar-logo-icon">
            <svg viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 3h7v7H3V3zm11 0h7v7h-7V3zM3 14h7v7H3v-7zm14 0v2h-3v2h3v2h2v-2h3v-2h-3v-2h-2z"/>
            </svg>
        </div>
        <div class="sidebar-logo-text">
            <div class="sidebar-logo-name">CORFIEM</div>
            <div class="sidebar-logo-sub">Sistema ERP</div>
        </div>
    </div>

    <div class="sidebar-section">
        <!-- Principal -->
        <div class="sidebar-section-label">Principal</div>
        <ul class="sidebar-nav">
            <li>
                <a href="<?= APP_URL ?>/dashboard.php"
                   class="<?= $page_active === 'dashboard' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/>
                        <rect x="14" y="3" width="7" height="7"/>
                        <rect x="3" y="14" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/>
                    </svg>
                    Dashboard
                </a>
            </li>
        </ul>

        <!-- LEADS -->
        <div class="sidebar-section-label" style="margin-top:12px;">Leads</div>
        <ul class="sidebar-nav">
            <li>
                <a href="<?= APP_URL ?>/modules/previas.php"
                   class="<?= $page_active === 'previas' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    Actividades Previas
                </a>
            </li>
        </ul>

        <!-- GESTIÓN -->
        <div class="sidebar-section-label" style="margin-top:12px;">Gestión</div>
        <ul class="sidebar-nav">
            <li>
                <a href="<?= APP_URL ?>/modules/crm.php"
                   class="<?= $page_active === 'clientes' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    CRM — Clientes
                </a>
            </li>
            <li>
                <a href="<?= APP_URL ?>/modules/proyectos.php"
                   class="<?= $page_active === 'proyectos' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    </svg>
                    Proyectos
                </a>
            </li>
            <li>
                <a href="<?= APP_URL ?>/modules/marcha.php"
                   class="<?= $page_active === 'marcha' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"/>
                        <polyline points="2 17 12 22 22 17"/>
                        <polyline points="2 12 12 17 22 12"/>
                    </svg>
                    Puesta en Marcha
                </a>
            </li>
        </ul>

        <!-- Análisis -->
        <div class="sidebar-section-label" style="margin-top:12px;">Análisis</div>
        <ul class="sidebar-nav">
            <li>
                <a href="<?= APP_URL ?>/modules/bi.php"
                   class="<?= $page_active === 'bi' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="20" x2="18" y2="10"/>
                        <line x1="12" y1="20" x2="12" y2="4"/>
                        <line x1="6"  y1="20" x2="6"  y2="14"/>
                    </svg>
                    Reportes BI
                </a>
            </li>
            <li>
                <a href="<?= APP_URL ?>/modules/capacitacion.php"
                   class="<?= $page_active === 'capacitacion' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                    </svg>
                    Capacitación
                </a>
            </li>
        </ul>

        <!-- Sistema -->
        <div class="sidebar-section-label" style="margin-top:12px;">Sistema</div>
        <ul class="sidebar-nav">
            <li>
                <a href="<?= APP_URL ?>/modules/auditoria.php"
                   class="<?= $page_active === 'auditoria' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    Seguridad y Auditoría
                </a>
            </li>
        </ul>
    </div><!-- .sidebar-section -->

    <!-- Usuario / Logout -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar"><?= htmlspecialchars($initials) ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= htmlspecialchars($username) ?></div>
                <div class="sidebar-user-role"><?= htmlspecialchars($rol) ?></div>
            </div>
            <a href="<?= APP_URL ?>/logout.php" class="sidebar-logout" title="Cerrar sesión">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16,17 21,12 16,7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </a>
        </div>
    </div>

</aside>