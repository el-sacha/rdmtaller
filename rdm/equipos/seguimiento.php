<?php
// equipos/seguimiento.php
require_once '../includes/db.php';
require_once '../includes/funciones.php'; // Para sanitizar_entrada

$numero_orden = isset($_REQUEST['numero_orden']) ? sanitizar_entrada(trim($_REQUEST['numero_orden'])) : '';
$dni_cliente = isset($_REQUEST['dni_cliente']) ? sanitizar_entrada(trim($_REQUEST['dni_cliente'])) : '';

$equipo_info = null;
$historial_seguimiento = [];
$mensaje_error = '';

if (!empty($numero_orden) && !empty($dni_cliente)) {
    if (!is_numeric($numero_orden)) {
        $mensaje_error = "El número de orden debe ser numérico.";
    } else {
        $numero_orden_int = (int)$numero_orden;

        // Consulta para obtener la información del equipo y verificar el DNI del cliente
        $sql_equipo = "SELECT
                            e.id AS equipo_id, e.tipo_equipo, e.marca, e.modelo,
                            er.nombre_estado AS estado_actual,
                            c.dni AS cliente_dni_db
                        FROM equipos e
                        JOIN clientes c ON e.cliente_id = c.id
                        LEFT JOIN estados_reparacion er ON e.estado_reparacion_id = er.id
                        WHERE e.id = ? AND c.dni = ?";

        $stmt_equipo = mysqli_prepare($enlace, $sql_equipo);
        if (!$stmt_equipo) {
            $mensaje_error = "Error al preparar la consulta del equipo: " . mysqli_error($enlace);
        } else {
            mysqli_stmt_bind_param($stmt_equipo, "is", $numero_orden_int, $dni_cliente);
            mysqli_stmt_execute($stmt_equipo);
            $resultado_equipo = mysqli_stmt_get_result($stmt_equipo);
            $equipo_info = mysqli_fetch_assoc($resultado_equipo);
            mysqli_stmt_close($stmt_equipo);

            if ($equipo_info) {
                // Si se encontró el equipo y el DNI coincide, obtener el historial de seguimiento
                $sql_historial = "SELECT
                                    s.fecha_actualizacion,
                                    er.nombre_estado,
                                    s.notas
                                FROM seguimiento_equipo s
                                JOIN estados_reparacion er ON s.estado_id = er.id
                                WHERE s.equipo_id = ?
                                ORDER BY s.fecha_actualizacion DESC";
                $stmt_historial = mysqli_prepare($enlace, $sql_historial);
                if (!$stmt_historial) {
                    $mensaje_error = "Error al preparar la consulta del historial: " . mysqli_error($enlace);
                } else {
                    mysqli_stmt_bind_param($stmt_historial, "i", $numero_orden_int);
                    mysqli_stmt_execute($stmt_historial);
                    $resultado_historial = mysqli_stmt_get_result($stmt_historial);
                    while ($fila = mysqli_fetch_assoc($resultado_historial)) {
                        $historial_seguimiento[] = $fila;
                    }
                    mysqli_stmt_close($stmt_historial);
                }
            } else {
                $mensaje_error = "No se encontró el equipo con el número de orden y DNI proporcionados. Verifique los datos e inténtelo de nuevo.";
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['numero_orden'])) {
    // Si se envió el formulario pero alguno de los campos está vacío
    $mensaje_error = "Debe ingresar el Número de Orden y el DNI del Cliente.";
}


require_once '../includes/header.php'; // Incluir el header con Bootstrap
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Seguimiento de Equipo</h3>
                </div>
                <div class="card-body">
                    <form action="seguimiento.php" method="GET" class="mb-4">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label for="numero_orden" class="form-label">Número de Orden:</label>
                                <input type="text" name="numero_orden" id="numero_orden" class="form-control" value="<?php echo htmlspecialchars($numero_orden); ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label for="dni_cliente" class="form-label">DNI del Cliente:</label>
                                <input type="text" name="dni_cliente" id="dni_cliente" class="form-control" value="<?php echo htmlspecialchars($dni_cliente); ?>" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Buscar</button>
                            </div>
                        </div>
                    </form>

                    <?php if (!empty($mensaje_error)): ?>
                        <div class="alert alert-danger"><?php echo $mensaje_error; ?></div>
                    <?php endif; ?>

                    <?php if ($equipo_info): ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                Información del Equipo: <?php echo htmlspecialchars($equipo_info['tipo_equipo'] . " " . $equipo_info['marca'] . " " . $equipo_info['modelo']); ?>
                            </div>
                            <div class="card-body">
                                <p class="card-text"><strong>Estado Actual:</strong> <span class="badge bg-success"><?php echo htmlspecialchars($equipo_info['estado_actual']); ?></span></p>
                            </div>
                        </div>

                        <?php if (!empty($historial_seguimiento)): ?>
                            <h4 class="mt-4">Historial de Estados:</h4>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fecha Actualización</th>
                                            <th>Estado</th>
                                            <th>Notas Adicionales</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historial_seguimiento as $item): ?>
                                            <tr>
                                                <td><?php echo date("d/m/Y H:i:s", strtotime($item['fecha_actualizacion'])); ?></td>
                                                <td><?php echo htmlspecialchars($item['nombre_estado']); ?></td>
                                                <td><?php echo nl2br(htmlspecialchars($item['notas'] ?? '-')); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="mt-3">No hay historial de seguimiento disponible para este equipo.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
