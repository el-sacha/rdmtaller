<?php
// tecnicos/agregar.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login();

$mensaje = '';
$error_validacion = [];
$nombre = '';
$especialidad = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = isset($_POST['nombre']) ? sanitizar_entrada($_POST['nombre']) : '';
    $especialidad = isset($_POST['especialidad']) ? sanitizar_entrada($_POST['especialidad']) : '';

    if (empty($nombre)) {
        $error_validacion['nombre'] = "El nombre del técnico es obligatorio.";
    }
    if (empty($especialidad)) {
        $error_validacion['especialidad'] = "La especialidad es obligatoria.";
    }

    if (empty($error_validacion)) {
        // Opcional: Verificar si ya existe un técnico con el mismo nombre
        $stmt_check = mysqli_prepare($enlace, "SELECT id FROM tecnicos WHERE nombre = ?");
        mysqli_stmt_bind_param($stmt_check, "s", $nombre);
        mysqli_stmt_execute($stmt_check);
        $resultado_check = mysqli_stmt_get_result($stmt_check);

        if (mysqli_fetch_assoc($resultado_check)) {
            $mensaje = "<p class='mensaje-error'>Ya existe un técnico con este nombre.</p>";
            mysqli_stmt_close($stmt_check);
        } else {
            if ($stmt_check) mysqli_stmt_close($stmt_check);

            $sql = "INSERT INTO tecnicos (nombre, especialidad) VALUES (?, ?)";
            $stmt = mysqli_prepare($enlace, $sql);

            if (!$stmt) {
                $mensaje = "<p class='mensaje-error'>Error al preparar la consulta: " . mysqli_error($enlace) . "</p>";
            } else {
                mysqli_stmt_bind_param($stmt, "ss", $nombre, $especialidad);
                if (mysqli_stmt_execute($stmt)) {
                    $mensaje = "<p class='mensaje-exito'>Técnico agregado exitosamente.</p>";
                    $nombre = $especialidad = ''; // Limpiar campos
                } else {
                    $mensaje = "<p class='mensaje-error'>Error al agregar el técnico: " . mysqli_stmt_error($stmt) . "</p>";
                }
                mysqli_stmt_close($stmt);
            }
        }
    } else {
        $mensaje = "<p class='mensaje-error'>Por favor corrija los errores del formulario.</p>";
    }
}

require_once '../includes/header.php';
?>

<div class="container-form">
    <h2>Agregar Nuevo Técnico</h2>

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
            <label for="especialidad">Especialidad:</label>
            <input type="text" name="especialidad" id="especialidad" value="<?php echo htmlspecialchars($especialidad); ?>" required>
            <?php if (isset($error_validacion['especialidad'])): ?>
                <span class="error-texto"><?php echo $error_validacion['especialidad']; ?></span>
            <?php endif; ?>
        </div>

        <div>
            <button type="submit" class="btn">Agregar Técnico</button>
            <a href="listar.php" class="btn btn-secondary">Ver Lista de Técnicos</a>
        </div>
    </form>
</div>

<?php
require_once '../includes/footer.php';
?>
