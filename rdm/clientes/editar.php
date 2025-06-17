<?php
// clientes/editar.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login();

$mensaje = '';
$error_validacion = [];
$cliente_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Variables para pre-llenar el formulario
$nombre = '';
$dni = '';
$email = '';
$telefono = '';
$direccion = '';

if ($cliente_id <= 0) {
    $_SESSION['mensaje_accion_cliente'] = "ID de cliente no válido.";
    $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
    header("Location: listar.php");
    return; // Usar return en lugar de exit para la herramienta
}

// --- Lógica de POST (Actualización) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // El ID del cliente debe venir del POST también para asegurar que es el correcto
    $cliente_id_post = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
    if ($cliente_id_post !== $cliente_id) {
        $_SESSION['mensaje_accion_cliente'] = "Error: Intento de modificación de ID incorrecto.";
        $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
        header("Location: listar.php");
        return;
    }

    $nombre = isset($_POST['nombre']) ? sanitizar_entrada($_POST['nombre']) : '';
    $dni = isset($_POST['dni']) ? sanitizar_entrada($_POST['dni']) : '';
    $email = isset($_POST['email']) ? sanitizar_entrada($_POST['email']) : '';
    $telefono = isset($_POST['telefono']) ? sanitizar_entrada($_POST['telefono']) : '';
    $direccion = isset($_POST['direccion']) ? sanitizar_entrada($_POST['direccion']) : '';

    // Validaciones
    if (empty($nombre)) $error_validacion['nombre'] = "El nombre es obligatorio.";
    if (empty($dni)) $error_validacion['dni'] = "El DNI es obligatorio.";
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_validacion['email'] = "El formato del email no es válido.";
    }

    if (empty($error_validacion)) {
        // Verificar si el DNI o Email ya existen para OTRO cliente
        $stmt_check = mysqli_prepare($enlace, "SELECT id FROM clientes WHERE (dni = ? OR (email = ? AND email != '')) AND id != ?");
        mysqli_stmt_bind_param($stmt_check, "ssi", $dni, $email, $cliente_id);
        mysqli_stmt_execute($stmt_check);
        $resultado_check = mysqli_stmt_get_result($stmt_check);

        if (mysqli_fetch_assoc($resultado_check)) {
            $mensaje = "<p class='mensaje-error'>Ya existe OTRO cliente con el mismo DNI o Email.</p>";
        } else {
            mysqli_stmt_close($stmt_check);

            $sql = "UPDATE clientes SET nombre = ?, dni = ?, email = ?, telefono = ?, direccion = ? WHERE id = ?";
            $stmt = mysqli_prepare($enlace, $sql);
            if (!$stmt) {
                $mensaje = "<p class='mensaje-error'>Error al preparar la consulta de actualización: " . mysqli_error($enlace) . "</p>";
            } else {
                mysqli_stmt_bind_param($stmt, "sssssi", $nombre, $dni, $email, $telefono, $direccion, $cliente_id);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['mensaje_accion_cliente'] = "Cliente actualizado exitosamente.";
                    $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-exito";
                    header("Location: listar.php");
                    return;
                } else {
                    $mensaje = "<p class='mensaje-error'>Error al actualizar el cliente: " . mysqli_stmt_error($stmt) . "</p>";
                }
                mysqli_stmt_close($stmt);
            }
        }
        if (isset($stmt_check) && $stmt_check) mysqli_stmt_close($stmt_check);
    } else {
        $mensaje = "<p class='mensaje-error'>Por favor corrija los errores del formulario.</p>";
    }
} else {
    // --- Lógica de GET (Cargar datos del cliente para editar) ---
    $stmt_select = mysqli_prepare($enlace, "SELECT nombre, dni, email, telefono, direccion FROM clientes WHERE id = ?");
    if (!$stmt_select) {
        $_SESSION['mensaje_accion_cliente'] = "Error al preparar la consulta de selección: " . mysqli_error($enlace);
        $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
        header("Location: listar.php");
        return;
    }
    mysqli_stmt_bind_param($stmt_select, "i", $cliente_id);
    mysqli_stmt_execute($stmt_select);
    $resultado_select = mysqli_stmt_get_result($stmt_select);
    $cliente_actual = mysqli_fetch_assoc($resultado_select);
    mysqli_stmt_close($stmt_select);

    if (!$cliente_actual) {
        $_SESSION['mensaje_accion_cliente'] = "Cliente no encontrado.";
        $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
        header("Location: listar.php");
        return;
    }
    // Pre-llenar variables para el formulario
    $nombre = $cliente_actual['nombre'];
    $dni = $cliente_actual['dni'];
    $email = $cliente_actual['email'];
    $telefono = $cliente_actual['telefono'];
    $direccion = $cliente_actual['direccion'];
}


require_once '../includes/header.php';
?>

<div class="container-form">
    <h2>Editar Cliente</h2>

    <?php if (!empty($mensaje)) echo $mensaje; ?>

    <form action="editar.php?id=<?php echo $cliente_id; ?>" method="POST" novalidate>
        <input type="hidden" name="cliente_id" value="<?php echo $cliente_id; ?>">

        <div class="form-grupo">
            <label for="nombre">Nombre Completo:</label>
            <input type="text" name="nombre" id="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required>
            <?php if (isset($error_validacion['nombre'])): ?>
                <span class="error-texto"><?php echo $error_validacion['nombre']; ?></span>
            <?php endif; ?>
        </div>

        <div class="form-grupo">
            <label for="dni">DNI/CIF:</label>
            <input type="text" name="dni" id="dni" value="<?php echo htmlspecialchars($dni); ?>" required>
            <?php if (isset($error_validacion['dni'])): ?>
                <span class="error-texto"><?php echo $error_validacion['dni']; ?></span>
            <?php endif; ?>
        </div>

        <div class="form-grupo">
            <label for="email">Email:</label>
            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($email); ?>">
            <?php if (isset($error_validacion['email'])): ?>
                <span class="error-texto"><?php echo $error_validacion['email']; ?></span>
            <?php endif; ?>
        </div>

        <div class="form-grupo">
            <label for="telefono">Teléfono:</label>
            <input type="tel" name="telefono" id="telefono" value="<?php echo htmlspecialchars($telefono); ?>">
        </div>

        <div class="form-grupo">
            <label for="direccion">Dirección:</label>
            <textarea name="direccion" id="direccion" rows="3"><?php echo htmlspecialchars($direccion); ?></textarea>
        </div>

        <div>
            <button type="submit" class="btn">Guardar Cambios</button>
            <a href="listar.php" class="btn btn-secondary">Cancelar y Volver al Listado</a>
        </div>
    </form>
</div>

<?php
require_once '../includes/footer.php';
// mysqli_close($enlace);
?>
