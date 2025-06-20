<?php
// equipos/seguimiento.php
require_once '../includes/db.php';
require_once '../includes/funciones.php';

verificar_login(); // Asegurar que el usuario esté logueado

$equipo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0; // Cambiado para usar 'id' como en editar_estado

$equipo_info = null;
$historial_seguimiento = [];
$mensaje_error_display = ''; // Para errores que se muestran en la página
// Usar $_SESSION['error_accion_equipo'] para errores que causan redirección

if ($equipo_id <= 0) {
    $_SESSION['error_accion_equipo'] = "ID de equipo no válido.";
    header("Location: listar_equipos.php");
    return;
}

// --- Lógica de autorización ---
$usuario_rol = $_SESSION['rol'] ?? 'desconocido';
$usuario_actual_tecnico_id = null;

if ($usuario_rol === 'tecnico') {
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

    if (!$usuario_actual_tecnico_id) {
        $_SESSION['error_accion_equipo'] = "Error: No se pudo determinar el ID de técnico para el usuario actual o el usuario no está vinculado a un técnico.";
        header("Location: listar_equipos.php");
        return;
    }
}

// Consulta para obtener la información del equipo
$sql_equipo = "SELECT
                    e.id AS equipo_id, e.tipo_equipo, e.marca, e.modelo, e.tecnico_asignado_id,
                    er.nombre_estado AS estado_actual,
                    c.nombre AS cliente_nombre, c.dni AS cliente_dni
                FROM equipos e
                JOIN clientes c ON e.cliente_id = c.id
                LEFT JOIN estados_reparacion er ON e.estado_reparacion_id = er.id
                WHERE e.id = ?";

$stmt_equipo = mysqli_prepare($enlace, $sql_equipo);
if (!$stmt_equipo) {
    error_log("Error DB (prepare equipo seguimiento): " . mysqli_error($enlace));
    // Mostrar error en la página ya que no es un error de flujo que requiera siempre redirección
    $mensaje_error_display = "Error al preparar la consulta del equipo.";
} else {
    mysqli_stmt_bind_param($stmt_equipo, "i", $equipo_id);
    mysqli_stmt_execute($stmt_equipo);
    $resultado_equipo = mysqli_stmt_get_result($stmt_equipo);
    $equipo_info = mysqli_fetch_assoc($resultado_equipo);
    mysqli_stmt_close($stmt_equipo);

    if ($equipo_info) {
        // Aplicar autorización si es técnico
        if ($usuario_rol === 'tecnico') {
            if ($equipo_info['tecnico_asignado_id'] != $usuario_actual_tecnico_id) {
                $_SESSION['error_accion_equipo'] = "Acceso denegado: No está asignado a este equipo.";
                header("Location: listar_equipos.php");
                return;
            }
        }

        // Si la autorización es exitosa, obtener el historial de seguimiento
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
            error_log("Error DB (prepare historial seguimiento): " . mysqli_error($enlace));
            $mensaje_error_display = "Error al preparar la consulta del historial.";
        } else {
            mysqli_stmt_bind_param($stmt_historial, "i", $equipo_id);
            mysqli_stmt_execute($stmt_historial);
            $resultado_historial = mysqli_stmt_get_result($stmt_historial);
            while ($fila = mysqli_fetch_assoc($resultado_historial)) {
                $historial_seguimiento[] = $fila;
            }
            mysqli_stmt_close($stmt_historial);
        }
    } else {
        // No se encontró el equipo, podría ser un ID inválido o un problema.
        // Si el ID era válido, pero no se encontró, es un error que debe redirigir.
        $_SESSION['error_accion_equipo'] = "Equipo no encontrado con ID: " . htmlspecialchars($equipo_id);
        header("Location: listar_equipos.php");
        return;
    }
}
// --- Fin lógica de autorización y carga de datos ---

require_once '../includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-10"> {/* Ampliado un poco para más contenido */}
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Seguimiento de Equipo Nº <?php echo htmlspecialchars($equipo_id); ?></h3>
                </div>
                <div class="card-body">
                    <?php
                    // Mostrar mensaje de error de sesión si existe (de una redirección previa)
                    if (isset($_SESSION['error_accion_equipo'])) {
                        echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error_accion_equipo']) . '</div>';
                        unset($_SESSION['error_accion_equipo']);
                    }
                    // Mostrar mensaje de error generado en esta carga de página
                    if (!empty($mensaje_error_display)) {
                        echo '<div class="alert alert-danger">' . htmlspecialchars($mensaje_error_display) . '</div>';
                    }
                    ?>

                    <?php if ($equipo_info && empty($mensaje_error_display)): // Solo mostrar si hay info y no hubo error en esta carga ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                Información del Equipo: <?php echo htmlspecialchars($equipo_info['tipo_equipo'] . " " . $equipo_info['marca'] . " " . $equipo_info['modelo']); ?>
                            </div>
                            <div class="card-body">
                                <p class="card-text"><strong>Cliente:</strong> <?php echo htmlspecialchars($equipo_info['cliente_nombre'] . " (DNI: " . $equipo_info['cliente_dni'] . ")"); ?></p>
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
