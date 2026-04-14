<?php
// ============================================================
//  modules/bi.php — Reportes Analíticos (BI)
//  Ruta: C:\xampp\htdocs\Corfiem_Cesar\modules\bi.php
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session     = require_auth();
$page_title  = 'Reportes BI';
$page_active = 'bi';

// ── KPIs desde la vista ───────────────────────────────────────
$kpi = db_fetch_one("SELECT * FROM vw_kpi_dashboard");

// ── Proyectos por estado ──────────────────────────────────────
$por_estado = db_fetch_all(
    "SELECT ep.nombre AS estado, ep.color, COUNT(p.id) AS total
     FROM estados_proyecto ep
     LEFT JOIN proyectos p ON p.estado_id = ep.id
     GROUP BY ep.id, ep.nombre, ep.color
     ORDER BY ep.id"
);

// ── Proyectos por mes (últimos 12) ────────────────────────────
$por_mes = db_fetch_all(
    "SELECT TO_CHAR(DATE_TRUNC('month', created_at), 'Mon') AS mes,
            TO_CHAR(DATE_TRUNC('month', created_at), 'YYYY') AS anio,
            COUNT(*) AS total
     FROM proyectos
     WHERE created_at >= NOW() - INTERVAL '12 months'
     GROUP BY DATE_TRUNC('month', created_at)
     ORDER BY DATE_TRUNC('month', created_at) ASC"
);

// ── Presupuesto vs costo real por mes ────────────────────────
$presupuesto_mes = db_fetch_all(
    "SELECT TO_CHAR(DATE_TRUNC('month', created_at), 'Mon') AS mes,
            COALESCE(SUM(presupuesto), 0) AS presupuesto,
            COALESCE(SUM(costo_real),  0) AS costo_real
     FROM proyectos
     WHERE created_at >= NOW() - INTERVAL '12 months'
     GROUP BY DATE_TRUNC('month', created_at)
     ORDER BY DATE_TRUNC('month', created_at) ASC"
);

// ── Top 5 clientes ────────────────────────────────────────────
$top_clientes = db_fetch_all(
    "SELECT c.razon_social AS cliente,
            COUNT(p.id)    AS total_proyectos,
            COALESCE(SUM(p.presupuesto), 0) AS presupuesto_total
     FROM clientes c
     LEFT JOIN proyectos p ON p.cliente_id = c.id
     WHERE c.activo = TRUE
     GROUP BY c.id, c.razon_social
     ORDER BY total_proyectos DESC
     LIMIT 5"
);

// ── Avance de proyectos activos ───────────────────────────────
$avance = db_fetch_all(
    "SELECT p.nombre, p.avance_porcentaje, p.presupuesto,
            p.fecha_fin_estimada,
            c.razon_social AS cliente,
            ep.nombre AS estado, ep.color AS estado_color
     FROM proyectos p
     LEFT JOIN clientes c          ON p.cliente_id = c.id
     LEFT JOIN estados_proyecto ep ON p.estado_id  = ep.id
     WHERE p.estado_id NOT IN (4,5,6)
     ORDER BY p.avance_porcentaje DESC
     LIMIT 8"
);

// ── Incidencias por severidad ─────────────────────────────────
$incidencias = db_fetch_all(
    "SELECT severidad,
            COUNT(*) AS total,
            COUNT(*) FILTER (WHERE estado = 'resuelta') AS resueltas
     FROM incidencias
     GROUP BY severidad
     ORDER BY CASE severidad
         WHEN 'Crítica' THEN 1 WHEN 'Alta'  THEN 2
         WHEN 'Media'   THEN 3 WHEN 'Baja'  THEN 4 END"
);

// ── Tasa de éxito ─────────────────────────────────────────────
$totales = db_fetch_one(
    "SELECT COUNT(*) AS total,
            COUNT(*) FILTER (WHERE estado_id = 4) AS completados
     FROM proyectos"
);
$tasa = $totales['total'] > 0
    ? round(($totales['completados'] / $totales['total']) * 100, 1)
    : 0;

// Preparar datos para Chart.js (JSON)
$json_meses        = json_encode(array_column($por_mes, 'mes'));
$json_total_mes    = json_encode(array_map('intval', array_column($por_mes, 'total')));
$json_estados      = json_encode(array_column($por_estado, 'estado'));
$json_estados_tot  = json_encode(array_map('intval', array_column($por_estado, 'total')));
$json_estados_col  = json_encode(array_column($por_estado, 'color'));
$json_pres_meses   = json_encode(array_column($presupuesto_mes, 'mes'));
$json_pres_vals    = json_encode(array_map('floatval', array_column($presupuesto_mes, 'presupuesto')));
$json_costo_vals   = json_encode(array_map('floatval', array_column($presupuesto_mes, 'costo_real')));

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
<?php render_topbar('Reportes Analíticos', 'Business Intelligence'); ?>

<div class="page-body">

    <!-- ── KPIs ─────────────────────────────────────────────── -->
    <div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin-bottom:24px;">

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
            <div class="kpi-label">Completados</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
            </div>
            <div class="kpi-value">
                $<?= number_format(($kpi['presupuesto_total_activo'] ?? 0) / 1000, 0) ?>K
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
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12,6 12,12 16,14"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $tasa ?>%</div>
            <div class="kpi-label">Tasa de Éxito</div>
        </div>

    </div>

    <!-- ── Fila 1: Gráficas principales ──────────────────────── -->
    <div class="grid-2" style="margin-bottom:20px;">

        <!-- Proyectos por mes -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">📈 Proyectos por Mes</span>
                <span style="font-size:11px;color:var(--c-text-3);">Últimos 12 meses</span>
            </div>
            <div style="position:relative;height:220px;">
                <canvas id="chartMes"></canvas>
            </div>
        </div>

        <!-- Distribución por estado (dona) -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">🔵 Distribución por Estado</span>
            </div>
            <div style="display:flex;align-items:center;gap:20px;">
                <div style="position:relative;height:200px;width:200px;flex-shrink:0;">
                    <canvas id="chartEstado"></canvas>
                </div>
                <div style="flex:1;">
                    <?php foreach ($por_estado as $e): ?>
                        <?php if ((int)$e['total'] > 0): ?>
                        <div style="display:flex;justify-content:space-between;
                                    align-items:center;padding:5px 0;
                                    border-bottom:1px solid #F3F4F6;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="width:10px;height:10px;border-radius:50%;
                                             background:<?= $e['color'] ?>;display:inline-block;">
                                </span>
                                <span style="font-size:12.5px;"><?= htmlspecialchars($e['estado']) ?></span>
                            </div>
                            <span style="font-size:13px;font-weight:600;"><?= $e['total'] ?></span>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Fila 2: Presupuesto y Top Clientes ────────────────── -->
    <div class="grid-2" style="margin-bottom:20px;">

        <!-- Presupuesto vs Costo Real -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">💰 Presupuesto vs Costo Real</span>
                <span style="display:flex;gap:12px;font-size:11px;">
                    <span style="display:flex;align-items:center;gap:4px;">
                        <span style="width:10px;height:3px;background:var(--c-navy);display:inline-block;border-radius:2px;"></span>
                        Presupuesto
                    </span>
                    <span style="display:flex;align-items:center;gap:4px;">
                        <span style="width:10px;height:3px;background:#94A3B8;display:inline-block;border-radius:2px;"></span>
                        Costo Real
                    </span>
                </span>
            </div>
            <div style="position:relative;height:220px;">
                <canvas id="chartPresupuesto"></canvas>
            </div>
        </div>

        <!-- Top Clientes -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">🏆 Top Clientes</span>
            </div>
            <?php if (empty($top_clientes)): ?>
                <p style="color:var(--c-text-3);text-align:center;padding:30px;">
                    Sin datos aún.
                </p>
            <?php else: ?>
                <?php
                $max_proy = max(array_column($top_clientes, 'total_proyectos')) ?: 1;
                foreach ($top_clientes as $i => $tc):
                    $pct = round(($tc['total_proyectos'] / $max_proy) * 100);
                ?>
                <div style="margin-bottom:14px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                        <span style="font-size:13px;font-weight:500;
                                     overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:60%;">
                            <?= htmlspecialchars($tc['cliente']) ?>
                        </span>
                        <span style="font-size:12px;color:var(--c-text-3);">
                            <?= $tc['total_proyectos'] ?> proyecto<?= $tc['total_proyectos'] != 1 ? 's' : '' ?>
                        </span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width:<?= $pct ?>%;
                             background:<?= ['#0D1B2A','#1B3A6B','#2563EB','#60A5FA','#BAC8FF'][$i] ?? 'var(--c-navy)' ?>;">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- ── Fila 3: Avance proyectos + Incidencias ─────────────── -->
    <div class="grid-2" style="margin-bottom:20px;">

        <!-- Avance de proyectos activos -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">📊 Avance de Proyectos Activos</span>
            </div>
            <?php if (empty($avance)): ?>
                <p style="color:var(--c-text-3);text-align:center;padding:30px;">
                    No hay proyectos activos.
                </p>
            <?php else: ?>
                <?php foreach ($avance as $p): ?>
                <div style="margin-bottom:14px;">
                    <div style="display:flex;justify-content:space-between;
                                align-items:flex-start;margin-bottom:5px;">
                        <div style="flex:1;min-width:0;padding-right:12px;">
                            <div style="font-size:13px;font-weight:500;
                                        overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?= htmlspecialchars($p['nombre']) ?>
                            </div>
                            <div style="font-size:11px;color:var(--c-text-3);">
                                <?= htmlspecialchars($p['cliente'] ?? '—') ?>
                            </div>
                        </div>
                        <div style="text-align:right;flex-shrink:0;">
                            <span style="font-size:14px;font-weight:700;color:var(--c-navy);">
                                <?= $p['avance_porcentaje'] ?>%
                            </span>
                            <br>
                            <span class="badge badge-dot"
                                  style="background:<?= $p['estado_color'] ?>22;
                                         color:<?= $p['estado_color'] ?>;font-size:10px;">
                                <?= htmlspecialchars($p['estado']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar"
                             style="width:<?= $p['avance_porcentaje'] ?>%;
                                    background:<?= $p['estado_color'] ?>;">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Incidencias por severidad -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">⚠️ Incidencias por Severidad</span>
            </div>
            <?php if (empty($incidencias)): ?>
                <p style="color:var(--c-text-3);text-align:center;padding:30px;">
                    Sin incidencias registradas.
                </p>
            <?php else: ?>
                <div style="position:relative;height:200px;margin-bottom:16px;">
                    <canvas id="chartIncidencias"></canvas>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Severidad</th>
                                <th>Total</th>
                                <th>Resueltas</th>
                                <th>Pendientes</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($incidencias as $inc):
                            $pendientes = $inc['total'] - $inc['resueltas'];
                            $badgeClass = match($inc['severidad']) {
                                'Crítica' => 'badge-cancelled',
                                'Alta'    => 'badge-cancelled',
                                'Media'   => 'badge-pending',
                                default   => 'badge-active',
                            };
                        ?>
                            <tr>
                                <td>
                                    <span class="badge badge-dot <?= $badgeClass ?>">
                                        <?= htmlspecialchars($inc['severidad']) ?>
                                    </span>
                                </td>
                                <td><strong><?= $inc['total'] ?></strong></td>
                                <td style="color:var(--c-success);">
                                    <?= $inc['resueltas'] ?>
                                </td>
                                <td style="color:<?= $pendientes > 0 ? 'var(--c-danger)' : 'var(--c-text-3)' ?>;">
                                    <?= $pendientes ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ── Tabla comparativa de proyectos ─────────────────────── -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">📋 Comparativa de Proyectos</span>
            <a href="<?= APP_URL ?>/modules/proyectos.php" class="btn btn-secondary btn-sm">
                Ver todos
            </a>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Proyecto</th>
                        <th>Cliente</th>
                        <th>Avance</th>
                        <th>Presupuesto</th>
                        <th>Costo Real</th>
                        <th>Variación</th>
                        <th>Fecha Fin</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $proyectos_tabla = db_fetch_all(
                    "SELECT p.nombre, p.avance_porcentaje, p.presupuesto,
                            p.costo_real, p.fecha_fin_estimada,
                            c.razon_social AS cliente,
                            ep.nombre AS estado, ep.color AS estado_color
                     FROM proyectos p
                     LEFT JOIN clientes c          ON p.cliente_id = c.id
                     LEFT JOIN estados_proyecto ep ON p.estado_id  = ep.id
                     ORDER BY p.created_at DESC
                     LIMIT 10"
                );
                ?>
                <?php if (empty($proyectos_tabla)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:30px;color:var(--c-text-3);">
                            No hay proyectos registrados aún.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($proyectos_tabla as $p):
                        $variacion = ($p['presupuesto'] > 0)
                            ? $p['costo_real'] - $p['presupuesto']
                            : null;
                        $var_pct   = ($p['presupuesto'] > 0 && $variacion !== null)
                            ? round(($variacion / $p['presupuesto']) * 100, 1)
                            : null;
                    ?>
                    <tr>
                        <td style="font-weight:500;max-width:200px;
                                   overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?= htmlspecialchars($p['nombre']) ?>
                        </td>
                        <td style="font-size:12.5px;">
                            <?= htmlspecialchars($p['cliente'] ?? '—') ?>
                        </td>
                        <td style="min-width:120px;">
                            <div style="display:flex;align-items:center;gap:6px;">
                                <div class="progress" style="flex:1;">
                                    <div class="progress-bar"
                                         style="width:<?= $p['avance_porcentaje'] ?>%">
                                    </div>
                                </div>
                                <span style="font-size:12px;font-weight:600;min-width:30px;">
                                    <?= $p['avance_porcentaje'] ?>%
                                </span>
                            </div>
                        </td>
                        <td style="font-size:13px;">
                            <?= $p['presupuesto']
                                ? 'S/ ' . number_format($p['presupuesto'], 2)
                                : '—' ?>
                        </td>
                        <td style="font-size:13px;">
                            <?= $p['costo_real'] > 0
                                ? 'S/' . number_format($p['costo_real'], 2)
                                : '—' ?>
                        </td>
                        <td>
                            <?php if ($var_pct !== null): ?>
                                <span style="font-size:12px;font-weight:600;
                                             color:<?= $variacion > 0 ? 'var(--c-danger)' : 'var(--c-success)' ?>;">
                                    <?= $variacion > 0 ? '+' : '' ?><?= $var_pct ?>%
                                </span>
                            <?php else: ?>
                                <span style="color:var(--c-text-4);">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12.5px;">
                            <?= $p['fecha_fin_estimada']
                                ? date('d/m/Y', strtotime($p['fecha_fin_estimada']))
                                : '—' ?>
                        </td>
                        <td>
                            <span class="badge badge-dot"
                                  style="background:<?= $p['estado_color'] ?>22;
                                         color:<?= $p['estado_color'] ?>;">
                                <?= htmlspecialchars($p['estado'] ?? '—') ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- .page-body -->
</div><!-- .main-content -->

<?php include __DIR__ . '/../includes/footer.php'; ?>

<!-- ── Chart.js desde CDN ─────────────────────────────────────── -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
// Configuración global corporativa para Chart.js
Chart.defaults.font.family  = "'Inter', 'Segoe UI', sans-serif";
Chart.defaults.font.size    = 12;
Chart.defaults.color        = '#6B7280';
Chart.defaults.plugins.legend.display = false;

const NAVY   = '#1B3A6B';
const SLATE  = '#94A3B8';
const BORDER = '#E5E7EB';

// ── 1. Gráfica: Proyectos por Mes (barras) ────────────────────
const ctxMes = document.getElementById('chartMes')?.getContext('2d');
if (ctxMes) {
    new Chart(ctxMes, {
        type: 'bar',
        data: {
            labels:   <?= $json_meses ?>,
            datasets: [{
                label:           'Proyectos',
                data:            <?= $json_total_mes ?>,
                backgroundColor: NAVY,
                borderRadius:    4,
                borderSkipped:   false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 },
                    grid: { color: BORDER }
                }
            }
        }
    });
}

// ── 2. Gráfica: Por Estado (dona) ─────────────────────────────
const ctxEstado = document.getElementById('chartEstado')?.getContext('2d');
if (ctxEstado) {
    new Chart(ctxEstado, {
        type: 'doughnut',
        data: {
            labels:   <?= $json_estados ?>,
            datasets: [{
                data:            <?= $json_estados_tot ?>,
                backgroundColor: <?= $json_estados_col ?>,
                borderWidth:     2,
                borderColor:     '#fff',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.label}: ${ctx.parsed}`
                    }
                }
            }
        }
    });
}

// ── 3. Gráfica: Presupuesto vs Costo Real (líneas) ────────────
const ctxPres = document.getElementById('chartPresupuesto')?.getContext('2d');
if (ctxPres) {
    new Chart(ctxPres, {
        type: 'line',
        data: {
            labels: <?= $json_pres_meses ?>,
            datasets: [
                {
                    label:       'Presupuesto',
                    data:        <?= $json_pres_vals ?>,
                    borderColor: NAVY,
                    borderWidth: 2,
                    pointBackgroundColor: NAVY,
                    pointRadius: 4,
                    tension:     0.3,
                    fill:        false,
                },
                {
                    label:       'Costo Real',
                    data:        <?= $json_costo_vals ?>,
                    borderColor: SLATE,
                    borderWidth: 2,
                    borderDash:  [5, 4],
                    pointBackgroundColor: SLATE,
                    pointRadius: 4,
                    tension:     0.3,
                    fill:        false,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    grid: { color: BORDER },
                    ticks: {
                        callback: v => 'S/' + (v/1000).toFixed(0) + 'K'
                    }
                }
            }
        }
    });
}

// ── 4. Gráfica: Incidencias por Severidad (barras horizontales)
const ctxInc = document.getElementById('chartIncidencias')?.getContext('2d');
if (ctxInc) {
    const sevLabels = <?= json_encode(array_column($incidencias, 'severidad')) ?>;
    const sevTotals = <?= json_encode(array_map('intval', array_column($incidencias, 'total'))) ?>;
    const sevColors = sevLabels.map(s => ({
        'Crítica': '#DC2626', 'Alta': '#F97316',
        'Media':   '#D97706', 'Baja': '#059669'
    }[s] ?? NAVY));

    new Chart(ctxInc, {
        type: 'bar',
        data: {
            labels: sevLabels,
            datasets: [{
                data:            sevTotals,
                backgroundColor: sevColors,
                borderRadius:    4,
            }]
        },
        options: {
            indexAxis:           'y',
            responsive:          true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 },
                    grid: { color: BORDER }
                },
                y: { grid: { display: false } }
            }
        }
    });
}
</script>