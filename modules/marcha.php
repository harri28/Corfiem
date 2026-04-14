<?php
// ============================================================
//  modules/marcha.php — Puesta en Marcha e Incidencias
//  Ruta: C:\xampp\htdocs\Corfiem_Cesar\modules\marcha.php
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session     = require_auth();
$uid         = (int)$session['usuario_id'];
$page_title  = 'Puesta en Marcha';
$page_active = 'marcha';

// ── Datos para selects ────────────────────────────────────────
$proyectos = db_fetch_all(
    "SELECT p.id, p.nombre, p.codigo, c.razon_social AS cliente
     FROM proyectos p
     LEFT JOIN clientes c ON p.cliente_id = c.id
     WHERE p.estado_id NOT IN (5,6)
     ORDER BY p.nombre"
);

$usuarios = db_fetch_all(
    "SELECT id, nombre || ' ' || apellido AS nombre_completo
     FROM usuarios WHERE activo = TRUE ORDER BY nombre"
);

// ── Filtros ───────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if (!empty($_GET['proyecto_id'])) {
    $where[]  = 'i.proyecto_id = ?';
    $params[] = (int)$_GET['proyecto_id'];
}
if (!empty($_GET['estado'])) {
    $where[]  = 'i.estado = ?';
    $params[] = $_GET['estado'];
}
if (!empty($_GET['severidad'])) {
    $where[]  = 'i.severidad = ?';
    $params[] = $_GET['severidad'];
}
if (!empty($_GET['q'])) {
    $where[]  = '(i.titulo ILIKE ? OR i.descripcion ILIKE ?)';
    $q        = '%' . trim($_GET['q']) . '%';
    $params   = array_merge($params, [$q, $q]);
}

$incidencias = db_fetch_all(
    "SELECT i.id, i.titulo, i.descripcion, i.tipo, i.severidad,
            i.estado, i.solucion, i.fecha_reporte, i.fecha_resolucion,
            p.nombre AS proyecto, p.codigo AS proyecto_codigo,
            c.razon_social AS cliente,
            ur.nombre || ' ' || ur.apellido AS reportado_por,
            ua.nombre || ' ' || ua.apellido AS asignado_a
     FROM incidencias i
     LEFT JOIN proyectos p  ON i.proyecto_id  = p.id
     LEFT JOIN clientes  c  ON p.cliente_id   = c.id
     LEFT JOIN usuarios  ur ON i.reportado_por = ur.id
     LEFT JOIN usuarios  ua ON i.asignado_a    = ua.id
     WHERE " . implode(' AND ', $where) .
    " ORDER BY
         CASE i.severidad WHEN 'Crítica' THEN 1 WHEN 'Alta' THEN 2
                          WHEN 'Media'   THEN 3 ELSE 4 END,
         i.fecha_reporte DESC",
    $params
);

// ── Retroalimentación de beneficiarios ────────────────────────
$retroalimentacion = db_fetch_all(
    "SELECT i.descripcion, i.fecha,
            c.razon_social AS cliente,
            NULL AS proyecto,
            u.nombre || ' ' || u.apellido AS registrado_por
     FROM interacciones i
     LEFT JOIN clientes c ON i.cliente_id = c.id
     LEFT JOIN usuarios u ON i.usuario_id = u.id
     WHERE i.tipo = 'Nota'
     ORDER BY i.fecha DESC
     LIMIT 5"
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
<?php render_topbar('Puesta en Marcha', 'Validaciones técnicas e incidencias'); ?>

<div class="page-body">

    <!-- ── Cabecera ──────────────────────────────────────────── -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Incidencias</h1>
            <p class="page-subtitle">Registro, seguimiento y resolución de incidencias técnicas</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('modalNuevaIncidencia')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5"  y1="12" x2="19" y2="12"/>
            </svg>
            Reportar Incidencia
        </button>
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
                       placeholder="Buscar incidencia..."
                       value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
            </div>
            <select name="proyecto_id" class="form-control" style="width:190px;">
                <option value="">Todos los proyectos</option>
                <?php foreach ($proyectos as $p): ?>
                    <option value="<?= $p['id'] ?>"
                        <?= ($_GET['proyecto_id']??'') == $p['id'] ? 'selected':'' ?>>
                        <?= htmlspecialchars($p['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="estado" class="form-control" style="width:140px;">
                <option value="">Todos</option>
                <option value="abierta"    <?= ($_GET['estado']??'') === 'abierta'    ? 'selected':'' ?>>Abierta</option>
                <option value="en_proceso" <?= ($_GET['estado']??'') === 'en_proceso' ? 'selected':'' ?>>En Proceso</option>
                <option value="resuelta"   <?= ($_GET['estado']??'') === 'resuelta'   ? 'selected':'' ?>>Resuelta</option>
                <option value="cerrada"    <?= ($_GET['estado']??'') === 'cerrada'    ? 'selected':'' ?>>Cerrada</option>
            </select>
            <select name="severidad" class="form-control" style="width:130px;">
                <option value="">Severidad</option>
                <option value="Crítica" <?= ($_GET['severidad']??'') === 'Crítica' ? 'selected':'' ?>>Crítica</option>
                <option value="Alta"    <?= ($_GET['severidad']??'') === 'Alta'    ? 'selected':'' ?>>Alta</option>
                <option value="Media"   <?= ($_GET['severidad']??'') === 'Media'   ? 'selected':'' ?>>Media</option>
                <option value="Baja"    <?= ($_GET['severidad']??'') === 'Baja'    ? 'selected':'' ?>>Baja</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            <?php if (!empty($_GET['q'])||!empty($_GET['proyecto_id'])
                      ||!empty($_GET['estado'])||!empty($_GET['severidad'])): ?>
                <a href="marcha.php" class="btn btn-secondary btn-sm">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ── Contenido principal ───────────────────────────────── -->
    <div class="grid-2" style="gap:20px;align-items:start;">

        <!-- Lista de incidencias -->
        <div style="grid-column:1/-1;">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Registro de Incidencias</span>
                <span style="font-size:12px;color:var(--c-text-3);">
                    <?= count($incidencias) ?> registros
                </span>
            </div>

            <?php if (empty($incidencias)): ?>
                <p style="text-align:center;padding:40px;color:var(--c-text-3);">
                    No hay incidencias registradas.
                </p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Severidad</th>
                                <th>Incidencia</th>
                                <th>Proyecto</th>
                                <th>Tipo</th>
                                <th>Reportado por</th>
                                <th>Asignado a</th>
                                <th>Fecha Reporte</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($incidencias as $inc):
                            $sevColor = match($inc['severidad']) {
                                'Crítica' => '#DC2626',
                                'Alta'    => '#EA580C',
                                'Media'   => '#D97706',
                                default   => '#059669',
                            };
                            $badgeEstado = match($inc['estado']) {
                                'abierta'    => 'badge-cancelled',
                                'en_proceso' => 'badge-pending',
                                'resuelta'   => 'badge-active',
                                default      => 'badge-completed',
                            };
                            $labelEstado = match($inc['estado']) {
                                'abierta'    => 'Abierta',
                                'en_proceso' => 'En Proceso',
                                'resuelta'   => 'Resuelta',
                                default      => 'Cerrada',
                            };
                        ?>
                        <tr>
                            <td>
                                <span class="badge badge-dot"
                                      style="background:<?= $sevColor ?>22;color:<?= $sevColor ?>;">
                                    <?= htmlspecialchars($inc['severidad']) ?>
                                </span>
                            </td>
                            <td style="max-width:220px;">
                                <div style="font-weight:600;font-size:13px;
                                            overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= htmlspecialchars($inc['titulo']) ?>
                                </div>
                                <?php if ($inc['descripcion']): ?>
                                <div style="font-size:11.5px;color:var(--c-text-3);margin-top:2px;
                                            overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= htmlspecialchars(substr($inc['descripcion'], 0, 60))
                                        . (strlen($inc['descripcion']) > 60 ? '...' : '') ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:12.5px;">
                                <?= htmlspecialchars($inc['proyecto'] ?? '—') ?>
                                <?php if ($inc['proyecto_codigo']): ?>
                                    <br><span style="font-size:10.5px;color:var(--c-text-4);
                                                     font-family:monospace;">
                                        <?= htmlspecialchars($inc['proyecto_codigo']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-navy" style="font-size:11px;">
                                    <?= htmlspecialchars($inc['tipo']) ?>
                                </span>
                            </td>
                            <td style="font-size:12.5px;">
                                <?= htmlspecialchars($inc['reportado_por'] ?? '—') ?>
                            </td>
                            <td style="font-size:12.5px;">
                                <?= htmlspecialchars($inc['asignado_a'] ?? '—') ?>
                            </td>
                            <td style="font-size:12px;color:var(--c-text-3);">
                                <?= date('d/m/Y H:i', strtotime($inc['fecha_reporte'])) ?>
                                <?php if ($inc['fecha_resolucion']): ?>
                                    <br><span style="color:var(--c-success);">
                                        ✓ <?= date('d/m/Y', strtotime($inc['fecha_resolucion'])) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-dot <?= $badgeEstado ?>">
                                    <?= $labelEstado ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;gap:5px;">
                                    <button class="btn btn-secondary btn-sm"
                                            onclick="verIncidencia(<?= $inc['id'] ?>)">
                                        Ver
                                    </button>
                                    <?php if ($inc['estado'] !== 'resuelta' && $inc['estado'] !== 'cerrada'): ?>
                                    <button class="btn btn-success btn-sm"
                                            onclick="resolverIncidencia(<?= $inc['id'] ?>)">
                                        Resolver
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        </div>

        <!-- Retroalimentación de beneficiarios -->
        <div class="card" style="grid-column:1/-1;">
            <div class="card-header">
                <span class="card-title">⭐ Retroalimentación de Beneficiarios</span>
                <a href="<?= APP_URL ?>/modules/crm.php" class="btn btn-secondary btn-sm">
                    + Registrar
                </a>
            </div>
            <?php if (empty($retroalimentacion)): ?>
                <p style="text-align:center;padding:30px;color:var(--c-text-3);font-size:13px;">
                    No hay retroalimentación registrada aún.
                </p>
            <?php else: ?>
                <div class="grid-2" style="gap:16px;">
                <?php foreach ($retroalimentacion as $fb): ?>
                    <div style="background:#F9FAFB;border:1px solid var(--c-border);
                                border-radius:var(--radius-lg);padding:16px;">
                        <div style="display:flex;justify-content:space-between;
                                    align-items:flex-start;margin-bottom:10px;">
                            <div>
                                <div style="font-weight:600;font-size:13.5px;">
                                    <?= htmlspecialchars($fb['cliente'] ?? '—') ?>
                                </div>
                                <?php if ($fb['proyecto']): ?>
                                    <div style="font-size:12px;color:var(--c-text-3);margin-top:2px;">
                                        <?= htmlspecialchars($fb['proyecto']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span style="font-size:11px;color:var(--c-text-4);">
                                <?= date('d/m/Y', strtotime($fb['fecha'])) ?>
                            </span>
                        </div>
                        <p style="font-size:13px;color:var(--c-text-2);line-height:1.5;
                                  font-style:italic;">
                            "<?= htmlspecialchars($fb['descripcion']) ?>"
                        </p>
                        <div style="margin-top:8px;font-size:11.5px;color:var(--c-text-4);">
                            Registrado por: <?= htmlspecialchars($fb['registrado_por'] ?? '—') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- .grid-2 -->

</div><!-- .page-body -->
</div><!-- .main-content -->

<!-- ============================================================
     MODAL: Nueva Incidencia
     ============================================================ -->
<div class="modal-overlay" id="modalNuevaIncidencia">
    <div class="modal-box" style="max-width:680px;">
        <div class="modal-header">
            <span class="modal-title" id="modalIncTitulo">Reportar Incidencia</span>
            <button class="modal-close" onclick="closeModal('modalNuevaIncidencia')">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="inc_id">
            <div class="form-grid">
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Título <span class="required">*</span></label>
                    <input class="form-control" type="text" id="inc_titulo"
                           placeholder="Describe brevemente el problema">
                </div>
                <div class="form-group">
                    <label class="form-label">Proyecto <span class="required">*</span></label>
                    <select class="form-control" id="inc_proyecto_id">
                        <option value="">Seleccionar proyecto...</option>
                        <?php foreach ($proyectos as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tipo</label>
                    <select class="form-control" id="inc_tipo">
                        <option value="Error">Error</option>
                        <option value="Mejora">Mejora</option>
                        <option value="Consulta">Consulta</option>
                        <option value="Bloqueo">Bloqueo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Severidad</label>
                    <select class="form-control" id="inc_severidad">
                        <option value="Baja">Baja</option>
                        <option value="Media" selected>Media</option>
                        <option value="Alta">Alta</option>
                        <option value="Crítica">Crítica</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Asignar a</label>
                    <select class="form-control" id="inc_asignado_a">
                        <option value="">Sin asignar</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?= $u['id'] ?>">
                                <?= htmlspecialchars($u['nombre_completo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Descripción detallada <span class="required">*</span></label>
                    <textarea class="form-control" id="inc_descripcion" rows="4"
                              placeholder="Describe el problema con detalle, pasos para reproducirlo, impacto..."></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalNuevaIncidencia')">
                Cancelar
            </button>
            <button class="btn btn-primary" onclick="guardarIncidencia()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"/>
                    <polyline points="17,21 17,13 7,13"/>
                </svg>
                Guardar
            </button>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL: Ver / Resolver Incidencia
     ============================================================ -->
<div class="modal-overlay" id="modalVerIncidencia">
    <div class="modal-box" style="max-width:600px;">
        <div class="modal-header">
            <span class="modal-title">Detalle de Incidencia</span>
            <button class="modal-close" onclick="closeModal('modalVerIncidencia')">×</button>
        </div>
        <div class="modal-body" id="modalVerBody">
            <!-- contenido dinámico -->
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL: Resolver Incidencia
     ============================================================ -->
<div class="modal-overlay" id="modalResolver">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header">
            <span class="modal-title">Registrar Resolución</span>
            <button class="modal-close" onclick="closeModal('modalResolver')">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="res_id">
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">Estado</label>
                <select class="form-control" id="res_estado">
                    <option value="en_proceso">En Proceso</option>
                    <option value="resuelta">Resuelta</option>
                    <option value="cerrada">Cerrada</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Descripción de la solución</label>
                <textarea class="form-control" id="res_solucion" rows="4"
                          placeholder="Describe cómo se resolvió el problema..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalResolver')">Cancelar</button>
            <button class="btn btn-success" onclick="guardarResolucion()">
                ✓ Confirmar Resolución
            </button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
// ── Guardar incidencia ────────────────────────────────────────
async function guardarIncidencia() {
    const titulo      = document.getElementById('inc_titulo').value.trim();
    const proyecto_id = document.getElementById('inc_proyecto_id').value;
    const descripcion = document.getElementById('inc_descripcion').value.trim();

    if (!titulo)      { showToast('Requerido', 'El título es obligatorio.', 'error'); return; }
    if (!proyecto_id) { showToast('Requerido', 'Selecciona un proyecto.', 'error'); return; }
    if (!descripcion) { showToast('Requerido', 'La descripción es obligatoria.', 'error'); return; }

    const id = document.getElementById('inc_id').value;
    const fd = new FormData();
    fd.append('action',      id ? 'update' : 'create');
    fd.append('id',          id);
    fd.append('titulo',      titulo);
    fd.append('proyecto_id', proyecto_id);
    fd.append('tipo',        document.getElementById('inc_tipo').value);
    fd.append('severidad',   document.getElementById('inc_severidad').value);
    fd.append('asignado_a',  document.getElementById('inc_asignado_a').value);
    fd.append('descripcion', descripcion);

    try {
        const res  = await fetch('../api/incidencias_api.php', { method:'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast('Guardado', data.message, 'success');
            closeModal('modalNuevaIncidencia');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo conectar con el servidor.', 'error');
    }
}

// ── Ver detalle ───────────────────────────────────────────────
async function verIncidencia(id) {
    try {
        const res  = await fetch(`../api/incidencias_api.php?action=get&id=${id}`);
        const data = await res.json();
        if (!data.success) { showToast('Error', data.message, 'error'); return; }

        const i = data.data;
        const sevColor = {'Crítica':'#DC2626','Alta':'#EA580C','Media':'#D97706','Baja':'#059669'}[i.severidad] ?? '#1B3A6B';

        document.getElementById('modalVerBody').innerHTML = `
            <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
                <span class="badge badge-dot" style="background:${sevColor}22;color:${sevColor};">${i.severidad}</span>
                <span class="badge badge-navy">${i.tipo}</span>
                <span class="badge ${i.estado==='resuelta'?'badge-active':i.estado==='en_proceso'?'badge-pending':'badge-cancelled'} badge-dot">
                    ${i.estado.charAt(0).toUpperCase()+i.estado.slice(1).replace('_',' ')}
                </span>
            </div>
            <h3 style="font-size:16px;font-weight:700;margin-bottom:8px;">${escHtml(i.titulo)}</h3>
            <p style="font-size:13px;color:var(--c-text-2);margin-bottom:16px;line-height:1.6;">
                ${escHtml(i.descripcion)}
            </p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:12.5px;
                        background:#F9FAFB;padding:14px;border-radius:var(--radius);margin-bottom:14px;">
                <div><span style="color:var(--c-text-3);">Proyecto:</span><br>
                    <strong>${escHtml(i.proyecto??'—')}</strong></div>
                <div><span style="color:var(--c-text-3);">Reportado por:</span><br>
                    <strong>${escHtml(i.reportado_por??'—')}</strong></div>
                <div><span style="color:var(--c-text-3);">Asignado a:</span><br>
                    <strong>${escHtml(i.asignado_a??'—')}</strong></div>
                <div><span style="color:var(--c-text-3);">Fecha reporte:</span><br>
                    <strong>${new Date(i.fecha_reporte).toLocaleDateString('es-PE')}</strong></div>
            </div>
            ${i.solucion ? `
            <div style="border-left:3px solid var(--c-success);padding:12px 14px;
                        background:var(--c-success-lt);border-radius:0 var(--radius) var(--radius) 0;">
                <div style="font-size:12px;font-weight:600;color:var(--c-success);margin-bottom:4px;">
                    SOLUCIÓN APLICADA
                </div>
                <p style="font-size:13px;color:var(--c-text-2);">${escHtml(i.solucion)}</p>
            </div>` : ''}
        `;
        openModal('modalVerIncidencia');
    } catch(e) {
        showToast('Error', 'No se pudo cargar la incidencia.', 'error');
    }
}

// ── Resolver incidencia ───────────────────────────────────────
function resolverIncidencia(id) {
    document.getElementById('res_id').value      = id;
    document.getElementById('res_estado').value  = 'resuelta';
    document.getElementById('res_solucion').value= '';
    openModal('modalResolver');
}

async function guardarResolucion() {
    const id       = document.getElementById('res_id').value;
    const estado   = document.getElementById('res_estado').value;
    const solucion = document.getElementById('res_solucion').value.trim();

    const fd = new FormData();
    fd.append('action',   'resolver');
    fd.append('id',       id);
    fd.append('estado',   estado);
    fd.append('solucion', solucion);

    try {
        const res  = await fetch('../api/incidencias_api.php', { method:'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast('Resuelta', data.message, 'success');
            closeModal('modalResolver');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'Error al guardar.', 'error');
    }
}

// Limpiar modal al abrir
document.querySelector('[onclick="openModal(\'modalNuevaIncidencia\')"]')
    ?.addEventListener('click', () => {
        ['inc_id','inc_titulo','inc_descripcion'].forEach(id =>
            document.getElementById(id).value = '');
        document.getElementById('inc_proyecto_id').value = '';
        document.getElementById('inc_tipo').value        = 'Error';
        document.getElementById('inc_severidad').value   = 'Media';
        document.getElementById('inc_asignado_a').value  = '';
        document.getElementById('modalIncTitulo').textContent = 'Reportar Incidencia';
    });
</script>