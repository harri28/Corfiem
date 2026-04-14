<?php
// ============================================================
//  modules/auditoria.php — Seguridad y Auditoría
//  Ruta: C:\xampp\htdocs\Corfiem_Cesar\modules\auditoria.php
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session     = require_auth();
$uid         = (int)$session['usuario_id'];
$page_title  = 'Seguridad y Auditoría';
$page_active = 'auditoria';

// Solo administradores pueden gestionar usuarios
$es_admin = $session['usuario_rol'] === 'Admin';

// ── Filtros del log ───────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if (!empty($_GET['usuario_id'])) {
    $where[]  = 'a.usuario_id = ?';
    $params[] = (int)$_GET['usuario_id'];
}
if (!empty($_GET['modulo'])) {
    $where[]  = 'a.modulo = ?';
    $params[] = $_GET['modulo'];
}
if (!empty($_GET['accion'])) {
    $where[]  = 'a.accion = ?';
    $params[] = strtoupper($_GET['accion']);
}
if (!empty($_GET['fecha_desde'])) {
    $where[]  = 'a.created_at >= ?';
    $params[] = $_GET['fecha_desde'] . ' 00:00:00';
}
if (!empty($_GET['fecha_hasta'])) {
    $where[]  = 'a.created_at <= ?';
    $params[] = $_GET['fecha_hasta'] . ' 23:59:59';
}
if (!empty($_GET['q'])) {
    $where[]  = '(u.nombre ILIKE ? OR u.email ILIKE ? OR a.modulo ILIKE ?)';
    $q        = '%' . trim($_GET['q']) . '%';
    $params   = array_merge($params, [$q, $q, $q]);
}

// ── Log de auditoría (paginado) ───────────────────────────────
$pagina   = max(1, (int)($_GET['page'] ?? 1));
$por_pag  = 25;
$offset   = ($pagina - 1) * $por_pag;

$total_log = db_fetch_one(
    "SELECT COUNT(*) AS total FROM auditoria_log a
     LEFT JOIN usuarios u ON a.usuario_id = u.id
     WHERE " . implode(' AND ', $where),
    $params
)['total'];

$logs = db_fetch_all(
    "SELECT a.id, a.accion, a.modulo, a.registro_id,
            a.datos_antes, a.datos_despues,
            a.ip_address, a.created_at,
            u.nombre || ' ' || u.apellido AS usuario,
            u.email
     FROM auditoria_log a
     LEFT JOIN usuarios u ON a.usuario_id = u.id
     WHERE " . implode(' AND ', $where) .
    " ORDER BY a.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$por_pag, $offset])
);

$total_pags = (int)ceil($total_log / $por_pag);

// ── Usuarios del sistema ──────────────────────────────────────
$usuarios = db_fetch_all(
    "SELECT u.id, u.nombre, u.apellido, u.email, u.rol,
            u.activo, u.ultimo_acceso, u.created_at
     FROM usuarios u
     ORDER BY u.nombre"
);

// Roles predefinidos del sistema (ya no hay tabla roles)
$roles = [
    ['id' => 'Admin', 'nombre' => 'Admin'],
    ['id' => 'Gerente', 'nombre' => 'Gerente'],
    ['id' => 'Usuario', 'nombre' => 'Usuario'],
    ['id' => 'Consultor', 'nombre' => 'Consultor']
];

// ── KPIs de seguridad ─────────────────────────────────────────
$kpi = db_fetch_one(
    "SELECT
        (SELECT COUNT(*) FROM usuarios WHERE activo = TRUE)         AS usuarios_activos,
        (SELECT COUNT(*) FROM auditoria_log
         WHERE created_at >= CURRENT_DATE)                          AS acciones_hoy,
        (SELECT COUNT(*) FROM auditoria_log
         WHERE accion = 'LOGIN' AND created_at >= CURRENT_DATE)     AS logins_hoy,
        0                                                             AS sesiones_activas,
        (SELECT COUNT(*) FROM auditoria_log
         WHERE accion = 'DELETE' AND created_at >= CURRENT_DATE)    AS eliminaciones_hoy,
        (SELECT COUNT(*) FROM auditoria_log
         WHERE created_at >= NOW() - INTERVAL '7 days')             AS acciones_semana"
);

// ── Módulos y acciones únicos para filtros ────────────────────
$modulos_unicos = db_fetch_all(
    "SELECT DISTINCT modulo FROM auditoria_log WHERE modulo IS NOT NULL ORDER BY modulo"
);
$acciones_unicas = db_fetch_all(
    "SELECT DISTINCT accion FROM auditoria_log ORDER BY accion"
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
<?php render_topbar('Seguridad y Auditoría', 'Control de accesos y trazabilidad'); ?>

<div class="page-body">

    <!-- ── KPIs ─────────────────────────────────────────────── -->
    <div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
                                  margin-bottom:24px;">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:var(--c-success-lt);">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--c-success)" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $kpi['usuarios_activos'] ?></div>
            <div class="kpi-label">Usuarios Activos</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12,6 12,12 16,14"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $kpi['sesiones_activas'] ?></div>
            <div class="kpi-label">Sesiones Activas</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                    <polyline points="10,17 15,12 10,7"/>
                    <line x1="15" y1="12" x2="3" y2="12"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $kpi['logins_hoy'] ?></div>
            <div class="kpi-label">Logins Hoy</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $kpi['acciones_hoy'] ?></div>
            <div class="kpi-label">Acciones Hoy</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $kpi['acciones_semana'] ?></div>
            <div class="kpi-label">Acciones (7 días)</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:<?= $kpi['eliminaciones_hoy'] > 0 ? 'var(--c-danger-lt)' : 'var(--c-success-lt)' ?>;">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="<?= $kpi['eliminaciones_hoy'] > 0 ? 'var(--c-danger)' : 'var(--c-success)' ?>"
                     stroke-width="2">
                    <polyline points="3,6 5,6 21,6"/>
                    <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6m3,0V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2v2"/>
                </svg>
            </div>
            <div class="kpi-value"
                 style="color:<?= $kpi['eliminaciones_hoy'] > 0 ? 'var(--c-danger)' : 'var(--c-success)' ?>;">
                <?= $kpi['eliminaciones_hoy'] ?>
            </div>
            <div class="kpi-label">Eliminaciones Hoy</div>
        </div>
    </div>

    <!-- ── Gestión de Usuarios ───────────────────────────────── -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
            <span class="card-title">👥 Gestión de Usuarios y Permisos</span>
            <?php if ($es_admin): ?>
            <button class="btn btn-primary btn-sm" onclick="openModal('modalNuevoUsuario')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     style="width:14px;height:14px;">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5"  y1="12" x2="19" y2="12"/>
                </svg>
                Nuevo Usuario
            </button>
            <?php endif; ?>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Último Acceso</th>
                        <th>Miembro desde</th>
                        <th>Estado</th>
                        <?php if ($es_admin): ?><th>Acciones</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="sidebar-avatar" style="width:32px;height:32px;font-size:11px;
                                         background:var(--c-navy);flex-shrink:0;">
                                <?= strtoupper(substr($u['nombre'],0,1) . substr($u['apellido'],0,1)) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:13.5px;">
                                    <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?>
                                    <?php if ($u['id'] == $uid): ?>
                                        <span style="font-size:10px;color:var(--c-accent);
                                                     margin-left:4px;">(Tú)</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:11.5px;color:var(--c-text-3);">
                                    <?= htmlspecialchars($u['email']) ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-navy"><?= htmlspecialchars($u['rol'] ?? '—') ?></span>
                    </td>
                    <td style="font-size:12px;color:var(--c-text-3);">
                        <?= $u['ultimo_acceso']
                            ? date('d/m/Y H:i', strtotime($u['ultimo_acceso']))
                            : 'Nunca' ?>
                    </td>
                    <td style="font-size:12px;color:var(--c-text-3);">
                        <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                    </td>
                    <td>
                        <span class="badge badge-dot <?= $u['activo'] ? 'badge-active' : 'badge-cancelled' ?>">
                            <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </td>
                    <?php if ($es_admin): ?>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <button class="btn btn-secondary btn-sm"
                                    onclick="editarUsuario(<?= $u['id'] ?>)">
                                Editar
                            </button>
                            <?php if ($u['id'] != $uid): ?>
                            <button class="btn btn-sm"
                                    style="background:<?= $u['activo'] ? 'var(--c-danger)':'var(--c-success)' ?>;color:#fff;"
                                    onclick="toggleUsuario(<?= $u['id'] ?>, <?= $u['activo'] ? 'false':'true' ?>)">
                                <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                            <button class="btn btn-sm"
                                    style="background:#7F1D1D;color:#fff;border-color:#7F1D1D;"
                                    onclick="confirmarEliminarUsuario(<?= $u['id'] ?>,
                                    '<?= htmlspecialchars(addslashes($u['nombre'] . ' ' . $u['apellido'])) ?>')">
                                🗑
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── Log de Auditoría ──────────────────────────────────── -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">📋 Registro de Auditoría</span>
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:12px;color:var(--c-text-3);">
                    <?= number_format($total_log) ?> registros
                </span>
                <button class="btn btn-secondary btn-sm" onclick="exportarLog()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         style="width:13px;height:13px;">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7,10 12,15 17,10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Exportar CSV
                </button>
            </div>
        </div>

        <!-- Filtros del log -->
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;
                                   padding:0 0 16px;border-bottom:1px solid var(--c-border);
                                   margin-bottom:16px;">
            <div class="search-wrapper" style="flex:1;min-width:180px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input class="search-input" type="text" name="q"
                       placeholder="Buscar usuario o módulo..."
                       value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
            </div>
            <select name="usuario_id" class="form-control" style="width:170px;">
                <option value="">Todos los usuarios</option>
                <?php foreach ($usuarios as $u): ?>
                    <option value="<?= $u['id'] ?>"
                        <?= ($_GET['usuario_id']??'') == $u['id'] ? 'selected':'' ?>>
                        <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="modulo" class="form-control" style="width:140px;">
                <option value="">Módulo</option>
                <?php foreach ($modulos_unicos as $m): ?>
                    <option value="<?= $m['modulo'] ?>"
                        <?= ($_GET['modulo']??'') === $m['modulo'] ? 'selected':'' ?>>
                        <?= htmlspecialchars($m['modulo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="accion" class="form-control" style="width:130px;">
                <option value="">Acción</option>
                <?php foreach ($acciones_unicas as $a): ?>
                    <option value="<?= $a['accion'] ?>"
                        <?= ($_GET['accion']??'') === $a['accion'] ? 'selected':'' ?>>
                        <?= htmlspecialchars($a['accion']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input class="form-control" type="date" name="fecha_desde" style="width:145px;"
                   value="<?= htmlspecialchars($_GET['fecha_desde'] ?? '') ?>"
                   title="Desde">
            <input class="form-control" type="date" name="fecha_hasta" style="width:145px;"
                   value="<?= htmlspecialchars($_GET['fecha_hasta'] ?? '') ?>"
                   title="Hasta">
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            <a href="auditoria.php" class="btn btn-secondary btn-sm">Limpiar</a>
        </form>

        <!-- Tabla del log -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha / Hora</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Módulo</th>
                        <th>IP</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:30px;color:var(--c-text-3);">
                            No se encontraron registros con los filtros aplicados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log):
                        $accionColor = match($log['accion']) {
                            'CREATE' => '#059669',
                            'UPDATE' => '#D97706',
                            'DELETE' => '#DC2626',
                            'LOGIN'  => '#1B3A6B',
                            'LOGOUT' => '#6B7280',
                            default  => '#374151',
                        };
                        $accionBg = match($log['accion']) {
                            'CREATE' => '#ECFDF5',
                            'UPDATE' => '#FFFBEB',
                            'DELETE' => '#FEF2F2',
                            'LOGIN'  => '#EFF3FB',
                            'LOGOUT' => '#F3F4F6',
                            default  => '#F9FAFB',
                        };
                        // Datos del detalle
                        $antes   = $log['datos_antes']  ? json_decode($log['datos_antes'],  true) : [];
                        $despues = $log['datos_despues'] ? json_decode($log['datos_despues'], true) : [];
                        $tiene_detalle = !empty($antes) || !empty($despues);
                    ?>
                    <tr>
                        <td style="font-size:11px;color:var(--c-text-4);font-family:monospace;">
                            #<?= $log['id'] ?>
                        </td>
                        <td style="font-size:12px;white-space:nowrap;">
                            <div><?= date('d/m/Y', strtotime($log['created_at'])) ?></div>
                            <div style="color:var(--c-text-3);">
                                <?= date('H:i:s', strtotime($log['created_at'])) ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:13px;font-weight:500;">
                                <?= htmlspecialchars($log['usuario'] ?? '—') ?>
                            </div>
                            <div style="font-size:11px;color:var(--c-text-3);">
                                <?= htmlspecialchars($log['email'] ?? '') ?>
                            </div>
                        </td>
                        <td>
                            <span style="display:inline-flex;align-items:center;
                                         padding:3px 10px;border-radius:20px;
                                         font-size:11.5px;font-weight:700;
                                         background:<?= $accionBg ?>;
                                         color:<?= $accionColor ?>;">
                                <?= htmlspecialchars($log['accion']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-navy" style="font-size:11px;">
                                <?= htmlspecialchars($log['modulo'] ?? '—') ?>
                            </span>
                            <?php if ($log['registro_id']): ?>
                                <span style="font-size:10.5px;color:var(--c-text-4);
                                             margin-left:4px;">#<?= $log['registro_id'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:11.5px;font-family:monospace;color:var(--c-text-3);">
                            <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
                        </td>
                        <td>
                            <?php if ($tiene_detalle): ?>
                            <button class="btn btn-secondary btn-sm"
                                    onclick='verDetalle(<?= htmlspecialchars(json_encode([
                                        "antes"   => $antes,
                                        "despues" => $despues,
                                        "accion"  => $log['accion'],
                                        "modulo"  => $log['modulo'],
                                    ]), ENT_QUOTES) ?>)'>
                                Ver
                            </button>
                            <?php else: ?>
                                <span style="color:var(--c-text-4);font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($total_pags > 1): ?>
        <div style="display:flex;justify-content:center;gap:6px;margin-top:20px;flex-wrap:wrap;">
            <?php
            $q_params = $_GET;
            for ($i = 1; $i <= $total_pags; $i++):
                $q_params['page'] = $i;
                $url = '?' . http_build_query($q_params);
            ?>
                <a href="<?= $url ?>"
                   style="padding:7px 13px;border-radius:var(--radius);font-size:13px;
                          text-decoration:none;
                          <?= $i === $pagina
                              ? 'background:var(--c-primary);color:#fff;font-weight:600;'
                              : 'background:var(--c-surface);border:1px solid var(--c-border);color:var(--c-text-2);' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

</div><!-- .page-body -->
</div><!-- .main-content -->

<!-- ============================================================
     MODAL: Nuevo / Editar Usuario
     ============================================================ -->
<div class="modal-overlay" id="modalNuevoUsuario">
    <div class="modal-box" style="max-width:680px;">
        <div class="modal-header">
            <span class="modal-title" id="modalUsuarioTitulo">Nuevo Usuario</span>
            <button class="modal-close" onclick="closeModal('modalNuevoUsuario')">×</button>
        </div>
        <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
            <input type="hidden" id="usu_id">

            <!-- Sección: Datos Personales -->
            <div style="font-size:11px;font-weight:700;color:var(--c-text-3);text-transform:uppercase;
                        letter-spacing:.07em;margin-bottom:12px;">👤 Datos Personales</div>
            <div class="form-grid" style="margin-bottom:20px;">
                <div class="form-group">
                    <label class="form-label">Nombre <span class="required">*</span></label>
                    <input class="form-control" type="text" id="usu_nombre" placeholder="Juan">
                </div>
                <div class="form-group">
                    <label class="form-label">Apellido <span class="required">*</span></label>
                    <input class="form-control" type="text" id="usu_apellido" placeholder="Pérez">
                </div>
                <div class="form-group">
                    <label class="form-label">DNI / Documento</label>
                    <input class="form-control" type="text" id="usu_dni" placeholder="12345678">
                </div>
                <div class="form-group">
                    <label class="form-label">Teléfono</label>
                    <input class="form-control" type="tel" id="usu_telefono" placeholder="+51 999 999 999">
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Email <span class="required">*</span></label>
                    <input class="form-control" type="email" id="usu_email" placeholder="usuario@corfiem.com">
                </div>
            </div>

            <!-- Sección: Datos Laborales -->
            <div style="font-size:11px;font-weight:700;color:var(--c-text-3);text-transform:uppercase;
                        letter-spacing:.07em;margin-bottom:12px;border-top:1px solid var(--c-border);padding-top:16px;">
                💼 Datos Laborales
            </div>
            <div class="form-grid" style="margin-bottom:20px;">
                <div class="form-group">
                    <label class="form-label">Cargo / Posición</label>
                    <input class="form-control" type="text" id="usu_cargo" placeholder="Especialista en Proyectos">
                </div>
                <div class="form-group">
                    <label class="form-label">Especialidad</label>
                    <input class="form-control" type="text" id="usu_especialidad" placeholder="Gestión de Proyectos, ISO...">
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Rol en el Sistema <span class="required">*</span></label>
                    <select class="form-control" id="usu_rol">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint">Define los permisos de acceso del usuario en la plataforma.</span>
                </div>
            </div>

            <!-- Sección: Acceso -->
            <div style="font-size:11px;font-weight:700;color:var(--c-text-3);text-transform:uppercase;
                        letter-spacing:.07em;margin-bottom:12px;border-top:1px solid var(--c-border);padding-top:16px;">
                🔐 Acceso al Sistema
            </div>
            <div class="form-grid" style="margin-bottom:20px;">
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">
                        Contraseña <span class="required" id="pass_required">*</span>
                    </label>
                    <input class="form-control" type="password" id="usu_password"
                           placeholder="Mínimo 8 caracteres">
                    <span class="form-hint" id="pass_hint" style="display:none;">
                        Dejar vacío para mantener la contraseña actual.
                    </span>
                </div>
            </div>

            <!-- Sección: Curriculum -->
            <div style="font-size:11px;font-weight:700;color:var(--c-text-3);text-transform:uppercase;
                        letter-spacing:.07em;margin-bottom:12px;border-top:1px solid var(--c-border);padding-top:16px;">
                📄 Currículum Vitae
            </div>
            <div id="cvExistente" style="display:none;background:#EFF6FF;border:1px solid #BFDBFE;
                        border-radius:8px;padding:12px 16px;margin-bottom:12px;
                        align-items:center;gap:12px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="#1D4ED8" stroke-width="2"
                     style="width:24px;height:24px;flex-shrink:0;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14,2 14,8 20,8"/>
                </svg>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                         id="cvNombreActual"></div>
                    <div style="font-size:11.5px;color:#1D4ED8;">CV adjunto</div>
                </div>
                <a id="cvVerLink" href="#" target="_blank"
                   class="btn btn-secondary btn-sm" style="flex-shrink:0;font-size:12px;">Ver</a>
                <button class="btn btn-sm" style="flex-shrink:0;background:#DC2626;color:#fff;border-color:#DC2626;font-size:12px;"
                        onclick="eliminarCvUsuario()">Quitar</button>
            </div>
            <label for="inputCv"
                   style="display:flex;flex-direction:column;align-items:center;gap:6px;
                          border:2px dashed var(--c-border);border-radius:8px;padding:20px;
                          cursor:pointer;transition:border-color .2s;"
                   id="cvDropzone"
                   onmouseover="this.style.borderColor='var(--c-primary)'"
                   onmouseout="this.style.borderColor='var(--c-border)'">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--c-text-3)" stroke-width="1.5"
                     style="width:32px;height:32px;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14,2 14,8 20,8"/>
                    <line x1="12" y1="18" x2="12" y2="12"/>
                    <line x1="9"  y1="15" x2="15" y2="15"/>
                </svg>
                <span style="font-size:13px;color:var(--c-text-2);" id="cvDropLabel">
                    Haz clic para adjuntar el CV (PDF)
                </span>
                <span style="font-size:11.5px;color:var(--c-text-4);">Solo PDF · Máx. 10 MB</span>
                <input type="file" id="inputCv" accept=".pdf" style="display:none;"
                       onchange="previewCv(this)">
            </label>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalNuevoUsuario')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarUsuario()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     style="width:14px;height:14px;">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"/>
                    <polyline points="17,21 17,13 7,13"/>
                </svg>
                Guardar Usuario
            </button>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL: Confirmar Eliminación de Usuario
     ============================================================ -->
<div class="modal-overlay" id="modalEliminarUsuario">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header" style="border-bottom:2px solid #DC2626;">
            <span class="modal-title" style="color:#DC2626;">⚠️ Eliminar Usuario</span>
            <button class="modal-close" onclick="closeModal('modalEliminarUsuario')">×</button>
        </div>
        <div class="modal-body">
            <p style="font-size:13.5px;color:var(--c-text);margin:0 0 12px;">
                Estás a punto de eliminar permanentemente al usuario:
            </p>
            <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;
                        padding:12px 16px;margin-bottom:16px;">
                <div style="font-weight:700;font-size:14px;color:#DC2626;" id="elimUsuarioNombre"></div>
                <div style="font-size:12px;color:#991B1B;margin-top:4px;">
                    Se eliminarán todos sus registros de acceso y actividad.
                </div>
            </div>
            <p style="font-size:12.5px;color:var(--c-text-3);margin:0 0 16px;">
                Esta acción <strong>no se puede deshacer</strong>.
            </p>
            <div class="form-group" style="margin:0;">
                <label class="form-label">
                    Escribe <strong id="elimUsuarioNombreConfirm" style="color:#DC2626;"></strong> para confirmar:
                </label>
                <input class="form-control" type="text" id="inputConfirmUsuario"
                       placeholder="Escribe el nombre completo..."
                       oninput="validarConfirmUsuario()">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalEliminarUsuario')">
                Cancelar
            </button>
            <button class="btn btn-sm" id="btnConfirmEliminarUsuario" disabled
                    style="background:#DC2626;color:#fff;border-color:#DC2626;
                           opacity:0.4;cursor:not-allowed;padding:8px 18px;"
                    onclick="ejecutarEliminarUsuario()">
                🗑 Eliminar definitivamente
            </button>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL: Detalle del registro de auditoría
     ============================================================ -->
<div class="modal-overlay" id="modalDetalle">
    <div class="modal-box" style="max-width:620px;">
        <div class="modal-header">
            <span class="modal-title">Detalle del Registro</span>
            <button class="modal-close" onclick="closeModal('modalDetalle')">×</button>
        </div>
        <div class="modal-body" id="modalDetalleBody"></div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
// ── Ver detalle de cambio ─────────────────────────────────────
function verDetalle(data) {
    const fmt = obj => {
        if (!obj || Object.keys(obj).length === 0) return '<em style="color:var(--c-text-4);">Sin datos</em>';
        return Object.entries(obj).map(([k,v]) =>
            `<div style="display:flex;gap:8px;padding:5px 0;border-bottom:1px solid #F3F4F6;">
                <span style="font-weight:600;min-width:140px;color:var(--c-text-2);font-size:12px;">
                    ${escHtml(k)}
                </span>
                <span style="font-size:12px;color:var(--c-text-1);word-break:break-all;">
                    ${v !== null && v !== undefined ? escHtml(String(v)) : '<em style="color:var(--c-text-4);">null</em>'}
                </span>
            </div>`
        ).join('');
    };

    document.getElementById('modalDetalleBody').innerHTML = `
        <div style="display:flex;gap:10px;margin-bottom:16px;">
            <span class="badge badge-navy">${escHtml(data.modulo ?? '—')}</span>
            <span class="badge" style="background:#F3F4F6;color:var(--c-text-1);">
                ${escHtml(data.accion ?? '—')}
            </span>
        </div>
        ${data.antes && Object.keys(data.antes).length > 0 ? `
        <div style="margin-bottom:16px;">
            <div style="font-size:12px;font-weight:700;color:var(--c-danger);
                        margin-bottom:8px;text-transform:uppercase;letter-spacing:0.05em;">
                Antes del cambio
            </div>
            <div style="background:#FEF2F2;padding:12px;border-radius:var(--radius);
                        border:1px solid #FECACA;">
                ${fmt(data.antes)}
            </div>
        </div>` : ''}
        ${data.despues && Object.keys(data.despues).length > 0 ? `
        <div>
            <div style="font-size:12px;font-weight:700;color:var(--c-success);
                        margin-bottom:8px;text-transform:uppercase;letter-spacing:0.05em;">
                Después del cambio
            </div>
            <div style="background:var(--c-success-lt);padding:12px;border-radius:var(--radius);
                        border:1px solid #A7F3D0;">
                ${fmt(data.despues)}
            </div>
        </div>` : ''}
    `;
    openModal('modalDetalle');
}

// ── Guardar usuario ───────────────────────────────────────────
async function guardarUsuario() {
    const id       = document.getElementById('usu_id').value;
    const nombre   = document.getElementById('usu_nombre').value.trim();
    const apellido = document.getElementById('usu_apellido').value.trim();
    const email    = document.getElementById('usu_email').value.trim();
    const password = document.getElementById('usu_password').value;

    if (!nombre || !apellido || !email) {
        showToast('Campos requeridos', 'Nombre, apellido y email son obligatorios.', 'error');
        return;
    }
    if (!id && password.length < 8) {
        showToast('Contraseña inválida', 'La contraseña debe tener mínimo 8 caracteres.', 'error');
        return;
    }

    const fd = new FormData();
    fd.append('action',       id ? 'update' : 'create');
    fd.append('id',           id);
    fd.append('nombre',       nombre);
    fd.append('apellido',     apellido);
    fd.append('email',        email);
    fd.append('rol',          document.getElementById('usu_rol').value);
    fd.append('dni',          document.getElementById('usu_dni').value.trim());
    fd.append('telefono',     document.getElementById('usu_telefono').value.trim());
    fd.append('cargo',        document.getElementById('usu_cargo').value.trim());
    fd.append('especialidad', document.getElementById('usu_especialidad').value.trim());
    if (password) fd.append('password', password);

    const cvFile = document.getElementById('inputCv').files[0];
    if (cvFile) fd.append('cv', cvFile);

    try {
        const res  = await fetch('../api/usuarios_api.php', { method:'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast('Guardado', data.message, 'success');
            closeModal('modalNuevoUsuario');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo conectar con el servidor.', 'error');
    }
}

// ── Cargar usuario para editar ────────────────────────────────
async function editarUsuario(id) {
    try {
        const res  = await fetch(`../api/usuarios_api.php?action=get&id=${id}`);
        const data = await res.json();
        if (!data.success) { showToast('Error', data.message, 'error'); return; }

        const u = data.data;
        document.getElementById('usu_id').value           = u.id;
        document.getElementById('usu_nombre').value       = u.nombre;
        document.getElementById('usu_apellido').value     = u.apellido;
        document.getElementById('usu_email').value        = u.email;
        document.getElementById('usu_rol').value          = u.rol ?? 'Usuario';
        document.getElementById('usu_dni').value          = u.dni ?? '';
        document.getElementById('usu_telefono').value     = u.telefono ?? '';
        document.getElementById('usu_cargo').value        = u.cargo ?? '';
        document.getElementById('usu_especialidad').value = u.especialidad ?? '';
        document.getElementById('usu_password').value     = '';
        document.getElementById('inputCv').value          = '';

        // CV existente
        if (u.cv_path) {
            document.getElementById('cvNombreActual').textContent = u.cv_nombre ?? 'CV adjunto';
            document.getElementById('cvVerLink').href = '../' + u.cv_path;
            document.getElementById('cvExistente').style.display = 'flex';
            document.getElementById('cvDropLabel').textContent   = 'Reemplazar CV (PDF)';
        } else {
            document.getElementById('cvExistente').style.display = 'none';
            document.getElementById('cvDropLabel').textContent   = 'Haz clic para adjuntar el CV (PDF)';
        }

        document.getElementById('pass_required').style.display = 'none';
        document.getElementById('pass_hint').style.display     = 'inline';
        document.getElementById('modalUsuarioTitulo').textContent = 'Editar Usuario';
        openModal('modalNuevoUsuario');
    } catch(e) {
        showToast('Error', 'No se pudo cargar el usuario.', 'error');
    }
}

// ── Preview CV seleccionado ───────────────────────────────────
function previewCv(input) {
    if (!input.files.length) return;
    const file = input.files[0];
    if (!file.name.endsWith('.pdf')) {
        showToast('Formato inválido', 'Solo se permite PDF.', 'error');
        input.value = '';
        return;
    }
    document.getElementById('cvDropLabel').textContent = '✓ ' + file.name;
}

// ── Eliminar CV del usuario en edición ───────────────────────
async function eliminarCvUsuario() {
    const id = document.getElementById('usu_id').value;
    if (!id) return;

    const fd = new FormData();
    fd.append('action', 'delete_cv');
    fd.append('id',     id);

    try {
        const res  = await fetch('../api/usuarios_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('cvExistente').style.display = 'none';
            document.getElementById('cvDropLabel').textContent   = 'Haz clic para adjuntar el CV (PDF)';
            showToast('Eliminado', 'CV eliminado.', 'success');
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo eliminar.', 'error');
    }
}

// ── Activar / Desactivar usuario ──────────────────────────────
async function toggleUsuario(id, activar) {
    const msg = activar
        ? '¿Activar este usuario? Podrá acceder al sistema.'
        : '¿Desactivar este usuario? Perderá acceso al sistema.';

    confirmAction(msg, async () => {
        const fd = new FormData();
        fd.append('action', 'toggle');
        fd.append('id',     id);
        fd.append('activo', activar ? '1' : '0');

        try {
            const res  = await fetch('../api/usuarios_api.php', { method:'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showToast('Actualizado', data.message, 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast('Error', data.message, 'error');
            }
        } catch(e) {
            showToast('Error', 'No se pudo procesar la solicitud.', 'error');
        }
    });
}

// ── Exportar log a CSV ────────────────────────────────────────
function exportarLog() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = '../api/auditoria_api.php?' + params.toString();
}

// ── Eliminar usuario ──────────────────────────────────────────
let elimUsuarioId    = null;
let elimUsuarioTexto = '';

function confirmarEliminarUsuario(id, nombre) {
    elimUsuarioId    = id;
    elimUsuarioTexto = nombre;

    document.getElementById('elimUsuarioNombre').textContent       = nombre;
    document.getElementById('elimUsuarioNombreConfirm').textContent = nombre;
    document.getElementById('inputConfirmUsuario').value           = '';

    const btn = document.getElementById('btnConfirmEliminarUsuario');
    btn.disabled      = true;
    btn.style.opacity = '0.4';
    btn.style.cursor  = 'not-allowed';

    openModal('modalEliminarUsuario');
}

function validarConfirmUsuario() {
    const input = document.getElementById('inputConfirmUsuario').value.trim();
    const btn   = document.getElementById('btnConfirmEliminarUsuario');
    const ok    = input === elimUsuarioTexto;
    btn.disabled      = !ok;
    btn.style.opacity = ok ? '1'       : '0.4';
    btn.style.cursor  = ok ? 'pointer' : 'not-allowed';
}

async function ejecutarEliminarUsuario() {
    if (!elimUsuarioId) return;

    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id',     elimUsuarioId);

    try {
        const res  = await fetch('../api/usuarios_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            showToast('Eliminado', data.message, 'success');
            closeModal('modalEliminarUsuario');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo conectar con el servidor.', 'error');
    }
}

// Limpiar modal al crear nuevo usuario
document.querySelector('[onclick="openModal(\'modalNuevoUsuario\')"]')
    ?.addEventListener('click', () => {
        ['usu_id','usu_nombre','usu_apellido','usu_email','usu_password',
         'usu_dni','usu_telefono','usu_cargo','usu_especialidad']
            .forEach(id => document.getElementById(id).value = '');
        document.getElementById('usu_rol').value              = 'Usuario';
        document.getElementById('inputCv').value              = '';
        document.getElementById('cvExistente').style.display  = 'none';
        document.getElementById('cvDropLabel').textContent    = 'Haz clic para adjuntar el CV (PDF)';
        document.getElementById('pass_required').style.display = 'inline';
        document.getElementById('pass_hint').style.display     = 'none';
        document.getElementById('modalUsuarioTitulo').textContent = 'Nuevo Usuario';
    });
</script>