<?php
// ============================================================
//  modules/prospecto_detalle.php — Detalle de Prospecto
//  Ruta: C:\xampp\htdocs\Corfiem_Cesar\modules\prospecto_detalle.php
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session = require_auth();
$uid     = (int)$session['usuario_id'];
$page_title  = 'Detalle de Prospecto';
$page_active = 'previas';

$prospecto_id = (int)($_GET['id'] ?? 0);
if ($prospecto_id <= 0) {
    header('Location: previas.php');
    exit;
}

// Cargar prospecto
$prospecto = db_fetch_one(
    "SELECT * FROM vw_prospectos_resumen WHERE id = ?",
    [$prospecto_id]
);

if (!$prospecto) {
    header('Location: previas.php');
    exit;
}

// Cotizaciones
$cotizaciones = db_fetch_all(
    "SELECT c.*,
     (SELECT COUNT(*) FROM cotizacion_items WHERE cotizacion_id = c.id) AS total_items
     FROM cotizaciones c
     WHERE c.prospecto_id = ?
     ORDER BY c.created_at DESC",
    [$prospecto_id]
);

// Actividades
$actividades = db_fetch_all(
    "SELECT a.*, u.nombre || ' ' || u.apellido AS responsable_nombre
     FROM actividades_previas a
     LEFT JOIN usuarios u ON a.responsable_id = u.id
     WHERE a.prospecto_id = ?
     ORDER BY a.fecha_programada DESC NULLS LAST, a.created_at DESC",
    [$prospecto_id]
);

// Usuarios para asignación
$usuarios = db_fetch_all(
    "SELECT id, nombre || ' ' || apellido AS nombre_completo 
     FROM usuarios WHERE activo = TRUE ORDER BY nombre"
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
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
.tab-btn:hover {
    color: var(--c-navy);
    background: rgba(27, 58, 107, 0.05);
}
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}
.actividad-card {
    background: #fff;
    border: 1px solid var(--c-border);
    border-radius: var(--radius);
    padding: 16px 18px;
    margin-bottom: 16px;
}
</style>

<div class="main-content">
<?php
    render_topbar(
        $prospecto['nombre_contacto'] ?? 'Prospecto', 
        ($prospecto['empresa'] ?? '') . ' · ' . ($prospecto['codigo'] ?? 'Sin código')
    );
 ?>

<div class="page-body">

    <!-- Info principal -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
            <div>
                <span class="card-title"><?= htmlspecialchars($prospecto['nombre_contacto']) ?></span>
                <?php if ($prospecto['empresa']): ?>
                <div style="font-size:13px;color:var(--c-text-3);margin-top:4px;">
                    🏢 <?= htmlspecialchars($prospecto['empresa']) ?>
                </div>
                <?php endif; ?>
            </div>
           <div style="display:flex;gap:8px;">
                <button class="btn btn-secondary btn-sm" onclick="openModal('modalEditarProspecto')">
                    Editar
                </button>
                <a href="previas.php" class="btn btn-secondary btn-sm">← Volver</a>
            </div>           

        </div>
        <div style="padding:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
                    gap:20px;border-top:1px solid var(--c-border);background:#FAFAFA;">
            <div>
                <div style="font-size:11px;color:var(--c-text-3);margin-bottom:4px;">Estado</div>
                <div>
                    <span class="badge estado-<?= $prospecto['estado'] ?>" style="font-size:12px;">
                        <?= strtoupper(str_replace('_', ' ', $prospecto['estado'])) ?>
                    </span>
                </div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--c-text-3);margin-bottom:4px;">Prioridad</div>
                <div>
                    <span class="prioridad-badge prioridad-<?= $prospecto['prioridad'] ?>" style="font-size:12px;">
                        <?= strtoupper($prospecto['prioridad']) ?>
                    </span>
                </div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--c-text-3);margin-bottom:4px;">Tipo de Servicio</div>
                <div style="font-size:14px;font-weight:600;">
                    <?= htmlspecialchars($prospecto['tipo_servicio'] ?? '—') ?>
                </div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--c-text-3);margin-bottom:4px;">Responsable</div>
                <div style="font-size:13px;font-weight:600;">
                    <?= htmlspecialchars($prospecto['responsable_nombre'] ?? 'Sin asignar') ?>
                </div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--c-text-3);margin-bottom:4px;">Actividades</div>
                <div style="font-size:14px;font-weight:700;">
                    <?= $prospecto['actividades_completadas'] ?>/<?= $prospecto['total_actividades'] ?>
                </div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--c-text-3);margin-bottom:4px;">Fecha Registro</div>
                <div style="font-size:13px;">
                    <?= date('d/m/Y', strtotime($prospecto['created_at'])) ?>
                </div>
            </div>
        </div>
        
        <!-- Datos de contacto -->
        <?php if ($prospecto['telefono'] || $prospecto['email'] || $prospecto['ruc']): ?>
        <div style="padding:16px 20px;border-top:1px solid var(--c-border);">
            <div style="font-size:12px;font-weight:700;color:var(--c-text-2);margin-bottom:10px;">
                DATOS DE CONTACTO
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:12px;">
                <?php if ($prospecto['telefono']): ?>
                <div style="font-size:13px;">
                    📱 <strong>Teléfono:</strong> <?= htmlspecialchars($prospecto['telefono']) ?>
                </div>
                <?php endif; ?>
                <?php if ($prospecto['email']): ?>
                <div style="font-size:13px;">
                    📧 <strong>Email:</strong> <?= htmlspecialchars($prospecto['email']) ?>
                </div>
                <?php endif; ?>
                <?php if ($prospecto['ruc']): ?>
                <div style="font-size:13px;">
                    🏢 <strong>RUC:</strong> <?= htmlspecialchars($prospecto['ruc']) ?>
                </div>
                <?php endif; ?>
                <?php if ($prospecto['direccion']): ?>
                <div style="font-size:13px;grid-column:1/-1;">
                    📍 <strong>Dirección:</strong> <?= htmlspecialchars($prospecto['direccion']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tabs -->
    <div class="card">
        <div class="tab-buttons" style="padding:16px 20px 0;">
            <button class="tab-btn active" onclick="switchTab('cotizaciones')">
                💰 Cotizaciones (<?= count($cotizaciones) ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('actividades')">
                📋 Actividades (<?= count($actividades) ?>)
            </button>
        </div>








<!-- TAB: COTIZACIONES -->
<div id="tab-cotizaciones" class="tab-content active" style="padding:20px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
        <h3 style="font-size:16px;font-weight:700;margin:0;">Cotizaciones</h3>
        <button class="btn btn-primary btn-sm" onclick="confirmarNuevaCotizacion()">
            + Nueva Cotización
        </button>
    </div>

    <?php if (empty($cotizaciones)): ?>
        <div style="text-align:center;padding:40px;color:var(--c-text-3);">
            <p style="font-size:15px;margin-bottom:8px;">No hay cotizaciones generadas.</p>
            <p style="font-size:13px;">Haz clic en "Nueva Cotización" para comenzar.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Fecha Emisión</th>
                        <th>Vencimiento</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cotizaciones as $c): ?>
                    <tr>
                        <td style="font-family:monospace;font-weight:700;">
                            <?= htmlspecialchars($c['numero']) ?>
                        </td>
                        <td style="font-size:12px;">
                            <?= date('d/m/Y', strtotime($c['fecha_emision'])) ?>
                        </td>
                        <td style="font-size:12px;">
                            <?= date('d/m/Y', strtotime($c['fecha_vencimiento'])) ?>
                        </td>
                        <td style="font-weight:700;color:var(--c-navy);">
                            <?= MONEDA_SIMBOLO ?> <?= number_format($c['total'], 2) ?>
                        </td>
                        <td>
                            <?php
                            $estado_colores = [
                                'borrador' => ['bg'=>'#F3F4F6','color'=>'#6B7280'],
                                'enviada' => ['bg'=>'#DBEAFE','color'=>'#1E40AF'],
                                'aceptada' => ['bg'=>'#D1FAE5','color'=>'#065F46'],
                                'rechazada' => ['bg'=>'#FEE2E2','color'=>'#991B1B'],
                                'vencida' => ['bg'=>'#F9FAFB','color'=>'#9CA3AF']
                            ];
                            $est = $estado_colores[$c['estado']] ?? $estado_colores['borrador'];
                            ?>
                            <span class="badge" 
                                  style="background:<?= $est['bg'] ?>;color:<?= $est['color'] ?>;font-size:11px;">
                                <?= strtoupper($c['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn btn-secondary btn-sm" 
                                        onclick="window.location.href='cotizacion_detalle.php?id=<?= $c['id'] ?>'">
                                    📝 Editar
                                </button>
                                <?php if ($c['estado'] === 'borrador'): ?>
                                <button class="btn btn-sm" 
                                        style="background:var(--c-danger);color:#fff;"
                                        onclick="eliminarCotizacion(<?= $c['id'] ?>)">
                                    🗑
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







        <!-- TAB: ACTIVIDADES -->
        <div id="tab-actividades" class="tab-content" style="padding:20px;">
            <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
                <h3 style="font-size:16px;font-weight:700;margin:0;">Actividades Previas</h3>
                <button class="btn btn-primary btn-sm" onclick="openModal('modalNuevaActividad')">
                    + Nueva Actividad
                </button>
            </div>

            <?php if (empty($actividades)): ?>
                <div style="text-align:center;padding:40px;color:var(--c-text-3);">
                    No hay actividades registradas.
                </div>
            <?php else: ?>
                <?php foreach ($actividades as $a): ?>
                <div class="actividad-card">
                    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:10px;">
                        <div style="flex:1;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                                <span class="badge badge-navy" style="font-size:11px;">
                                    <?= strtoupper(str_replace('_', ' ', $a['tipo'])) ?>
                                </span>
                                <span class="badge" style="font-size:11px;
                                      background:<?= $a['estado'] === 'completada' ? 'var(--c-success-lt)' : '#FEF3C7' ?>;
                                      color:<?= $a['estado'] === 'completada' ? 'var(--c-success)' : '#92400E' ?>;">
                                    <?= strtoupper($a['estado']) ?>
                                </span>
                            </div>
                            <div style="font-size:15px;font-weight:600;margin-bottom:6px;">
                                <?= htmlspecialchars($a['titulo']) ?>
                            </div>
                            <?php if ($a['descripcion']): ?>
                            <div style="font-size:13px;color:var(--c-text-3);">
                                <?= nl2br(htmlspecialchars($a['descripcion'])) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <button class="btn btn-secondary btn-sm" onclick="editarActividad(<?= $a['id'] ?>)">
                            Editar
                        </button>
                    </div>
                    
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
                                gap:12px;margin-top:12px;padding-top:12px;border-top:1px solid var(--c-border);
                                font-size:12px;">
                        <?php if ($a['fecha_programada']): ?>
                        <div>
                            <strong>Programada:</strong> <?= date('d/m/Y H:i', strtotime($a['fecha_programada'])) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($a['fecha_realizada']): ?>
                        <div>
                            <strong>Realizada:</strong> <?= date('d/m/Y H:i', strtotime($a['fecha_realizada'])) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($a['responsable_nombre']): ?>
                        <div>
                            <strong>Responsable:</strong> <?= htmlspecialchars($a['responsable_nombre']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div><!-- .page-body -->
</div><!-- .main-content -->

<!-- MODAL: Nueva Actividad -->
<div class="modal-overlay" id="modalNuevaActividad">
    <div class="modal-box" style="max-width:700px;">
        <div class="modal-header">
            <span class="modal-title">Nueva Actividad Previa</span>
            <button class="modal-close" onclick="closeModal('modalNuevaActividad')">×</button>
        </div>
        <div class="modal-body">
            <form id="formNuevaActividad">
                <input type="hidden" name="prospecto_id" value="<?= $prospecto_id ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Tipo de Actividad</label>
                        <select class="form-control" name="tipo">
                            <option value="diagnostico">Diagnóstico</option>
                            <option value="visita_campo">Visita de Campo</option>
                            <option value="levantamiento_info">Levantamiento de Información</option>
                            <option value="reunion">Reunión</option>
                            <option value="propuesta_tecnica">Elaboración de Propuesta</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Responsable</label>
                        <select class="form-control" name="responsable_id">
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= $u['id'] == $uid ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['nombre_completo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Título <span class="required">*</span></label>
                        <input class="form-control" type="text" name="titulo" required>
                    </div>
                    
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Objetivo</label>
                        <textarea class="form-control" name="objetivo" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Fecha Programada</label>
                        <input class="form-control" type="datetime-local" name="fecha_programada">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Duración Estimada</label>
                        <input class="form-control" type="text" name="duracion_estimada" 
                               placeholder="Ej: 2 horas">
                    </div>
                    
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Ubicación</label>
                        <input class="form-control" type="text" name="ubicacion">
                    </div>
                    
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Participantes</label>
                        <input class="form-control" type="text" name="participantes" 
                               placeholder="Nombres separados por comas">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalNuevaActividad')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarActividad()">Crear Actividad</button>
        </div>
    </div>
</div>

<!-- MODAL: Editar Prospecto (placeholder) -->
<div class="modal-overlay" id="modalEditarProspecto">
    <div class="modal-box" style="max-width:500px;">
        <div class="modal-header">
            <span class="modal-title">Editar Prospecto</span>
            <button class="modal-close" onclick="closeModal('modalEditarProspecto')">×</button>
        </div>
        <div class="modal-body">
            <p style="text-align:center;padding:20px;color:var(--c-text-3);">
                Función de edición en desarrollo.
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalEditarProspecto')">Cerrar</button>
        </div>
    </div>
</div>



<!-- MODAL: Confirmar nueva cotización -->
        <div class="modal-overlay" id="modalConfirmarCotizacion">
            <div class="modal-box" style="max-width:450px;">
                <div class="modal-header">
                    <span class="modal-title">Nueva Cotización</span>
                    <button class="modal-close" onclick="closeModal('modalConfirmarCotizacion')">×</button>
                </div>
                <div class="modal-body">
                    <div style="text-align:center;padding:20px;">
                        <div style="font-size:48px;margin-bottom:16px;">📄</div>
                        <p style="font-size:15px;margin-bottom:12px;font-weight:600;">
                            ¿Crear una nueva cotización?
                        </p>
                        <p style="font-size:13px;color:var(--c-text-3);">
                            Se generará una cotización en borrador con los datos del prospecto.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal('modalConfirmarCotizacion')">
                        Cancelar
                    </button>
                    <button class="btn btn-primary" onclick="crearCotizacion()">
                        Sí, Crear Cotización
                    </button>
                </div>
            </div>
        </div>




<?php include __DIR__ . '/../includes/footer.php'; ?>





<script>
        const prospectoId = <?= $prospecto_id ?>;

        // ── Tabs ──────────────────────────────────────────────────────
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById('tab-' + tab).classList.add('active');
        }

        // ── Guardar actividad ─────────────────────────────────────────
        async function guardarActividad() {
            const form = document.getElementById('formNuevaActividad');
            const fd = new FormData(form);
            fd.append('action', 'create_actividad');
            
            try {
                const res = await fetch('../api/previas_api.php', { method: 'POST', body: fd });
                const data = await res.json();
                
                if (data.success) {
                    showToast('Actividad creada', data.message, 'success');
                    closeModal('modalNuevaActividad');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Error', data.message, 'error');
                }
            } catch (e) {
                showToast('Error', 'No se pudo guardar la actividad.', 'error');
            }
        }




        // ── Confirmar nueva cotización ───────────────────────────────
function confirmarNuevaCotizacion() {
    openModal('modalConfirmarCotizacion');
}

// ── Crear cotización ──────────────────────────────────────────
async function crearCotizacion() {
    closeModal('modalConfirmarCotizacion');
    
    const fd = new FormData();
    fd.append('action', 'create_cotizacion');
    fd.append('prospecto_id', prospectoId);
    
    showToast('Creando cotización...', 'Por favor espera', 'info');
    
    try {
        const res = await fetch('../api/previas_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            showToast('Cotización creada', 'Redirigiendo al editor...', 'success');
            setTimeout(() => {
                window.location.href = `cotizacion_detalle.php?id=${data.id}`;
            }, 500);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch (e) {
        showToast('Error', 'No se pudo crear la cotización.', 'error');
    }
}

// ── Eliminar cotización ───────────────────────────────────────
async function eliminarCotizacion(id) {
    if (!confirm('¿Eliminar esta cotización?\n\nEsta acción no se puede deshacer.')) return;
    
    const fd = new FormData();
    fd.append('action', 'delete_cotizacion');
    fd.append('id', id);
    
    try {
        const res = await fetch('../api/previas_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            showToast('Cotización eliminada', data.message, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch (e) {
        showToast('Error', 'No se pudo eliminar la cotización.', 'error');
    }
}

        // ── Editar actividad (pendiente) ──────────────────────────────
        function editarActividad(id) {
            showToast('En desarrollo', 'Función de edición próximamente.', 'info');
        }
</script>