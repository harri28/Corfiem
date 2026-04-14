<?php
// ============================================================
//  modules/curso_detalle.php — Detalle completo del curso
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session = require_auth();
$uid     = (int)$session['usuario_id'];
$page_title  = 'Detalle del Curso';
$page_active = 'capacitacion';

$es_admin = in_array($session['usuario_rol'], ['Admin', 'Gerente']);

$curso_id = (int)($_GET['id'] ?? 0);
if ($curso_id <= 0) { header('Location: capacitacion.php'); exit; }

$instructores = db_fetch_all(
    "SELECT id, nombre || ' ' || apellido AS nombre_completo FROM usuarios WHERE activo = TRUE ORDER BY nombre"
);

$curso = db_fetch_one(
    "SELECT c.*, u.nombre || ' ' || u.apellido AS instructor_nombre
     FROM cursos c LEFT JOIN usuarios u ON c.instructor_id = u.id WHERE c.id = ?",
    [$curso_id]
);
if (!$curso) { header('Location: capacitacion.php'); exit; }

$materiales = db_fetch_all(
    "SELECT m.*,
     (SELECT pm.completado FROM progreso_materiales pm
      INNER JOIN inscripciones_curso ic ON pm.inscripcion_id = ic.id
      WHERE pm.material_id = m.id AND ic.usuario_id = ? AND ic.curso_id = m.curso_id
      LIMIT 1) AS mi_progreso
     FROM materiales_curso m WHERE m.curso_id = ? ORDER BY m.orden, m.id",
    [$uid, $curso_id]
);

$cuestionarios = db_fetch_all(
    "SELECT q.*,
     (SELECT COUNT(*) FROM preguntas p WHERE p.cuestionario_id = q.id) AS total_preguntas,
     (SELECT COUNT(*) FROM intentos_cuestionario i WHERE i.cuestionario_id = q.id AND i.usuario_id = ?) AS mis_intentos,
     (SELECT MAX(porcentaje) FROM intentos_cuestionario i WHERE i.cuestionario_id = q.id AND i.usuario_id = ?) AS mejor_nota
     FROM cuestionarios q WHERE q.curso_id = ? ORDER BY q.orden, q.id",
    [$uid, $uid, $curso_id]
);

$inscritos = db_fetch_all(
    "SELECT ic.*, u.nombre || ' ' || u.apellido AS usuario_nombre, u.email
     FROM inscripciones_curso ic
     LEFT JOIN usuarios u ON ic.usuario_id = u.id
     WHERE ic.curso_id = ? ORDER BY ic.fecha_inscripcion DESC",
    [$curso_id]
);

$mi_inscripcion = db_fetch_one(
    "SELECT * FROM inscripciones_curso WHERE curso_id = ? AND usuario_id = ?",
    [$curso_id, $uid]
);

// Progreso del usuario (materiales completados)
$total_obligatorios = count(array_filter($materiales, fn($m) => $m['obligatorio']));
$completados_oblig  = count(array_filter($materiales, fn($m) => $m['obligatorio'] && $m['mi_progreso']));
$progreso_pct = $total_obligatorios > 0 ? round($completados_oblig / $total_obligatorios * 100) : 0;

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
/* ── Tabs ─────────────────────────────────────────── */
.tab-nav { display:flex; gap:4px; border-bottom:2px solid var(--c-border); margin-bottom:24px; padding-bottom:0; }
.tab-nav-btn {
    padding:10px 20px; background:none; border:none; font-size:13.5px; font-weight:600;
    color:var(--c-text-3); cursor:pointer; border-bottom:3px solid transparent;
    margin-bottom:-2px; transition:all .2s; border-radius:4px 4px 0 0;
}
.tab-nav-btn:hover { color:var(--c-navy); background:#F8FAFF; }
.tab-nav-btn.active { color:var(--c-navy); border-bottom-color:var(--c-navy); background:#fff; }
.tab-panel { display:none; }
.tab-panel.active { display:block; }

/* ── Type picker ──────────────────────────────────── */
.type-picker { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px; }
.type-btn {
    display:flex; flex-direction:column; align-items:center; gap:6px;
    padding:14px 18px; background:#fff; border:2px solid var(--c-border);
    border-radius:10px; cursor:pointer; transition:all .2s; min-width:90px;
    font-size:12px; font-weight:600; color:var(--c-text-2);
}
.type-btn:hover { border-color:var(--c-navy); color:var(--c-navy); background:#F0F4FF; }
.type-btn .type-icon { font-size:24px; line-height:1; }

/* ── Material item ────────────────────────────────── */
.mat-item {
    display:flex; align-items:center; gap:14px; padding:14px 18px;
    border:1px solid var(--c-border); border-radius:8px; background:#fff;
    transition:all .2s; margin-bottom:10px;
}
.mat-item:hover { box-shadow:0 2px 8px rgba(0,0,0,.07); border-color:#CBD5E1; }
.mat-icon {
    width:42px; height:42px; border-radius:8px; display:flex;
    align-items:center; justify-content:center; flex-shrink:0; font-size:20px;
}
.mat-badge { font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; }

/* ── Progress bar ─────────────────────────────────── */
.prog-bar { height:6px; background:#E5E7EB; border-radius:3px; overflow:hidden; }
.prog-fill { height:100%; background:var(--c-navy); border-radius:3px; transition:width .4s; }

/* ── Viewer modal ─────────────────────────────────── */
#viewerModal .modal-box { max-width:900px; width:95vw; }
.texto-viewer {
    background:#FAFAFA; border:1px solid var(--c-border); border-radius:6px;
    padding:20px 24px; font-size:14px; line-height:1.8; white-space:pre-wrap;
    max-height:60vh; overflow-y:auto; font-family:Georgia,serif; color:var(--c-text-1);
}

/* ── Config form ──────────────────────────────────── */
.config-section { margin-bottom:28px; }
.config-section h4 { font-size:13px; font-weight:700; text-transform:uppercase;
    letter-spacing:.05em; color:var(--c-text-3); margin-bottom:14px;
    padding-bottom:8px; border-bottom:1px solid var(--c-border); }

/* ── Progress ring ────────────────────────────────── */
.prog-ring { position:relative; width:60px; height:60px; flex-shrink:0; }
.prog-ring svg { transform:rotate(-90deg); }
.prog-ring .ring-text {
    position:absolute; inset:0; display:flex; align-items:center;
    justify-content:center; font-size:13px; font-weight:700; color:var(--c-navy);
}
</style>

<div class="main-content">
<?php render_topbar($curso['titulo'], 'Detalle del curso · ' . htmlspecialchars($curso['curso_titulo'] ?? $curso['titulo'])); ?>

<div class="page-body">

<!-- ── Header del Curso ─────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px;">
    <div style="padding:20px 24px;display:flex;justify-content:space-between;align-items:start;gap:16px;flex-wrap:wrap;">
        <div style="flex:1;min-width:220px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <h2 style="font-size:20px;font-weight:800;color:var(--c-navy);margin:0;">
                    <?= htmlspecialchars($curso['titulo']) ?>
                </h2>
                <?php
                $sc = match($curso['estado']) {
                    'borrador'  => ['#6B7280','#F3F4F6'],
                    'publicado' => ['#1B3A6B','#EFF3FB'],
                    'archivado' => ['#92400E','#FEF3C7'],
                    default     => ['#374151','#F9FAFB']
                };
                ?>
                <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;
                             color:<?= $sc[0] ?>;background:<?= $sc[1] ?>;">
                    <?= strtoupper($curso['estado']) ?>
                </span>
            </div>
            <?php if ($curso['descripcion']): ?>
            <p style="font-size:14px;color:var(--c-text-3);margin:0 0 14px;line-height:1.6;">
                <?= htmlspecialchars($curso['descripcion']) ?>
            </p>
            <?php endif; ?>
            <div style="display:flex;gap:20px;flex-wrap:wrap;font-size:13px;color:var(--c-text-2);">
                <?php if ($curso['instructor_nombre']): ?>
                <span>👨‍🏫 <?= htmlspecialchars($curso['instructor_nombre']) ?></span>
                <?php endif; ?>
                <?php if ($curso['duracion_horas']): ?>
                <span>⏱ <?= $curso['duracion_horas'] ?> horas</span>
                <?php endif; ?>
                <span>📍 <?= htmlspecialchars($curso['modalidad']) ?></span>
                <span>👥 <?= count($inscritos) ?><?= $curso['max_participantes'] ? '/'. $curso['max_participantes'] : '' ?> inscritos</span>
            </div>
        </div>

        <!-- Progreso personal -->
        <?php if ($mi_inscripcion): ?>
        <div style="display:flex;align-items:center;gap:14px;background:#F8FAFF;border:1px solid #DBEAFE;
                    border-radius:10px;padding:14px 18px;">
            <div class="prog-ring">
                <svg width="60" height="60" viewBox="0 0 60 60">
                    <circle cx="30" cy="30" r="24" fill="none" stroke="#E5E7EB" stroke-width="6"/>
                    <circle cx="30" cy="30" r="24" fill="none" stroke="var(--c-navy)" stroke-width="6"
                            stroke-dasharray="<?= round(150.8 * $progreso_pct / 100) ?> 150.8"
                            stroke-linecap="round"/>
                </svg>
                <div class="ring-text"><?= $progreso_pct ?>%</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--c-text-3);margin-bottom:2px;">Mi progreso</div>
                <div style="font-size:13px;font-weight:600;">
                    <?= $completados_oblig ?>/<?= $total_obligatorios ?> materiales
                </div>
                <div style="font-size:11px;color:var(--c-text-3);margin-top:2px;">
                    Estado: <strong><?= $mi_inscripcion['estado'] ?></strong>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Botones de acción -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:start;">
            <?php if (!$mi_inscripcion): ?>
            <button class="btn btn-primary btn-sm" onclick="inscribirse()">✓ Inscribirme</button>
            <?php endif; ?>
            <?php if ($es_admin): ?>
            <button class="btn btn-secondary btn-sm" onclick="switchTab('config')">⚙ Configurar</button>
            <?php endif; ?>
            <a href="capacitacion.php" class="btn btn-secondary btn-sm">← Volver</a>
        </div>
    </div>

    <!-- Stats bar -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));
                border-top:1px solid var(--c-border);background:#FAFAFA;">
        <?php
        $stats = [
            ['📚', count($materiales), 'Materiales'],
            ['✍️', count($cuestionarios), 'Evaluaciones'],
            ['👥', count($inscritos), 'Participantes'],
            ['✅', count(array_filter($inscritos, fn($i) => $i['estado'] === 'completado')), 'Completados'],
        ];
        foreach ($stats as $s):
        ?>
        <div style="padding:12px 16px;text-align:center;border-right:1px solid var(--c-border);">
            <div style="font-size:18px;"><?= $s[0] ?></div>
            <div style="font-size:17px;font-weight:800;color:var(--c-navy);"><?= $s[1] ?></div>
            <div style="font-size:11px;color:var(--c-text-3);"><?= $s[2] ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── Tabs ────────────────────────────────────────────────── -->
<div class="card">
    <div style="padding:0 20px;">
        <nav class="tab-nav">
            <button class="tab-nav-btn active" onclick="switchTab('materiales')">📚 Materiales</button>
            <button class="tab-nav-btn" onclick="switchTab('evaluaciones')">✍️ Evaluaciones</button>
            <button class="tab-nav-btn" onclick="switchTab('participantes')">👥 Participantes</button>
            <?php if ($es_admin): ?>
            <button class="tab-nav-btn" onclick="switchTab('config')">⚙ Configuración</button>
            <?php endif; ?>
        </nav>
    </div>

    <!-- ───────────────────────────────────────────────────────
         TAB: MATERIALES
         ─────────────────────────────────────────────────────── -->
    <div id="tab-materiales" class="tab-panel active" style="padding:20px 24px;">

        <?php if ($es_admin): ?>
        <!-- Type picker -->
        <div style="background:#F8FAFF;border:1px dashed #BFDBFE;border-radius:10px;
                    padding:16px 20px;margin-bottom:20px;">
            <div style="font-size:12px;font-weight:700;color:var(--c-text-3);
                        text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px;">
                Agregar material
            </div>
            <div class="type-picker">
                <button class="type-btn" onclick="openAddModal('video')">
                    <span class="type-icon">🎬</span>Video
                </button>
                <button class="type-btn" onclick="openAddModal('pdf')">
                    <span class="type-icon">📄</span>PDF
                </button>
                <button class="type-btn" onclick="openAddModal('guia')">
                    <span class="type-icon">📝</span>Guía .txt
                </button>
                <button class="type-btn" onclick="openAddModal('resumen')">
                    <span class="type-icon">📋</span>Resumen
                </button>
                <button class="type-btn" onclick="openAddModal('link')">
                    <span class="type-icon">🔗</span>Enlace
                </button>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($materiales)): ?>
            <div style="text-align:center;padding:48px 20px;color:var(--c-text-3);">
                <div style="font-size:48px;margin-bottom:12px;opacity:.4;">📂</div>
                <p style="font-size:15px;">No hay materiales aún.</p>
                <?php if ($es_admin): ?>
                <p style="font-size:13px;">Usa los botones de arriba para agregar contenido.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php
            $iconMap = [
                'video'  => ['🎬','#EF4444','#FEF2F2'],
                'pdf'    => ['📄','#DC2626','#FEF2F2'],
                'link'   => ['🔗','#3B82F6','#EFF6FF'],
                'texto'  => ['📝','#059669','#ECFDF5'],
            ];
            ?>
            <div>
            <?php foreach ($materiales as $mat):
                [$ico,$clr,$bg] = $iconMap[$mat['tipo']] ?? ['📎','#6B7280','#F3F4F6'];
                $completado = (bool)$mat['mi_progreso'];
            ?>
            <div class="mat-item" id="mat-<?= $mat['id'] ?>">
                <div class="mat-icon" style="background:<?= $bg ?>;color:<?= $clr ?>;">
                    <?= $ico ?>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:14px;font-weight:600;margin-bottom:3px;
                                <?= $completado ? 'color:var(--c-success)' : '' ?>">
                        <?= htmlspecialchars($mat['titulo']) ?>
                        <?php if ($completado): ?>
                        <span style="font-size:11px;color:var(--c-success);margin-left:6px;">✓ Completado</span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;gap:12px;align-items:center;font-size:12px;color:var(--c-text-3);">
                        <span style="background:<?= $bg ?>;color:<?= $clr ?>;padding:1px 8px;
                                     border-radius:10px;font-weight:700;font-size:10px;">
                            <?= strtoupper($mat['tipo']) ?>
                        </span>
                        <?php if ($mat['descripcion']): ?>
                        <span><?= htmlspecialchars(mb_substr($mat['descripcion'],0,60)) ?><?= strlen($mat['descripcion'])>60?'…':'' ?></span>
                        <?php endif; ?>
                        <?php if ($mat['duracion_minutos']): ?>
                        <span>⏱ <?= $mat['duracion_minutos'] ?> min</span>
                        <?php endif; ?>
                        <?php if ($mat['obligatorio']): ?>
                        <span style="color:var(--c-danger);font-weight:600;">Obligatorio</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;gap:8px;align-items:center;flex-shrink:0;">
                    <?php if ($mi_inscripcion): ?>
                    <label style="display:flex;align-items:center;gap:5px;cursor:pointer;
                                  font-size:12px;color:var(--c-text-2);">
                        <input type="checkbox" style="width:15px;height:15px;"
                               <?= $completado ? 'checked' : '' ?>
                               onchange="marcarCompletado(<?= $mat['id'] ?>, this.checked)">
                        Completado
                    </label>
                    <?php endif; ?>

                    <?php if ($mat['tipo'] === 'video' && $mat['contenido']): ?>
                    <button class="btn btn-primary btn-sm"
                            onclick="verVideo(<?= htmlspecialchars(json_encode($mat['contenido']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($mat['titulo']), ENT_QUOTES) ?>)">
                        ▶ Ver
                    </button>
                    <?php elseif ($mat['tipo'] === 'pdf' && $mat['contenido']): ?>
                    <a href="../uploads/capacitacion/<?= htmlspecialchars(basename($mat['contenido'])) ?>"
                       target="_blank" class="btn btn-primary btn-sm">📄 Abrir PDF</a>
                    <?php elseif ($mat['tipo'] === 'texto' && $mat['contenido']): ?>
                    <button class="btn btn-primary btn-sm"
                            onclick="verTexto(<?= htmlspecialchars(json_encode($mat['titulo']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($mat['contenido']), ENT_QUOTES) ?>)">
                        👁 Leer
                    </button>
                    <?php elseif ($mat['tipo'] === 'link' && $mat['contenido']): ?>
                    <a href="<?= htmlspecialchars($mat['contenido']) ?>" target="_blank"
                       rel="noopener noreferrer" class="btn btn-secondary btn-sm">🔗 Abrir</a>
                    <?php endif; ?>

                    <?php if ($es_admin): ?>
                    <button class="btn btn-sm" style="background:var(--c-danger);color:#fff;padding:5px 8px;"
                            onclick="eliminarMaterial(<?= $mat['id'] ?>)" title="Eliminar">🗑</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ───────────────────────────────────────────────────────
         TAB: EVALUACIONES
         ─────────────────────────────────────────────────────── -->
    <div id="tab-evaluaciones" class="tab-panel" style="padding:20px 24px;">
        <?php if ($es_admin): ?>
        <div style="margin-bottom:18px;">
            <button class="btn btn-primary btn-sm" onclick="openModal('modalNuevoCuestionario')">
                + Crear Evaluación
            </button>
        </div>
        <?php endif; ?>

        <?php if (empty($cuestionarios)): ?>
            <div style="text-align:center;padding:48px;color:var(--c-text-3);">
                <div style="font-size:48px;opacity:.4;margin-bottom:12px;">📋</div>
                <p>No hay evaluaciones configuradas.</p>
            </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:14px;">
        <?php foreach ($cuestionarios as $quiz):
            $aprobado_quiz = $quiz['mejor_nota'] !== null && $quiz['mejor_nota'] >= $quiz['puntaje_minimo_aprobacion'];
        ?>
        <div style="border:1px solid var(--c-border);border-radius:8px;padding:18px 20px;
                    background:#fff;<?= $aprobado_quiz ? 'border-left:4px solid var(--c-success)' : '' ?>">
            <div style="display:flex;justify-content:space-between;align-items:start;gap:12px;flex-wrap:wrap;">
                <div style="flex:1;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                        <h4 style="font-size:15px;font-weight:700;margin:0;">
                            <?= htmlspecialchars($quiz['titulo']) ?>
                        </h4>
                        <?php if ($aprobado_quiz): ?>
                        <span style="font-size:11px;color:var(--c-success);font-weight:700;">✓ APROBADO</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($quiz['descripcion']): ?>
                    <p style="font-size:13px;color:var(--c-text-3);margin:0 0 10px;">
                        <?= htmlspecialchars($quiz['descripcion']) ?>
                    </p>
                    <?php endif; ?>
                    <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:12px;color:var(--c-text-3);">
                        <span>📝 <?= $quiz['total_preguntas'] ?> preguntas</span>
                        <span>📊 Mínimo: <?= $quiz['puntaje_minimo_aprobacion'] ?>%</span>
                        <span>🔁 <?= $quiz['intentos_permitidos'] ?> intentos</span>
                        <?php if ($quiz['tiempo_limite_minutos']): ?>
                        <span>⏱ <?= $quiz['tiempo_limite_minutos'] ?> min</span>
                        <?php endif; ?>
                        <?php if ($quiz['mis_intentos'] > 0): ?>
                        <span style="color:var(--c-navy);font-weight:600;">
                            Mejor nota: <?= round($quiz['mejor_nota'] ?? 0, 1) ?>%
                            (<?= $quiz['mis_intentos'] ?>/<?= $quiz['intentos_permitidos'] ?> intentos)
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;gap:8px;">
                    <?php if ($mi_inscripcion && $quiz['mis_intentos'] < $quiz['intentos_permitidos']): ?>
                    <button class="btn btn-primary btn-sm"
                            onclick="window.location='cuestionario_tomar.php?id=<?= $quiz['id'] ?>'">
                        ✍️ Realizar
                    </button>
                    <?php endif; ?>
                    <?php if ($es_admin): ?>
                    <button class="btn btn-secondary btn-sm"
                            onclick="window.location='cuestionario_editor.php?id=<?= $quiz['id'] ?>'">
                        ✏️ Editar
                    </button>
                    <button class="btn btn-sm" style="background:var(--c-danger);color:#fff;"
                            onclick="eliminarCuestionario(<?= $quiz['id'] ?>)">🗑</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ───────────────────────────────────────────────────────
         TAB: PARTICIPANTES
         ─────────────────────────────────────────────────────── -->
    <div id="tab-participantes" class="tab-panel" style="padding:20px 24px;">
        <?php if (empty($inscritos)): ?>
            <div style="text-align:center;padding:48px;color:var(--c-text-3);">
                <div style="font-size:48px;opacity:.4;margin-bottom:12px;">👥</div>
                <p>No hay participantes inscritos.</p>
            </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Participante</th>
                        <th>Email</th>
                        <th>Estado</th>
                        <th>Progreso</th>
                        <th>Calificación</th>
                        <th>Inscripción</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($inscritos as $ins): ?>
                <tr>
                    <td style="font-weight:600;"><?= htmlspecialchars($ins['usuario_nombre']) ?></td>
                    <td style="font-size:13px;color:var(--c-text-3);"><?= htmlspecialchars($ins['email'] ?? '—') ?></td>
                    <td>
                        <?php $sc2 = match($ins['estado']) {
                            'completado' => 'badge-active', 'abandonado' => 'badge-danger',
                            default => 'badge-navy'
                        }; ?>
                        <span class="badge <?= $sc2 ?>"><?= htmlspecialchars($ins['estado']) ?></span>
                    </td>
                    <td>
                        <div class="prog-bar" style="width:80px;">
                            <div class="prog-fill" style="width:<?= $ins['progreso_porcentaje'] ?? 0 ?>%"></div>
                        </div>
                        <div style="font-size:11px;color:var(--c-text-3);margin-top:2px;">
                            <?= $ins['progreso_porcentaje'] ?? 0 ?>%
                        </div>
                    </td>
                    <td>
                        <?php if ($ins['calificacion_final']): ?>
                        <strong style="color:var(--c-success);"><?= round($ins['calificacion_final'], 1) ?>%</strong>
                        <?php else: ?>
                        <span style="color:var(--c-text-4);">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:var(--c-text-3);">
                        <?= date('d/m/Y', strtotime($ins['fecha_inscripcion'])) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ───────────────────────────────────────────────────────
         TAB: CONFIGURACIÓN (solo admin)
         ─────────────────────────────────────────────────────── -->
    <?php if ($es_admin): ?>
    <div id="tab-config" class="tab-panel" style="padding:20px 24px;">
        <form id="formConfigCurso">
            <input type="hidden" name="id" value="<?= $curso_id ?>">

            <div class="config-section">
                <h4>Información General</h4>
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Título del Curso <span class="required">*</span></label>
                        <input class="form-control" type="text" name="titulo"
                               value="<?= htmlspecialchars($curso['titulo']) ?>" required>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3"><?= htmlspecialchars($curso['descripcion'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Instructor</label>
                        <select class="form-control" name="instructor_id">
                            <option value="">Sin asignar</option>
                            <?php foreach ($instructores as $inst): ?>
                            <option value="<?= $inst['id'] ?>"
                                <?= $inst['id'] == $curso['instructor_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($inst['nombre_completo']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Modalidad</label>
                        <select class="form-control" name="modalidad">
                            <?php foreach (['Presencial','Virtual','Híbrido'] as $m): ?>
                            <option <?= $curso['modalidad'] === $m ? 'selected' : '' ?>><?= $m ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Duración (horas)</label>
                        <input class="form-control" type="number" name="duracion_horas"
                               step="0.5" min="0" value="<?= $curso['duracion_horas'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Máx. Participantes</label>
                        <input class="form-control" type="number" name="max_participantes"
                               min="1" value="<?= $curso['max_participantes'] ?? '' ?>"
                               placeholder="Ilimitado">
                    </div>
                </div>
            </div>

            <div class="config-section">
                <h4>Fechas y Estado</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Fecha de Inicio</label>
                        <input class="form-control" type="datetime-local" name="fecha_inicio"
                               value="<?= $curso['fecha_inicio'] ? date('Y-m-d\TH:i', strtotime($curso['fecha_inicio'])) : '' ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fecha de Fin</label>
                        <input class="form-control" type="datetime-local" name="fecha_fin"
                               value="<?= $curso['fecha_fin'] ? date('Y-m-d\TH:i', strtotime($curso['fecha_fin'])) : '' ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nivel</label>
                        <select class="form-control" name="nivel">
                            <?php foreach (['básico','intermedio','avanzado'] as $nv): ?>
                            <option value="<?= $nv ?>" <?= ($curso['nivel'] ?? '') === $nv ? 'selected' : '' ?>>
                                <?= ucfirst($nv) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estado del Curso</label>
                        <select class="form-control" name="estado">
                            <?php foreach (['borrador','publicado','archivado'] as $est): ?>
                            <option value="<?= $est ?>" <?= $curso['estado'] === $est ? 'selected' : '' ?>>
                                <?= ucfirst($est) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="button" class="btn btn-primary" onclick="guardarConfiguracion()">
                    💾 Guardar Cambios
                </button>
                <button type="button" class="btn btn-sm"
                        style="background:var(--c-danger);color:#fff;"
                        onclick="eliminarCurso()">
                    🗑 Eliminar Curso
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

</div><!-- .card -->
</div><!-- .page-body -->
</div><!-- .main-content -->

<!-- ============================================================ MODALES ============================================================ -->

<!-- Modal Agregar Video -->
<div class="modal-overlay" id="modalVideo">
    <div class="modal-box" style="max-width:580px;">
        <div class="modal-header">
            <span class="modal-title">🎬 Agregar Video</span>
            <button class="modal-close" onclick="closeModal('modalVideo')">×</button>
        </div>
        <div class="modal-body">
            <form id="formVideo">
                <input type="hidden" name="tipo" value="video">
                <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                <div class="form-group">
                    <label class="form-label">Título <span class="required">*</span></label>
                    <input class="form-control" type="text" name="titulo" required placeholder="Ej: Introducción al módulo">
                </div>
                <div class="form-group">
                    <label class="form-label">URL del Video <span class="required">*</span></label>
                    <input class="form-control" type="url" name="contenido" required
                           placeholder="https://www.youtube.com/watch?v=...">
                    <span class="form-hint">YouTube, Vimeo, Google Drive, etc.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción (opcional)</label>
                    <textarea class="form-control" name="descripcion" rows="2"
                              placeholder="¿Qué aprenderá el alumno en este video?"></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Duración (minutos)</label>
                        <input class="form-control" type="number" name="duracion_minutos" min="0">
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="obligatorio" value="1" checked>
                            <span>Material obligatorio</span>
                        </label>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalVideo')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarMaterial('formVideo')">Guardar Video</button>
        </div>
    </div>
</div>

<!-- Modal Agregar PDF -->
<div class="modal-overlay" id="modalPDF">
    <div class="modal-box" style="max-width:580px;">
        <div class="modal-header">
            <span class="modal-title">📄 Agregar PDF</span>
            <button class="modal-close" onclick="closeModal('modalPDF')">×</button>
        </div>
        <div class="modal-body">
            <form id="formPDF" enctype="multipart/form-data">
                <input type="hidden" name="tipo" value="pdf">
                <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                <div class="form-group">
                    <label class="form-label">Título <span class="required">*</span></label>
                    <input class="form-control" type="text" name="titulo" required placeholder="Ej: Manual de Procedimientos">
                </div>
                <div class="form-group">
                    <label class="form-label">Archivo PDF <span class="required">*</span></label>
                    <input class="form-control" type="file" name="archivo" accept=".pdf" required>
                    <span class="form-hint">Máximo 50 MB · Solo archivos .pdf</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción (opcional)</label>
                    <textarea class="form-control" name="descripcion" rows="2"
                              placeholder="Breve descripción del documento..."></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Duración estimada (minutos)</label>
                        <input class="form-control" type="number" name="duracion_minutos" min="0">
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="obligatorio" value="1" checked>
                            <span>Material obligatorio</span>
                        </label>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalPDF')">Cancelar</button>
            <button class="btn btn-primary" id="btnGuardarPDF" onclick="guardarMaterial('formPDF')">Subir PDF</button>
        </div>
    </div>
</div>

<!-- Modal Agregar Guía .txt -->
<div class="modal-overlay" id="modalGuia">
    <div class="modal-box" style="max-width:640px;">
        <div class="modal-header">
            <span class="modal-title">📝 Agregar Guía</span>
            <button class="modal-close" onclick="closeModal('modalGuia')">×</button>
        </div>
        <div class="modal-body">
            <form id="formGuia" enctype="multipart/form-data">
                <input type="hidden" name="tipo" value="texto">
                <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                <div class="form-group">
                    <label class="form-label">Título <span class="required">*</span></label>
                    <input class="form-control" type="text" name="titulo" required placeholder="Ej: Guía de Instalación">
                </div>
                <!-- Tabs: subir archivo / escribir -->
                <div style="display:flex;gap:0;margin-bottom:14px;border:1px solid var(--c-border);border-radius:6px;overflow:hidden;">
                    <button type="button" id="btnTabArchivo"
                            onclick="switchGuiaTab('archivo')"
                            style="flex:1;padding:9px;border:none;background:var(--c-navy);
                                   color:#fff;font-size:13px;font-weight:600;cursor:pointer;">
                        📁 Subir .txt
                    </button>
                    <button type="button" id="btnTabEscribir"
                            onclick="switchGuiaTab('escribir')"
                            style="flex:1;padding:9px;border:none;background:#F3F4F6;
                                   color:var(--c-text-2);font-size:13px;font-weight:600;cursor:pointer;">
                        ✏️ Escribir
                    </button>
                </div>
                <div id="panelArchivo">
                    <div class="form-group">
                        <label class="form-label">Archivo .txt</label>
                        <input class="form-control" type="file" name="archivo" id="archivoTxt" accept=".txt">
                        <span class="form-hint">Se importará el contenido del archivo de texto</span>
                    </div>
                </div>
                <div id="panelEscribir" style="display:none;">
                    <div class="form-group">
                        <label class="form-label">Contenido de la Guía</label>
                        <textarea class="form-control" name="contenido_texto" rows="10"
                                  style="font-family:monospace;font-size:13px;"
                                  placeholder="Escribe aquí la guía de capacitación...&#10;&#10;Puedes usar formato de texto plano."></textarea>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción (opcional)</label>
                    <textarea class="form-control" name="descripcion" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="obligatorio" value="1" checked>
                        <span>Material obligatorio</span>
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalGuia')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarGuia()">Guardar Guía</button>
        </div>
    </div>
</div>

<!-- Modal Agregar Resumen -->
<div class="modal-overlay" id="modalResumen">
    <div class="modal-box" style="max-width:680px;">
        <div class="modal-header">
            <span class="modal-title">📋 Agregar Resumen</span>
            <button class="modal-close" onclick="closeModal('modalResumen')">×</button>
        </div>
        <div class="modal-body">
            <form id="formResumen">
                <input type="hidden" name="tipo" value="texto">
                <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                <div class="form-group">
                    <label class="form-label">Título <span class="required">*</span></label>
                    <input class="form-control" type="text" name="titulo" required placeholder="Ej: Resumen del Módulo 1">
                </div>
                <div class="form-group">
                    <label class="form-label">Contenido del Resumen <span class="required">*</span></label>
                    <textarea class="form-control" name="contenido" rows="12"
                              style="font-size:14px;line-height:1.7;"
                              placeholder="Escribe el resumen aquí...&#10;&#10;Puedes incluir:&#10;• Conceptos clave&#10;• Puntos importantes&#10;• Definiciones&#10;• Conclusiones" required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción breve (opcional)</label>
                    <input class="form-control" type="text" name="descripcion" placeholder="Breve descripción de este resumen...">
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="obligatorio" value="1">
                        <span>Material obligatorio</span>
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalResumen')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarMaterial('formResumen')">Guardar Resumen</button>
        </div>
    </div>
</div>

<!-- Modal Agregar Enlace -->
<div class="modal-overlay" id="modalEnlace">
    <div class="modal-box" style="max-width:540px;">
        <div class="modal-header">
            <span class="modal-title">🔗 Agregar Enlace</span>
            <button class="modal-close" onclick="closeModal('modalEnlace')">×</button>
        </div>
        <div class="modal-body">
            <form id="formEnlace">
                <input type="hidden" name="tipo" value="link">
                <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                <div class="form-group">
                    <label class="form-label">Título <span class="required">*</span></label>
                    <input class="form-control" type="text" name="titulo" required placeholder="Ej: Documentación oficial">
                </div>
                <div class="form-group">
                    <label class="form-label">URL <span class="required">*</span></label>
                    <input class="form-control" type="url" name="contenido" required placeholder="https://...">
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción (opcional)</label>
                    <textarea class="form-control" name="descripcion" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="obligatorio" value="1">
                        <span>Material obligatorio</span>
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalEnlace')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarMaterial('formEnlace')">Guardar Enlace</button>
        </div>
    </div>
</div>

<!-- Modal Nueva Evaluación -->
<div class="modal-overlay" id="modalNuevoCuestionario">
    <div class="modal-box" style="max-width:600px;">
        <div class="modal-header">
            <span class="modal-title">✍️ Nueva Evaluación</span>
            <button class="modal-close" onclick="closeModal('modalNuevoCuestionario')">×</button>
        </div>
        <div class="modal-body">
            <form id="formNuevoCuestionario">
                <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Título <span class="required">*</span></label>
                        <input class="form-control" type="text" name="titulo" required placeholder="Ej: Evaluación Final">
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Descripción (opcional)</label>
                        <textarea class="form-control" name="descripcion" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nota mínima (%)</label>
                        <input class="form-control" type="number" name="nota_minima" value="70" min="0" max="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Intentos permitidos</label>
                        <input class="form-control" type="number" name="intentos_max" value="3" min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tiempo límite (minutos)</label>
                        <input class="form-control" type="number" name="tiempo_limite" placeholder="Sin límite">
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="mostrar_respuestas" value="1">
                            <span style="font-size:13px;">Mostrar respuestas al finalizar</span>
                        </label>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalNuevoCuestionario')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarCuestionario()">Crear y Agregar Preguntas →</button>
        </div>
    </div>
</div>

<!-- Modal Viewer: Texto / Guía -->
<div class="modal-overlay" id="viewerModal">
    <div class="modal-box" style="max-width:760px;">
        <div class="modal-header">
            <span class="modal-title" id="viewerTitulo">Contenido</span>
            <button class="modal-close" onclick="closeModal('viewerModal')">×</button>
        </div>
        <div class="modal-body">
            <div class="texto-viewer" id="viewerContenido"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('viewerModal')">Cerrar</button>
            <button class="btn btn-primary" onclick="copiarTexto()" id="btnCopiar">📋 Copiar</button>
        </div>
    </div>
</div>

<!-- Modal Viewer: Video -->
<div class="modal-overlay" id="videoModal">
    <div class="modal-box" style="max-width:900px;">
        <div class="modal-header">
            <span class="modal-title" id="videoTitulo">Video</span>
            <button class="modal-close" onclick="closeModal('videoModal');document.getElementById('videoFrame').src='';">×</button>
        </div>
        <div class="modal-body" style="padding:0;">
            <iframe id="videoFrame" width="100%" height="500" frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen></iframe>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
const cursoId = <?= $curso_id ?>;

// ── Tabs ──────────────────────────────────────────────────────
function switchTab(name) {
    document.querySelectorAll('.tab-nav-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');

    // Activar el botón correspondiente
    document.querySelectorAll('.tab-nav-btn').forEach(b => {
        if (b.getAttribute('onclick') === `switchTab('${name}')`) b.classList.add('active');
    });
}

// ── Abrir modal según tipo ─────────────────────────────────────
function openAddModal(tipo) {
    const map = { video:'modalVideo', pdf:'modalPDF', guia:'modalGuia',
                  resumen:'modalResumen', link:'modalEnlace' };
    openModal(map[tipo]);
}

// ── Guardar material genérico ──────────────────────────────────
async function guardarMaterial(formId) {
    const form = document.getElementById(formId);
    if (!form.titulo.value.trim()) {
        showToast('Campo requerido', 'El título es obligatorio.', 'error');
        return;
    }
    const fd = new FormData(form);
    fd.append('action', 'create_material');

    const btn = form.closest('.modal-box').querySelector('.btn-primary');
    const orig = btn.textContent;
    btn.disabled = true; btn.textContent = 'Guardando…';

    try {
        const res  = await fetch('../api/capacitacion_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            showToast('Material agregado', data.message, 'success');
            form.closest('.modal-overlay').querySelectorAll('.modal-close')[0].click();
            setTimeout(() => location.reload(), 900);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo guardar.', 'error');
    } finally {
        btn.disabled = false; btn.textContent = orig;
    }
}

// ── Guardar Guía (.txt subido o escrito) ─────────────────────
async function guardarGuia() {
    const form = document.getElementById('formGuia');
    if (!form.titulo.value.trim()) {
        showToast('Campo requerido', 'El título es obligatorio.', 'error');
        return;
    }

    const modo = document.getElementById('panelArchivo').style.display !== 'none' ? 'archivo' : 'escribir';

    if (modo === 'archivo') {
        const fileInput = document.getElementById('archivoTxt');
        if (!fileInput.files.length) {
            showToast('Archivo requerido', 'Selecciona un archivo .txt.', 'error');
            return;
        }
        // Leer el archivo en el cliente
        const texto = await fileInput.files[0].text();
        // Poner en contenido_texto y enviar como texto
        form.querySelector('[name="contenido_texto"]').value = texto;
    }

    const contenidoEl = form.querySelector('[name="contenido_texto"]');
    if (!contenidoEl.value.trim()) {
        showToast('Contenido vacío', 'La guía no tiene contenido.', 'error');
        return;
    }

    // Construir FormData manualmente
    const fd = new FormData();
    fd.append('action', 'create_material');
    fd.append('curso_id', '<?= $curso_id ?>');
    fd.append('tipo', 'texto');
    fd.append('titulo', form.titulo.value.trim());
    fd.append('contenido', contenidoEl.value.trim());
    fd.append('descripcion', form.descripcion.value.trim());
    const oblig = form.querySelector('[name="obligatorio"]');
    if (oblig && oblig.checked) fd.append('obligatorio', '1');

    const btn = document.querySelector('#modalGuia .btn-primary');
    const orig = btn.textContent;
    btn.disabled = true; btn.textContent = 'Guardando…';

    try {
        const res  = await fetch('../api/capacitacion_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            showToast('Guía agregada', data.message, 'success');
            closeModal('modalGuia');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo guardar.', 'error');
    } finally {
        btn.disabled = false; btn.textContent = orig;
    }
}

// ── Tabs dentro del modal de guía ─────────────────────────────
function switchGuiaTab(tab) {
    const isArchivo = tab === 'archivo';
    document.getElementById('panelArchivo').style.display = isArchivo ? '' : 'none';
    document.getElementById('panelEscribir').style.display = isArchivo ? 'none' : '';
    document.getElementById('btnTabArchivo').style.background = isArchivo ? 'var(--c-navy)' : '#F3F4F6';
    document.getElementById('btnTabArchivo').style.color     = isArchivo ? '#fff' : 'var(--c-text-2)';
    document.getElementById('btnTabEscribir').style.background = isArchivo ? '#F3F4F6' : 'var(--c-navy)';
    document.getElementById('btnTabEscribir').style.color     = isArchivo ? 'var(--c-text-2)' : '#fff';
}

// ── Eliminar material ─────────────────────────────────────────
async function eliminarMaterial(id) {
    if (!confirm('¿Eliminar este material?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_material');
    fd.append('id', id);
    const res  = await fetch('../api/capacitacion_api.php', { method:'POST', body:fd });
    const data = await res.json();
    if (data.success) {
        document.getElementById('mat-' + id)?.remove();
        showToast('Eliminado', data.message, 'success');
    } else {
        showToast('Error', data.message, 'error');
    }
}

// ── Marcar completado ─────────────────────────────────────────
async function marcarCompletado(materialId, completado) {
    const fd = new FormData();
    fd.append('action', 'marcar_completado');
    fd.append('material_id', materialId);
    fd.append('completado', completado ? '1' : '0');
    await fetch('../api/capacitacion_api.php', { method:'POST', body:fd });
}

// ── Viewer: Texto ─────────────────────────────────────────────
function verTexto(titulo, contenido) {
    document.getElementById('viewerTitulo').textContent = titulo;
    document.getElementById('viewerContenido').textContent = contenido;
    openModal('viewerModal');
}

function copiarTexto() {
    navigator.clipboard.writeText(document.getElementById('viewerContenido').textContent)
        .then(() => showToast('Copiado', 'Texto copiado al portapapeles.', 'success'));
}

// ── Viewer: Video ─────────────────────────────────────────────
function verVideo(url, titulo) {
    document.getElementById('videoTitulo').textContent = titulo;
    let embed = url;
    if (url.includes('youtube.com/watch')) {
        embed = 'https://www.youtube.com/embed/' + new URLSearchParams(new URL(url).search).get('v');
    } else if (url.includes('youtu.be/')) {
        embed = 'https://www.youtube.com/embed/' + url.split('youtu.be/')[1].split('?')[0];
    } else if (url.includes('vimeo.com/')) {
        embed = 'https://player.vimeo.com/video/' + url.split('vimeo.com/')[1].split('?')[0];
    }
    document.getElementById('videoFrame').src = embed;
    openModal('videoModal');
}

// ── Cuestionarios ─────────────────────────────────────────────
async function guardarCuestionario() {
    const form = document.getElementById('formNuevoCuestionario');
    if (!form.titulo.value.trim()) {
        showToast('Campo requerido', 'El título es obligatorio.', 'error');
        return;
    }
    const fd = new FormData(form);
    fd.append('action', 'create_cuestionario');
    const res  = await fetch('../api/capacitacion_api.php', { method:'POST', body:fd });
    const data = await res.json();
    if (data.success) {
        showToast('Evaluación creada', 'Redirigiendo al editor…', 'success');
        setTimeout(() => window.location = 'cuestionario_editor.php?id=' + data.id, 900);
    } else {
        showToast('Error', data.message, 'error');
    }
}

async function eliminarCuestionario(id) {
    if (!confirm('¿Eliminar esta evaluación y todas sus preguntas?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_cuestionario');
    fd.append('id', id);
    const res  = await fetch('../api/capacitacion_api.php', { method:'POST', body:fd });
    const data = await res.json();
    if (data.success) { showToast('Eliminado', data.message, 'success'); setTimeout(() => location.reload(), 900); }
    else showToast('Error', data.message, 'error');
}

// ── Inscripción ───────────────────────────────────────────────
async function inscribirse() {
    const fd = new FormData();
    fd.append('action', 'inscribirse');
    fd.append('curso_id', cursoId);
    const res  = await fetch('../api/capacitacion_api.php', { method:'POST', body:fd });
    const data = await res.json();
    if (data.success) { showToast('Inscrito', data.message, 'success'); setTimeout(() => location.reload(), 1000); }
    else showToast('Error', data.message, 'error');
}

// ── Configuración del curso ───────────────────────────────────
async function guardarConfiguracion() {
    const form  = document.getElementById('formConfigCurso');
    const fd    = new FormData(form);
    fd.append('action', 'update');
    const btn   = document.querySelector('#tab-config .btn-primary');
    const orig  = btn.textContent;
    btn.disabled = true; btn.textContent = 'Guardando…';
    try {
        const res  = await fetch('../api/capacitacion_api.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) { showToast('Guardado', 'Configuración actualizada.', 'success'); setTimeout(() => location.reload(), 1000); }
        else showToast('Error', data.message, 'error');
    } finally { btn.disabled = false; btn.textContent = orig; }
}

async function eliminarCurso() {
    if (!confirm('¿Eliminar este curso permanentemente? Se perderán todos los materiales y evaluaciones.')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', cursoId);
    const res  = await fetch('../api/capacitacion_api.php', { method:'POST', body:fd });
    const data = await res.json();
    if (data.success) { window.location = 'capacitacion.php'; }
    else showToast('Error', data.message, 'error');
}
</script>
