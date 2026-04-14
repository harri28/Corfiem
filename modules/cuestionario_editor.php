<?php
// ============================================================
//  modules/cuestionario_editor.php — Editor de Preguntas
//  Ruta: C:\xampp\htdocs\Corfiem_Cesar\modules\cuestionario_editor.php
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session = require_auth();
$uid     = (int)$session['usuario_id'];
$page_title  = 'Editor de Cuestionario';
$page_active = 'capacitacion';

$es_admin = $session['usuario_rol'] === 'Admin';

if (!$es_admin) {
    header('Location: capacitacion.php');
    exit;
}

$cuestionario_id = (int)($_GET['id'] ?? 0);
if ($cuestionario_id <= 0) {
    header('Location: capacitacion.php');
    exit;
}

// Cargar cuestionario
$cuestionario = db_fetch_one(
    "SELECT q.*, c.titulo AS curso_titulo
     FROM cuestionarios q
     LEFT JOIN cursos c ON q.curso_id = c.id
     WHERE q.id = ?",
    [$cuestionario_id]
);

if (!$cuestionario) {
    header('Location: capacitacion.php');
    exit;
}

// Cargar preguntas
$preguntas = db_fetch_all(
    "SELECT * FROM preguntas
     WHERE cuestionario_id = ?
     ORDER BY orden, id",
    [$cuestionario_id]
);

// Cargar opciones de cada pregunta
foreach ($preguntas as &$pregunta) {
    $pregunta['opciones'] = db_fetch_all(
        "SELECT * FROM opciones_respuesta
         WHERE pregunta_id = ?
         ORDER BY orden, id",
        [$pregunta['id']]
    );
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
.pregunta-card {
    background: #fff;
    border: 1px solid var(--c-border);
    border-radius: var(--radius);
    padding: 18px 20px;
    margin-bottom: 16px;
}
.opcion-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: #FAFAFA;
    border: 1px solid var(--c-border);
    border-radius: 4px;
    margin-bottom: 8px;
}
.opcion-item.correcta {
    background: #ECFDF5;
    border-color: #059669;
}
.opcion-correcta-icon {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #059669;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}
</style>

<div class="main-content">
<?php render_topbar('Editor de Cuestionario', $cuestionario['titulo']); ?>

<div class="page-body">

    <!-- Info del cuestionario -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
            <div>
                <span class="card-title"><?= htmlspecialchars($cuestionario['titulo']) ?></span>
                <div style="font-size:13px;color:var(--c-text-3);margin-top:4px;">
                    Curso: <?= htmlspecialchars($cuestionario['curso_titulo']) ?>
                </div>
            </div>
            <a href="curso_detalle.php?id=<?= $cuestionario['curso_id'] ?>" 
               class="btn btn-secondary btn-sm">
                ← Volver al Curso
            </a>
        </div>
        
        <div style="padding:16px 20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
                    gap:16px;border-top:1px solid var(--c-border);background:#FAFAFA;">
            <div>
                <div style="font-size:11px;color:var(--c-text-3);margin-bottom:2px;">Total Preguntas</div>
                <div style="font-size:14px;font-weight:700;">📝 <?= count($preguntas) ?></div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--c-text-3);margin-bottom:2px;">Nota Mínima</div>
                <div style="font-size:14px;font-weight:700;">📊 <?= $cuestionario['puntaje_minimo_aprobacion'] ?>%</div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--c-text-3);margin-bottom:2px;">Intentos Permitidos</div>
                <div style="font-size:14px;font-weight:700;">🔁 <?= $cuestionario['intentos_permitidos'] ?></div>
            </div>
            <?php if ($cuestionario['tiempo_limite_minutos']): ?>
            <div>
                <div style="font-size:11px;color:var(--c-text-3);margin-bottom:2px;">Tiempo Límite</div>
                <div style="font-size:14px;font-weight:700;">⏱ <?= $cuestionario['tiempo_limite_minutos'] ?> min</div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lista de preguntas -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Preguntas del Cuestionario</span>
            <button class="btn btn-primary btn-sm" onclick="openModal('modalNuevaPregunta')">
                + Nueva Pregunta
            </button>
        </div>

        <div style="padding:20px;">
            <?php if (empty($preguntas)): ?>
                <div style="text-align:center;padding:40px;color:var(--c-text-3);">
                    <p style="font-size:15px;margin-bottom:8px;">No hay preguntas aún</p>
                    <p style="font-size:13px;">Agrega la primera pregunta para comenzar</p>
                </div>
            <?php else: ?>
                <?php foreach ($preguntas as $idx => $p): ?>
                <div class="pregunta-card">
                    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px;">
                        <div style="flex:1;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                                <span style="display:inline-flex;align-items:center;justify-content:center;
                                             width:28px;height:28px;border-radius:50%;
                                             background:var(--c-navy);color:#fff;
                                             font-size:13px;font-weight:700;">
                                    <?= $idx + 1 ?>
                                </span>
                                <span class="badge badge-navy" style="font-size:11px;">
                                    <?= ucfirst($p['tipo']) ?>
                                </span>
                                <span style="font-size:12px;color:var(--c-text-3);">
                                    Puntaje: <strong><?= $p['puntos'] ?></strong> pts
                                </span>
                            </div>
                            <div style="font-size:14.5px;font-weight:500;line-height:1.5;">
                                <?= nl2br(htmlspecialchars($p['texto'])) ?>
                            </div>
                        </div>
                        <button class="btn btn-sm" 
                                style="background:var(--c-danger);color:#fff;margin-left:16px;"
                                onclick="eliminarPregunta(<?= $p['id'] ?>)">
                            🗑 Eliminar
                        </button>
                    </div>
                    
                    <?php if ($p['tipo'] === 'multiple_choice' && !empty($p['opciones'])): ?>
                    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--c-border);">
                        <div style="font-size:12px;font-weight:600;color:var(--c-text-3);
                                    margin-bottom:10px;text-transform:uppercase;letter-spacing:0.05em;">
                            Opciones de Respuesta
                        </div>
                        <?php foreach ($p['opciones'] as $opc): ?>
                        <div class="opcion-item <?= $opc['es_correcta'] ? 'correcta' : '' ?>">
                            <?php if ($opc['es_correcta']): ?>
                            <div class="opcion-correcta-icon">✓</div>
                            <?php else: ?>
                            <div style="width:20px;height:20px;border-radius:50%;
                                        border:2px solid var(--c-border);background:#fff;"></div>
                            <?php endif; ?>
                            <div style="flex:1;font-size:13.5px;">
                                <?= htmlspecialchars($opc['texto']) ?>
                            </div>
                            <?php if ($opc['es_correcta']): ?>
                            <span style="font-size:11px;color:#059669;font-weight:700;">CORRECTA</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php elseif ($p['tipo'] === 'verdadero_falso' && !empty($p['opciones'])): ?>
                    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--c-border);">
                        <div style="font-size:12px;font-weight:600;color:var(--c-text-3);margin-bottom:10px;">
                            VERDADERO / FALSO
                        </div>
                        <div style="display:flex;gap:10px;">
                        <?php foreach ($p['opciones'] as $opc): ?>
                        <div style="display:flex;align-items:center;gap:8px;padding:8px 14px;
                                    border-radius:6px;font-size:13px;font-weight:600;
                                    background:<?= $opc['es_correcta'] ? '#ECFDF5' : '#F9FAFB' ?>;
                                    border:2px solid <?= $opc['es_correcta'] ? '#059669' : 'var(--c-border)' ?>;
                                    color:<?= $opc['es_correcta'] ? '#059669' : 'var(--c-text-3)' ?>;">
                            <?= $opc['es_correcta'] ? '✓' : '○' ?> <?= htmlspecialchars($opc['texto']) ?>
                        </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <?php elseif ($p['tipo'] === 'respuesta_corta'): ?>
                    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--c-border);">
                        <div style="font-size:12px;font-weight:600;color:var(--c-text-3);margin-bottom:8px;">
                            RESPUESTA ABIERTA
                        </div>
                        <div style="background:#FFF7ED;border:1px solid #FED7AA;border-radius:6px;
                                    padding:10px 14px;font-size:13px;color:#92400E;">
                            ✏️ El alumno escribirá su respuesta libremente. Revisión manual requerida.
                            <?php if ($p['explicacion']): ?>
                            <div style="margin-top:6px;padding-top:6px;border-top:1px solid #FED7AA;
                                        color:var(--c-text-2);">
                                <strong>Respuesta esperada:</strong> <?= htmlspecialchars($p['explicacion']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div><!-- .page-body -->
</div><!-- .main-content -->

<!-- MODAL: Nueva Pregunta -->
<div class="modal-overlay" id="modalNuevaPregunta">
    <div class="modal-box" style="max-width:700px;">
        <div class="modal-header">
            <span class="modal-title">Nueva Pregunta</span>
            <button class="modal-close" onclick="closeModal('modalNuevaPregunta')">×</button>
        </div>
        <div class="modal-body">
            <form id="formNuevaPregunta">
                <input type="hidden" name="cuestionario_id" value="<?= $cuestionario_id ?>">
                
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Pregunta <span class="required">*</span></label>
                        <textarea class="form-control" name="pregunta" rows="3" 
                                  placeholder="Escribe la pregunta..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipo de Pregunta</label>
                        <select class="form-control" name="tipo" id="tipoPregunta" onchange="cambiarTipoPregunta()">
                            <option value="multiple_choice">Opción Múltiple</option>
                            <option value="verdadero_falso">Verdadero/Falso</option>
                            <option value="respuesta_corta">Respuesta Abierta</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Puntaje</label>
                        <input class="form-control" type="number" name="puntaje" 
                               value="1" min="0" step="0.5">
                    </div>
                    
                    <div class="form-group" id="opcionesContainer" style="grid-column:1/-1;">
                        <label class="form-label">Opciones de Respuesta</label>
                        <div id="opcionesList">
                            <div class="opcion-input" style="display:flex;gap:8px;margin-bottom:8px;">
                                <input type="checkbox" class="opcion-correcta" title="Marcar como correcta">
                                <input type="text" class="form-control opcion-texto"
                                       placeholder="Opción 1" style="flex:1;">
                                <button type="button" class="btn btn-sm"
                                        style="background:var(--c-danger);color:#fff;"
                                        onclick="this.parentElement.remove()">🗑</button>
                            </div>
                            <div class="opcion-input" style="display:flex;gap:8px;margin-bottom:8px;">
                                <input type="checkbox" class="opcion-correcta" title="Marcar como correcta">
                                <input type="text" class="form-control opcion-texto"
                                       placeholder="Opción 2" style="flex:1;">
                                <button type="button" class="btn btn-sm"
                                        style="background:var(--c-danger);color:#fff;"
                                        onclick="this.parentElement.remove()">🗑</button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm"
                                style="margin-top:8px;" onclick="agregarOpcion()">
                            + Agregar Opción
                        </button>
                    </div>

                    <div class="form-group" id="respuestaAbiertaContainer" style="grid-column:1/-1;display:none;">
                        <div style="background:#FFF7ED;border:1px solid #FED7AA;border-radius:6px;
                                    padding:12px 16px;margin-bottom:12px;font-size:13px;color:#92400E;">
                            ✏️ El alumno escribirá su respuesta libremente. Puedes indicar una respuesta de referencia.
                        </div>
                        <label class="form-label">Respuesta esperada / referencia (opcional)</label>
                        <textarea class="form-control" name="respuesta_referencia" rows="3"
                                  placeholder="Escribe la respuesta correcta o de referencia para el evaluador..."></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalNuevaPregunta')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarPregunta()">Guardar Pregunta</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
const cuestionarioId = <?= $cuestionario_id ?>;

// ── Cambiar tipo de pregunta ─────────────────────────────────
function cambiarTipoPregunta() {
    const tipo      = document.getElementById('tipoPregunta').value;
    const container = document.getElementById('opcionesContainer');
    const abierto   = document.getElementById('respuestaAbiertaContainer');
    const lista     = document.getElementById('opcionesList');
    const addBtn    = container.querySelector('.btn-secondary');

    if (tipo === 'respuesta_corta') {
        container.style.display = 'none';
        abierto.style.display   = '';
    } else {
        container.style.display = '';
        abierto.style.display   = 'none';

        if (tipo === 'verdadero_falso') {
            lista.innerHTML = `
                <div class="opcion-input" style="display:flex;gap:8px;margin-bottom:8px;">
                    <input type="checkbox" class="opcion-correcta" title="Marcar como correcta">
                    <input type="text" class="form-control opcion-texto" value="Verdadero" readonly style="flex:1;">
                </div>
                <div class="opcion-input" style="display:flex;gap:8px;margin-bottom:8px;">
                    <input type="checkbox" class="opcion-correcta" title="Marcar como correcta">
                    <input type="text" class="form-control opcion-texto" value="Falso" readonly style="flex:1;">
                </div>`;
            addBtn.style.display = 'none';
        } else {
            lista.innerHTML = `
                <div class="opcion-input" style="display:flex;gap:8px;margin-bottom:8px;">
                    <input type="checkbox" class="opcion-correcta" title="Marcar como correcta">
                    <input type="text" class="form-control opcion-texto" placeholder="Opción 1" style="flex:1;">
                    <button type="button" class="btn btn-sm" style="background:var(--c-danger);color:#fff;"
                            onclick="this.parentElement.remove()">🗑</button>
                </div>
                <div class="opcion-input" style="display:flex;gap:8px;margin-bottom:8px;">
                    <input type="checkbox" class="opcion-correcta" title="Marcar como correcta">
                    <input type="text" class="form-control opcion-texto" placeholder="Opción 2" style="flex:1;">
                    <button type="button" class="btn btn-sm" style="background:var(--c-danger);color:#fff;"
                            onclick="this.parentElement.remove()">🗑</button>
                </div>`;
            addBtn.style.display = 'inline-block';
        }
    }
}

// ── Agregar opción ────────────────────────────────────────────
function agregarOpcion() {
    const lista = document.getElementById('opcionesList');
    const numOpciones = lista.children.length + 1;
    
    const div = document.createElement('div');
    div.className = 'opcion-input';
    div.style.cssText = 'display:flex;gap:8px;margin-bottom:8px;';
    div.innerHTML = `
        <input type="checkbox" class="opcion-correcta" title="Marcar como correcta">
        <input type="text" class="form-control opcion-texto" 
               placeholder="Opción ${numOpciones}" style="flex:1;">
        <button type="button" class="btn btn-sm" 
                style="background:var(--c-danger);color:#fff;"
                onclick="this.parentElement.remove()">🗑</button>
    `;
    lista.appendChild(div);
}

// ── Guardar pregunta ──────────────────────────────────────────
async function guardarPregunta() {
    const form     = document.getElementById('formNuevaPregunta');
    const pregunta = form.pregunta.value.trim();
    const tipo     = form.tipo.value;
    const puntaje  = form.puntaje.value;

    if (!pregunta) {
        showToast('Campo requerido', 'Escribe la pregunta.', 'error');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'guardar_pregunta');
    fd.append('cuestionario_id', cuestionarioId);
    fd.append('pregunta', pregunta);
    fd.append('tipo', tipo);
    fd.append('puntaje', puntaje);

    if (tipo === 'respuesta_corta') {
        // Sin opciones — solo referencia en explicacion
        const ref = form.respuesta_referencia?.value?.trim() ?? '';
        fd.append('respuesta_referencia', ref);
        fd.append('opciones', JSON.stringify([]));
    } else {
        // Recopilar opciones
        const opciones = [];
        let hayCorrecta = false;
        document.querySelectorAll('.opcion-input').forEach(opc => {
            const texto    = opc.querySelector('.opcion-texto').value.trim();
            const correcta = opc.querySelector('.opcion-correcta').checked;
            if (texto) {
                opciones.push({ texto, correcta });
                if (correcta) hayCorrecta = true;
            }
        });

        if (opciones.length < 2) {
            showToast('Error', 'Agrega al menos 2 opciones.', 'error');
            return;
        }
        if (!hayCorrecta) {
            showToast('Error', 'Marca al menos una opción como correcta.', 'error');
            return;
        }
        fd.append('opciones', JSON.stringify(opciones));
    }

    try {
        const res  = await fetch('../api/capacitacion_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast('Pregunta guardada', data.message, 'success');
            closeModal('modalNuevaPregunta');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch (e) {
        showToast('Error', 'No se pudo guardar la pregunta.', 'error');
    }
}

// ── Eliminar pregunta ─────────────────────────────────────────
async function eliminarPregunta(id) {
    if (!confirm('¿Eliminar esta pregunta?')) return;
    
    const fd = new FormData();
    fd.append('action', 'eliminar_pregunta');
    fd.append('id', id);
    
    const res = await fetch('../api/capacitacion_api.php', { method: 'POST', body: fd });
    const data = await res.json();
    
    if (data.success) {
        showToast('Eliminada', data.message, 'success');
        setTimeout(() => location.reload(), 800);
    } else {
        showToast('Error', data.message, 'error');
    }
}
</script>