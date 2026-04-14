<?php
// ============================================================
//  api/incidencias_api.php — CRUD Incidencias
//  Ruta: C:\xampp\htdocs\Corfiem_Cesar\api\incidencias_api.php
//
//  POST action=create   → Crear incidencia
//  POST action=update   → Actualizar incidencia
//  POST action=resolver → Registrar resolución
//  POST action=delete   → Eliminar incidencia
//  GET  action=get&id=N → Obtener una incidencia
//  GET  action=list     → Listar incidencias (filtros)
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session = require_auth();
$uid     = (int)$session['usuario_id'];

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

try {
    match ([$method, $action]) {
        ['POST', 'create']  => createIncidencia($uid),
        ['POST', 'update']  => updateIncidencia($uid),
        ['POST', 'resolver']=> resolverIncidencia($uid),
        ['POST', 'delete']  => deleteIncidencia($uid),
        ['GET',  'get']     => getIncidencia(),
        ['GET',  'list']    => listIncidencias(),
        default             => json_response(['success'=>false,'message'=>'Acción no válida.'], 400),
    };
} catch (Throwable $e) {
    error_log('[CORFIEM Incidencias API] ' . $e->getMessage());
    json_response(['success'=>false,'message'=>'Error interno del servidor.'], 500);
}

// ── CREATE ────────────────────────────────────────────────────
function createIncidencia(int $uid): never {
    $titulo      = trim($_POST['titulo']       ?? '');
    $proyecto_id = (int)($_POST['proyecto_id'] ?? 0);
    $descripcion = trim($_POST['descripcion']  ?? '');
    $tipo        = $_POST['tipo']       ?? 'Error';
    $severidad   = $_POST['severidad']  ?? 'Media';
    $asignado_a  = (int)($_POST['asignado_a']  ?? 0) ?: null;

    if (empty($titulo))     json_response(['success'=>false,'message'=>'El título es requerido.'], 422);
    if ($proyecto_id <= 0)  json_response(['success'=>false,'message'=>'El proyecto es requerido.'], 422);
    if (empty($descripcion))json_response(['success'=>false,'message'=>'La descripción es requerida.'], 422);

    $tipos_validos = ['Error','Mejora','Consulta','Bloqueo'];
    $sevs_validas  = ['Baja','Media','Alta','Crítica'];
    if (!in_array($tipo,      $tipos_validos)) $tipo      = 'Error';
    if (!in_array($severidad, $sevs_validas))  $severidad = 'Media';

    $id = db_insert(
        "INSERT INTO incidencias
           (titulo, descripcion, tipo, severidad, estado,
            proyecto_id, reportado_por, asignado_a, fecha_reporte)
         VALUES (?,?,?,?,'abierta',?,?,?,NOW())
         RETURNING id",
        [$titulo, $descripcion, $tipo, $severidad,
         $proyecto_id, $uid, $asignado_a]
    );

    audit_log($uid, 'CREATE', 'incidencias', (int)$id, [], [
        'titulo'     => $titulo,
        'severidad'  => $severidad,
        'proyecto_id'=> $proyecto_id,
    ]);

    json_response(['success'=>true,'message'=>'Incidencia reportada exitosamente.','id'=>(int)$id]);
}

// ── UPDATE ────────────────────────────────────────────────────
function updateIncidencia(int $uid): never {
    $id          = (int)($_POST['id']          ?? 0);
    $titulo      = trim($_POST['titulo']       ?? '');
    $proyecto_id = (int)($_POST['proyecto_id'] ?? 0);
    $descripcion = trim($_POST['descripcion']  ?? '');
    $tipo        = $_POST['tipo']      ?? 'Error';
    $severidad   = $_POST['severidad'] ?? 'Media';
    $asignado_a  = (int)($_POST['asignado_a']  ?? 0) ?: null;
    $estado      = $_POST['estado']    ?? 'abierta';

    if ($id <= 0 || empty($titulo) || $proyecto_id <= 0) {
        json_response(['success'=>false,'message'=>'Datos incompletos.'], 422);
    }

    $estados_validos = ['abierta','en_proceso','resuelta','cerrada'];
    if (!in_array($estado, $estados_validos)) $estado = 'abierta';

    $antes = db_fetch_one("SELECT * FROM incidencias WHERE id=?", [$id]);

    db_execute(
        "UPDATE incidencias SET
           titulo=?, descripcion=?, tipo=?, severidad=?,
           estado=?, proyecto_id=?, asignado_a=?
         WHERE id=?",
        [$titulo, $descripcion, $tipo, $severidad,
         $estado, $proyecto_id, $asignado_a, $id]
    );

    audit_log($uid, 'UPDATE', 'incidencias', $id, $antes ?: [],
        ['estado' => $estado, 'severidad' => $severidad]);

    json_response(['success'=>true,'message'=>'Incidencia actualizada correctamente.']);
}

// ── RESOLVER ─────────────────────────────────────────────────
function resolverIncidencia(int $uid): never {
    $id       = (int)($_POST['id']      ?? 0);
    $estado   = $_POST['estado']        ?? 'resuelta';
    $solucion = trim($_POST['solucion'] ?? '') ?: null;

    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    $estados_validos = ['en_proceso','resuelta','cerrada'];
    if (!in_array($estado, $estados_validos)) $estado = 'resuelta';

    $fecha_resolucion = in_array($estado, ['resuelta','cerrada']) ? 'NOW()' : 'NULL';

    db_execute(
        "UPDATE incidencias SET
           estado=?, solucion=?,
           fecha_resolucion = CASE WHEN ? IN ('resuelta','cerrada') THEN NOW() ELSE NULL END
         WHERE id=?",
        [$estado, $solucion, $estado, $id]
    );

    audit_log($uid, 'UPDATE', 'incidencias', $id, [],
        ['estado' => $estado, 'solucion' => $solucion]);

    json_response(['success'=>true,'message'=>'Resolución registrada correctamente.']);
}

// ── DELETE ────────────────────────────────────────────────────
function deleteIncidencia(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    $antes = db_fetch_one("SELECT titulo FROM incidencias WHERE id=?", [$id]);
    db_execute("DELETE FROM incidencias WHERE id=?", [$id]);
    audit_log($uid, 'DELETE', 'incidencias', $id, $antes ?: []);

    json_response(['success'=>true,'message'=>'Incidencia eliminada.']);
}

// ── GET ONE ───────────────────────────────────────────────────
function getIncidencia(): never {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    $row = db_fetch_one(
        "SELECT i.*,
                p.nombre  AS proyecto,
                p.codigo  AS proyecto_codigo,
                ur.nombre || ' ' || ur.apellido AS reportado_por,
                ua.nombre || ' ' || ua.apellido AS asignado_a
         FROM incidencias i
         LEFT JOIN proyectos p  ON i.proyecto_id   = p.id
         LEFT JOIN usuarios  ur ON i.reportado_por = ur.id
         LEFT JOIN usuarios  ua ON i.asignado_a    = ua.id
         WHERE i.id = ?",
        [$id]
    );

    if (!$row) json_response(['success'=>false,'message'=>'Incidencia no encontrada.'], 404);
    json_response(['success'=>true,'data'=>$row]);
}

// ── LIST ──────────────────────────────────────────────────────
function listIncidencias(): never {
    $where  = ['1=1'];
    $params = [];

    if (!empty($_GET['proyecto_id'])) {
        $where[]  = 'i.proyecto_id = ?';
        $params[] = (int)$_GET['proyecto_id'];
    }
    if (!empty($_GET['estado'])) {
        $where[]  = 'i.estado = ?';
        $params[] = $_GET['estado'];
    }
    if (!empty($_GET['severidad'])) {
        $where[]  = 'i.severidad = ?';
        $params[] = $_GET['severidad'];
    }
    if (!empty($_GET['q'])) {
        $where[]  = 'i.titulo ILIKE ?';
        $params[] = '%' . trim($_GET['q']) . '%';
    }

    $rows = db_fetch_all(
        "SELECT i.id, i.titulo, i.tipo, i.severidad, i.estado,
                i.fecha_reporte, i.fecha_resolucion,
                p.nombre AS proyecto,
                ur.nombre || ' ' || ur.apellido AS reportado_por,
                ua.nombre || ' ' || ua.apellido AS asignado_a
         FROM incidencias i
         LEFT JOIN proyectos p  ON i.proyecto_id   = p.id
         LEFT JOIN usuarios  ur ON i.reportado_por = ur.id
         LEFT JOIN usuarios  ua ON i.asignado_a    = ua.id
         WHERE " . implode(' AND ', $where) .
        " ORDER BY
             CASE i.severidad WHEN 'Crítica' THEN 1 WHEN 'Alta' THEN 2
                              WHEN 'Media' THEN 3 ELSE 4 END,
             i.fecha_reporte DESC
         LIMIT 200",
        $params
    );

    json_response(['success'=>true,'data'=>$rows,'total'=>count($rows)]);
}