<?php
// ============================================================
//  modules/proyecto_detalle.php — Detalle de Proyecto
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session = require_auth();
$uid     = (int)$session['usuario_id'];
$page_title  = 'Detalle de Proyecto';
$page_active = 'proyectos';

$proyecto_id = (int)($_GET['id'] ?? 0);
if ($proyecto_id <= 0) { header('Location: proyectos.php'); exit; }

$proyecto = db_fetch_one(
    "SELECT p.*, c.razon_social AS cliente_nombre,
            ep.nombre AS estado_nombre, ep.color AS estado_color,
            u.nombre || ' ' || u.apellido AS responsable_nombre,
            (SELECT COUNT(*) FROM tareas WHERE proyecto_id = p.id) AS total_tareas,
            (SELECT COUNT(*) FROM tareas WHERE proyecto_id = p.id AND estado = 'completada') AS tareas_completadas
     FROM proyectos p
     LEFT JOIN clientes c ON p.cliente_id = c.id
     LEFT JOIN estados_proyecto ep ON p.estado_id = ep.id
     LEFT JOIN usuarios u ON p.responsable_id = u.id
     WHERE p.id = ?",
    [$proyecto_id]
);
if (!$proyecto) { header('Location: proyectos.php'); exit; }

$estados  = db_fetch_all("SELECT id, nombre, color FROM estados_proyecto ORDER BY id");
$usuarios = db_fetch_all(
    "SELECT id, nombre || ' ' || apellido AS nombre_completo FROM usuarios WHERE activo = TRUE ORDER BY nombre"
);

$entregables = db_fetch_all(
    "SELECT * FROM entregables WHERE proyecto_id = ? ORDER BY orden, id",
    [$proyecto_id]
);

// Cargar archivos de cada entregable
foreach ($entregables as &$ent) {
    $ent['archivos'] = db_fetch_all(
        "SELECT id, archivo_path, archivo_nombre, archivo_tipo, archivo_tamano
         FROM entregables_archivos WHERE entregable_id = ? ORDER BY created_at",
        [$ent['id']]
    );
}
unset($ent);

$suma_porcentajes   = array_sum(array_column($entregables, 'porcentaje'));
$porcentaje_faltante = 100 - $suma_porcentajes;

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
.proyecto-header {
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
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}
.info-item {
    padding: 16px;
    background: #F9FAFB;
    border-radius: 6px;
    border-left: 4px solid var(--c-navy);
}
.info-label {
    font-size: 11px;
    color: var(--c-text-3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}
.info-value {
    font-size: 15px;
    font-weight: 600;
    color: var(--c-text);
}
.archivo-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
}
.archivo-badge.pdf   { background:#FEE2E2; color:#DC2626; }
.archivo-badge.imagen{ background:#E0F2FE; color:#0369A1; }
.archivo-badge.word  { background:#DBEAFE; color:#1D4ED8; }
.archivo-badge.zip   { background:#FEF3C7; color:#92400E; }
.entregable-card {
    background:#fff;
    border:1px solid var(--c-border);
    border-radius:10px;
    padding:18px 20px;
    transition: box-shadow 0.2s;
}
.entregable-card:hover { box-shadow: 0 3px 12px rgba(0,0,0,0.08); }
.fecha-ampliada {
    background:#FEF3C7;
    border:1px solid #FDE68A;
    border-radius:6px;
    padding:8px 12px;
    font-size:11.5px;
    color:#92400E;
    margin-top:8px;
}
</style>

<div class="main-content">
<?php render_topbar($proyecto['codigo'] ?? 'Proyecto', $proyecto['nombre']); ?>

<div class="page-body">

    <!-- Header del proyecto -->
    <div class="proyecto-header">
        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:16px;">
            <div>
                <div style="font-size:13px;opacity:0.9;margin-bottom:8px;">
                    <?= htmlspecialchars($proyecto['codigo'] ?? 'Sin código') ?>
                </div>
                <h1 style="font-size:28px;font-weight:700;margin:0;">
                    <?= htmlspecialchars($proyecto['nombre']) ?>
                </h1>
                <?php if ($proyecto['cliente_nombre']): ?>
                <div style="font-size:15px;margin-top:8px;opacity:0.9;">
                    🏢 <?= htmlspecialchars($proyecto['cliente_nombre']) ?>
                </div>
                <?php endif; ?>
            </div>
            <a href="proyectos.php" class="btn btn-secondary btn-sm">← Volver</a>
        </div>

        <!-- Costo destacado -->
        <?php if ($proyecto['presupuesto']): ?>
        <div style="display:inline-flex;align-items:center;gap:10px;
                    background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.3);
                    border-radius:10px;padding:10px 18px;margin-bottom:14px;">
            <span style="font-size:18px;">💰</span>
            <div>
                <div style="font-size:10px;opacity:0.8;text-transform:uppercase;letter-spacing:.6px;font-weight:600;">
                    Costo del Proyecto
                </div>
                <div style="font-size:22px;font-weight:800;letter-spacing:-.3px;">
                    S/ <?= number_format($proyecto['presupuesto'], 2) ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div style="display:inline-flex;align-items:center;gap:8px;
                    background:rgba(255,255,255,0.1);border:1px dashed rgba(255,255,255,0.3);
                    border-radius:10px;padding:8px 14px;margin-bottom:14px;
                    font-size:12px;opacity:0.7;cursor:pointer;"
             onclick="switchTabByName('editar')" title="Clic para editar">
            💰 Sin costo definido — <u>Agregar</u>
        </div>
        <?php endif; ?>

        <div style="background:rgba(255,255,255,0.2);border-radius:12px;padding:12px;">
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                <span style="font-size:13px;font-weight:600;">Avance del Proyecto</span>
                <span style="font-size:16px;font-weight:700;"><?= $proyecto['avance_porcentaje'] ?>%</span>
            </div>
            <div style="background:rgba(255,255,255,0.3);height:20px;border-radius:10px;overflow:hidden;">
                <div style="background:#10B981;height:100%;width:<?= $proyecto['avance_porcentaje'] ?>%;transition:width 0.3s;border-radius:10px;"></div>
            </div>
            <div style="font-size:11px;margin-top:6px;opacity:0.9;">
                <?= count($entregables) ?> entregable<?= count($entregables) != 1 ? 's' : '' ?> definido<?= count($entregables) != 1 ? 's' : '' ?>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tab-buttons" style="padding:16px 20px 0;">
        <button class="tab-btn active" onclick="switchTab('info',this)">📋 Información General</button>
        <button class="tab-btn" onclick="switchTab('editar',this)">✏️ Editar Proyecto</button>
        <button class="tab-btn" onclick="switchTab('entregables',this)">
            📦 Entregables (<?= count($entregables) ?>)
        </button>
        <button class="tab-btn" onclick="switchTab('pagos',this)" id="tabBtnPagos">
            💳 Pagos
        </button>
        <button class="tab-btn" onclick="switchTab('cierre',this)"
                style="<?= $proyecto['estado_id'] == 4 ? 'color:#065F46;' : '' ?>">
            <?= $proyecto['estado_id'] == 4 ? '✅' : '🏁' ?> Cierre del Proyecto
        </button>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB: INFORMACIÓN GENERAL
    ══════════════════════════════════════════════ -->
    <div id="tab-info" class="tab-content active" style="padding:20px;">
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Estado</div>
                <div class="info-value">
                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;
                                 background:<?= $proyecto['estado_color'] ?>;margin-right:8px;"></span>
                    <?= htmlspecialchars($proyecto['estado_nombre'] ?? 'Sin estado') ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Responsable</div>
                <div class="info-value"><?= htmlspecialchars($proyecto['responsable_nombre'] ?? 'Sin asignar') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Prioridad</div>
                <div class="info-value">
                    <?php $pc=['Baja'=>'#6B7280','Media'=>'#3B82F6','Alta'=>'#F59E0B','Crítica'=>'#DC2626']; ?>
                    <span style="color:<?= $pc[$proyecto['prioridad']] ?? '#6B7280' ?>">
                        <?= htmlspecialchars($proyecto['prioridad']) ?>
                    </span>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Costo de Proyecto</div>
                <div class="info-value" style="color:var(--c-navy);">
                    <?= $proyecto['presupuesto'] ? 'S/ ' . number_format($proyecto['presupuesto'], 2) : 'No definido' ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Fecha de Inicio</div>
                <div class="info-value">
                    <?= $proyecto['fecha_inicio'] ? date('d/m/Y', strtotime($proyecto['fecha_inicio'])) : 'No definida' ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Fecha de Fin Estimada</div>
                <div class="info-value">
                    <?= $proyecto['fecha_fin_estimada'] ? date('d/m/Y', strtotime($proyecto['fecha_fin_estimada'])) : 'No definida' ?>
                </div>
            </div>
        </div>

        <?php if ($proyecto['alcance']): ?>
        <div style="margin-bottom:20px;">
            <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;color:var(--c-text-2);">ALCANCE DEL PROYECTO</h3>
            <div style="background:#F9FAFB;padding:16px;border-radius:6px;border-left:4px solid var(--c-navy);">
                <?= nl2br(htmlspecialchars($proyecto['alcance'])) ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($proyecto['descripcion']): ?>
        <div style="margin-bottom:20px;">
            <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;color:var(--c-text-2);">DESCRIPCIÓN / OBJETIVOS</h3>
            <div style="background:#F9FAFB;padding:16px;border-radius:6px;border-left:4px solid var(--c-navy);">
                <?= nl2br(htmlspecialchars($proyecto['descripcion'])) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Resumen de entregables en info -->
        <div>
            <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;color:var(--c-text-2);">ENTREGABLES DEL PROYECTO</h3>
            <?php if (empty($entregables)): ?>
                <div style="background:#F9FAFB;padding:20px;border-radius:6px;text-align:center;color:var(--c-text-3);">
                    No hay entregables definidos.
                </div>
            <?php else: ?>
                <div style="background:#F0F9FF;border-left:4px solid #3B82F6;padding:12px 16px;border-radius:6px;margin-bottom:16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <span style="font-size:12px;color:#0369A1;font-weight:600;">AVANCE TOTAL:</span>
                            <span style="font-size:20px;font-weight:700;color:#1B3A6B;margin-left:8px;">
                                <?= number_format($suma_porcentajes, 1) ?>%
                            </span>
                        </div>
                        <?php if ($porcentaje_faltante != 0): ?>
                        <div style="font-size:12px;color:<?= $porcentaje_faltante > 0 ? '#D97706' : '#DC2626' ?>;">
                            <?= $porcentaje_faltante > 0 ? 'Faltante: +' : 'Excedente: ' ?><?= number_format(abs($porcentaje_faltante), 1) ?>%
                        </div>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top:8px;background:rgba(255,255,255,0.6);border-radius:4px;height:8px;overflow:hidden;">
                        <div style="background:#10B981;height:100%;width:<?= min($suma_porcentajes, 100) ?>%;transition:width 0.3s;"></div>
                    </div>
                </div>

                <div style="display:flex;flex-direction:column;gap:10px;">
                    <?php foreach ($entregables as $idx => $ent): ?>
                    <div style="background:#FAFAFA;border:1px solid var(--c-border);border-radius:6px;padding:12px 16px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <div style="flex:1;">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                                    <span style="background:var(--c-navy);color:#fff;width:24px;height:24px;
                                                 border-radius:50%;display:inline-flex;align-items:center;
                                                 justify-content:center;font-size:11px;font-weight:700;">
                                        <?= $idx + 1 ?>
                                    </span>
                                    <span style="font-size:14px;font-weight:600;"><?= htmlspecialchars($ent['nombre']) ?></span>
                                    <?php if ($ent['estado'] === 'completado'): ?>
                                    <span style="background:#D1FAE5;color:#065F46;font-size:10px;padding:2px 6px;border-radius:8px;font-weight:600;">✓ Completado</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($ent['descripcion']): ?>
                                <div style="font-size:12px;color:var(--c-text-3);margin-left:32px;">
                                    <?= htmlspecialchars($ent['descripcion']) ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($ent['fecha_inicio'] || $ent['fecha_fin']): ?>
                                <div style="font-size:11px;color:var(--c-text-3);margin-left:32px;margin-top:4px;">
                                    📅
                                    <?= $ent['fecha_inicio'] ? date('d/m/Y', strtotime($ent['fecha_inicio'])) : '—' ?>
                                    →
                                    <?= $ent['fecha_fin'] ? date('d/m/Y', strtotime($ent['fecha_fin'])) : '—' ?>
                                    <?php if ($ent['fecha_fin_original']): ?>
                                    <span style="color:#D97706;">(ampliado)</span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div style="text-align:right;min-width:80px;">
                                <div style="font-size:18px;font-weight:700;color:var(--c-navy);">
                                    <?= number_format($ent['porcentaje'], 1) ?>%
                                </div>
                                <div style="font-size:10px;color:var(--c-text-3);">del total</div>
                            </div>
                        </div>
                        <div style="margin-top:8px;background:#E5E7EB;border-radius:3px;height:4px;overflow:hidden;">
                            <div style="background:#10B981;height:100%;width:<?= $ent['porcentaje'] ?>%;transition:width 0.3s;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top:12px;text-align:center;">
                    <button class="btn btn-secondary btn-sm" onclick="switchTabByName('entregables')">
                        📦 Gestionar Entregables
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB: EDITAR PROYECTO
    ══════════════════════════════════════════════ -->
    <div id="tab-editar" class="tab-content" style="padding:20px;">
        <h3 style="font-size:16px;font-weight:700;margin-bottom:20px;">Editar Información del Proyecto</h3>

        <form id="formEditarProyecto">
            <input type="hidden" name="id"         value="<?= $proyecto_id ?>">
            <input type="hidden" name="action"     value="update">
            <input type="hidden" name="cliente_id" value="<?= $proyecto['cliente_id'] ?>">

            <div class="form-group">
                <label class="form-label">Nombre del Proyecto <span class="required">*</span></label>
                <input class="form-control" type="text" name="nombre"
                       value="<?= htmlspecialchars($proyecto['nombre']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Cliente</label>
                <input class="form-control" type="text"
                       value="<?= htmlspecialchars($proyecto['cliente_nombre']) ?>"
                       disabled style="background:#F3F4F6;">
            </div>

            <div class="form-group">
                <label class="form-label">Responsable</label>
                <select class="form-control" name="responsable_id">
                    <option value="">Sin asignar</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $u['id'] == $proyecto['responsable_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['nombre_completo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Estado</label>
                <select class="form-control" name="estado_id">
                    <?php foreach ($estados as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $e['id'] == $proyecto['estado_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Prioridad</label>
                <select class="form-control" name="prioridad">
                    <option value="Baja"    <?= $proyecto['prioridad'] === 'Baja'    ? 'selected':'' ?>>Baja</option>
                    <option value="Media"   <?= $proyecto['prioridad'] === 'Media'   ? 'selected':'' ?>>Media</option>
                    <option value="Alta"    <?= $proyecto['prioridad'] === 'Alta'    ? 'selected':'' ?>>Alta</option>
                    <option value="Crítica" <?= $proyecto['prioridad'] === 'Crítica' ? 'selected':'' ?>>Crítica</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Costo de Proyecto (S/)</label>
                <input class="form-control" type="number" name="presupuesto"
                       value="<?= $proyecto['presupuesto'] ?>" step="0.01" min="0">
            </div>

            <div class="form-group">
                <label class="form-label">Fecha de Inicio</label>
                <input class="form-control" type="date" name="fecha_inicio"
                       value="<?= $proyecto['fecha_inicio'] ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Fecha de Fin Estimada</label>
                <input class="form-control" type="date" name="fecha_fin_estimada"
                       value="<?= $proyecto['fecha_fin_estimada'] ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Alcance del Proyecto</label>
                <textarea class="form-control" name="alcance" rows="4"><?= htmlspecialchars($proyecto['alcance']) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Descripción / Objetivos</label>
                <textarea class="form-control" name="descripcion" rows="4"><?= htmlspecialchars($proyecto['descripcion']) ?></textarea>
            </div>

            <div style="margin-top:24px;padding-top:20px;border-top:2px solid var(--c-border);display:flex;gap:12px;">
                <button type="button" class="btn btn-primary" onclick="guardarCambios()">💾 Guardar Cambios</button>
                <button type="button" class="btn btn-secondary" onclick="switchTabByName('info')">Cancelar</button>
            </div>
        </form>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB: ENTREGABLES
    ══════════════════════════════════════════════ -->
    <div id="tab-entregables" class="tab-content" style="padding:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="font-size:16px;font-weight:700;margin:0;">Lista de Entregables</h3>
            <button class="btn btn-primary btn-sm" onclick="openModal('modalNuevoEntregable')">
                + Nuevo Entregable
            </button>
        </div>

        <!-- Indicador de porcentaje total -->
        <div style="background:#F0F9FF;border:2px solid #BAE6FD;border-radius:8px;padding:16px;margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-size:12px;color:#0369A1;margin-bottom:4px;">PORCENTAJE ASIGNADO</div>
                    <div style="font-size:24px;font-weight:700;color:#1B3A6B;">
                        <?= number_format($suma_porcentajes, 1) ?>%
                    </div>
                </div>
                <?php if ($porcentaje_faltante != 0): ?>
                <div style="text-align:right;">
                    <div style="font-size:12px;color:<?= $porcentaje_faltante > 0 ? '#D97706' : '#DC2626' ?>;margin-bottom:4px;">
                        <?= $porcentaje_faltante > 0 ? 'FALTANTE' : 'EXCEDENTE' ?>
                    </div>
                    <div style="font-size:20px;font-weight:700;color:<?= $porcentaje_faltante > 0 ? '#D97706' : '#DC2626' ?>;">
                        <?= $porcentaje_faltante > 0 ? '+' : '' ?><?= number_format($porcentaje_faltante, 1) ?>%
                    </div>
                </div>
                <?php else: ?>
                <div style="background:#D1FAE5;color:#065F46;padding:8px 16px;border-radius:6px;font-weight:600;">
                    ✓ 100% Asignado
                </div>
                <?php endif; ?>
            </div>
            <div style="margin-top:12px;background:#fff;border-radius:4px;height:12px;overflow:hidden;">
                <div style="background:#10B981;height:100%;width:<?= min($suma_porcentajes, 100) ?>%;transition:width 0.3s;"></div>
            </div>
        </div>

        <!-- Lista de entregables -->
        <?php if (empty($entregables)): ?>
            <div style="text-align:center;padding:40px;color:var(--c-text-3);background:#FAFAFA;border-radius:8px;">
                No hay entregables. Haz clic en "Nuevo Entregable" para comenzar.
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:14px;">
                <?php foreach ($entregables as $ent): ?>
                <div class="entregable-card">

                    <!-- Fila principal: info + controles -->
                    <div style="display:flex;gap:16px;align-items:flex-start;">

                        <!-- Nombre y meta -->
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                                <span style="font-size:15px;font-weight:700;">
                                    <?= htmlspecialchars($ent['nombre']) ?>
                                </span>
                                <?php if ($ent['estado'] === 'completado'): ?>
                                <span style="background:#D1FAE5;color:#065F46;font-size:10px;
                                             padding:2px 7px;border-radius:8px;font-weight:600;">
                                    ✓ Completado
                                </span>
                                <?php else: ?>
                                <span style="background:#FEF3C7;color:#92400E;font-size:10px;
                                             padding:2px 7px;border-radius:8px;font-weight:600;">
                                    ⏳ Pendiente
                                </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($ent['descripcion']): ?>
                            <div style="font-size:12.5px;color:var(--c-text-3);margin-bottom:8px;">
                                <?= htmlspecialchars($ent['descripcion']) ?>
                            </div>
                            <?php endif; ?>

                            <!-- Fechas -->
                            <?php if ($ent['fecha_inicio'] || $ent['fecha_fin']): ?>
                            <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:12px;color:var(--c-text-3);margin-bottom:8px;">
                                <?php if ($ent['fecha_inicio']): ?>
                                <span>🗓 Inicio: <strong><?= date('d/m/Y', strtotime($ent['fecha_inicio'])) ?></strong></span>
                                <?php endif; ?>
                                <?php if ($ent['fecha_fin']): ?>
                                <span>⏱ Fin: <strong><?= date('d/m/Y', strtotime($ent['fecha_fin'])) ?></strong></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Aviso de ampliación -->
                            <?php if ($ent['fecha_fin_original']): ?>
                            <div class="fecha-ampliada">
                                🔔 Fecha ampliada. Original: <strong><?= date('d/m/Y', strtotime($ent['fecha_fin_original'])) ?></strong>
                                <?php if ($ent['justificacion_ampliacion']): ?>
                                — Justificación: <?= htmlspecialchars($ent['justificacion_ampliacion']) ?>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Archivos adjuntos -->
                            <?php if (!empty($ent['archivos'])): ?>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px;">
                                <?php foreach ($ent['archivos'] as $arch): ?>
                                <div style="display:flex;align-items:center;gap:4px;">
                                    <a href="<?= APP_URL ?>/<?= htmlspecialchars($arch['archivo_path']) ?>"
                                       target="_blank"
                                       class="archivo-badge <?= $arch['archivo_tipo'] ?>">
                                        <?php
                                        $icons = ['pdf'=>'📄','imagen'=>'🖼','word'=>'📝','zip'=>'🗜'];
                                        echo $icons[$arch['archivo_tipo']] ?? '📎';
                                        ?>
                                        <?= htmlspecialchars($arch['archivo_nombre']) ?>
                                    </a>
                                    <button onclick="eliminarArchivo(<?= $arch['id'] ?>, this)"
                                            style="background:none;border:none;cursor:pointer;color:#9CA3AF;
                                                   font-size:13px;padding:0;line-height:1;"
                                            title="Eliminar archivo">×</button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Controles derechos -->
                        <div style="display:flex;flex-direction:column;gap:8px;min-width:140px;align-items:stretch;">

                            <!-- Porcentaje -->
                            <div>
                                <input type="number" class="form-control"
                                       style="text-align:center;font-weight:700;font-size:14px;"
                                       value="<?= $ent['porcentaje'] ?>"
                                       min="0" max="100" step="0.5"
                                       onchange="actualizarPorcentaje(<?= $ent['id'] ?>, this.value)"
                                       title="% de avance">
                                <div style="font-size:10px;color:var(--c-text-3);text-align:center;margin-top:2px;">% Avance</div>
                            </div>

                            <!-- Subir archivo -->
                            <button class="btn btn-secondary btn-sm"
                                    style="font-size:11px;"
                                    onclick="document.getElementById('file_<?= $ent['id'] ?>').click()">
                                📤 Subir Archivo
                            </button>
                            <input type="file" id="file_<?= $ent['id'] ?>" style="display:none;"
                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.zip"
                                   onchange="subirArchivo(<?= $ent['id'] ?>, this.files[0])">

                            <!-- Ampliar fecha -->
                            <button class="btn btn-sm"
                                    style="background:#FEF3C7;color:#92400E;border:1px solid #FDE68A;font-size:11px;"
                                    onclick="abrirAmpliarFecha(<?= $ent['id'] ?>, '<?= $ent['fecha_fin'] ?>')">
                                📅 Ampliar Fecha
                            </button>

                            <!-- Marcar completado -->
                            <button class="btn btn-sm"
                                    style="background:<?= $ent['estado']==='completado' ? '#D1FAE5' : '#F3F4F6' ?>;
                                           color:<?= $ent['estado']==='completado' ? '#065F46' : 'var(--c-text-3)' ?>;
                                           font-size:11px;"
                                    onclick="toggleCompletado(<?= $ent['id'] ?>, <?= $ent['estado']==='completado' ? 0 : 1 ?>)">
                                <?= $ent['estado']==='completado' ? '↩ Reabrir' : '✓ Completar' ?>
                            </button>

                            <!-- Eliminar -->
                            <button class="btn btn-sm"
                                    style="background:#FEE2E2;color:#DC2626;border:1px solid #FECACA;font-size:11px;"
                                    onclick="eliminarEntregable(<?= $ent['id'] ?>)">
                                🗑 Eliminar
                            </button>
                        </div>
                    </div>

                    <!-- Barra de progreso -->
                    <div style="margin-top:12px;background:#E5E7EB;border-radius:4px;height:5px;overflow:hidden;">
                        <div style="background:#10B981;height:100%;width:<?= $ent['porcentaje'] ?>%;transition:width 0.3s;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB: PAGOS
    ══════════════════════════════════════════════ -->
    <div id="tab-pagos" class="tab-content" style="padding:20px;">

        <!-- Resumen financiero -->
        <div id="resumenPagos" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
                                      gap:14px;margin-bottom:20px;">
            <div class="kpi-card" style="padding:16px;">
                <div class="kpi-label">Presupuesto</div>
                <div class="kpi-value" style="font-size:18px;">
                    S/ <?= number_format($proyecto['presupuesto'] ?? 0, 2) ?>
                </div>
            </div>
            <div class="kpi-card" style="padding:16px;" id="kpiCobrado">
                <div class="kpi-label">Cobrado</div>
                <div class="kpi-value" style="font-size:18px;color:#10B981;">S/ —</div>
            </div>
            <div class="kpi-card" style="padding:16px;" id="kpiPendiente">
                <div class="kpi-label">Pendiente</div>
                <div class="kpi-value" style="font-size:18px;color:#F59E0B;">S/ —</div>
            </div>
            <div class="kpi-card" style="padding:16px;" id="kpiVencido">
                <div class="kpi-label">Vencido</div>
                <div class="kpi-value" style="font-size:18px;color:#EF4444;">S/ —</div>
            </div>
        </div>

        <!-- Barra de progreso de cobro -->
        <div id="barraCobroWrap" style="background:#fff;border:1px solid var(--c-border);
                                        border-radius:8px;padding:16px;margin-bottom:20px;display:none;">
            <div style="display:flex;justify-content:space-between;font-size:12px;
                        color:var(--c-text-3);margin-bottom:6px;">
                <span>Progreso de cobro</span>
                <strong id="pctCobro">0%</strong>
            </div>
            <div style="background:#E5E7EB;border-radius:6px;height:10px;overflow:hidden;">
                <div id="barCobro" style="background:#10B981;height:100%;width:0%;transition:width 0.4s;border-radius:6px;"></div>
            </div>
        </div>

        <!-- Cabecera lista -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <h3 style="font-size:15px;font-weight:700;margin:0;">Registro de Pagos</h3>
            <button class="btn btn-primary btn-sm" onclick="abrirNuevoPago()">
                + Nuevo Pago
            </button>
        </div>

        <!-- Lista dinámica -->
        <div id="listaPagos">
            <div style="text-align:center;padding:40px;color:var(--c-text-3);">
                Cargando pagos...
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB: CIERRE DEL PROYECTO
    ══════════════════════════════════════════════ -->
    <div id="tab-cierre" class="tab-content" style="padding:20px;">

        <?php
        $ya_completado   = $proyecto['estado_id'] == 4;
        $total_ent       = count($entregables);
        $completados_ent = count(array_filter($entregables, fn($e) => $e['estado'] === 'completado'));
        $todos_ok        = $total_ent > 0 && $completados_ent === $total_ent;
        ?>

        <!-- Banner si ya está completado -->
        <?php if ($ya_completado): ?>
        <div style="background:#D1FAE5;border:2px solid #6EE7B7;border-radius:10px;
                    padding:20px 24px;margin-bottom:24px;display:flex;align-items:center;gap:14px;">
            <span style="font-size:36px;">✅</span>
            <div>
                <div style="font-size:16px;font-weight:700;color:#065F46;">Proyecto Completado</div>
                <div style="font-size:13px;color:#047857;margin-top:2px;">
                    Este proyecto fue cerrado<?= $proyecto['fecha_fin_real'] ? ' el ' . date('d/m/Y', strtotime($proyecto['fecha_fin_real'])) : '' ?>
                    y está activo en <strong>Puesta en Marcha</strong>.
                    <a href="marcha.php?proyecto_id=<?= $proyecto_id ?>"
                       style="color:#065F46;font-weight:700;margin-left:4px;">
                        Ver incidencias →
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Checklist de cierre -->
        <div style="background:#fff;border:1px solid var(--c-border);border-radius:10px;
                    padding:20px;margin-bottom:20px;">
            <h3 style="font-size:15px;font-weight:700;margin:0 0 16px;color:var(--c-text);">
                Verificación de Cierre
            </h3>

            <?php
            $checks = [
                [
                    'ok'    => $total_ent > 0,
                    'label' => 'Entregables definidos',
                    'detalle' => $total_ent > 0 ? "$total_ent entregable(s) registrado(s)" : 'No hay entregables',
                ],
                [
                    'ok'    => $todos_ok,
                    'label' => 'Todos los entregables completados',
                    'detalle' => "$completados_ent de $total_ent completados",
                ],
                [
                    'ok'    => !empty($proyecto['fecha_fin_estimada']),
                    'label' => 'Fecha de fin definida',
                    'detalle' => $proyecto['fecha_fin_estimada']
                                 ? date('d/m/Y', strtotime($proyecto['fecha_fin_estimada']))
                                 : 'Sin fecha de fin',
                ],
                [
                    'ok'    => !empty($proyecto['responsable_nombre']) && $proyecto['responsable_nombre'] !== 'Sin asignar',
                    'label' => 'Responsable asignado',
                    'detalle' => $proyecto['responsable_nombre'] ?? 'Sin asignar',
                ],
            ];
            $checks_ok = count(array_filter($checks, fn($c) => $c['ok']));
            ?>

            <div id="alertaPagosCierre"></div>

            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">
                <?php foreach ($checks as $chk): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;
                            background:<?= $chk['ok'] ? '#F0FDF4' : '#FFF7ED' ?>;
                            border-radius:7px;border-left:4px solid <?= $chk['ok'] ? '#10B981' : '#F59E0B' ?>;">
                    <span style="font-size:18px;"><?= $chk['ok'] ? '✅' : '⚠️' ?></span>
                    <div style="flex:1;">
                        <div style="font-size:13px;font-weight:600;color:var(--c-text);">
                            <?= $chk['label'] ?>
                        </div>
                        <div style="font-size:11.5px;color:var(--c-text-3);"><?= $chk['detalle'] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Barra de preparación -->
            <div>
                <div style="display:flex;justify-content:space-between;font-size:12px;
                            color:var(--c-text-3);margin-bottom:5px;">
                    <span>Preparación para cierre</span>
                    <strong><?= $checks_ok ?>/<?= count($checks) ?> verificaciones</strong>
                </div>
                <div style="background:#E5E7EB;border-radius:6px;height:8px;overflow:hidden;">
                    <div style="background:<?= $checks_ok === count($checks) ? '#10B981' : '#F59E0B' ?>;
                                height:100%;width:<?= round($checks_ok / count($checks) * 100) ?>%;
                                transition:width 0.3s;border-radius:6px;"></div>
                </div>
            </div>
        </div>

        <!-- Lista de entregables para el acta -->
        <div style="background:#fff;border:1px solid var(--c-border);border-radius:10px;
                    padding:20px;margin-bottom:20px;">
            <h3 style="font-size:15px;font-weight:700;margin:0 0 14px;color:var(--c-text);">
                Entregables del Proyecto
            </h3>

            <?php if (empty($entregables)): ?>
            <div style="text-align:center;padding:20px;color:var(--c-text-3);font-size:13px;">
                No hay entregables registrados.
            </div>
            <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <?php foreach ($entregables as $idx => $ent): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;
                            background:#FAFAFA;border-radius:7px;
                            border:1px solid <?= $ent['estado']==='completado' ? '#6EE7B7' : '#FDE68A' ?>;">
                    <span style="font-size:18px;"><?= $ent['estado']==='completado' ? '✅' : '⏳' ?></span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:600;">
                            <?= htmlspecialchars($ent['nombre']) ?>
                        </div>
                        <?php if ($ent['fecha_inicio'] || $ent['fecha_fin']): ?>
                        <div style="font-size:11px;color:var(--c-text-3);margin-top:2px;">
                            <?= $ent['fecha_inicio'] ? date('d/m/Y', strtotime($ent['fecha_inicio'])) : '—' ?>
                            → <?= $ent['fecha_fin'] ? date('d/m/Y', strtotime($ent['fecha_fin'])) : '—' ?>
                            <?php if ($ent['fecha_fin_original']): ?>
                            <span style="color:#D97706;">(fecha ampliada)</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:14px;font-weight:700;color:var(--c-navy);">
                            <?= number_format($ent['porcentaje'], 0) ?>%
                        </div>
                        <div style="font-size:10px;color:var(--c-text-3);">
                            <?= $ent['estado'] === 'completado' ? 'Completado' : 'Pendiente' ?>
                        </div>
                    </div>
                    <?php if (!empty($ent['archivos'])): ?>
                    <div style="font-size:11px;color:#3B82F6;white-space:nowrap;">
                        📎 <?= count($ent['archivos']) ?> archivo<?= count($ent['archivos']) > 1 ? 's' : '' ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Documento de Conformidad -->
        <div style="background:#fff;border:1px solid var(--c-border);border-radius:10px;
                    padding:20px;margin-bottom:20px;">
            <h3 style="font-size:15px;font-weight:700;margin:0 0 4px;color:var(--c-text);">
                📄 Documento de Conformidad
            </h3>
            <p style="font-size:12.5px;color:var(--c-text-3);margin:0 0 16px;">
                Adjunta el PDF de conformidad del servicio o acta de finalización del contrato.
            </p>

            <?php if (!empty($proyecto['conformidad_path'])): ?>
            <!-- Archivo ya subido -->
            <div id="conformidadExistente"
                 style="display:flex;align-items:center;gap:12px;background:#F0FDF4;
                        border:1px solid #BBF7D0;border-radius:8px;padding:12px 16px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"
                     style="width:28px;height:28px;flex-shrink:0;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14,2 14,8 20,8"/>
                </svg>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:13px;color:var(--c-text);
                                white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?= htmlspecialchars($proyecto['conformidad_nombre']) ?>
                    </div>
                    <div style="font-size:11.5px;color:#059669;">Documento subido correctamente</div>
                </div>
                <a href="../<?= htmlspecialchars($proyecto['conformidad_path']) ?>"
                   target="_blank"
                   class="btn btn-secondary btn-sm" style="flex-shrink:0;">
                    👁 Ver
                </a>
                <button class="btn btn-sm" style="flex-shrink:0;background:#DC2626;color:#fff;border-color:#DC2626;"
                        onclick="eliminarConformidad()">
                    🗑 Quitar
                </button>
            </div>
            <div id="conformidadUploadZone" style="display:none;margin-top:12px;">
            <?php else: ?>
            <div id="conformidadExistente" style="display:none;"></div>
            <div id="conformidadUploadZone">
            <?php endif; ?>
                <label for="inputConformidad"
                       style="display:flex;flex-direction:column;align-items:center;gap:8px;
                              border:2px dashed var(--c-border);border-radius:8px;padding:24px;
                              cursor:pointer;transition:border-color .2s;"
                       onmouseover="this.style.borderColor='var(--c-primary)'"
                       onmouseout="this.style.borderColor='var(--c-border)'">
                    <svg viewBox="0 0 24 24" fill="none" stroke="var(--c-text-3)" stroke-width="1.5"
                         style="width:36px;height:36px;">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14,2 14,8 20,8"/>
                        <line x1="12" y1="18" x2="12" y2="12"/>
                        <line x1="9"  y1="15" x2="15" y2="15"/>
                    </svg>
                    <span style="font-size:13px;color:var(--c-text-2);">
                        Haz clic para seleccionar el PDF
                    </span>
                    <span style="font-size:11.5px;color:var(--c-text-4);">Solo PDF · Máx. 20 MB</span>
                    <input type="file" id="inputConformidad" accept=".pdf" style="display:none;"
                           onchange="subirConformidad(this)">
                </label>
                <div id="conformidadProgress" style="display:none;margin-top:10px;">
                    <div style="height:4px;background:#E5E7EB;border-radius:2px;overflow:hidden;">
                        <div style="height:100%;background:var(--c-primary);width:0%;transition:width .3s;"
                             id="conformidadBar"></div>
                    </div>
                    <div style="font-size:12px;color:var(--c-text-3);margin-top:4px;">Subiendo...</div>
                </div>
            </div>
        </div>

        <!-- Sección de cierre -->
        <?php if (!$ya_completado): ?>
        <div style="background:#fff;border:2px solid <?= $todos_ok ? '#10B981' : '#E5E7EB' ?>;
                    border-radius:10px;padding:24px;">
            <h3 style="font-size:15px;font-weight:700;margin:0 0 6px;color:var(--c-text);">
                🏁 Finalizar Proyecto
            </h3>
            <p style="font-size:13px;color:var(--c-text-3);margin:0 0 18px;">
                Al finalizar, el proyecto pasará a estado <strong>Completado</strong> y se habilitará
                automáticamente en <strong>Puesta en Marcha</strong> para el registro de incidencias y feedback.
            </p>

            <?php if (!$todos_ok && $total_ent > 0): ?>
            <div style="background:#FFF7ED;border:1px solid #FDE68A;border-radius:7px;
                        padding:10px 14px;margin-bottom:16px;font-size:12.5px;color:#92400E;">
                ⚠️ <strong><?= $total_ent - $completados_ent ?> entregable(s)</strong> aún pendiente(s).
                Puedes finalizar igualmente o completarlos primero.
            </div>
            <?php endif; ?>

            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">Observaciones de cierre (opcional)</label>
                <textarea class="form-control" id="cierre_observaciones" rows="3"
                          placeholder="Notas finales, condiciones de entrega, acuerdos con el cliente..."></textarea>
            </div>

            <button class="btn btn-primary"
                    style="background:#065F46;border-color:#065F46;font-size:14px;padding:10px 24px;"
                    onclick="finalizarProyecto()">
                ✅ Finalizar y Pasar a Puesta en Marcha
            </button>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:20px;">
            <a href="marcha.php?proyecto_id=<?= $proyecto_id ?>"
               class="btn btn-primary"
               style="background:#065F46;border-color:#065F46;font-size:14px;padding:10px 28px;">
                📋 Ver Puesta en Marcha e Incidencias
            </a>
        </div>
        <?php endif; ?>

    </div><!-- #tab-cierre -->

</div><!-- .page-body -->
</div><!-- .main-content -->

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Nuevo Entregable
═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalNuevoEntregable">
    <div class="modal-box" style="max-width:580px;">
        <div class="modal-header">
            <span class="modal-title">Nuevo Entregable</span>
            <button class="modal-close" onclick="closeModal('modalNuevoEntregable')">×</button>
        </div>
        <div class="modal-body">
            <form id="formNuevoEntregable">
                <input type="hidden" name="proyecto_id" value="<?= $proyecto_id ?>">

                <div class="form-group">
                    <label class="form-label">Nombre del Entregable <span class="required">*</span></label>
                    <input class="form-control" type="text" name="nombre" required
                           placeholder="Ej: Informe técnico final">
                </div>

                <div class="form-group">
                    <label class="form-label">Descripción (opcional)</label>
                    <textarea class="form-control" name="descripcion" rows="2"
                              placeholder="Descripción breve del entregable"></textarea>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Fecha de Inicio</label>
                        <input class="form-control" type="date" name="fecha_inicio">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fecha de Fin</label>
                        <input class="form-control" type="date" name="fecha_fin">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Porcentaje de Avance <span class="required">*</span></label>
                    <input class="form-control" type="number" name="porcentaje"
                           min="0" max="<?= $porcentaje_faltante ?>" step="0.5" required
                           placeholder="0.0">
                    <div style="font-size:11px;color:var(--c-text-3);margin-top:4px;">
                        Porcentaje disponible: <?= number_format($porcentaje_faltante, 1) ?>%
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalNuevoEntregable')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarEntregable()">Crear Entregable</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Ampliar Fecha
═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalAmpliarFecha">
    <div class="modal-box" style="max-width:460px;">
        <div class="modal-header">
            <span class="modal-title">📅 Ampliar Fecha de Entregable</span>
            <button class="modal-close" onclick="closeModal('modalAmpliarFecha')">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="ampliar_entregable_id">

            <div class="form-group">
                <label class="form-label">Fecha actual de fin</label>
                <input class="form-control" type="date" id="ampliar_fecha_actual" disabled
                       style="background:#F3F4F6;">
            </div>

            <div class="form-group">
                <label class="form-label">Nueva fecha de fin <span class="required">*</span></label>
                <input class="form-control" type="date" id="ampliar_nueva_fecha" required>
            </div>

            <div class="form-group">
                <label class="form-label">Justificación de la ampliación <span class="required">*</span></label>
                <textarea class="form-control" id="ampliar_justificacion" rows="3"
                          placeholder="Describe el motivo de la ampliación..."></textarea>
                <div style="font-size:11px;color:var(--c-text-3);margin-top:4px;">
                    Esta justificación quedará registrada en el sistema.
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalAmpliarFecha')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarAmpliarFecha()">Confirmar Ampliación</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Nuevo / Editar Pago
═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalNuevoPago">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-header">
            <span class="modal-title" id="tituloPagoModal">Nuevo Pago</span>
            <button class="modal-close" onclick="closeModal('modalNuevoPago')">×</button>
        </div>
        <div class="modal-body">
            <form id="formNuevoPago">
                <input type="hidden" name="proyecto_id" value="<?= $proyecto_id ?>">
                <input type="hidden" name="id" id="editPagoId" value="">

                <div class="form-group">
                    <label class="form-label">Concepto <span class="required">*</span></label>
                    <input class="form-control" type="text" name="concepto" id="pagoConcepto"
                           placeholder="Ej: Adelanto 30%, Cuota 1, Saldo final...">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Monto (S/) <span class="required">*</span></label>
                        <input class="form-control" type="number" name="monto" id="pagoMonto"
                               min="0.01" step="0.01" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Monto Total del Proyecto</label>
                        <input class="form-control" type="text" disabled
                               value="S/ <?= number_format($proyecto['presupuesto'] ?? 0, 2) ?>"
                               style="background:#F3F4F6;color:var(--c-text-3);">
                    </div>
                </div>

                <!-- Cascade: Proyecto → Entregable -->
                <div style="background:#F8FAFC;border:1px solid var(--c-border);
                            border-radius:8px;padding:14px;margin-bottom:4px;">
                    <div style="font-size:11px;font-weight:700;color:var(--c-text-3);
                                text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">
                        Vincular a Entregable (opcional)
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:end;">
                        <!-- Paso 1: Proyecto -->
                        <div class="form-group" style="margin:0;">
                            <label class="form-label" style="font-size:11.5px;">1. Proyecto</label>
                            <select class="form-control" id="cascadeProyecto"
                                    onchange="cascadeCargarEntregables(this.value)"
                                    style="font-size:13px;">
                                <option value="">— Seleccionar proyecto —</option>
                                <?php
                                $todos_proyectos = db_fetch_all(
                                    "SELECT p.id, p.nombre, c.razon_social AS cliente
                                     FROM proyectos p
                                     LEFT JOIN clientes c ON p.cliente_id = c.id
                                     WHERE p.estado_id NOT IN (5)
                                     ORDER BY p.nombre"
                                );
                                foreach ($todos_proyectos as $tp):
                                ?>
                                <option value="<?= $tp['id'] ?>"
                                        <?= $tp['id'] == $proyecto_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tp['nombre']) ?>
                                    <?php if ($tp['cliente']): ?>
                                    · <?= htmlspecialchars($tp['cliente']) ?>
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Paso 2: Entregable (se carga por AJAX) -->
                        <div class="form-group" style="margin:0;">
                            <label class="form-label" style="font-size:11.5px;">
                                2. Entregable
                                <span id="cascadeSpinner" style="display:none;margin-left:4px;">⏳</span>
                            </label>
                            <select class="form-control" name="entregable_id" id="pagoEntregable"
                                    style="font-size:13px;" disabled>
                                <option value="">— Primero elige proyecto —</option>
                            </select>
                        </div>
                    </div>

                    <!-- Buscador de entregable (aparece cuando hay entregables) -->
                    <div id="cascadeBuscadorWrap" style="display:none;margin-top:8px;">
                        <input type="text" id="cascadeBuscador" class="form-control"
                               style="font-size:12px;" placeholder="🔍 Filtrar entregables..."
                               oninput="cascadeFiltrar(this.value)">
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Fecha de Vencimiento</label>
                        <input class="form-control" type="date" name="fecha_vencimiento" id="pagoVencimiento">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fecha de Pago</label>
                        <input class="form-control" type="date" name="fecha_pago" id="pagoFechaPago"
                               placeholder="Dejar vacío si aún no se paga">
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Método de Pago</label>
                        <select class="form-control" name="metodo_pago" id="pagoMetodo">
                            <option value="">Sin especificar</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Depósito">Depósito</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Efectivo">Efectivo</option>
                            <option value="Yape/Plin">Yape/Plin</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">N° Comprobante</label>
                        <input class="form-control" type="text" name="numero_comprobante"
                               id="pagoComprobante" placeholder="Factura, recibo, operación...">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Notas</label>
                    <textarea class="form-control" name="notas" id="pagoNotas" rows="2"
                              placeholder="Observaciones adicionales..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalNuevoPago')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarPago()">Guardar Pago</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: Confirmar Pago Rápido
═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalConfirmarPago">
    <div class="modal-box" style="max-width:420px;">
        <div class="modal-header">
            <span class="modal-title">✅ Confirmar Pago</span>
            <button class="modal-close" onclick="closeModal('modalConfirmarPago')">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="confirmarPagoId">
            <p style="font-size:13px;color:var(--c-text-3);margin-bottom:16px;">
                Confirma los datos del pago recibido:
            </p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label">Fecha de Pago</label>
                    <input class="form-control" type="date" id="confirmarFecha">
                </div>
                <div class="form-group">
                    <label class="form-label">Método</label>
                    <select class="form-control" id="confirmarMetodo">
                        <option value="">Sin especificar</option>
                        <option value="Transferencia">Transferencia</option>
                        <option value="Depósito">Depósito</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Efectivo">Efectivo</option>
                        <option value="Yape/Plin">Yape/Plin</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalConfirmarPago')">Cancelar</button>
            <button class="btn btn-primary" style="background:#10B981;border-color:#10B981;"
                    onclick="ejecutarMarcarPagado()">Confirmar Pago</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
const proyectoId = <?= $proyecto_id ?>;

// ── Tabs ──────────────────────────────────────────────────────
function switchTab(tab, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    if (btn) btn.classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
    if (tab === 'pagos')  cargarPagos();
    if (tab === 'cierre') cargarAlertaPagosCierre();
}

function switchTabByName(tab) {
    const btns = document.querySelectorAll('.tab-btn');
    const map  = { info: 0, editar: 1, entregables: 2, pagos: 3, cierre: 4 };
    switchTab(tab, btns[map[tab]]);
}

// ══════════════════════════════════════════════════════════════
//  PAGOS
// ══════════════════════════════════════════════════════════════
const METODOS_ICON = {
    'Transferencia': '🏦', 'Depósito': '🏧', 'Cheque': '📝',
    'Efectivo': '💵', 'Yape/Plin': '📱', 'Otro': '💳', '': '💳'
};

async function cargarPagos() {
    try {
        const res  = await fetch(`../api/pagos_api.php?action=list&proyecto_id=${proyectoId}`);
        const data = await res.json();
        if (!data.success) return;

        const r = data.resumen;

        // KPIs
        document.querySelector('#kpiCobrado .kpi-value').textContent   = 'S/ ' + fmt(r.cobrado);
        document.querySelector('#kpiPendiente .kpi-value').textContent = 'S/ ' + fmt(r.pendiente);
        document.querySelector('#kpiVencido .kpi-value').textContent   = 'S/ ' + fmt(r.vencido);

        // Barra de cobro
        const presupuesto = <?= (float)($proyecto['presupuesto'] ?? 0) ?>;
        if (presupuesto > 0) {
            const pct = Math.min(100, Math.round((r.cobrado / presupuesto) * 100));
            document.getElementById('barraCobroWrap').style.display = 'block';
            document.getElementById('barCobro').style.width  = pct + '%';
            document.getElementById('pctCobro').textContent  = pct + '%';
        }

        // Lista
        const lista = document.getElementById('listaPagos');
        if (data.data.length === 0) {
            lista.innerHTML = `<div style="text-align:center;padding:40px;color:var(--c-text-3);
                                background:#FAFAFA;border-radius:8px;">
                No hay pagos registrados. Haz clic en "+ Nuevo Pago" para comenzar.
            </div>`;
            return;
        }

        lista.innerHTML = data.data.map(p => renderPagoCard(p)).join('');
    } catch(e) {
        console.error(e);
    }
}

function fmt(n) {
    return parseFloat(n).toLocaleString('es-PE', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function renderPagoCard(p) {
    const estadoStyles = {
        pagado:   { bg:'#D1FAE5', color:'#065F46', label:'✅ Pagado' },
        pendiente:{ bg:'#FEF3C7', color:'#92400E', label:'⏳ Pendiente' },
        vencido:  { bg:'#FEE2E2', color:'#991B1B', label:'🔴 Vencido' },
    };
    const est = estadoStyles[p.estado] ?? estadoStyles.pendiente;

    const fechaVenc = p.fecha_vencimiento
        ? `<span style="font-size:11px;color:${p.estado==='vencido'?'#DC2626':'var(--c-text-3)'};">
               ⏱ Vence: ${formatFecha(p.fecha_vencimiento)}
           </span>` : '';

    const fechaPago = p.fecha_pago
        ? `<span style="font-size:11px;color:#059669;">💰 Pagado: ${formatFecha(p.fecha_pago)}</span>` : '';

    const comprobante = p.numero_comprobante
        ? `<span style="font-size:11px;color:var(--c-text-3);">📄 ${escHtml(p.numero_comprobante)}</span>` : '';

    const vinculado = p.entregable_nombre
        ? `<span style="font-size:11px;color:#3B82F6;">🔗 ${escHtml(p.entregable_nombre)}</span>` : '';

    const archivo = p.archivo_path
        ? `<a href="${APP_URL_JS}/${p.archivo_path}" target="_blank"
              style="font-size:11px;color:#3B82F6;text-decoration:none;">
              📎 ${escHtml(p.archivo_nombre)}
           </a>` : '';

    const btnPagar = p.estado !== 'pagado'
        ? `<button class="btn btn-sm" style="background:#D1FAE5;color:#065F46;font-size:11px;"
                   onclick="abrirConfirmarPago(${p.id})">✅ Marcar Pagado</button>`
        : '';

    const btnSubir = `<button class="btn btn-sm" style="background:#EFF6FF;color:#1D4ED8;font-size:11px;"
                              onclick="document.getElementById('filePago_${p.id}').click()">
                         📎 ${p.archivo_path ? 'Cambiar' : 'Subir'} Comprobante
                      </button>
                      <input type="file" id="filePago_${p.id}" style="display:none;"
                             accept=".pdf,.jpg,.jpeg,.png"
                             onchange="subirComprobante(${p.id}, this.files[0])">`;

    return `
    <div style="background:#fff;border:1px solid var(--c-border);border-radius:10px;
                padding:16px 18px;margin-bottom:10px;">
        <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap;">
                    <span style="font-size:14px;font-weight:700;">${escHtml(p.concepto)}</span>
                    <span style="background:${est.bg};color:${est.color};font-size:10px;
                                 padding:2px 8px;border-radius:8px;font-weight:700;">
                        ${est.label}
                    </span>
                    ${p.metodo_pago ? `<span style="font-size:11px;color:var(--c-text-3);">${METODOS_ICON[p.metodo_pago]??'💳'} ${escHtml(p.metodo_pago)}</span>` : ''}
                </div>
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    ${fechaVenc}${fechaPago}${comprobante}${vinculado}
                </div>
                ${archivo ? `<div style="margin-top:6px;">${archivo}</div>` : ''}
                ${p.notas ? `<div style="font-size:11.5px;color:var(--c-text-3);margin-top:6px;">${escHtml(p.notas)}</div>` : ''}
            </div>
            <div style="text-align:right;min-width:120px;">
                <div style="font-size:20px;font-weight:700;color:var(--c-navy);">S/ ${fmt(p.monto)}</div>
                <div style="display:flex;flex-direction:column;gap:5px;margin-top:8px;align-items:flex-end;">
                    ${btnPagar}
                    ${btnSubir}
                    <button class="btn btn-sm" style="background:#F3F4F6;color:var(--c-text-3);font-size:11px;"
                            onclick="editarPago(${JSON.stringify(p).replace(/"/g,'&quot;')})">
                        ✏️ Editar
                    </button>
                    <button class="btn btn-sm" style="background:#FEE2E2;color:#DC2626;font-size:11px;"
                            onclick="eliminarPago(${p.id})">
                        🗑 Eliminar
                    </button>
                </div>
            </div>
        </div>
    </div>`;
}

function formatFecha(iso) {
    if (!iso) return '—';
    const [y,m,d] = iso.split('T')[0].split('-');
    return `${d}/${m}/${y}`;
}
function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Guardar pago (crear / editar) ─────────────────────────────
async function guardarPago() {
    const form = document.getElementById('formNuevoPago');
    const fd   = new FormData(form);
    const isEdit = !!document.getElementById('editPagoId').value;
    fd.append('action', isEdit ? 'update' : 'create');

    if (!fd.get('concepto').trim()) {
        showToast('Error', 'El concepto es obligatorio.', 'error'); return;
    }
    if (!parseFloat(fd.get('monto'))) {
        showToast('Error', 'El monto debe ser mayor a 0.', 'error'); return;
    }

    try {
        const res  = await fetch('../api/pagos_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            showToast('✅ Guardado', data.message, 'success');
            closeModal('modalNuevoPago');
            cargarPagos();
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo guardar.', 'error');
    }
}


// ── Marcar pagado ─────────────────────────────────────────────
function abrirConfirmarPago(id) {
    document.getElementById('confirmarPagoId').value  = id;
    document.getElementById('confirmarFecha').value   = new Date().toISOString().split('T')[0];
    document.getElementById('confirmarMetodo').value  = '';
    openModal('modalConfirmarPago');
}

async function ejecutarMarcarPagado() {
    const id     = document.getElementById('confirmarPagoId').value;
    const fecha  = document.getElementById('confirmarFecha').value;
    const metodo = document.getElementById('confirmarMetodo').value;

    if (!fecha) { showToast('Error','Selecciona la fecha de pago.','error'); return; }

    const fd = new FormData();
    fd.append('action','marcar_pagado');
    fd.append('id', id);
    fd.append('fecha_pago', fecha);
    fd.append('metodo_pago', metodo);

    try {
        const res  = await fetch('../api/pagos_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            showToast('✅ Pago confirmado', data.message, 'success');
            closeModal('modalConfirmarPago');
            cargarPagos();
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo actualizar.', 'error');
    }
}

// ── Subir comprobante ─────────────────────────────────────────
async function subirComprobante(id, file) {
    if (!file) return;
    const fd = new FormData();
    fd.append('action','upload');
    fd.append('id', id);
    fd.append('archivo', file);
    showToast('Subiendo...','Por favor espera','info');
    try {
        const res  = await fetch('../api/pagos_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            showToast('✅ Comprobante subido', data.message, 'success');
            cargarPagos();
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error','No se pudo subir el comprobante.','error');
    }
}

// ── Eliminar pago ─────────────────────────────────────────────
async function eliminarPago(id) {
    if (!confirm('¿Eliminar este pago?')) return;
    const fd = new FormData();
    fd.append('action','delete');
    fd.append('id', id);
    try {
        const res  = await fetch('../api/pagos_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            showToast('Eliminado', data.message, 'success');
            cargarPagos();
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error','No se pudo eliminar.','error');
    }
}

// ── Alerta de pagos en pestaña Cierre ────────────────────────
async function cargarAlertaPagosCierre() {
    try {
        const res  = await fetch(`../api/pagos_api.php?action=list&proyecto_id=${proyectoId}`);
        const data = await res.json();
        if (!data.success) return;

        const r   = data.resumen;
        const div = document.getElementById('alertaPagosCierre');
        if (!div) return;

        if (parseFloat(r.vencido) > 0 || parseFloat(r.pendiente) > 0) {
            div.innerHTML = `
            <div style="background:#FFF7ED;border:1px solid #FDE68A;border-radius:8px;
                        padding:12px 16px;margin-bottom:16px;font-size:12.5px;color:#92400E;">
                💳 <strong>Pagos pendientes:</strong>
                S/ ${fmt(r.pendiente)} pendiente
                ${parseFloat(r.vencido)>0 ? ` · <span style="color:#DC2626;font-weight:700;">S/ ${fmt(r.vencido)} vencido</span>` : ''}
                — <button onclick="switchTabByName('pagos')"
                           style="background:none;border:none;color:#1D4ED8;cursor:pointer;
                                  font-size:12px;font-weight:600;text-decoration:underline;">
                      Ver pagos →
                  </button>
            </div>`;
        } else if (parseFloat(r.cobrado) > 0) {
            div.innerHTML = `
            <div style="background:#D1FAE5;border:1px solid #6EE7B7;border-radius:8px;
                        padding:10px 14px;margin-bottom:16px;font-size:12.5px;color:#065F46;">
                ✅ <strong>Pagos al día</strong> — S/ ${fmt(r.cobrado)} cobrado.
            </div>`;
        }
    } catch(e) { /* silencioso */ }
}

const APP_URL_JS = '<?= APP_URL ?>';

// ── Cascade Proyecto → Entregable ─────────────────────────────
let _entregablesCache = {}; // cache por proyecto_id para no re-fetchear

async function cascadeCargarEntregables(proyId) {
    const selEnt   = document.getElementById('pagoEntregable');
    const spinner  = document.getElementById('cascadeSpinner');
    const buscWrap = document.getElementById('cascadeBuscadorWrap');
    const buscador = document.getElementById('cascadeBuscador');

    selEnt.innerHTML = '<option value="">Cargando...</option>';
    selEnt.disabled  = true;
    buscWrap.style.display = 'none';
    if (buscador) buscador.value = '';

    if (!proyId) {
        selEnt.innerHTML = '<option value="">— Primero elige proyecto —</option>';
        return;
    }

    // Usar cache si ya se cargó antes
    if (_entregablesCache[proyId]) {
        cascadeRellenarSelect(selEnt, _entregablesCache[proyId]);
        buscWrap.style.display = 'block';
        return;
    }

    spinner.style.display = 'inline';
    try {
        const res  = await fetch(`../api/pagos_api.php?action=get_entregables&proyecto_id=${proyId}`);
        const data = await res.json();

        if (data.success && data.data.length > 0) {
            _entregablesCache[proyId] = data.data;
            cascadeRellenarSelect(selEnt, data.data);
            buscWrap.style.display = 'block';
        } else {
            selEnt.innerHTML = '<option value="">Sin entregables</option>';
        }
    } catch(e) {
        selEnt.innerHTML = '<option value="">Error al cargar</option>';
    } finally {
        spinner.style.display = 'none';
    }
}

function cascadeRellenarSelect(sel, items) {
    const ESTADO_ICON = { completado: '✅', pendiente: '⏳' };
    sel.innerHTML = '<option value="">Sin entregable</option>'
        + items.map(e =>
            `<option value="${e.id}" data-nombre="${escHtml(e.nombre).toLowerCase()}">
                ${ESTADO_ICON[e.estado] ?? '⏳'} ${escHtml(e.nombre)}
             </option>`
        ).join('');
    sel.disabled = false;
}

function cascadeFiltrar(q) {
    const sel = document.getElementById('pagoEntregable');
    const term = q.toLowerCase();
    Array.from(sel.options).forEach(opt => {
        if (!opt.value) { opt.style.display = ''; return; } // "Sin entregable" siempre visible
        opt.style.display = opt.dataset.nombre?.includes(term) ? '' : 'none';
    });
}

// Pre-cargar entregables del proyecto actual al abrir el modal
function abrirNuevoPago() {
    document.getElementById('tituloPagoModal').textContent = 'Nuevo Pago';
    document.getElementById('editPagoId').value = '';
    document.getElementById('formNuevoPago').reset();
    document.getElementById('cascadeProyecto').value = proyectoId;
    cascadeCargarEntregables(proyectoId);
    openModal('modalNuevoPago');
}

// Al editar, pre-seleccionar el entregable correspondiente
async function editarPago(p) {
    document.getElementById('tituloPagoModal').textContent = 'Editar Pago';
    document.getElementById('editPagoId').value       = p.id;
    document.getElementById('pagoConcepto').value     = p.concepto ?? '';
    document.getElementById('pagoMonto').value        = p.monto ?? '';
    document.getElementById('pagoVencimiento').value  = p.fecha_vencimiento ? p.fecha_vencimiento.split('T')[0] : '';
    document.getElementById('pagoFechaPago').value    = p.fecha_pago ? p.fecha_pago.split('T')[0] : '';
    document.getElementById('pagoMetodo').value       = p.metodo_pago ?? '';
    document.getElementById('pagoComprobante').value  = p.numero_comprobante ?? '';
    document.getElementById('pagoNotas').value        = p.notas ?? '';

    // Cascade: proyecto → entregable
    const pid = p.proyecto_id ?? proyectoId;
    document.getElementById('cascadeProyecto').value = pid;
    await cascadeCargarEntregables(pid);
    if (p.entregable_id) {
        document.getElementById('pagoEntregable').value = p.entregable_id;
    }

    openModal('modalNuevoPago');
}

// ── Guardar cambios del proyecto ─────────────────────────────
async function guardarCambios() {
    const form = document.getElementById('formEditarProyecto');
    const fd   = new FormData(form);

    try {
        const res  = await fetch('../api/proyectos_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            showToast('Proyecto actualizado', 'Cambios guardados.', 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudieron guardar los cambios.', 'error');
    }
}

// ── Crear entregable ──────────────────────────────────────────
async function guardarEntregable() {
    const form = document.getElementById('formNuevoEntregable');
    const fd   = new FormData(form);
    fd.append('action', 'create');

    try {
        const res  = await fetch('../api/entregables_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            showToast('Entregable creado', data.message, 'success');
            closeModal('modalNuevoEntregable');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo crear el entregable.', 'error');
    }
}

// ── Actualizar porcentaje ─────────────────────────────────────
async function actualizarPorcentaje(id, porcentaje) {
    const card  = event.target.closest('.entregable-card');
    const nombre = card.querySelector('[style*="font-weight:700"]').textContent.trim();

    const fd = new FormData();
    fd.append('action', 'update');
    fd.append('id', id);
    fd.append('nombre', nombre);
    fd.append('porcentaje', porcentaje);

    try {
        const res  = await fetch('../api/entregables_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            showToast('Actualizado', 'Porcentaje guardado.', 'success');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo actualizar.', 'error');
    }
}

// ── Subir archivo ─────────────────────────────────────────────
async function subirArchivo(id, file) {
    if (!file) return;

    const fd = new FormData();
    fd.append('action', 'upload');
    fd.append('id', id);
    fd.append('archivo', file);

    showToast('Subiendo...', 'Por favor espera', 'info');

    try {
        const res  = await fetch('../api/entregables_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            showToast('Archivo subido', data.message, 'success');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo subir el archivo.', 'error');
    }
}

// ── Eliminar archivo individual ───────────────────────────────
async function eliminarArchivo(archivoId, btn) {
    if (!confirm('¿Eliminar este archivo?')) return;

    const fd = new FormData();
    fd.append('action', 'delete_archivo');
    fd.append('archivo_id', archivoId);

    try {
        const res  = await fetch('../api/entregables_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            // Eliminar el badge del DOM sin recargar
            btn.closest('div[style*="display:flex"]').remove();
            showToast('Eliminado', data.message, 'success');
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo eliminar el archivo.', 'error');
    }
}

// ── Ampliar fecha ─────────────────────────────────────────────
function abrirAmpliarFecha(id, fechaActual) {
    document.getElementById('ampliar_entregable_id').value = id;
    document.getElementById('ampliar_fecha_actual').value  = fechaActual || '';
    document.getElementById('ampliar_nueva_fecha').value   = '';
    document.getElementById('ampliar_justificacion').value = '';
    openModal('modalAmpliarFecha');
}

async function guardarAmpliarFecha() {
    const id            = document.getElementById('ampliar_entregable_id').value;
    const nueva_fecha   = document.getElementById('ampliar_nueva_fecha').value;
    const justificacion = document.getElementById('ampliar_justificacion').value.trim();

    if (!nueva_fecha) {
        showToast('Error', 'Selecciona la nueva fecha.', 'error');
        return;
    }
    if (!justificacion) {
        showToast('Error', 'La justificación es obligatoria.', 'error');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'ampliar_fecha');
    fd.append('id', id);
    fd.append('nueva_fecha_fin', nueva_fecha);
    fd.append('justificacion', justificacion);

    try {
        const res  = await fetch('../api/entregables_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            showToast('Fecha ampliada', data.message, 'success');
            closeModal('modalAmpliarFecha');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo ampliar la fecha.', 'error');
    }
}

// ── Toggle completado ─────────────────────────────────────────
async function toggleCompletado(id, completado) {
    const fd = new FormData();
    fd.append('action', 'marcar_completado');
    fd.append('id', id);
    fd.append('completado', completado);

    try {
        const res  = await fetch('../api/entregables_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            showToast('Estado actualizado', data.message, 'success');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo actualizar el estado.', 'error');
    }
}

// ── Finalizar proyecto → Puesta en Marcha ────────────────────
async function finalizarProyecto() {
    const obs = document.getElementById('cierre_observaciones')?.value?.trim() ?? '';

    if (!confirm('¿Confirmar el cierre del proyecto?\n\nEsto lo marcará como Completado y lo enviará a Puesta en Marcha.')) return;

    const fd = new FormData();
    fd.append('action', 'finalizar');
    fd.append('id', proyectoId);
    fd.append('observaciones', obs);

    try {
        const res  = await fetch('../api/proyectos_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            showToast('¡Proyecto cerrado!', 'Pasando a Puesta en Marcha...', 'success');
            setTimeout(() => {
                window.location.href = 'marcha.php?proyecto_id=' + proyectoId;
            }, 1500);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo finalizar el proyecto.', 'error');
    }
}

// ── Eliminar entregable ───────────────────────────────────────
async function eliminarEntregable(id) {
    if (!confirm('¿Eliminar este entregable y todos sus archivos?')) return;

    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);

    try {
        const res  = await fetch('../api/entregables_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            showToast('Eliminado', data.message, 'success');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo eliminar.', 'error');
    }
}

// ── Conformidad: subir ────────────────────────────────────────
async function subirConformidad(input) {
    if (!input.files.length) return;
    const file = input.files[0];

    if (file.type !== 'application/pdf' && !file.name.endsWith('.pdf')) {
        showToast('Formato inválido', 'Solo se permite PDF.', 'error');
        input.value = '';
        return;
    }

    document.getElementById('conformidadProgress').style.display = 'block';
    document.getElementById('conformidadBar').style.width = '40%';

    const fd = new FormData();
    fd.append('action',  'upload_conformidad');
    fd.append('id',      proyectoId);
    fd.append('archivo', file);

    try {
        document.getElementById('conformidadBar').style.width = '70%';
        const res  = await fetch('../api/proyectos_api.php', { method:'POST', body:fd });
        const data = await res.json();
        document.getElementById('conformidadBar').style.width = '100%';

        if (data.success) {
            showToast('Subido', data.message, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast('Error', data.message, 'error');
            document.getElementById('conformidadProgress').style.display = 'none';
            input.value = '';
        }
    } catch(e) {
        showToast('Error', 'No se pudo subir el archivo.', 'error');
        document.getElementById('conformidadProgress').style.display = 'none';
        input.value = '';
    }
}

// ── Conformidad: eliminar ─────────────────────────────────────
async function eliminarConformidad() {
    if (!confirm('¿Eliminar el documento de conformidad adjunto?')) return;

    const fd = new FormData();
    fd.append('action', 'delete_conformidad');
    fd.append('id',     proyectoId);

    try {
        const res  = await fetch('../api/proyectos_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            showToast('Eliminado', data.message, 'success');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo eliminar.', 'error');
    }
}
</script>
