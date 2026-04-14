<?php
require_once __DIR__ . '/../config/db.php';
$session = require_auth();
$uid = (int)$session['usuario_id'];

header('Content-Type: application/json; charset=utf-8');

$action = $_REQUEST['action'] ?? '';

try {
    match ($action) {
        'create'            => createEntregable($uid),
        'update'            => updateEntregable($uid),
        'delete'            => deleteEntregable($uid),
        'list'              => listEntregables(),
        'upload'            => uploadArchivo($uid),
        'delete_archivo'    => deleteArchivo($uid),
        'ampliar_fecha'     => ampliarFecha($uid),
        'marcar_completado' => marcarCompletado($uid),
        default             => json_response(['success'=>false,'message'=>'Acción no válida.'], 400),
    };
} catch (Throwable $e) {
    error_log('[CORFIEM Entregables API] ' . $e->getMessage());
    json_response(['success'=>false,'message'=>'Error interno del servidor.'], 500);
}

// ── CREATE ────────────────────────────────────────────────────
function createEntregable(int $uid): never {
    $proyecto_id = (int)($_POST['proyecto_id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $porcentaje  = (float)($_POST['porcentaje'] ?? 0);
    $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
    $fecha_fin    = !empty($_POST['fecha_fin'])    ? $_POST['fecha_fin']    : null;

    if ($proyecto_id <= 0 || empty($nombre)) {
        json_response(['success'=>false,'message'=>'Datos incompletos.'], 422);
    }

    if ($porcentaje < 0 || $porcentaje > 100) {
        json_response(['success'=>false,'message'=>'El porcentaje debe estar entre 0 y 100.'], 422);
    }

    $suma_actual = db_fetch_one(
        "SELECT COALESCE(SUM(porcentaje), 0) as total FROM entregables WHERE proyecto_id = ?",
        [$proyecto_id]
    )['total'];

    if (($suma_actual + $porcentaje) > 100) {
        json_response([
            'success'=>false,
            'message'=>"La suma de porcentajes no puede exceder 100%. Actualmente: {$suma_actual}%"
        ], 422);
    }

    $id = db_insert(
        "INSERT INTO entregables
           (proyecto_id, nombre, descripcion, porcentaje, fecha_inicio, fecha_fin, orden)
         VALUES (?, ?, ?, ?, ?, ?,
           (SELECT COALESCE(MAX(orden), 0) + 1 FROM entregables WHERE proyecto_id = ?))
         RETURNING id",
        [$proyecto_id, $nombre, $descripcion, $porcentaje, $fecha_inicio, $fecha_fin, $proyecto_id]
    );

    audit_log($uid, 'CREATE', 'entregables', (int)$id, [], ['nombre'=>$nombre]);
    json_response(['success'=>true,'message'=>'Entregable creado.','id'=>(int)$id]);
}

// ── UPDATE ────────────────────────────────────────────────────
function updateEntregable(int $uid): never {
    $id          = (int)($_POST['id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $porcentaje  = (float)($_POST['porcentaje'] ?? 0);
    $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
    $fecha_fin    = !empty($_POST['fecha_fin'])    ? $_POST['fecha_fin']    : null;

    if ($id <= 0 || empty($nombre)) {
        json_response(['success'=>false,'message'=>'Datos incompletos.'], 422);
    }

    $entregable = db_fetch_one("SELECT proyecto_id, porcentaje FROM entregables WHERE id = ?", [$id]);
    if (!$entregable) {
        json_response(['success'=>false,'message'=>'Entregable no encontrado.'], 404);
    }

    $suma_otros = db_fetch_one(
        "SELECT COALESCE(SUM(porcentaje), 0) as total FROM entregables
         WHERE proyecto_id = ? AND id != ?",
        [$entregable['proyecto_id'], $id]
    )['total'];

    if (($suma_otros + $porcentaje) > 100) {
        json_response([
            'success'=>false,
            'message'=>"La suma de porcentajes no puede exceder 100%. Otros entregables suman: {$suma_otros}%"
        ], 422);
    }

    db_execute(
        "UPDATE entregables
         SET nombre=?, descripcion=?, porcentaje=?, fecha_inicio=?, fecha_fin=?, updated_at=NOW()
         WHERE id=?",
        [$nombre, $descripcion, $porcentaje, $fecha_inicio, $fecha_fin, $id]
    );

    audit_log($uid, 'UPDATE', 'entregables', $id, [], ['nombre'=>$nombre]);
    json_response(['success'=>true,'message'=>'Entregable actualizado.']);
}

// ── DELETE ────────────────────────────────────────────────────
function deleteEntregable(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    // Eliminar archivos físicos
    $archivos = db_fetch_all(
        "SELECT archivo_path FROM entregables_archivos WHERE entregable_id = ?", [$id]
    );
    foreach ($archivos as $a) {
        $full = __DIR__ . '/../' . $a['archivo_path'];
        if (file_exists($full)) unlink($full);
    }

    db_execute("DELETE FROM entregables WHERE id=?", [$id]);
    audit_log($uid, 'DELETE', 'entregables', $id, []);
    json_response(['success'=>true,'message'=>'Entregable eliminado.']);
}

// ── LIST ──────────────────────────────────────────────────────
function listEntregables(): never {
    $proyecto_id = (int)($_GET['proyecto_id'] ?? 0);
    if ($proyecto_id <= 0) json_response(['success'=>false,'message'=>'ID de proyecto inválido.'], 422);

    $entregables = db_fetch_all(
        "SELECT * FROM entregables WHERE proyecto_id = ? ORDER BY orden, id",
        [$proyecto_id]
    );

    // Cargar archivos por entregable
    foreach ($entregables as &$ent) {
        $ent['archivos'] = db_fetch_all(
            "SELECT id, archivo_path, archivo_nombre, archivo_tipo, archivo_tamano
             FROM entregables_archivos WHERE entregable_id = ? ORDER BY created_at",
            [$ent['id']]
        );
    }
    unset($ent);

    $suma_total       = array_sum(array_column($entregables, 'porcentaje'));
    $suma_completados = array_sum(array_map(
        fn($e) => $e['estado'] === 'completado' ? $e['porcentaje'] : 0,
        $entregables
    ));

    json_response([
        'success'          => true,
        'data'             => $entregables,
        'suma_total'       => $suma_total,
        'suma_completados' => $suma_completados,
        'faltante'         => 100 - $suma_total
    ]);
}

// ── UPLOAD ARCHIVO ────────────────────────────────────────────
function uploadArchivo(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    if (empty($_FILES['archivo']['tmp_name'])) {
        json_response(['success'=>false,'message'=>'No se recibió ningún archivo.'], 422);
    }

    $file    = $_FILES['archivo'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf' => 'pdf', 'jpg' => 'imagen', 'jpeg' => 'imagen', 'png' => 'imagen',
                'doc' => 'word', 'docx' => 'word', 'zip' => 'zip'];

    if (!array_key_exists($ext, $allowed)) {
        json_response(['success'=>false,'message'=>'Solo se permiten PDF, imágenes (JPG/PNG), Word (DOC/DOCX) y ZIP.'], 422);
    }

    $maxSize = $ext === 'zip' ? 200 * 1024 * 1024 : 20 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        $limite = $ext === 'zip' ? '200 MB' : '20 MB';
        json_response(['success'=>false,'message'=>"El archivo excede $limite."], 422);
    }

    $filename  = 'entregable_' . $id . '_' . uniqid() . '.' . $ext;
    $uploadDir = __DIR__ . '/../uploads/entregables/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        json_response(['success'=>false,'message'=>'Error al guardar el archivo.'], 500);
    }

    $relativePath = 'uploads/entregables/' . $filename;
    $tipoArchivo  = $allowed[$ext];

    $archivo_id = db_insert(
        "INSERT INTO entregables_archivos (entregable_id, archivo_path, archivo_nombre, archivo_tipo, archivo_tamano)
         VALUES (?, ?, ?, ?, ?) RETURNING id",
        [$id, $relativePath, $file['name'], $tipoArchivo, $file['size']]
    );

    // Actualizar updated_at del entregable
    db_execute("UPDATE entregables SET updated_at=NOW() WHERE id=?", [$id]);

    audit_log($uid, 'UPDATE', 'entregables', $id, [], ['archivo'=>$file['name'], 'tipo'=>$tipoArchivo]);
    json_response([
        'success'  => true,
        'message'  => 'Archivo subido correctamente.',
        'archivo'  => [
            'id'             => (int)$archivo_id,
            'archivo_path'   => $relativePath,
            'archivo_nombre' => $file['name'],
            'archivo_tipo'   => $tipoArchivo,
            'archivo_tamano' => $file['size'],
        ]
    ]);
}

// ── DELETE ARCHIVO ────────────────────────────────────────────
function deleteArchivo(int $uid): never {
    $archivo_id = (int)($_POST['archivo_id'] ?? 0);
    if ($archivo_id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    $archivo = db_fetch_one(
        "SELECT ea.*, e.id AS eid FROM entregables_archivos ea
         JOIN entregables e ON e.id = ea.entregable_id
         WHERE ea.id = ?",
        [$archivo_id]
    );

    if (!$archivo) json_response(['success'=>false,'message'=>'Archivo no encontrado.'], 404);

    $full = __DIR__ . '/../' . $archivo['archivo_path'];
    if (file_exists($full)) unlink($full);

    db_execute("DELETE FROM entregables_archivos WHERE id=?", [$archivo_id]);
    audit_log($uid, 'DELETE', 'entregables_archivos', $archivo_id, [], ['archivo'=>$archivo['archivo_nombre']]);
    json_response(['success'=>true,'message'=>'Archivo eliminado.']);
}

// ── AMPLIAR FECHA ─────────────────────────────────────────────
function ampliarFecha(int $uid): never {
    $id             = (int)($_POST['id'] ?? 0);
    $nueva_fecha    = trim($_POST['nueva_fecha_fin'] ?? '');
    $justificacion  = trim($_POST['justificacion'] ?? '');

    if ($id <= 0 || empty($nueva_fecha)) {
        json_response(['success'=>false,'message'=>'Datos incompletos.'], 422);
    }

    if (empty($justificacion)) {
        json_response(['success'=>false,'message'=>'La justificación es obligatoria para ampliar la fecha.'], 422);
    }

    $entregable = db_fetch_one("SELECT id, fecha_fin, fecha_fin_original FROM entregables WHERE id=?", [$id]);
    if (!$entregable) json_response(['success'=>false,'message'=>'Entregable no encontrado.'], 404);

    // Guardar fecha original solo la primera vez
    $fecha_original = $entregable['fecha_fin_original'] ?? $entregable['fecha_fin'];

    db_execute(
        "UPDATE entregables
         SET fecha_fin=?, fecha_fin_original=?, justificacion_ampliacion=?, updated_at=NOW()
         WHERE id=?",
        [$nueva_fecha, $fecha_original, $justificacion, $id]
    );

    audit_log($uid, 'UPDATE', 'entregables', $id, [], [
        'accion'       => 'ampliacion_fecha',
        'fecha_nueva'  => $nueva_fecha,
        'justificacion'=> $justificacion,
    ]);
    json_response(['success'=>true,'message'=>'Fecha ampliada correctamente.']);
}

// ── MARCAR COMPLETADO ─────────────────────────────────────────
function marcarCompletado(int $uid): never {
    $id         = (int)($_POST['id'] ?? 0);
    $completado = (int)($_POST['completado'] ?? 0);

    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    $estado = $completado ? 'completado' : 'pendiente';

    db_execute(
        "UPDATE entregables SET estado=?, fecha_entrega=?, updated_at=NOW() WHERE id=?",
        [$estado, $completado ? date('Y-m-d') : null, $id]
    );

    audit_log($uid, 'UPDATE', 'entregables', $id, [], ['estado'=>$estado]);
    json_response(['success'=>true,'message'=>'Estado actualizado.']);
}
