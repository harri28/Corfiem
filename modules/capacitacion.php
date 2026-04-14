<?php
// ============================================================
//  modules/capacitacion.php — Sistema de Capacitación
//  Ruta: C:\xampp\htdocs\Corfiem_Cesar\modules\capacitacion.php
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session     = require_auth();
$uid         = (int)$session['usuario_id'];
$page_title  = 'Capacitación';
$page_active = 'capacitacion';

$es_admin = $session['usuario_rol'] === 'Admin';

// ── Listado de cursos ─────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if (!empty($_GET['estado'])) {
    $where[]  = 'c.estado = ?';
    $params[] = $_GET['estado'];
}
if (!empty($_GET['q'])) {
    $where[]  = 'c.titulo ILIKE ?';
    $q        = '%' . trim($_GET['q']) . '%';
    $params[] = $q;
}

$cursos = db_fetch_all(
    "SELECT c.id, c.titulo, c.descripcion, c.duracion_horas,
            c.modalidad, c.estado, c.fecha_inicio, c.fecha_fin,
            c.max_participantes,
            u.nombre || ' ' || u.apellido AS instructor,
            (SELECT COUNT(*) FROM inscripciones_curso ic 
             WHERE ic.curso_id = c.id) AS total_inscritos,
            (SELECT COUNT(*) FROM inscripciones_curso ic 
             WHERE ic.curso_id = c.id AND ic.estado = 'aprobado') AS total_aprobados,
            (SELECT COUNT(*) FROM materiales_curso mc 
             WHERE mc.curso_id = c.id) AS total_materiales,
            (SELECT COUNT(*) FROM cuestionarios q 
             WHERE q.curso_id = c.id) AS total_cuestionarios
     FROM cursos c
     LEFT JOIN usuarios u ON c.instructor_id = u.id
     WHERE " . implode(' AND ', $where) .
    " ORDER BY c.created_at DESC",
    $params
);

// ── KPIs ──────────────────────────────────────────────────────
$kpi = db_fetch_one(
    "SELECT
        COUNT(*) AS total_cursos,
        COUNT(*) FILTER (WHERE estado = 'publicado') AS cursos_activos,
        (SELECT COUNT(*) FROM inscripciones_curso) AS total_inscripciones,
        (SELECT COUNT(*) FROM inscripciones_curso WHERE estado = 'completado') AS total_aprobados
     FROM cursos"
);

$instructores = db_fetch_all(
    "SELECT id, nombre || ' ' || apellido AS nombre_completo 
     FROM usuarios WHERE activo = TRUE ORDER BY nombre"
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
<?php render_topbar('Sistema de Capacitación', 'Cursos, materiales y evaluaciones'); ?>

<div class="page-body">

    <!-- ── KPIs ─────────────────────────────────────────────── -->
    <div class="kpi-grid" style="margin-bottom:24px;">
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
            <div class="kpi-icon" style="background:var(--c-accent-lt);">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--c-accent)" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12,6 12,12 16,14"/>
                </svg>
            </div>
            <div class="kpi-value" style="color:var(--c-accent);"><?= $kpi['cursos_activos'] ?></div>
            <div class="kpi-label">Cursos Activos</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon" style="background:#E0E7FF;">
                <svg viewBox="0 0 24 24" fill="none" stroke="#6366F1" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div class="kpi-value" style="color:#6366F1;"><?= $kpi['total_inscripciones'] ?></div>
            <div class="kpi-label">Inscripciones</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon" style="background:var(--c-success-lt);">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--c-success)" stroke-width="2">
                    <polyline points="20,6 9,17 4,12"/>
                </svg>
            </div>
            <div class="kpi-value" style="color:var(--c-success);"><?= $kpi['total_aprobados'] ?></div>
            <div class="kpi-label">Aprobados</div>
        </div>
    </div>

    <!-- ── Filtros ───────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:20px;padding:16px 20px;">
        <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <div class="search-wrapper" style="flex:1;min-width:220px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input class="search-input" type="text" name="q"
                       placeholder="Buscar curso..."
                       value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
            </div>
            <select name="estado" class="form-control" style="width:160px;">
                <option value="">Todos los estados</option>
                <option value="borrador"  <?= ($_GET['estado']??'') === 'borrador'  ? 'selected':'' ?>>Borrador</option>
                <option value="publicado" <?= ($_GET['estado']??'') === 'publicado' ? 'selected':'' ?>>Publicado</option>
                <option value="archivado" <?= ($_GET['estado']??'') === 'archivado' ? 'selected':'' ?>>Archivado</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            <?php if (!empty($_GET['q']) || !empty($_GET['estado'])): ?>
                <a href="capacitacion.php" class="btn btn-secondary btn-sm">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ── Listado de cursos ─────────────────────────────────── -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">📚 Catálogo de Cursos</span>
            <?php if ($es_admin): ?>
            <button class="btn btn-primary btn-sm" onclick="openModal('modalNuevoCurso')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Nuevo Curso
            </button>
            <?php endif; ?>
        </div>

        <?php if (empty($cursos)): ?>
            <div style="text-align:center;padding:60px 20px;color:var(--c-text-3);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                     style="width:64px;height:64px;margin:0 auto 16px;opacity:0.3;">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                </svg>
                <p style="font-size:15px;margin-bottom:8px;">No hay cursos disponibles</p>
                <p style="font-size:13px;">Crea el primer curso para comenzar</p>
            </div>
        <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));
                        gap:20px;padding:20px;">
                <?php foreach ($cursos as $curso): 
                    $estadoColor = match($curso['estado']) {
                        'borrador'   => '#6B7280',
                        'publicado'  => '#1B3A6B',
                        'archivado'  => '#92400E',
                        default      => '#374151'
                    };
                    $estadoBg = match($curso['estado']) {
                        'borrador'   => '#F3F4F6',
                        'publicado'  => '#EFF3FB',
                        'archivado'  => '#FEF3C7',
                        default      => '#F9FAFB'
                    };
                    $progreso = $curso['total_inscritos'] > 0 
                        ? round(($curso['total_aprobados'] / $curso['total_inscritos']) * 100)
                        : 0;
                ?>
                <div style="background:#fff;border:1px solid var(--c-border);
                            border-radius:var(--radius);overflow:hidden;
                            transition:box-shadow 0.2s;cursor:pointer;"
                     onclick="verCurso(<?= $curso['id'] ?>)"
                     onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'"
                     onmouseout="this.style.boxShadow='none'">
                    
                    <!-- Header del curso -->
                    <div style="padding:16px 18px;border-bottom:1px solid var(--c-border);">
                        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px;">
                            <h3 style="font-size:15px;font-weight:700;color:var(--c-text-1);
                                       margin:0;flex:1;">
                                <?= htmlspecialchars($curso['titulo']) ?>
                            </h3>
                            <span style="display:inline-flex;padding:3px 10px;
                                         border-radius:20px;font-size:11px;font-weight:700;
                                         background:<?= $estadoBg ?>;color:<?= $estadoColor ?>;
                                         margin-left:8px;white-space:nowrap;">
                                <?= strtoupper($curso['estado']) ?>
                            </span>
                        </div>
                        
                        <?php if ($curso['descripcion']): ?>
                        <p style="font-size:13px;color:var(--c-text-3);margin:0;
                                  line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;
                                  -webkit-box-orient:vertical;overflow:hidden;">
                            <?= htmlspecialchars($curso['descripcion']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Info del curso -->
                    <div style="padding:14px 18px;background:#FAFAFA;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;
                                    font-size:12px;">
                            <div style="display:flex;align-items:center;gap:6px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                                     stroke-width="2" style="width:14px;height:14px;color:var(--c-text-3);">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12,6 12,12 16,14"/>
                                </svg>
                                <span style="color:var(--c-text-2);">
                                    <?= $curso['duracion_horas'] ?> horas
                                </span>
                            </div>
                            
                            <div style="display:flex;align-items:center;gap:6px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                                     stroke-width="2" style="width:14px;height:14px;color:var(--c-text-3);">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                                <span style="color:var(--c-text-2);">
                                    <?= $curso['modalidad'] ?>
                                </span>
                            </div>
                            
                            <div style="display:flex;align-items:center;gap:6px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                                     stroke-width="2" style="width:14px;height:14px;color:var(--c-text-3);">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                </svg>
                                <span style="color:var(--c-text-2);">
                                    <?= $curso['total_inscritos'] ?>/<?= $curso['max_participantes'] ?? '∞' ?>
                                </span>
                            </div>
                            
                            <div style="display:flex;align-items:center;gap:6px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                                     stroke-width="2" style="width:14px;height:14px;color:var(--c-text-3);">
                                    <polyline points="20,6 9,17 4,12"/>
                                </svg>
                                <span style="color:var(--c-success);font-weight:600;">
                                    <?= $progreso ?>% aprobados
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($curso['instructor']): ?>
                        <div style="margin-top:12px;padding-top:12px;
                                    border-top:1px solid var(--c-border);
                                    font-size:12px;color:var(--c-text-3);">
                            👨‍🏫 <?= htmlspecialchars($curso['instructor']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Stats -->
                    <div style="padding:12px 18px;display:flex;gap:16px;
                                border-top:1px solid var(--c-border);
                                background:#fff;font-size:12px;">
                        <div style="display:flex;align-items:center;gap:5px;">
                            <span style="font-weight:700;color:var(--c-navy);">
                                <?= $curso['total_materiales'] ?>
                            </span>
                            <span style="color:var(--c-text-3);">materiales</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:5px;">
                            <span style="font-weight:700;color:var(--c-accent);">
                                <?= $curso['total_cuestionarios'] ?>
                            </span>
                            <span style="color:var(--c-text-3);">evaluaciones</span>
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
     MODAL: Nuevo Curso
     ============================================================ -->
<div class="modal-overlay" id="modalNuevoCurso">
    <div class="modal-box" style="max-width:700px;">
        <div class="modal-header">
            <span class="modal-title">Nuevo Curso de Capacitación</span>
            <button class="modal-close" onclick="closeModal('modalNuevoCurso')">×</button>
        </div>
        <div class="modal-body">
            <form id="formNuevoCurso">
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Título del Curso <span class="required">*</span></label>
                        <input class="form-control" type="text" name="titulo" 
                               placeholder="Ej: Fundamentos de Project Management" required>
                    </div>
                    
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3"
                                  placeholder="Descripción breve del curso..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nivel <span class="required">*</span></label>
                        <select class="form-control" name="nivel" required>
                            <option value="básico">Básico</option>
                            <option value="intermedio" selected>Intermedio</option>
                            <option value="avanzado">Avanzado</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Instructor</label>
                        <select class="form-control" name="instructor_id">
                            <option value="">Asignar instructor...</option>
                            <?php foreach ($instructores as $inst): ?>
                                <option value="<?= $inst['id'] ?>">
                                    <?= htmlspecialchars($inst['nombre_completo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Duración (horas)</label>
                        <input class="form-control" type="number" name="duracion_horas"
                               step="0.5" min="0" placeholder="8">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Modalidad</label>
                        <select class="form-control" name="modalidad">
                            <option value="Presencial">Presencial</option>
                            <option value="Virtual" selected>Virtual</option>
                            <option value="Híbrido">Híbrido</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Máx. Participantes</label>
                        <input class="form-control" type="number" name="max_participantes"
                               min="1" placeholder="Ilimitado">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Estado inicial</label>
                        <select class="form-control" name="estado">
                            <option value="borrador" selected>Borrador</option>
                            <option value="publicado">Publicado</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Fecha de Inicio</label>
                        <input class="form-control" type="datetime-local" name="fecha_inicio">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Fecha de Fin</label>
                        <input class="form-control" type="datetime-local" name="fecha_fin">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('modalNuevoCurso')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarCurso()">Crear Curso</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
// ── Guardar curso ─────────────────────────────────────────────
async function guardarCurso() {
    const form = document.getElementById('formNuevoCurso');
    const fd = new FormData(form);
    fd.append('action', 'create');
    
    if (!form.titulo.value.trim()) {
        showToast('Campo requerido', 'El título es obligatorio.', 'error');
        return;
    }
    
    try {
        const res = await fetch('../api/capacitacion_api.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            showToast('Curso creado', data.message, 'success');
            closeModal('modalNuevoCurso');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast('Error', data.message, 'error');
        }
    } catch (e) {
        showToast('Error', 'No se pudo guardar el curso.', 'error');
    }
}

// ── Ver curso (ir al detalle) ─────────────────────────────────
function verCurso(id) {
    window.location.href = `curso_detalle.php?id=${id}`;
}
</script>