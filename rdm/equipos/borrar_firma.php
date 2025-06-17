<?php
// equipos/borrar_firma.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login(); // O ajustar según política de acceso

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Petición inválida.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Podría ser GET o POST, POST es ligeramente más seguro para acciones.
    $equipo_id = isset($_POST['equipo_id']) ? (int)$_POST['equipo_id'] : 0;

    if ($equipo_id <= 0) {
        $response['message'] = 'ID de equipo no válido.';
        echo json_encode($response);
        return;
    }

    $sql = "UPDATE equipos SET firma_cliente_base64 = NULL WHERE id = ?";
    $stmt = mysqli_prepare($enlace, $sql);

    if (!$stmt) {
        $response['message'] = 'Error al preparar la consulta: ' . mysqli_error($enlace);
    } else {
        mysqli_stmt_bind_param($stmt, "i", $equipo_id);
        if (mysqli_stmt_execute($stmt)) {
             $response['success'] = true;
             $response['message'] = 'Firma borrada exitosamente.';
        } else {
            $response['message'] = 'Error al borrar la firma: ' . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    }
}

echo json_encode($response);
?>
