<?php
// clientes/agregar.php
require_once '../includes/funciones.php';
require_once '../includes/db.php'; // Para $enlace

verificar_login(); // Asegura que el usuario esté logueado

$mensaje = '';
$error_validacion = [];
$nombre = '';
$dni = '';
$email = '';
$telefono = '';
$direccion = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizar entradas
    $nombre = isset($_POST['nombre']) ? sanitizar_entrada($_POST['nombre']) : '';
    $dni = isset($_POST['dni']) ? sanitizar_entrada($_POST['dni']) : '';
    $email = isset($_POST['email']) ? sanitizar_entrada($_POST['email']) : '';
    $telefono = isset($_POST['telefono']) ? sanitizar_entrada($_POST['telefono']) : '';
    $direccion = isset($_POST['direccion']) ? sanitizar_entrada($_POST['direccion']) : '';

    // Validaciones
    if (empty($nombre)) {
        $error_validacion['nombre'] = "El nombre es obligatorio.";
    }
    if (empty($dni)) {
        $error_validacion['dni'] = "El DNI es obligatorio.";
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_validacion['email'] = "El formato del email no es válido.";
    }
    // Otras validaciones (longitud, formato DNI/teléfono, etc.) pueden agregarse aquí.

    if (empty($error_validacion)) {
        // Verificar si el DNI o Email ya existen (si deben ser únicos)
        $stmt_check = mysqli_prepare($enlace, "SELECT id FROM clientes WHERE dni = ? OR (email = ? AND email != '')");
        mysqli_stmt_bind_param($stmt_check, "ss", $dni, $email);
        mysqli_stmt_execute($stmt_check);
        $resultado_check = mysqli_stmt_get_result($stmt_check);

        if (mysqli_fetch_assoc($resultado_check)) {
            $mensaje = "<p class='mensaje-error'>Ya existe un cliente con el mismo DNI o Email.</p>";
            // Podríamos ser más específicos:
            // $cliente_existente = mysqli_fetch_assoc($resultado_check);
            // if ($cliente_existente['dni'] == $dni) $error_validacion['dni'] = "Este DNI ya está registrado.";
            // if ($cliente_existente['email'] == $email) $error_validacion['email'] = "Este Email ya está registrado.";
        } else {
            mysqli_stmt_close($stmt_check);

            $sql = "INSERT INTO clientes (nombre, dni, email, telefono, direccion) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($enlace, $sql);

            if (!$stmt) {
                $mensaje = "<p class='mensaje-error'>Error al preparar la consulta: " . mysqli_error($enlace) . "</p>";
            } else {
                mysqli_stmt_bind_param($stmt, "sssss", $nombre, $dni, $email, $telefono, $direccion);
                if (mysqli_stmt_execute($stmt)) {
                    $mensaje = "<p class='mensaje-exito'>Cliente agregado exitosamente.</p>";
                    // Limpiar campos después de éxito
                    $nombre = $dni = $email = $telefono = $direccion = '';
                } else {
                    $mensaje = "<p class='mensaje-error'>Error al agregar el cliente: " . mysqli_stmt_error($stmt) . "</p>";
                }
                mysqli_stmt_close($stmt);
            }
        }
        if (isset($stmt_check) && $stmt_check) mysqli_stmt_close($stmt_check);
    } else {
        $mensaje = "<p class='mensaje-error'>Por favor corrija los errores del formulario.</p>";
    }
}

// Incluir header después de la lógica PHP y antes de cualquier salida HTML
require_once '../includes/header.php';
?>

<div class="container-form">
    <h2>Agregar Nuevo Cliente</h2>

    <?php if (!empty($mensaje)) echo $mensaje; ?>

    <form action="agregar.php" method="POST" novalidate>
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
            <button type="submit" class="btn">Agregar Cliente</button>
            <a href="listar.php" class="btn btn-secondary">Ver Lista de Clientes</a>
        </div>
    </form>
</div>

<?php
require_once '../includes/footer.php';
// mysqli_close($enlace); // Considerar cerrar $enlace aquí si no se usa en footer.php
?>
