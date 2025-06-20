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
    contrasena VARCHAR(255) NOT NULL,
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
    notas_internas_reparacion TEXT NULL,
    costo_total_reparacion DECIMAL(10, 2) DEFAULT 0.00,
    firma_cliente_ruta VARCHAR(255) NULL DEFAULT NULL, -- Ruta al archivo de firma del cliente
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
    FOREIGN KEY (tecnico_asignado_id) REFERENCES tecnicos(id) ON DELETE SET NULL,
    FOREIGN KEY (estado_reparacion_id) REFERENCES estados_reparacion(id) ON DELETE SET NULL
);

-- Tabla de Datos Fiscales de la Empresa
CREATE TABLE datos_fiscales_empresa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_empresa VARCHAR(255) NOT NULL,
    cuit VARCHAR(20) NOT NULL UNIQUE,
    domicilio_comercial TEXT NOT NULL,
    condicion_iva VARCHAR(100) NOT NULL,
    punto_venta VARCHAR(10) NOT NULL,
    ingresos_brutos VARCHAR(50),
    fecha_inicio_actividades DATE,
    logo_url VARCHAR(255) NULL
);

-- Tabla de Facturas
CREATE TABLE facturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    equipo_id INT NULL,
    datos_fiscales_empresa_id INT NOT NULL,
    fecha_emision TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    numero_factura VARCHAR(50) UNIQUE NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    iva_porcentaje DECIMAL(5, 2) NOT NULL,
    iva_monto DECIMAL(10, 2) NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    metodo_pago VARCHAR(100),
    condicion_venta VARCHAR(100),
    cae VARCHAR(100) NULL,
    fecha_vto_cae DATE NULL,
    notas TEXT NULL,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE SET NULL,
    FOREIGN KEY (datos_fiscales_empresa_id) REFERENCES datos_fiscales_empresa(id) ON DELETE RESTRICT
);

-- Tabla de Items de Factura
CREATE TABLE factura_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    factura_id INT NOT NULL,
    descripcion TEXT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(10, 2) NOT NULL,
    subtotal_item DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE
);

-- Tabla de Seguimiento del Equipo (Historial de Estados)
CREATE TABLE seguimiento_equipo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id INT NOT NULL,
    estado_id INT NOT NULL,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notas TEXT NULL,
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE,
    FOREIGN KEY (estado_id) REFERENCES estados_reparacion(id) ON DELETE RESTRICT
);

-- Inserción de estados de reparación por defecto
INSERT INTO estados_reparacion (nombre_estado) VALUES
('Ingresado'), ('En revisión'), ('Esperando repuestos'), ('Presupuesto enviado'),
('Presupuesto aceptado'), ('Presupuesto rechazado'), ('Reparado'), ('No reparado'), ('Listo para entregar'), ('Entregado');

-- Inserción de usuario administrador por defecto
INSERT INTO usuarios (nombre_usuario, contrasena, rol) VALUES ('admin', '$2y$10$JaYI6bwJoYiDt1ZxWH0Phe7W5.VWtHRukBIPHDMLeeN77gpH5A6ga', 'admin');

-- Inserción de datos fiscales de la empresa (placeholder)
INSERT INTO datos_fiscales_empresa (nombre_empresa, cuit, domicilio_comercial, condicion_iva, punto_venta, ingresos_brutos, fecha_inicio_actividades, logo_url) VALUES
('RDM Tech Solutions (Placeholder)', '30-12345678-9', 'Calle Falsa 123, Ciudad, Provincia', 'Responsable Monotributo', '0001', 'Exento', '2023-01-01', NULL);

CREATE TABLE reparaciones_intervenciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id INT NOT NULL,
    tecnico_id INT,
    fecha_inicio TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_fin TIMESTAMP NULL,
    descripcion_problema_detectado TEXT NULL,
    descripcion_trabajo_realizado TEXT NULL,
    costo_repuestos_usados DECIMAL(10, 2) DEFAULT 0.00,
    costo_mano_obra DECIMAL(10, 2) DEFAULT 0.00,
    notas_tecnico TEXT NULL,
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE,
    FOREIGN KEY (tecnico_id) REFERENCES tecnicos(id) ON DELETE SET NULL
);
