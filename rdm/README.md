# RDM - Reparación de Dispositivos Móviles

## Descripción
RDM es un sistema de gestión de taller para la reparación de dispositivos móviles y otros equipos electrónicos. Permite administrar clientes, técnicos, ingresos de equipos, seguimiento de reparaciones, facturación básica y generación de reportes.

## Features Principales
*   **Autenticación de Usuarios**: Roles para Administradores y Técnicos.
*   **Gestión de Clientes**: CRUD (Crear, Leer, Actualizar, Eliminar) para clientes.
*   **Gestión de Técnicos**: CRUD para técnicos.
*   **Gestión de Usuarios**: Creación de usuarios y asignación de roles (Administrador).
*   **Recepción de Equipos**: Formulario de ingreso detallado de equipos, incluyendo fallas, estado físico, accesorios.
*   **Seguimiento de Equipos**:
    *   Actualización de estado de reparación.
    *   Registro de notas internas y para el historial de seguimiento.
    *   Página pública para que los clientes consulten el estado de su equipo con Nº de Orden y DNI.
*   **Ficha de Ingreso**: Generación de una ficha imprimible al ingresar un equipo.
*   **Captura de Firma**: El cliente puede firmar digitalmente la ficha de ingreso.
*   **Facturación**:
    *   Configuración de datos fiscales de la empresa.
    *   Generación de facturas (Tipo "C" simulada) con items, cálculo de IVA y totales.
    *   Vista HTML de la factura.
    *   Descarga de factura en formato PDF.
    *   Listado de facturas emitidas.
*   **Dashboard**: Panel principal con estadísticas visuales:
    *   Equipos ingresados por mes (últimos 6 meses).
    *   Distribución de equipos por estado actual.
    *   Facturación mensual (últimos 6 meses, solo para administradores).
*   **Reportes**:
    *   Equipos por Fecha y Técnico (con exportación a CSV y PDF).
    *   Recaudación por Fecha (con exportación a CSV y PDF, solo para administradores).
*   **Interfaz Responsiva**: Uso de Bootstrap para adaptabilidad a diferentes tamaños de pantalla.

## Technology Stack
*   **Backend**: PHP (versión 8.0+ recomendada, no usa frameworks específicos)
*   **Frontend**: HTML5, CSS3, JavaScript (Vanilla JS)
*   **Base de Datos**: MySQL / MariaDB
*   **Frameworks/Librerías**:
    *   [Bootstrap v5.3.7](https://getbootstrap.com/): Para el diseño y componentes de UI.
    *   [Chart.js v4.4.1](https://www.chartjs.org/): Para la generación de gráficos en el dashboard.
    *   [FPDF v1.86](http://www.fpdf.org/): Para la generación de documentos PDF (facturas, reportes).
    *   [SignaturePad.js v4.1.7](https://github.com/szimek/signature_pad): Para la captura de firmas digitales.

## Instrucciones de Configuración

### 1. Requisitos del Servidor
*   Servidor Web: Apache o Nginx con soporte para PHP.
*   PHP: Versión 7.4 o superior (8.0+ recomendado).
*   Extensiones PHP requeridas:
    *   `mysqli` (para la conexión a la base de datos MySQL/MariaDB).
    *   `mbstring` (para funciones de cadenas multibyte, útil con FPDF y caracteres especiales).
    *   `gd` (generalmente útil para manipulación de imágenes, aunque no se usa explícitamente para funciones críticas en este proyecto, FPDF puede requerirla para ciertos formatos de imagen si se extienden sus capacidades).
    *   `intl` (para `strftime` con localización de nombres de meses en el dashboard, si se quiere una correcta traducción. Si no está, los nombres de meses pueden aparecer en inglés).

### 2. Configuración de la Base de Datos
1.  Cree una nueva base de datos MySQL/MariaDB. Ejemplo: `rdm_db`.
    ```sql
    CREATE DATABASE rdm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ```
2.  Cree un usuario de base de datos con permisos para esta base de datos. Ejemplo: `rdm_user`.
    ```sql
    CREATE USER 'rdm_user'@'localhost' IDENTIFIED BY 'su_contraseña_segura';
    GRANT ALL PRIVILEGES ON rdm_db.* TO 'rdm_user'@'localhost';
    FLUSH PRIVILEGES;
    ```
3.  Importe la estructura de la base de datos y los datos iniciales utilizando el archivo `sql/estructura_base.sql`:
    ```bash
    mysql -u rdm_user -p rdm_db < sql/estructura_base.sql
    ```
    (Se le pedirá la contraseña del usuario `rdm_user`).

### 3. Archivos de la Aplicación
1.  Clone o descargue el contenido de este repositorio.
2.  Copie todos los archivos y directorios dentro de la carpeta `rdm/` al directorio raíz de su servidor web (ej. `htdocs`, `www`, `public_html`) o a un subdirectorio si prefiere (ej. `http://localhost/rdm_app/`).
    *   Asegúrese de que los directorios `assets/css`, `assets/js`, e `includes/fpdf` (y su contenido) se copien correctamente.

### 4. Configuración de la Aplicación
1.  Edite el archivo `includes/db.php`.
2.  Modifique las siguientes constantes con sus credenciales de base de datos:
    ```php
    define('DB_HOST', 'localhost');     // Host de la base de datos
    define('DB_USER', 'rdm_user');      // Usuario de la base de datos
    define('DB_PASS', 'su_contraseña_segura'); // Contraseña del usuario
    define('DB_NAME', 'rdm_db');        // Nombre de la base de datos
    ```
    No hay un archivo de configuración centralizado adicional; las configuraciones específicas (como esta de la BD) se encuentran en los archivos relevantes.

### 5. Acceso por Defecto (Administrador)
*   **Usuario**: `admin`
*   **Contraseña**: `admin123`

Se recomienda cambiar esta contraseña inmediatamente después del primer inicio de sesión (funcionalidad no implementada directamente en la UI, requeriría una página de "cambiar contraseña" o modificación directa en la BD para el hash).

## Estructura de Carpetas Principal
```
rdm/
├── admin/             # Scripts de administración (gestión de usuarios, datos fiscales)
├── assets/            # Archivos estáticos (CSS, JS, imágenes)
│   ├── css/
│   └── js/
├── clientes/          # CRUD para Clientes
├── equipos/           # Gestión de Equipos (ingreso, listado, edición, seguimiento, firma)
├── facturas/          # Generación, visualización y listado de Facturas (HTML, PDF)
├── includes/          # Archivos PHP comunes (conexión BD, funciones, header, footer)
│   └── fpdf/          # Librería FPDF
├── reportes/          # Scripts para generación de reportes (equipos, recaudación)
├── sql/               # Archivo SQL para la estructura y datos iniciales
├── index.php          # Página de Login
├── dashboard.php      # Panel principal post-login
└── README.md          # Este archivo
```

## Notas Adicionales
*   **Seguridad**: Este proyecto es una demostración y tiene simplificaciones. Para un entorno de producción, se deben revisar y mejorar aspectos de seguridad como:
    *   Protección CSRF en todos los formularios que realizan acciones.
    *   Validación de entradas más exhaustiva.
    *   Manejo de errores más robusto.
    *   Políticas de contraseñas seguras y mecanismo de cambio.
    *   Control de acceso basado en roles más granular para todas las secciones.
    *   Configuración segura del servidor y PHP.
*   **Generación de Número de Factura**: La generación actual en `facturas/generar_factura.php` es básica. En un sistema real, esto debería ser más robusto para asegurar unicidad y cumplir con normativas fiscales (ej. usando secuencias de BD o un sistema de numeración dedicado).
*   **CAE para Facturas**: El campo CAE es un placeholder. La integración real con AFIP (Argentina) u otras entidades fiscales para obtener CAE es un proceso complejo no incluido.

## Contribuciones
Este proyecto es principalmente para fines demostrativos. Las contribuciones pueden ser consideradas si se alinean con los objetivos educativos del sistema.
