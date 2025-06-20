<?php
// equipos/borrar_firma.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login(); // O ajustar según política de acceso

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Petición inválida.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipo_id = isset($_POST['equipo_id']) ? (int)$_POST['equipo_id'] : 0;

    if ($equipo_id <= 0) {
        $response['message'] = 'ID de equipo no válido.';
        echo json_encode($response);
        return;
    }

    // --- Lógica de autorización IDOR ---
    $usuario_rol = $_SESSION['rol'] ?? 'desconocido';
    $permitido = false;

    if ($usuario_rol === 'admin') {
        $permitido = true;
    } elseif ($usuario_rol === 'tecnico') {
        $usuario_actual_tecnico_id = null;
        $stmt_user_tecnico = mysqli_prepare($enlace, "SELECT tecnico_id FROM usuarios WHERE id = ?");
        if ($stmt_user_tecnico) {
            mysqli_stmt_bind_param($stmt_user_tecnico, "i", $_SESSION['usuario_id']);
            mysqli_stmt_execute($stmt_user_tecnico);
            $res_user_tecnico = mysqli_stmt_get_result($stmt_user_tecnico);
            if ($row_user_tecnico = mysqli_fetch_assoc($res_user_tecnico)) {
                $usuario_actual_tecnico_id = $row_user_tecnico['tecnico_id'];
            }
            mysqli_stmt_close($stmt_user_tecnico);
        }

        if ($usuario_actual_tecnico_id) {
            $stmt_check_equipo = mysqli_prepare($enlace, "SELECT tecnico_asignado_id FROM equipos WHERE id = ?");
            if ($stmt_check_equipo) {
                mysqli_stmt_bind_param($stmt_check_equipo, "i", $equipo_id);
                mysqli_stmt_execute($stmt_check_equipo);
                $res_check_equipo = mysqli_stmt_get_result($stmt_check_equipo);
                $equipo_a_editar = mysqli_fetch_assoc($res_check_equipo);
                mysqli_stmt_close($stmt_check_equipo);
                if ($equipo_a_editar && $equipo_a_editar['tecnico_asignado_id'] == $usuario_actual_tecnico_id) {
                    $permitido = true;
                }
            }
        }
    }

    if (!$permitido) {
        $response['message'] = 'Acceso denegado. No tiene permiso para modificar este equipo.';
        echo json_encode($response);
        return;
    }
    // --- Fin Lógica de autorización IDOR ---

    // Obtener ruta de la firma actual para borrar el archivo
    $sql_get_path = "SELECT firma_cliente_ruta FROM equipos WHERE id = ?";
    $stmt_get_path = mysqli_prepare($enlace, $sql_get_path);
    $ruta_antigua_servidor = null;

    if ($stmt_get_path) {
        mysqli_stmt_bind_param($stmt_get_path, "i", $equipo_id);
        mysqli_stmt_execute($stmt_get_path);
        $res_path = mysqli_stmt_get_result($stmt_get_path);
        if ($row_path = mysqli_fetch_assoc($res_path)) {
            if (!empty($row_path['firma_cliente_ruta'])) {
                $ruta_antigua_servidor = __DIR__ . '/../' . $row_path['firma_cliente_ruta'];
            }
        }
        mysqli_stmt_close($stmt_get_path);
    } else {
        $response['message'] = 'Error al obtener la ruta de la firma existente.';
        error_log("Error DB (prepare select firma_cliente_ruta): " . mysqli_error($enlace));
        echo json_encode($response);
        return;
    }

    // Actualizar la base de datos para quitar la referencia
    $sql_update = "UPDATE equipos SET firma_cliente_ruta = NULL WHERE id = ?";
    $stmt_update = mysqli_prepare($enlace, $sql_update);

    if (!$stmt_update) {
        $response['message'] = 'Error al preparar la consulta de actualización.';
        error_log("Error DB (prepare update firma_cliente_ruta to NULL): " . mysqli_error($enlace));
    } else {
        mysqli_stmt_bind_param($stmt_update, "i", $equipo_id);
        if (mysqli_stmt_execute($stmt_update)) {
            // Si la actualización de la BD es exitosa, intentar borrar el archivo físico
            if ($ruta_antigua_servidor && file_exists($ruta_antigua_servidor)) {
                if (unlink($ruta_antigua_servidor)) {
                    $response['success'] = true;
                    $response['message'] = 'Firma borrada exitosamente y archivo eliminado.';
                } else {
                    // La BD se actualizó, pero el archivo no se pudo borrar.
                    $response['success'] = true; // Considerar false si el borrado de archivo es crítico
                    $response['message'] = 'Referencia de firma borrada de la BD, pero el archivo físico no pudo ser eliminado del servidor.';
                    error_log("No se pudo eliminar el archivo de firma: " . $ruta_antigua_servidor);
                }
            } else {
                // No había archivo que borrar, o la ruta no era válida, pero la BD se actualizó.
                $response['success'] = true;
                $response['message'] = 'Referencia de firma borrada (no había archivo físico o ruta).';
            }
        } else {
            $response['message'] = 'Error al borrar la referencia de la firma en la base de datos.';
            error_log("Error DB (execute update firma_cliente_ruta to NULL): " . mysqli_stmt_error($stmt_update));
        }
        mysqli_stmt_close($stmt_update);
    }
}

echo json_encode($response);
?>
