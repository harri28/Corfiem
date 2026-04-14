<?php
// ============================================================
//  modules/cuestionario_tomar.php — Realizar Cuestionario
//  Ruta: C:\xampp\htdocs\Corfiem_Cesar\modules\cuestionario_tomar.php
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session = require_auth();
$uid     = (int)$session['usuario_id'];
$page_title  = 'Realizar Cuestionario';
$page_active = 'capacitacion';

$cuestionario_id = (int)($_GET['id'] ?? 0);
if ($cuestionario_id <= 0) {
    header('Location: capacitacion.php');
    exit;
}

// Cargar cuestionario
$cuestionario = db_fetch_one(
    "SELECT q.*, c.titulo AS curso_titulo, c.id AS curso_id
     FROM cuestionarios q
     LEFT JOIN cursos c ON q.curso_id = c.id
     WHERE q.id = ?",
    [$cuestionario_id]
);

if (!$cuestionario) {
    header('Location: capacitacion.php');
    exit;
}

// Verificar inscripción
$inscrito = db_fetch_one(
    "SELECT id FROM inscripciones_curso 
     WHERE curso_id = ? AND usuario_id = ?",
    [$cuestionario['curso_id'], $uid]
);

if (!$inscrito) {
    header('Location: curso_detalle.php?id=' . $cuestionario['curso_id']);
    exit;
}

// Verificar intentos disponibles
$intentos_realizados = (int)db_fetch_one(
    "SELECT COUNT(*) as total FROM intentos_cuestionario 
     WHERE cuestionario_id = ? AND usuario_id = ?",
    [$cuestionario_id, $uid]
)['total'];

$intentos_restantes = $cuestionario['intentos_permitidos'] - $intentos_realizados;

if ($intentos_restantes <= 0) {
    $mejor_intento = db_fetch_one(
        "SELECT MAX(porcentaje) as nota, aprobado
         FROM intentos_cuestionario
         WHERE cuestionario_id = ? AND usuario_id = ?",
        [$cuestionario_id, $uid]
    );
}

// Cargar preguntas
$preguntas = db_fetch_all(
    "SELECT * FROM preguntas
     WHERE cuestionario_id = ?
     ORDER BY orden, id",
    [$cuestionario_id]
);

// Cargar opciones
foreach ($preguntas as &$pregunta) {
    $pregunta['opciones'] = db_fetch_all(
        "SELECT id, texto FROM opciones_respuesta
         WHERE pregunta_id = ?
         ORDER BY orden, id",
        [$pregunta['id']]
    );
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
.pregunta-box {
    background: #fff;
    border: 1px solid var(--c-border);
    border-radius: var(--radius);
    padding: 20px 24px;
    margin-bottom: 20px;
}
.opcion-radio {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: #FAFAFA;
    border: 2px solid var(--c-border);
    border-radius: 6px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s;
}
.opcion-radio:hover {
    background: #F0F9FF;
    border-color: var(--c-navy);
}
.opcion-radio input[type="radio"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}
.opcion-radio.seleccionada {
    background: #EFF3FB;
    border-color: var(--c-navy);
    font-weight: 600;
}
.timer-box {
    position: fixed;
    top: 80px;
    right: 20px;
    background: #fff;
    border: 2px solid var(--c-border);
    border-radius: var(--radius);
    padding: 16px 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    z-index: 100;
}
.resultado-box {
    text-align: center;
    padding: 40px 20px;
}
.nota-grande {
    font-size: 64px;
    font-weight: 700;
    margin: 20px 0;
}
</style>

<div class="main-content">
<?php render_topbar($cuestionario['titulo'], $cuestionario['curso_titulo']); ?>

<div class="page-body">

    <?php if ($intentos_restantes <= 0): ?>
        <!-- Sin intentos disponibles -->
        <div class="card">
            <div class="resultado-box">
                <div style="font-size:48px;margin-bottom:16px;">⚠️</div>
                <h2 style="font-size:24px;margin-bottom:12px;">
                    Has agotado todos los intentos
                </h2>
                <p style="font-size:15px;color:var(--c-text-3);margin-bottom:24px;">
                    Realizaste <?= $cuestionario['intentos_permitidos'] ?>/<?= $cuestionario['intentos_permitidos'] ?> intentos permitidos
                </p>
                
                <?php if ($mejor_intento): ?>
                <div style="display:inline-block;background:<?= $mejor_intento['aprobado'] ? 'var(--c-success-lt)' : '#FEF2F2' ?>;
                            border:2px solid <?= $mejor_intento['aprobado'] ? 'var(--c-success)' : 'var(--c-danger)' ?>;
                            border-radius:var(--radius);padding:20px 30px;margin-bottom:20px;">
                    <div style="font-size:13px;color:var(--c-text-3);margin-bottom:6px;">Tu mejor calificación</div>
                    <div class="nota-grande" style="color:<?= $mejor_intento['aprobado'] ? 'var(--c-success)' : 'var(--c-danger)' ?>;">
                        <?= round($mejor_intento['nota'], 1) ?>%
                    </div>
                    <div style="font-size:14px;font-weight:600;margin-top:8px;">
                        <?= $mejor_intento['aprobado'] ? '✓ APROBADO' : '✗ NO APROBADO' ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div>
                    <a href="curso_detalle.php?id=<?= $cuestionario['curso_id'] ?>" 
                       class="btn btn-primary">
                        Volver al Curso
                    </a>
                </div>
            </div>
        </div>
    
    <?php else: ?>
        <!-- Realizar cuestionario -->
        
        <?php if ($cuestionario['tiempo_limite_minutos']): ?>
        <div class="timer-box" id="timerBox">
            <div style="font-size:11px;color:var(--c-text-3);margin-bottom:4px;">TIEMPO RESTANTE</div>
            <div id="timer" style="font-size:28px;font-weight:700;color:var(--c-navy);">
                <?= $cuestionario['tiempo_limite_minutos'] ?>:00
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <div>
                    <span class="card-title">Instrucciones</span>
                </div>
            </div>
            <div style="padding:18px 24px;background:#FAFAFA;border-top:1px solid var(--c-border);">
                <ul style="margin:0;padding-left:20px;font-size:14px;line-height:1.8;">
                    <li>Total de preguntas: <strong><?= count($preguntas) ?></strong></li>
                    <li>Nota mínima para aprobar: <strong><?= $cuestionario['puntaje_minimo_aprobacion'] ?>%</strong></li>
                    <li>Intentos disponibles: <strong><?= $intentos_restantes ?>/<?= $cuestionario['intentos_permitidos'] ?></strong></li>
                    <?php if ($cuestionario['tiempo_limite_minutos']): ?>
                    <li>Tiempo límite: <strong><?= $cuestionario['tiempo_limite_minutos'] ?> minutos</strong></li>
                    <?php endif; ?>
                    <li>Selecciona una respuesta para cada pregunta y envía al finalizar</li>
                </ul>
            </div>
        </div>

        <form id="formCuestionario">
            <?php foreach ($preguntas as $idx => $p): ?>
            <div class="pregunta-box">
                <div style="display:flex;align-items:start;gap:12px;margin-bottom:16px;">
                    <span style="display:inline-flex;align-items:center;justify-content:center;
                                 min-width:32px;height:32px;border-radius:50%;
                                 background:var(--c-navy);color:#fff;
                                 font-size:14px;font-weight:700;">
                        <?= $idx + 1 ?>
                    </span>
                    <div style="flex:1;">
                        <div style="font-size:15px;font-weight:600;line-height:1.6;">
                            <?= nl2br(htmlspecialchars($p['texto'])) ?>
                        </div>
                        <div style="font-size:12px;color:var(--c-text-3);margin-top:6px;">
                            <?= $p['puntos'] ?> punto<?= $p['puntos'] != 1 ? 's' : '' ?>
                        </div>
                    </div>
                </div>
                
                <div style="margin-left:44px;">
                    <?php foreach ($p['opciones'] as $opc): ?>
                    <label class="opcion-radio" onclick="this.classList.add('seleccionada');
                           this.parentElement.querySelectorAll('.opcion-radio').forEach(el => {
                               if(el !== this) el.classList.remove('seleccionada');
                           })">
                        <input type="radio" name="pregunta_<?= $p['id'] ?>" 
                               value="<?= $opc['id'] ?>" required>
                        <span style="flex:1;font-size:14px;">
                            <?= htmlspecialchars($opc['texto']) ?>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="card" style="padding:20px 24px;text-align:center;">
                <button type="submit" class="btn btn-primary" style="min-width:200px;font-size:15px;">
                    ✓ Enviar Respuestas
                </button>
                <div style="margin-top:12px;font-size:13px;color:var(--c-text-3);">
                    Asegúrate de haber respondido todas las preguntas
                </div>
            </div>
        </form>
    <?php endif; ?>

</div><!-- .page-body -->
</div><!-- .main-content -->

<!-- MODAL: Resultado -->
<div class="modal-overlay" id="modalResultado">
    <div class="modal-box" style="max-width:500px;">
        <div class="modal-header">
            <span class="modal-title">Resultado del Cuestionario</span>
        </div>
        <div class="modal-body">
            <div class="resultado-box">
                <div id="resultadoIcono" style="font-size:64px;margin-bottom:16px;"></div>
                <h3 id="resultadoTitulo" style="font-size:22px;margin-bottom:12px;"></h3>
                <div class="nota-grande" id="resultadoNota"></div>
                <p id="resultadoMensaje" style="font-size:14px;color:var(--c-text-3);margin-top:16px;"></p>
            </div>
        </div>
        <div class="modal-footer">
            <a href="curso_detalle.php?id=<?= $cuestionario['curso_id'] ?>" 
               class="btn btn-primary">
                Volver al Curso
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
const cuestionarioId = <?= $cuestionario_id ?>;
const tiempoLimite = <?= $cuestionario['tiempo_limite_minutos'] ?? 0 ?>;
let intentoId = null;
let tiempoRestante = tiempoLimite * 60; // en segundos
let timerInterval = null;

// ── Iniciar intento ───────────────────────────────────────────
async function iniciarIntento() {
    const fd = new FormData();
    fd.append('action', 'iniciar_intento');
    fd.append('cuestionario_id', cuestionarioId);
    
    const res = await fetch('../api/capacitacion_api.php', { method: 'POST', body: fd });
    const data = await res.json();
    
    if (data.success) {
        intentoId = data.intento_id;
        
        // Iniciar timer si hay límite de tiempo
        if (tiempoLimite > 0) {
            iniciarTimer();
        }
    } else {
        showToast('Error', data.message, 'error');
        setTimeout(() => location.reload(), 2000);
    }
}

// ── Timer ─────────────────────────────────────────────────────
function iniciarTimer() {
    timerInterval = setInterval(() => {
        tiempoRestante--;
        
        const minutos = Math.floor(tiempoRestante / 60);
        const segundos = tiempoRestante % 60;
        
        document.getElementById('timer').textContent = 
            `${minutos}:${segundos.toString().padStart(2, '0')}`;
        
        // Cambiar color cuando queden menos de 2 minutos
        if (tiempoRestante < 120) {
            document.getElementById('timer').style.color = 'var(--c-danger)';
        }
        
        // Tiempo agotado
        if (tiempoRestante <= 0) {
            clearInterval(timerInterval);
            showToast('Tiempo agotado', 'Se enviará automáticamente...', 'error');
            setTimeout(() => {
                document.getElementById('formCuestionario').requestSubmit();
            }, 2000);
        }
    }, 1000);
}

// ── Enviar respuestas ─────────────────────────────────────────
document.getElementById('formCuestionario').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    if (timerInterval) clearInterval(timerInterval);
    
    const fd = new FormData(e.target);
    
    // Convertir respuestas a JSON
    const respuestas = {};
    for (let [key, value] of fd.entries()) {
        if (key.startsWith('pregunta_')) {
            const preguntaId = key.replace('pregunta_', '');
            respuestas[preguntaId] = value;
        }
    }
    
    const envio = new FormData();
    envio.append('action', 'enviar_respuestas');
    envio.append('intento_id', intentoId);
    envio.append('respuestas', JSON.stringify(respuestas));
    
    try {
        const res = await fetch('../api/capacitacion_api.php', { method: 'POST', body: envio });
        const data = await res.json();
        
        if (data.success) {
            mostrarResultado(data);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch (e) {
        showToast('Error', 'No se pudo enviar el cuestionario.', 'error');
    }
});

// ── Mostrar resultado ─────────────────────────────────────────
function mostrarResultado(data) {
    const aprobado = data.aprobado;
    const nota = data.calificacion;
    
    document.getElementById('resultadoIcono').textContent = aprobado ? '🎉' : '😔';
    document.getElementById('resultadoTitulo').textContent = 
        aprobado ? '¡Felicitaciones!' : 'No aprobaste';
    document.getElementById('resultadoNota').textContent = nota + '%';
    document.getElementById('resultadoNota').style.color = 
        aprobado ? 'var(--c-success)' : 'var(--c-danger)';
    document.getElementById('resultadoMensaje').textContent = data.message;
    
    openModal('modalResultado');
}

// Iniciar intento al cargar la página
<?php if ($intentos_restantes > 0): ?>
iniciarIntento();
<?php endif; ?>
</script>