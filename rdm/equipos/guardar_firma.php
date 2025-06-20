<?php
// equipos/guardar_firma.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login();

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Petición inválida.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipo_id = isset($_POST['equipo_id']) ? (int)$_POST['equipo_id'] : 0;
    $signature_data = isset($_POST['signature_data']) ? $_POST['signature_data'] : '';

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

    if (empty($signature_data) || strpos($signature_data, 'data:image/png;base64,') !== 0) {
        $response['message'] = 'Formato de firma no válido.';
        echo json_encode($response);
        return;
    }

    // Directorio de firmas
    $directorio_firmas_base = __DIR__ . '/../firmas/'; // rdm/equipos/../firmas/ -> rdm/firmas/
    if (!is_dir($directorio_firmas_base) && !mkdir($directorio_firmas_base, 0755, true)) {
        $response['message'] = 'Error: No se puede crear el directorio de firmas.';
        error_log("Error al crear directorio de firmas: " . $directorio_firmas_base);
        echo json_encode($response);
        return;
    }

    // Procesar firma
    list($type, $data) = explode(';', $signature_data);
    list(, $data)      = explode(',', $data);
    $imagen_decodificada = base64_decode($data);

    if ($imagen_decodificada === false) {
        $response['message'] = 'Error al decodificar la imagen de la firma.';
        echo json_encode($response);
        return;
    }

    $nombre_archivo_unico = 'firma_eq' . $equipo_id . '_' . time() . '.png';
    $ruta_archivo_servidor = $directorio_firmas_base . $nombre_archivo_unico;
    $ruta_bd = 'firmas/' . $nombre_archivo_unico; // Ruta relativa para la BD

    // Manejar firma antigua
    $sql_get_old_signature = "SELECT firma_cliente_ruta FROM equipos WHERE id = ?";
    $stmt_get_old = mysqli_prepare($enlace, $sql_get_old_signature);
    if ($stmt_get_old) {
        mysqli_stmt_bind_param($stmt_get_old, "i", $equipo_id);
        mysqli_stmt_execute($stmt_get_old);
        $res_old = mysqli_stmt_get_result($stmt_get_old);
        if ($row_old = mysqli_fetch_assoc($res_old)) {
            if (!empty($row_old['firma_cliente_ruta'])) {
                $ruta_antigua_servidor = __DIR__ . '/../' . $row_old['firma_cliente_ruta']; // rdm/equipos/../firmas/firma_eq...png
                if (file_exists($ruta_antigua_servidor)) {
                    unlink($ruta_antigua_servidor);
                }
            }
        }
        mysqli_stmt_close($stmt_get_old);
    } // Continuar incluso si no se puede obtener la firma antigua, para no bloquear el guardado de la nueva

    // Guardar nueva firma
    if (file_put_contents($ruta_archivo_servidor, $imagen_decodificada)) {
        $sql_update = "UPDATE equipos SET firma_cliente_ruta = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($enlace, $sql_update);
        if (!$stmt_update) {
            $response['message'] = 'Error al preparar la actualización de la base de datos.';
            error_log("Error DB (prepare update firma_cliente_ruta): " . mysqli_error($enlace));
        } else {
            mysqli_stmt_bind_param($stmt_update, "si", $ruta_bd, $equipo_id);
            if (mysqli_stmt_execute($stmt_update)) {
                $response['success'] = true;
                $response['message'] = 'Firma guardada exitosamente.';
                $response['filePath'] = $ruta_bd; // Enviar nueva ruta para actualizar la imagen en el cliente
            } else {
                $response['message'] = 'Error al actualizar la base de datos.';
                error_log("Error DB (execute update firma_cliente_ruta): " . mysqli_stmt_error($stmt_update));
            }
            mysqli_stmt_close($stmt_update);
        }
    } else {
        $response['message'] = 'Error al guardar el archivo de la firma en el servidor.';
        error_log("Error al guardar firma en: " . $ruta_archivo_servidor);
    }
}

echo json_encode($response);
?>
