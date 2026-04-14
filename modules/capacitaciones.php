<?php
// ============================================================
//  modules/capacitacion.php — Programa de Capacitación
//  Ruta: C:\xampp\htdocs\Corfiem_Cesar\modules\capacitacion.php
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session     = require_auth();
$uid         = (int)$session['usuario_id'];
$page_title  = 'Capacitación';
$page_active = 'capacitacion';

// ── Datos para selects ────────────────────────────────────────
$usuarios = db_fetch_all(
    "SELECT id, nombre || ' ' || apellido AS nombre_completo
     FROM usuarios WHERE activo = TRUE ORDER BY nombre"
);

// ── Filtros ───────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if (!empty($_GET['estado'])) {
    $where[]  = 'c.estado = ?';
    $params[] = $_GET['estado'];
}
if (!empty($_GET['modalidad'])) {
    $where[]  = 'c.modalidad = ?';
    $params[] = $_GET['modalidad'];
}
if (!empty($_GET['q'])) {
    $where[]  = 'c.titulo ILIKE ?';
    $params[] = '%' . trim($_GET['q']) . '%';
}

// ── Listado de cursos ─────────────────────────────────────────
$cursos = db_fetch_all(
    "SELECT c.id, c.titulo, c.descripcion, c.modalidad, c.estado,
            c.duracion_horas, c.max_participantes,
            c.fecha_inicio, c.fecha_fin,
            u.nombre || ' ' || u.apellido AS instructor,
            COUNT(i.id) FILTER (WHERE i.estado != 'retirado') AS inscritos
     FROM cursos c
     LEFT JOIN usuarios u ON c.instructor_id = u.id
     LEFT JOIN inscripciones_curso i ON i.curso_id = c.id
     WHERE " . implode(' AND ', $where) .
    " GROUP BY c.id, u.nombre, u.apellido
     ORDER BY c.fecha_inicio DESC NULLS LAST",
    $params
);

// ── KPIs ──────────────────────────────────────────────────────
$kpi = db_fetch_one(
    "SELECT
        COUNT(*)                                             AS total_cursos,
        COUNT(*) FILTER (WHERE estado = 'programado')       AS programados,
        COUNT(*) FILTER (WHERE estado = 'en_curso')         AS en_curso,
        COUNT(*) FILTER (WHERE estado = 'completado')       AS completados,
        (SELECT COUNT(*) FROM inscripciones_curso
         WHERE estado NOT IN ('retirado'))                   AS total_inscritos,
        (SELECT COUNT(*) FROM inscripciones_curso
         WHERE estado = 'aprobado')                         AS aprobados
     FROM cursos"
);

// ── Mis inscripciones ─────────────────────────────────────────
$mis_cursos = db_fetch_all(
    "SELECT c.id, c.titulo, c.modalidad, c.estado AS estado_curso,
            c.fecha_inicio, c.fecha_fin, c.duracion_horas,
            i.id AS inscripcion_id, i.estado AS estado_inscripcion,
            i.calificacion,
            u.nombre || ' ' || u.apellido AS instructor
     FROM inscripciones_curso i
     JOIN cursos   c ON i.curso_id     = c.id
     LEFT JOIN usuarios u ON c.instructor_id = u.id
     WHERE i.usuario_id = ? AND i.estado != 'retirado'
     ORDER BY c.fecha_inicio DESC NULLS LAST",
    [$uid]
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
<?php render_topbar('Capacitación', 'Gestión de cursos y formación'); ?>

<div class="page-body">

    <!-- ── KPIs ─────────────────────────────────────────────── -->
    <div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
                                  margin-bottom:24px;">
        <div class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $kpi['total_cursos'] ?></div>
            <div class="kpi-label">Total Cursos</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:var(--c-warning-lt);">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--c-warning)" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8"  y1="2" x2="8"  y2="6"/>
                    <line x1="3"  y1="10" x2="21" y2="10"/>
                </svg>
            </div>
            <div class="kpi-value" style="color:var(--c-warning);"><?= $kpi['programados'] ?></div>
            <div class="kpi-label">Programados</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:var(--c-accent-lt);">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--c-accent)" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12,6 12,12 16,14"/>
                </svg>
            </div>
            <div class="kpi-value" style="color:var(--c-accent);"><?= $kpi['en_curso'] ?></div>
            <div class="kpi-label">En Curso</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:var(--c-success-lt);">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--c-success)" stroke-width="2">
                    <polyline points="20,6 9,17 4,12"/>
                </svg>
            </div>
            <div class="kpi-value" style="color:var(--c-success);"><?= $kpi['completados'] ?></div>
            <div class="kpi-label">Completados</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $kpi['total_inscritos'] ?></div>
            <div class="kpi-label">Total Inscritos</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#FEF3C7;">
                <svg viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2">
                    <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26 12,2"/>
                </svg>
            </div>
            <div class="kpi-value" style="color:#D97706;"><?= $kpi['aprobados'] ?></div>
            <div class="kpi-label">Aprobados</div>
        </div>
    </div>

    <!-- ── Cabecera ──────────────────────────────────────────── -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Programa de Capacitación</h1>
            <p class="page-subtitle">Cursos, inscripciones y seguimiento de formación</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('modalNuevoCurso')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5"  y1="12" x2="19" y2="12"/>
            </svg>
            Nuevo Curso
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
                       placeholder="Buscar curso..."
                       value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
            </div>
            <select name="estado" class="form-control" style="width:150px;">
                <option value="">Todos los estados</option>
                <option value="programado"  <?= ($_GET['estado']??'') === 'programado'  ? 'selected':'' ?>>Programado</option>
                <option value="en_curso"    <?= ($_GET['estado']??'') === 'en_curso'    ? 'selected':'' ?>>En Curso</option>
                <option value="completado"  <?= ($_GET['estado']??'') === 'completado'  ? 'selected':'' ?>>Completado</option>
                <option value="cancelado"   <?= ($_GET['estado']??'') === 'cancelado'   ? 'selected':'' ?>>Cancelado</option>
            </select>
            <select name="modalidad" class="form-control" style="width:140px;">
                <option value="">Modalidad</option>
                <option value="Presencial" <?= ($_GET['modalidad']??'') === 'Presencial' ? 'selected':'' ?>>Presencial</option>
                <option value="Virtual"    <?= ($_GET['modalidad']??'') === 'Virtual'    ? 'selected':'' ?>>Virtual</option>
                <option value="Híbrido"    <?= ($_GET['modalidad']??'') === 'Híbrido'    ? 'selected':'' ?>>Híbrido</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            <?php if (!empty($_GET['q'])||!empty($_GET['estado'])||!empty($_GET['modalidad'])): ?>
                <a href="capacitacion.php" class="btn btn-secondary btn-sm">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ── Grid principal ────────────────────────────────────── -->
    <div class="grid-2" style="gap:20px;align-items:start;">

        <!-- Catálogo de cursos -->
        <div style="grid-column:1/-1;">
        <div class="card">
            <div class="card-header">
                <span class="card-title">Catálogo de Cursos</span>
                <span style="font-size:12px;color:var(--c-text-3);"><?= count($cursos) ?> cursos</span>
            </div>

            <?php if (empty($cursos)): ?>
                <p style="text-align:center;padding:40px;color:var(--c-text-3);">
                    No hay cursos registrados. Crea el primero con el botón <strong>Nuevo Curso</strong>.
                </p>
            <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;
                        padding:4px 0;">
                <?php foreach ($cursos as $c):
                    $badgeEstado = match($c['estado']) {
                        'programado' => 'badge-pending',
                        'en_curso'   => 'badge-active',
                        'completado' => 'badge-completed',
                        default      => 'badge-cancelled',
                    };
                    $labelEstado = match($c['estado']) {
                        'programado' => 'Programado',
                        'en_curso'   => 'En Curso',
                        'completado' => 'Completado',
                        default      => 'Cancelado',
                    };
                    $cupo_max  = (int)$c['max_participantes'];
                    $inscritos = (int)$c['inscritos'];
                    $cupo_pct  = $cupo_max > 0 ? round(($inscritos / $cupo_max) * 100) : 0;
                    $sin_cupo  = $cupo_max > 0 && $inscritos >= $cupo_max;
                ?>
                <div style="border:1px solid var(--c-border);border-radius:var(--radius-lg);
                            padding:18px;background:var(--c-surface);
                            transition:box-shadow 0.2s;"
                     onmouseover="this.style.boxShadow='var(--shadow)'"
                     onmouseout="this.style.boxShadow='none'">

                    <!-- Header tarjeta -->
                    <div style="display:flex;justify-content:space-between;
                                align-items:flex-start;margin-bottom:10px;">
                        <span class="badge badge-dot <?= $badgeEstado ?>"><?= $labelEstado ?></span>
                        <span class="badge badge-navy" style="font-size:11px;">
                            <?= htmlspecialchars($c['modalidad']) ?>
                        </span>
                    </div>

                    <!-- Título -->
                    <h4 style="font-size:14px;font-weight:700;color:var(--c-text-1);
                               margin-bottom:6px;line-height:1.3;">
                        <?= htmlspecialchars($c['titulo']) ?>
                    </h4>

                    <!-- Descripción -->
                    <?php if ($c['descripcion']): ?>
                        <p style="font-size:12.5px;color:var(--c-text-3);margin-bottom:12px;
                                  display:-webkit-box;-webkit-line-clamp:2;
                                  -webkit-box-orient:vertical;overflow:hidden;">
                            <?= htmlspecialchars($c['descripcion']) ?>
                        </p>
                    <?php endif; ?>

                    <!-- Metadatos -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;
                                gap:6px;margin-bottom:12px;font-size:12px;">
                        <div style="color:var(--c-text-3);">
                            ⏱ <?= $c['duracion_horas'] ? $c['duracion_horas'] . ' hrs' : '—' ?>
                        </div>
                        <div style="color:var(--c-text-3);">
                            👤 <?= htmlspecialchars($c['instructor'] ?? 'Sin asignar') ?>
                        </div>
                        <?php if ($c['fecha_inicio']): ?>
                        <div style="color:var(--c-text-3);">
                            📅 <?= date('d/m/Y', strtotime($c['fecha_inicio'])) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($c['fecha_fin']): ?>
                        <div style="color:var(--c-text-3);">
                            🏁 <?= date('d/m/Y', strtotime($c['fecha_fin'])) ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Barra de cupo -->
                    <?php if ($cupo_max > 0): ?>
                    <div style="margin-bottom:12px;">
                        <div style="display:flex;justify-content:space-between;
                                    font-size:11.5px;margin-bottom:4px;">
                            <span style="color:var(--c-text-3);">Participantes</span>
                            <span style="font-weight:600;
                                         color:<?= $sin_cupo ? 'var(--c-danger)' : 'var(--c-text-2)' ?>;">
                                <?= $inscritos ?>/<?= $cupo_max ?>
                                <?= $sin_cupo ? ' · Completo' : '' ?>
                            </span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar"
                                 style="width:<?= $cupo_pct ?>%;
                                        background:<?= $sin_cupo ? 'var(--c-danger)' : 'var(--c-navy)' ?>;">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Acciones -->
                    <div style="display:flex;gap:8px;margin-top:4px;">
                        <button class="btn btn-secondary btn-sm" style="flex:1;"
                                onclick="verCurso(<?= $c['id'] ?>)">
                            Ver detalles
                        </button>
                        <?php if (!$sin_cupo && $c['estado'] !== 'cancelado'
                                  && $c['estado'] !== 'completado'): ?>
                        <button class="btn btn-primary btn-sm" style="flex:1;"
                                onclick="inscribirme(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['titulo'])) ?>')">
                            Inscribirse
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-secondary btn-sm"
                                onclick="editarCurso(<?= $c['id'] ?>)" title="Editar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" style="width:13px;height:13px;">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        </div>

        <!-- Mis cursos -->
        <?php if (!empty($mis_cursos)): ?>
        <div class="card" style="grid-column:1/-1;">
            <div class="card-header">
                <span class="card-title">📚 Mis Inscripciones</span>
                <span style="font-size:12px;color:var(--c-text-3);"><?= count($mis_cursos) ?> cursos</span>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Curso</th>
                            <th>Instructor</th>
                            <th>Modalidad</th>
                            <th>Fechas</th>
                            <th>Duración</th>
                            <th>Mi Estado</th>
                            <th>Calificación</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($mis_cursos as $mc):
                        $badgeIns = match($mc['estado_inscripcion']) {
                            'aprobado'   => 'badge-active',
                            'en_curso'   => 'badge-pending',
                            'reprobado'  => 'badge-cancelled',
                            default      => 'badge-completed',
                        };
                        $labelIns = match($mc['estado_inscripcion']) {
                            'inscrito'  => 'Inscrito',
                            'en_curso'  => 'En Curso',
                            'aprobado'  => 'Aprobado',
                            'reprobado' => 'Reprobado',
                            default     => 'Retirado',
                        };
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($mc['titulo']) ?></strong></td>
                        <td><?= htmlspecialchars($mc['instructor'] ?? '—') ?></td>
                        <td>
                            <span class="badge badge-navy" style="font-size:11px;">
                                <?= htmlspecialchars($mc['modalidad']) ?>
                            </span>
                        </td>
                        <td style="font-size:12px;">
                            <?= $mc['fecha_inicio'] ? date('d/m/Y', strtotime($mc['fecha_inicio'])) : '—' ?>
                            <?php if ($mc['fecha_fin']): ?>
                                <br><?= date('d/m/Y', strtotime($mc['fecha_fin'])) ?>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12.5px;">
                            <?= $mc['duracion_horas'] ? $mc['duracion_horas'] . ' hrs' : '—' ?>
                        </td>
                        <td>
                            <span class="badge badge-dot <?= $badgeIns ?>"><?= $labelIns ?></span>
                        </td>
                        <td style="font-weight:600;font-size:14px;">
                            <?= $mc['calificacion'] !== null
                                ? number_format($mc['calificacion'], 1)
                                : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- .grid-2 -->

</div><!-- .page-body -->
</div><!-- .main-content -->

<!-- ============================================================
     MODAL: Nuevo / Editar Curso
     ============================================================ -->
<div class="modal-overlay" id="modalNuevoCurso">
    <div class="modal-box" style="max-width:680px;">
        <div class="modal-header">
            <span class="modal-title" id="modalCursoTitulo">Nuevo Curso</span>
            <button class="modal-close" onclick="closeModal('modalNuevoCurso')">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="curso_id">
            <div class="form-grid">
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Título del Curso <span class="required">*</span></label>
                    <input class="form-control" type="text" id="curso_titulo"
                           placeholder="Ej: Gestión Avanzada de Proyectos">
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Descripción</label>
                    <textarea class="form-control" id="curso_descripcion" rows="2"
                              placeholder="Descripción del curso y sus objetivos..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Instructor</label>
                    <select class="form-control" id="curso_instructor_id">
                        <option value="">Sin asignar</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?= $u['id'] ?>">
                                <?= htmlspecialchars($u['nombre_completo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Modalidad</label>
                    <select class="form-control" id="curso_modalidad">
                        <option value="Presencial">Presencial</option>
                        <option value="Virtual">Virtual</option>
                        <option value="Híbrido">Híbrido</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Duración (horas)</label>
                    <input class="form-control" type="number" id="curso_duracion"
                           placeholder="0" min="0" step="0.5">
                </div>
                <div class="form-group">
                    <label class="form-label">Máx. Participantes</label>
                    <input class="form-control" type="number" id="curso_max_part"
                           placeholder="Sin límite" min="1">
                </div>
                <div class="form-group">
                    <label class="form-label">Fecha de Inicio</label>
                    <input class="form-control" type="datetime-local" id="curso_fecha_inicio">
                </div>
                <div class="form-group">
                    <label class="form-label">Fecha de Fin</label>
                    <input class="form-control" type="datetime-local" id="curso_fecha_fin">
                </div>
                <div class="form-group" id="grupo_estado" style="display:none;">
                    <label class="form-label">Estado</label>
                    <select class="form-control" id="curso_estado">
                        <option value="programado">Programado</option>
                        <option value="en_curso">En Curso</option>
                        <option value="completado">Completado</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalNuevoCurso')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarCurso()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"/>
                    <polyline points="17,21 17,13 7,13"/>
                    <polyline points="7,3 7,8 15,8"/>
                </svg>
                Guardar Curso
            </button>
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL: Ver Detalle del Curso + Participantes
     ============================================================ -->
<div class="modal-overlay" id="modalVerCurso">
    <div class="modal-box" style="max-width:700px;">
        <div class="modal-header">
            <span class="modal-title">Detalle del Curso</span>
            <button class="modal-close" onclick="closeModal('modalVerCurso')">×</button>
        </div>
        <div class="modal-body" id="modalVerCursoBody">
            <!-- contenido dinámico -->
        </div>
    </div>
</div>

<!-- ============================================================
     MODAL: Inscripción
     ============================================================ -->
<div class="modal-overlay" id="modalInscripcion">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header">
            <span class="modal-title">Inscripción al Curso</span>
            <button class="modal-close" onclick="closeModal('modalInscripcion')">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="ins_curso_id">
            <p id="ins_curso_nombre" style="font-size:14px;font-weight:600;
               color:var(--c-navy);margin-bottom:16px;"></p>
            <div class="form-group">
                <label class="form-label">Inscribir a</label>
                <select class="form-control" id="ins_usuario_id">
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= $u['id'] ?>"
                            <?= $u['id'] == $uid ? 'selected':'' ?>>
                            <?= htmlspecialchars($u['nombre_completo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalInscripcion')">Cancelar</button>
            <button class="btn btn-primary" onclick="confirmarInscripcion()">
                Confirmar Inscripción
            </button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
// ── Guardar curso ─────────────────────────────────────────────
async function guardarCurso() {
    const titulo = document.getElementById('curso_titulo').value.trim();
    if (!titulo) {
        showToast('Requerido', 'El título del curso es obligatorio.', 'error'); return;
    }

    const id = document.getElementById('curso_id').value;
    const fd = new FormData();
    fd.append('action',            id ? 'update_curso' : 'create_curso');
    fd.append('id',                id);
    fd.append('titulo',            titulo);
    fd.append('descripcion',       document.getElementById('curso_descripcion').value);
    fd.append('instructor_id',     document.getElementById('curso_instructor_id').value);
    fd.append('modalidad',         document.getElementById('curso_modalidad').value);
    fd.append('duracion_horas',    document.getElementById('curso_duracion').value);
    fd.append('max_participantes', document.getElementById('curso_max_part').value);
    fd.append('fecha_inicio',      document.getElementById('curso_fecha_inicio').value);
    fd.append('fecha_fin',         document.getElementById('curso_fecha_fin').value);
    if (id) fd.append('estado',    document.getElementById('curso_estado').value);

    try {
        const res  = await fetch('../api/capacitacion_api.php', { method:'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast('Guardado', data.message, 'success');
            closeModal('modalNuevoCurso');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'No se pudo conectar con el servidor.', 'error');
    }
}

// ── Ver detalle del curso ─────────────────────────────────────
async function verCurso(id) {
    try {
        const res  = await fetch(`../api/capacitacion_api.php?action=get_curso&id=${id}`);
        const data = await res.json();
        if (!data.success) { showToast('Error', data.message, 'error'); return; }

        const c = data.data;
        const p = data.participantes ?? [];

        let participantesHTML = p.length === 0
            ? '<p style="color:var(--c-text-3);text-align:center;padding:16px;">Sin participantes aún.</p>'
            : `<div class="table-wrapper"><table>
                <thead><tr><th>Nombre</th><th>Rol</th><th>Estado</th><th>Calificación</th></tr></thead>
                <tbody>${p.map(u => `<tr>
                    <td><strong>${escHtml(u.nombre)}</strong><br>
                        <small style="color:var(--c-text-3);">${escHtml(u.email??'')}</small></td>
                    <td>${escHtml(u.rol??'')}</td>
                    <td><span class="badge ${u.estado==='aprobado'?'badge-active':u.estado==='reprobado'?'badge-cancelled':'badge-pending'} badge-dot">
                        ${u.estado}</span></td>
                    <td style="font-weight:600;">${u.calificacion ?? '—'}</td>
                </tr>`).join('')}
                </tbody></table></div>`;

        document.getElementById('modalVerCursoBody').innerHTML = `
            <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
                <span class="badge badge-navy">${escHtml(c.modalidad)}</span>
                <span class="badge ${c.estado==='completado'?'badge-completed':c.estado==='en_curso'?'badge-active':'badge-pending'} badge-dot">
                    ${escHtml(c.estado)}</span>
            </div>
            <h3 style="font-size:16px;font-weight:700;margin-bottom:8px;">${escHtml(c.titulo)}</h3>
            ${c.descripcion ? `<p style="font-size:13px;color:var(--c-text-2);margin-bottom:16px;">
                ${escHtml(c.descripcion)}</p>` : ''}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:12.5px;
                        background:#F9FAFB;padding:14px;border-radius:var(--radius);margin-bottom:16px;">
                <div><span style="color:var(--c-text-3);">Instructor:</span><br>
                    <strong>${escHtml(c.instructor_nombre??'—')}</strong></div>
                <div><span style="color:var(--c-text-3);">Duración:</span><br>
                    <strong>${c.duracion_horas ? c.duracion_horas + ' horas' : '—'}</strong></div>
                <div><span style="color:var(--c-text-3);">Inicio:</span><br>
                    <strong>${c.fecha_inicio ? new Date(c.fecha_inicio).toLocaleDateString('es-PE') : '—'}</strong></div>
                <div><span style="color:var(--c-text-3);">Fin:</span><br>
                    <strong>${c.fecha_fin ? new Date(c.fecha_fin).toLocaleDateString('es-PE') : '—'}</strong></div>
            </div>
            <div style="font-size:13px;font-weight:600;margin-bottom:10px;color:var(--c-text-1);">
                Participantes (${p.length}${c.max_participantes ? '/' + c.max_participantes : ''})
            </div>
            ${participantesHTML}
        `;
        openModal('modalVerCurso');
    } catch(e) {
        showToast('Error', 'No se pudo cargar el curso.', 'error');
    }
}

// ── Editar curso ──────────────────────────────────────────────
async function editarCurso(id) {
    try {
        const res  = await fetch(`../api/capacitacion_api.php?action=get_curso&id=${id}`);
        const data = await res.json();
        if (!data.success) { showToast('Error', data.message, 'error'); return; }

        const c = data.data;
        document.getElementById('curso_id').value           = c.id;
        document.getElementById('curso_titulo').value       = c.titulo;
        document.getElementById('curso_descripcion').value  = c.descripcion ?? '';
        document.getElementById('curso_instructor_id').value= c.instructor_id ?? '';
        document.getElementById('curso_modalidad').value    = c.modalidad;
        document.getElementById('curso_duracion').value     = c.duracion_horas ?? '';
        document.getElementById('curso_max_part').value     = c.max_participantes ?? '';
        document.getElementById('curso_fecha_inicio').value = c.fecha_inicio
            ? c.fecha_inicio.replace(' ','T').substring(0,16) : '';
        document.getElementById('curso_fecha_fin').value    = c.fecha_fin
            ? c.fecha_fin.replace(' ','T').substring(0,16) : '';
        document.getElementById('curso_estado').value       = c.estado;

        document.getElementById('grupo_estado').style.display = 'block';
        document.getElementById('modalCursoTitulo').textContent = 'Editar Curso';
        openModal('modalNuevoCurso');
    } catch(e) {
        showToast('Error', 'No se pudo cargar el curso.', 'error');
    }
}

// ── Inscripción ───────────────────────────────────────────────
function inscribirme(cursoId, cursoNombre) {
    document.getElementById('ins_curso_id').value    = cursoId;
    document.getElementById('ins_curso_nombre').textContent = cursoNombre;
    openModal('modalInscripcion');
}

async function confirmarInscripcion() {
    const fd = new FormData();
    fd.append('action',     'inscribir');
    fd.append('curso_id',   document.getElementById('ins_curso_id').value);
    fd.append('usuario_id', document.getElementById('ins_usuario_id').value);

    try {
        const res  = await fetch('../api/capacitacion_api.php', { method:'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast('Inscripción exitosa', data.message, 'success');
            closeModal('modalInscripcion');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch(e) {
        showToast('Error', 'Error al procesar la inscripción.', 'error');
    }
}

// Limpiar modal al crear nuevo curso
document.querySelector('[onclick="openModal(\'modalNuevoCurso\')"]')
    ?.addEventListener('click', () => {
        ['curso_id','curso_titulo','curso_descripcion','curso_duracion',
         'curso_max_part','curso_fecha_inicio','curso_fecha_fin'].forEach(id =>
            document.getElementById(id).value = '');
        document.getElementById('curso_instructor_id').value = '';
        document.getElementById('curso_modalidad').value     = 'Presencial';
        document.getElementById('curso_estado').value        = 'programado';
        document.getElementById('grupo_estado').style.display= 'none';
        document.getElementById('modalCursoTitulo').textContent = 'Nuevo Curso';
    });
</script>