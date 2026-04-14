<?php
// ============================================================
//  api/bi_api.php
//  Endpoints de datos para el módulo de Reportes BI
//
//  GET  action=kpi_general       → KPIs principales del dashboard
//  GET  action=proyectos_estado  → Cantidad de proyectos por estado
//  GET  action=proyectos_mes     → Proyectos creados por mes (últimos 12)
//  GET  action=presupuesto_mes   → Presupuesto acumulado por mes
//  GET  action=top_clientes      → Clientes con más proyectos
//  GET  action=avance_proyectos  → Avance % de proyectos activos
//  GET  action=incidencias_tipo  → Incidencias agrupadas por tipo/severidad
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_auth();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

try {
    match ($action) {
        'kpi_general'      => kpiGeneral(),
        'proyectos_estado' => proyectosPorEstado(),
        'proyectos_mes'    => proyectosPorMes(),
        'presupuesto_mes'  => presupuestoPorMes(),
        'top_clientes'     => topClientes(),
        'avance_proyectos' => avanceProyectos(),
        'incidencias_tipo' => incidenciasPorTipo(),
        default            => json_response(['success'=>false,'message'=>'Acción no válida.'], 400),
    };
} catch (Throwable $e) {
    error_log('[CORFIEM BI API] ' . $e->getMessage());
    json_response(['success'=>false,'message'=>'Error interno del servidor.'], 500);
}

// ── KPIs generales ────────────────────────────────────────────
function kpiGeneral(): never {
    $data = db_fetch_one("SELECT * FROM vw_kpi_dashboard");

    // Tasa de éxito: proyectos completados / total proyectos
    $totales = db_fetch_one(
        "SELECT COUNT(*) AS total,
                COUNT(*) FILTER (WHERE estado_id = 4) AS completados,
                COUNT(*) FILTER (WHERE estado_id = 5) AS cancelados
         FROM proyectos"
    );

    $total     = (int)$totales['total'];
    $tasa_exito = $total > 0
        ? round(($totales['completados'] / $total) * 100, 1)
        : 0;

    // Promedio de duración real de proyectos completados (en días)
    $duracion = db_fetch_one(
        "SELECT ROUND(AVG(fecha_fin_real - fecha_inicio)) AS promedio_dias
         FROM proyectos
         WHERE estado_id = 4
           AND fecha_fin_real IS NOT NULL
           AND fecha_inicio IS NOT NULL"
    );

    json_response([
        'success' => true,
        'data'    => [
            'proyectos_activos'     => (int)($data['proyectos_activos']     ?? 0),
            'proyectos_completados' => (int)($data['proyectos_completados'] ?? 0),
            'clientes_activos'      => (int)($data['clientes_activos']      ?? 0),
            'incidencias_abiertas'  => (int)($data['incidencias_abiertas']  ?? 0),
            'presupuesto_activo'    => (float)($data['presupuesto_total_activo'] ?? 0),
            'avance_promedio'       => (float)($data['avance_promedio']     ?? 0),
            'tasa_exito'            => $tasa_exito,
            'duracion_promedio_dias'=> (int)($duracion['promedio_dias']     ?? 0),
            'total_proyectos'       => $total,
        ]
    ]);
}

// ── Proyectos por estado ──────────────────────────────────────
function proyectosPorEstado(): never {
    $rows = db_fetch_all(
        "SELECT ep.nombre AS estado, ep.color, COUNT(p.id) AS total
         FROM estados_proyecto ep
         LEFT JOIN proyectos p ON p.estado_id = ep.id
         GROUP BY ep.id, ep.nombre, ep.color
         ORDER BY ep.id"
    );

    json_response([
        'success' => true,
        'data'    => array_map(fn($r) => [
            'estado' => $r['estado'],
            'color'  => $r['color'],
            'total'  => (int)$r['total'],
        ], $rows)
    ]);
}

// ── Proyectos creados por mes (últimos 12 meses) ──────────────
function proyectosPorMes(): never {
    $rows = db_fetch_all(
        "SELECT TO_CHAR(DATE_TRUNC('month', created_at), 'Mon YYYY') AS mes,
                DATE_TRUNC('month', created_at) AS fecha_orden,
                COUNT(*) AS total
         FROM proyectos
         WHERE created_at >= NOW() - INTERVAL '12 months'
         GROUP BY DATE_TRUNC('month', created_at)
         ORDER BY fecha_orden ASC"
    );

    json_response([
        'success' => true,
        'data'    => array_map(fn($r) => [
            'mes'   => $r['mes'],
            'total' => (int)$r['total'],
        ], $rows)
    ]);
}

// ── Presupuesto acumulado por mes ─────────────────────────────
function presupuestoPorMes(): never {
    $rows = db_fetch_all(
        "SELECT TO_CHAR(DATE_TRUNC('month', created_at), 'Mon YYYY') AS mes,
                DATE_TRUNC('month', created_at) AS fecha_orden,
                COALESCE(SUM(presupuesto), 0) AS total_presupuesto,
                COALESCE(SUM(costo_real),  0) AS total_costo_real
         FROM proyectos
         WHERE created_at >= NOW() - INTERVAL '12 months'
         GROUP BY DATE_TRUNC('month', created_at)
         ORDER BY fecha_orden ASC"
    );

    json_response([
        'success' => true,
        'data'    => array_map(fn($r) => [
            'mes'              => $r['mes'],
            'presupuesto'      => (float)$r['total_presupuesto'],
            'costo_real'       => (float)$r['total_costo_real'],
        ], $rows)
    ]);
}

// ── Top 5 clientes con más proyectos ─────────────────────────
function topClientes(): never {
    $rows = db_fetch_all(
        "SELECT c.razon_social AS cliente,
                COUNT(p.id)   AS total_proyectos,
                COUNT(p.id) FILTER (WHERE p.estado_id = 4) AS completados,
                COALESCE(SUM(p.presupuesto), 0)            AS presupuesto_total
         FROM clientes c
         LEFT JOIN proyectos p ON p.cliente_id = c.id
         WHERE c.activo = TRUE
         GROUP BY c.id, c.razon_social
         ORDER BY total_proyectos DESC
         LIMIT 5"
    );

    json_response([
        'success' => true,
        'data'    => array_map(fn($r) => [
            'cliente'           => $r['cliente'],
            'total_proyectos'   => (int)$r['total_proyectos'],
            'completados'       => (int)$r['completados'],
            'presupuesto_total' => (float)$r['presupuesto_total'],
        ], $rows)
    ]);
}

// ── Avance de proyectos activos ───────────────────────────────
function avanceProyectos(): never {
    $rows = db_fetch_all(
        "SELECT p.nombre,
                p.avance_porcentaje,
                p.fecha_fin_estimada,
                c.razon_social AS cliente,
                ep.color       AS estado_color,
                ep.nombre      AS estado
         FROM proyectos p
         LEFT JOIN clientes c          ON p.cliente_id = c.id
         LEFT JOIN estados_proyecto ep ON p.estado_id  = ep.id
         WHERE p.estado_id NOT IN (4, 5, 6)   -- excluye completados/cancelados
         ORDER BY p.avance_porcentaje DESC
         LIMIT 10"
    );

    json_response([
        'success' => true,
        'data'    => array_map(fn($r) => [
            'nombre'          => $r['nombre'],
            'avance'          => (int)$r['avance_porcentaje'],
            'fecha_fin'       => $r['fecha_fin_estimada'],
            'cliente'         => $r['cliente'],
            'estado'          => $r['estado'],
            'estado_color'    => $r['estado_color'],
        ], $rows)
    ]);
}

// ── Incidencias agrupadas por tipo y severidad ────────────────
function incidenciasPorTipo(): never {
    $por_tipo = db_fetch_all(
        "SELECT tipo, COUNT(*) AS total
         FROM incidencias
         GROUP BY tipo
         ORDER BY total DESC"
    );

    $por_severidad = db_fetch_all(
        "SELECT severidad, COUNT(*) AS total,
                COUNT(*) FILTER (WHERE estado = 'resuelta') AS resueltas
         FROM incidencias
         GROUP BY severidad
         ORDER BY
             CASE severidad
                 WHEN 'Crítica' THEN 1 WHEN 'Alta'  THEN 2
                 WHEN 'Media'   THEN 3 WHEN 'Baja'  THEN 4
             END"
    );

    $por_estado = db_fetch_all(
        "SELECT estado, COUNT(*) AS total FROM incidencias GROUP BY estado"
    );

    json_response([
        'success' => true,
        'data'    => [
            'por_tipo'      => $por_tipo,
            'por_severidad' => $por_severidad,
            'por_estado'    => $por_estado,
        ]
    ]);
}