<?php
// admin/datos_fiscales.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login();
// Asegurar que solo admin pueda acceder
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    $_SESSION['error_accion_equipo'] = "Acceso denegado. Permisos insuficientes."; // Usar un mensaje más genérico o específico de admin
    header("Location: ../dashboard.php");
    return;
}

$mensaje = '';
$datos_fiscales = null;

// Fetch current fiscal data (assuming one row, id=1 or first row)
$stmt_fetch = mysqli_prepare($enlace, "SELECT id, nombre_empresa, cuit, domicilio_comercial, condicion_iva, punto_venta, ingresos_brutos, fecha_inicio_actividades, logo_url FROM datos_fiscales_empresa ORDER BY id LIMIT 1");
if ($stmt_fetch) {
    mysqli_stmt_execute($stmt_fetch);
    $resultado_fetch = mysqli_stmt_get_result($stmt_fetch);
    $datos_fiscales = mysqli_fetch_assoc($resultado_fetch);
    mysqli_stmt_close($stmt_fetch);
}

if (!$datos_fiscales) {
    // Si no hay datos, podría ser un error o la primera vez.
    // Para este caso, se podría permitir crear una nueva entrada si no existe.
    // Por ahora, asumimos que la inserción inicial en SQL la creó.
    $mensaje = "<div class='alert alert-warning'>No se encontraron datos fiscales. Verifique la configuración inicial.</div>";
    // Aquí podríamos inicializar $datos_fiscales con valores vacíos para el formulario si quisiéramos permitir crear desde aquí
    $datos_fiscales = [
        'id' => null, 'nombre_empresa' => '', 'cuit' => '', 'domicilio_comercial' => '',
        'condicion_iva' => '', 'punto_venta' => '', 'ingresos_brutos' => '',
        'fecha_inicio_actividades' => '', 'logo_url' => ''
    ];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_df = isset($_POST['id_df']) ? (int)$_POST['id_df'] : $datos_fiscales['id']; // Usar el ID existente
    $nombre_empresa = sanitizar_entrada($_POST['nombre_empresa']);
    $cuit = sanitizar_entrada($_POST['cuit']);
    $domicilio_comercial = sanitizar_entrada($_POST['domicilio_comercial']);
    $condicion_iva = sanitizar_entrada($_POST['condicion_iva']);
    $punto_venta = sanitizar_entrada($_POST['punto_venta']);
    $ingresos_brutos = sanitizar_entrada($_POST['ingresos_brutos']);
    $fecha_inicio_actividades = sanitizar_entrada($_POST['fecha_inicio_actividades']);
    $logo_url = sanitizar_entrada($_POST['logo_url']); // Validar como URL si es necesario

    // Validaciones (básicas)
    if (empty($nombre_empresa) || empty($cuit) || empty($domicilio_comercial) || empty($condicion_iva) || empty($punto_venta)) {
        $mensaje = "<div class='alert alert-danger'>Los campos Nombre Empresa, CUIT, Domicilio, Condición IVA y Punto de Venta son obligatorios.</div>";
    } else {
        // Si $id_df es null o no existe, sería un INSERT, sino UPDATE.
        // Por simplicidad y dado que se asume una fila, siempre será UPDATE del ID existente.
        if ($id_df) {
            $sql = "UPDATE datos_fiscales_empresa SET nombre_empresa=?, cuit=?, domicilio_comercial=?, condicion_iva=?, punto_venta=?, ingresos_brutos=?, fecha_inicio_actividades=?, logo_url=? WHERE id=?";
            $stmt_update = mysqli_prepare($enlace, $sql);
            mysqli_stmt_bind_param($stmt_update, "ssssssssi", $nombre_empresa, $cuit, $domicilio_comercial, $condicion_iva, $punto_venta, $ingresos_brutos, $fecha_inicio_actividades, $logo_url, $id_df);
        } else {
            // Lógica de INSERT si se permite crear una nueva entrada (no implementado aquí para mantenerlo simple)
             $mensaje = "<div class='alert alert-danger'>Error: No se pudo determinar el ID de los datos fiscales para actualizar.</div>";
        }

        if (isset($stmt_update) && mysqli_stmt_execute($stmt_update)) {
            $mensaje = "<div class='alert alert-success'>Datos fiscales actualizados correctamente.</div>";
            mysqli_stmt_close($stmt_update);
            // Refrescar datos para el formulario
            $stmt_fetch_refresh = mysqli_prepare($enlace, "SELECT id, nombre_empresa, cuit, domicilio_comercial, condicion_iva, punto_venta, ingresos_brutos, fecha_inicio_actividades, logo_url FROM datos_fiscales_empresa WHERE id = ?");
            mysqli_stmt_bind_param($stmt_fetch_refresh, "i", $id_df);
            mysqli_stmt_execute($stmt_fetch_refresh);
            $resultado_fetch_refresh = mysqli_stmt_get_result($stmt_fetch_refresh);
            $datos_fiscales = mysqli_fetch_assoc($resultado_fetch_refresh);
            mysqli_stmt_close($stmt_fetch_refresh);

        } elseif (isset($stmt_update)) {
            $mensaje = "<div class='alert alert-danger'>Error al actualizar datos fiscales: " . mysqli_stmt_error($stmt_update) . "</div>";
             mysqli_stmt_close($stmt_update);
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Configuración de Datos Fiscales de la Empresa</h2>
    <?php if (!empty($mensaje)) echo $mensaje; ?>

    <?php if ($datos_fiscales): ?>
    <form action="datos_fiscales.php" method="POST" class="mt-3">
        <input type="hidden" name="id_df" value="<?php echo htmlspecialchars($datos_fiscales['id'] ?? ''); ?>">

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="nombre_empresa" class="form-label">Nombre de la Empresa:</label>
                <input type="text" class="form-control" id="nombre_empresa" name="nombre_empresa" value="<?php echo htmlspecialchars($datos_fiscales['nombre_empresa'] ?? ''); ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="cuit" class="form-label">CUIT:</label>
                <input type="text" class="form-control" id="cuit" name="cuit" value="<?php echo htmlspecialchars($datos_fiscales['cuit'] ?? ''); ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label for="domicilio_comercial" class="form-label">Domicilio Comercial:</label>
            <input type="text" class="form-control" id="domicilio_comercial" name="domicilio_comercial" value="<?php echo htmlspecialchars($datos_fiscales['domicilio_comercial'] ?? ''); ?>" required>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="condicion_iva" class="form-label">Condición frente al IVA:</label>
                <input type="text" class="form-control" id="condicion_iva" name="condicion_iva" value="<?php echo htmlspecialchars($datos_fiscales['condicion_iva'] ?? ''); ?>" placeholder="Ej: Responsable Monotributo" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="punto_venta" class="form-label">Punto de Venta (para facturas):</label>
                <input type="text" class="form-control" id="punto_venta" name="punto_venta" value="<?php echo htmlspecialchars($datos_fiscales['punto_venta'] ?? ''); ?>" placeholder="Ej: 0001" required>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="ingresos_brutos" class="form-label">Ingresos Brutos:</label>
                <input type="text" class="form-control" id="ingresos_brutos" name="ingresos_brutos" value="<?php echo htmlspecialchars($datos_fiscales['ingresos_brutos'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label for="fecha_inicio_actividades" class="form-label">Fecha Inicio de Actividades:</label>
                <input type="date" class="form-control" id="fecha_inicio_actividades" name="fecha_inicio_actividades" value="<?php echo htmlspecialchars($datos_fiscales['fecha_inicio_actividades'] ?? ''); ?>">
            </div>
        </div>
         <div class="mb-3">
            <label for="logo_url" class="form-label">URL del Logo (opcional):</label>
            <input type="url" class="form-control" id="logo_url" name="logo_url" value="<?php echo htmlspecialchars($datos_fiscales['logo_url'] ?? ''); ?>" placeholder="https://ejemplo.com/logo.png">
        </div>

        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
    </form>
    <?php else: ?>
        <p>No se pueden mostrar los datos fiscales en este momento. Contacte al administrador.</p>
    <?php endif; ?>
</div>

<?php
require_once '../includes/footer.php';
?>
