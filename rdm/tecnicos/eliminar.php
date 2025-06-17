<?php
// tecnicos/eliminar.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login();

// IMPORTANTE: Añadir CSRF token y método POST para seguridad.
// Confirmación JS en listar.php, pero una confirmación server-side sería mejor.

$tecnico_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($tecnico_id <= 0) {
    $_SESSION['mensaje_accion_tecnico'] = "ID de técnico no válido.";
    $_SESSION['mensaje_accion_tecnico_tipo'] = "mensaje-error";
    header("Location: listar.php");
    return;
}

// ANTES DE ELIMINAR: Actualizar usuarios que tengan este tecnico_id a NULL
$sql_update_users = "UPDATE usuarios SET tecnico_id = NULL WHERE tecnico_id = ?";
$stmt_update = mysqli_prepare($enlace, $sql_update_users);
if (!$stmt_update) {
    $_SESSION['mensaje_accion_tecnico'] = "Error al preparar actualización de usuarios: " . mysqli_error($enlace);
    $_SESSION['mensaje_accion_tecnico_tipo'] = "mensaje-error";
    header("Location: listar.php");
    return;
}
mysqli_stmt_bind_param($stmt_update, "i", $tecnico_id);
if (!mysqli_stmt_execute($stmt_update)) {
    $_SESSION['mensaje_accion_tecnico'] = "Error al actualizar usuarios asignados: " . mysqli_stmt_error($stmt_update);
    $_SESSION['mensaje_accion_tecnico_tipo'] = "mensaje-error";
    mysqli_stmt_close($stmt_update);
    header("Location: listar.php");
    return;
}
mysqli_stmt_close($stmt_update);


// PROCEDER CON LA ELIMINACIÓN DEL TÉCNICO
$sql_delete = "DELETE FROM tecnicos WHERE id = ?";
$stmt_delete = mysqli_prepare($enlace, $sql_delete);

if (!$stmt_delete) {
    $_SESSION['mensaje_accion_tecnico'] = "Error al preparar la consulta de eliminación: " . mysqli_error($enlace);
    $_SESSION['mensaje_accion_tecnico_tipo'] = "mensaje-error";
} else {
    mysqli_stmt_bind_param($stmt_delete, "i", $tecnico_id);
    if (mysqli_stmt_execute($stmt_delete)) {
        if (mysqli_stmt_affected_rows($stmt_delete) > 0) {
            $_SESSION['mensaje_accion_tecnico'] = "Técnico eliminado exitosamente. Los usuarios que lo tenían asignado han sido actualizados.";
            $_SESSION['mensaje_accion_tecnico_tipo'] = "mensaje-exito";
        } else {
            $_SESSION['mensaje_accion_tecnico'] = "No se encontró el técnico o ya fue eliminado.";
            $_SESSION['mensaje_accion_tecnico_tipo'] = "mensaje-advertencia";
        }
    } else {
        $_SESSION['mensaje_accion_tecnico'] = "Error al eliminar el técnico: " . mysqli_stmt_error($stmt_delete);
        $_SESSION['mensaje_accion_tecnico_tipo'] = "mensaje-error";
    }
    mysqli_stmt_close($stmt_delete);
}

header("Location: listar.php");
return;
?>
