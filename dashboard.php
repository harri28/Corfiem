<?php
// ============================================================
//  dashboard.php — Panel principal con KPIs reales
//  Ruta: C:\xampp\htdocs\Corfiem_Cesar\dashboard.php
// ============================================================
require_once __DIR__ . '/config/db.php';
$session     = require_auth();
$page_title  = 'Dashboard';
$page_active = 'dashboard';

// ── KPIs desde vista ──────────────────────────────────────────
$kpi = db_fetch_one("SELECT * FROM vw_kpi_dashboard");

// ── Proyectos recientes ───────────────────────────────────────
$proyectos_recientes = db_fetch_all(
    "SELECT p.id, p.codigo, p.nombre, p.avance_porcentaje,
            p.fecha_fin_estimada, p.prioridad,
            c.razon_social AS cliente,
            ep.nombre AS estado, ep.color AS estado_color
     FROM proyectos p
     LEFT JOIN clientes c          ON p.cliente_id  = c.id
     LEFT JOIN estados_proyecto ep ON p.estado_id   = ep.id
     ORDER BY p.updated_at DESC LIMIT 6"
);

// ── Actividad reciente (log) ──────────────────────────────────
$actividad = db_fetch_all(
    "SELECT a.accion, a.modulo, a.created_at,
            u.nombre || ' ' || u.apellido AS usuario,
            a.datos_despues
     FROM auditoria_log a
     JOIN usuarios u ON a.usuario_id = u.id
     ORDER BY a.created_at DESC LIMIT 8"
);

// ── Proyectos por estado (para mini gráfico) ──────────────────
$por_estado = db_fetch_all(
    "SELECT ep.nombre, ep.color, COUNT(p.id) AS total
     FROM estados_proyecto ep
     LEFT JOIN proyectos p ON p.estado_id = ep.id
     GROUP BY ep.id, ep.nombre, ep.color
     ORDER BY ep.id"
);

// ── Incidencias abiertas ──────────────────────────────────────
$incidencias = db_fetch_all(
    "SELECT i.id, i.titulo, i.severidad, i.tipo,
            p.nombre AS proyecto, i.created_at
     FROM incidencias i
     JOIN proyectos p ON i.proyecto_id = p.id
     WHERE i.estado = 'abierta'
     ORDER BY
       CASE i.severidad WHEN 'Crítica' THEN 0 WHEN 'Alta' THEN 1
                        WHEN 'Media'   THEN 2 ELSE 3 END
     LIMIT 5"
);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
<?php render_topbar('Dashboard', date('d/m/Y')); ?>

<div class="page-body">

    <!-- ── KPIs ─────────────────────────────────────────────── -->
    <div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
        <div class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14,2 14,8 20,8"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $kpi['proyectos_activos'] ?? 0 ?></div>
            <div class="kpi-label">Proyectos Activos</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20,6 9,17 4,12"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $kpi['proyectos_completados'] ?? 0 ?></div>
            <div class="kpi-label">Proyectos Completados</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $kpi['clientes_activos'] ?? 0 ?></div>
            <div class="kpi-label">Clientes Activos</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:var(--c-danger-lt);">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--c-danger)" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8"  x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
            </div>
            <div class="kpi-value" style="color:var(--c-danger);">
                <?= $kpi['incidencias_abiertas'] ?? 0 ?>
            </div>
            <div class="kpi-label">Incidencias Abiertas</div>
        </div>

        
            <div class="kpi-card">
        <div class="kpi-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="1"  x2="12" y2="23"/>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
        </div>
                <div class="kpi-value">
                    S/ <?= number_format($kpi['presupuesto_total_activo'] ?? 0, 2) ?>
                </div>
            <div class="kpi-label">Presupuesto Activo</div>
         </div>


        <div class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $kpi['avance_promedio'] ?? 0 ?>%</div>
            <div class="kpi-label">Avance Promedio</div>
        </div>
    </div>

    <!-- ── Contenido principal ───────────────────────────────── -->
    <div class="grid-2" style="gap:20px;margin-bottom:20px;margin-top:20px;">

        <!-- Proyectos recientes -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">Proyectos Recientes</span>
                <a href="modules/proyectos.php" class="btn btn-secondary btn-sm">Ver todos</a>
            </div>
            <?php if (empty($proyectos_recientes)): ?>
                <p style="color:var(--c-text-3);text-align:center;padding:30px;font-size:13px;">
                    No hay proyectos aún.
                </p>
            <?php else: ?>
                <?php foreach ($proyectos_recientes as $p): ?>
                <div style="padding:12px 0;border-bottom:1px solid #F3F4F6;">
                    <div style="display:flex;justify-content:space-between;
                                align-items:flex-start;margin-bottom:6px;">
                        <div style="flex:1;min-width:0;padding-right:10px;">
                            <span style="font-size:11px;font-family:monospace;
                                         color:var(--c-text-3);">
                                <?= htmlspecialchars($p['codigo'] ?? '') ?>
                            </span>
                            <div style="font-weight:600;font-size:13.5px;margin-top:1px;
                                        overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?= htmlspecialchars($p['nombre']) ?>
                            </div>
                            <div style="font-size:12px;color:var(--c-text-3);">
                                <?= htmlspecialchars($p['cliente'] ?? '') ?>
                            </div>
                        </div>
                        <span class="badge badge-dot"
                              style="background:<?= $p['estado_color'] ?>22;
                                     color:<?= $p['estado_color'] ?>;flex-shrink:0;">
                            <?= htmlspecialchars($p['estado'] ?? '') ?>
                        </span>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="progress" style="flex:1;">
                            <div class="progress-bar"
                                 style="width:<?= $p['avance_porcentaje'] ?>%">
                            </div>
                        </div>
                        <span style="font-size:11px;font-weight:600;
                                     color:var(--c-text-2);min-width:32px;">
                            <?= $p['avance_porcentaje'] ?>%
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Columna derecha -->
        <div style="display:flex;flex-direction:column;gap:20px;">

            <!-- Distribución por estado -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Proyectos por Estado</span>
                </div>
                <?php foreach ($por_estado as $e): ?>
                    <?php if ((int)$e['total'] > 0): ?>
                    <div style="margin-bottom:10px;">
                        <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                            <span style="font-size:12.5px;color:var(--c-text-2);">
                                <?= htmlspecialchars($e['nombre']) ?>
                            </span>
                            <span style="font-size:12px;font-weight:600;">
                                <?= $e['total'] ?>
                            </span>
                        </div>
                        <?php
                            $total_p = array_sum(array_column($por_estado, 'total')) ?: 1;
                            $pct     = round($e['total'] / $total_p * 100);
                        ?>
                        <div class="progress">
                            <div class="progress-bar"
                                 style="width:<?= $pct ?>%;background:<?= $e['color'] ?>;">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Incidencias abiertas -->
            <?php if (!empty($incidencias)): ?>
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Incidencias Abiertas</span>
                    <a href="modules/marcha.php" class="btn btn-secondary btn-sm">Ver todas</a>
                </div>
                <?php foreach ($incidencias as $inc):
                    $sevColor = match($inc['severidad']) {
                        'Crítica' => '#DC2626',
                        'Alta'    => '#EA580C',
                        'Media'   => '#D97706',
                        default   => '#059669',
                    };
                ?>
                <div style="display:flex;align-items:flex-start;gap:10px;
                            padding:8px 0;border-bottom:1px solid #F3F4F6;">
                    <span class="badge badge-dot"
                          style="background:<?= $sevColor ?>22;color:<?= $sevColor ?>;
                                 flex-shrink:0;font-size:10.5px;">
                        <?= $inc['severidad'] ?>
                    </span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:500;
                                    overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?= htmlspecialchars($inc['titulo']) ?>
                        </div>
                        <div style="font-size:11px;color:var(--c-text-3);">
                            <?= htmlspecialchars($inc['proyecto']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- ── Feed de actividad reciente ────────────────────────── -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Actividad Reciente del Sistema</span>
        </div>
        <div class="activity-list">
        <?php if (empty($actividad)): ?>
            <p style="color:var(--c-text-3);text-align:center;padding:24px;font-size:13px;">
                Sin actividad registrada aún.
            </p>
        <?php else: ?>
            <?php
            $iconos = [
                'CREATE' => '<path d="M12 5v14M5 12h14"/>',
                'UPDATE' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                             <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
                'DELETE' => '<polyline points="3,6 5,6 21,6"/>
                             <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6"/>',
                'LOGIN'  => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                             <polyline points="10,17 15,12 10,7"/>
                             <line x1="15" y1="12" x2="3" y2="12"/>',
                'LOGOUT' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                             <polyline points="16,17 21,12 16,7"/>
                             <line x1="21" y1="12" x2="9" y2="12"/>',
            ];
            $modulos = [
                'proyectos'   => 'Proyectos',
                'clientes'    => 'Clientes',
                'previas'     => 'Actividades Previas',
                'incidencias' => 'Incidencias',
                'capacitacion'=> 'Capacitación',
                'auth'        => 'Autenticación',
                'usuarios'    => 'Usuarios',
            ];
            ?>
            <?php foreach ($actividad as $act): ?>
            <div class="activity-item">
                <div class="activity-avatar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <?= $iconos[$act['accion']] ?? '<circle cx="12" cy="12" r="10"/>' ?>
                    </svg>
                </div>
                <div style="flex:1;">
                    <div class="activity-text">
                        <strong><?= htmlspecialchars($act['usuario']) ?></strong>
                        realizó
                        <span style="font-weight:500;color:var(--c-navy);">
                            <?= strtolower($act['accion']) ?>
                        </span>
                        en
                        <span style="color:var(--c-text-2);">
                            <?= $modulos[$act['modulo']] ?? $act['modulo'] ?>
                        </span>
                    </div>
                    <div class="activity-time"
                         data-timestamp="<?= $act['created_at'] ?>">
                        <?= date('d/m/Y H:i', strtotime($act['created_at'])) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>

</div><!-- .page-body -->
</div><!-- .main-content -->

<?php include __DIR__ . '/includes/footer.php'; ?>