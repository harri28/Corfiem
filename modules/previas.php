<?php
// ============================================================
//  modules/previas.php — Actividades Previas
//  Ruta: C:\xampp\htdocs\Corfiem_Cesar\modules\previas.php
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session = require_auth();
$uid     = (int)$session['usuario_id'];
$page_title  = 'Actividades Previas';
$page_active = 'previas';

// ── Métricas ──────────────────────────────────────────────────
$metricas = db_fetch_one("SELECT * FROM vw_metricas_previas");

// ── Prospectos ────────────────────────────────────────────────
$prospectos = db_fetch_all(
    "SELECT * FROM vw_prospectos_resumen 
     ORDER BY created_at DESC 
     LIMIT 100"
);

// ── Usuarios para asignación ─────────────────────────────────
$usuarios = db_fetch_all(
    "SELECT id, nombre || ' ' || apellido AS nombre_completo 
     FROM usuarios WHERE activo = TRUE ORDER BY nombre"
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
.estado-nuevo { background: #DBEAFE; color: #1E40AF; }
.estado-en_evaluacion { background: #FEF3C7; color: #92400E; }
.estado-propuesta_enviada { background: #E0E7FF; color: #3730A3; }
.estado-aceptado { background: #D1FAE5; color: #065F46; }
.estado-rechazado { background: #FEE2E2; color: #991B1B; }
.estado-archivado { background: #F3F4F6; color: #6B7280; }

.prioridad-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}
.prioridad-baja { background: #E5E7EB; color: #6B7280; }
.prioridad-media { background: #FEF3C7; color: #92400E; }
.prioridad-alta { background: #FED7AA; color: #C2410C; }
.prioridad-urgente { background: #FEE2E2; color: #991B1B; }
</style>

<div class="main-content">
<?php render_topbar('Actividades Previas', 'Gestión de prospectos y pre-proyectos'); ?>

<div class="page-body">

    <!-- ── KPIs ──────────────────────────────────────────────── -->
    <div class="kpi-grid" style="margin-bottom:24px;">
        <div class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $metricas['total_prospectos'] ?></div>
            <div class="kpi-label">Total Prospectos</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon" style="background:#FEF3C7;">
                <svg viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12,6 12,12 16,14"/>
                </svg>
            </div>
            <div class="kpi-value" style="color:#D97706;"><?= $metricas['en_evaluacion'] ?></div>
            <div class="kpi-label">En Evaluación</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon" style="background:#DBEAFE;">
                <svg viewBox="0 0 24 24" fill="none" stroke="#1E40AF" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14,2 14,8 20,8"/>
                </svg>
            </div>
            <div class="kpi-value" style="color:#1E40AF;"><?= $metricas['propuestas_enviadas'] ?></div>
            <div class="kpi-label">Propuestas Enviadas</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon" style="background:var(--c-success-lt);">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--c-success)" stroke-width="2">
                    <polyline points="20,6 9,17 4,12"/>
                </svg>
            </div>
            <div class="kpi-value" style="color:var(--c-success);"><?= $metricas['aceptados'] ?></div>
            <div class="kpi-label">Aceptados</div>
            <?php if ($metricas['tasa_conversion']): ?>
            <div style="font-size:11px;color:var(--c-success);margin-top:4px;">
                ↗ <?= $metricas['tasa_conversion'] ?>% conversión
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Lista de prospectos ───────────────────────────────── -->
    <div class="card">
        <div class="card-header">
            <div>
                <span class="card-title">Prospectos (<?= count($prospectos) ?>)</span>
            </div>
            <div style="display:flex;gap:12px;align-items:center;">
                <div class="search-wrapper" style="width:300px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input class="search-input" type="text" id="searchProspectos"
                           placeholder="Buscar prospecto..." onkeyup="filtrarProspectos()">
                </div>

                <select class="form-control" style="width:150px;" id="filterEstado" onchange="filtrarProspectos()">
                    <option value="">Todos los estados</option>
                    <option value="nuevo">Nuevo</option>
                    <option value="en_evaluacion">En Evaluación</option>
                    <option value="propuesta_enviada">Propuesta Enviada</option>
                    <option value="aceptado">Aceptado</option>
                    <option value="rechazado">Rechazado</option>
                    <option value="archivado">Archivado</option>
                </select>

                <button class="btn btn-primary btn-sm" onclick="openModal('modalNuevoProspecto')"
                        style="padding:6px 14px;font-size:13px;height:34px;line-height:1;box-sizing:border-box;">
                    + Nuevo Prospecto
                </button>

                <button class="btn btn-sm" onclick="abrirModalCotizacion()"
                        style="padding:6px 14px;font-size:13px;height:34px;line-height:1;box-sizing:border-box;background:#00BB2D;color:#fff;border:1px solid #009924;font-weight:600;">
                    📄 Nueva Cotización
                </button>
            </div>
        </div>

        <div class="table-wrapper">
            <table id="tableProspectos">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Contacto / Empresa</th>
                        <th>Tipo de Servicio</th>
                        <th>Estado</th>
                        <th>Prioridad</th>
                        <th>Actividades</th>
                        <th>Responsable</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($prospectos)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;padding:40px;color:var(--c-text-3);">
                            No hay prospectos registrados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($prospectos as $p): ?>
                    <tr data-estado="<?= $p['estado'] ?>">
                        <td style="font-family:monospace;font-size:12px;font-weight:700;">
                            <?= htmlspecialchars($p['codigo']) ?>
                        </td>
                        <td>
                            <div style="font-weight:600;font-size:13.5px;">
                                <?= htmlspecialchars($p['nombre_contacto']) ?>
                            </div>
                            <?php if ($p['empresa']): ?>
                            <div style="font-size:12px;color:var(--c-text-3);">
                                🏢 <?= htmlspecialchars($p['empresa']) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($p['telefono']): ?>
                            <div style="font-size:12px;color:var(--c-text-3);">
                                📱 <?= htmlspecialchars($p['telefono']) ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:13px;">
                            <?= htmlspecialchars($p['tipo_servicio'] ?? '—') ?>
                        </td>
                        <td>
                            <span class="badge estado-<?= $p['estado'] ?>" style="font-size:11px;">
                                <?= strtoupper(str_replace('_', ' ', $p['estado'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="prioridad-badge prioridad-<?= strtolower($p['prioridad']) ?>">
                                <?= strtoupper($p['prioridad']) ?>
                            </span>
                        </td>
                        <td style="font-size:12px;">
                            <?php if ($p['total_actividades'] > 0): ?>
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <div style="width:60px;height:6px;background:#E5E7EB;border-radius:3px;overflow:hidden;">
                                        <?php 
                                        $progreso = $p['total_actividades'] > 0 
                                            ? round(($p['actividades_completadas'] / $p['total_actividades']) * 100) 
                                            : 0;
                                        ?>
                                        <div style="width:<?= $progreso ?>%;height:100%;background:var(--c-success);"></div>
                                    </div>
                                    <span><?= $p['actividades_completadas'] ?>/<?= $p['total_actividades'] ?></span>
                                </div>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;">
                            <?= htmlspecialchars($p['responsable_nombre'] ?? 'Sin asignar') ?>
                        </td>
                        <td style="font-size:12px;color:var(--c-text-3);">
                            <?= date('d/m/Y', strtotime($p['created_at'])) ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:5px;">
                                <button class="btn btn-secondary btn-sm" 
                                        onclick="window.location.href='prospecto_detalle.php?id=<?= $p['id'] ?>'">
                                    Ver
                                </button>
                                <button class="btn btn-danger btn-sm"
                                        onclick="eliminarProspecto(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['empresa'] ?: $p['nombre_contacto'])) ?>')"
                                        title="Eliminar prospecto">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;">
                                        <polyline points="3,6 5,6 21,6"/>
                                        <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6m3,0V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"/>
                                    </svg>
                                </button>
                            </div>
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

<!-- ============================================================
     MODAL: Nuevo Prospecto
     ============================================================ -->
<div class="modal-overlay" id="modalNuevoProspecto">
    <div class="modal-box" style="max-width:700px;">
        <div class="modal-header">
            <span class="modal-title">Nuevo Prospecto</span>
            <button class="modal-close" onclick="closeModal('modalNuevoProspecto')">×</button>
        </div>
        <div class="modal-body">
            <form id="formNuevoProspecto">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Nombre del Contacto <span class="required">*</span></label>
                        <input class="form-control" type="text" name="nombre_contacto" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Empresa</label>
                        <input class="form-control" type="text" name="empresa">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">RUC</label>
                        <input class="form-control" type="text" name="ruc" maxlength="20">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Teléfono</label>
                        <input class="form-control" type="tel" name="telefono">
                    </div>
                    
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email">
                    </div>
                    
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Dirección</label>
                        <input class="form-control" type="text" name="direccion">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipo de Servicio</label>
                        <input class="form-control" type="text" name="tipo_servicio" 
                               placeholder="Ej: Consultoría ambiental">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Origen</label>
                        <select class="form-control" name="origen">
                            <option value="Directo">Directo</option>
                            <option value="Referido">Referido</option>
                            <option value="Web">Web</option>
                            <option value="Llamada">Llamada</option>
                            <option value="Email">Email</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Prioridad</label>
                        <select class="form-control" name="prioridad">
                            <option value="Baja">Baja</option>
                            <option value="Media" selected>Media</option>
                            <option value="Alta">Alta</option>
                            <option value="Urgente">Urgente</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Responsable</label>
                        <select class="form-control" name="responsable_id">
                            <option value="">Auto-asignar</option>
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?= $u['id'] ?>">
                                    <?= htmlspecialchars($u['nombre_completo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Fecha de Contacto</label>
                        <input class="form-control" type="date" name="fecha_contacto" 
                               value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Notas</label>
                        <textarea class="form-control" name="notas" rows="3"></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalNuevoProspecto')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarProspecto()">Crear Prospecto</button>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL: Nueva Cotización
     ============================================================ -->
<div class="modal-overlay" id="modalNuevaCotizacion">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header">
            <span class="modal-title">📄 Nueva Cotización</span>
            <button class="modal-close" onclick="closeModal('modalNuevaCotizacion')">×</button>
        </div>
        <div class="modal-body">

            <!-- Buscador -->
            <div class="form-group">
                <label class="form-label">Buscar empresa o contacto <span class="required">*</span></label>
                <div style="position:relative;">
                    <input class="form-control" type="text" id="cotBuscador"
                           placeholder="Filtrar por empresa, contacto o RUC..."
                           autocomplete="off"
                           oninput="cotBuscar(this.value)">
                    <div id="cotResultados"
                         style="display:none;position:absolute;top:100%;left:0;right:0;
                                background:#fff;border:1px solid var(--c-border);
                                border-radius:0 0 8px 8px;box-shadow:0 4px 16px rgba(0,0,0,0.1);
                                max-height:240px;overflow-y:auto;z-index:999;"></div>
                </div>
            </div>

            <!-- Empresa seleccionada -->
            <div id="cotSeleccionadoWrap" style="display:none;
                 background:#F0F9FF;border:1px solid #BAE6FD;border-radius:8px;padding:14px 16px;">
                <div style="font-size:11px;color:#0369A1;font-weight:700;
                            text-transform:uppercase;margin-bottom:6px;">Empresa seleccionada</div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:14px;font-weight:700;" id="cotEmpresaNombre"></div>
                        <div style="font-size:12px;color:var(--c-text-3);" id="cotEmpresaDetalle"></div>
                    </div>
                    <button onclick="cotLimpiarSeleccion()"
                            style="background:none;border:none;font-size:18px;cursor:pointer;
                                   color:var(--c-text-3);" title="Cambiar">×</button>
                </div>
                <input type="hidden" id="cotProspectoId" value="">
            </div>

            <!-- Sin resultados -->
            <div id="cotSinResultados" style="display:none;text-align:center;padding:16px;
                 color:var(--c-text-3);font-size:13px;background:#FAFAFA;border-radius:8px;">
                No se encontraron prospectos.<br>
                <button class="btn btn-secondary btn-sm" style="margin-top:8px;"
                        onclick="closeModal('modalNuevaCotizacion');openModal('modalNuevoProspecto')">
                    + Crear Prospecto primero
                </button>
            </div>

            <div style="font-size:11.5px;color:var(--c-text-3);margin-top:12px;">
                Solo aparecen prospectos activos. Si el cliente aún no está registrado,
                créalo primero como prospecto.
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalNuevaCotizacion')">Cancelar</button>
            <button class="btn btn-primary" id="cotBtnCrear" disabled onclick="crearCotizacion()">
                Crear Cotización →
            </button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
// ── Filtrar prospectos ────────────────────────────────────────
function filtrarProspectos() {
    const buscar = document.getElementById('searchProspectos').value.toLowerCase();
    const estado = document.getElementById('filterEstado').value;
    const filas = document.querySelectorAll('#tableProspectos tbody tr');
    
    filas.forEach(fila => {
        const texto = fila.textContent.toLowerCase();
        const estadoFila = fila.dataset.estado;
        
        const matchBusqueda = texto.includes(buscar);
        const matchEstado = !estado || estadoFila === estado;
        
        fila.style.display = (matchBusqueda && matchEstado) ? '' : 'none';
    });
}

// ── Guardar prospecto ─────────────────────────────────────────
async function guardarProspecto() {
    const form = document.getElementById('formNuevoProspecto');
    const fd = new FormData(form);
    fd.append('action', 'create_prospecto');
    
    try {
        const res = await fetch('../api/previas_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            showToast('Prospecto creado', data.message, 'success');
            closeModal('modalNuevoProspecto');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch (e) {
        showToast('Error', 'No se pudo guardar el prospecto.', 'error');
    }
}

// ── Nueva Cotización ──────────────────────────────────────────
let cotTodosProspectos = [];   // cache de la lista completa

async function cotCargarTodos() {
    const res  = document.getElementById('cotResultados');
    const sinR = document.getElementById('cotSinResultados');
    res.innerHTML = '<div style="padding:12px 14px;color:var(--c-text-3);font-size:13px;">Cargando...</div>';
    res.style.display = 'block';

    try {
        const r    = await fetch('../api/previas_api.php?action=list_prospectos');
        const data = await r.json();

        if (!data.success || data.data.length === 0) {
            res.style.display  = 'none';
            sinR.style.display = 'block';
            return;
        }

        cotTodosProspectos = data.data;
        cotRenderLista(cotTodosProspectos);
    } catch(e) {
        res.innerHTML = '<div style="padding:12px;color:#DC2626;">Error al cargar prospectos.</div>';
    }
}

function cotRenderLista(lista) {
    const res  = document.getElementById('cotResultados');
    const sinR = document.getElementById('cotSinResultados');

    if (lista.length === 0) {
        res.style.display  = 'none';
        sinR.style.display = 'block';
        return;
    }

    sinR.style.display = 'none';
    res.innerHTML = lista.map(p => `
        <div onclick="cotSeleccionar(${p.id}, '${escCot(p.empresa || p.nombre_contacto)}',
                                     '${escCot(p.nombre_contacto)}', '${escCot(p.ruc || '')}',
                                     '${escCot(p.estado)}')"
             style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--c-border);
                    transition:background 0.15s;"
             onmouseover="this.style.background='#F0F9FF'"
             onmouseout="this.style.background=''">
            <div style="font-weight:600;font-size:13px;">
                🏢 ${escHtmlCot(p.empresa || p.nombre_contacto)}
            </div>
            <div style="font-size:11.5px;color:var(--c-text-3);margin-top:2px;
                        display:flex;gap:10px;flex-wrap:wrap;">
                ${p.nombre_contacto && p.nombre_contacto !== (p.empresa||'') ? `<span>👤 ${escHtmlCot(p.nombre_contacto)}</span>` : ''}
                ${p.ruc  ? `<span>RUC: ${escHtmlCot(p.ruc)}</span>` : ''}
                <span style="background:#DBEAFE;color:#1E40AF;padding:1px 7px;
                             border-radius:8px;font-size:10px;font-weight:600;">
                    ${p.estado.replace(/_/g,' ').toUpperCase()}
                </span>
            </div>
        </div>`
    ).join('');
    res.style.display = 'block';
}

function cotBuscar(q) {
    if (!q.trim()) {
        cotRenderLista(cotTodosProspectos);
        return;
    }
    const term = q.toLowerCase();
    const filtrados = cotTodosProspectos.filter(p =>
        (p.empresa       || '').toLowerCase().includes(term) ||
        (p.nombre_contacto || '').toLowerCase().includes(term) ||
        (p.ruc           || '').toLowerCase().includes(term)
    );
    cotRenderLista(filtrados);
}

function cotSeleccionar(id, empresa, contacto, ruc, estado) {
    document.getElementById('cotProspectoId').value      = id;
    document.getElementById('cotEmpresaNombre').textContent  = empresa;
    document.getElementById('cotEmpresaDetalle').textContent =
        [contacto !== empresa ? contacto : null, ruc ? 'RUC: ' + ruc : null]
        .filter(Boolean).join(' · ');
    document.getElementById('cotSeleccionadoWrap').style.display = 'block';
    document.getElementById('cotResultados').style.display       = 'none';
    document.getElementById('cotBuscador').value                 = empresa;
    document.getElementById('cotBtnCrear').disabled              = false;
}

function cotLimpiarSeleccion() {
    document.getElementById('cotProspectoId').value          = '';
    document.getElementById('cotSeleccionadoWrap').style.display = 'none';
    document.getElementById('cotBuscador').value             = '';
    document.getElementById('cotBtnCrear').disabled          = true;
    document.getElementById('cotResultados').style.display   = 'none';
    document.getElementById('cotSinResultados').style.display= 'none';
    document.getElementById('cotBuscador').focus();
}

async function crearCotizacion() {
    const pid = document.getElementById('cotProspectoId').value;
    if (!pid) { return; }

    const btn = document.getElementById('cotBtnCrear');
    btn.disabled     = true;
    btn.textContent  = 'Creando...';

    const fd = new FormData();
    fd.append('action', 'create_cotizacion');
    fd.append('prospecto_id', pid);

    try {
        const res  = await fetch('../api/previas_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            window.location.href = `cotizacion_detalle.php?id=${data.id}`;
        } else {
            showToast('Error', data.message, 'error');
            btn.disabled    = false;
            btn.textContent = 'Crear Cotización →';
        }
    } catch(e) {
        showToast('Error', 'No se pudo crear la cotización.', 'error');
        btn.disabled    = false;
        btn.textContent = 'Crear Cotización →';
    }
}

function abrirModalCotizacion() {
    cotLimpiarSeleccion();
    openModal('modalNuevaCotizacion');
    cotCargarTodos();
}

// Limpiar modal al cerrar
document.getElementById('modalNuevaCotizacion').addEventListener('click', function(e) {
    if (e.target === this) cotLimpiarSeleccion();
});

function escCot(s)    { return (s||'').replace(/'/g,"\\'").replace(/"/g,'&quot;'); }
function escHtmlCot(s){ return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// ── Eliminar prospecto individual ─────────────────────────────
async function eliminarProspecto(id, nombre) {
    if (!confirm(`¿Estás seguro de eliminar el prospecto "${nombre}"?\n\nEsta acción eliminará también:\n• Cotizaciones asociadas\n• Actividades previas\n• Historial de seguimiento\n\nEsta acción NO se puede deshacer.`)) {
        return;
    }
    
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    
    try {
        const res = await fetch('../api/previas_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            showToast('Eliminado', data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo conectar con el servidor.', 'error');
        console.error(e);
    }
}
</script>