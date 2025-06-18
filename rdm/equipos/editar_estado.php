<?php
// equipos/editar_estado.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login();

$equipo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mensaje = '';
$error_validacion = [];

if ($equipo_id <= 0) {
    $_SESSION['error_accion_equipo'] = "ID de equipo no válido.";
    header("Location: listar_equipos.php");
    return;
}

// Cargar estados de reparación para el dropdown
$estados_reparacion = [];
$stmt_estados = mysqli_prepare($enlace, "SELECT id, nombre_estado FROM estados_reparacion ORDER BY nombre_estado ASC");
if ($stmt_estados) {
    mysqli_stmt_execute($stmt_estados);
    $res_estados = mysqli_stmt_get_result($stmt_estados);
    while ($row = mysqli_fetch_assoc($res_estados)) {
        $estados_reparacion[] = $row;
    }
    mysqli_stmt_close($stmt_estados);
}

// Variables para el formulario
$estado_reparacion_id_actual = '';
$notas_internas_reparacion_actual = '';

// --- Lógica de POST (Actualización de estado y notas) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipo_id_post = isset($_POST['equipo_id']) ? (int)$_POST['equipo_id'] : 0;
    if ($equipo_id_post !== $equipo_id) {
        $_SESSION['error_accion_equipo'] = "Error: ID de equipo incorrecto en el formulario.";
        header("Location: listar_equipos.php");
        return;
    }

    $nuevo_estado_id = isset($_POST['estado_reparacion_id']) ? (int)$_POST['estado_reparacion_id'] : '';
    $nuevas_notas_internas = isset($_POST['notas_internas_reparacion']) ? sanitizar_entrada($_POST['notas_internas_reparacion']) : '';
    $nota_seguimiento = isset($_POST['nota_seguimiento']) ? sanitizar_entrada($_POST['nota_seguimiento']) : '';


    if (empty($nuevo_estado_id)) {
        $error_validacion['estado_reparacion_id'] = "Debe seleccionar un nuevo estado.";
    }

    if (empty($error_validacion)) {
        // Iniciar transacción
        mysqli_begin_transaction($enlace);

        try {
            // 1. Actualizar el equipo
            $sql_update_equipo = "UPDATE equipos SET estado_reparacion_id = ?, notas_internas_reparacion = ? WHERE id = ?";
            $stmt_update = mysqli_prepare($enlace, $sql_update_equipo);
            if (!$stmt_update) throw new Exception("Error al preparar actualización de equipo: " . mysqli_error($enlace));

            mysqli_stmt_bind_param($stmt_update, "isi", $nuevo_estado_id, $nuevas_notas_internas, $equipo_id);
            if (!mysqli_stmt_execute($stmt_update)) throw new Exception("Error al actualizar equipo: " . mysqli_stmt_error($stmt_update));
            mysqli_stmt_close($stmt_update);

            // 2. Insertar en seguimiento_equipo
            $fecha_actualizacion = date('Y-m-d H:i:s');
            $sql_seguimiento = "INSERT INTO seguimiento_equipo (equipo_id, estado_id, fecha_actualizacion, notas) VALUES (?, ?, ?, ?)";
            $stmt_seguimiento = mysqli_prepare($enlace, $sql_seguimiento);
            if (!$stmt_seguimiento) throw new Exception("Error al preparar inserción de seguimiento: " . mysqli_error($enlace));

            mysqli_stmt_bind_param($stmt_seguimiento, "iiss", $equipo_id, $nuevo_estado_id, $fecha_actualizacion, $nota_seguimiento);
            if (!mysqli_stmt_execute($stmt_seguimiento)) throw new Exception("Error al insertar en seguimiento: " . mysqli_stmt_error($stmt_seguimiento));
            mysqli_stmt_close($stmt_seguimiento);

            // Si todo fue bien, commit
            mysqli_commit($enlace);
            $_SESSION['mensaje_accion_equipo'] = "Estado y notas del equipo actualizados exitosamente.";
            header("Location: listar_equipos.php");
            return;

        } catch (Exception $e) {
            mysqli_rollback($enlace); // Revertir cambios en caso de error
            $mensaje = "<div class='alert alert-danger'>Error en la transacción: " . $e->getMessage() . "</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-danger'>Por favor corrija los errores del formulario.</div>";
    }
     // Para mantener los valores en el formulario si hay error
    $estado_reparacion_id_actual = $nuevo_estado_id;
    $notas_internas_reparacion_actual = $nuevas_notas_internas;
}


// --- Lógica de GET (Cargar datos del equipo para editar) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { // Solo cargar si no es un reintento de POST fallido
    $sql_select_equipo = "SELECT
                            e.id AS equipo_id, e.tipo_equipo, e.marca, e.modelo, e.numero_serie_imei,
                            e.fallas_reportadas, e.estado_fisico, e.observaciones, e.accesorios_entregados,
                            e.fecha_ingreso, e.estado_reparacion_id, e.notas_internas_reparacion,
                            c.nombre AS cliente_nombre, c.dni AS cliente_dni,
                            t.nombre AS tecnico_nombre,
                            er.nombre_estado AS estado_actual_nombre
                        FROM equipos e
                        JOIN clientes c ON e.cliente_id = c.id
                        LEFT JOIN tecnicos t ON e.tecnico_asignado_id = t.id
                        LEFT JOIN estados_reparacion er ON e.estado_reparacion_id = er.id
                        WHERE e.id = ?";
    $stmt_select = mysqli_prepare($enlace, $sql_select_equipo);
    if (!$stmt_select) {
        $_SESSION['error_accion_equipo'] = "Error al preparar consulta de equipo: " . mysqli_error($enlace);
        header("Location: listar_equipos.php");
        return;
    }
    mysqli_stmt_bind_param($stmt_select, "i", $equipo_id);
    mysqli_stmt_execute($stmt_select);
    $res_select = mysqli_stmt_get_result($stmt_select);
    $equipo_actual = mysqli_fetch_assoc($res_select);
    mysqli_stmt_close($stmt_select);

    if (!$equipo_actual) {
        $_SESSION['error_accion_equipo'] = "Equipo no encontrado.";
        header("Location: listar_equipos.php");
        return;
    }
    $estado_reparacion_id_actual = $equipo_actual['estado_reparacion_id'];
    $notas_internas_reparacion_actual = $equipo_actual['notas_internas_reparacion'];
}


require_once '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Gestionar Estado y Notas del Equipo Nº <?php echo htmlspecialchars($equipo_id); ?></h2>

    <?php if (!empty($mensaje)) echo $mensaje; ?>

    <?php if (isset($equipo_actual) && $equipo_actual): ?>
    <div class="card mb-4">
        <div class="card-header">Información del Equipo</div>
        <div class="card-body">
            <p><strong>Cliente:</strong> <?php echo htmlspecialchars($equipo_actual['cliente_nombre'] . ' (DNI: ' . $equipo_actual['cliente_dni'] . ')'); ?></p>
            <p><strong>Equipo:</strong> <?php echo htmlspecialchars($equipo_actual['tipo_equipo'] . ' ' . $equipo_actual['marca'] . ' ' . $equipo_actual['modelo']); ?></p>
            <p><strong>Serie/IMEI:</strong> <?php echo htmlspecialchars($equipo_actual['numero_serie_imei'] ?? 'N/A'); ?></p>
            <p><strong>Técnico Asignado:</strong> <?php echo htmlspecialchars($equipo_actual['tecnico_nombre'] ?? 'N/A'); ?></p>
            <p><strong>Estado Actual:</strong> <span class="badge bg-primary"><?php echo htmlspecialchars($equipo_actual['estado_actual_nombre'] ?? 'N/A'); ?></span></p>
            <p><strong>Fallas Reportadas:</strong> <?php echo nl2br(htmlspecialchars($equipo_actual['fallas_reportadas'])); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <form action="editar_estado.php?id=<?php echo $equipo_id; ?>" method="POST" novalidate>
        <input type="hidden" name="equipo_id" value="<?php echo $equipo_id; ?>">

        <div class="mb-3">
            <label for="estado_reparacion_id" class="form-label">Cambiar Estado de Reparación:</label>
            <select name="estado_reparacion_id" id="estado_reparacion_id" class="form-select <?php if(isset($error_validacion['estado_reparacion_id'])) echo 'is-invalid'; ?>" required>
                <option value="">Seleccione un estado...</option>
                <?php foreach ($estados_reparacion as $estado): ?>
                    <option value="<?php echo $estado['id']; ?>" <?php echo ($estado_reparacion_id_actual == $estado['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($estado['nombre_estado']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($error_validacion['estado_reparacion_id'])): ?>
                <div class="invalid-feedback"><?php echo $error_validacion['estado_reparacion_id']; ?></div>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="notas_internas_reparacion" class="form-label">Notas Internas de Reparación (Generales del Equipo):</label>
            <textarea name="notas_internas_reparacion" id="notas_internas_reparacion" class="form-control" rows="3"><?php echo htmlspecialchars($notas_internas_reparacion_actual); ?></textarea>
        </div>

        <div class="mb-3">
            <label for="nota_seguimiento" class="form-label">Nota para este Cambio de Estado (se guardará en el historial):</label>
            <textarea name="nota_seguimiento" id="nota_seguimiento" class="form-control" rows="2"></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Actualizar Equipo</button>
        <a href="listar_equipos.php" class="btn btn-secondary">Cancelar y Volver al Listado</a>
    </form>
</div>

<?php
require_once '../includes/footer.php';
?>
