<?php
// clientes/eliminar.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login();

// IMPORTANTE: Este script debe ser accedido vía POST con un token CSRF para seguridad.
// Por ahora, se implementa con GET para simplicidad, pero con una advertencia.
// También falta una confirmación explícita del tipo "Está seguro?" antes de borrar,
// aunque se añadió un confirm() en el enlace de listar.php.

$cliente_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($cliente_id <= 0) {
    $_SESSION['mensaje_accion_cliente'] = "ID de cliente no válido para eliminar.";
    $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
    header("Location: listar.php");
    return; // Usar return en lugar de exit para la herramienta
}

// Antes de eliminar, sería bueno verificar si el cliente tiene registros asociados (equipos, facturas)
// y decidir cómo manejar esos casos (borrado en cascada, impedir borrado, anonimizar, etc.).
// Por ahora, se procede con el borrado directo.

$sql = "DELETE FROM clientes WHERE id = ?";
$stmt = mysqli_prepare($enlace, $sql);

if (!$stmt) {
    // Error en la preparación de la consulta
    $_SESSION['mensaje_accion_cliente'] = "Error al preparar la consulta de eliminación: " . mysqli_error($enlace);
    $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
} else {
    mysqli_stmt_bind_param($stmt, "i", $cliente_id);
    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $_SESSION['mensaje_accion_cliente'] = "Cliente eliminado exitosamente.";
            $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-exito";
        } else {
            $_SESSION['mensaje_accion_cliente'] = "No se encontró el cliente a eliminar o ya fue eliminado.";
            $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-advertencia";
        }
    } else {
        // Error en la ejecución (podría ser por restricciones de clave foránea si no están ON DELETE CASCADE/SET NULL)
        $_SESSION['mensaje_accion_cliente'] = "Error al eliminar el cliente: " . mysqli_stmt_error($stmt) . ". Verifique si tiene equipos u otros registros asociados.";
        $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
    }
    mysqli_stmt_close($stmt);
}

// mysqli_close($enlace); // No cerrar aquí, header() no funcionará
header("Location: listar.php");
return; // Usar return en lugar de exit para la herramienta
?>
