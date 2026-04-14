<?php
// ============================================================
//  modules/proyectos.php — Gestión de Proyectos
//  Ruta: C:\xampp\htdocs\Corfiem_Cesar\modules\proyectos.php
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session     = require_auth();
$page_title  = 'Proyectos';
$page_active = 'proyectos';

// ── Obtener clientes activos ─────────────────────────────────
$clientes = db_fetch_all(
    "SELECT id, razon_social FROM clientes WHERE activo = TRUE ORDER BY razon_social"
);

// ── Obtener usuarios (responsables) ──────────────────────────
$usuarios = db_fetch_all(
    "SELECT id, nombre || ' ' || apellido AS nombre_completo 
     FROM usuarios WHERE activo = TRUE ORDER BY nombre"
);

// ── Obtener estados ───────────────────────────────────────────
$estados = db_fetch_all(
    "SELECT id, nombre, color FROM estados_proyecto ORDER BY orden"
);

// ── Filtros ───────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if (!empty($_GET['q'])) {
    $where[]  = '(p.codigo ILIKE ? OR p.nombre ILIKE ? OR c.razon_social ILIKE ?)';
    $q        = '%' . trim($_GET['q']) . '%';
    $params   = array_merge($params, [$q, $q, $q]);
}

if (!empty($_GET['cliente_id'])) {
    $where[]  = 'p.cliente_id = ?';
    $params[] = (int)$_GET['cliente_id'];
}

if (!empty($_GET['estado_id'])) {
    $where[]  = 'p.estado_id = ?';
    $params[] = (int)$_GET['estado_id'];
}

if (!empty($_GET['responsable_id'])) {
    $where[]  = 'p.responsable_id = ?';
    $params[] = (int)$_GET['responsable_id'];
}

if (!empty($_GET['prioridad'])) {
    $where[]  = 'p.prioridad = ?';
    $params[] = $_GET['prioridad'];
}

// ── Paginación ────────────────────────────────────────────────
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit  = 15;
$offset = ($page - 1) * $limit;

// ── Ordenamiento ──────────────────────────────────────────────
$sort_column = $_GET['sort'] ?? 'created_at';
$sort_order  = $_GET['order'] ?? 'DESC';

$allowed_sorts = ['codigo', 'nombre', 'cliente_nombre', 'estado_nombre', 
                  'avance_porcentaje', 'presupuesto', 'fecha_inicio', 
                  'fecha_fin_estimada', 'created_at'];

if (!in_array($sort_column, $allowed_sorts)) {
    $sort_column = 'created_at';
}

$sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

// ── Query principal ───────────────────────────────────────────
$proyectos = db_fetch_all(
    "SELECT p.id, p.codigo, p.nombre, p.descripcion, p.alcance,
            p.avance_porcentaje, p.fecha_inicio, p.fecha_fin_estimada,
            p.presupuesto, p.prioridad, p.created_at,
            c.razon_social AS cliente_nombre,
            ep.nombre AS estado_nombre,
            ep.color AS estado_color,
            u.nombre || ' ' || u.apellido AS responsable_nombre,
            (SELECT COUNT(*) FROM entregables e 
             WHERE e.proyecto_id = p.id) AS total_entregables,
            (SELECT COUNT(*) FROM entregables e 
             WHERE e.proyecto_id = p.id 
             AND e.estado = 'completado') AS entregables_completados
     FROM proyectos p
     LEFT JOIN clientes c ON p.cliente_id = c.id
     LEFT JOIN estados_proyecto ep ON p.estado_id = ep.id
     LEFT JOIN usuarios u ON p.responsable_id = u.id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY " . $sort_column . " " . $sort_order . "
     LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);

// ── Total de registros (para paginación) ──────────────────────
$total_count = db_fetch_one(
    "SELECT COUNT(*) AS total
     FROM proyectos p
     LEFT JOIN clientes c ON p.cliente_id = c.id
     WHERE " . implode(' AND ', $where),
    $params
)['total'] ?? 0;

$total_pages = ceil($total_count / $limit);

// ── KPIs ──────────────────────────────────────────────────────
$kpis = db_fetch_one(
    "SELECT 
        COUNT(*) AS total,
        COUNT(*) FILTER (WHERE estado_id NOT IN (4,5,6)) AS activos,
        COUNT(*) FILTER (WHERE estado_id = 4) AS completados,
        COUNT(*) FILTER (WHERE estado_id = 5) AS cancelados,
        COALESCE(SUM(presupuesto) FILTER (WHERE estado_id NOT IN (4,5,6)), 0) AS presupuesto_activo,
        COALESCE(ROUND(AVG(avance_porcentaje) FILTER (WHERE estado_id NOT IN (4,5,6)), 2), 0) AS avance_promedio
     FROM proyectos"
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
<?php render_topbar('Proyectos', 'Gestión de proyectos de clientes'); ?>

<div class="page-body">

    <!-- ── KPIs ─────────────────────────────────────────────── -->
    <div class="kpi-grid" style="margin-bottom:24px;">
        <div class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $kpis['total'] ?></div>
            <div class="kpi-label">Total Proyectos</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon" style="background:var(--c-accent-lt);">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--c-accent)" stroke-width="2">
                    <polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/>
                </svg>
            </div>
            <div class="kpi-value" style="color:var(--c-accent);"><?= $kpis['activos'] ?></div>
            <div class="kpi-label">Proyectos Activos</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon" style="background:var(--c-success-lt);">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--c-success)" stroke-width="2">
                    <polyline points="20,6 9,17 4,12"/>
                </svg>
            </div>
            <div class="kpi-value" style="color:var(--c-success);"><?= $kpis['completados'] ?></div>
            <div class="kpi-label">Completados</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
            </div>
            <div class="kpi-value">S/ <?= number_format($kpis['presupuesto_activo'], 2) ?></div>
            <div class="kpi-label">Presupuesto Activo</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12,6 12,12 16,14"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $kpis['avance_promedio'] ?>%</div>
            <div class="kpi-label">Avance Promedio</div>
        </div>
    </div>

    <!-- ── Filtros ───────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:20px;padding:16px 20px;">
        <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <div class="search-wrapper" style="flex:1;min-width:200px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input class="search-input" type="text" name="q"
                       placeholder="Buscar por código, nombre o cliente..."
                       value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
            </div>

            <select name="cliente_id" class="form-control" style="width:180px;">
                <option value="">Todos los clientes</option>
                <?php foreach ($clientes as $cli): ?>
                    <option value="<?= $cli['id'] ?>"
                        <?= ($_GET['cliente_id'] ?? '') == $cli['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cli['razon_social']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="estado_id" class="form-control" style="width:150px;">
                <option value="">Todos los estados</option>
                <?php foreach ($estados as $est): ?>
                    <option value="<?= $est['id'] ?>"
                        <?= ($_GET['estado_id'] ?? '') == $est['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($est['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="prioridad" class="form-control" style="width:130px;">
                <option value="">Todas prioridades</option>
                <option value="Baja" <?= ($_GET['prioridad'] ?? '') === 'Baja' ? 'selected' : '' ?>>Baja</option>
                <option value="Media" <?= ($_GET['prioridad'] ?? '') === 'Media' ? 'selected' : '' ?>>Media</option>
                <option value="Alta" <?= ($_GET['prioridad'] ?? '') === 'Alta' ? 'selected' : '' ?>>Alta</option>
                <option value="Crítica" <?= ($_GET['prioridad'] ?? '') === 'Crítica' ? 'selected' : '' ?>>Crítica</option>
            </select>

            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            
            <?php if (!empty($_GET['q']) || !empty($_GET['cliente_id']) || !empty($_GET['estado_id']) || !empty($_GET['prioridad'])): ?>
                <a href="proyectos.php" class="btn btn-secondary btn-sm">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ── Cabecera de cuadrícula ───────────────────────────── -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <span style="font-size:14px;color:var(--c-text-3);">
            <?= $total_count ?> proyecto<?= $total_count != 1 ? 's' : '' ?>
        </span>
    </div>

    <?php if (empty($proyectos)): ?>
        <div class="card" style="text-align:center;padding:60px;color:var(--c-text-3);">
            No hay proyectos que coincidan con los filtros.
        </div>
    <?php else: ?>

    <!-- ── Cuadrícula de proyectos ───────────────────────────── -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px;">
        <?php foreach ($proyectos as $p):
            $prioridad_colors = [
                'Baja'    => '#6B7280',
                'Media'   => '#3B82F6',
                'Alta'    => '#F59E0B',
                'Crítica' => '#EF4444',
            ];
            $pcolor = $prioridad_colors[$p['prioridad']] ?? '#6B7280';
        ?>
        <a href="proyecto_detalle.php?id=<?= $p['id'] ?>"
           style="text-decoration:none;color:inherit;">
            <div style="background:#fff;border:1px solid var(--c-border);border-radius:10px;
                        padding:20px;display:flex;flex-direction:column;gap:14px;
                        transition:box-shadow 0.2s,transform 0.2s;cursor:pointer;"
                 onmouseover="this.style.boxShadow='0 6px 20px rgba(0,0,0,0.1)';this.style.transform='translateY(-2px)'"
                 onmouseout="this.style.boxShadow='';this.style.transform=''">

                <!-- Ícono + código -->
                <div style="display:flex;align-items:center;gap:12px;">
                    <img src="carpeta.png" alt="proyecto"
                         style="width:44px;height:44px;object-fit:contain;flex-shrink:0;">
                    <div>
                        <div style="font-family:monospace;font-size:11px;color:var(--c-text-4);">
                            <?= htmlspecialchars($p['codigo'] ?? '—') ?>
                        </div>
                        <div style="font-size:14px;font-weight:700;color:var(--c-text);
                                    line-height:1.3;margin-top:2px;">
                            <?= htmlspecialchars($p['nombre']) ?>
                        </div>
                    </div>
                </div>

                <!-- Cliente -->
                <div style="font-size:12.5px;color:var(--c-text-3);">
                    🏢 <?= htmlspecialchars($p['cliente_nombre'] ?? 'Sin cliente') ?>
                </div>

                <!-- Estado + Prioridad -->
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <span class="badge badge-dot"
                          style="background:<?= $p['estado_color'] ?>22;color:<?= $p['estado_color'] ?>;font-size:11px;">
                        <?= htmlspecialchars($p['estado_nombre'] ?? '—') ?>
                    </span>
                    <span class="badge badge-dot"
                          style="background:<?= $pcolor ?>22;color:<?= $pcolor ?>;font-size:11px;">
                        <?= htmlspecialchars($p['prioridad'] ?? 'Media') ?>
                    </span>
                </div>

                <!-- Barra de avance -->
                <div>
                    <div style="display:flex;justify-content:space-between;
                                font-size:11px;color:var(--c-text-3);margin-bottom:5px;">
                        <span>Avance</span>
                        <span style="font-weight:700;color:var(--c-text);">
                            <?= number_format($p['avance_porcentaje'], 0) ?>%
                        </span>
                    </div>
                    <div style="background:#E5E7EB;border-radius:6px;height:7px;overflow:hidden;">
                        <div style="background:#10B981;height:100%;
                                    width:<?= $p['avance_porcentaje'] ?>%;
                                    border-radius:6px;transition:width 0.3s;">
                        </div>
                    </div>
                    <?php if ($p['total_entregables'] > 0): ?>
                        <div style="font-size:10px;color:var(--c-text-4);margin-top:4px;">
                            <?= $p['entregables_completados'] ?>/<?= $p['total_entregables'] ?> entregables
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pie: presupuesto y fechas -->
                <div style="display:flex;justify-content:space-between;align-items:center;
                            border-top:1px solid var(--c-border);padding-top:12px;
                            font-size:11.5px;color:var(--c-text-3);">
                    <span>
                        <?= $p['presupuesto'] ? 'S/ ' . number_format($p['presupuesto'], 0) : 'Sin presupuesto' ?>
                    </span>
                    <?php if ($p['fecha_fin_estimada']): ?>
                        <span>📅 <?= date('d/m/Y', strtotime($p['fecha_fin_estimada'])) ?></span>
                    <?php endif; ?>
                </div>

            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── Paginación ────────────────────────────────────────── -->
    <?php if ($total_pages > 1): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:24px;">
        <div style="color:var(--c-text-3);font-size:13px;">
            Mostrando <?= min($offset + 1, $total_count) ?> -
            <?= min($offset + $limit, $total_count) ?> de <?= $total_count ?>
        </div>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?><?= !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : '' ?>"
                   class="pagination-btn">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?><?= !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : '' ?>"
                   class="pagination-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?><?= !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : '' ?>"
                   class="pagination-btn">Siguiente</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>

</div><!-- .page-body -->
</div><!-- .main-content -->

<?php include __DIR__ . '/../includes/footer.php'; ?>