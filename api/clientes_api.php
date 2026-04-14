<?php
// ============================================================
//  api/clientes_api.php — API de Clientes
// ============================================================
require_once __DIR__ . '/../config/db.php';

$session = require_auth();
$uid     = (int)$session['usuario_id'];
$action  = $_REQUEST['action'] ?? '';

header('Content-Type: application/json; charset=utf-8');

try {
    match ($action) {
        'buscar_prospectos'   => buscarProspectos(),
        'create'              => createCliente($uid),
        'update'              => updateCliente($uid),
        'delete'              => deleteCliente($uid),
        'create_interaccion'  => createInteraccion($uid),
        'get'                 => getCliente(),
        default               => json_response(['success'=>false,'message'=>'Acción no válida.'],400),
    };
} catch (Throwable $e) {
    error_log('[CORFIEM Clientes API] ' . $e->getMessage());
    json_response(['success'=>false,'message'=>'Error interno: ' . $e->getMessage()],500);
}

// ═════════════════════════════════════════════════════════════
//  BUSCAR PROSPECTOS PARA AUTOCOMPLETAR
// ═════════════════════════════════════════════════════════════
function buscarProspectos(): never {
    $q = trim($_GET['q'] ?? '');
    
    if (strlen($q) < 2) {
        json_response(['success'=>true,'data'=>[]]);
    }
    
    // Buscar prospectos que no estén ya convertidos a cliente
    $prospectos = db_fetch_all(
        "SELECT id, empresa, nombre_contacto, ruc, telefono, email, direccion,
                tipo_servicio, estado
         FROM prospectos
         WHERE estado NOT IN ('rechazado', 'archivado')
           AND cliente_id IS NULL
           AND (empresa ILIKE ? OR nombre_contacto ILIKE ? OR ruc ILIKE ?)
         ORDER BY
           CASE WHEN empresa ILIKE ? THEN 1 ELSE 2 END,
           empresa, nombre_contacto
         LIMIT 10",
        ["%$q%", "%$q%", "%$q%", "$q%"]
    );
    
    json_response(['success'=>true,'data'=>$prospectos]);
}

// ═════════════════════════════════════════════════════════════
//  CLIENTES
// ═════════════════════════════════════════════════════════════
function createCliente(int $uid): never {
    $razon = trim($_POST['razon_social'] ?? '');
    
    if (empty($razon)) {
        json_response(['success'=>false,'message'=>'La razón social es requerida.'], 422);
    }
    
    $prospecto_id = !empty($_POST['prospecto_id']) ? (int)$_POST['prospecto_id'] : null;
    
    $id = db_insert(
        "INSERT INTO clientes 
           (razon_social, ruc_nit, sector_id, ciudad, telefono, email_principal,
            contacto_nombre, contacto_cargo, contacto_email, contacto_telefono, notas)
         VALUES (?,?,?,?,?,?,?,?,?,?,?) RETURNING id",
        [
            $razon,
            $_POST['ruc_nit'] ?? null,
            !empty($_POST['sector_id']) ? (int)$_POST['sector_id'] : null,
            $_POST['ciudad'] ?? null,
            $_POST['telefono'] ?? null,
            $_POST['email_principal'] ?? null,
            $_POST['contacto_nombre'] ?? null,
            $_POST['contacto_cargo'] ?? null,
            $_POST['contacto_email'] ?? null,
            $_POST['contacto_telefono'] ?? null,
            $_POST['notas'] ?? null
        ]
    );
    
    // Si viene de un prospecto, vincular
    $nombre_proyecto = $razon; // por defecto usar razón social
    if ($prospecto_id) {
        db_execute(
            "UPDATE prospectos SET cliente_id = ?, updated_at = NOW() WHERE id = ?",
            [$id, $prospecto_id]
        );

        $prospecto = db_fetch_one(
            "SELECT empresa FROM prospectos WHERE id = ?",
            [$prospecto_id]
        );

        if ($prospecto && !empty($prospecto['empresa'])) {
            $nombre_proyecto = $prospecto['empresa'];
        }

        audit_log($uid, 'CONVERT', 'prospectos', $prospecto_id, [], ['cliente_id' => $id]);
    }

    // Crear proyecto automáticamente siempre (estado "Planificación" id=1)
    $proyecto_id = db_insert(
        "INSERT INTO proyectos (nombre, cliente_id, estado_id, created_at, updated_at)
         VALUES (?, ?, 1, NOW(), NOW()) RETURNING id",
        [$nombre_proyecto, $id]
    );
    audit_log($uid, 'CREATE', 'proyectos', (int)$proyecto_id, [], [
        'nombre'     => $nombre_proyecto,
        'cliente_id' => $id,
        'origen'     => $prospecto_id ? 'conversion_prospecto' : 'cliente_directo',
    ]);
    
    audit_log($uid, 'CREATE', 'clientes', (int)$id, [], ['razon_social' => $razon]);
    json_response(['success'=>true,'message'=>'Cliente creado exitosamente.','id'=>(int)$id]);
}

function updateCliente(int $uid): never {
    $id    = (int)($_POST['id'] ?? 0);
    $razon = trim($_POST['razon_social'] ?? '');
    
    if ($id <= 0 || empty($razon)) {
        json_response(['success'=>false,'message'=>'Datos incompletos.'],422);
    }

    $antes = db_fetch_one("SELECT * FROM clientes WHERE id=?",[$id]);
    
    db_execute(
        "UPDATE clientes SET razon_social=?,ruc_nit=?,sector_id=?,ciudad=?,
         telefono=?,email_principal=?,contacto_nombre=?,contacto_email=?,
         contacto_telefono=?,contacto_cargo=?,notas=?,updated_at=NOW() WHERE id=?",
        [
            $razon, 
            $_POST['ruc_nit']??null, 
            !empty($_POST['sector_id']) ? (int)$_POST['sector_id'] : null,
            $_POST['ciudad']??null, 
            $_POST['telefono']??null,
            $_POST['email_principal']??null, 
            $_POST['contacto_nombre']??null,
            $_POST['contacto_email']??null, 
            $_POST['contacto_telefono']??null,
            $_POST['contacto_cargo']??null, 
            $_POST['notas']??null, 
            $id
        ]
    );
    
    audit_log($uid,'UPDATE','clientes',$id,$antes??[],['razon_social'=>$razon]);
    json_response(['success'=>true,'message'=>'Cliente actualizado.']);
}

function deleteCliente(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    $cliente = db_fetch_one("SELECT razon_social FROM clientes WHERE id=?", [$id]);
    if (!$cliente) json_response(['success'=>false,'message'=>'Cliente no encontrado.'], 404);

    // Eliminar archivos físicos de entregables y pagos de proyectos del cliente
    $proyectos = db_fetch_all("SELECT id FROM proyectos WHERE cliente_id=?", [$id]);
    foreach ($proyectos as $p) {
        $archivos = db_fetch_all(
            "SELECT archivo_path FROM entregables_archivos ea
             JOIN entregables e ON ea.entregable_id = e.id
             WHERE e.proyecto_id = ?", [$p['id']]
        );
        foreach ($archivos as $a) {
            $full = __DIR__ . '/../' . $a['archivo_path'];
            if (file_exists($full)) unlink($full);
        }
        $pagos = db_fetch_all(
            "SELECT archivo_path FROM pagos_proyecto WHERE proyecto_id=? AND archivo_path IS NOT NULL", [$p['id']]
        );
        foreach ($pagos as $pa) {
            $full = __DIR__ . '/../' . $pa['archivo_path'];
            if (file_exists($full)) unlink($full);
        }
        // Conformidad
        $conf = db_fetch_one("SELECT conformidad_path FROM proyectos WHERE id=?", [$p['id']]);
        if (!empty($conf['conformidad_path'])) {
            $full = __DIR__ . '/../' . $conf['conformidad_path'];
            if (file_exists($full)) unlink($full);
        }
    }

    // Eliminar en cascada (FK ON DELETE CASCADE cubre el resto si está configurado,
    // pero lo hacemos explícito para garantizar)
    db_execute("DELETE FROM clientes WHERE id=?", [$id]);

    audit_log($uid, 'DELETE', 'clientes', $id, $cliente, []);
    json_response(['success'=>true,'message'=>'Cliente y sus proyectos eliminados correctamente.']);
}

// ═════════════════════════════════════════════════════════════
//  INTERACCIONES
// ═════════════════════════════════════════════════════════════
function createInteraccion(int $uid): never {
    $cid    = (int)($_POST['cliente_id'] ?? 0);
    $tipo   = trim($_POST['tipo'] ?? 'Nota');
    $asunto = trim($_POST['asunto'] ?? '') ?: null;
    $desc   = trim($_POST['descripcion'] ?? '');
    
    if ($cid <= 0) {
        json_response(['success'=>false,'message'=>'Cliente no válido.'],422);
    }

    db_execute(
        "INSERT INTO interacciones (cliente_id,tipo,asunto,descripcion,usuario_id) 
         VALUES (?,?,?,?,?)",
        [$cid,$tipo,$asunto,$desc,$uid]
    );
    
    json_response(['success'=>true,'message'=>'Interacción registrada.']);
}

// ═════════════════════════════════════════════════════════════
//  CONSULTAS
// ═════════════════════════════════════════════════════════════
function getCliente(): never {
    $id  = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        json_response(['success'=>false,'message'=>'ID inválido.'], 422);
    }
    
    $row = db_fetch_one(
        "SELECT c.*, s.nombre AS sector 
         FROM clientes c
         LEFT JOIN sectores s ON c.sector_id = s.id 
         WHERE c.id=?", 
        [$id]
    );
    
    if (!$row) {
        json_response(['success'=>false,'message'=>'Cliente no encontrado.'],404);
    }
    
    json_response(['success'=>true,'data'=>$row]);
}