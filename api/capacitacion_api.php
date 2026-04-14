<?php
// ============================================================
//  api/capacitacion_api.php — API de Capacitación
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session = require_auth();
$uid     = (int)$session['usuario_id'];
$action  = $_REQUEST['action'] ?? '';

header('Content-Type: application/json; charset=utf-8');

try {
    match ($action) {
        'create'              => createCurso($uid),
        'update'              => updateCurso($uid),
        'delete'              => deleteCurso($uid),
        'get'                 => getCurso(),
        
        // Materiales
        'create_material'     => createMaterial($uid),
        'delete_material'     => deleteMaterial($uid),
        'marcar_completado'   => marcarCompletado($uid),
        
        // Cuestionarios
        'create_cuestionario' => createCuestionario($uid),
        'delete_cuestionario' => deleteCuestionario($uid),
        'get_cuestionario'    => getCuestionario(),
        'guardar_pregunta'    => guardarPregunta($uid),
        'eliminar_pregunta'   => eliminarPregunta($uid),
        'iniciar_intento'     => iniciarIntento($uid),
        'enviar_respuestas'   => enviarRespuestas($uid),
        
        // Inscripciones
        'inscribirse'         => inscribirse($uid),
        
        default               => json_response(['success'=>false,'message'=>'Acción no válida.'],400),
    };
} catch (Throwable $e) {
    error_log('[CORFIEM Capacitación API] ' . $e->getMessage());
    json_response(['success'=>false,'message'=>'Error interno.'],500);
}

// ═════════════════════════════════════════════════════════════
//  CURSOS
// ═════════════════════════════════════════════════════════════

function createCurso(int $uid): never {
    $titulo      = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $instructor  = (int)($_POST['instructor_id'] ?? 0) ?: null;
    $duracion    = (float)($_POST['duracion_horas'] ?? 0) ?: null;
    $modalidad   = $_POST['modalidad'] ?? 'Virtual';
    $nivel       = $_POST['nivel'] ?? 'intermedio';
    $max_part    = (int)($_POST['max_participantes'] ?? 0) ?: null;
    $fecha_ini   = $_POST['fecha_inicio'] ?? null ?: null;
    $fecha_fin   = $_POST['fecha_fin'] ?? null ?: null;
    $estado      = in_array($_POST['estado'] ?? '', ['borrador','publicado','archivado'])
                   ? $_POST['estado'] : 'borrador';

    if (empty($titulo)) {
        json_response(['success'=>false,'message'=>'El título es requerido.'], 422);
    }

    $id = db_insert(
        "INSERT INTO cursos
           (titulo, descripcion, instructor_id, duracion_horas, modalidad, nivel,
            max_participantes, fecha_inicio, fecha_fin, estado)
         VALUES (?,?,?,?,?,?,?,?,?,?) RETURNING id",
        [$titulo, $descripcion, $instructor, $duracion, $modalidad, $nivel,
         $max_part, $fecha_ini, $fecha_fin, $estado]
    );

    audit_log($uid, 'CREATE', 'cursos', (int)$id, [], ['titulo'=>$titulo]);
    json_response(['success'=>true,'message'=>'Curso creado exitosamente.','id'=>(int)$id]);
}

function updateCurso(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    
    if ($id <= 0 || empty($titulo)) {
        json_response(['success'=>false,'message'=>'Datos incompletos.'], 422);
    }
    
    $antes = db_fetch_one("SELECT * FROM cursos WHERE id=?", [$id]);
    
    $estado_upd = in_array($_POST['estado'] ?? '', ['borrador','publicado','archivado'])
                  ? $_POST['estado'] : 'borrador';
    $nivel_upd  = in_array($_POST['nivel'] ?? '', ['básico','intermedio','avanzado'])
                  ? $_POST['nivel'] : 'intermedio';

    db_execute(
        "UPDATE cursos SET titulo=?, descripcion=?, instructor_id=?,
         duracion_horas=?, modalidad=?, nivel=?, max_participantes=?,
         fecha_inicio=?, fecha_fin=?, estado=?, updated_at=NOW()
         WHERE id=?",
        [
            $titulo,
            $_POST['descripcion'] ?? '',
            (int)($_POST['instructor_id'] ?? 0) ?: null,
            (float)($_POST['duracion_horas'] ?? 0) ?: null,
            $_POST['modalidad'] ?? 'Virtual',
            $nivel_upd,
            (int)($_POST['max_participantes'] ?? 0) ?: null,
            $_POST['fecha_inicio'] ?? null ?: null,
            $_POST['fecha_fin']    ?? null ?: null,
            $estado_upd,
            $id
        ]
    );
    
    audit_log($uid, 'UPDATE', 'cursos', $id, $antes??[], ['titulo'=>$titulo]);
    json_response(['success'=>true,'message'=>'Curso actualizado.']);
}

function deleteCurso(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    $antes = db_fetch_one("SELECT titulo FROM cursos WHERE id=?", [$id]);
    db_execute("DELETE FROM cursos WHERE id=?", [$id]);
    audit_log($uid, 'DELETE', 'cursos', $id, $antes??[]);
    json_response(['success'=>true,'message'=>'Curso eliminado.']);
}

function getCurso(): never {
    $id = (int)($_GET['id'] ?? 0);
    
    $curso = db_fetch_one(
        "SELECT c.*, u.nombre || ' ' || u.apellido AS instructor_nombre
         FROM cursos c
         LEFT JOIN usuarios u ON c.instructor_id = u.id
         WHERE c.id = ?",
        [$id]
    );
    
    if (!$curso) {
        json_response(['success'=>false,'message'=>'Curso no encontrado.'], 404);
    }
    
    // Materiales del curso
    $materiales = db_fetch_all(
        "SELECT m.*,
         (SELECT COUNT(*) FROM progreso_materiales pm
          WHERE pm.material_id = m.id AND pm.completado = TRUE) AS completados
         FROM materiales_curso m
         WHERE m.curso_id = ?
         ORDER BY m.orden, m.id",
        [$id]
    );

    // Cuestionarios
    $cuestionarios = db_fetch_all(
        "SELECT q.*,
         (SELECT COUNT(*) FROM preguntas p
          WHERE p.cuestionario_id = q.id) AS total_preguntas
         FROM cuestionarios q
         WHERE q.curso_id = ?
         ORDER BY q.id",
        [$id]
    );
    
    // Inscritos
    $inscritos = db_fetch_all(
        "SELECT ic.*, u.nombre || ' ' || u.apellido AS usuario_nombre
         FROM inscripciones_curso ic
         LEFT JOIN usuarios u ON ic.usuario_id = u.id
         WHERE ic.curso_id = ?
         ORDER BY ic.created_at DESC",
        [$id]
    );
    
    json_response([
        'success' => true,
        'data' => [
            'curso' => $curso,
            'materiales' => $materiales,
            'cuestionarios' => $cuestionarios,
            'inscritos' => $inscritos
        ]
    ]);
}

// ═════════════════════════════════════════════════════════════
//  MATERIALES
// ═════════════════════════════════════════════════════════════

function createMaterial(int $uid): never {
    $curso_id    = (int)($_POST['curso_id'] ?? 0);
    $titulo      = trim($_POST['titulo'] ?? '');
    $tipo        = $_POST['tipo'] ?? 'video';
    $contenido   = trim($_POST['contenido'] ?? '') ?: null;
    $descripcion = trim($_POST['descripcion'] ?? '') ?: null;
    $duracion    = (int)($_POST['duracion_minutos'] ?? 0) ?: null;
    $obligatorio = ($_POST['obligatorio'] ?? '0') === '1' ? 'true' : 'false';

    if ($curso_id <= 0 || empty($titulo)) {
        json_response(['success'=>false,'message'=>'Datos incompletos.'], 422);
    }

    // Validar tipo permitido por el schema
    $tipos_validos = ['video', 'pdf', 'link', 'texto'];
    if (!in_array($tipo, $tipos_validos)) {
        json_response(['success'=>false,'message'=>'Tipo de material no válido.'], 422);
    }

    // Manejo de archivo PDF subido
    if ($tipo === 'pdf' && !empty($_FILES['archivo']['tmp_name'])) {
        $file = $_FILES['archivo'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext !== 'pdf') {
            json_response(['success'=>false,'message'=>'Solo se permiten archivos PDF.'], 422);
        }
        if ($file['size'] > 50 * 1024 * 1024) {
            json_response(['success'=>false,'message'=>'El archivo excede 50 MB.'], 422);
        }

        $filename = uniqid('cap_', true) . '.pdf';
        $destPath = CAP_PATH . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            json_response(['success'=>false,'message'=>'Error al guardar el archivo.'], 500);
        }

        $contenido = 'capacitacion/' . $filename; // ruta relativa desde uploads/
    }

    // Para video y link, contenido es obligatorio
    if (in_array($tipo, ['video', 'link']) && empty($contenido)) {
        json_response(['success'=>false,'message'=>'La URL es requerida.'], 422);
    }

    $id = db_insert(
        "INSERT INTO materiales_curso
           (curso_id, titulo, tipo, contenido, descripcion, duracion_minutos, obligatorio)
         VALUES (?,?,?,?,?,?,?) RETURNING id",
        [$curso_id, $titulo, $tipo, $contenido, $descripcion, $duracion, $obligatorio]
    );

    audit_log($uid, 'CREATE', 'materiales_curso', (int)$id, [], ['titulo'=>$titulo,'tipo'=>$tipo]);
    json_response(['success'=>true,'message'=>'Material agregado correctamente.','id'=>(int)$id]);
}

function deleteMaterial(int $uid): never {
    $id       = (int)($_POST['id'] ?? 0);
    $material = db_fetch_one("SELECT * FROM materiales_curso WHERE id=?", [$id]);

    // Eliminar archivo físico si es un PDF subido
    if (!empty($material['contenido']) && $material['tipo'] === 'pdf' &&
        str_starts_with($material['contenido'], 'capacitacion/')) {
        $fullPath = UPLOADS_PATH . '/' . $material['contenido'];
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    db_execute("DELETE FROM materiales_curso WHERE id=?", [$id]);
    audit_log($uid, 'DELETE', 'materiales_curso', $id, $material ?? []);
    json_response(['success'=>true,'message'=>'Material eliminado.']);
}

function marcarCompletado(int $uid): never {
    $material_id = (int)($_POST['material_id'] ?? 0);
    $completado = $_POST['completado'] === '1';

    // Obtener inscripcion_id
    $inscripcion = db_fetch_one(
        "SELECT ic.id FROM inscripciones_curso ic
         INNER JOIN materiales_curso m ON m.curso_id = ic.curso_id
         WHERE m.id = ? AND ic.usuario_id = ?
         LIMIT 1",
        [$material_id, $uid]
    );

    if (!$inscripcion) {
        json_response(['success'=>false,'message'=>'No estás inscrito en este curso.'], 422);
    }

    $inscripcion_id = $inscripcion['id'];

    if ($completado) {
        db_execute(
            "INSERT INTO progreso_materiales (inscripcion_id, material_id, completado, fecha_completado)
             VALUES (?,?,TRUE,NOW())
             ON CONFLICT (inscripcion_id, material_id)
             DO UPDATE SET completado = TRUE, fecha_completado = NOW()",
            [$inscripcion_id, $material_id]
        );
    } else {
        db_execute(
            "UPDATE progreso_materiales SET completado = FALSE
             WHERE inscripcion_id = ? AND material_id = ?",
            [$inscripcion_id, $material_id]
        );
    }

    json_response(['success'=>true,'message'=>'Progreso actualizado.']);
}

// ═════════════════════════════════════════════════════════════
//  CUESTIONARIOS
// ═════════════════════════════════════════════════════════════

function createCuestionario(int $uid): never {
    $curso_id = (int)($_POST['curso_id'] ?? 0);
    $titulo   = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $nota_min = (float)($_POST['nota_minima'] ?? 60);
    $intentos = (int)($_POST['intentos_max'] ?? 3);
    $tiempo   = (int)($_POST['tiempo_limite'] ?? 0) ?: null;
    
    if ($curso_id <= 0 || empty($titulo)) {
        json_response(['success'=>false,'message'=>'Datos incompletos.'], 422);
    }
    
    $id = db_insert(
        "INSERT INTO cuestionarios
           (curso_id, titulo, descripcion, puntaje_minimo_aprobacion, intentos_permitidos, tiempo_limite_minutos)
         VALUES (?,?,?,?,?,?) RETURNING id",
        [$curso_id, $titulo, $descripcion, $nota_min, $intentos, $tiempo]
    );
    
    audit_log($uid, 'CREATE', 'cuestionarios', (int)$id, [], ['titulo'=>$titulo]);
    json_response(['success'=>true,'message'=>'Cuestionario creado.','id'=>(int)$id]);
}

function deleteCuestionario(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    $antes = db_fetch_one("SELECT titulo FROM cuestionarios WHERE id=?", [$id]);
    db_execute("DELETE FROM cuestionarios WHERE id=?", [$id]);
    audit_log($uid, 'DELETE', 'cuestionarios', $id, $antes??[]);
    json_response(['success'=>true,'message'=>'Cuestionario eliminado.']);
}

function getCuestionario(): never {
    $id = (int)($_GET['id'] ?? 0);
    
    $cuestionario = db_fetch_one("SELECT * FROM cuestionarios WHERE id=?", [$id]);
    if (!$cuestionario) {
        json_response(['success'=>false,'message'=>'Cuestionario no encontrado.'], 404);
    }
    
    $preguntas = db_fetch_all(
        "SELECT * FROM preguntas
         WHERE cuestionario_id = ? ORDER BY orden, id",
        [$id]
    );

    // Opciones de cada pregunta
    foreach ($preguntas as &$pregunta) {
        $pregunta['opciones'] = db_fetch_all(
            "SELECT * FROM opciones_respuesta
             WHERE pregunta_id = ? ORDER BY orden, id",
            [$pregunta['id']]
        );
    }
    
    json_response([
        'success' => true,
        'data' => [
            'cuestionario' => $cuestionario,
            'preguntas' => $preguntas
        ]
    ]);
}

function guardarPregunta(int $uid): never {
    $cuestionario_id = (int)($_POST['cuestionario_id'] ?? 0);
    $pregunta   = trim($_POST['pregunta'] ?? '');
    $tipo       = $_POST['tipo'] ?? 'multiple_choice';
    $puntaje    = (float)($_POST['puntaje'] ?? 1);
    $opciones   = json_decode($_POST['opciones'] ?? '[]', true);
    $referencia = trim($_POST['respuesta_referencia'] ?? '') ?: null;

    if ($cuestionario_id <= 0 || empty($pregunta)) {
        json_response(['success'=>false,'message'=>'Datos incompletos.'], 422);
    }

    $tipos_validos = ['multiple_choice', 'verdadero_falso', 'respuesta_corta'];
    if (!in_array($tipo, $tipos_validos)) {
        json_response(['success'=>false,'message'=>'Tipo de pregunta no válido.'], 422);
    }

    $pregunta_id = db_insert(
        "INSERT INTO preguntas
           (cuestionario_id, texto, tipo, puntos, explicacion)
         VALUES (?,?,?,?,?) RETURNING id",
        [$cuestionario_id, $pregunta, $tipo, $puntaje, $referencia]
    );

    // Guardar opciones para multiple_choice y verdadero_falso
    if (in_array($tipo, ['multiple_choice', 'verdadero_falso']) && !empty($opciones)) {
        foreach ($opciones as $idx => $opc) {
            db_execute(
                "INSERT INTO opciones_respuesta
                   (pregunta_id, texto, es_correcta, orden)
                 VALUES (?,?,?,?)",
                [
                    $pregunta_id,
                    $opc['texto'] ?? '',
                    ($opc['correcta'] ?? false) ? 'TRUE' : 'FALSE',
                    $idx
                ]
            );
        }
    }

    json_response(['success'=>true,'message'=>'Pregunta guardada correctamente.','id'=>(int)$pregunta_id]);
}

function eliminarPregunta(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    db_execute("DELETE FROM preguntas WHERE id=?", [$id]);
    json_response(['success'=>true,'message'=>'Pregunta eliminada.']);
}

function iniciarIntento(int $uid): never {
    $cuestionario_id = (int)($_POST['cuestionario_id'] ?? 0);
    
    // Verificar intentos previos
    $intentos_realizados = db_fetch_one(
        "SELECT COUNT(*) as total FROM intentos_cuestionario 
         WHERE cuestionario_id = ? AND usuario_id = ?",
        [$cuestionario_id, $uid]
    )['total'];
    
    $cuestionario = db_fetch_one("SELECT intentos_permitidos FROM cuestionarios WHERE id=?", [$cuestionario_id]);

    if ($intentos_realizados >= $cuestionario['intentos_permitidos']) {
        json_response(['success'=>false,'message'=>'Has alcanzado el máximo de intentos.'], 422);
    }
    
    $intento_id = db_insert(
        "INSERT INTO intentos_cuestionario (cuestionario_id, usuario_id)
         VALUES (?,?) RETURNING id",
        [$cuestionario_id, $uid]
    );
    
    json_response(['success'=>true,'intento_id'=>(int)$intento_id]);
}

function enviarRespuestas(int $uid): never {
    $intento_id = (int)($_POST['intento_id'] ?? 0);
    $respuestas = json_decode($_POST['respuestas'] ?? '{}', true);
    
    $intento = db_fetch_one("SELECT * FROM intentos_cuestionario WHERE id=?", [$intento_id]);
    if (!$intento || $intento['usuario_id'] != $uid) {
        json_response(['success'=>false,'message'=>'Intento inválido.'], 422);
    }
    
    // Obtener preguntas y calcular calificación
    $preguntas = db_fetch_all(
        "SELECT p.*, o.id as opcion_id, o.es_correcta
         FROM preguntas p
         LEFT JOIN opciones_respuesta o ON p.id = o.pregunta_id
         WHERE p.cuestionario_id = ?",
        [$intento['cuestionario_id']]
    );

    $puntaje_total = 0;
    $puntaje_obtenido = 0;

    foreach ($preguntas as $preg) {
        $puntaje_total += $preg['puntos'];

        $respuesta_usuario = $respuestas[$preg['id']] ?? null;
        if ($respuesta_usuario && $preg['opcion_id'] == $respuesta_usuario && $preg['es_correcta']) {
            $puntaje_obtenido += $preg['puntos'];
        }
    }

    $calificacion = $puntaje_total > 0 ? ($puntaje_obtenido / $puntaje_total) * 100 : 0;

    $cuestionario = db_fetch_one("SELECT puntaje_minimo_aprobacion FROM cuestionarios WHERE id=?", [$intento['cuestionario_id']]);
    $aprobado = $calificacion >= $cuestionario['puntaje_minimo_aprobacion'];

    db_execute(
        "UPDATE intentos_cuestionario
         SET fecha_finalizacion = NOW(), puntaje_obtenido = ?, porcentaje = ?, aprobado = ?
         WHERE id = ?",
        [$puntaje_obtenido, $calificacion, $aprobado ? 'TRUE' : 'FALSE', $intento_id]
    );
    
    json_response([
        'success' => true,
        'calificacion' => round($calificacion, 2),
        'aprobado' => $aprobado,
        'message' => $aprobado ? '¡Felicitaciones! Has aprobado.' : 'No alcanzaste la nota mínima.'
    ]);
}

// ═════════════════════════════════════════════════════════════
//  INSCRIPCIONES
// ═════════════════════════════════════════════════════════════

function inscribirse(int $uid): never {
    $curso_id = (int)($_POST['curso_id'] ?? 0);
    
    // Verificar si ya está inscrito
    $existe = db_fetch_one(
        "SELECT id FROM inscripciones_curso WHERE curso_id = ? AND usuario_id = ?",
        [$curso_id, $uid]
    );
    
    if ($existe) {
        json_response(['success'=>false,'message'=>'Ya estás inscrito en este curso.'], 422);
    }
    
    db_execute(
        "INSERT INTO inscripciones_curso (curso_id, usuario_id, estado)
         VALUES (?,?,'en_progreso')",
        [$curso_id, $uid]
    );
    
    json_response(['success'=>true,'message'=>'Inscripción realizada exitosamente.']);
}