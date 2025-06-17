<?php
// tecnicos/editar.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login();

$mensaje = '';
$error_validacion = [];
$tecnico_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$nombre = '';
$especialidad = '';

if ($tecnico_id <= 0) {
    $_SESSION['mensaje_accion_tecnico'] = "ID de técnico no válido.";
    $_SESSION['mensaje_accion_tecnico_tipo'] = "mensaje-error";
    header("Location: listar.php");
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tecnico_id_post = isset($_POST['tecnico_id']) ? (int)$_POST['tecnico_id'] : 0;
    if ($tecnico_id_post !== $tecnico_id) {
        $_SESSION['mensaje_accion_tecnico'] = "Error: ID incorrecto en el formulario.";
        $_SESSION['mensaje_accion_tecnico_tipo'] = "mensaje-error";
        header("Location: listar.php");
        return;
    }

    $nombre = isset($_POST['nombre']) ? sanitizar_entrada($_POST['nombre']) : '';
    $especialidad = isset($_POST['especialidad']) ? sanitizar_entrada($_POST['especialidad']) : '';

    if (empty($nombre)) $error_validacion['nombre'] = "El nombre es obligatorio.";
    if (empty($especialidad)) $error_validacion['especialidad'] = "La especialidad es obligatoria.";

    if (empty($error_validacion)) {
        // Opcional: Verificar si el nombre ya existe para OTRO técnico
        $stmt_check = mysqli_prepare($enlace, "SELECT id FROM tecnicos WHERE nombre = ? AND id != ?");
        mysqli_stmt_bind_param($stmt_check, "si", $nombre, $tecnico_id);
        mysqli_stmt_execute($stmt_check);
        $resultado_check = mysqli_stmt_get_result($stmt_check);

        if (mysqli_fetch_assoc($resultado_check)) {
            $mensaje = "<p class='mensaje-error'>Ya existe OTRO técnico con este nombre.</p>";
            mysqli_stmt_close($stmt_check);
        } else {
            if($stmt_check) mysqli_stmt_close($stmt_check);

            $sql = "UPDATE tecnicos SET nombre = ?, especialidad = ? WHERE id = ?";
            $stmt = mysqli_prepare($enlace, $sql);
            if (!$stmt) {
                $mensaje = "<p class='mensaje-error'>Error al preparar la consulta: " . mysqli_error($enlace) . "</p>";
            } else {
                mysqli_stmt_bind_param($stmt, "ssi", $nombre, $especialidad, $tecnico_id);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['mensaje_accion_tecnico'] = "Técnico actualizado exitosamente.";
                    $_SESSION['mensaje_accion_tecnico_tipo'] = "mensaje-exito";
                    header("Location: listar.php");
                    return;
                } else {
                    $mensaje = "<p class='mensaje-error'>Error al actualizar el técnico: " . mysqli_stmt_error($stmt) . "</p>";
                }
                mysqli_stmt_close($stmt);
            }
        }
    } else {
        $mensaje = "<p class='mensaje-error'>Por favor corrija los errores.</p>";
    }
} else {
    $stmt_select = mysqli_prepare($enlace, "SELECT nombre, especialidad FROM tecnicos WHERE id = ?");
    if (!$stmt_select) {
        $_SESSION['mensaje_accion_tecnico'] = "Error al preparar consulta para cargar técnico: " . mysqli_error($enlace);
        $_SESSION['mensaje_accion_tecnico_tipo'] = "mensaje-error";
        header("Location: listar.php");
        return;
    }
    mysqli_stmt_bind_param($stmt_select, "i", $tecnico_id);
    mysqli_stmt_execute($stmt_select);
    $resultado_select = mysqli_stmt_get_result($stmt_select);
    $tecnico_actual = mysqli_fetch_assoc($resultado_select);
    mysqli_stmt_close($stmt_select);

    if (!$tecnico_actual) {
        $_SESSION['mensaje_accion_tecnico'] = "Técnico no encontrado.";
        $_SESSION['mensaje_accion_tecnico_tipo'] = "mensaje-error";
        header("Location: listar.php");
        return;
    }
    $nombre = $tecnico_actual['nombre'];
    $especialidad = $tecnico_actual['especialidad'];
}

require_once '../includes/header.php';
?>

<div class="container-form">
    <h2>Editar Técnico</h2>

    <?php if (!empty($mensaje)) echo $mensaje; ?>

    <form action="editar.php?id=<?php echo $tecnico_id; ?>" method="POST" novalidate>
        <input type="hidden" name="tecnico_id" value="<?php echo $tecnico_id; ?>">

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
            <button type="submit" class="btn">Guardar Cambios</button>
            <a href="listar.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php
require_once '../includes/footer.php';
?>
