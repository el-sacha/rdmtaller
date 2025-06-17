-- Base de datos para el Sistema de Gestión de Reparaciones (RDM)

-- Tabla de Clientes
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    dni VARCHAR(20) UNIQUE,
    email VARCHAR(255) UNIQUE,
    telefono VARCHAR(50),
    direccion TEXT
);

-- Tabla de Técnicos
CREATE TABLE tecnicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    especialidad VARCHAR(100)
);

-- Tabla de Usuarios del Sistema
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_usuario VARCHAR(100) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL, -- Almacena el hash de la contraseña
    rol ENUM('admin', 'tecnico') NOT NULL,
    tecnico_id INT,
    FOREIGN KEY (tecnico_id) REFERENCES tecnicos(id) ON DELETE SET NULL
);

-- Tabla de Estados de Reparación
CREATE TABLE estados_reparacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_estado VARCHAR(100) NOT NULL UNIQUE
);

-- Tabla de Equipos
CREATE TABLE equipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    tipo_equipo VARCHAR(100) NOT NULL,
    marca VARCHAR(100),
    modelo VARCHAR(100),
    numero_serie_imei VARCHAR(100) UNIQUE,
    fallas_reportadas TEXT NOT NULL,
    estado_fisico TEXT,
    observaciones TEXT,
    accesorios_entregados TEXT,
    tecnico_asignado_id INT,
    fecha_ingreso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado_reparacion_id INT,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (tecnico_asignado_id) REFERENCES tecnicos(id) ON DELETE SET NULL,
    FOREIGN KEY (estado_reparacion_id) REFERENCES estados_reparacion(id) ON DELETE SET NULL
);

-- Tabla de Reparaciones
CREATE TABLE reparaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id INT NOT NULL,
    tecnico_id INT,
    fecha_inicio TIMESTAMP,
    fecha_fin TIMESTAMP,
    descripcion_trabajo TEXT,
    costo_repuestos DECIMAL(10, 2) DEFAULT 0.00,
    costo_mano_obra DECIMAL(10, 2) DEFAULT 0.00,
    notas_internas TEXT,
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE,
    FOREIGN KEY (tecnico_id) REFERENCES tecnicos(id) ON DELETE SET NULL
);

-- Tabla de Facturas
CREATE TABLE facturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    equipo_id INT, -- Puede ser null si la factura es por otros servicios
    fecha_emision TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    numero_factura VARCHAR(50) UNIQUE NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    iva DECIMAL(10, 2) NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    metodo_pago VARCHAR(50),
    datos_fiscales_empresa TEXT, -- Podría normalizarse en otra tabla si es complejo
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE SET NULL
);

-- Tabla de Seguimiento del Equipo (Historial de Estados)
CREATE TABLE seguimiento_equipo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id INT NOT NULL,
    estado_id INT NOT NULL,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notas TEXT,
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE,
    FOREIGN KEY (estado_id) REFERENCES estados_reparacion(id) ON DELETE RESTRICT
);

-- Inserción de estados de reparación por defecto
INSERT INTO estados_reparacion (nombre_estado) VALUES
('Ingresado'),
('En revisión'),
('Esperando repuestos'),
('Reparado'),
('No reparado'),
('Entregado'),
('Presupuesto enviado'),
('Presupuesto aceptado'),
('Presupuesto rechazado');

-- Inserción de usuario administrador por defecto
-- Contraseña para 'admin' es 'admin123' (hasheada con PASSWORD_DEFAULT)
INSERT INTO usuarios (nombre_usuario, contrasena, rol) VALUES ('admin', '$2y$10$JaYI6bwJoYiDt1ZxWH0Phe7W5.VWtHRukBIPHDMLeeN77gpH5A6ga', 'admin');

-- Consideraciones adicionales:
-- 1. Índices: Agregar índices en columnas frecuentemente usadas en búsquedas (ej. email en clientes, numero_serie_imei en equipos).
--    CREATE INDEX idx_clientes_email ON clientes(email);
--    CREATE INDEX idx_equipos_serie ON equipos(numero_serie_imei);
-- 2. Contraseñas: Asegurarse de que las contraseñas en la tabla `usuarios` se almacenen usando funciones de hash seguras (ej. bcrypt, Argon2).
-- 3. Datos fiscales: Si `datos_fiscales_empresa` es complejo o se repite, considerar una tabla separada.
-- 4. Auditoría: Para un seguimiento más detallado, se podrían agregar tablas de auditoría para cambios importantes.
-- 5. Backup y Mantenimiento: Establecer rutinas de backup y mantenimiento de la base de datos.
