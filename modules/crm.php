<?php
// ============================================================
//  modules/crm.php — Gestión de Clientes (CRM)
//  Ruta: C:\xampp\htdocs\Corfiem_Cesar\modules\crm.php
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session     = require_auth();
$page_title  = 'CRM — Clientes';
$page_active = 'crm';

$sectores = db_fetch_all("SELECT id, nombre FROM sectores ORDER BY nombre");

// ── Filtros GET ───────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if (!empty($_GET['q'])) {
    $where[]  = '(c.razon_social ILIKE ? OR c.contacto_nombre ILIKE ? OR c.email_principal ILIKE ?)';
    $q        = '%' . trim($_GET['q']) . '%';
    $params   = array_merge($params, [$q, $q, $q]);
}
if (!empty($_GET['sector_id'])) {
    $where[]  = 'c.sector_id = ?';
    $params[] = (int)$_GET['sector_id'];
}
if (isset($_GET['activo']) && $_GET['activo'] !== '') {
    $where[]  = 'c.activo = ?';
    $params[] = ($_GET['activo'] === '1') ? 'true' : 'false';
}

// ── Listado de clientes ───────────────────────────────────────
$clientes = db_fetch_all(
    "SELECT c.id, c.razon_social, c.ruc_nit, c.contacto_nombre,
            c.email_principal, c.telefono, c.ciudad, c.activo,
            s.nombre AS sector,
            (SELECT COUNT(*) FROM proyectos p
             WHERE p.cliente_id = c.id)                          AS total_proyectos,
            (SELECT COUNT(*) FROM proyectos p
             WHERE p.cliente_id = c.id
               AND p.estado_id NOT IN (4,5,6))                   AS proyectos_activos,
            (SELECT MAX(i.fecha) FROM interacciones i
             WHERE i.cliente_id = c.id)                          AS ultima_interaccion
     FROM clientes c
     LEFT JOIN sectores s ON c.sector_id = s.id
     WHERE " . implode(' AND ', $where) .
    " ORDER BY c.razon_social",
    $params
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
<?php render_topbar('CRM — Clientes', 'Gestión de relaciones con clientes'); ?>

<div class="page-body">

    <!-- ── Filtros ───────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:20px;padding:16px 20px;">
        <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <div class="search-wrapper" style="flex:1;min-width:220px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input class="search-input" type="text" name="q"
                       placeholder="Buscar empresa, contacto o email..."
                       value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
            </div>
            <select name="sector_id" class="form-control" style="width:160px;">
                <option value="">Todos los sectores</option>
                <?php foreach ($sectores as $s): ?>
                    <option value="<?= $s['id'] ?>"
                        <?= ($_GET['sector_id'] ?? '') == $s['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="activo" class="form-control" style="width:140px;">
                <option value="">Todos</option>
                <option value="1" <?= ($_GET['activo'] ?? '') === '1' ? 'selected' : '' ?>>Activos</option>
                <option value="0" <?= ($_GET['activo'] ?? '') === '0' ? 'selected' : '' ?>>Inactivos</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            <?php if (!empty($_GET['q']) || !empty($_GET['sector_id']) || isset($_GET['activo'])): ?>
                <a href="crm.php" class="btn btn-secondary btn-sm">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ── Tabla de clientes ─────────────────────────────────── -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Directorio de Clientes</span>
            <button class="btn btn-primary btn-sm" onclick="openModal('modalNuevoCliente')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5"  y1="12" x2="19" y2="12"/>
                </svg>
                Nuevo Cliente
            </button>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Sector</th>
                        <th>Contacto</th>
                        <th>Ciudad</th>
                        <th>Proyectos</th>
                        <th>Última Interacción</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($clientes)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:40px;color:var(--c-text-3);">
                            No hay clientes registrados. Crea el primero con
                            <strong>Nuevo Cliente</strong>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($clientes as $c): ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;font-size:13.5px;">
                                <?= htmlspecialchars($c['razon_social']) ?>
                            </div>
                            <?php if ($c['ruc_nit']): ?>
                                <div style="font-size:11px;color:var(--c-text-3);">
                                    RUC: <?= htmlspecialchars($c['ruc_nit']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-navy">
                                <?= htmlspecialchars($c['sector'] ?? '—') ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($c['contacto_nombre']): ?>
                                <div style="font-size:13px;">
                                    <?= htmlspecialchars($c['contacto_nombre']) ?>
                                </div>
                                <div style="font-size:11px;color:var(--c-text-3);">
                                    <?= htmlspecialchars($c['email_principal'] ?? '') ?>
                                </div>
                            <?php else: ?>
                                <span style="color:var(--c-text-4);">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:13px;">
                            <?= htmlspecialchars($c['ciudad'] ?? '—') ?>
                        </td>
                        <td>
                            <div style="display:flex;flex-direction:column;gap:3px;">
                                <span style="font-weight:600;font-size:13px;">
                                    <?= $c['total_proyectos'] ?> total
                                </span>
                                <?php if ($c['proyectos_activos'] > 0): ?>
                                    <span class="badge badge-active badge-dot" style="font-size:10px;">
                                        <?= $c['proyectos_activos'] ?> activos
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="font-size:12px;color:var(--c-text-3);">
                            <?= $c['ultima_interaccion']
                                ? date('d/m/Y', strtotime($c['ultima_interaccion']))
                                : 'Sin registro' ?>
                        </td>
                        <td>
                            <span class="badge badge-dot
                                <?= $c['activo'] ? 'badge-active' : 'badge-cancelled' ?>">
                                <?= $c['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn btn-secondary btn-sm"
                                        onclick="verCliente(<?= $c['id'] ?>)">
                                    Ver
                                </button>
                                <button class="btn btn-secondary btn-sm"
                                        onclick="nuevaInteraccion(<?= $c['id'] ?>,
                                        '<?= htmlspecialchars(addslashes($c['razon_social'])) ?>')">
                                    + Nota
                                </button>
                                <?php if ($session['usuario_rol'] === 'Admin'): ?>
                                <button class="btn btn-sm"
                                        style="background:#DC2626;color:#fff;border-color:#DC2626;"
                                        onclick="confirmarEliminarCliente(<?= $c['id'] ?>,
                                        '<?= htmlspecialchars(addslashes($c['razon_social'])) ?>',
                                        <?= (int)$c['total_proyectos'] ?>)">
                                    🗑
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── Interacciones recientes ───────────────────────────── -->
    <div class="card" style="margin-top:20px;">
        <div class="card-header">
            <span class="card-title">💬 Interacciones Recientes</span>
        </div>
        <?php
        $interacciones = db_fetch_all(
            "SELECT i.tipo, i.asunto, i.descripcion, i.fecha,
                    c.razon_social AS cliente,
                    u.nombre || ' ' || u.apellido AS usuario
             FROM interacciones i
             LEFT JOIN clientes c ON i.cliente_id  = c.id
             LEFT JOIN usuarios u ON i.usuario_id  = u.id
             ORDER BY i.fecha DESC
             LIMIT 8"
        );
        $iconos_tipo = [
            'Llamada'  => '📞',
            'Email'    => '📧',
            'Reunión'  => '🤝',
            'Visita'   => '🏢',
            'WhatsApp' => '💬',
            'Nota'     => '📝',
        ];
        ?>
        <?php if (empty($interacciones)): ?>
            <p style="text-align:center;padding:30px;color:var(--c-text-3);font-size:13px;">
                No hay interacciones registradas aún.
            </p>
        <?php else: ?>
            <div class="activity-list">
            <?php foreach ($interacciones as $int): ?>
                <div class="activity-item">
                    <div class="activity-avatar">
                        <span style="font-size:16px;">
                            <?= $iconos_tipo[$int['tipo']] ?? '📝' ?>
                        </span>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:13px;font-weight:600;">
                            <?= htmlspecialchars($int['cliente'] ?? '—') ?>
                            <span style="font-weight:400;color:var(--c-text-3);margin-left:6px;">
                                · <?= htmlspecialchars($int['tipo']) ?>
                            </span>
                        </div>
                        <?php if ($int['asunto']): ?>
                            <div style="font-size:12.5px;color:var(--c-text-2);margin-top:2px;">
                                <?= htmlspecialchars($int['asunto']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($int['descripcion']): ?>
                            <div style="font-size:12px;color:var(--c-text-3);margin-top:3px;">
                                <?= htmlspecialchars(substr($int['descripcion'], 0, 100))
                                    . (strlen($int['descripcion']) > 100 ? '...' : '') ?>
                            </div>
                        <?php endif; ?>
                        <div class="activity-time">
                            <?= date('d/m/Y H:i', strtotime($int['fecha'])) ?>
                            · <?= htmlspecialchars($int['usuario'] ?? '') ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div><!-- .page-body -->
</div><!-- .main-content -->

<!-- ============================================================
     MODAL: Nuevo Cliente
     ============================================================ -->
<div class="modal-overlay" id="modalNuevoCliente">
    <div class="modal-box">
        <div class="modal-header">
            <span class="modal-title">Nuevo Cliente</span>
            <button class="modal-close" onclick="closeModal('modalNuevoCliente')">×</button>
        </div>




<div class="modal-body">
    <form id="formNuevoCliente">
        <input type="hidden" name="prospecto_id" id="prospecto_id">
        
        <!-- Aviso de prospecto seleccionado -->
        <div id="prospectoSeleccionado" style="display:none;background:#DBEAFE;border:1px solid #3B82F6;border-radius:6px;padding:12px;margin-bottom:16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2" style="width:20px;height:20px;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <span style="font-size:13px;font-weight:600;color:#1E40AF;">
                        Prospecto seleccionado: <span id="prospectoNombre"></span>
                    </span>
                </div>
                <button type="button" class="btn btn-sm" 
                        style="background:#3B82F6;color:#fff;padding:4px 10px;font-size:11px;"
                        onclick="limpiarProspecto()">
                    Limpiar
                </button>
            </div>
        </div>
        
        <div class="form-grid">
            <div class="form-group" style="grid-column:1/-1;position:relative;">
                <label class="form-label">Razón Social <span class="required">*</span></label>
                <input class="form-control" type="text" name="razon_social" id="razonSocial"
                       placeholder="Buscar prospecto o escribir nueva empresa..."
                       autocomplete="off"
                       oninput="buscarProspectos(this.value)"
                       required>
                
                <!-- Dropdown de sugerencias -->
                <div id="prospectosSugerencias" 
                     style="display:none;position:absolute;top:100%;left:0;right:0;
                            background:#fff;border:1px solid var(--c-border);border-top:none;
                            border-radius:0 0 6px 6px;max-height:250px;overflow-y:auto;
                            box-shadow:0 4px 12px rgba(0,0,0,0.1);z-index:1000;margin-top:-1px;">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">RUC / NIT</label>
                <input class="form-control" type="text" name="ruc_nit" id="rucNit"
                       placeholder="20123456789">
            </div>
            <div class="form-group">
                <label class="form-label">Sector</label>
                <select class="form-control" name="sector_id" id="sectorId">
                    <option value="">Seleccionar...</option>
                    <?php foreach ($sectores as $s): ?>
                        <option value="<?= $s['id'] ?>">
                            <?= htmlspecialchars($s['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Ciudad</label>
                <input class="form-control" type="text" name="ciudad" id="ciudad" placeholder="Lima">
            </div>
            <div class="form-group">
                <label class="form-label">Teléfono</label>
                <input class="form-control" type="tel" name="telefono" id="telefono"
                       placeholder="+51 999 999 999">
            </div>
            <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label">Email Principal</label>
                <input class="form-control" type="email" name="email_principal" id="emailPrincipal"
                       placeholder="contacto@empresa.com">
            </div>
            <!-- Separador -->
            <div style="grid-column:1/-1;border-top:1px solid var(--c-border);
                        padding-top:14px;margin-top:4px;">
                <span style="font-size:12px;font-weight:600;color:var(--c-text-3);
                             text-transform:uppercase;letter-spacing:0.06em;">
                    Contacto Principal
                </span>
            </div>
            <div class="form-group">
                <label class="form-label">Nombre del Contacto</label>
                <input class="form-control" type="text" name="contacto_nombre" id="contactoNombre"
                       placeholder="Juan Pérez">
            </div>
            <div class="form-group">
                <label class="form-label">Cargo</label>
                <input class="form-control" type="text" name="contacto_cargo" id="contactoCargo"
                       placeholder="Gerente General">
            </div>
            <div class="form-group">
                <label class="form-label">Email del Contacto</label>
                <input class="form-control" type="email" name="contacto_email" id="contactoEmail"
                       placeholder="juan@empresa.com">
            </div>
            <div class="form-group">
                <label class="form-label">Teléfono del Contacto</label>
                <input class="form-control" type="tel" name="contacto_telefono" id="contactoTelefono">
            </div>
            <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label">Notas</label>
                <textarea class="form-control" name="notas" id="notas" rows="2"
                          placeholder="Observaciones relevantes..."></textarea>
            </div>
        </div>
    </form>
</div>
       




        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalNuevoCliente')">
                Cancelar
            </button>
            <button class="btn btn-primary" onclick="guardarCliente()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"/>
                    <polyline points="17,21 17,13 7,13"/>
                    <polyline points="7,3 7,8 15,8"/>
                </svg>
                Guardar Cliente
            </button>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL: Confirmar Eliminación de Cliente
     ============================================================ -->
<div class="modal-overlay" id="modalEliminarCliente">
    <div class="modal-box" style="max-width:460px;">
        <div class="modal-header" style="border-bottom:2px solid #DC2626;">
            <span class="modal-title" style="color:#DC2626;">⚠️ Eliminar Cliente</span>
            <button class="modal-close" onclick="closeModal('modalEliminarCliente')">×</button>
        </div>
        <div class="modal-body">
            <p style="font-size:13.5px;color:var(--c-text);margin:0 0 12px;">
                Estás a punto de eliminar permanentemente al cliente:
            </p>
            <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;
                        padding:12px 16px;margin-bottom:16px;">
                <div style="font-weight:700;font-size:14px;color:#DC2626;" id="elimClienteNombre"></div>
                <div style="font-size:12px;color:#991B1B;margin-top:4px;" id="elimClienteProyectos"></div>
            </div>
            <p style="font-size:12.5px;color:var(--c-text-3);margin:0 0 16px;">
                Esta acción <strong>no se puede deshacer</strong>. Se eliminarán todos los proyectos,
                entregables, pagos e incidencias asociados.
            </p>
            <div class="form-group" style="margin:0;">
                <label class="form-label">
                    Escribe <strong id="elimClienteNombreConfirm" style="color:#DC2626;"></strong> para confirmar:
                </label>
                <input class="form-control" type="text" id="inputConfirmNombre"
                       placeholder="Escribe el nombre exacto..."
                       oninput="validarConfirmNombre()">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalEliminarCliente')">
                Cancelar
            </button>
            <button class="btn btn-sm" id="btnConfirmEliminar" disabled
                    style="background:#DC2626;color:#fff;border-color:#DC2626;
                           opacity:0.4;cursor:not-allowed;padding:8px 18px;"
                    onclick="ejecutarEliminarCliente()">
                🗑 Eliminar definitivamente
            </button>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL: Nueva Interacción
     ============================================================ -->
<div class="modal-overlay" id="modalInteraccion">
    <div class="modal-box" style="max-width:500px;">
        <div class="modal-header">
            <span class="modal-title">Registrar Interacción</span>
            <button class="modal-close" onclick="closeModal('modalInteraccion')">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="interaccion_cliente_id">
            <div class="form-group" style="margin-bottom:14px;">
                <label class="form-label">Cliente</label>
                <input class="form-control" type="text"
                       id="interaccion_cliente_nombre" disabled>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Tipo</label>
                    <select class="form-control" id="interaccion_tipo">
                        <option value="Nota">Nota</option>
                        <option value="Llamada">Llamada</option>
                        <option value="Email">Email</option>
                        <option value="Reunión">Reunión</option>
                        <option value="Visita">Visita</option>
                        <option value="WhatsApp">WhatsApp</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Asunto</label>
                    <input class="form-control" type="text" id="interaccion_asunto"
                           placeholder="Tema de la interacción">
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Descripción</label>
                    <textarea class="form-control" id="interaccion_desc" rows="4"
                              placeholder="Detalle de la interacción..."></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalInteraccion')">
                Cancelar
            </button>
            <button class="btn btn-primary" onclick="guardarInteraccion()">
                Guardar
            </button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
// ── Guardar cliente ───────────────────────────────────────────
async function guardarCliente() {
    const form = document.getElementById('formNuevoCliente');
    const fd   = new FormData(form);
    fd.append('action', 'create');

    try {
        const res  = await fetch('../api/clientes_api.php', { method:'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast('Cliente guardado', data.message, 'success');
            closeModal('modalNuevoCliente');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo conectar con el servidor.', 'error');
    }
}

// ── Ver cliente ───────────────────────────────────────────────
function verCliente(id) {
    window.location.href = `cliente_detalle.php?id=${id}`;
}

// ── Nueva interacción ─────────────────────────────────────────
function nuevaInteraccion(clienteId, nombre) {
    document.getElementById('interaccion_cliente_id').value    = clienteId;
    document.getElementById('interaccion_cliente_nombre').value = nombre;
    document.getElementById('interaccion_desc').value          = '';
    document.getElementById('interaccion_asunto').value        = '';
    openModal('modalInteraccion');
}

// ── Guardar interacción ───────────────────────────────────────
async function guardarInteraccion() {
    const fd = new FormData();
    fd.append('action',      'create_interaccion');
    fd.append('cliente_id',  document.getElementById('interaccion_cliente_id').value);
    fd.append('tipo',        document.getElementById('interaccion_tipo').value);
    fd.append('asunto',      document.getElementById('interaccion_asunto').value);
    fd.append('descripcion', document.getElementById('interaccion_desc').value);

    try {
        const res  = await fetch('../api/clientes_api.php', { method:'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast('Interacción registrada', '', 'success');
            closeModal('modalInteraccion');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'Error al guardar.', 'error');
    }
}

// ── Autocompletado de prospectos ──────────────────────────────
let searchTimeout = null;
let prospectoSeleccionado = null;
let prospectosCacheados = [];

function buscarProspectos(query) {
    clearTimeout(searchTimeout);

    if (prospectoSeleccionado) {
        limpiarProspecto();
    }

    const suggestionsDiv = document.getElementById('prospectosSugerencias');

    if (query.length < 2) {
        suggestionsDiv.style.display = 'none';
        return;
    }

    searchTimeout = setTimeout(async () => {
        try {
            const res = await fetch(`../api/clientes_api.php?action=buscar_prospectos&q=${encodeURIComponent(query)}`);
            const data = await res.json();

            if (data.success && data.data.length > 0) {
                prospectosCacheados = data.data;
                mostrarSugerencias(data.data);
            } else {
                prospectosCacheados = [];
                suggestionsDiv.innerHTML = `
                    <div style="padding:12px;text-align:center;color:var(--c-text-3);font-size:12px;">
                        No se encontraron prospectos. Puedes crear un cliente nuevo.
                    </div>
                `;
                suggestionsDiv.style.display = 'block';
            }
        } catch(e) {
            console.error('Error buscando prospectos:', e);
            suggestionsDiv.style.display = 'none';
        }
    }, 300);
}

function mostrarSugerencias(prospectos) {
    const suggestionsDiv = document.getElementById('prospectosSugerencias');

    let html = '';
    prospectos.forEach(p => {
        const empresa = p.empresa || p.nombre_contacto;
        const subtexto = [p.ruc ? `RUC: ${p.ruc}` : null, p.tipo_servicio || null, `Estado: ${p.estado}`].filter(Boolean).join(' · ');

        html += `
            <div onclick="seleccionarProspecto(${p.id})"
                 style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #F3F4F6;
                        transition:background 0.15s;"
                 onmouseover="this.style.background='#F9FAFB'"
                 onmouseout="this.style.background='#fff'">
                <div style="font-weight:600;font-size:13px;color:var(--c-text-1);">
                    ${empresa}
                </div>
                ${subtexto ? `
                <div style="font-size:11px;color:var(--c-text-3);margin-top:2px;">
                    ${subtexto}
                </div>
                ` : ''}
            </div>
        `;
    });

    suggestionsDiv.innerHTML = html;
    suggestionsDiv.style.display = 'block';
}

function seleccionarProspecto(id) {
    const prospecto = prospectosCacheados.find(p => p.id === id);
    if (!prospecto) return;

    prospectoSeleccionado = prospecto;

    // Auto-rellenar campos
    document.getElementById('prospecto_id').value      = prospecto.id;
    document.getElementById('razonSocial').value        = prospecto.empresa || prospecto.nombre_contacto;
    document.getElementById('rucNit').value             = prospecto.ruc || '';
    document.getElementById('telefono').value           = prospecto.telefono || '';
    document.getElementById('emailPrincipal').value     = prospecto.email || '';
    document.getElementById('contactoNombre').value     = prospecto.nombre_contacto || '';
    document.getElementById('contactoEmail').value      = prospecto.email || '';
    document.getElementById('contactoTelefono').value   = prospecto.telefono || '';
    document.getElementById('ciudad').value             = prospecto.direccion || '';
    document.getElementById('notas').value              = prospecto.tipo_servicio ? `Servicio requerido: ${prospecto.tipo_servicio}` : '';

    // Mostrar aviso
    document.getElementById('prospectoNombre').textContent          = prospecto.empresa || prospecto.nombre_contacto;
    document.getElementById('prospectoSeleccionado').style.display  = 'block';

    // Ocultar sugerencias
    document.getElementById('prospectosSugerencias').style.display = 'none';

    showToast('Prospecto cargado', 'Los datos se han auto-rellenado', 'success');
}

function limpiarProspecto() {
    prospectoSeleccionado = null;
    document.getElementById('prospecto_id').value = '';
    document.getElementById('prospectoSeleccionado').style.display = 'none';
    
    // Limpiar todos los campos
    document.getElementById('formNuevoCliente').reset();
    
    showToast('Limpiado', 'Puedes crear un cliente nuevo desde cero', 'info');
}

// ── Eliminar cliente ──────────────────────────────────────────
let elimClienteId   = null;
let elimClienteTexto = '';

function confirmarEliminarCliente(id, nombre, totalProyectos) {
    elimClienteId    = id;
    elimClienteTexto = nombre;

    document.getElementById('elimClienteNombre').textContent       = nombre;
    document.getElementById('elimClienteNombreConfirm').textContent = nombre;
    document.getElementById('inputConfirmNombre').value            = '';
    document.getElementById('elimClienteProyectos').textContent    =
        totalProyectos > 0
            ? `⚠️ Se eliminarán ${totalProyectos} proyecto(s) asociado(s).`
            : 'Este cliente no tiene proyectos asociados.';

    const btn = document.getElementById('btnConfirmEliminar');
    btn.disabled = true;
    btn.style.opacity = '0.4';
    btn.style.cursor  = 'not-allowed';

    openModal('modalEliminarCliente');
}

function validarConfirmNombre() {
    const input = document.getElementById('inputConfirmNombre').value.trim();
    const btn   = document.getElementById('btnConfirmEliminar');
    const ok    = input === elimClienteTexto;
    btn.disabled      = !ok;
    btn.style.opacity = ok ? '1'            : '0.4';
    btn.style.cursor  = ok ? 'pointer'      : 'not-allowed';
}

async function ejecutarEliminarCliente() {
    if (!elimClienteId) return;

    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id',     elimClienteId);

    try {
        const res  = await fetch('../api/clientes_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            showToast('Eliminado', data.message, 'success');
            closeModal('modalEliminarCliente');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo conectar con el servidor.', 'error');
    }
}

// Cerrar sugerencias al hacer clic fuera
document.addEventListener('click', function(e) {
    const suggestionsDiv = document.getElementById('prospectosSugerencias');
    const inputRazonSocial = document.getElementById('razonSocial');
    
    if (suggestionsDiv && !suggestionsDiv.contains(e.target) && e.target !== inputRazonSocial) {
        suggestionsDiv.style.display = 'none';
    }
});

</script>