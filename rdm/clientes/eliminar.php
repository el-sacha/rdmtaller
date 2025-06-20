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

    // 3. Comprobar equipos asociados ANTES de intentar eliminar el cliente
    $sql_check_equipos = "SELECT COUNT(*) as num_equipos FROM equipos WHERE cliente_id = ?";
    $stmt_check_equipos = mysqli_prepare($enlace, $sql_check_equipos);
    $equipos_asociados = true; // Asumir que hay equipos por defecto o si falla la consulta

    if ($stmt_check_equipos) {
        mysqli_stmt_bind_param($stmt_check_equipos, "i", $cliente_id);
        mysqli_stmt_execute($stmt_check_equipos);
        $resultado_check = mysqli_stmt_get_result($stmt_check_equipos);
        $fila_check = mysqli_fetch_assoc($resultado_check);
        mysqli_stmt_close($stmt_check_equipos);

        if ($fila_check && $fila_check['num_equipos'] == 0) {
            $equipos_asociados = false;
        } elseif ($fila_check && $fila_check['num_equipos'] > 0) {
            $_SESSION['mensaje_accion_cliente'] = "Error al eliminar el cliente: No se puede eliminar porque tiene equipos asociados. Por favor, reasigne o elimine los equipos primero.";
            $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
            header("Location: listar.php");
            return;
        } else {
            // Error al obtener el conteo, no continuar con la eliminación por seguridad
            $user_message = log_db_error_y_mostrar_mensaje_generico(mysqli_error($enlace), "", "No se pudo verificar la existencia de equipos asociados.");
            $_SESSION['mensaje_accion_cliente'] = strip_tags($user_message);
            $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
            header("Location: listar.php");
            return;
        }
    } else {
        // Error al preparar la consulta de verificación de equipos
        $user_message = log_db_error_y_mostrar_mensaje_generico(mysqli_error($enlace), "", "Error al intentar verificar equipos asociados.");
        $_SESSION['mensaje_accion_cliente'] = strip_tags($user_message);
        $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
        header("Location: listar.php");
        return;
    }

    // 4. Si no hay equipos asociados, intentar eliminar el cliente
    if (!$equipos_asociados) {
        $sql_delete_cliente = "DELETE FROM clientes WHERE id = ?";
        $stmt_delete_cliente = mysqli_prepare($enlace, $sql_delete_cliente);

        if (!$stmt_delete_cliente) {
            $user_message = log_db_error_y_mostrar_mensaje_generico(mysqli_error($enlace), "", "No se pudo procesar la solicitud de eliminación en este momento.");
            $_SESSION['mensaje_accion_cliente'] = strip_tags($user_message);
            $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
        } else {
            mysqli_stmt_bind_param($stmt_delete_cliente, "i", $cliente_id);
            if (mysqli_stmt_execute($stmt_delete_cliente)) {
                if (mysqli_stmt_affected_rows($stmt_delete_cliente) > 0) {
                    $_SESSION['mensaje_accion_cliente'] = "Cliente eliminado exitosamente."; // Mensaje actualizado
                    $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-exito";
                } else {
                    $_SESSION['mensaje_accion_cliente'] = "No se encontró el cliente a eliminar o ya fue eliminado.";
                }
            } else {
                // Error común: restricción de FK por facturas existentes
                // (la comprobación de equipos ya pasó)
                if (mysqli_errno($enlace) == 1451) {
                    $_SESSION['mensaje_accion_cliente'] = "Error al eliminar el cliente: No se puede eliminar porque tiene facturas asociadas. Por favor, elimine o reasigne las facturas primero.";
                    $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
                } else {
                    $user_message = log_db_error_y_mostrar_mensaje_generico(mysqli_error($enlace), mysqli_stmt_error($stmt_delete_cliente), "No se pudo eliminar el cliente.");
                    $_SESSION['mensaje_accion_cliente'] = strip_tags($user_message);
                    $_SESSION['mensaje_accion_cliente_tipo'] = "mensaje-error";
                }
            }
            mysqli_stmt_close($stmt_delete_cliente);
        }
    } // Cierre if (!$equipos_asociados)
    // Si $equipos_asociados era true y se manejó el mensaje, no se llega aquí.
    // Si hubo error en la comprobación de equipos, tampoco se llega aquí.

} else { // if ($_SERVER['REQUEST_METHOD'] === 'POST')
    // Si no es POST, redirigir o mostrar error
    // Este mensaje no necesita logueo de DB, es un error de flujo.
    $_SESSION['mensaje_accion_cliente'] = "Acción no permitida (solo POST).";
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
