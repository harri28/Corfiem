<?php
// ============================================================
//  modules/cotizacion_detalle.php — Editor de Cotización
//  Ruta: C:\xampp\htdocs\Corfiem_Cesar\modules\cotizacion_detalle.php
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session = require_auth();
$uid     = (int)$session['usuario_id'];
$page_title  = 'Editor de Cotización';
$page_active = 'previas';

$cotizacion_id = (int)($_GET['id'] ?? 0);
if ($cotizacion_id <= 0) {
    header('Location: previas.php');
    exit;
}

// Cargar cotización con datos del prospecto
$cot = db_fetch_one(
    "SELECT c.*, 
            p.nombre_contacto, p.empresa, p.ruc, p.telefono, p.email, p.direccion,
            p.id AS prospecto_id
     FROM cotizaciones c
     JOIN prospectos p ON c.prospecto_id = p.id
     WHERE c.id = ?",
    [$cotizacion_id]
);

if (!$cot) {
    header('Location: previas.php');
    exit;
}

// Items de la cotización
$items = db_fetch_all(
    "SELECT * FROM cotizacion_items 
     WHERE cotizacion_id = ? 
     ORDER BY orden, id",
    [$cotizacion_id]
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
/* Tabs */
.tabs-container {
    display: flex;
    gap: 4px;
    border-bottom: 2px solid var(--c-border);
    margin-bottom: 16px;
}
.tab-btn {
    padding: 8px 16px;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: var(--c-text-3);
    transition: all 0.2s;
}
.tab-btn.active {
    color: var(--c-navy);
    border-bottom-color: var(--c-navy);
}
.tab-btn:hover {
    color: var(--c-navy);
    background: #F9FAFB;
}
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}

/* Items compactos */
.item-row {
    display: grid;
    grid-template-columns: 1fr 80px 100px 100px 35px;
    gap: 8px;
    align-items: center;
    padding: 8px;
    background: #FAFAFA;
    border: 1px solid var(--c-border);
    border-radius: 4px;
    margin-bottom: 6px;
    font-size: 12px;
}

/* Totales compactos */
.totales-box {
    background: #F0F9FF;
    border: 2px solid #BAE6FD;
    border-radius: 6px;
    padding: 12px;
    margin-top: 12px;
}

/* Logo preview */
.logo-preview {
    max-width: 150px;
    max-height: 60px;
    border: 1px solid var(--c-border);
    border-radius: 4px;
    padding: 4px;
}
</style>

<div class="main-content">
<?php render_topbar('Cotización ' . $cot['numero'], $cot['empresa'] ?: $cot['nombre_contacto']); ?>

<div class="page-body">
    
    <!-- Barra de acciones superior -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding:12px;background:#fff;border:1px solid var(--c-border);border-radius:6px;">
        <div style="display:flex;gap:8px;align-items:center;">
            <?php
            $estado_colores = [
                'borrador' => '#6B7280',
                'enviada' => '#3B82F6',
                'aceptada' => '#059669',
                'rechazada' => '#DC2626',
                'vencida' => '#9CA3AF'
            ];
            ?>
            <span class="badge" 
                  style="background:<?= $estado_colores[$cot['estado']] ?>22;
                         color:<?= $estado_colores[$cot['estado']] ?>;
                         font-size:12px;padding:4px 10px;">
                <?= strtoupper($cot['estado']) ?>
            </span>
            
            <?php if ($cot['estado'] === 'enviada'): ?>
                <button class="btn btn-success btn-sm" onclick="aceptarCotizacion()" style="padding:4px 10px;font-size:12px;">
                    ✓ Aceptar
                </button>
                <button class="btn btn-danger btn-sm" onclick="rechazarCotizacion()" style="padding:4px 10px;font-size:12px;">
                    ✗ Rechazar
                </button>
            <?php endif; ?>
        </div>
        
        <div style="display:flex;gap:6px;">
            <button class="btn btn-secondary btn-sm" onclick="previsualizarPDF()" style="padding:4px 10px;font-size:12px;">
                👁 Previsualizar
            </button>
            <button class="btn btn-primary btn-sm" onclick="generarPDF()" style="padding:4px 10px;font-size:12px;">
                📄 Descargar PDF
            </button>
            <button class="btn btn-sm" onclick="enviarWhatsApp()"
                    style="padding:4px 10px;font-size:12px;background:#25D366;color:#fff;border:1px solid #1ebe5d;">
                💬 Enviar por WhatsApp
            </button>
            <a href="prospecto_detalle.php?id=<?= $cot['prospecto_id'] ?>"
               class="btn btn-secondary btn-sm" style="padding:4px 10px;font-size:12px;">
                ← Volver
            </a>
        </div>
    </div>

    <!-- Contenedor con tabs -->
    <div class="card">
        <div style="padding:16px 20px 0 20px;">
            <div class="tabs-container">
                <button class="tab-btn active" onclick="switchTab('servicios')">🛠 Servicios</button>
                <button class="tab-btn" onclick="switchTab('configuracion')">⚙️ Configurar Plantilla</button>
            </div>
        </div>

        <div style="padding:0 20px 20px 20px;">
            
            <!-- TAB: Servicios -->
            <div id="tab-servicios" class="tab-content active">
                <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
                    <button class="btn btn-primary btn-sm" onclick="openModal('modalNuevoItem')" style="font-size:12px;padding:6px 12px;">
                        + Agregar Servicio
                    </button>
                </div>
                
                <div id="listaItems">
                    <?php if (empty($items)): ?>
                        <div style="text-align:center;padding:30px;color:var(--c-text-3);font-size:13px;">
                            No hay servicios agregados.
                        </div>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                        <div class="item-row" id="item-<?= $item['id'] ?>">
                            <div>
                                <div style="font-weight:600;font-size:12px;margin-bottom:2px;">
                                    <?= htmlspecialchars($item['descripcion']) ?>
                                </div>
                                <?php if ($item['notas']): ?>
                                <div style="font-size:10px;color:var(--c-text-3);">
                                    <?= htmlspecialchars($item['notas']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div style="text-align:center;">
                                <?= number_format($item['cantidad'], 2) ?>
                            </div>
                            <div style="text-align:right;">
                                <?= MONEDA_SIMBOLO ?> <?= number_format($item['precio_unitario'], 2) ?>
                            </div>
                            <div style="text-align:right;font-weight:700;">
                                <?= MONEDA_SIMBOLO ?> <?= number_format($item['importe'], 2) ?>
                            </div>
                            <div style="text-align:center;">
                                <button class="btn btn-sm" 
                                        style="background:var(--c-danger);color:#fff;padding:3px 6px;font-size:11px;"
                                        onclick="eliminarItem(<?= $item['id'] ?>)">
                                    🗑
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Totales -->
                    <div class="totales-box">
                        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #93C5FD;font-size:12px;">
                            <span>Subtotal:</span>
                            <span style="font-weight:600;" id="displaySubtotal">
                                <?= MONEDA_SIMBOLO ?> <?= number_format($cot['subtotal'], 2) ?>
                            </span>
                        </div>
                        
                        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #93C5FD;align-items:center;font-size:12px;">
                            <span>Descuento:</span>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <input type="number" class="form-control" id="inputDescuento" 
                                    value="<?= $cot['descuento'] ?>" 
                                    style="width:100px;text-align:right;font-size:12px;padding:4px 8px;" 
                                    step="0.01" min="0"
                                    onchange="actualizarDescuento()">
                            </div>
                        </div>
                        
                        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #93C5FD;align-items:center;font-size:12px;">
                            <div style="display:flex;align-items:center;gap:6px;">
                                <input type="checkbox" id="checkAplicaIgv" 
                                    <?= $cot['aplica_igv'] ? 'checked' : '' ?>
                                    onchange="toggleIGV()"
                                    style="cursor:pointer;">
                                <label for="checkAplicaIgv" style="cursor:pointer;margin:0;">
                                    Aplicar IGV (18%)
                                </label>
                            </div>
                            <span style="font-weight:600;" id="displayIgv">
                                <?= MONEDA_SIMBOLO ?> <?= number_format($cot['igv'], 2) ?>
                            </span>
                        </div>
                        
                        <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:14px;">
                            <span style="font-weight:700;color:var(--c-navy);">TOTAL:</span>
                            <span style="font-weight:700;color:var(--c-navy);font-size:16px;" id="displayTotal">
                                <?= MONEDA_SIMBOLO ?> <?= number_format($cot['total'], 2) ?>
                            </span>
                        </div>
                    </div>
            </div>

            <!-- TAB: Configuración -->
            <div id="tab-configuracion" class="tab-content">
                <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                    <div class="form-group">
                        <label class="form-label" style="font-size:11px;">Logo de la Empresa</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="file" id="logoInput" accept="image/*" 
                                   onchange="subirLogo(this)"
                                   style="font-size:12px;">
                            <div id="logoPreview"></div>
                        </div>
                        <div style="font-size:10px;color:var(--c-text-3);margin-top:4px;">
                            Aparecerá en la esquina superior derecha del PDF
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" style="font-size:11px;">Observaciones Adicionales</label>
                        <textarea class="form-control" id="observaciones" rows="3" 
                                  style="font-size:12px;padding:6px 10px;"><?= htmlspecialchars($cot['observaciones'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group" style="grid-column:1/-1;">
                        <button class="btn btn-secondary btn-sm" onclick="guardarConfiguracion()" style="font-size:12px;padding:6px 12px;">
                            💾 Guardar Configuración
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div><!-- .page-body -->
</div><!-- .main-content -->

<!-- MODAL: Nuevo Item -->
<div class="modal-overlay" id="modalNuevoItem">
    <div class="modal-box" style="max-width:600px;">
        <div class="modal-header">
            <span class="modal-title">Agregar Servicio</span>
            <button class="modal-close" onclick="closeModal('modalNuevoItem')">×</button>
        </div>
        <div class="modal-body">
            <form id="formNuevoItem">
                <input type="hidden" name="cotizacion_id" value="<?= $cotizacion_id ?>">
                
                <div class="form-group">
                    <label class="form-label">Descripción del Servicio <span class="required">*</span></label>
                    <textarea class="form-control" name="descripcion" rows="2" 
                              placeholder="Ej: Consultoría ambiental..." required></textarea>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Cantidad</label>
                        <input class="form-control" type="number" name="cantidad" 
                               value="1" min="0.01" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Unidad</label>
                        <select class="form-control" name="unidad">
                            <option value="servicio">Servicio</option>
                            <option value="día">Día</option>
                            <option value="mes">Mes</option>
                            <option value="unidad">Unidad</option>
                            <option value="hora">Hora</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Precio Unitario (<?= MONEDA_SIMBOLO ?>) <span class="required">*</span></label>
                        <input class="form-control" type="number" name="precio_unitario" 
                               min="0" step="0.01" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notas Adicionales (opcional)</label>
                    <textarea class="form-control" name="notas" rows="1"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalNuevoItem')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarItem()">Agregar Servicio</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
const cotizacionId = <?= $cotizacion_id ?>;
const cotTelefono  = <?= json_encode($cot['telefono'] ?? '') ?>;
const cotEmpresa   = <?= json_encode($cot['empresa'] ?? $cot['nombre_contacto'] ?? '') ?>;
const cotNumero    = <?= json_encode($cot['numero'] ?? '') ?>;
const cotTotal     = <?= json_encode(number_format((float)($cot['total'] ?? 0), 2)) ?>;
const cotMoneda    = <?= json_encode(MONEDA_SIMBOLO) ?>;
let logoBase64 = null;

// ── Tabs ──────────────────────────────────────────────────────
function switchTab(tab) {
    // Desactivar todos
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Activar el seleccionado
    document.querySelector(`[onclick="switchTab('${tab}')"]`).classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}

// ── Subir logo ────────────────────────────────────────────────
function subirLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            logoBase64 = e.target.result;
            document.getElementById('logoPreview').innerHTML = 
                `<img src="${e.target.result}" class="logo-preview">`;
            showToast('Logo cargado', 'Se incluirá en el PDF', 'success');
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// ── Guardar configuración ─────────────────────────────────────
async function guardarConfiguracion() {
    const fd = new FormData();
    fd.append('action', 'update_cotizacion');
    fd.append('id', cotizacionId);
    fd.append('fecha_vencimiento', document.getElementById('fechaVencimiento').value);
    fd.append('validez_oferta', document.getElementById('validezOferta').value);
    fd.append('tiempo_entrega', document.getElementById('tiempoEntrega').value);
    fd.append('condiciones_pago', document.getElementById('condicionesPago').value);
    fd.append('observaciones', document.getElementById('observaciones').value);
    
    try {
        const res = await fetch('../api/previas_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            showToast('Configuración guardada', data.message, 'success');
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch (e) {
        showToast('Error', 'No se pudo guardar la configuración.', 'error');
    }
}

// ── Agregar item ──────────────────────────────────────────────
async function guardarItem() {
    const form = document.getElementById('formNuevoItem');
    const fd = new FormData(form);
    fd.append('action', 'add_item');
    
    try {
        const res = await fetch('../api/previas_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            showToast('Servicio agregado', data.message, 'success');
            closeModal('modalNuevoItem');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch (e) {
        showToast('Error', 'No se pudo agregar el servicio.', 'error');
    }
}

// ── Eliminar item ─────────────────────────────────────────────
async function eliminarItem(id) {
    if (!confirm('¿Eliminar este servicio?')) return;
    
    const fd = new FormData();
    fd.append('action', 'delete_item');
    fd.append('id', id);
    
    try {
        const res = await fetch('../api/previas_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            showToast('Servicio eliminado', data.message, 'success');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch (e) {
        showToast('Error', 'No se pudo eliminar el servicio.', 'error');
    }
}

// ── Actualizar descuento ──────────────────────────────────────
async function actualizarDescuento() {
    const descuento = parseFloat(document.getElementById('inputDescuento').value) || 0;
    
    const fd = new FormData();
    fd.append('action', 'update_cotizacion');
    fd.append('id', cotizacionId);
    fd.append('descuento', descuento);
    
    try {
        const res = await fetch('../api/previas_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            location.reload();
        }
    } catch (e) {
        showToast('Error', 'No se pudo actualizar el descuento.', 'error');
    }
}

// ── Toggle IGV ────────────────────────────────────────────────
// ── Toggle IGV ────────────────────────────────────────────────
async function toggleIGV() {
    const aplicaIgv = document.getElementById('checkAplicaIgv').checked;
    
    const fd = new FormData();
    fd.append('action', 'update_cotizacion');
    fd.append('id', cotizacionId);
    fd.append('aplica_igv', aplicaIgv ? 'true' : 'false');
    
    try {
        const res = await fetch('../api/previas_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            showToast('IGV ' + (aplicaIgv ? 'activado' : 'desactivado'), 'Recalculando totales...', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast('Error', data.message, 'error');
            document.getElementById('checkAplicaIgv').checked = !aplicaIgv;
        }
    } catch (e) {
        showToast('Error', 'No se pudo actualizar.', 'error');
        document.getElementById('checkAplicaIgv').checked = !aplicaIgv;
    }
}

// ── Aceptar cotización ────────────────────────────────────────
async function aceptarCotizacion() {
    if (!confirm('¿Marcar como aceptada? Esto cambiará el estado del prospecto.')) return;
    
    const fd = new FormData();
    fd.append('action', 'aceptar_cotizacion');
    fd.append('id', cotizacionId);
    
    try {
        const res = await fetch('../api/previas_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            showToast('¡Cotización aceptada!', data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch (e) {
        showToast('Error', 'No se pudo aceptar la cotización.', 'error');
    }
}

// ── Rechazar cotización ───────────────────────────────────────
async function rechazarCotizacion() {
    const motivo = prompt('Motivo del rechazo:');
    if (!motivo) return;
    
    const fd = new FormData();
    fd.append('action', 'rechazar_cotizacion');
    fd.append('id', cotizacionId);
    fd.append('motivo', motivo);
    
    try {
        const res = await fetch('../api/previas_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            showToast('Cotización rechazada', data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch (e) {
        showToast('Error', 'No se pudo rechazar la cotización.', 'error');
    }
}

// ── Previsualizar PDF ─────────────────────────────────────────
function previsualizarPDF() {
    window.open('../includes/generar_cotizacion_pdf.php?id=' + cotizacionId + '&preview=1', '_blank');
}

// ── Generar PDF (Descarga) ────────────────────────────────────
function generarPDF() {
    window.open('../includes/generar_cotizacion_pdf.php?id=' + cotizacionId, '_blank');
}

// ── Enviar por WhatsApp ───────────────────────────────────────
function enviarWhatsApp() {
    // Limpiar teléfono: solo dígitos
    let tel = cotTelefono.replace(/\D/g, '');

    // Si empieza con 0, quitarlo (número local peruano)
    if (tel.startsWith('0')) tel = tel.substring(1);

    // Agregar código de país Perú si no lo tiene
    if (tel.length <= 9) tel = '51' + tel;

    const mensaje =
        `Estimado(a) ${cotEmpresa},\n\n` +
        `Le hacemos llegar la cotización *${cotNumero}* por un monto de *${cotMoneda} ${cotTotal}*.\n\n` +
        `Quedamos a su disposición para cualquier consulta o coordinación.\n\n` +
        `Saludos,\n<?= addslashes(APP_NAME) ?>`;

    if (!tel || tel.length < 10) {
        // Sin teléfono registrado: abrir WhatsApp sin número
        window.open('https://wa.me/?text=' + encodeURIComponent(mensaje), '_blank');
    } else {
        window.open('https://wa.me/' + tel + '?text=' + encodeURIComponent(mensaje), '_blank');
    }
}
</script>