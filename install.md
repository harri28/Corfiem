<?php
// ============================================================
//  logout.php — Cierre de sesión seguro
// ============================================================
require_once __DIR__ . '/config/db.php';

session_name(SESSION_NAME);
session_start();

// Log de auditoría antes de destruir sesión
if (!empty($_SESSION['usuario_id'])) {
    audit_log((int)$_SESSION['usuario_id'], 'LOGOUT', 'auth', (int)$_SESSION['usuario_id']);
}

// Destruir sesión completamente
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

header('Location: index.php?bye=1');
exit;


/*
================================================================
  INSTALL.md — GUÍA DE INSTALACIÓN ERP CORFIEM
  Copia esta sección en un archivo INSTALL.md en la raíz
================================================================

# ERP CORFIEM — Guía de Instalación
Entorno: Windows + XAMPP + PostgreSQL 15+ + PHP 8.1+

---

## 1. PRERREQUISITOS

- XAMPP con PHP 8.1+ y extensiones habilitadas:
    * pdo_pgsql
    * pgsql
    * curl
    * fileinfo
    * mbstring
- PostgreSQL 15 instalado y ejecutándose
- Composer (opcional, para futuras dependencias)

---

## 2. HABILITAR PDO_PGSQL EN XAMPP

Edita C:\xampp\php\php.ini y descomenta estas líneas:
  extension=pdo_pgsql
  extension=pgsql

Reinicia Apache desde el Panel de Control de XAMPP.

---

## 3. CREAR BASE DE DATOS EN POSTGRESQL

Opción A — pgAdmin:
  1. Abrir pgAdmin 4
  2. Crear nueva base de datos: corfiem_db
  3. Abrir Query Tool y ejecutar el contenido de schema.sql

Opción B — psql (consola):
  > psql -U postgres
  postgres=# CREATE DATABASE corfiem_db ENCODING 'UTF8';
  postgres=# \c corfiem_db
  corfiem_db=# \i C:/xampp/htdocs/Corfiem_Cesar/schema.sql

---

## 4. ESTRUCTURA DE CARPETAS

Crea manualmente si no existen:
este es mapa actualizado  de directorios
C:\xampp\htdocs\Corfiem_Cesar
│
│   dashboard.php
│   generar_hash.php          ← ELIMINAR después de usarlo
│   index.php
│   logout.php
│   schema.sql
│   INSTALL.md
│
├───api
│       bi_api.php
│       capacitacion_api.php
│       clientes_api.php
│       claude_proxy.php
│       incidencias_api.php
│       previas_api.php
│       proyectos_api.php
│       tareas_api.php        ← pendiente
│
├───assets
│   ├───css
│   │       style.css
│   │
│   └───js
│           main.js
│
├───config
│       db.php
│
├───includes
│       footer.php
│       header.php
│       sidebar.php
│
├───modules
│       auditoria.php         ← pendiente
│       bi.php
│       capacitacion.php
│       crm.php
│       marcha.php
│       previas.php
│       proyectos.php
│
└───uploads
    ├───documentos            ← se crea automáticamente
    └───proyectos             ← se crea automáticamente

---

## 5. CONFIGURAR API KEY DE CLAUDE

En config/db.php, busca esta línea y reemplaza con tu API key:

  define('CLAUDE_API_KEY', 'TU_API_KEY_AQUI');

Obtén tu API key en: https://console.anthropic.com

---

## 6. CREDENCIALES POR DEFECTO

  URL:        http://localhost/Corfiem_Cesar
  Email:      admin@corfiem.com
  Contraseña: Admin2025#
  http://localhost/Corfiem_Cesar/index.php

    Email:    felimon2025@gmail.com
    Contraseña: 12345678
 

⚠️  CAMBIA LA CONTRASEÑA INMEDIATAMENTE DESPUÉS DEL PRIMER LOGIN.

Para cambiar el hash en la BD:
  En PHP: echo password_hash('TuNuevaContraseña', PASSWORD_BCRYPT, ['cost'=>12]);
  Luego en psql:
    UPDATE usuarios SET password_hash = 'nuevo_hash' WHERE email = 'admin@corfiem.com';

---

## 7. PERMISOS DE CARPETA uploads/

En Windows/XAMPP normalmente no es necesario, pero verifica que
Apache tenga permisos de escritura en:
  C:\xampp\htdocs\Corfiem_Cesar\uploads\

---

## 8. VERIFICAR INSTALACIÓN

Abre en tu navegador:
  http://localhost/Corfiem_Cesar

Si ves la pantalla de login corporativa: ✅ Instalación correcta.
Si hay un error de conexión: verifica PostgreSQL esté activo y
los datos en config/db.php sean correctos.

---

## 9. CONFIGURAR CLAUDE API (Smart PDF Upload)

El sistema usa la API de Claude para extraer datos de PDFs.
El flujo es:
  1. Frontend (JS) extrae texto del PDF con PDF.js
  2. Frontend hace POST a /api/claude_proxy.php con el texto
  3. PHP hace la petición segura a api.anthropic.com
  4. Se devuelve JSON con datos extraídos
  5. El formulario se auto-rellena

Asegúrate que PHP tiene habilitado cURL y acceso a internet.
En producción, agrega rate limiting a claude_proxy.php.

---

## 10. PRÓXIMOS PASOS SUGERIDOS

  [ ] Cambiar contraseña del admin
  [ ] Agregar tus clientes reales en CRM
  [ ] Subir el primer proyecto PDF para probar la extracción IA
  [ ] Completar módulos: previas.php, marcha.php, auditoria.php
  [ ] Configurar backup automático de PostgreSQL
  [ ] Agregar HTTPS en producción (Let's Encrypt)

================================================================
*/