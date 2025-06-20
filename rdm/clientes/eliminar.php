<?php
// clientes/eliminar.php
require_once '../includes/funciones.php';
require_once '../includes/db.php'; // Para $enlace

verificar_login(); // Asegura que el usuario esté logueado y la sesión iniciada

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validar CSRF token
    if (!isset($_POST['csrf_token']) || !validar_token_csrf($_POST['csrf_token'])) {
        $_SESSION['mensaje_accion_cliente'] = "Error de validación CSRF. Intento de eliminación bloqueado.";
        $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
        header("Location: listar.php");
        return; // Usar return para la herramienta
    }

    // 2. Obtener y validar cliente_id
    $cliente_id = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;

    if ($cliente_id <= 0) {
        $_SESSION['mensaje_accion_cliente'] = "ID de cliente no válido para eliminar.";
        $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
        header("Location: listar.php");
        return; // Usar return para la herramienta
    }

    // 3. Intentar eliminar el cliente
    // Considerar las restricciones de FK: facturas (ON DELETE RESTRICT), equipos (ON DELETE CASCADE)
    $sql = "DELETE FROM clientes WHERE id = ?";
    $stmt = mysqli_prepare($enlace, $sql);

    if (!$stmt) {
        // Log error y preparar mensaje para sesión
        $user_message = log_db_error_y_mostrar_mensaje_generico(mysqli_error($enlace), "", "No se pudo procesar la solicitud de eliminación en este momento.");
        $_SESSION['mensaje_accion_cliente'] = strip_tags($user_message); // strip_tags para quitar <p> si se usa en sesión
        $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
    } else {
        mysqli_stmt_bind_param($stmt, "i", $cliente_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $_SESSION['mensaje_accion_cliente'] = "Cliente eliminado exitosamente. Todos sus equipos asociados (si los tuviera) también han sido eliminados.";
                $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-exito";
            } else {
                $_SESSION['mensaje_accion_cliente'] = "No se encontró el cliente a eliminar o ya fue eliminado.";
                $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-aviso";
            }
        } else {
            // Error común: restricción de FK por facturas existentes
            if (mysqli_errno($enlace) == 1451) { // Error de restricción de clave externa
                 $_SESSION['mensaje_accion_cliente'] = "Error al eliminar el cliente: No se puede eliminar porque tiene facturas asociadas. Por favor, elimine o reasigne las facturas primero.";
                 $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
            } else {
                // Log error y preparar mensaje para sesión
                $user_message = log_db_error_y_mostrar_mensaje_generico(mysqli_error($enlace), mysqli_stmt_error($stmt), "No se pudo eliminar el cliente.");
                $_SESSION['mensaje_accion_cliente'] = strip_tags($user_message);
                $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
            }
        }
        mysqli_stmt_close($stmt);
    }
} else {
    // Si no es POST, redirigir o mostrar error
    // Este mensaje no necesita logueo de DB, es un error de flujo.
    $_SESSION['mensaje_accion_cliente'] = "Acción no permitida (solo POST).";
    $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
}

// Siempre redirigir de vuelta a listar.php
header("Location: listar.php");
return; // Usar return para la herramienta
?>
