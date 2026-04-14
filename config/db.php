<?php
// ============================================================
//  config/db.php — Conexión PDO a PostgreSQL + helpers
//  Ruta: C:\xampp\htdocs\Corfiem_Cesar\config\db.php
// ============================================================

// Cargar constantes globales
require_once __DIR__ . '/config.php';

// ============================================================
//  Clase de conexión — Patrón Singleton
// ============================================================
class Database {
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone()     {}

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s;options=--client_encoding=UTF8',
                    DB_HOST, DB_PORT, DB_NAME
                );
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_PERSISTENT         => false,
                ]);
            } catch (PDOException $e) {
                error_log('[CORFIEM DB ERROR] ' . $e->getMessage());
                http_response_code(503);
                die(json_encode([
                    'success' => false,
                    'message' => 'Error de conexión a la base de datos.'
                ]));
            }
        }
        return self::$instance;
    }

    public static function pdo(): PDO {
        return self::getInstance();
    }
}

// ============================================================
//  Helpers de base de datos
// ============================================================

/** Retorna todos los resultados de una query */
function db_fetch_all(string $sql, array $params = []): array {
    $stmt = Database::pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Retorna una sola fila */
function db_fetch_one(string $sql, array $params = []): array|false {
    $stmt = Database::pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

/** Ejecuta INSERT/UPDATE/DELETE, retorna true si tuvo éxito */
function db_execute(string $sql, array $params = []): bool {
    try {
        $pdo = Database::pdo(); // ← CORREGIDO: usar Database::pdo()
        $stmt = $pdo->prepare($sql);
        
        // Convertir booleanos de PHP a formato PostgreSQL
        foreach ($params as $i => $param) {
            if (is_bool($param)) {
                $stmt->bindValue($i + 1, $param, PDO::PARAM_BOOL);
            } else {
                $stmt->bindValue($i + 1, $param);
            }
        }
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log('[DB Execute] ' . $e->getMessage());
        throw $e;
    }
}

/** INSERT y retorna el ID generado */
function db_insert(string $sql, array $params = []): string {
    $stmt = Database::pdo()->prepare($sql);
    $stmt->execute($params);
    return Database::pdo()->lastInsertId();
}

// ============================================================
//  Helper: Log de auditoría
// ============================================================
function audit_log(int $uid, string $accion, string $modulo, ?int $registro_id = null, array $antes = [], array $despues = []): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    try {
        db_execute(
            "INSERT INTO auditoria_log
                (usuario_id, accion, modulo, registro_id, datos_antes, datos_despues, ip_address, user_agent)
             VALUES (?,?,?,?,?,?,?,?)",
            [
                $uid,
                $accion,
                $modulo,
                $registro_id,
                empty($antes)   ? null : json_encode($antes,   JSON_UNESCAPED_UNICODE),
                empty($despues) ? null : json_encode($despues, JSON_UNESCAPED_UNICODE),
                $ip,
                $ua,
            ]
        );
    } catch (Exception $e) {
        error_log('[Audit Log Error] ' . $e->getMessage());
        // No lanzar excepción para que no detenga el flujo principal
    }
}

// ============================================================
//  Helper: Respuesta JSON y fin de ejecución
// ============================================================
function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ============================================================
//  Helper: Sanitizar texto (prevenir XSS)
// ============================================================
function clean(string $str): string {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

// ============================================================
//  Helper: Verificar autenticación
// ============================================================
function require_auth(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
    if (empty($_SESSION['usuario_id'])) {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
    return $_SESSION;
}

// ============================================================
//  Crear directorios de uploads si no existen
// ============================================================
function init_upload_dirs(): void {
    foreach ([UPLOADS_PATH, PDF_PATH, DOCS_PATH] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

init_upload_dirs();