<?php
// equipos/imprimir_ficha.php
require_once '../includes/funciones.php';
require_once '../includes/db.php'; // $enlace

verificar_login(); // Habilitar la verificación de login

$equipo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($equipo_id <= 0) {
    $_SESSION['error_accion_equipo'] = "ID de equipo no válido.";
    header("Location: listar_equipos.php");
    return;
}

// --- Lógica de autorización ---
$usuario_rol = $_SESSION['rol'] ?? 'desconocido';
$usuario_actual_tecnico_id = null;
$equipo = null; // Inicializar $equipo

if ($usuario_rol === 'tecnico') {
    $stmt_user_tecnico = mysqli_prepare($enlace, "SELECT tecnico_id FROM usuarios WHERE id = ?");
    if ($stmt_user_tecnico) {
        mysqli_stmt_bind_param($stmt_user_tecnico, "i", $_SESSION['usuario_id']);
        mysqli_stmt_execute($stmt_user_tecnico);
        $res_user_tecnico = mysqli_stmt_get_result($stmt_user_tecnico);
        if ($row_user_tecnico = mysqli_fetch_assoc($res_user_tecnico)) {
            $usuario_actual_tecnico_id = $row_user_tecnico['tecnico_id'];
        }
        mysqli_stmt_close($stmt_user_tecnico);
    }

    if (!$usuario_actual_tecnico_id) {
        $_SESSION['error_accion_equipo'] = "Error: No se pudo determinar el ID de técnico para el usuario actual o el usuario no está vinculado a un técnico.";
        header("Location: listar_equipos.php");
        return;
    }
}

// Fetch equipo data
$sql = "SELECT
            e.*,  -- e.tecnico_asignado_id se incluye aquí
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
    error_log("Error al preparar la consulta para imprimir ficha: " . mysqli_error($enlace));
    $_SESSION['error_accion_equipo'] = "Error al cargar datos del equipo. Intente más tarde.";
    header("Location: listar_equipos.php");
    return;
}
mysqli_stmt_bind_param($stmt, "i", $equipo_id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$equipo = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

if (!$equipo) {
    $_SESSION['error_accion_equipo'] = "Ficha de equipo no encontrada. ID: " . htmlspecialchars($equipo_id);
    header("Location: listar_equipos.php");
    return;
}

// Aplicar autorización si es técnico
if ($usuario_rol === 'tecnico') {
    if ($equipo['tecnico_asignado_id'] != $usuario_actual_tecnico_id) {
        $_SESSION['error_accion_equipo'] = "Acceso denegado: No está asignado a este equipo.";
        header("Location: listar_equipos.php");
        return;
    }
}
// --- Fin lógica de autorización ---

$fecha_ingreso_formateada = date("d/m/Y H:i:s", strtotime($equipo['fecha_ingreso']));

// Determinar path para JS assets (signature_pad.umd.min.js)
// Asumiendo que imprimir_ficha.php está en rdm/equipos/ y assets en rdm/assets/
$assets_path_prefix = '../assets';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de Ingreso Equipo N° <?php echo htmlspecialchars($equipo['id']); ?></title>
    <!-- Incluir Bootstrap CSS si se desea un estilo más allá del básico para impresión -->
    <link href="<?php echo $assets_path_prefix; ?>/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container-ficha { max-width: 800px; margin: auto; padding: 20px; border: 1px solid #ccc; background-color: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2 { text-align: center; }
        h1 { font-size: 24px; margin-bottom: 5px; }
        h2 { font-size: 20px; margin-bottom: 20px; color: #555; }
        .header-info { display: flex; justify-content: space-between; margin-bottom: 20px; padding-bottom:10px; border-bottom:1px solid #eee;}
        .section { margin-bottom: 15px; padding-bottom:10px; border-bottom:1px dashed #eee;}
        .section:last-of-type { border-bottom: none; }
        .section h3 { font-size: 16px; margin-bottom: 8px; color: #333; border-bottom: 1px solid #f0f0f0; padding-bottom: 5px;}
        .section p { margin: 2px 0; line-height:1.5;}
        .section p strong { display: inline-block; min-width: 140px; color:#444; }

        #signature-pad-container { margin-top: 20px; text-align: center; }
        #signature-canvas { border: 1px solid #000; cursor: crosshair; }
        .signature-image { max-width: 350px; max-height: 150px; border: 1px solid #eee; margin: 10px auto; display:block; }
        .firma-actions button { margin: 5px; }

        .print-button-container { text-align: center; margin-top: 20px; }
        .print-button { padding: 10px 20px; font-size: 16px; }

        @media print {
            body { background-color: #fff; margin: 0; }
            .container-ficha { width: 100%; margin: 0; padding: 0; border: none; box-shadow:none; }
            .print-button-container, .firma-actions, .no-print { display: none !important; }
            #signature-pad-container { margin-top:5px; page-break-inside: avoid; } /* Evitar corte de página en la firma */
            .signature-image { max-width: 300px; max-height: 120px; }
             h1, h2, h3 {margin-bottom:8px;}
        }
    </style>
</head>
<body>
    <div class="container-ficha" id="printable-area">
        <h1>RDM - Reparación de Dispositivos Móviles</h1>
        <h2>Comprobante de Recepción de Equipo</h2>

        <div class="header-info">
            <div><strong>Nº Orden:</strong> <?php echo htmlspecialchars($equipo['id']); ?></div>
            <div><strong>Fecha Recepción:</strong> <?php echo $fecha_ingreso_formateada; ?></div>
        </div>

        <div class="section">
            <h3>Datos del Cliente</h3>
            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($equipo['cliente_nombre']); ?></p>
            <p><strong>DNI/CIF:</strong> <?php echo htmlspecialchars($equipo['cliente_dni']); ?></p>
            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($equipo['cliente_telefono'] ?? '-'); ?></p>
        </div>

        <div class="section">
            <h3>Datos del Equipo</h3>
            <p><strong>Tipo:</strong> <?php echo htmlspecialchars($equipo['tipo_equipo']); ?></p>
            <p><strong>Marca:</strong> <?php echo htmlspecialchars($equipo['marca']); ?> <strong>Modelo:</strong> <?php echo htmlspecialchars($equipo['modelo']); ?></p>
            <p><strong>Nº Serie/IMEI:</strong> <?php echo htmlspecialchars($equipo['numero_serie_imei'] ?? 'No especificado'); ?></p>
        </div>

        <div class="section">
            <h3>Diagnóstico y Estado</h3>
            <p><strong>Fallas Reportadas:</strong> <?php echo nl2br(htmlspecialchars($equipo['fallas_reportadas'])); ?></p>
            <p><strong>Estado Físico (al ingreso):</strong> <?php echo nl2br(htmlspecialchars($equipo['estado_fisico'] ?? 'No especificado')); ?></p>
            <p><strong>Accesorios Entregados:</strong> <?php echo nl2br(htmlspecialchars($equipo['accesorios_entregados'] ?? 'Ninguno')); ?></p>
        </div>

        <div class="section">
            <h3>Asignación y Estado Inicial</h3>
             <p><strong>Técnico Asignado:</strong> <?php echo htmlspecialchars($equipo['tecnico_nombre'] ?? 'No asignado'); ?></p>
             <p><strong>Estado Inicial:</strong> <?php echo htmlspecialchars($equipo['estado_actual'] ?? 'Indefinido'); ?></p>
        </div>

        <div id="signature-pad-container" data-equipo-id="<?php echo $equipo['id']; ?>">
            <h3>Firma del Cliente:</h3>
            <?php if (!empty($equipo['firma_cliente_ruta'])): ?>
                <div id="firma-existente">
                    <img src="../<?php echo htmlspecialchars($equipo['firma_cliente_ruta']); ?>?t=<?php echo time(); ?>" alt="Firma del Cliente" class="signature-image">
                    <div class="firma-actions no-print">
                        <button id="borrar-firma-btn" class="btn btn-sm btn-danger">Borrar Firma y Volver a Firmar</button>
                    </div>
                </div>
                <div id="canvas-container" style="display:none;">
                     <canvas id="signature-canvas" width="350" height="150"></canvas>
                     <div class="firma-actions no-print mt-2">
                        <button id="guardar-firma-btn" class="btn btn-sm btn-primary">Guardar Firma</button>
                        <button id="limpiar-firma-btn" class="btn btn-sm btn-secondary">Limpiar</button>
                    </div>
                </div>
            <?php else: ?>
                <div id="canvas-container">
                    <canvas id="signature-canvas" width="350" height="150"></canvas>
                    <div class="firma-actions no-print mt-2">
                        <button id="guardar-firma-btn" class="btn btn-sm btn-primary">Guardar Firma</button>
                        <button id="limpiar-firma-btn" class="btn btn-sm btn-secondary">Limpiar</button>
                    </div>
                </div>
                 <div id="firma-existente" style="display:none;">
                    <img src="" alt="Firma del Cliente" class="signature-image">
                     <div class="firma-actions no-print">
                        <button id="borrar-firma-btn" class="btn btn-sm btn-danger">Borrar Firma y Volver a Firmar</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
         <p class="text-center small mt-3">Declaro conocer y aceptar los términos y condiciones del servicio técnico.</p>
    </div>

    <div class="print-button-container no-print">
        <button onclick="window.print();" class="btn btn-primary print-button">Imprimir Ficha</button>
        <a href="listar_equipos.php" class="btn btn-secondary print-button">Volver al Listado</a>
    </div>

    <script src="<?php echo $assets_path_prefix; ?>/js/signature_pad.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const canvas = document.getElementById('signature-canvas');
        const signaturePadContainer = document.getElementById('signature-pad-container');
        const equipoId = signaturePadContainer.dataset.equipoId;
        const guardarBtn = document.getElementById('guardar-firma-btn');
        const limpiarBtn = document.getElementById('limpiar-firma-btn');
        const borrarBtn = document.getElementById('borrar-firma-btn');
        const firmaExistenteDiv = document.getElementById('firma-existente');
        const canvasContainerDiv = document.getElementById('canvas-container');
        let signaturePad = null;

        function initializeSignaturePad() {
            if (canvas && !signaturePad) { // Solo inicializar si el canvas está visible y no hay instancia
                 signaturePad = new SignaturePad(canvas, {
                    backgroundColor: 'rgb(255, 255, 255)' // Fondo blanco
                });
            }
        }

        // Inicializar si el canvas está visible al cargar (no hay firma previa)
        if (canvasContainerDiv && window.getComputedStyle(canvasContainerDiv).display !== 'none') {
            initializeSignaturePad();
        }

        if (limpiarBtn && signaturePad) {
            limpiarBtn.addEventListener('click', function () {
                signaturePad.clear();
            });
        }

        if (guardarBtn && signaturePadContainer) { // Verificar signaturePadContainer para equipoId
            guardarBtn.addEventListener('click', function () {
                if (!equipoId || parseInt(equipoId, 10) <= 0) {
                    alert('Error: No se pudo obtener el ID del equipo para guardar la firma. Recargue la página.');
                    return;
                }
                if (!signaturePad || signaturePad.isEmpty()) {
                    alert("Por favor, provea una firma primero.");
                } else {
                    const dataURL = signaturePad.toDataURL('image/png');
                    fetch('guardar_firma.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `equipo_id=${equipoId}&signature_data=${encodeURIComponent(dataURL)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // alert('Firma guardada exitosamente.');
                            // Actualizar UI: mostrar imagen, ocultar canvas
                            if(firmaExistenteDiv) {
                                firmaExistenteDiv.querySelector('img').src = '../' + data.filePath + '?t=' + new Date().getTime();
                                firmaExistenteDiv.style.display = 'block';
                            }
                            if(canvasContainerDiv) canvasContainerDiv.style.display = 'none';
                            if(signaturePad) signaturePad.off(); // Desactivar eventos del pad
                            signaturePad = null; // Destruir instancia para que se reinicie si se borra

                        } else {
                            alert('Error al guardar firma: ' + (data.message || 'Error desconocido.'));
                        }
                    })
                    .catch(error => console.error('Error en AJAX:', error));
                }
            });
        }

        if (borrarBtn && signaturePadContainer) {
             borrarBtn.addEventListener('click', function () {
                if (!equipoId || parseInt(equipoId, 10) <= 0) {
                    alert('Error: No se pudo obtener el ID del equipo para borrar la firma. Recargue la página.');
                    return;
                }
                if (!confirm("¿Está seguro de que desea borrar la firma existente?")) return;
                fetch('borrar_firma.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `equipo_id=${equipoId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // alert('Firma borrada.');
                         if(firmaExistenteDiv) {
                            firmaExistenteDiv.querySelector('img').src = '';
                            firmaExistenteDiv.style.display = 'none';
                        }
                        if(canvasContainerDiv) canvasContainerDiv.style.display = 'block';
                        initializeSignaturePad(); // Re-inicializar el pad
                        if(signaturePad) signaturePad.clear();
                    } else {
                        alert('Error al borrar firma: ' + (data.message || 'Error desconocido.'));
                    }
                })
                .catch(error => console.error('Error en AJAX:', error));
            });
        }
    });
    </script>
</body>
</html>
