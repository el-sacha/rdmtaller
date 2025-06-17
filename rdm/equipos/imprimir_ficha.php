<?php
// equipos/imprimir_ficha.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login(); // Opcional: decidir si la ficha impresa requiere login para verse post-generación

$equipo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($equipo_id <= 0) {
    // Considerar redirigir a una página de error o al listado de equipos
    die("Error: ID de equipo no válido.");
}

$sql = "SELECT
            e.id AS equipo_id, e.tipo_equipo, e.marca, e.modelo, e.numero_serie_imei,
            e.fallas_reportadas, e.estado_fisico, e.observaciones, e.accesorios_entregados,
            e.fecha_ingreso,
            c.nombre AS cliente_nombre, c.dni AS cliente_dni, c.telefono AS cliente_telefono, c.email AS cliente_email,
            t.nombre AS tecnico_nombre,
            er.nombre_estado AS estado_actual
        FROM equipos e
        JOIN clientes c ON e.cliente_id = c.id
        LEFT JOIN tecnicos t ON e.tecnico_asignado_id = t.id
        LEFT JOIN estados_reparacion er ON e.estado_reparacion_id = er.id
        WHERE e.id = ?";

$stmt = mysqli_prepare($enlace, $sql);
if (!$stmt) {
    die("Error al preparar la consulta: " . mysqli_error($enlace));
}

mysqli_stmt_bind_param($stmt, "i", $equipo_id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$equipo = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

if (!$equipo) {
    die("Ficha de equipo no encontrada. ID: " . htmlspecialchars($equipo_id));
}

// mysqli_close($enlace); // Cerrar si no se usa en un footer incluido

// Para la visualización de la fecha en formato local
$fecha_ingreso_formateada = date("d/m/Y H:i:s", strtotime($equipo['fecha_ingreso']));

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de Ingreso Equipo N° <?php echo htmlspecialchars($equipo['equipo_id']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #fff; color: #000; }
        .container-ficha { width: 800px; margin: 20px auto; padding: 20px; border: 1px solid #ccc; background-color: #fff; }
        h1, h2, h3 { text-align: center; margin-top: 5px; margin-bottom:15px;}
        h1 { font-size: 24px; }
        h2 { font-size: 20px; }
        .header-info { display: flex; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom:10px;}
        .header-info div { width: 48%; }
        .section { margin-bottom: 20px; padding:10px; border: 1px solid #eee; border-radius:4px;}
        .section h3 { text-align:left; margin-bottom:10px; background-color:#f2f2f2; padding:5px; border-radius:3px;}
        .section p { margin: 5px 0; line-height:1.6;}
        .section p strong { display: inline-block; min-width: 150px; }
        .firma-section { margin-top: 40px; padding-top: 20px; border-top: 1px dashed #ccc; text-align:center;}
        .firma-linea { width: 300px; border-bottom: 1px solid #000; margin: 40px auto 5px auto; }
        .print-button { display: block; width: 100px; margin: 20px auto; padding: 10px; background-color: #007bff; color: white; text-align: center; border: none; border-radius: 5px; cursor: pointer; text-decoration:none; }

        @media print {
            body { background-color: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .container-ficha { width: 100%; margin: 0; padding: 0; border: none; }
            .print-button { display: none; }
            /* Estilos adicionales para impresión si son necesarios */
        }
    </style>
</head>
<body>
    <div class="container-ficha">
        <h1>RDM - Reparación de Dispositivos Móviles</h1>
        <h2>Comprobante de Recepción de Equipo</h2>

        <div class="header-info">
            <div><strong>Nº de Orden:</strong> <?php echo htmlspecialchars($equipo['equipo_id']); ?></div>
            <div><strong>Fecha Recepción:</strong> <?php echo $fecha_ingreso_formateada; ?></div>
        </div>

        <div class="section">
            <h3>Datos del Cliente</h3>
            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($equipo['cliente_nombre']); ?></p>
            <p><strong>DNI/CIF:</strong> <?php echo htmlspecialchars($equipo['cliente_dni']); ?></p>
            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($equipo['cliente_telefono'] ?? '-'); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($equipo['cliente_email'] ?? '-'); ?></p>
        </div>

        <div class="section">
            <h3>Datos del Equipo</h3>
            <p><strong>Tipo:</strong> <?php echo htmlspecialchars($equipo['tipo_equipo']); ?></p>
            <p><strong>Marca:</strong> <?php echo htmlspecialchars($equipo['marca']); ?></p>
            <p><strong>Modelo:</strong> <?php echo htmlspecialchars($equipo['modelo']); ?></p>
            <p><strong>Nº Serie/IMEI:</strong> <?php echo htmlspecialchars($equipo['numero_serie_imei'] ?? 'No especificado'); ?></p>
        </div>

        <div class="section">
            <h3>Diagnóstico y Estado</h3>
            <p><strong>Fallas Reportadas:</strong><br><?php echo nl2br(htmlspecialchars($equipo['fallas_reportadas'])); ?></p>
            <p><strong>Estado Físico (al ingreso):</strong><br><?php echo nl2br(htmlspecialchars($equipo['estado_fisico'] ?? 'No especificado')); ?></p>
            <p><strong>Accesorios Entregados:</strong><br><?php echo nl2br(htmlspecialchars($equipo['accesorios_entregados'] ?? 'Ninguno')); ?></p>
            <p><strong>Observaciones (uso interno):</strong><br><?php echo nl2br(htmlspecialchars($equipo['observaciones'] ?? 'Ninguna')); ?></p>
        </div>

        <div class="section">
            <h3>Asignación y Estado Actual</h3>
             <p><strong>Técnico Asignado:</strong> <?php echo htmlspecialchars($equipo['tecnico_nombre'] ?? 'No asignado'); ?></p>
             <p><strong>Estado Actual del Equipo:</strong> <?php echo htmlspecialchars($equipo['estado_actual'] ?? 'Indefinido'); ?></p>
        </div>

        <div class="firma-section">
            <div class="firma-linea"></div>
            <p>Firma del Cliente</p>
        </div>

        <button onclick="window.print();" class="print-button">Imprimir Ficha</button>
        <p style="text-align:center; font-size:12px; margin-top:20px;">
            Gracias por confiar en RDM. Conservar este comprobante.
        </p>
    </div>
</body>
</html>
