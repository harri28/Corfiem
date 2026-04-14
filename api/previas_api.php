<?php
// ============================================================
//  api/previas_api.php — API de Actividades Previas
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/generar_cotizacion_pdf.php';

$session = require_auth();
$uid     = (int)$session['usuario_id'];
$action  = $_REQUEST['action'] ?? '';

header('Content-Type: application/json; charset=utf-8');

try {
    match ($action) {
        // PROSPECTOS
        'create_prospecto'    => createProspecto($uid),
        'update_prospecto'    => updateProspecto($uid),
        'delete_prospecto'    => deleteProspecto($uid),
        'delete'              => deleteProspectos($uid), // ← NUEVO: eliminación individual y múltiple
        'get_prospecto'       => getProspecto(),
        'list_prospectos'     => listProspectos(),
        'convertir_prospecto' => convertirProspecto($uid),
        
        // ACTIVIDADES PREVIAS
        'create_actividad'    => createActividad($uid),
        'update_actividad'    => updateActividad($uid),
        'delete_actividad'    => deleteActividad($uid),
        'list_actividades'    => listActividades(),
        
        // PROPUESTAS TÉCNICAS
        'create_propuesta'    => createPropuesta($uid),
        'update_propuesta'    => updatePropuesta($uid),
        'delete_propuesta'    => deletePropuesta($uid),
        'get_propuesta'       => getPropuesta(),
        'enviar_propuesta'    => enviarPropuesta($uid),
        
        // COTIZACIONES
        'create_cotizacion'   => createCotizacion($uid),
        'update_cotizacion'   => updateCotizacion($uid),
        'delete_cotizacion'   => deleteCotizacion($uid),
        'get_cotizacion'      => getCotizacion(),
        'add_item'            => addItemCotizacion($uid),
        'update_item'         => updateItemCotizacion($uid),
        'delete_item'         => deleteItemCotizacion($uid),
        'enviar_cotizacion'   => enviarCotizacion($uid),
        'aceptar_cotizacion'  => aceptarCotizacion($uid),
        'rechazar_cotizacion' => rechazarCotizacion($uid),
        'generar_pdf'         => generarPDF($uid),
        
        // SEGUIMIENTO
        'add_seguimiento'     => addSeguimiento($uid),
        'list_seguimientos'   => listSeguimientos(),
        
        // MÉTRICAS
        'get_metricas'        => getMetricas(),

        // OBTENER DATOS PARA AUTO-RELLENAR PROYECTO
        'get_datos_proyecto' => getDatosProyecto(),
        
        default => json_response(['success'=>false,'message'=>'Acción no válida.'], 400),

        // ═════════════════════════════════════════════════════════════
        //  ELIMINAR PROSPECTO(S) - Individual o múltiple
        // ═════════════════════════════════════════════════════════════
    };
} catch (Throwable $e) {
    error_log('[CORFIEM Previas API] ' . $e->getMessage());
    json_response(['success'=>false,'message'=>'Error interno: ' . $e->getMessage()], 500);
}

// ═════════════════════════════════════════════════════════════
//  PROSPECTOS
// ═════════════════════════════════════════════════════════════

function createProspecto(int $uid): never {
    $nombre    = trim($_POST['nombre_contacto'] ?? '');
    $empresa   = trim($_POST['empresa'] ?? '') ?: null;
    $ruc       = trim($_POST['ruc'] ?? '') ?: null;
    $telefono  = trim($_POST['telefono'] ?? '') ?: null;
    $email     = trim($_POST['email'] ?? '') ?: null;
    $direccion = trim($_POST['direccion'] ?? '') ?: null;
    $tipo      = trim($_POST['tipo_servicio'] ?? '') ?: null;
    $origen    = $_POST['origen'] ?? 'Directo';
    $prioridad = $_POST['prioridad'] ?? 'media';
    $responsable = (int)($_POST['responsable_id'] ?? 0) ?: $uid;
    $notas     = trim($_POST['notas'] ?? '');
    
    if (empty($nombre)) {
        json_response(['success'=>false,'message'=>'El nombre del contacto es requerido.'], 422);
    }
    
    $id = db_insert(
        "INSERT INTO prospectos
           (nombre_contacto, empresa, ruc, telefono, email, direccion,
            tipo_servicio, origen, prioridad, responsable_id,
            fecha_contacto, notas, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id",
        [$nombre, $empresa, $ruc, $telefono, $email, $direccion,
         $tipo, $origen, $prioridad, $responsable,
         $_POST['fecha_contacto'] ?? date('Y-m-d'),
         $notas, $uid]
    );

    // Generar código único con el ID recién creado
    db_execute(
        "UPDATE prospectos SET codigo = 'PROS-' || LPAD(id::text, 4, '0') WHERE id = ?",
        [$id]
    );

    // Registrar en timeline
    db_execute(
        "INSERT INTO seguimiento_prospectos (prospecto_id, tipo, titulo, descripcion, usuario_id)
         VALUES (?,?,?,?,?)",
        [$id, 'nota', 'Prospecto creado', 'Prospecto registrado en el sistema', $uid]
    );
    
    audit_log($uid, 'CREATE', 'prospectos', (int)$id, [], ['nombre'=>$nombre]);
    json_response(['success'=>true,'message'=>'Prospecto creado exitosamente.','id'=>(int)$id]);
}

function updateProspecto(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);
    
    $antes = db_fetch_one("SELECT * FROM prospectos WHERE id=?", [$id]);
    
    db_execute(
        "UPDATE prospectos 
         SET nombre_contacto=?, empresa=?, ruc=?, telefono=?, email=?, direccion=?,
             tipo_servicio=?, origen=?, prioridad=?, responsable_id=?,
             fecha_contacto=?, estado=?, notas=?, updated_at=NOW()
         WHERE id=?",
        [
            $_POST['nombre_contacto'] ?? $antes['nombre_contacto'],
            $_POST['empresa'] ?? $antes['empresa'],
            $_POST['ruc'] ?? $antes['ruc'],
            $_POST['telefono'] ?? $antes['telefono'],
            $_POST['email'] ?? $antes['email'],
            $_POST['direccion'] ?? $antes['direccion'],
            $_POST['tipo_servicio'] ?? $antes['tipo_servicio'],
            $_POST['origen'] ?? $antes['origen'],
            $_POST['prioridad'] ?? $antes['prioridad'],
            $_POST['responsable_id'] ?? $antes['responsable_id'],
            $_POST['fecha_contacto'] ?? $antes['fecha_contacto'],
            $_POST['estado'] ?? $antes['estado'],
            $_POST['notas'] ?? $antes['notas'],
            $id
        ]
    );
    
    audit_log($uid, 'UPDATE', 'prospectos', $id, $antes, $_POST);
    json_response(['success'=>true,'message'=>'Prospecto actualizado.']);
}

function deleteProspecto(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    $antes = db_fetch_one("SELECT nombre_contacto FROM prospectos WHERE id=?", [$id]);
    db_execute("DELETE FROM prospectos WHERE id=?", [$id]);
    audit_log($uid, 'DELETE', 'prospectos', $id, $antes??[]);
    json_response(['success'=>true,'message'=>'Prospecto eliminado.']);
}

function getProspecto(): never {
    $id = (int)($_GET['id'] ?? 0);
    $prospecto = db_fetch_one("SELECT * FROM vw_prospectos_resumen WHERE id=?", [$id]);
    
    if (!$prospecto) {
        json_response(['success'=>false,'message'=>'Prospecto no encontrado.'], 404);
    }
    
    json_response(['success'=>true,'data'=>$prospecto]);
}

function listProspectos(): never {
    $where = ['1=1'];
    $params = [];
    
    if (!empty($_GET['estado'])) {
        $where[] = 'estado = ?';
        $params[] = $_GET['estado'];
    }
    if (!empty($_GET['responsable_id'])) {
        $where[] = 'responsable_id = ?';
        $params[] = (int)$_GET['responsable_id'];
    }
    if (!empty($_GET['q'])) {
        $where[] = '(nombre_contacto ILIKE ? OR empresa ILIKE ? OR email ILIKE ?)';
        $q = '%' . $_GET['q'] . '%';
        $params = array_merge($params, [$q, $q, $q]);
    }
    
    $prospectos = db_fetch_all(
        "SELECT * FROM vw_prospectos_resumen 
         WHERE " . implode(' AND ', $where) .
        " ORDER BY created_at DESC",
        $params
    );
    
    json_response(['success'=>true,'data'=>$prospectos]);
}

function convertirProspecto(int $uid): never {
    $id = (int)($_POST['prospecto_id'] ?? 0);
    
    if ($id <= 0) {
        json_response(['success'=>false,'message'=>'ID de prospecto inválido.'], 422);
    }
    
    try {
        $resultado = db_fetch_one(
            "SELECT * FROM convertir_prospecto_a_cliente(?, ?)",
            [$id, $uid]
        );
        
        json_response([
            'success' => true,
            'message' => 'Prospecto convertido exitosamente a cliente y proyecto.',
            'cliente_id' => (int)$resultado['cliente_id'],
            'proyecto_id' => (int)$resultado['proyecto_id']
        ]);
    } catch (Exception $e) {
        json_response(['success'=>false,'message'=>$e->getMessage()], 500);
    }
}

// ═════════════════════════════════════════════════════════════
//  ACTIVIDADES PREVIAS
// ═════════════════════════════════════════════════════════════

function createActividad(int $uid): never {
    $prospecto_id = (int)($_POST['prospecto_id'] ?? 0);
    $tipo = $_POST['tipo'] ?? 'otro';
    $titulo = trim($_POST['titulo'] ?? '');
    
    if ($prospecto_id <= 0 || empty($titulo)) {
        json_response(['success'=>false,'message'=>'Datos incompletos.'], 422);
    }
    
    $id = db_insert(
        "INSERT INTO actividades_previas 
           (prospecto_id, tipo, titulo, descripcion, objetivo, fecha_programada,
            duracion_estimada, ubicacion, responsable_id, participantes, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?) RETURNING id",
        [
            $prospecto_id, $tipo, $titulo,
            $_POST['descripcion'] ?? null,
            $_POST['objetivo'] ?? null,
            $_POST['fecha_programada'] ?? null,
            $_POST['duracion_estimada'] ?? null,
            $_POST['ubicacion'] ?? null,
            $_POST['responsable_id'] ?? $uid,
            $_POST['participantes'] ?? null,
            $uid
        ]
    );
    
    // Registrar en timeline
    db_execute(
        "INSERT INTO seguimiento_prospectos (prospecto_id, tipo, titulo, descripcion, usuario_id)
         VALUES (?,?,?,?,?)",
        [$prospecto_id, 'nota', 'Nueva actividad: ' . $titulo, 'Se programó: ' . $tipo, $uid]
    );
    
    json_response(['success'=>true,'message'=>'Actividad creada.','id'=>(int)$id]);
}

function updateActividad(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);
    
    $antes = db_fetch_one("SELECT * FROM actividades_previas WHERE id=?", [$id]);
    
    db_execute(
        "UPDATE actividades_previas 
         SET tipo=?, titulo=?, descripcion=?, objetivo=?, fecha_programada=?,
             fecha_realizada=?, duracion_estimada=?, ubicacion=?, responsable_id=?,
             participantes=?, estado=?, hallazgos=?, recomendaciones=?, updated_at=NOW()
         WHERE id=?",
        [
            $_POST['tipo'] ?? $antes['tipo'],
            $_POST['titulo'] ?? $antes['titulo'],
            $_POST['descripcion'] ?? $antes['descripcion'],
            $_POST['objetivo'] ?? $antes['objetivo'],
            $_POST['fecha_programada'] ?? $antes['fecha_programada'],
            $_POST['fecha_realizada'] ?? $antes['fecha_realizada'],
            $_POST['duracion_estimada'] ?? $antes['duracion_estimada'],
            $_POST['ubicacion'] ?? $antes['ubicacion'],
            $_POST['responsable_id'] ?? $antes['responsable_id'],
            $_POST['participantes'] ?? $antes['participantes'],
            $_POST['estado'] ?? $antes['estado'],
            $_POST['hallazgos'] ?? $antes['hallazgos'],
            $_POST['recomendaciones'] ?? $antes['recomendaciones'],
            $id
        ]
    );
    
    audit_log($uid, 'UPDATE', 'actividades_previas', $id, $antes, $_POST);
    json_response(['success'=>true,'message'=>'Actividad actualizada.']);
}

function deleteActividad(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    db_execute("DELETE FROM actividades_previas WHERE id=?", [$id]);
    json_response(['success'=>true,'message'=>'Actividad eliminada.']);
}

function listActividades(): never {
    $prospecto_id = (int)($_GET['prospecto_id'] ?? 0);
    
    $actividades = db_fetch_all(
        "SELECT a.*, u.nombre || ' ' || u.apellido AS responsable_nombre
         FROM actividades_previas a
         LEFT JOIN usuarios u ON a.responsable_id = u.id
         WHERE a.prospecto_id = ?
         ORDER BY a.fecha_programada DESC NULLS LAST, a.created_at DESC",
        [$prospecto_id]
    );
    
    json_response(['success'=>true,'data'=>$actividades]);
}

// ═════════════════════════════════════════════════════════════
//  PROPUESTAS TÉCNICAS
// ═════════════════════════════════════════════════════════════

function createPropuesta(int $uid): never {
    $prospecto_id = (int)($_POST['prospecto_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $alcance = trim($_POST['alcance'] ?? '');
    
    if ($prospecto_id <= 0 || empty($titulo) || empty($alcance)) {
        json_response(['success'=>false,'message'=>'Datos incompletos.'], 422);
    }
    
    $id = db_insert(
        "INSERT INTO propuestas_tecnicas 
           (prospecto_id, titulo, alcance, metodologia, entregables, cronograma,
            equipo_trabajo, presupuesto, plazo_ejecucion, condiciones,
            fecha_vencimiento, vigencia, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id",
        [
            $prospecto_id, $titulo, $alcance,
            $_POST['metodologia'] ?? null,
            $_POST['entregables'] ?? null,
            $_POST['cronograma'] ?? null,
            $_POST['equipo_trabajo'] ?? null,
            $_POST['presupuesto'] ?? null,
            $_POST['plazo_ejecucion'] ?? null,
            $_POST['condiciones'] ?? null,
            $_POST['fecha_vencimiento'] ?? date('Y-m-d', strtotime('+30 days')),
            $_POST['vigencia'] ?? '30 días',
            $uid
        ]
    );
    
    json_response(['success'=>true,'message'=>'Propuesta técnica creada.','id'=>(int)$id]);
}

function updatePropuesta(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);
    
    $antes = db_fetch_one("SELECT * FROM propuestas_tecnicas WHERE id=?", [$id]);
    
    db_execute(
        "UPDATE propuestas_tecnicas 
         SET titulo=?, alcance=?, metodologia=?, entregables=?, cronograma=?,
             equipo_trabajo=?, presupuesto=?, plazo_ejecucion=?, condiciones=?,
             fecha_vencimiento=?, vigencia=?, estado=?, updated_at=NOW()
         WHERE id=?",
        [
            $_POST['titulo'] ?? $antes['titulo'],
            $_POST['alcance'] ?? $antes['alcance'],
            $_POST['metodologia'] ?? $antes['metodologia'],
            $_POST['entregables'] ?? $antes['entregables'],
            $_POST['cronograma'] ?? $antes['cronograma'],
            $_POST['equipo_trabajo'] ?? $antes['equipo_trabajo'],
            $_POST['presupuesto'] ?? $antes['presupuesto'],
            $_POST['plazo_ejecucion'] ?? $antes['plazo_ejecucion'],
            $_POST['condiciones'] ?? $antes['condiciones'],
            $_POST['fecha_vencimiento'] ?? $antes['fecha_vencimiento'],
            $_POST['vigencia'] ?? $antes['vigencia'],
            $_POST['estado'] ?? $antes['estado'],
            $id
        ]
    );
    
    json_response(['success'=>true,'message'=>'Propuesta actualizada.']);
}

function deletePropuesta(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    db_execute("DELETE FROM propuestas_tecnicas WHERE id=?", [$id]);
    json_response(['success'=>true,'message'=>'Propuesta eliminada.']);
}

function getPropuesta(): never {
    $id = (int)($_GET['id'] ?? 0);
    $propuesta = db_fetch_one("SELECT * FROM propuestas_tecnicas WHERE id=?", [$id]);
    
    if (!$propuesta) {
        json_response(['success'=>false,'message'=>'Propuesta no encontrada.'], 404);
    }
    
    json_response(['success'=>true,'data'=>$propuesta]);
}

function enviarPropuesta(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    
    db_execute(
        "UPDATE propuestas_tecnicas SET estado='enviada', updated_at=NOW() WHERE id=?",
        [$id]
    );
    
    json_response(['success'=>true,'message'=>'Propuesta enviada.']);
}

// ═════════════════════════════════════════════════════════════
//  COTIZACIONES
// ═════════════════════════════════════════════════════════════

function createCotizacion(int $uid): never {
    $prospecto_id = (int)($_POST['prospecto_id'] ?? 0);
    
    if ($prospecto_id <= 0) {
        json_response(['success'=>false,'message'=>'ID de prospecto inválido.'], 422);
    }
    
    $id = db_insert(
        "INSERT INTO cotizaciones 
           (prospecto_id, fecha_emision, fecha_vencimiento, condiciones_pago,
            tiempo_entrega, validez_oferta, observaciones, creada_por)
         VALUES (?,?,?,?,?,?,?,?) RETURNING id",
        [
            $prospecto_id,
            $_POST['fecha_emision'] ?? date('Y-m-d'),
            $_POST['fecha_vencimiento'] ?? date('Y-m-d', strtotime('+30 days')),
            $_POST['condiciones_pago'] ?? null,
            $_POST['tiempo_entrega'] ?? null,
            $_POST['validez_oferta'] ?? '30 días',
            $_POST['observaciones'] ?? null,
            $uid
        ]
    );
    
    json_response(['success'=>true,'message'=>'Cotización creada.','id'=>(int)$id]);
}

function updateCotizacion(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);
    
    $antes = db_fetch_one("SELECT * FROM cotizaciones WHERE id=?", [$id]);
    
    // Construir campos a actualizar
    $updates = [];
    $params = [];
    
    if (isset($_POST['fecha_vencimiento'])) {
        $updates[] = 'fecha_vencimiento=?';
        $params[] = $_POST['fecha_vencimiento'];
    }
    if (isset($_POST['condiciones_pago'])) {
        $updates[] = 'condiciones_pago=?';
        $params[] = $_POST['condiciones_pago'];
    }
    if (isset($_POST['tiempo_entrega'])) {
        $updates[] = 'tiempo_entrega=?';
        $params[] = $_POST['tiempo_entrega'];
    }
    if (isset($_POST['validez_oferta'])) {
        $updates[] = 'validez_oferta=?';
        $params[] = $_POST['validez_oferta'];
    }
    if (isset($_POST['observaciones'])) {
        $updates[] = 'observaciones=?';
        $params[] = $_POST['observaciones'];
    }
    if (isset($_POST['descuento'])) {
        $updates[] = 'descuento=?';
        $params[] = (float)$_POST['descuento'];
    }
    if (isset($_POST['aplica_igv'])) {
        // PostgreSQL acepta 't', 'f', 'true', 'false', '1', '0'
        $updates[] = 'aplica_igv=?::boolean';
        $valor = $_POST['aplica_igv'];
        $params[] = ($valor === 'true' || $valor === '1') ? 'true' : 'false';
    }
    
    if (empty($updates)) {
        json_response(['success'=>false,'message'=>'No hay datos para actualizar.'], 422);
    }
    
    $updates[] = 'updated_at=NOW()';
    $params[] = $id;
    
    db_execute(
        "UPDATE cotizaciones SET " . implode(', ', $updates) . " WHERE id=?",
        $params
    );
    
    json_response(['success'=>true,'message'=>'Cotización actualizada.']);
}

function deleteCotizacion(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    db_execute("DELETE FROM cotizaciones WHERE id=?", [$id]);
    json_response(['success'=>true,'message'=>'Cotización eliminada.']);
}

function getCotizacion(): never {
    $id = (int)($_GET['id'] ?? 0);
    
    $cot = db_fetch_one(
        "SELECT c.*, p.nombre_contacto, p.empresa, p.ruc, p.telefono, p.email
         FROM cotizaciones c
         JOIN prospectos p ON c.prospecto_id = p.id
         WHERE c.id = ?",
        [$id]
    );
    
    if (!$cot) {
        json_response(['success'=>false,'message'=>'Cotización no encontrada.'], 404);
    }
    
    $items = db_fetch_all(
        "SELECT * FROM cotizacion_items WHERE cotizacion_id = ? ORDER BY orden, id",
        [$id]
    );
    
    json_response(['success'=>true,'data'=>['cotizacion'=>$cot,'items'=>$items]]);
}

function addItemCotizacion(int $uid): never {
    $cotizacion_id = (int)($_POST['cotizacion_id'] ?? 0);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = (float)($_POST['precio_unitario'] ?? 0);
    
    if ($cotizacion_id <= 0 || empty($descripcion) || $precio <= 0) {
        json_response(['success'=>false,'message'=>'Datos incompletos.'], 422);
    }
    
    db_execute(
        "INSERT INTO cotizacion_items 
           (cotizacion_id, descripcion, cantidad, unidad, precio_unitario, orden, notas)
         VALUES (?,?,?,?,?,?,?)",
        [
            $cotizacion_id, $descripcion,
            $_POST['cantidad'] ?? 1,
            $_POST['unidad'] ?? 'servicio',
            $precio,
            $_POST['orden'] ?? 0,
            $_POST['notas'] ?? null
        ]
    );
    
    json_response(['success'=>true,'message'=>'Ítem agregado.']);
}

function updateItemCotizacion(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);
    
    db_execute(
        "UPDATE cotizacion_items 
         SET descripcion=?, cantidad=?, unidad=?, precio_unitario=?, notas=?
         WHERE id=?",
        [
            $_POST['descripcion'],
            $_POST['cantidad'],
            $_POST['unidad'] ?? 'servicio',
            $_POST['precio_unitario'],
            $_POST['notas'] ?? null,
            $id
        ]
    );
    
    json_response(['success'=>true,'message'=>'Ítem actualizado.']);
}

function deleteItemCotizacion(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    db_execute("DELETE FROM cotizacion_items WHERE id=?", [$id]);
    json_response(['success'=>true,'message'=>'Ítem eliminado.']);
}

function enviarCotizacion(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    
    db_execute(
        "UPDATE cotizaciones SET estado='enviada', updated_at=NOW() WHERE id=?",
        [$id]
    );
    
    // Actualizar estado del prospecto
    $cot = db_fetch_one("SELECT prospecto_id FROM cotizaciones WHERE id=?", [$id]);
    db_execute(
        "UPDATE prospectos SET estado='propuesta_enviada', updated_at=NOW() WHERE id=?",
        [$cot['prospecto_id']]
    );
    
    json_response(['success'=>true,'message'=>'Cotización enviada.']);
}

function aceptarCotizacion(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    
    db_execute(
        "UPDATE cotizaciones SET estado='aceptada', aprobada_por=?, fecha_aprobacion=NOW() WHERE id=?",
        [$uid, $id]
    );
    
    // Actualizar prospecto
    $cot = db_fetch_one("SELECT prospecto_id FROM cotizaciones WHERE id=?", [$id]);
    db_execute(
        "UPDATE prospectos SET estado='aceptado', updated_at=NOW() WHERE id=?",
        [$cot['prospecto_id']]
    );
    
    json_response(['success'=>true,'message'=>'Cotización aceptada. Puede convertir el prospecto a cliente.']);
}

function rechazarCotizacion(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? '');
    
    db_execute(
        "UPDATE cotizaciones SET estado='rechazada', updated_at=NOW() WHERE id=?",
        [$id]
    );
    
    $cot = db_fetch_one("SELECT prospecto_id FROM cotizaciones WHERE id=?", [$id]);
    db_execute(
        "UPDATE prospectos SET estado='rechazado', notas=CONCAT(COALESCE(notas,''), '\nRechazo: ', ?) WHERE id=?",
        [$motivo, $cot['prospecto_id']]
    );
    
    json_response(['success'=>true,'message'=>'Cotización rechazada.']);
}

function generarPDF(int $uid): never {
    $id = (int)($_POST['cotizacion_id'] ?? 0);
    
    try {
        $resultado = generarCotizacionPDF($id);
        json_response($resultado);
    } catch (Exception $e) {
        json_response(['success'=>false,'message'=>'Error al generar PDF: ' . $e->getMessage()], 500);
    }




}

// ═════════════════════════════════════════════════════════════
//  SEGUIMIENTO
// ═════════════════════════════════════════════════════════════

function addSeguimiento(int $uid): never {
    $prospecto_id = (int)($_POST['prospecto_id'] ?? 0);
    $tipo = $_POST['tipo'] ?? 'nota';
    $titulo = trim($_POST['titulo'] ?? '');
    
    db_execute(
        "INSERT INTO seguimiento_prospectos 
           (prospecto_id, tipo, titulo, descripcion, resultado, usuario_id)
         VALUES (?,?,?,?,?,?)",
        [
            $prospecto_id, $tipo, $titulo,
            $_POST['descripcion'] ?? null,
            $_POST['resultado'] ?? null,
            $uid
        ]
    );
    
    json_response(['success'=>true,'message'=>'Seguimiento registrado.']);
}

function listSeguimientos(): never {
    $prospecto_id = (int)($_GET['prospecto_id'] ?? 0);
    
    $seguimientos = db_fetch_all(
        "SELECT s.*, u.nombre || ' ' || u.apellido AS usuario_nombre
         FROM seguimiento_prospectos s
         LEFT JOIN usuarios u ON s.usuario_id = u.id
         WHERE s.prospecto_id = ?
         ORDER BY s.fecha DESC",
        [$prospecto_id]
    );
    
    json_response(['success'=>true,'data'=>$seguimientos]);
}

// ═════════════════════════════════════════════════════════════
//  MÉTRICAS
// ═════════════════════════════════════════════════════════════

function getMetricas(): never {
    $metricas = db_fetch_one("SELECT * FROM vw_metricas_previas");
    json_response(['success'=>true,'data'=>$metricas]);
}





function getDatosProyecto(): never {
    $cliente_id = (int)($_GET['cliente_id'] ?? 0);
    
    if ($cliente_id <= 0) {
        json_response(['success'=>false,'message'=>'ID de cliente inválido.'], 422);
    }
    
    // Buscar prospecto asociado al cliente
    $prospecto = db_fetch_one(
        "SELECT p.*, 
                (SELECT SUM(c.total) FROM cotizaciones c 
                 WHERE c.prospecto_id = p.id AND c.estado = 'aceptada' 
                 LIMIT 1) AS presupuesto_cotizacion,
                (SELECT pt.alcance FROM propuestas_tecnicas pt 
                 WHERE pt.prospecto_id = p.id 
                 ORDER BY pt.created_at DESC LIMIT 1) AS alcance_propuesta
         FROM prospectos p
         WHERE p.cliente_id = ?
         LIMIT 1",
        [$cliente_id]
    );
    
    if (!$prospecto) {
        json_response(['success'=>false,'message'=>'Cliente sin datos de actividades previas.'], 404);
    }
    
    // Datos para auto-rellenar
    $datos = [
        'nombre_sugerido' => 'Proyecto: ' . ($prospecto['empresa'] ?: $prospecto['nombre_contacto']),
        'presupuesto' => $prospecto['presupuesto_cotizacion'] ?? null,
        'descripcion' => $prospecto['tipo_servicio'] ?? '',
        'alcance' => $prospecto['alcance_propuesta'] ?? '',
        'notas_prospecto' => $prospecto['notas'] ?? ''
    ];
    
    json_response(['success'=>true,'data'=>$datos]);
}

// ═════════════════════════════════════════════════════════════
//  ELIMINAR PROSPECTO(S) - Individual o múltiple
// ═════════════════════════════════════════════════════════════

function deleteProspectos(int $uid): never {
    // Soporta tanto 'id' individual como 'ids[]' múltiple
    $ids = $_POST['ids'] ?? ($_POST['id'] ?? null);
    
    if (empty($ids)) {
        json_response(['success' => false, 'message' => 'No se proporcionaron IDs'], 422);
    }
    
    // Convertir a array si es un solo ID
    if (!is_array($ids)) {
        $ids = [$ids];
    }
    
    $deleted = 0;
    $errors = [];
    
    foreach ($ids as $id) {
        $id = (int)$id;
        
        if ($id <= 0) {
            $errors[] = "ID inválido: $id";
            continue;
        }
        
        try {
            // Obtener datos del prospecto antes de eliminar (para auditoría)
            $prospecto = db_fetch_one(
                "SELECT * FROM prospectos WHERE id = ?",
                [$id]
            );
            
            if (!$prospecto) {
                $errors[] = "Prospecto $id no encontrado";
                continue;
            }
            
            // Eliminar el prospecto
            db_execute("DELETE FROM prospectos WHERE id = ?", [$id]);
            
            // Registrar en auditoría
            audit_log($uid, 'DELETE', 'previas', $id, $prospecto, []);
            
            $deleted++;
            
        } catch (Exception $e) {
            $errors[] = "Error al eliminar prospecto $id: " . $e->getMessage();
            error_log("[CORFIEM] Error eliminando prospecto $id: " . $e->getMessage());
        }
    }
    
    // Respuesta
    if ($deleted > 0) {
        $msg = $deleted === 1 
            ? 'Prospecto eliminado correctamente' 
            : "$deleted prospectos eliminados correctamente";
        
        if (!empty($errors)) {
            $msg .= " (con algunos errores: " . implode(', ', $errors) . ")";
        }
        
        json_response(['success' => true, 'message' => $msg, 'deleted' => $deleted]);
    } else {
        $errorMsg = !empty($errors) 
            ? implode(', ', $errors) 
            : 'No se pudo eliminar ningún prospecto';
        json_response(['success' => false, 'message' => $errorMsg], 500);
    }
}