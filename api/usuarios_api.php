<?php
// ============================================================
//  api/usuarios_api.php — CRUD de usuarios
// ============================================================
require_once __DIR__ . '/../config/db.php';
$session = require_auth();
$uid     = (int)$session['usuario_id'];
$action  = $_REQUEST['action'] ?? '';

// Solo administradores
if ($session['usuario_rol'] !== 'Admin') {
    json_response(['success'=>false,'message'=>'Sin permisos de administrador.'], 403);
}

header('Content-Type: application/json; charset=utf-8');

try {
    match ($action) {
        'create'    => createUsuario($uid),
        'update'    => updateUsuario($uid),
        'toggle'    => toggleUsuario($uid),
        'delete'    => deleteUsuario($uid),
        'delete_cv' => deleteCv($uid),
        'get'       => getUsuario(),
        default     => json_response(['success'=>false,'message'=>'Acción no válida.'], 400),
    };
} catch (Throwable $e) {
    error_log('[CORFIEM Usuarios API] ' . $e->getMessage());
    json_response(['success'=>false,'message'=>'Error interno del servidor.'], 500);
}

// ── CREATE ────────────────────────────────────────────────────
function createUsuario(int $uid): never {
    $nombre      = trim($_POST['nombre']      ?? '');
    $apellido    = trim($_POST['apellido']    ?? '');
    $email       = trim($_POST['email']       ?? '');
    $password    = trim($_POST['password']    ?? '');
    $rol         = trim($_POST['rol']         ?? '');
    $dni         = trim($_POST['dni']         ?? '') ?: null;
    $telefono    = trim($_POST['telefono']    ?? '') ?: null;
    $cargo       = trim($_POST['cargo']       ?? '') ?: null;
    $especialidad= trim($_POST['especialidad']?? '') ?: null;

    $roles_validos = ['Admin','Gerente','Usuario','Consultor'];

    if (empty($nombre) || empty($apellido) || empty($email) || empty($password)) {
        json_response(['success'=>false,'message'=>'Nombre, apellido, email y contraseña son requeridos.'], 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['success'=>false,'message'=>'Email inválido.'], 422);
    }
    if (strlen($password) < 8) {
        json_response(['success'=>false,'message'=>'La contraseña debe tener al menos 8 caracteres.'], 422);
    }
    if (!in_array($rol, $roles_validos)) {
        json_response(['success'=>false,'message'=>'Rol no válido.'], 422);
    }

    $existe = db_fetch_one("SELECT id FROM usuarios WHERE email = ?", [$email]);
    if ($existe) {
        json_response(['success'=>false,'message'=>'El email ya está registrado.'], 422);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

    $id = db_insert(
        "INSERT INTO usuarios
           (nombre, apellido, email, password_hash, rol, dni, telefono, cargo, especialidad, activo)
         VALUES (?,?,?,?,?,?,?,?,?,TRUE) RETURNING id",
        [$nombre, $apellido, $email, $hash, $rol, $dni, $telefono, $cargo, $especialidad]
    );

    // Subir CV si se adjuntó
    if (!empty($_FILES['cv']['tmp_name'])) {
        _guardarCv((int)$id);
    }

    audit_log($uid, 'CREATE', 'usuarios', (int)$id, [], [
        'nombre' => "$nombre $apellido", 'email' => $email, 'rol' => $rol,
    ]);
    json_response(['success'=>true,'message'=>'Usuario creado exitosamente.','id'=>(int)$id]);
}

// ── UPDATE ────────────────────────────────────────────────────
function updateUsuario(int $uid): never {
    $id          = (int)($_POST['id']         ?? 0);
    $nombre      = trim($_POST['nombre']      ?? '');
    $apellido    = trim($_POST['apellido']    ?? '');
    $email       = trim($_POST['email']       ?? '');
    $rol         = trim($_POST['rol']         ?? '');
    $password    = trim($_POST['password']    ?? '');
    $dni         = trim($_POST['dni']         ?? '') ?: null;
    $telefono    = trim($_POST['telefono']    ?? '') ?: null;
    $cargo       = trim($_POST['cargo']       ?? '') ?: null;
    $especialidad= trim($_POST['especialidad']?? '') ?: null;

    $roles_validos = ['Admin','Gerente','Usuario','Consultor'];

    if ($id <= 0 || empty($nombre) || empty($apellido) || empty($email)) {
        json_response(['success'=>false,'message'=>'Datos incompletos.'], 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['success'=>false,'message'=>'Email inválido.'], 422);
    }
    if (!in_array($rol, $roles_validos)) {
        json_response(['success'=>false,'message'=>'Rol no válido.'], 422);
    }

    $antes = db_fetch_one("SELECT * FROM usuarios WHERE id=?", [$id]);
    if (!$antes) json_response(['success'=>false,'message'=>'Usuario no encontrado.'], 404);

    if (!empty($password)) {
        if (strlen($password) < 8) {
            json_response(['success'=>false,'message'=>'La contraseña debe tener al menos 8 caracteres.'], 422);
        }
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        db_execute(
            "UPDATE usuarios SET nombre=?, apellido=?, email=?, password_hash=?, rol=?,
             dni=?, telefono=?, cargo=?, especialidad=?, updated_at=NOW() WHERE id=?",
            [$nombre, $apellido, $email, $hash, $rol, $dni, $telefono, $cargo, $especialidad, $id]
        );
    } else {
        db_execute(
            "UPDATE usuarios SET nombre=?, apellido=?, email=?, rol=?,
             dni=?, telefono=?, cargo=?, especialidad=?, updated_at=NOW() WHERE id=?",
            [$nombre, $apellido, $email, $rol, $dni, $telefono, $cargo, $especialidad, $id]
        );
    }

    if (!empty($_FILES['cv']['tmp_name'])) {
        _guardarCv($id);
    }

    audit_log($uid, 'UPDATE', 'usuarios', $id, $antes, [
        'nombre' => "$nombre $apellido", 'email' => $email, 'rol' => $rol,
    ]);
    json_response(['success'=>true,'message'=>'Usuario actualizado correctamente.']);
}

// ── HELPER: guardar CV ────────────────────────────────────────
function _guardarCv(int $id): void {
    $file = $_FILES['cv'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') return;
    if ($file['size'] > 10 * 1024 * 1024) return;

    $dir = __DIR__ . '/../uploads/cv/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    // Eliminar CV anterior
    $anterior = db_fetch_one("SELECT cv_path FROM usuarios WHERE id=?", [$id]);
    if (!empty($anterior['cv_path'])) {
        $prev = __DIR__ . '/../' . $anterior['cv_path'];
        if (file_exists($prev)) unlink($prev);
    }

    $filename = 'cv_' . $id . '_' . uniqid() . '.pdf';
    if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        $path = 'uploads/cv/' . $filename;
        db_execute(
            "UPDATE usuarios SET cv_path=?, cv_nombre=?, updated_at=NOW() WHERE id=?",
            [$path, $file['name'], $id]
        );
    }
}

// ── DELETE CV ─────────────────────────────────────────────────
function deleteCv(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    $u = db_fetch_one("SELECT cv_path FROM usuarios WHERE id=?", [$id]);
    if (!empty($u['cv_path'])) {
        $full = __DIR__ . '/../' . $u['cv_path'];
        if (file_exists($full)) unlink($full);
    }
    db_execute("UPDATE usuarios SET cv_path=NULL, cv_nombre=NULL, updated_at=NOW() WHERE id=?", [$id]);
    audit_log($uid, 'UPDATE', 'usuarios', $id, [], ['cv' => 'eliminado']);
    json_response(['success'=>true,'message'=>'CV eliminado.']);
}

// ── TOGGLE ESTADO ─────────────────────────────────────────────
function toggleUsuario(int $uid): never {
    $id     = (int)($_POST['id']     ?? 0);
    $activo = $_POST['activo'] === '1';

    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);
    if ($id === $uid) json_response(['success'=>false,'message'=>'No puedes desactivar tu propia cuenta.'], 422);

    $antes = db_fetch_one("SELECT activo FROM usuarios WHERE id=?", [$id]);
    db_execute("UPDATE usuarios SET activo=?, updated_at=NOW() WHERE id=?", [$activo ? 'TRUE' : 'FALSE', $id]);
    audit_log($uid, 'UPDATE', 'usuarios', $id, $antes ?? [], ['activo' => $activo]);

    json_response(['success'=>true,'message'=>$activo ? 'Usuario activado.' : 'Usuario desactivado.']);
}

// ── DELETE ────────────────────────────────────────────────────
function deleteUsuario(int $uid): never {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);
    if ($id === $uid) json_response(['success'=>false,'message'=>'No puedes eliminar tu propia cuenta.'], 422);

    $usuario = db_fetch_one("SELECT nombre, apellido, cv_path FROM usuarios WHERE id=?", [$id]);
    if (!$usuario) json_response(['success'=>false,'message'=>'Usuario no encontrado.'], 404);

    if (!empty($usuario['cv_path'])) {
        $full = __DIR__ . '/../' . $usuario['cv_path'];
        if (file_exists($full)) unlink($full);
    }

    db_execute("DELETE FROM usuarios WHERE id=?", [$id]);
    audit_log($uid, 'DELETE', 'usuarios', $id, $usuario, []);
    json_response(['success'=>true,'message'=>'Usuario eliminado correctamente.']);
}

// ── GET ONE ───────────────────────────────────────────────────
function getUsuario(): never {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_response(['success'=>false,'message'=>'ID inválido.'], 422);

    $row = db_fetch_one(
        "SELECT id, nombre, apellido, email, rol, dni, telefono, cargo, especialidad,
                cv_path, cv_nombre, activo
         FROM usuarios WHERE id=?",
        [$id]
    );
    if (!$row) json_response(['success'=>false,'message'=>'Usuario no encontrado.'], 404);

    json_response(['success'=>true,'data'=>$row]);
}
