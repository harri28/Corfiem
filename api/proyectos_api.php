<?php   // ← nuevo archivo, separado del anterior
require_once __DIR__ . '/../config/db.php';
$session = require_auth();
$uid     = (int)$session['usuario_id'];

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

// Encabezados JSON para respuestas AJAX
header('Content-Type: application/json; charset=utf-8');

try {
    match ([$method, $action]) {
        ['POST', 'create']          => createProyecto($uid),
        ['POST', 'update']          => updateProyecto($uid),
        ['POST', 'delete']          => deleteProyecto($uid),
        ['POST', 'finalizar']           => finalizarProyecto($uid),
        ['POST', 'upload_conformidad']  => uploadConformidad($uid),
        ['POST', 'delete_conformidad']  => deleteConformidad($uid),
        ['GET',  'get']             => getProyecto(),
        ['GET',  'list']            => listProyectos(),
        ['GET',  'calcular_avance'] => calcularAvance(),
        default                     => json_response(['success'=>false,'message'=>'Acción no válida.'], 400),
    };
} catch (Throwable $e) {
    error_log('[CORFIEM Proyectos API] ' . $e->getMessage());
    json_response(['success'=>false,'message'=>'Error interno del servidor.'], 500);
}

// ── CREATE ────────────────────────────────────────────────────
function createProyecto(int $uid): never {
    $nombre      = trim($_POST['nombre']      ?? '');
    $cliente_id  = (int)($_POST['cliente_id'] ?? 0);
    $es_prospecto = (int)($_POST['es_prospecto'] ?? 0);
    $prospecto_id = (int)($_POST['prospecto_id'] ?? 0);
    
    if ($es_prospecto && $prospecto_id > 0) {
        // Obtener datos del prospecto
        $prospecto = db_fetch_one(
            "SELECT * FROM prospectos WHERE id = ?",
            [$prospecto_id]
        );
        
        if (!$prospecto) {
            json_response(['success'=>false,'message'=>'Prospecto no encontrado.'], 404);
        }
        
        // Crear el cliente automáticamente
        $cliente_id = db_insert(
            "INSERT INTO clientes (razon_social, ruc_nit, contacto_nombre, contacto_telefono, contacto_email, direccion, activo)
             VALUES (?, ?, ?, ?, ?, ?, TRUE) RETURNING id",
            [
                $prospecto['empresa'] ?: $prospecto['nombre_contacto'],
                $prospecto['ruc'],
                $prospecto['nombre_contacto'],
                $prospecto['telefono'],
                $prospecto['email'],
                $prospecto['direccion']
            ]
        );
        
        // Actualizar prospecto con el cliente_id
        db_execute(
            "UPDATE prospectos SET cliente_id = ?, estado = 'aceptado', fecha_conversion = NOW() WHERE id = ?",
            [$cliente_id, $prospecto_id]
        );
        
        // Registrar en auditoría
        audit_log($uid, 'CREATE', 'clientes', $cliente_id, [], ['desde_prospecto' => $prospecto_id]);
    }
    // ── FIN NUEVO ──
    
    $resp_id     = (int)($_POST['responsable_id'] ?? 0) ?: null;
    $resp_id     = (int)($_POST['responsable_id'] ?? 0) ?: null;
    $presupuesto = (float)($_POST['presupuesto'] ?? 0) ?: null;
    $prioridad   = $_POST['prioridad']   ?? 'Media';
    $fi          = $_POST['fecha_inicio'] ?? null ?: null;
    $ff          = $_POST['fecha_fin_estimada'] ?? null ?: null;
    $alcance     = trim($_POST['alcance']     ?? '');
    $desc        = trim($_POST['descripcion'] ?? '');
    $entregables = trim($_POST['entregables'] ?? '');
    $extraido_ia = (bool)($_POST['pdf_extraido'] ?? false);
    $confianza   = (int)($_POST['ia_confianza']  ?? 0) ?: null;

    if (empty($nombre))     json_response(['success'=>false,'message'=>'El nombre es requerido.'], 422);
    if ($cliente_id <= 0)   json_response(['success'=>false,'message'=>'El cliente es requerido.'], 422);

    // Manejo del PDF
    $pdf_path   = null;
    $pdf_nombre = null;
    $pdf_size   = null;

    if (!empty($_FILES['pdf_file']['tmp_name'])) {
        $file = $_FILES['pdf_file'];
        if ($file['type'] !== 'application/pdf') {
            json_response(['success'=>false,'message'=>'Solo se permiten archivos PDF.'], 422);
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            json_response(['success'=>false,'message'=>'El PDF excede el tamaño máximo de 10 MB.'], 422);
        }

        $ext      = 'pdf';
        $filename = uniqid('proyecto_', true) . '.' . $ext;
        $destDir  = PDF_PATH;
        $destPath = $destDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            json_response(['success'=>false,'message'=>'Error al guardar el archivo PDF.'], 500);
        }

        $pdf_path   = 'uploads/proyectos/' . $filename;
        $pdf_nombre = $file['name'];
        $pdf_size   = $file['size'];
    }

    $id = db_insert(
        "INSERT INTO proyectos
           (nombre, cliente_id, responsable_id, presupuesto, prioridad,
            fecha_inicio, fecha_fin_estimada, alcance, descripcion, entregables,
            pdf_path, pdf_nombre_original, pdf_tamaño,
            extraido_por_ia, ia_confianza, estado_id)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)
         RETURNING id",
        [
            $nombre, $cliente_id, $resp_id, $presupuesto, $prioridad,
            $fi, $ff, $alcance, $desc, $entregables,
            $pdf_path, $pdf_nombre, $pdf_size,
            $extraido_ia ? 'true' : 'false', $confianza,
        ]
    );

    audit_log($uid, 'CREATE', 'proyectos', (int)$id, [], [
        'nombre' => $nombre, 'cliente_id' => $cliente_id
    ]);

    // Si fue solicitud AJAX, devolver JSON; si fue form submit, redirigir
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        json_response(['success'=>true,'message'=>'Proyecto creado exitosamente.','id'=>(int)$id]);
    } else {
        header('Location: ../modules/proyectos.php?ok=1');
        exit;
    }
}

// ── UPDATE ────────────────────────────────────────────────────
function updateProyecto(int $uid): never {
    $id          = (int)($_POST['id'] ?? 0);
    $nombre      = trim($_POST['nombre']   ?? '');
    $cliente_id  = (int)($_POST['cliente_id'] ?? 0);
    $resp_id     = (int)($_POST['responsable_id'] ?? 0) ?: null;
    $presupuesto = (float)($_POST['presupuesto']  ?? 0) ?: null;
    $prioridad   = $_POST['prioridad']    ?? 'Media';
    $fi          = $_POST['fecha_inicio'] ?? null ?: null;
    $ff          = $_POST['fecha_fin_estimada'] ?? null ?: null;
    $alcance     = trim($_POST['alcance']    ?? '');
    $desc        = trim($_POST['descripcion']?? '');
    $avance      = min(100, max(0, (int)($_POST['avance_porcentaje'] ?? 0)));
    $estado_id   = (int)($_POST['estado_id'] ?? 1);

    if ($id <= 0 || empty($nombre)) {
        json_response(['success'=>false,'message'=>'Datos incompletos.'], 422);
    }

    // Snapshot antes del cambio para auditoría
    $antes = db_fetch_one("SELECT * FROM proyectos WHERE id = ?", [$id]);

    db_execute(
        "UPDATE proyectos SET
            nombre=?, cliente_id=?, responsable_id=?, presupuesto=?,
            prioridad=?, fecha_inicio=?, fecha_fin_estimada=?,
            alcance=?, descripcion=?,
            estado_id=?, updated_at=NOW()
            WHERE id=?",
            [
                $nombre, $cliente_id, $resp_id, $presupuesto, $prioridad,
                $fi, $ff, $alcance, $desc,
                $estado_id, $id
            ]
    );

    audit_log($uid, 'UPDATE', 'proyectos', $id, $antes ?: [], [
        'nombre'=>$nombre,'estado_id'=>$estado_id,'avance'=>$avance
    ]);

    json_response(['success'=>true,'message'=>'Proyecto actualizado correctamente.']);
}

// ── DELETE ────────────────────────────────────────────────────
function deleteProyecto(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    $antes = db_fetch_one("SELECT nombre FROM proyectos WHERE id=?", [$id]);
    db_execute("DELETE FROM proyectos WHERE id=?", [$id]);
    audit_log($uid, 'DELETE', 'proyectos', $id, $antes ?: []);

    json_response(['success'=>true,'message'=>'Proyecto eliminado.']);
}

// ── GET ONE ───────────────────────────────────────────────────
function getProyecto(): never {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    $row = db_fetch_one("SELECT * FROM vw_proyectos_completo WHERE id=?", [$id]);
    if (!$row) json_response(['success'=>false,'message'=>'Proyecto no encontrado.'], 404);

    json_response(['success'=>true,'data'=>$row]);
}

// ── LIST ──────────────────────────────────────────────────────
function listProyectos(): never {
    $where  = ['1=1'];
    $params = [];

    if (!empty($_GET['estado_id'])) {
        $where[]  = 'estado_id = ?';
        $params[] = (int)$_GET['estado_id'];
    }
    if (!empty($_GET['q'])) {
        $where[]  = '(nombre ILIKE ? OR cliente_nombre ILIKE ?)';
        $q        = '%' . trim($_GET['q']) . '%';
        $params   = array_merge($params, [$q, $q]);
    }

    $rows = db_fetch_all(
        'SELECT * FROM vw_proyectos_completo WHERE ' . implode(' AND ', $where)
        . ' ORDER BY created_at DESC LIMIT 200',
        $params
    );

    json_response(['success'=>true,'data'=>$rows,'total'=>count($rows)]);
}
// ── FINALIZAR PROYECTO → Puesta en Marcha ─────────────────────
function finalizarProyecto(int $uid): never {
    $id            = (int)($_POST['id'] ?? 0);
    $observaciones = trim($_POST['observaciones'] ?? '');

    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    $proyecto = db_fetch_one(
        "SELECT p.id, p.nombre, p.codigo, p.estado_id, p.responsable_id,
                c.razon_social AS cliente
         FROM proyectos p
         LEFT JOIN clientes c ON p.cliente_id = c.id
         WHERE p.id = ?",
        [$id]
    );

    if (!$proyecto) json_response(['success'=>false,'message'=>'Proyecto no encontrado.'], 404);
    if ($proyecto['estado_id'] == 4) {
        json_response(['success'=>false,'message'=>'El proyecto ya está completado.'], 422);
    }

    // 1) Marcar proyecto como Completado (estado_id=4) y registrar fecha real
    db_execute(
        "UPDATE proyectos
         SET estado_id=4, avance_porcentaje=100, fecha_fin_real=CURRENT_DATE, updated_at=NOW()
         WHERE id=?",
        [$id]
    );

    // 2) Crear incidencia de arranque en Puesta en Marcha
    $titulo_pm = 'Inicio Puesta en Marcha — ' . $proyecto['nombre'];
    $desc_pm   = 'Proyecto finalizado y en etapa de puesta en marcha. '
               . 'Registrar aquí incidencias y retroalimentación del cliente.'
               . ($observaciones ? "\n\nObservaciones de cierre: $observaciones" : '');

    $inc_id = db_insert(
        "INSERT INTO incidencias
           (proyecto_id, titulo, descripcion, tipo, severidad, estado,
            reportado_por, asignado_a, fecha_reporte)
         VALUES (?, ?, ?, 'Consulta', 'Baja', 'abierta', ?, ?, NOW())
         RETURNING id",
        [$id, $titulo_pm, $desc_pm, $uid, $proyecto['responsable_id'] ?? $uid]
    );

    audit_log($uid, 'UPDATE', 'proyectos', $id, [], [
        'accion'       => 'cierre_proyecto',
        'estado_nuevo' => 'Completado',
        'incidencia_pm'=> (int)$inc_id,
    ]);

    json_response([
        'success'     => true,
        'message'     => 'Proyecto cerrado y enviado a Puesta en Marcha.',
        'incidencia_id' => (int)$inc_id,
    ]);
}

// ── UPLOAD CONFORMIDAD ────────────────────────────────────────
function uploadConformidad(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    if (empty($_FILES['archivo']['tmp_name'])) {
        json_response(['success'=>false,'message'=>'No se recibió archivo.'], 422);
    }

    $file = $_FILES['archivo'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        json_response(['success'=>false,'message'=>'Solo se permite PDF.'], 422);
    }
    if ($file['size'] > 20 * 1024 * 1024) {
        json_response(['success'=>false,'message'=>'El archivo excede 20 MB.'], 422);
    }

    $uploadDir = __DIR__ . '/../uploads/conformidad/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    // Eliminar anterior si existe
    $anterior = db_fetch_one("SELECT conformidad_path FROM proyectos WHERE id=?", [$id]);
    if (!empty($anterior['conformidad_path'])) {
        $prev = __DIR__ . '/../' . $anterior['conformidad_path'];
        if (file_exists($prev)) unlink($prev);
    }

    $filename = 'conformidad_' . $id . '_' . uniqid() . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        json_response(['success'=>false,'message'=>'Error al guardar el archivo.'], 500);
    }

    $path = 'uploads/conformidad/' . $filename;
    db_execute(
        "UPDATE proyectos SET conformidad_path=?, conformidad_nombre=?, updated_at=NOW() WHERE id=?",
        [$path, $file['name'], $id]
    );

    audit_log($uid, 'UPDATE', 'proyectos', $id, [], ['conformidad' => $file['name']]);
    json_response(['success'=>true,'message'=>'Documento subido correctamente.','path'=>$path,'nombre'=>$file['name']]);
}

// ── DELETE CONFORMIDAD ────────────────────────────────────────
function deleteConformidad(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    $proy = db_fetch_one("SELECT conformidad_path, conformidad_nombre FROM proyectos WHERE id=?", [$id]);
    if (!empty($proy['conformidad_path'])) {
        $full = __DIR__ . '/../' . $proy['conformidad_path'];
        if (file_exists($full)) unlink($full);
    }

    db_execute("UPDATE proyectos SET conformidad_path=NULL, conformidad_nombre=NULL, updated_at=NOW() WHERE id=?", [$id]);
    audit_log($uid, 'UPDATE', 'proyectos', $id, [], ['conformidad' => 'eliminado']);
    json_response(['success'=>true,'message'=>'Documento eliminado.']);
}

// ── CALCULAR AVANCE (automático basado en tareas) ─────────────
function calcularAvance(): never {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);
    
    $stats = db_fetch_one(
        "SELECT 
            COUNT(*) AS total,
            COUNT(*) FILTER (WHERE estado = 'completada') AS completadas
         FROM tareas WHERE proyecto_id = ?",
        [$id]
    );
    
    $total = (int)$stats['total'];
    $completadas = (int)$stats['completadas'];
    
    $porcentaje = $total > 0 ? round(($completadas / $total) * 100) : 0;
    
    json_response([
        'success'     => true,
        'porcentaje'  => $porcentaje,
        'total'       => $total,
        'completadas' => $completadas
    ]);
}

?>