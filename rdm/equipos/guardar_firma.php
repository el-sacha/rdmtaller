<?php
// equipos/guardar_firma.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login(); // O ajustar según política de acceso para firma

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

    // Validación básica de Base64 (no exhaustiva, pero disuade errores simples)
    // Formato esperado: data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...
    if (empty($signature_data) || strpos($signature_data, 'data:image/png;base64,') !== 0) {
        $response['message'] = 'Formato de firma no válido.';
        echo json_encode($response);
        return;
    }

    // Extraer solo los datos base64
    // $base64_image = str_replace('data:image/png;base64,', '', $signature_data);
    // $base64_image = base64_decode($base64_image, true); // Decodificar para verificar
    // if ($base64_image === false) {
    //    $response['message'] = 'Datos de firma corruptos (decodificación base64 fallida).';
    //    echo json_encode($response);
    //    return;
    // }
    // Guardamos directamente el Data URL completo, es más simple para <img src="...">
    // Si se quisiera guardar solo el binario, se necesitaría decodificar y manejar el blob en la BD.

    $sql = "UPDATE equipos SET firma_cliente_base64 = ? WHERE id = ?";
    $stmt = mysqli_prepare($enlace, $sql);

    if (!$stmt) {
        $response['message'] = 'Error al preparar la consulta: ' . mysqli_error($enlace);
    } else {
        mysqli_stmt_bind_param($stmt, "si", $signature_data, $equipo_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $response['success'] = true;
                $response['message'] = 'Firma guardada exitosamente.';
            } else {
                // Podría ser que el equipo_id no exista o la firma sea la misma (no hay cambio)
                // Para simplificar, si no hay error, asumimos que está bien o no era necesario actualizar.
                // Una comprobación más robusta verificaría si el equipo existe primero.
                $response['success'] = true; // O false si se quiere ser estricto con affected_rows > 0
                $response['message'] = 'Firma procesada (sin cambios o equipo no encontrado).';
            }
        } else {
            $response['message'] = 'Error al guardar la firma: ' . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    }
}

echo json_encode($response);
// No hay exit/return aquí ya que json_encode es la última salida.
?>
