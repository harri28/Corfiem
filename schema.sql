-- ============================================================
--  CORFIEM ERP - SCHEMA COMPLETO
--  Versión: 3.0 - Actualizada
--  Fecha: 2025-03-15
--  
--  ADVERTENCIA: Este script ELIMINA todo y lo recrea desde cero
--  Solo ejecutar en instalación nueva o para reset completo
-- ============================================================

-- Eliminar TODO lo existente (CUIDADO: esto borra todo)
DROP SCHEMA public CASCADE;
CREATE SCHEMA public;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO public;

-- Extensiones
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "unaccent";

-- ============================================================
--  1. USUARIOS
-- ============================================================

CREATE TABLE usuarios (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol VARCHAR(50) DEFAULT 'Usuario' CHECK (rol IN ('Admin', 'Gerente', 'Usuario', 'Consultor')),
    telefono VARCHAR(20),
    avatar_url VARCHAR(500),
    avatar_initials VARCHAR(5),
    activo BOOLEAN DEFAULT TRUE,
    ultimo_acceso TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_usuarios_email ON usuarios(email);
CREATE INDEX idx_usuarios_activo ON usuarios(activo);
COMMENT ON TABLE usuarios IS 'Usuarios del sistema con autenticación';

-- ============================================================
--  2. SECTORES Y CLIENTES
-- ============================================================

CREATE TABLE sectores (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE clientes (
    id SERIAL PRIMARY KEY,
    razon_social VARCHAR(255) NOT NULL,
    ruc_nit VARCHAR(50),
    sector_id INTEGER REFERENCES sectores(id) ON DELETE SET NULL,
    ciudad VARCHAR(100),
    direccion TEXT,
    telefono VARCHAR(20),
    email_principal VARCHAR(255),
    sitio_web VARCHAR(255),
    contacto_nombre VARCHAR(150),
    contacto_cargo VARCHAR(100),
    contacto_email VARCHAR(255),
    contacto_telefono VARCHAR(20),
    notas TEXT,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_clientes_razon_social ON clientes(razon_social);
CREATE INDEX idx_clientes_sector ON clientes(sector_id);
CREATE INDEX idx_clientes_activo ON clientes(activo);

CREATE TABLE interacciones (
    id SERIAL PRIMARY KEY,
    cliente_id INTEGER NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE SET NULL,
    tipo VARCHAR(50) NOT NULL CHECK (tipo IN ('Llamada', 'Email', 'Reunión', 'Visita', 'WhatsApp', 'Nota')),
    asunto VARCHAR(255),
    descripcion TEXT,
    fecha TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_interacciones_cliente ON interacciones(cliente_id);
CREATE INDEX idx_interacciones_fecha ON interacciones(fecha DESC);

-- ============================================================
--  3. PROSPECTOS Y ACTIVIDADES PREVIAS
-- ============================================================

CREATE TABLE prospectos (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE,
    nombre_contacto VARCHAR(150) NOT NULL,
    empresa VARCHAR(255),
    telefono VARCHAR(20),
    email VARCHAR(255),
    ruc VARCHAR(20),
    direccion TEXT,
    tipo_servicio TEXT,
    presupuesto_estimado NUMERIC(12,2),
    origen VARCHAR(100),
    prioridad VARCHAR(50) DEFAULT 'Media' CHECK (prioridad IN ('Baja', 'Media', 'Alta', 'Urgente')),
    estado VARCHAR(50) DEFAULT 'nuevo' CHECK (estado IN ('nuevo', 'contactado', 'en_evaluacion', 'propuesta_enviada', 'negociacion', 'aceptado', 'rechazado', 'archivado')),
    notas TEXT,
    responsable_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    cliente_id INTEGER REFERENCES clientes(id) ON DELETE SET NULL,
    fecha_conversion TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Crear tabla de seguimiento de prospectos
CREATE TABLE IF NOT EXISTS seguimiento_prospectos (
    id SERIAL PRIMARY KEY,
    prospecto_id INTEGER NOT NULL REFERENCES prospectos(id) ON DELETE CASCADE,
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    tipo VARCHAR(50) NOT NULL CHECK (tipo IN ('llamada', 'email', 'reunion', 'whatsapp', 'visita', 'nota', 'cambio_estado')),
    titulo VARCHAR(255),
    descripcion TEXT,
    resultado TEXT,
    proximo_contacto DATE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_seguimiento_prospecto ON seguimiento_prospectos(prospecto_id);
CREATE INDEX IF NOT EXISTS idx_seguimiento_usuario ON seguimiento_prospectos(usuario_id);
CREATE INDEX IF NOT EXISTS idx_seguimiento_fecha ON seguimiento_prospectos(created_at DESC);

COMMENT ON TABLE seguimiento_prospectos IS 'Historial de seguimiento y contactos con prospectos';

-- Verificar
SELECT table_name FROM information_schema.tables 
WHERE table_schema = 'public' AND table_name = 'seguimiento_prospectos';
-------------------------------------------------------------------------------

CREATE INDEX idx_prospectos_estado ON prospectos(estado);
CREATE INDEX idx_prospectos_responsable ON prospectos(responsable_id);

CREATE TABLE cotizaciones (
    id SERIAL PRIMARY KEY,
    prospecto_id INTEGER NOT NULL REFERENCES prospectos(id) ON DELETE CASCADE,
    numero VARCHAR(50) UNIQUE,
    fecha_emision DATE DEFAULT CURRENT_DATE,
    fecha_vencimiento DATE,
    validez_oferta VARCHAR(100),
    tiempo_entrega VARCHAR(100),
    condiciones_pago TEXT,
    observaciones TEXT,
    subtotal NUMERIC(12,2) DEFAULT 0,
    descuento NUMERIC(12,2) DEFAULT 0,
    igv NUMERIC(12,2) DEFAULT 0,
    total NUMERIC(12,2) DEFAULT 0,
    estado VARCHAR(50) DEFAULT 'borrador' CHECK (estado IN ('borrador', 'enviada', 'aceptada', 'rechazada', 'vencida')),
    pdf_generado BOOLEAN DEFAULT FALSE,
    pdf_ruta VARCHAR(500),
    logo_empresa VARCHAR(500),
    nombre_empresa VARCHAR(255),
    ruc_empresa VARCHAR(20),
    direccion_empresa TEXT,
    telefono_empresa VARCHAR(50),
    email_empresa VARCHAR(255),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

--AGREGAR IGV

-- Agregar columna aplica_igv
ALTER TABLE cotizaciones 
ADD COLUMN IF NOT EXISTS aplica_igv BOOLEAN DEFAULT FALSE;

-- Comentario
COMMENT ON COLUMN cotizaciones.aplica_igv IS 'Indica si la cotización incluye IGV (18%)';

-- Verificar
SELECT id, numero, aplica_igv 
FROM cotizaciones 
LIMIT 5;

-- Actualizar trigger para considerar aplica_igv
CREATE OR REPLACE FUNCTION trigger_recalcular_totales_cotizacion()
RETURNS TRIGGER AS $$
BEGIN
    -- Recalcular subtotal sumando todos los items
    NEW.subtotal := COALESCE((
        SELECT SUM(subtotal)
        FROM cotizacion_items
        WHERE cotizacion_id = NEW.id
    ), 0);
    
    -- Aplicar descuento
    NEW.subtotal := NEW.subtotal - COALESCE(NEW.descuento, 0);
    
    -- Calcular IGV solo si aplica_igv es TRUE
    IF NEW.aplica_igv = TRUE THEN
        NEW.igv := NEW.subtotal * 0.18;
    ELSE
        NEW.igv := 0;
    END IF;
    
    -- Calcular total
    NEW.total := NEW.subtotal + NEW.igv;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Recrear el trigger
DROP TRIGGER IF EXISTS trigger_recalcular_totales ON cotizaciones;
CREATE TRIGGER trigger_recalcular_totales
    BEFORE UPDATE OF subtotal, descuento, aplica_igv ON cotizaciones
    FOR EACH ROW
    EXECUTE FUNCTION trigger_recalcular_totales_cotizacion();

-- Comentario
COMMENT ON FUNCTION trigger_recalcular_totales_cotizacion() IS 
'Recalcula automáticamente subtotal, IGV (si aplica) y total de una cotización';
------------------------------------------------

CREATE INDEX idx_cotizaciones_prospecto ON cotizaciones(prospecto_id);

CREATE TABLE cotizacion_items (
    id SERIAL PRIMARY KEY,
    cotizacion_id INTEGER NOT NULL REFERENCES cotizaciones(id) ON DELETE CASCADE,
    descripcion TEXT NOT NULL,
    cantidad NUMERIC(10,2) DEFAULT 1,
    unidad VARCHAR(50) DEFAULT 'servicio',
    precio_unitario NUMERIC(12,2) NOT NULL,
    importe NUMERIC(12,2) GENERATED ALWAYS AS (cantidad * precio_unitario) STORED,
    notas TEXT,
    orden SMALLINT DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_cotizacion_items_cotizacion ON cotizacion_items(cotizacion_id);

CREATE TABLE actividades_previas (
    id SERIAL PRIMARY KEY,
    prospecto_id INTEGER NOT NULL REFERENCES prospectos(id) ON DELETE CASCADE,
    tipo VARCHAR(100) NOT NULL CHECK (tipo IN ('diagnostico', 'visita_campo', 'levantamiento_info', 'reunion', 'propuesta_tecnica', 'otro')),
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    objetivo TEXT,
    responsable_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    estado VARCHAR(50) DEFAULT 'pendiente' CHECK (estado IN ('pendiente', 'en_progreso', 'completada', 'cancelada')),
    fecha_programada TIMESTAMP WITH TIME ZONE,
    fecha_realizada TIMESTAMP WITH TIME ZONE,
    duracion_estimada VARCHAR(50),
    ubicacion VARCHAR(255),
    participantes TEXT,
    resultados TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);


-- Agregar columna creada_por a cotizaciones
ALTER TABLE cotizaciones 
ADD COLUMN IF NOT EXISTS creada_por INTEGER REFERENCES usuarios(id) ON DELETE SET NULL;

-- Agregar columnas adicionales que podrían faltar
ALTER TABLE cotizaciones 
ADD COLUMN IF NOT EXISTS aprobada_por INTEGER REFERENCES usuarios(id) ON DELETE SET NULL;

ALTER TABLE cotizaciones 
ADD COLUMN IF NOT EXISTS fecha_aprobacion TIMESTAMP WITH TIME ZONE;

-- Crear índices
CREATE INDEX IF NOT EXISTS idx_cotizaciones_creada_por ON cotizaciones(creada_por);
CREATE INDEX IF NOT EXISTS idx_cotizaciones_aprobada_por ON cotizaciones(aprobada_por);

-- Comentarios
COMMENT ON COLUMN cotizaciones.creada_por IS 'Usuario que creó la cotización';
COMMENT ON COLUMN cotizaciones.aprobada_por IS 'Usuario que aprobó la cotización';

-- Verificar
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'cotizaciones' 
  AND column_name IN ('creada_por', 'aprobada_por', 'fecha_aprobacion')
ORDER BY ordinal_position;


CREATE INDEX idx_actividades_prospecto ON actividades_previas(prospecto_id);


ALTER TABLE prospectos 
ADD COLUMN IF NOT EXISTS fecha_contacto DATE;

ALTER TABLE prospectos 
ADD COLUMN IF NOT EXISTS created_by INTEGER REFERENCES usuarios(id) ON DELETE SET NULL;

-- Crear índices
CREATE INDEX IF NOT EXISTS idx_prospectos_fecha_contacto ON prospectos(fecha_contacto);
CREATE INDEX IF NOT EXISTS idx_prospectos_created_by ON prospectos(created_by);

COMMENT ON COLUMN prospectos.fecha_contacto IS 'Fecha del primer contacto con el prospecto';
COMMENT ON COLUMN prospectos.created_by IS 'Usuario que creó el registro';

-- Verificar
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'prospectos' 
  AND column_name IN ('fecha_contacto', 'created_by', 'responsable_id')
ORDER BY ordinal_position;


-- Eliminar restricción antigua
ALTER TABLE prospectos 
DROP CONSTRAINT IF EXISTS prospectos_prioridad_check;

-- Crear nueva restricción que acepte ambos casos
ALTER TABLE prospectos 
ADD CONSTRAINT prospectos_prioridad_check 
CHECK (prioridad IN ('Baja', 'Media', 'Alta', 'Urgente', 'baja', 'media', 'alta', 'urgente'));
-- ============================================================
--  4. PROYECTOS
-- ============================================================

CREATE TABLE estados_proyecto (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    color VARCHAR(20) DEFAULT '#3B82F6',
    orden SMALLINT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE
);

CREATE TABLE proyectos (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE,
    nombre VARCHAR(255) NOT NULL,
    cliente_id INTEGER NOT NULL REFERENCES clientes(id) ON DELETE RESTRICT,
    responsable_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    estado_id INTEGER REFERENCES estados_proyecto(id) ON DELETE SET NULL,
    presupuesto NUMERIC(12,2),
    prioridad VARCHAR(50) DEFAULT 'Media' CHECK (prioridad IN ('Baja', 'Media', 'Alta', 'Crítica')),
    fecha_inicio DATE,
    fecha_fin_estimada DATE,
    fecha_fin_real DATE,
    alcance TEXT,
    descripcion TEXT,
    entregables TEXT,
    avance_porcentaje NUMERIC(5,2) DEFAULT 0 CHECK (avance_porcentaje >= 0 AND avance_porcentaje <= 100),
    pdf_path VARCHAR(500),
    pdf_nombre_original VARCHAR(255),
    pdf_tamaño INTEGER,
    extraido_por_ia BOOLEAN DEFAULT FALSE,
    ia_confianza NUMERIC(5,2),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_proyectos_cliente ON proyectos(cliente_id);
CREATE INDEX idx_proyectos_responsable ON proyectos(responsable_id);
CREATE INDEX idx_proyectos_estado ON proyectos(estado_id);

CREATE TABLE entregables (
    id SERIAL PRIMARY KEY,
    proyecto_id INTEGER NOT NULL REFERENCES proyectos(id) ON DELETE CASCADE,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    porcentaje NUMERIC(5,2) NOT NULL DEFAULT 0 CHECK (porcentaje >= 0 AND porcentaje <= 100),
    archivo_path VARCHAR(500),
    archivo_nombre VARCHAR(255),
    estado VARCHAR(50) DEFAULT 'pendiente' CHECK (estado IN ('pendiente', 'en_progreso', 'completado')),
    fecha_entrega DATE,
    orden SMALLINT DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_entregables_proyecto ON entregables(proyecto_id);

CREATE TABLE tareas (
    id SERIAL PRIMARY KEY,
    proyecto_id INTEGER NOT NULL REFERENCES proyectos(id) ON DELETE CASCADE,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    responsable_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    estado VARCHAR(50) DEFAULT 'pendiente' CHECK (estado IN ('pendiente', 'en_progreso', 'completada', 'cancelada')),
    prioridad VARCHAR(50) DEFAULT 'Media' CHECK (prioridad IN ('Baja', 'Media', 'Alta', 'Urgente')),
    fecha_inicio DATE,
    fecha_vencimiento DATE,
    fecha_completada DATE,
    orden SMALLINT DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_tareas_proyecto ON tareas(proyecto_id);

-- Agregar columna costo_real a proyectos ( Agregado 2da vez)
ALTER TABLE proyectos 
ADD COLUMN IF NOT EXISTS costo_real NUMERIC(12,2) DEFAULT 0;

COMMENT ON COLUMN proyectos.costo_real IS 'Costo real del proyecto (vs presupuesto estimado)';

-- Verificar que se agregó
SELECT column_name, data_type, column_default
FROM information_schema.columns 
WHERE table_name = 'proyectos' 
  AND column_name IN ('presupuesto', 'costo_real')
ORDER BY ordinal_position;



-- ============================================================
--  5. INCIDENCIAS
-- ============================================================

CREATE TABLE incidencias (
    id SERIAL PRIMARY KEY,
    proyecto_id INTEGER NOT NULL REFERENCES proyectos(id) ON DELETE CASCADE,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    tipo VARCHAR(50) CHECK (tipo IN ('Error', 'Mejora', 'Consulta', 'Otro')),
    severidad VARCHAR(50) DEFAULT 'Media' CHECK (severidad IN ('Baja', 'Media', 'Alta', 'Crítica')),
    estado VARCHAR(50) DEFAULT 'abierta' CHECK (estado IN ('abierta', 'en_proceso', 'resuelta', 'cerrada')),
    responsable_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    fecha_resolucion TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_incidencias_proyecto ON incidencias(proyecto_id);
CREATE INDEX idx_incidencias_estado ON incidencias(estado);


-- Agregar columnas faltantes a incidencias (código agregado)
ALTER TABLE incidencias 
ADD COLUMN IF NOT EXISTS reportado_por INTEGER REFERENCES usuarios(id) ON DELETE SET NULL;

ALTER TABLE incidencias 
ADD COLUMN IF NOT EXISTS asignado_a INTEGER REFERENCES usuarios(id) ON DELETE SET NULL;

ALTER TABLE incidencias 
ADD COLUMN IF NOT EXISTS fecha_reporte TIMESTAMP WITH TIME ZONE DEFAULT NOW();

ALTER TABLE incidencias 
ADD COLUMN IF NOT EXISTS solucion TEXT;

-- Crear índices
CREATE INDEX IF NOT EXISTS idx_incidencias_reportado ON incidencias(reportado_por);
CREATE INDEX IF NOT EXISTS idx_incidencias_asignado ON incidencias(asignado_a);
-- ============================================================
--  6. CAPACITACIÓN
-- ============================================================

CREATE TABLE cursos (
    id SERIAL PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    objetivos TEXT,
    duracion_horas NUMERIC(5,2),
    nivel VARCHAR(50) CHECK (nivel IN ('básico', 'intermedio', 'avanzado')),
    instructor VARCHAR(255),
    imagen_url VARCHAR(500),
    estado VARCHAR(50) DEFAULT 'borrador' CHECK (estado IN ('borrador', 'publicado', 'archivado')),
    orden SMALLINT DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE materiales_curso (
    id SERIAL PRIMARY KEY,
    curso_id INTEGER NOT NULL REFERENCES cursos(id) ON DELETE CASCADE,
    titulo VARCHAR(255) NOT NULL,
    tipo VARCHAR(50) NOT NULL CHECK (tipo IN ('video', 'pdf', 'link', 'texto')),
    contenido TEXT,
    descripcion TEXT,
    duracion_minutos INTEGER,
    orden SMALLINT DEFAULT 0,
    obligatorio BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE cuestionarios (
    id SERIAL PRIMARY KEY,
    curso_id INTEGER NOT NULL REFERENCES cursos(id) ON DELETE CASCADE,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    tiempo_limite_minutos INTEGER,
    intentos_permitidos INTEGER DEFAULT 1,
    puntaje_minimo_aprobacion NUMERIC(5,2) DEFAULT 70.00,
    mostrar_respuestas BOOLEAN DEFAULT FALSE,
    orden SMALLINT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE preguntas (
    id SERIAL PRIMARY KEY,
    cuestionario_id INTEGER NOT NULL REFERENCES cuestionarios(id) ON DELETE CASCADE,
    texto TEXT NOT NULL,
    tipo VARCHAR(50) NOT NULL CHECK (tipo IN ('multiple_choice', 'verdadero_falso', 'respuesta_corta')),
    puntos NUMERIC(5,2) DEFAULT 1.00,
    orden SMALLINT DEFAULT 0,
    explicacion TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE opciones_respuesta (
    id SERIAL PRIMARY KEY,
    pregunta_id INTEGER NOT NULL REFERENCES preguntas(id) ON DELETE CASCADE,
    texto TEXT NOT NULL,
    es_correcta BOOLEAN DEFAULT FALSE,
    orden SMALLINT DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE inscripciones_curso (
    id SERIAL PRIMARY KEY,
    curso_id INTEGER NOT NULL REFERENCES cursos(id) ON DELETE CASCADE,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    fecha_inscripcion TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    fecha_inicio TIMESTAMP WITH TIME ZONE,
    fecha_finalizacion TIMESTAMP WITH TIME ZONE,
    estado VARCHAR(50) DEFAULT 'en_progreso' CHECK (estado IN ('en_progreso', 'completado', 'abandonado')),
    progreso_porcentaje NUMERIC(5,2) DEFAULT 0.00,
    calificacion_final NUMERIC(5,2),
    UNIQUE(curso_id, usuario_id)
);

CREATE TABLE progreso_materiales (
    id SERIAL PRIMARY KEY,
    inscripcion_id INTEGER NOT NULL REFERENCES inscripciones_curso(id) ON DELETE CASCADE,
    material_id INTEGER NOT NULL REFERENCES materiales_curso(id) ON DELETE CASCADE,
    completado BOOLEAN DEFAULT FALSE,
    fecha_completado TIMESTAMP WITH TIME ZONE,
    tiempo_dedicado_minutos INTEGER DEFAULT 0,
    UNIQUE(inscripcion_id, material_id)
);

CREATE TABLE intentos_cuestionario (
    id SERIAL PRIMARY KEY,
    cuestionario_id INTEGER NOT NULL REFERENCES cuestionarios(id) ON DELETE CASCADE,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    fecha_inicio TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    fecha_finalizacion TIMESTAMP WITH TIME ZONE,
    puntaje_obtenido NUMERIC(5,2),
    puntaje_maximo NUMERIC(5,2),
    porcentaje NUMERIC(5,2),
    aprobado BOOLEAN,
    numero_intento INTEGER DEFAULT 1,
    tiempo_total_minutos INTEGER
);

CREATE TABLE respuestas_usuario (
    id SERIAL PRIMARY KEY,
    intento_id INTEGER NOT NULL REFERENCES intentos_cuestionario(id) ON DELETE CASCADE,
    pregunta_id INTEGER NOT NULL REFERENCES preguntas(id) ON DELETE CASCADE,
    opcion_id INTEGER REFERENCES opciones_respuesta(id) ON DELETE SET NULL,
    respuesta_texto TEXT,
    es_correcta BOOLEAN,
    puntos_obtenidos NUMERIC(5,2) DEFAULT 0.00,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_materiales_curso_id ON materiales_curso(curso_id);
CREATE INDEX idx_cuestionarios_curso_id ON cuestionarios(curso_id);


-- Agregar columna instructor_id como relación a usuarios
ALTER TABLE cursos 
ADD COLUMN IF NOT EXISTS instructor_id INTEGER REFERENCES   usuarios(id) ON DELETE SET NULL;

-- Crear índice
CREATE INDEX IF NOT EXISTS idx_cursos_instructor ON cursos(instructor_id);

COMMENT ON COLUMN cursos.instructor_id IS 'Instructor del curso (referencia a usuarios)';

-- Verificar
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'cursos' 
  AND column_name IN ('instructor', 'instructor_id')
ORDER BY ordinal_position;


-- Agregar todas las columnas faltantes a cursos
ALTER TABLE cursos 
ADD COLUMN IF NOT EXISTS modalidad VARCHAR(30) DEFAULT 'Presencial' 
CHECK (modalidad IN ('Presencial', 'Virtual', 'Híbrido'));

ALTER TABLE cursos 
ADD COLUMN IF NOT EXISTS max_participantes SMALLINT;

ALTER TABLE cursos 
ADD COLUMN IF NOT EXISTS fecha_inicio TIMESTAMP WITH TIME ZONE;

ALTER TABLE cursos 
ADD COLUMN IF NOT EXISTS fecha_fin TIMESTAMP WITH TIME ZONE;

-- Verificar que se agregaron
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'cursos'
ORDER BY ordinal_position;

-- ============================================================
--  7. AUDITORÍA
-- ============================================================

CREATE TABLE auditoria_log (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    accion VARCHAR(50) NOT NULL CHECK (accion IN ('CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT')),
    modulo VARCHAR(100) NOT NULL,
    registro_id INTEGER,
    datos_antes JSONB,
    datos_despues JSONB,
    ip_address VARCHAR(50),
    user_agent TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX idx_auditoria_usuario ON auditoria_log(usuario_id);
CREATE INDEX idx_auditoria_modulo ON auditoria_log(modulo);
CREATE INDEX idx_auditoria_created ON auditoria_log(created_at DESC);


CREATE TABLE IF NOT EXISTS sesiones (
    session_id VARCHAR(128) PRIMARY KEY,
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE CASCADE,
    data TEXT,
    ip_address VARCHAR(50),
    user_agent TEXT,
    last_activity TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_sesiones_usuario ON sesiones(usuario_id);
CREATE INDEX IF NOT EXISTS idx_sesiones_activity ON sesiones(last_activity);

COMMENT ON TABLE sesiones IS 'Gestión de sesiones de usuario (opcional)';

-- Verificar
SELECT table_name FROM information_schema.tables 
WHERE table_schema = 'public' AND table_name = 'sesiones';
-- ============================================================
--  TRIGGERS
-- ============================================================
-- Primero, verificar la estructura de cotizacion_items
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'cotizacion_items'
ORDER BY ordinal_position;


-- Trigger corregido: calcular subtotal desde cantidad * precio_unitario
CREATE OR REPLACE FUNCTION trigger_recalcular_totales_cotizacion()
RETURNS TRIGGER AS $$
BEGIN
    -- Recalcular subtotal sumando cantidad * precio_unitario de todos los items
    NEW.subtotal := COALESCE((
        SELECT SUM(cantidad * precio_unitario)
        FROM cotizacion_items
        WHERE cotizacion_id = NEW.id
    ), 0);
    
    -- Aplicar descuento
    NEW.subtotal := NEW.subtotal - COALESCE(NEW.descuento, 0);
    
    -- Calcular IGV solo si aplica_igv es TRUE
    IF NEW.aplica_igv = TRUE THEN
        NEW.igv := NEW.subtotal * 0.18;
    ELSE
        NEW.igv := 0;
    END IF;
    
    -- Calcular total
    NEW.total := NEW.subtotal + NEW.igv;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Recrear el trigger
DROP TRIGGER IF EXISTS trigger_recalcular_totales ON cotizaciones;
CREATE TRIGGER trigger_recalcular_totales
    BEFORE UPDATE OF descuento, aplica_igv ON cotizaciones
    FOR EACH ROW
    EXECUTE FUNCTION trigger_recalcular_totales_cotizacion();

-- También necesitamos un trigger para cuando se modifiquen los items
CREATE OR REPLACE FUNCTION trigger_actualizar_cotizacion_desde_items()
RETURNS TRIGGER AS $$
BEGIN
    -- Actualizar la cotización para disparar el recalculo
    UPDATE cotizaciones 
    SET updated_at = NOW() 
    WHERE id = COALESCE(NEW.cotizacion_id, OLD.cotizacion_id);
    
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_items_actualizar_cotizacion ON cotizacion_items;
CREATE TRIGGER trigger_items_actualizar_cotizacion
    AFTER INSERT OR UPDATE OR DELETE ON cotizacion_items
    FOR EACH ROW
    EXECUTE FUNCTION trigger_actualizar_cotizacion_desde_items();

-- Comentarios (No es necesario ejecutar, solo se queda aquí en el archivo schema.sql)
COMMENT ON FUNCTION trigger_recalcular_totales_cotizacion() IS 
'Recalcula automáticamente subtotal (suma de items), IGV (si aplica) y total';

COMMENT ON FUNCTION trigger_actualizar_cotizacion_desde_items() IS 
'Dispara el recalculo de totales cuando se modifican items de la cotización';
---------------------------------------------------------------
-- Trigger: Actualizar avance del proyecto basado en entregables
CREATE OR REPLACE FUNCTION actualizar_avance_proyecto()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE proyectos 
    SET avance_porcentaje = (
        SELECT COALESCE(SUM(porcentaje), 0)
        FROM entregables 
        WHERE proyecto_id = COALESCE(NEW.proyecto_id, OLD.proyecto_id)
    )
    WHERE id = COALESCE(NEW.proyecto_id, OLD.proyecto_id);
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_actualizar_avance
    AFTER INSERT OR UPDATE OR DELETE ON entregables
    FOR EACH ROW
    EXECUTE FUNCTION actualizar_avance_proyecto();

-- Trigger: Recalcular totales de cotización
CREATE OR REPLACE FUNCTION recalcular_totales_cotizacion()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE cotizaciones
    SET subtotal = (
            SELECT COALESCE(SUM(importe), 0)
            FROM cotizacion_items
            WHERE cotizacion_id = COALESCE(NEW.cotizacion_id, OLD.cotizacion_id)
        ),
        igv = (
            SELECT COALESCE(SUM(importe), 0) * 0.18
            FROM cotizacion_items
            WHERE cotizacion_id = COALESCE(NEW.cotizacion_id, OLD.cotizacion_id)
        ),
        total = (
            SELECT (COALESCE(SUM(importe), 0) * 1.18) - COALESCE((SELECT descuento FROM cotizaciones WHERE id = COALESCE(NEW.cotizacion_id, OLD.cotizacion_id)), 0)
            FROM cotizacion_items
            WHERE cotizacion_id = COALESCE(NEW.cotizacion_id, OLD.cotizacion_id)
        )
    WHERE id = COALESCE(NEW.cotizacion_id, OLD.cotizacion_id);
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_recalcular_totales
    AFTER INSERT OR UPDATE OR DELETE ON cotizacion_items
    FOR EACH ROW
    EXECUTE FUNCTION recalcular_totales_cotizacion();

-- ============================================================
--  VISTAS
-- ============================================================

CREATE VIEW vw_kpi_dashboard AS
SELECT
    (SELECT COUNT(*) FROM proyectos WHERE estado_id NOT IN (4,5,6)) AS proyectos_activos,
    (SELECT COUNT(*) FROM proyectos WHERE estado_id IN (4,5)) AS proyectos_completados,
    (SELECT COUNT(*) FROM clientes WHERE activo = TRUE) AS clientes_activos,
    (SELECT COUNT(*) FROM incidencias WHERE estado = 'abierta') AS incidencias_abiertas,
    (SELECT COALESCE(SUM(presupuesto), 0) FROM proyectos WHERE estado_id NOT IN (4,5,6)) AS presupuesto_total_activo,
    (SELECT COALESCE(ROUND(AVG(avance_porcentaje), 2), 0) FROM proyectos WHERE estado_id NOT IN (4,5,6)) AS avance_promedio;

CREATE VIEW vw_metricas_previas AS
SELECT
    COUNT(*) AS total_prospectos,
    COUNT(*) FILTER (WHERE estado = 'nuevo') AS nuevos,
    COUNT(*) FILTER (WHERE estado = 'en_evaluacion') AS en_evaluacion,
    COUNT(*) FILTER (WHERE estado = 'propuesta_enviada') AS propuestas_enviadas,
    COUNT(*) FILTER (WHERE estado = 'aceptado') AS aceptados,
    COUNT(*) FILTER (WHERE estado = 'rechazado') AS rechazados,
    (SELECT COUNT(*) FROM actividades_previas WHERE estado = 'pendiente') AS actividades_pendientes,
    (SELECT COUNT(*) FROM cotizaciones WHERE estado IN ('enviada', 'borrador')) AS propuestas_activas,
    CASE 
        WHEN COUNT(*) FILTER (WHERE estado IN ('nuevo', 'contactado', 'en_evaluacion')) > 0 
        THEN ROUND(
            (COUNT(*) FILTER (WHERE estado = 'aceptado')::NUMERIC / 
             COUNT(*) FILTER (WHERE estado IN ('nuevo', 'contactado', 'en_evaluacion', 'aceptado', 'rechazado'))::NUMERIC * 100), 
            2
        )
        ELSE NULL
    END AS tasa_conversion
FROM prospectos;

CREATE VIEW vw_prospectos_resumen AS
SELECT 
    p.*,
    u.nombre || ' ' || u.apellido AS responsable_nombre,
    (SELECT COUNT(*) FROM actividades_previas ap WHERE ap.prospecto_id = p.id) AS total_actividades,
    (SELECT COUNT(*) FROM actividades_previas ap WHERE ap.prospecto_id = p.id AND ap.estado = 'completada') AS actividades_completadas,
    (SELECT COUNT(*) FROM cotizaciones c WHERE c.prospecto_id = p.id) AS total_cotizaciones
FROM prospectos p
LEFT JOIN usuarios u ON p.responsable_id = u.id;

CREATE VIEW vw_proyectos_completo AS
SELECT 
    p.*,
    c.razon_social AS cliente_nombre,
    ep.nombre AS estado_nombre,
    ep.color AS estado_color,
    u.nombre || ' ' || u.apellido AS responsable_nombre,
    (SELECT COUNT(*) FROM tareas t WHERE t.proyecto_id = p.id) AS total_tareas,
    (SELECT COUNT(*) FROM tareas t WHERE t.proyecto_id = p.id AND t.estado = 'completada') AS tareas_ok
FROM proyectos p
LEFT JOIN clientes c ON p.cliente_id = c.id
LEFT JOIN estados_proyecto ep ON p.estado_id = ep.id
LEFT JOIN usuarios u ON p.responsable_id = u.id;

-- ============================================================
--  DATOS INICIALES
-- ============================================================

-- Estados de proyecto
INSERT INTO estados_proyecto (nombre, color, orden) VALUES
('Planificación', '#3B82F6', 1),
('En Desarrollo', '#F59E0B', 2),
('En Revisión', '#8B5CF6', 3),
('Completado', '#10B981', 4),
('Cancelado', '#EF4444', 5),
('En Pausa', '#6B7280', 6);

-- Sectores
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

-- Usuario admin (usa el hash generado en tu computadora)
INSERT INTO usuarios (nombre, apellido, email, password_hash, rol, activo, avatar_initials)
VALUES (
    'Admin',
    'Sistema',
    'admin@corfiem.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Admin',
    TRUE,
    'AS'
);

-- ============================================================
--  VERIFICACIÓN
-- ============================================================

SELECT 'Schema creado exitosamente' AS status;
SELECT COUNT(*) AS total_tablas FROM information_schema.tables 
WHERE table_schema = 'public' AND table_type = 'BASE TABLE';
SELECT COUNT(*) AS total_vistas FROM information_schema.views 
WHERE table_schema = 'public';
```

---

## **PASOS PARA EJECUTAR:**

### **1. Ejecutar el schema completo**

En pgAdmin → Query Tool → Pegar todo el SQL de arriba → Ejecutar (F5)

### **2. Generar nuevo hash de contraseña**
```
http://localhost/Corfiem_Cesar/generar_hash.php