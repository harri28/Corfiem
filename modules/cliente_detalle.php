<?php
// ============================================================
//  modules/cliente_detalle.php — Detalle de Cliente
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session     = require_auth();
$uid         = (int)$session['usuario_id'];
$page_title  = 'Detalle de Cliente';
$page_active = 'crm';

$cliente_id = (int)($_GET['id'] ?? 0);
if ($cliente_id <= 0) {
    header('Location: crm.php');
    exit;
}

$cliente = db_fetch_one(
    "SELECT c.*, s.nombre AS sector
     FROM clientes c
     LEFT JOIN sectores s ON c.sector_id = s.id
     WHERE c.id = ?",
    [$cliente_id]
);

if (!$cliente) {
    header('Location: crm.php');
    exit;
}

$proyectos = db_fetch_all(
    "SELECT p.id, p.nombre, p.codigo, p.avance_porcentaje,
            ep.nombre AS estado_nombre, ep.color AS estado_color,
            p.fecha_inicio, p.fecha_fin_estimada
     FROM proyectos p
     LEFT JOIN estados_proyecto ep ON p.estado_id = ep.id
     WHERE p.cliente_id = ?
     ORDER BY p.created_at DESC",
    [$cliente_id]
);

$interacciones = db_fetch_all(
    "SELECT i.tipo, i.asunto, i.descripcion, i.fecha,
            u.nombre || ' ' || u.apellido AS usuario
     FROM interacciones i
     LEFT JOIN usuarios u ON i.usuario_id = u.id
     WHERE i.cliente_id = ?
     ORDER BY i.fecha DESC
     LIMIT 20",
    [$cliente_id]
);

$sectores = db_fetch_all("SELECT id, nombre FROM sectores ORDER BY nombre");
$iconos_tipo = [
    'Llamada'  => '📞',
    'Email'    => '📧',
    'Reunión'  => '🤝',
    'Visita'   => '🏢',
    'WhatsApp' => '💬',
    'Nota'     => '📝',
];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
.cliente-header {
    background: linear-gradient(135deg, #1B3A6B 0%, #2C5282 100%);
    color: white;
    padding: 30px;
    border-radius: 8px;
    margin-bottom: 24px;
}
.tab-buttons {
    display: flex;
    gap: 8px;
    border-bottom: 2px solid var(--c-border);
    margin-bottom: 24px;
}
.tab-btn {
    padding: 12px 20px;
    background: none;
    border: none;
    font-size: 14px;
    font-weight: 600;
    color: var(--c-text-3);
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
}
.tab-btn.active {
    color: var(--c-navy);
    border-bottom-color: var(--c-navy);
}
.tab-content { display: none; }
.tab-content.active { display: block; }
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.info-item {
    padding: 14px 16px;
    background: #F9FAFB;
    border-radius: 6px;
    border-left: 4px solid var(--c-navy);
}
.info-label {
    font-size: 11px;
    color: var(--c-text-3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}
.info-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--c-text);
}
</style>

<div class="main-content">
<?php render_topbar($cliente['razon_social'], 'Ficha de Cliente'); ?>

<div class="page-body">

    <!-- Header -->
    <div class="cliente-header">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div>
                <div style="font-size:12px;opacity:0.8;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">
                    <?= htmlspecialchars($cliente['sector'] ?? 'Sin sector') ?>
                    <?php if ($cliente['ruc_nit']): ?>
                        · RUC: <?= htmlspecialchars($cliente['ruc_nit']) ?>
                    <?php endif; ?>
                </div>
                <h1 style="font-size:26px;font-weight:700;margin:0 0 8px;">
                    <?= htmlspecialchars($cliente['razon_social']) ?>
                </h1>
                <?php if ($cliente['ciudad']): ?>
                    <div style="font-size:14px;opacity:0.85;">
                        📍 <?= htmlspecialchars($cliente['ciudad']) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <span class="badge <?= $cliente['activo'] ? 'badge-active' : 'badge-cancelled' ?> badge-dot"
                      style="background:rgba(255,255,255,0.15);color:#fff;">
                    <?= $cliente['activo'] ? 'Activo' : 'Inactivo' ?>
                </span>
                <a href="crm.php" class="btn btn-secondary btn-sm">← Volver</a>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tab-buttons" style="padding:0 4px;">
        <button class="tab-btn active" onclick="switchTab('info', this)">📋 Información</button>
        <button class="tab-btn" onclick="switchTab('proyectos', this)">📁 Proyectos (<?= count($proyectos) ?>)</button>
        <button class="tab-btn" onclick="switchTab('interacciones', this)">💬 Interacciones (<?= count($interacciones) ?>)</button>
        <button class="tab-btn" onclick="switchTab('editar', this)">✏️ Editar</button>
    </div>

    <!-- TAB: INFORMACIÓN -->
    <div id="tab-info" class="tab-content active">
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Teléfono</div>
                <div class="info-value"><?= htmlspecialchars($cliente['telefono'] ?? '—') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Email Principal</div>
                <div class="info-value" style="font-size:13px;">
                    <?php if ($cliente['email_principal']): ?>
                        <a href="mailto:<?= htmlspecialchars($cliente['email_principal']) ?>"
                           style="color:var(--c-navy);">
                            <?= htmlspecialchars($cliente['email_principal']) ?>
                        </a>
                    <?php else: ?>—<?php endif; ?>
                </div>
            </div>
            <?php if ($cliente['sitio_web']): ?>
            <div class="info-item">
                <div class="info-label">Sitio Web</div>
                <div class="info-value" style="font-size:13px;">
                    <a href="<?= htmlspecialchars($cliente['sitio_web']) ?>" target="_blank"
                       style="color:var(--c-navy);">
                        <?= htmlspecialchars($cliente['sitio_web']) ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <div class="info-label">Ciudad</div>
                <div class="info-value"><?= htmlspecialchars($cliente['ciudad'] ?? '—') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Sector</div>
                <div class="info-value"><?= htmlspecialchars($cliente['sector'] ?? '—') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Cliente desde</div>
                <div class="info-value"><?= date('d/m/Y', strtotime($cliente['created_at'])) ?></div>
            </div>
        </div>

        <!-- Contacto principal -->
        <?php if ($cliente['contacto_nombre']): ?>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <span class="card-title">👤 Contacto Principal</span>
            </div>
            <div style="padding:16px 20px;">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Nombre</div>
                        <div class="info-value"><?= htmlspecialchars($cliente['contacto_nombre']) ?></div>
                    </div>
                    <?php if ($cliente['contacto_cargo']): ?>
                    <div class="info-item">
                        <div class="info-label">Cargo</div>
                        <div class="info-value"><?= htmlspecialchars($cliente['contacto_cargo']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($cliente['contacto_email']): ?>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value" style="font-size:13px;">
                            <a href="mailto:<?= htmlspecialchars($cliente['contacto_email']) ?>"
                               style="color:var(--c-navy);">
                                <?= htmlspecialchars($cliente['contacto_email']) ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($cliente['contacto_telefono']): ?>
                    <div class="info-item">
                        <div class="info-label">Teléfono</div>
                        <div class="info-value"><?= htmlspecialchars($cliente['contacto_telefono']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($cliente['notas']): ?>
        <div class="card">
            <div class="card-header"><span class="card-title">📝 Notas</span></div>
            <div style="padding:16px 20px;font-size:13.5px;color:var(--c-text-2);line-height:1.6;">
                <?= nl2br(htmlspecialchars($cliente['notas'])) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB: PROYECTOS -->
    <div id="tab-proyectos" class="tab-content">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Proyectos del Cliente</span>
                <span style="font-size:12px;color:var(--c-text-3);"><?= count($proyectos) ?> registros</span>
            </div>
            <?php if (empty($proyectos)): ?>
                <p style="text-align:center;padding:40px;color:var(--c-text-3);">
                    Este cliente no tiene proyectos registrados.
                </p>
            <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th>Avance</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin Est.</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($proyectos as $p): ?>
                    <tr>
                        <td style="font-family:monospace;font-size:12px;">
                            <?= htmlspecialchars($p['codigo'] ?? '—') ?>
                        </td>
                        <td style="font-weight:600;font-size:13.5px;">
                            <?= htmlspecialchars($p['nombre']) ?>
                        </td>
                        <td>
                            <span class="badge badge-dot"
                                  style="background:<?= $p['estado_color'] ?>22;color:<?= $p['estado_color'] ?>;">
                                <?= htmlspecialchars($p['estado_nombre'] ?? '—') ?>
                            </span>
                        </td>
                        <td style="min-width:120px;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="flex:1;background:#E5E7EB;border-radius:4px;height:6px;overflow:hidden;">
                                    <div style="background:#10B981;height:100%;width:<?= $p['avance_porcentaje'] ?>%;"></div>
                                </div>
                                <span style="font-size:12px;font-weight:600;min-width:32px;">
                                    <?= $p['avance_porcentaje'] ?>%
                                </span>
                            </div>
                        </td>
                        <td style="font-size:12px;color:var(--c-text-3);">
                            <?= $p['fecha_inicio'] ? date('d/m/Y', strtotime($p['fecha_inicio'])) : '—' ?>
                        </td>
                        <td style="font-size:12px;color:var(--c-text-3);">
                            <?= $p['fecha_fin_estimada'] ? date('d/m/Y', strtotime($p['fecha_fin_estimada'])) : '—' ?>
                        </td>
                        <td>
                            <a href="proyecto_detalle.php?id=<?= $p['id'] ?>"
                               class="btn btn-secondary btn-sm">Ver</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: INTERACCIONES -->
    <div id="tab-interacciones" class="tab-content">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Historial de Interacciones</span>
                <button class="btn btn-primary btn-sm" onclick="openModal('modalInteraccion')">
                    + Nueva Interacción
                </button>
            </div>
            <?php if (empty($interacciones)): ?>
                <p style="text-align:center;padding:40px;color:var(--c-text-3);">
                    No hay interacciones registradas.
                </p>
            <?php else: ?>
                <div class="activity-list">
                <?php foreach ($interacciones as $int): ?>
                    <div class="activity-item">
                        <div class="activity-avatar">
                            <span style="font-size:16px;"><?= $iconos_tipo[$int['tipo']] ?? '📝' ?></span>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:13px;font-weight:600;">
                                <?= htmlspecialchars($int['tipo']) ?>
                                <?php if ($int['asunto']): ?>
                                    <span style="font-weight:400;color:var(--c-text-2);margin-left:6px;">
                                        — <?= htmlspecialchars($int['asunto']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($int['descripcion']): ?>
                                <div style="font-size:12.5px;color:var(--c-text-3);margin-top:3px;">
                                    <?= htmlspecialchars(substr($int['descripcion'], 0, 120))
                                        . (strlen($int['descripcion']) > 120 ? '...' : '') ?>
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
    </div>

    <!-- TAB: EDITAR -->
    <div id="tab-editar" class="tab-content">
        <div class="card">
            <div style="padding:20px;">
                <h3 style="font-size:16px;font-weight:700;margin-bottom:20px;">Editar Cliente</h3>
                <form id="formEditarCliente">
                    <input type="hidden" name="id" value="<?= $cliente_id ?>">
                    <div class="form-grid">
                        <div class="form-group" style="grid-column:1/-1;">
                            <label class="form-label">Razón Social <span class="required">*</span></label>
                            <input class="form-control" type="text" name="razon_social"
                                   value="<?= htmlspecialchars($cliente['razon_social']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">RUC / NIT</label>
                            <input class="form-control" type="text" name="ruc_nit"
                                   value="<?= htmlspecialchars($cliente['ruc_nit'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sector</label>
                            <select class="form-control" name="sector_id">
                                <option value="">Seleccionar...</option>
                                <?php foreach ($sectores as $s): ?>
                                    <option value="<?= $s['id'] ?>"
                                        <?= $s['id'] == $cliente['sector_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Ciudad</label>
                            <input class="form-control" type="text" name="ciudad"
                                   value="<?= htmlspecialchars($cliente['ciudad'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Teléfono</label>
                            <input class="form-control" type="tel" name="telefono"
                                   value="<?= htmlspecialchars($cliente['telefono'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Principal</label>
                            <input class="form-control" type="email" name="email_principal"
                                   value="<?= htmlspecialchars($cliente['email_principal'] ?? '') ?>">
                        </div>
                        <div style="grid-column:1/-1;border-top:1px solid var(--c-border);
                                    padding-top:14px;margin-top:4px;">
                            <span style="font-size:12px;font-weight:600;color:var(--c-text-3);
                                         text-transform:uppercase;letter-spacing:0.06em;">
                                Contacto Principal
                            </span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nombre del Contacto</label>
                            <input class="form-control" type="text" name="contacto_nombre"
                                   value="<?= htmlspecialchars($cliente['contacto_nombre'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cargo</label>
                            <input class="form-control" type="text" name="contacto_cargo"
                                   value="<?= htmlspecialchars($cliente['contacto_cargo'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email del Contacto</label>
                            <input class="form-control" type="email" name="contacto_email"
                                   value="<?= htmlspecialchars($cliente['contacto_email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Teléfono del Contacto</label>
                            <input class="form-control" type="tel" name="contacto_telefono"
                                   value="<?= htmlspecialchars($cliente['contacto_telefono'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column:1/-1;">
                            <label class="form-label">Notas</label>
                            <textarea class="form-control" name="notas" rows="3"><?= htmlspecialchars($cliente['notas'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--c-border);
                                display:flex;gap:12px;">
                        <button type="button" class="btn btn-primary" onclick="guardarCliente()">
                            💾 Guardar Cambios
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="switchTab('info', null)">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div><!-- .page-body -->
</div><!-- .main-content -->

<!-- MODAL: Nueva Interacción -->
<div class="modal-overlay" id="modalInteraccion">
    <div class="modal-box" style="max-width:500px;">
        <div class="modal-header">
            <span class="modal-title">Registrar Interacción</span>
            <button class="modal-close" onclick="closeModal('modalInteraccion')">×</button>
        </div>
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Tipo</label>
                    <select class="form-control" id="int_tipo">
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
                    <input class="form-control" type="text" id="int_asunto"
                           placeholder="Tema de la interacción">
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Descripción</label>
                    <textarea class="form-control" id="int_descripcion" rows="4"
                              placeholder="Detalle de la interacción..."></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalInteraccion')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarInteraccion()">Guardar</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
const clienteId = <?= $cliente_id ?>;

function switchTab(tab, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    if (btn) btn.classList.add('active');
    else document.querySelector(`.tab-btn[onclick*="'${tab}'"]`)?.classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}

async function guardarCliente() {
    const form = document.getElementById('formEditarCliente');
    const fd   = new FormData(form);
    fd.append('action', 'update');

    try {
        const res  = await fetch('../api/clientes_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast('Guardado', 'Cliente actualizado correctamente.', 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo conectar con el servidor.', 'error');
    }
}

async function guardarInteraccion() {
    const fd = new FormData();
    fd.append('action',      'create_interaccion');
    fd.append('cliente_id',  clienteId);
    fd.append('tipo',        document.getElementById('int_tipo').value);
    fd.append('asunto',      document.getElementById('int_asunto').value);
    fd.append('descripcion', document.getElementById('int_descripcion').value);

    try {
        const res  = await fetch('../api/clientes_api.php', { method: 'POST', body: fd });
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
</script>
