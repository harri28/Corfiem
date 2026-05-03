-- ============================================================
--  CORFIEM ERP - SCHEMA COMPLETO (CLOUD READY)
--  Versión: 3.1
--
--  ADVERTENCIA: Elimina TODO y recrea desde cero.
--  Solo ejecutar en instalación nueva o reset completo.
--
--  ANTES DE EJECUTAR:
--    1. Generar hash bcrypt para la contraseña admin en:
--       http://localhost/Corfiem_Cesar/test/generar_hash.php
--    2. Reemplazar el valor INSERT del usuario admin al final.
-- ============================================================

BEGIN;

-- ============================================================
--  DROP (orden inverso a foreign keys)
-- ============================================================
DROP TABLE IF EXISTS respuestas_usuario       CASCADE;
DROP TABLE IF EXISTS intentos_cuestionario    CASCADE;
DROP TABLE IF EXISTS progreso_materiales      CASCADE;
DROP TABLE IF EXISTS inscripciones_curso      CASCADE;
DROP TABLE IF EXISTS opciones_respuesta       CASCADE;
DROP TABLE IF EXISTS preguntas                CASCADE;
DROP TABLE IF EXISTS cuestionarios            CASCADE;
DROP TABLE IF EXISTS materiales_curso         CASCADE;
DROP TABLE IF EXISTS cursos                   CASCADE;
DROP TABLE IF EXISTS auditoria_log            CASCADE;
DROP TABLE IF EXISTS sesiones                 CASCADE;
DROP TABLE IF EXISTS pagos_proyecto           CASCADE;
DROP TABLE IF EXISTS entregables_archivos     CASCADE;
DROP TABLE IF EXISTS entregables              CASCADE;
DROP TABLE IF EXISTS tareas                   CASCADE;
DROP TABLE IF EXISTS incidencias              CASCADE;
DROP TABLE IF EXISTS proyectos                CASCADE;
DROP TABLE IF EXISTS estados_proyecto         CASCADE;
DROP TABLE IF EXISTS actividades_previas      CASCADE;
DROP TABLE IF EXISTS cotizacion_items         CASCADE;
DROP TABLE IF EXISTS cotizaciones             CASCADE;
DROP TABLE IF EXISTS seguimiento_prospectos   CASCADE;
DROP TABLE IF EXISTS prospectos               CASCADE;
DROP TABLE IF EXISTS interacciones            CASCADE;
DROP TABLE IF EXISTS clientes                 CASCADE;
DROP TABLE IF EXISTS sectores                 CASCADE;
DROP TABLE IF EXISTS usuarios                 CASCADE;

DROP FUNCTION IF EXISTS trigger_recalcular_totales_cotizacion()  CASCADE;
DROP FUNCTION IF EXISTS trigger_actualizar_cotizacion_desde_items() CASCADE;
DROP FUNCTION IF EXISTS recalcular_totales_cotizacion()           CASCADE;
DROP FUNCTION IF EXISTS actualizar_avance_proyecto()              CASCADE;

-- ============================================================
--  1. USUARIOS
-- ============================================================
CREATE TABLE usuarios (
    id              SERIAL PRIMARY KEY,
    nombre          VARCHAR(100) NOT NULL,
    apellido        VARCHAR(100) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    rol             VARCHAR(50)  DEFAULT 'Usuario'
                    CHECK (rol IN ('Admin','Gerente','Usuario','Consultor')),
    telefono        VARCHAR(20),
    avatar_url      VARCHAR(500),
    avatar_initials VARCHAR(5),
    activo          BOOLEAN DEFAULT TRUE,
    ultimo_acceso   TIMESTAMP WITH TIME ZONE,
    created_at      TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at      TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_usuarios_email  ON usuarios(email);
CREATE INDEX idx_usuarios_activo ON usuarios(activo);
COMMENT ON TABLE usuarios IS 'Usuarios del sistema con autenticación';

-- ============================================================
--  2. SECTORES Y CLIENTES
-- ============================================================
CREATE TABLE sectores (
    id          SERIAL PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    created_at  TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE clientes (
    id                SERIAL PRIMARY KEY,
    razon_social      VARCHAR(255) NOT NULL,
    ruc_nit           VARCHAR(50),
    sector_id         INTEGER REFERENCES sectores(id) ON DELETE SET NULL,
    ciudad            VARCHAR(100),
    direccion         TEXT,
    telefono          VARCHAR(20),
    email_principal   VARCHAR(255),
    sitio_web         VARCHAR(255),
    contacto_nombre   VARCHAR(150),
    contacto_cargo    VARCHAR(100),
    contacto_email    VARCHAR(255),
    contacto_telefono VARCHAR(20),
    notas             TEXT,
    activo            BOOLEAN DEFAULT TRUE,
    created_at        TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at        TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_clientes_razon_social ON clientes(razon_social);
CREATE INDEX idx_clientes_sector       ON clientes(sector_id);
CREATE INDEX idx_clientes_activo       ON clientes(activo);

CREATE TABLE interacciones (
    id          SERIAL PRIMARY KEY,
    cliente_id  INTEGER NOT NULL REFERENCES clientes(id)  ON DELETE CASCADE,
    usuario_id  INTEGER NOT NULL REFERENCES usuarios(id)  ON DELETE SET NULL,
    tipo        VARCHAR(50) NOT NULL
                CHECK (tipo IN ('Llamada','Email','Reunión','Visita','WhatsApp','Nota')),
    asunto      VARCHAR(255),
    descripcion TEXT,
    fecha       TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    created_at  TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_interacciones_cliente ON interacciones(cliente_id);
CREATE INDEX idx_interacciones_fecha   ON interacciones(fecha DESC);

-- ============================================================
--  3. PROSPECTOS Y ACTIVIDADES PREVIAS
-- ============================================================
CREATE TABLE prospectos (
    id                   SERIAL PRIMARY KEY,
    codigo               VARCHAR(50) UNIQUE,
    nombre_contacto      VARCHAR(150) NOT NULL,
    empresa              VARCHAR(255),
    telefono             VARCHAR(20),
    email                VARCHAR(255),
    ruc                  VARCHAR(20),
    direccion            TEXT,
    tipo_servicio        TEXT,
    presupuesto_estimado NUMERIC(12,2),
    origen               VARCHAR(100),
    prioridad            VARCHAR(50) DEFAULT 'Media'
                         CHECK (prioridad IN ('Baja','Media','Alta','Urgente',
                                              'baja','media','alta','urgente')),
    estado               VARCHAR(50) DEFAULT 'nuevo'
                         CHECK (estado IN ('nuevo','contactado','en_evaluacion',
                                           'propuesta_enviada','negociacion',
                                           'aceptado','rechazado','archivado')),
    notas                TEXT,
    responsable_id       INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    cliente_id           INTEGER REFERENCES clientes(id) ON DELETE SET NULL,
    fecha_conversion     TIMESTAMP WITH TIME ZONE,
    fecha_contacto       DATE,
    created_by           INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    created_at           TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at           TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_prospectos_estado         ON prospectos(estado);
CREATE INDEX idx_prospectos_responsable    ON prospectos(responsable_id);
CREATE INDEX idx_prospectos_fecha_contacto ON prospectos(fecha_contacto);
CREATE INDEX idx_prospectos_created_by     ON prospectos(created_by);

COMMENT ON COLUMN prospectos.fecha_contacto IS 'Fecha del primer contacto con el prospecto';
COMMENT ON COLUMN prospectos.created_by     IS 'Usuario que creó el registro';

CREATE TABLE seguimiento_prospectos (
    id               SERIAL PRIMARY KEY,
    prospecto_id     INTEGER NOT NULL REFERENCES prospectos(id) ON DELETE CASCADE,
    usuario_id       INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    tipo             VARCHAR(50) NOT NULL
                     CHECK (tipo IN ('llamada','email','reunion','whatsapp',
                                     'visita','nota','cambio_estado')),
    titulo           VARCHAR(255),
    descripcion      TEXT,
    resultado        TEXT,
    proximo_contacto DATE,
    created_at       TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_seguimiento_prospecto ON seguimiento_prospectos(prospecto_id);
CREATE INDEX idx_seguimiento_usuario   ON seguimiento_prospectos(usuario_id);
CREATE INDEX idx_seguimiento_fecha     ON seguimiento_prospectos(created_at DESC);
COMMENT ON TABLE seguimiento_prospectos IS 'Historial de seguimiento y contactos con prospectos';

CREATE TABLE cotizaciones (
    id                SERIAL PRIMARY KEY,
    prospecto_id      INTEGER NOT NULL REFERENCES prospectos(id) ON DELETE CASCADE,
    numero            VARCHAR(50) UNIQUE,
    fecha_emision     DATE DEFAULT CURRENT_DATE,
    fecha_vencimiento DATE,
    validez_oferta    VARCHAR(100),
    tiempo_entrega    VARCHAR(100),
    condiciones_pago  TEXT,
    observaciones     TEXT,
    subtotal          NUMERIC(12,2) DEFAULT 0,
    descuento         NUMERIC(12,2) DEFAULT 0,
    igv               NUMERIC(12,2) DEFAULT 0,
    total             NUMERIC(12,2) DEFAULT 0,
    aplica_igv        BOOLEAN DEFAULT FALSE,
    estado            VARCHAR(50) DEFAULT 'borrador'
                      CHECK (estado IN ('borrador','enviada','aceptada','rechazada','vencida')),
    pdf_generado      BOOLEAN DEFAULT FALSE,
    pdf_ruta          VARCHAR(500),
    logo_empresa      VARCHAR(500),
    nombre_empresa    VARCHAR(255),
    ruc_empresa       VARCHAR(20),
    direccion_empresa TEXT,
    telefono_empresa  VARCHAR(50),
    email_empresa     VARCHAR(255),
    creada_por        INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    aprobada_por      INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    fecha_aprobacion  TIMESTAMP WITH TIME ZONE,
    created_at        TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at        TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_cotizaciones_prospecto    ON cotizaciones(prospecto_id);
CREATE INDEX idx_cotizaciones_creada_por   ON cotizaciones(creada_por);
CREATE INDEX idx_cotizaciones_aprobada_por ON cotizaciones(aprobada_por);

COMMENT ON COLUMN cotizaciones.aplica_igv   IS 'Indica si la cotización incluye IGV (18%)';
COMMENT ON COLUMN cotizaciones.creada_por   IS 'Usuario que creó la cotización';
COMMENT ON COLUMN cotizaciones.aprobada_por IS 'Usuario que aprobó la cotización';

CREATE TABLE cotizacion_items (
    id              SERIAL PRIMARY KEY,
    cotizacion_id   INTEGER NOT NULL REFERENCES cotizaciones(id) ON DELETE CASCADE,
    descripcion     TEXT NOT NULL,
    cantidad        NUMERIC(10,2) DEFAULT 1,
    unidad          VARCHAR(50) DEFAULT 'servicio',
    precio_unitario NUMERIC(12,2) NOT NULL,
    importe         NUMERIC(12,2) GENERATED ALWAYS AS (cantidad * precio_unitario) STORED,
    notas           TEXT,
    orden           SMALLINT DEFAULT 0,
    created_at      TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_cotizacion_items_cotizacion ON cotizacion_items(cotizacion_id);

CREATE TABLE actividades_previas (
    id                SERIAL PRIMARY KEY,
    prospecto_id      INTEGER NOT NULL REFERENCES prospectos(id) ON DELETE CASCADE,
    tipo              VARCHAR(100) NOT NULL
                      CHECK (tipo IN ('diagnostico','visita_campo','levantamiento_info',
                                      'reunion','propuesta_tecnica','otro')),
    titulo            VARCHAR(255) NOT NULL,
    descripcion       TEXT,
    objetivo          TEXT,
    responsable_id    INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    estado            VARCHAR(50) DEFAULT 'pendiente'
                      CHECK (estado IN ('pendiente','en_progreso','completada','cancelada')),
    fecha_programada  TIMESTAMP WITH TIME ZONE,
    fecha_realizada   TIMESTAMP WITH TIME ZONE,
    duracion_estimada VARCHAR(50),
    ubicacion         VARCHAR(255),
    participantes     TEXT,
    resultados        TEXT,
    created_at        TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at        TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_actividades_prospecto ON actividades_previas(prospecto_id);

-- ============================================================
--  4. PROYECTOS
-- ============================================================
CREATE TABLE estados_proyecto (
    id     SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    color  VARCHAR(20) DEFAULT '#3B82F6',
    orden  SMALLINT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE
);

CREATE TABLE proyectos (
    id                  SERIAL PRIMARY KEY,
    codigo              VARCHAR(50) UNIQUE,
    nombre              VARCHAR(255) NOT NULL,
    cliente_id          INTEGER NOT NULL REFERENCES clientes(id) ON DELETE RESTRICT,
    responsable_id      INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    estado_id           INTEGER REFERENCES estados_proyecto(id) ON DELETE SET NULL,
    presupuesto         NUMERIC(12,2),
    costo_real          NUMERIC(12,2) DEFAULT 0,
    prioridad           VARCHAR(50) DEFAULT 'Media'
                        CHECK (prioridad IN ('Baja','Media','Alta','Crítica')),
    fecha_inicio        DATE,
    fecha_fin_estimada  DATE,
    fecha_fin_real      DATE,
    alcance             TEXT,
    descripcion         TEXT,
    entregables         TEXT,
    avance_porcentaje   NUMERIC(5,2) DEFAULT 0
                        CHECK (avance_porcentaje >= 0 AND avance_porcentaje <= 100),
    pdf_path            VARCHAR(500),
    pdf_nombre_original VARCHAR(255),
    pdf_tamaño          INTEGER,
    extraido_por_ia     BOOLEAN DEFAULT FALSE,
    ia_confianza        NUMERIC(5,2),
    created_at          TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at          TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_proyectos_cliente     ON proyectos(cliente_id);
CREATE INDEX idx_proyectos_responsable ON proyectos(responsable_id);
CREATE INDEX idx_proyectos_estado      ON proyectos(estado_id);

COMMENT ON COLUMN proyectos.costo_real IS 'Costo real del proyecto (vs presupuesto estimado)';

CREATE TABLE entregables (
    id             SERIAL PRIMARY KEY,
    proyecto_id    INTEGER NOT NULL REFERENCES proyectos(id) ON DELETE CASCADE,
    nombre         VARCHAR(255) NOT NULL,
    descripcion    TEXT,
    porcentaje     NUMERIC(5,2) NOT NULL DEFAULT 0
                   CHECK (porcentaje >= 0 AND porcentaje <= 100),
    archivo_path   VARCHAR(500),
    archivo_nombre VARCHAR(255),
    estado         VARCHAR(50) DEFAULT 'pendiente'
                   CHECK (estado IN ('pendiente','en_progreso','completado')),
    fecha_entrega  DATE,
    fecha_inicio   DATE,
    fecha_fin      DATE,
    orden          SMALLINT DEFAULT 0,
    created_at     TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at     TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_entregables_proyecto ON entregables(proyecto_id);

CREATE TABLE entregables_archivos (
    id             SERIAL PRIMARY KEY,
    entregable_id  INTEGER NOT NULL REFERENCES entregables(id) ON DELETE CASCADE,
    archivo_path   VARCHAR(500) NOT NULL,
    archivo_nombre VARCHAR(255) NOT NULL,
    archivo_tipo   VARCHAR(50),
    archivo_tamano INTEGER,
    created_at     TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_entregables_archivos_entregable ON entregables_archivos(entregable_id);

CREATE TABLE tareas (
    id                SERIAL PRIMARY KEY,
    proyecto_id       INTEGER NOT NULL REFERENCES proyectos(id) ON DELETE CASCADE,
    titulo            VARCHAR(255) NOT NULL,
    descripcion       TEXT,
    responsable_id    INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    estado            VARCHAR(50) DEFAULT 'pendiente'
                      CHECK (estado IN ('pendiente','en_progreso','completada','cancelada')),
    prioridad         VARCHAR(50) DEFAULT 'Media'
                      CHECK (prioridad IN ('Baja','Media','Alta','Urgente')),
    fecha_inicio      DATE,
    fecha_vencimiento DATE,
    fecha_completada  DATE,
    orden             SMALLINT DEFAULT 0,
    created_at        TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at        TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_tareas_proyecto ON tareas(proyecto_id);

CREATE TABLE pagos_proyecto (
    id                 SERIAL PRIMARY KEY,
    proyecto_id        INTEGER NOT NULL REFERENCES proyectos(id) ON DELETE CASCADE,
    entregable_id      INTEGER REFERENCES entregables(id) ON DELETE SET NULL,
    concepto           VARCHAR(255) NOT NULL,
    monto              NUMERIC(12,2) NOT NULL,
    fecha_pago         DATE,
    fecha_vencimiento  DATE,
    estado             VARCHAR(50) DEFAULT 'pendiente'
                       CHECK (estado IN ('pendiente','pagado','vencido')),
    metodo_pago        VARCHAR(100),
    numero_comprobante VARCHAR(100),
    comprobante_path   VARCHAR(500),
    notas              TEXT,
    registrado_por     INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    created_at         TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at         TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_pagos_proyecto_id   ON pagos_proyecto(proyecto_id);
CREATE INDEX idx_pagos_entregable_id ON pagos_proyecto(entregable_id);
CREATE INDEX idx_pagos_estado        ON pagos_proyecto(estado);

-- ============================================================
--  5. INCIDENCIAS
-- ============================================================
CREATE TABLE incidencias (
    id               SERIAL PRIMARY KEY,
    proyecto_id      INTEGER NOT NULL REFERENCES proyectos(id) ON DELETE CASCADE,
    titulo           VARCHAR(255) NOT NULL,
    descripcion      TEXT,
    tipo             VARCHAR(50) CHECK (tipo IN ('Error','Mejora','Consulta','Otro')),
    severidad        VARCHAR(50) DEFAULT 'Media'
                     CHECK (severidad IN ('Baja','Media','Alta','Crítica')),
    estado           VARCHAR(50) DEFAULT 'abierta'
                     CHECK (estado IN ('abierta','en_proceso','resuelta','cerrada')),
    responsable_id   INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    reportado_por    INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    asignado_a       INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    fecha_reporte    TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    fecha_resolucion TIMESTAMP WITH TIME ZONE,
    solucion         TEXT,
    created_at       TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at       TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_incidencias_proyecto  ON incidencias(proyecto_id);
CREATE INDEX idx_incidencias_estado    ON incidencias(estado);
CREATE INDEX idx_incidencias_reportado ON incidencias(reportado_por);
CREATE INDEX idx_incidencias_asignado  ON incidencias(asignado_a);

-- ============================================================
--  6. CAPACITACIÓN
-- ============================================================
CREATE TABLE cursos (
    id                SERIAL PRIMARY KEY,
    titulo            VARCHAR(255) NOT NULL,
    descripcion       TEXT,
    objetivos         TEXT,
    duracion_horas    NUMERIC(5,2),
    nivel             VARCHAR(50) CHECK (nivel IN ('básico','intermedio','avanzado')),
    instructor        VARCHAR(255),
    instructor_id     INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    imagen_url        VARCHAR(500),
    modalidad         VARCHAR(30) DEFAULT 'Presencial'
                      CHECK (modalidad IN ('Presencial','Virtual','Híbrido')),
    max_participantes SMALLINT,
    fecha_inicio      TIMESTAMP WITH TIME ZONE,
    fecha_fin         TIMESTAMP WITH TIME ZONE,
    estado            VARCHAR(50) DEFAULT 'borrador'
                      CHECK (estado IN ('borrador','publicado','archivado')),
    orden             SMALLINT DEFAULT 0,
    created_at        TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at        TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_cursos_instructor ON cursos(instructor_id);
COMMENT ON COLUMN cursos.instructor_id IS 'Instructor del curso (referencia a usuarios)';

CREATE TABLE materiales_curso (
    id               SERIAL PRIMARY KEY,
    curso_id         INTEGER NOT NULL REFERENCES cursos(id) ON DELETE CASCADE,
    titulo           VARCHAR(255) NOT NULL,
    tipo             VARCHAR(50) NOT NULL CHECK (tipo IN ('video','pdf','link','texto')),
    contenido        TEXT,
    descripcion      TEXT,
    duracion_minutos INTEGER,
    orden            SMALLINT DEFAULT 0,
    obligatorio      BOOLEAN DEFAULT TRUE,
    created_at       TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE cuestionarios (
    id                        SERIAL PRIMARY KEY,
    curso_id                  INTEGER NOT NULL REFERENCES cursos(id) ON DELETE CASCADE,
    titulo                    VARCHAR(255) NOT NULL,
    descripcion               TEXT,
    tiempo_limite_minutos     INTEGER,
    intentos_permitidos       INTEGER DEFAULT 1,
    puntaje_minimo_aprobacion NUMERIC(5,2) DEFAULT 70.00,
    mostrar_respuestas        BOOLEAN DEFAULT FALSE,
    orden                     SMALLINT DEFAULT 0,
    activo                    BOOLEAN DEFAULT TRUE,
    created_at                TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at                TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE preguntas (
    id              SERIAL PRIMARY KEY,
    cuestionario_id INTEGER NOT NULL REFERENCES cuestionarios(id) ON DELETE CASCADE,
    texto           TEXT NOT NULL,
    tipo            VARCHAR(50) NOT NULL
                    CHECK (tipo IN ('multiple_choice','verdadero_falso','respuesta_corta')),
    puntos          NUMERIC(5,2) DEFAULT 1.00,
    orden           SMALLINT DEFAULT 0,
    explicacion     TEXT,
    created_at      TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE opciones_respuesta (
    id          SERIAL PRIMARY KEY,
    pregunta_id INTEGER NOT NULL REFERENCES preguntas(id) ON DELETE CASCADE,
    texto       TEXT NOT NULL,
    es_correcta BOOLEAN DEFAULT FALSE,
    orden       SMALLINT DEFAULT 0,
    created_at  TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE inscripciones_curso (
    id                  SERIAL PRIMARY KEY,
    curso_id            INTEGER NOT NULL REFERENCES cursos(id)    ON DELETE CASCADE,
    usuario_id          INTEGER NOT NULL REFERENCES usuarios(id)  ON DELETE CASCADE,
    fecha_inscripcion   TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    fecha_inicio        TIMESTAMP WITH TIME ZONE,
    fecha_finalizacion  TIMESTAMP WITH TIME ZONE,
    estado              VARCHAR(50) DEFAULT 'en_progreso'
                        CHECK (estado IN ('en_progreso','completado','abandonado')),
    progreso_porcentaje NUMERIC(5,2) DEFAULT 0.00,
    calificacion_final  NUMERIC(5,2),
    UNIQUE(curso_id, usuario_id)
);

CREATE TABLE progreso_materiales (
    id                      SERIAL PRIMARY KEY,
    inscripcion_id          INTEGER NOT NULL REFERENCES inscripciones_curso(id) ON DELETE CASCADE,
    material_id             INTEGER NOT NULL REFERENCES materiales_curso(id)    ON DELETE CASCADE,
    completado              BOOLEAN DEFAULT FALSE,
    fecha_completado        TIMESTAMP WITH TIME ZONE,
    tiempo_dedicado_minutos INTEGER DEFAULT 0,
    UNIQUE(inscripcion_id, material_id)
);

CREATE TABLE intentos_cuestionario (
    id                   SERIAL PRIMARY KEY,
    cuestionario_id      INTEGER NOT NULL REFERENCES cuestionarios(id) ON DELETE CASCADE,
    usuario_id           INTEGER NOT NULL REFERENCES usuarios(id)      ON DELETE CASCADE,
    fecha_inicio         TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    fecha_finalizacion   TIMESTAMP WITH TIME ZONE,
    puntaje_obtenido     NUMERIC(5,2),
    puntaje_maximo       NUMERIC(5,2),
    porcentaje           NUMERIC(5,2),
    aprobado             BOOLEAN,
    numero_intento       INTEGER DEFAULT 1,
    tiempo_total_minutos INTEGER
);

CREATE TABLE respuestas_usuario (
    id               SERIAL PRIMARY KEY,
    intento_id       INTEGER NOT NULL REFERENCES intentos_cuestionario(id) ON DELETE CASCADE,
    pregunta_id      INTEGER NOT NULL REFERENCES preguntas(id)              ON DELETE CASCADE,
    opcion_id        INTEGER REFERENCES opciones_respuesta(id)              ON DELETE SET NULL,
    respuesta_texto  TEXT,
    es_correcta      BOOLEAN,
    puntos_obtenidos NUMERIC(5,2) DEFAULT 0.00,
    created_at       TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_materiales_curso_id    ON materiales_curso(curso_id);
CREATE INDEX idx_cuestionarios_curso_id ON cuestionarios(curso_id);

-- ============================================================
--  7. AUDITORÍA
-- ============================================================
CREATE TABLE auditoria_log (
    id            SERIAL PRIMARY KEY,
    usuario_id    INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    accion        VARCHAR(50) NOT NULL
                  CHECK (accion IN ('CREATE','UPDATE','DELETE','LOGIN','LOGOUT')),
    modulo        VARCHAR(100) NOT NULL,
    registro_id   INTEGER,
    datos_antes   JSONB,
    datos_despues JSONB,
    ip_address    VARCHAR(50),
    user_agent    TEXT,
    created_at    TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_auditoria_usuario ON auditoria_log(usuario_id);
CREATE INDEX idx_auditoria_modulo  ON auditoria_log(modulo);
CREATE INDEX idx_auditoria_created ON auditoria_log(created_at DESC);

CREATE TABLE sesiones (
    session_id    VARCHAR(128) PRIMARY KEY,
    usuario_id    INTEGER REFERENCES usuarios(id) ON DELETE CASCADE,
    data          TEXT,
    ip_address    VARCHAR(50),
    user_agent    TEXT,
    last_activity TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_sesiones_usuario  ON sesiones(usuario_id);
CREATE INDEX idx_sesiones_activity ON sesiones(last_activity);
COMMENT ON TABLE sesiones IS 'Gestión de sesiones de usuario (opcional)';

-- ============================================================
--  TRIGGERS
-- ============================================================

-- Dispara cuando el usuario edita directamente descuento o aplica_igv
-- en una cotización, recalculando subtotal, IGV y total.
CREATE OR REPLACE FUNCTION trigger_recalcular_totales_cotizacion()
RETURNS TRIGGER AS $$
BEGIN
    NEW.subtotal := COALESCE((
        SELECT SUM(importe)
        FROM   cotizacion_items
        WHERE  cotizacion_id = NEW.id
    ), 0) - COALESCE(NEW.descuento, 0);

    NEW.igv   := CASE WHEN NEW.aplica_igv THEN NEW.subtotal * 0.18 ELSE 0 END;
    NEW.total := NEW.subtotal + NEW.igv;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_recalcular_totales
    BEFORE UPDATE OF descuento, aplica_igv ON cotizaciones
    FOR EACH ROW
    EXECUTE FUNCTION trigger_recalcular_totales_cotizacion();

COMMENT ON FUNCTION trigger_recalcular_totales_cotizacion() IS
    'Recalcula subtotal, IGV (si aplica) y total al cambiar descuento o aplica_igv';

-- Dispara cuando se insertan, modifican o eliminan ítems de una cotización,
-- recalculando directamente los totales en la cotización padre.
CREATE OR REPLACE FUNCTION trigger_actualizar_cotizacion_desde_items()
RETURNS TRIGGER AS $$
DECLARE
    v_cot_id    INTEGER;
    v_aplica    BOOLEAN;
    v_descuento NUMERIC(12,2);
    v_sub       NUMERIC(12,2);
    v_igv       NUMERIC(12,2);
BEGIN
    v_cot_id := COALESCE(NEW.cotizacion_id, OLD.cotizacion_id);

    SELECT aplica_igv, COALESCE(descuento, 0)
    INTO   v_aplica, v_descuento
    FROM   cotizaciones
    WHERE  id = v_cot_id;

    v_sub := COALESCE((
        SELECT SUM(importe)
        FROM   cotizacion_items
        WHERE  cotizacion_id = v_cot_id
    ), 0) - v_descuento;

    v_igv := CASE WHEN v_aplica THEN v_sub * 0.18 ELSE 0 END;

    UPDATE cotizaciones
    SET    subtotal   = v_sub,
           igv        = v_igv,
           total      = v_sub + v_igv,
           updated_at = NOW()
    WHERE  id = v_cot_id;

    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_items_actualizar_cotizacion
    AFTER INSERT OR UPDATE OR DELETE ON cotizacion_items
    FOR EACH ROW
    EXECUTE FUNCTION trigger_actualizar_cotizacion_desde_items();

COMMENT ON FUNCTION trigger_actualizar_cotizacion_desde_items() IS
    'Recalcula totales de la cotización cuando se modifican sus ítems';

-- Actualiza avance_porcentaje del proyecto según la suma de porcentajes
-- de sus entregables.
CREATE OR REPLACE FUNCTION actualizar_avance_proyecto()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE proyectos
    SET    avance_porcentaje = (
               SELECT COALESCE(SUM(porcentaje), 0)
               FROM   entregables
               WHERE  proyecto_id = COALESCE(NEW.proyecto_id, OLD.proyecto_id)
           )
    WHERE  id = COALESCE(NEW.proyecto_id, OLD.proyecto_id);
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_actualizar_avance
    AFTER INSERT OR UPDATE OR DELETE ON entregables
    FOR EACH ROW
    EXECUTE FUNCTION actualizar_avance_proyecto();

COMMENT ON FUNCTION actualizar_avance_proyecto() IS
    'Recalcula avance_porcentaje del proyecto al cambiar sus entregables';

-- ============================================================
--  VISTAS
-- ============================================================
CREATE VIEW vw_kpi_dashboard AS
SELECT
    (SELECT COUNT(*)
        FROM proyectos WHERE estado_id NOT IN (4,5,6))              AS proyectos_activos,
    (SELECT COUNT(*)
        FROM proyectos WHERE estado_id IN (4,5))                    AS proyectos_completados,
    (SELECT COUNT(*)
        FROM clientes  WHERE activo = TRUE)                         AS clientes_activos,
    (SELECT COUNT(*)
        FROM incidencias WHERE estado = 'abierta')                  AS incidencias_abiertas,
    (SELECT COALESCE(SUM(presupuesto), 0)
        FROM proyectos WHERE estado_id NOT IN (4,5,6))              AS presupuesto_total_activo,
    (SELECT COALESCE(ROUND(AVG(avance_porcentaje), 2), 0)
        FROM proyectos WHERE estado_id NOT IN (4,5,6))              AS avance_promedio;

CREATE VIEW vw_metricas_previas AS
SELECT
    COUNT(*) AS total_prospectos,
    COUNT(*) FILTER (WHERE estado = 'nuevo')             AS nuevos,
    COUNT(*) FILTER (WHERE estado = 'en_evaluacion')     AS en_evaluacion,
    COUNT(*) FILTER (WHERE estado = 'propuesta_enviada') AS propuestas_enviadas,
    COUNT(*) FILTER (WHERE estado = 'aceptado')          AS aceptados,
    COUNT(*) FILTER (WHERE estado = 'rechazado')         AS rechazados,
    (SELECT COUNT(*) FROM actividades_previas
        WHERE estado = 'pendiente')                      AS actividades_pendientes,
    (SELECT COUNT(*) FROM cotizaciones
        WHERE estado IN ('enviada','borrador'))           AS propuestas_activas,
    CASE
        WHEN COUNT(*) FILTER (WHERE estado IN ('nuevo','contactado','en_evaluacion')) > 0
        THEN ROUND(
            COUNT(*) FILTER (WHERE estado = 'aceptado')::NUMERIC /
            COUNT(*) FILTER (WHERE estado IN ('nuevo','contactado','en_evaluacion',
                                              'aceptado','rechazado'))::NUMERIC * 100,
            2)
        ELSE NULL
    END AS tasa_conversion
FROM prospectos;

CREATE VIEW vw_prospectos_resumen AS
SELECT
    p.*,
    u.nombre || ' ' || u.apellido AS responsable_nombre,
    (SELECT COUNT(*) FROM actividades_previas ap
        WHERE ap.prospecto_id = p.id)                              AS total_actividades,
    (SELECT COUNT(*) FROM actividades_previas ap
        WHERE ap.prospecto_id = p.id AND ap.estado = 'completada') AS actividades_completadas,
    (SELECT COUNT(*) FROM cotizaciones c
        WHERE c.prospecto_id = p.id)                               AS total_cotizaciones
FROM prospectos p
LEFT JOIN usuarios u ON p.responsable_id = u.id;

CREATE VIEW vw_proyectos_completo AS
SELECT
    p.*,
    c.razon_social                AS cliente_nombre,
    ep.nombre                     AS estado_nombre,
    ep.color                      AS estado_color,
    u.nombre || ' ' || u.apellido AS responsable_nombre,
    (SELECT COUNT(*) FROM tareas t
        WHERE t.proyecto_id = p.id)                              AS total_tareas,
    (SELECT COUNT(*) FROM tareas t
        WHERE t.proyecto_id = p.id AND t.estado = 'completada')  AS tareas_ok
FROM proyectos p
LEFT JOIN clientes        c  ON p.cliente_id      = c.id
LEFT JOIN estados_proyecto ep ON p.estado_id      = ep.id
LEFT JOIN usuarios         u  ON p.responsable_id = u.id;

-- ============================================================
--  DATOS INICIALES
-- ============================================================

INSERT INTO estados_proyecto (nombre, color, orden) VALUES
    ('Planificación', '#3B82F6', 1),
    ('En Desarrollo', '#F59E0B', 2),
    ('En Revisión',   '#8B5CF6', 3),
    ('Completado',    '#10B981', 4),
    ('Cancelado',     '#EF4444', 5),
    ('En Pausa',      '#6B7280', 6);

INSERT INTO sectores (nombre) VALUES
    ('Tecnología'),
    ('Construcción'),
    ('Salud'),
    ('Educación'),
    ('Manufactura'),
    ('Retail'),
    ('Servicios Profesionales'),
    ('Gobierno'),
    ('ONG'),
    ('Otro');

-- IMPORTANTE: Este hash corresponde a una contraseña de prueba.
-- Antes de desplegar, genera el hash correcto para 'Admin2025#' en:
--   http://localhost/Corfiem_Cesar/test/generar_hash.php
-- y reemplaza el valor password_hash a continuación.
INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, activo, avatar_initials)
VALUES (
    'Admin',
    'Sistema',
    'admin@corfiem.com',
    '$2y$12$REEMPLAZAR_CON_HASH_GENERADO_LOCALMENTE_PARA_Admin2025x',
    'Admin',
    TRUE,
    'AS'
);

COMMIT;
