<?php
// clientes/editar.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login();

$csrf_token = generar_token_csrf();
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
    if (!isset($_POST['csrf_token']) || !validar_token_csrf($_POST['csrf_token'])) {
        $mensaje = "<p class='mensaje-error'>Error de validación CSRF. Inténtelo de nuevo.</p>";
    } else {
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
        if ($stmt_check) {
            mysqli_stmt_bind_param($stmt_check, "ssi", $dni, $email, $cliente_id);
            mysqli_stmt_execute($stmt_check);
            $resultado_check = mysqli_stmt_get_result($stmt_check);

            if (mysqli_fetch_assoc($resultado_check)) {
                $mensaje = "<p class='mensaje-error'>Ya existe OTRO cliente con el mismo DNI o Email.</p>";
            } else {
                mysqli_stmt_close($stmt_check);
                $stmt_check = null;

                $sql = "UPDATE clientes SET nombre = ?, dni = ?, email = ?, telefono = ?, direccion = ? WHERE id = ?";
                $stmt = mysqli_prepare($enlace, $sql);
                if (!$stmt) {
                    $mensaje = log_db_error_y_mostrar_mensaje_generico(mysqli_error($enlace));
                } else {
                    mysqli_stmt_bind_param($stmt, "sssssi", $nombre, $dni, $email, $telefono, $direccion, $cliente_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION['mensaje_accion_cliente'] = "Cliente actualizado exitosamente.";
                        $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-exito";
                        header("Location: listar.php");
                        return;
                    } else {
                        $mensaje = log_db_error_y_mostrar_mensaje_generico(mysqli_error($enlace), mysqli_stmt_error($stmt));
                    }
                    mysqli_stmt_close($stmt);
                }
            }
            if (isset($stmt_check) && $stmt_check) mysqli_stmt_close($stmt_check);
        } else { // Falló mysqli_prepare para $stmt_check
            $mensaje = log_db_error_y_mostrar_mensaje_generico(mysqli_error($enlace), "", "Error al verificar datos duplicados.");
        }
    } // Cierre if (empty($error_validacion))
    } // Cierre del else de validación CSRF

    if (empty($mensaje) && !empty($error_validacion)) { // Si no hay mensaje de CSRF o DB, pero sí de validación de campos
         $mensaje = "<p class='mensaje-error'>Por favor corrija los errores del formulario.</p>";
    }
} // Cierre del if POST

// --- Lógica de GET (Cargar datos del cliente para editar) ---
// Se ejecuta si no es POST, o si es POST pero hubo error CSRF o de DB (para recargar datos con el mensaje)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($mensaje)) {
    // Si es un POST fallido con error $mensaje, $nombre, $dni, etc., ya tienen los valores del POST
    // y no queremos sobreescribirlos con los de la BD. Solo cargamos de BD si no es POST.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $stmt_select = mysqli_prepare($enlace, "SELECT nombre, dni, email, telefono, direccion FROM clientes WHERE id = ?");
        if (!$stmt_select) {
            // Para errores en GET, el mensaje se mostrará en la página actual si $mensaje está vacío,
            // o podemos redirigir con error en sesión si $mensaje ya tiene algo (ej. error CSRF)
            $error_msg_get = log_db_error_y_mostrar_mensaje_generico(mysqli_error($enlace), "", "Error al cargar datos del cliente.");
            if(empty($mensaje)) {
                $mensaje = $error_msg_get;
            } else {
                 // Si ya hay un mensaje (ej. CSRF), priorizamos ese y el error de carga de datos se loguea pero no se muestra directamente
                 error_log("Error DB GET (no mostrado directamente al user por mensaje CSRF existente): " . mysqli_error($enlace));
            }
            // Considerar redirigir si el error es crítico y no se pueden mostrar datos:
            // $_SESSION['mensaje_accion_cliente'] = $error_msg_get;
            // $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
            // header("Location: listar.php");
            // return;
        } else {
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
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
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
