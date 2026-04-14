<?php
// ============================================================
//  api/pagos_api.php — API de Pagos de Proyectos
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session = require_auth();
$uid     = (int)$session['usuario_id'];

header('Content-Type: application/json; charset=utf-8');

$action = $_REQUEST['action'] ?? '';

try {
    match ($action) {
        'create'            => createPago($uid),
        'update'            => updatePago($uid),
        'delete'            => deletePago($uid),
        'marcar_pagado'     => marcarPagado($uid),
        'upload'            => uploadComprobante($uid),
        'list'              => listPagos(),
        'get_entregables'   => getEntregablesByProyecto(),
        default             => json_response(['success'=>false,'message'=>'Acción no válida.'], 400),
    };
} catch (Throwable $e) {
    error_log('[CORFIEM Pagos API] ' . $e->getMessage());
    json_response(['success'=>false,'message'=>'Error interno: ' . $e->getMessage()], 500);
}

// ── CREATE ────────────────────────────────────────────────────
function createPago(int $uid): never {
    $proyecto_id   = (int)($_POST['proyecto_id'] ?? 0);
    $concepto      = trim($_POST['concepto'] ?? '');
    $monto         = (float)($_POST['monto'] ?? 0);
    $fecha_venc    = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;
    $fecha_pago    = !empty($_POST['fecha_pago'])        ? $_POST['fecha_pago']        : null;
    $entregable_id = (int)($_POST['entregable_id'] ?? 0) ?: null;
    $metodo        = trim($_POST['metodo_pago'] ?? '') ?: null;
    $comprobante   = trim($_POST['numero_comprobante'] ?? '') ?: null;
    $notas         = trim($_POST['notas'] ?? '') ?: null;
    $estado        = $fecha_pago ? 'pagado' : 'pendiente';

    if ($proyecto_id <= 0 || empty($concepto)) {
        json_response(['success'=>false,'message'=>'Datos incompletos.'], 422);
    }
    if ($monto <= 0) {
        json_response(['success'=>false,'message'=>'El monto debe ser mayor a 0.'], 422);
    }

    $id = db_insert(
        "INSERT INTO pagos_proyecto
           (proyecto_id, entregable_id, concepto, monto, fecha_pago,
            fecha_vencimiento, estado, metodo_pago, numero_comprobante,
            notas, registrado_por)
         VALUES (?,?,?,?,?,?,?,?,?,?,?) RETURNING id",
        [$proyecto_id, $entregable_id, $concepto, $monto, $fecha_pago,
         $fecha_venc, $estado, $metodo, $comprobante, $notas, $uid]
    );

    audit_log($uid, 'CREATE', 'pagos_proyecto', (int)$id, [], [
        'concepto' => $concepto, 'monto' => $monto,
    ]);
    json_response(['success'=>true,'message'=>'Pago registrado correctamente.','id'=>(int)$id]);
}

// ── UPDATE ────────────────────────────────────────────────────
function updatePago(int $uid): never {
    $id            = (int)($_POST['id'] ?? 0);
    $concepto      = trim($_POST['concepto'] ?? '');
    $monto         = (float)($_POST['monto'] ?? 0);
    $fecha_venc    = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;
    $fecha_pago    = !empty($_POST['fecha_pago'])        ? $_POST['fecha_pago']        : null;
    $entregable_id = (int)($_POST['entregable_id'] ?? 0) ?: null;
    $metodo        = trim($_POST['metodo_pago'] ?? '') ?: null;
    $comprobante   = trim($_POST['numero_comprobante'] ?? '') ?: null;
    $notas         = trim($_POST['notas'] ?? '') ?: null;
    $estado        = $fecha_pago ? 'pagado' : 'pendiente';

    if ($id <= 0 || empty($concepto) || $monto <= 0) {
        json_response(['success'=>false,'message'=>'Datos incompletos.'], 422);
    }

    $antes = db_fetch_one("SELECT * FROM pagos_proyecto WHERE id=?", [$id]);
    if (!$antes) json_response(['success'=>false,'message'=>'Pago no encontrado.'], 404);

    db_execute(
        "UPDATE pagos_proyecto
         SET concepto=?, monto=?, fecha_pago=?, fecha_vencimiento=?,
             estado=?, metodo_pago=?, numero_comprobante=?, entregable_id=?,
             notas=?, updated_at=NOW()
         WHERE id=?",
        [$concepto, $monto, $fecha_pago, $fecha_venc,
         $estado, $metodo, $comprobante, $entregable_id, $notas, $id]
    );

    audit_log($uid, 'UPDATE', 'pagos_proyecto', $id, $antes, ['concepto'=>$concepto,'monto'=>$monto]);
    json_response(['success'=>true,'message'=>'Pago actualizado.']);
}

// ── DELETE ────────────────────────────────────────────────────
function deletePago(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    $pago = db_fetch_one("SELECT * FROM pagos_proyecto WHERE id=?", [$id]);
    if (!$pago) json_response(['success'=>false,'message'=>'Pago no encontrado.'], 404);

    // Eliminar archivo físico si existe
    if ($pago['archivo_path']) {
        $full = __DIR__ . '/../' . $pago['archivo_path'];
        if (file_exists($full)) unlink($full);
    }

    db_execute("DELETE FROM pagos_proyecto WHERE id=?", [$id]);
    audit_log($uid, 'DELETE', 'pagos_proyecto', $id, $pago, []);
    json_response(['success'=>true,'message'=>'Pago eliminado.']);
}

// ── MARCAR PAGADO ─────────────────────────────────────────────
function marcarPagado(int $uid): never {
    $id         = (int)($_POST['id'] ?? 0);
    $fecha_pago = !empty($_POST['fecha_pago']) ? $_POST['fecha_pago'] : date('Y-m-d');
    $metodo     = trim($_POST['metodo_pago'] ?? '') ?: null;

    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    db_execute(
        "UPDATE pagos_proyecto
         SET estado='pagado', fecha_pago=?, metodo_pago=COALESCE(?,metodo_pago), updated_at=NOW()
         WHERE id=?",
        [$fecha_pago, $metodo, $id]
    );

    audit_log($uid, 'UPDATE', 'pagos_proyecto', $id, [], ['estado'=>'pagado','fecha_pago'=>$fecha_pago]);
    json_response(['success'=>true,'message'=>'Pago marcado como pagado.']);
}

// ── UPLOAD COMPROBANTE ────────────────────────────────────────
function uploadComprobante(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    if (empty($_FILES['archivo']['tmp_name'])) {
        json_response(['success'=>false,'message'=>'No se recibió archivo.'], 422);
    }

    $file    = $_FILES['archivo'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf','jpg','jpeg','png'];

    if (!in_array($ext, $allowed)) {
        json_response(['success'=>false,'message'=>'Solo se permiten PDF e imágenes (JPG/PNG).'], 422);
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        json_response(['success'=>false,'message'=>'El archivo excede 10 MB.'], 422);
    }

    $uploadDir = __DIR__ . '/../uploads/pagos/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    // Eliminar comprobante anterior si existe
    $anterior = db_fetch_one("SELECT archivo_path FROM pagos_proyecto WHERE id=?", [$id]);
    if ($anterior['archivo_path']) {
        $prev = __DIR__ . '/../' . $anterior['archivo_path'];
        if (file_exists($prev)) unlink($prev);
    }

    $filename = 'pago_' . $id . '_' . uniqid() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        json_response(['success'=>false,'message'=>'Error al guardar el archivo.'], 500);
    }

    $path = 'uploads/pagos/' . $filename;
    db_execute(
        "UPDATE pagos_proyecto SET archivo_path=?, archivo_nombre=?, updated_at=NOW() WHERE id=?",
        [$path, $file['name'], $id]
    );

    audit_log($uid, 'UPDATE', 'pagos_proyecto', $id, [], ['comprobante'=>$file['name']]);
    json_response(['success'=>true,'message'=>'Comprobante subido.','path'=>$path]);
}

// ── GET ENTREGABLES POR PROYECTO (para cascade) ───────────────
function getEntregablesByProyecto(): never {
    $proyecto_id = (int)($_GET['proyecto_id'] ?? 0);
    if ($proyecto_id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    $entregables = db_fetch_all(
        "SELECT id, nombre, estado, porcentaje
         FROM entregables
         WHERE proyecto_id = ?
         ORDER BY orden, nombre",
        [$proyecto_id]
    );

    json_response(['success'=>true,'data'=>$entregables]);
}

// ── LIST ──────────────────────────────────────────────────────
function listPagos(): never {
    $proyecto_id = (int)($_GET['proyecto_id'] ?? 0);
    if ($proyecto_id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    // Auto-marcar vencidos
    db_execute(
        "UPDATE pagos_proyecto
         SET estado='vencido', updated_at=NOW()
         WHERE proyecto_id=? AND estado='pendiente'
           AND fecha_vencimiento IS NOT NULL
           AND fecha_vencimiento < CURRENT_DATE",
        [$proyecto_id]
    );

    $pagos = db_fetch_all(
        "SELECT pp.*, e.nombre AS entregable_nombre
         FROM pagos_proyecto pp
         LEFT JOIN entregables e ON e.id = pp.entregable_id
         WHERE pp.proyecto_id = ?
         ORDER BY
           CASE pp.estado WHEN 'vencido' THEN 1 WHEN 'pendiente' THEN 2 ELSE 3 END,
           pp.fecha_vencimiento ASC NULLS LAST, pp.created_at",
        [$proyecto_id]
    );

    $resumen = db_fetch_one(
        "SELECT
           COALESCE(SUM(monto), 0) AS total,
           COALESCE(SUM(monto) FILTER (WHERE estado='pagado'),   0) AS cobrado,
           COALESCE(SUM(monto) FILTER (WHERE estado='pendiente'),0) AS pendiente,
           COALESCE(SUM(monto) FILTER (WHERE estado='vencido'),  0) AS vencido,
           COUNT(*) FILTER (WHERE estado='vencido') AS num_vencidos
         FROM pagos_proyecto WHERE proyecto_id=?",
        [$proyecto_id]
    );

    json_response(['success'=>true,'data'=>$pagos,'resumen'=>$resumen]);
}
