<?php
// equipos/ingresar.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login();

$csrf_token = generar_token_csrf();
$mensaje = '';
$error_validacion = [];

// Valores por defecto para el formulario
$cliente_id = '';
$tipo_equipo = '';
$marca = '';
$modelo = '';
$numero_serie_imei = '';
$fallas_reportadas = '';
$estado_fisico = '';
$observaciones = '';
$accesorios_entregados = '';
$tecnico_asignado_id = '';

// Cargar clientes y técnicos para los dropdowns
$clientes = [];
$stmt_clientes = mysqli_prepare($enlace, "SELECT id, nombre, dni FROM clientes ORDER BY nombre ASC");
if ($stmt_clientes) {
    mysqli_stmt_execute($stmt_clientes);
    $res_clientes = mysqli_stmt_get_result($stmt_clientes);
    while ($row = mysqli_fetch_assoc($res_clientes)) {
        $clientes[] = $row;
    }
    mysqli_stmt_close($stmt_clientes);
}

$tecnicos = [];
$stmt_tecnicos = mysqli_prepare($enlace, "SELECT id, nombre FROM tecnicos ORDER BY nombre ASC");
if ($stmt_tecnicos) {
    mysqli_stmt_execute($stmt_tecnicos);
    $res_tecnicos = mysqli_stmt_get_result($stmt_tecnicos);
    while ($row = mysqli_fetch_assoc($res_tecnicos)) {
        $tecnicos[] = $row;
    }
    mysqli_stmt_close($stmt_tecnicos);
}

// Obtener ID del estado 'Ingresado' (asumimos que existe y su nombre es 'Ingresado')
$estado_ingresado_id = null;
$stmt_estado = mysqli_prepare($enlace, "SELECT id FROM estados_reparacion WHERE nombre_estado = 'Ingresado' LIMIT 1");
if ($stmt_estado) {
    mysqli_stmt_execute($stmt_estado);
    $res_estado = mysqli_stmt_get_result($stmt_estado);
    if ($row_estado = mysqli_fetch_assoc($res_estado)) {
        $estado_ingresado_id = $row_estado['id'];
    }
    mysqli_stmt_close($stmt_estado);
}

if ($estado_ingresado_id === null) {
    // Fallback o error si el estado 'Ingresado' no se encuentra.
    // Podríamos crearlo si no existe, o mostrar un error fatal.
    // Por ahora, si no existe, la inserción fallará la FK o usará NULL si la columna lo permite (no ideal).
    $mensaje = "<p class='mensaje-error'>Error crítico: El estado 'Ingresado' no está definido en la base de datos.</p>";
    // Considerar no mostrar el formulario si este estado es crucial y no existe.
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validar_token_csrf($_POST['csrf_token'])) {
        $mensaje = "<p class='mensaje-error'>Error de validación CSRF. Inténtelo de nuevo.</p>";
    } else {
        $cliente_id = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : '';
        $tipo_equipo = isset($_POST['tipo_equipo']) ? sanitizar_entrada($_POST['tipo_equipo']) : '';
        $marca = isset($_POST['marca']) ? sanitizar_entrada($_POST['marca']) : '';
    $modelo = isset($_POST['modelo']) ? sanitizar_entrada($_POST['modelo']) : '';
    $numero_serie_imei = isset($_POST['numero_serie_imei']) ? sanitizar_entrada($_POST['numero_serie_imei']) : '';
    $fallas_reportadas = isset($_POST['fallas_reportadas']) ? sanitizar_entrada($_POST['fallas_reportadas']) : '';
    $estado_fisico = isset($_POST['estado_fisico']) ? sanitizar_entrada($_POST['estado_fisico']) : '';
    $observaciones = isset($_POST['observaciones']) ? sanitizar_entrada($_POST['observaciones']) : '';
    $accesorios_entregados = isset($_POST['accesorios_entregados']) ? sanitizar_entrada($_POST['accesorios_entregados']) : '';
    $tecnico_asignado_id = isset($_POST['tecnico_asignado_id']) ? (int)$_POST['tecnico_asignado_id'] : '';

    // Validaciones
    if (empty($cliente_id)) $error_validacion['cliente_id'] = "Debe seleccionar un cliente.";
    if (empty($tipo_equipo)) $error_validacion['tipo_equipo'] = "El tipo de equipo es obligatorio.";
    if (empty($marca)) $error_validacion['marca'] = "La marca es obligatoria.";
    if (empty($modelo)) $error_validacion['modelo'] = "El modelo es obligatorio.";
    if (empty($fallas_reportadas)) $error_validacion['fallas_reportadas'] = "Debe describir las fallas reportadas.";
    if (empty($tecnico_asignado_id)) $error_validacion['tecnico_asignado_id'] = "Debe asignar un técnico.";

    // Validar que el estado 'Ingresado' se haya cargado
    if ($estado_ingresado_id === null && empty($error_validacion)) {
         $mensaje = "<p class='mensaje-error'>No se puede registrar el equipo: Falta el estado inicial 'Ingresado'. Contacte al administrador.</p>";
         $error_validacion['sistema'] = "Error de configuración de estados."; // Error general
    }


    if (empty($error_validacion)) {
        $fecha_ingreso = date('Y-m-d H:i:s');

        $sql = "INSERT INTO equipos (cliente_id, tipo_equipo, marca, modelo, numero_serie_imei,
                    fallas_reportadas, estado_fisico, observaciones, accesorios_entregados,
                    tecnico_asignado_id, fecha_ingreso, estado_reparacion_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($enlace, $sql);
        if (!$stmt) {
            $mensaje = log_db_error_y_mostrar_mensaje_generico(mysqli_error($enlace));
        } else {
            mysqli_stmt_bind_param($stmt, "issssssssisi",
                $cliente_id, $tipo_equipo, $marca, $modelo, $numero_serie_imei,
                $fallas_reportadas, $estado_fisico, $observaciones, $accesorios_entregados,
                $tecnico_asignado_id, $fecha_ingreso, $estado_ingresado_id
            );

            if (mysqli_stmt_execute($stmt)) {
                $nuevo_equipo_id = mysqli_insert_id($enlace);
                mysqli_stmt_close($stmt);
                // Redirigir a imprimir ficha
                header("Location: imprimir_ficha.php?id=" . $nuevo_equipo_id);
                return; // Usar return para la herramienta
            } else {
                $mensaje = log_db_error_y_mostrar_mensaje_generico(mysqli_error($enlace), mysqli_stmt_error($stmt));
            }
            if($stmt) mysqli_stmt_close($stmt);
        }
    } // This actually closes if(empty($error_validacion))
    // The following 'if' should be inside the CSRF 'else' block.
    if (empty($mensaje) && !empty($error_validacion)) {
        $mensaje = "<p class='mensaje-error'>Por favor corrija los errores del formulario.</p>";
    }
} // Closes the `else` block for the CSRF check.
// The next brace was removed as it was extra.

require_once '../includes/header.php';
?>

<div class="container-form">
    <h2>Ingresar Nuevo Equipo</h2>

    <?php if (!empty($mensaje)) echo $mensaje; ?>

    <form action="ingresar.php" method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <fieldset>
            <legend>Información del Cliente y Equipo</legend>
            <div class="form-grupo">
                <label for="cliente_id">Cliente:</label>
                <select name="cliente_id" id="cliente_id" required>
                    <option value="">Seleccione un cliente...</option>
                    <?php foreach ($clientes as $cli): ?>
                        <option value="<?php echo $cli['id']; ?>" <?php echo ($cliente_id == $cli['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cli['nombre']) . ' (DNI: ' . htmlspecialchars($cli['dni']) . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($error_validacion['cliente_id'])): ?><span class="error-texto"><?php echo $error_validacion['cliente_id']; ?></span><?php endif; ?>
            </div>

            <div class="form-grupo">
                <label for="tipo_equipo">Tipo de Equipo:</label>
                <input type="text" name="tipo_equipo" id="tipo_equipo" value="<?php echo htmlspecialchars($tipo_equipo); ?>" required placeholder="Ej: Celular, Notebook, Tablet">
                <?php if (isset($error_validacion['tipo_equipo'])): ?><span class="error-texto"><?php echo $error_validacion['tipo_equipo']; ?></span><?php endif; ?>
            </div>

            <div class="form-grupo">
                <label for="marca">Marca:</label>
                <input type="text" name="marca" id="marca" value="<?php echo htmlspecialchars($marca); ?>" required>
                <?php if (isset($error_validacion['marca'])): ?><span class="error-texto"><?php echo $error_validacion['marca']; ?></span><?php endif; ?>
            </div>

            <div class="form-grupo">
                <label for="modelo">Modelo:</label>
                <input type="text" name="modelo" id="modelo" value="<?php echo htmlspecialchars($modelo); ?>" required>
                <?php if (isset($error_validacion['modelo'])): ?><span class="error-texto"><?php echo $error_validacion['modelo']; ?></span><?php endif; ?>
            </div>

            <div class="form-grupo">
                <label for="numero_serie_imei">Número de Serie / IMEI:</label>
                <input type="text" name="numero_serie_imei" id="numero_serie_imei" value="<?php echo htmlspecialchars($numero_serie_imei); ?>">
            </div>
        </fieldset>

        <fieldset>
            <legend>Estado y Fallas</legend>
            <div class="form-grupo">
                <label for="fallas_reportadas">Fallas Reportadas por el Cliente:</label>
                <textarea name="fallas_reportadas" id="fallas_reportadas" rows="4" required><?php echo htmlspecialchars($fallas_reportadas); ?></textarea>
                <?php if (isset($error_validacion['fallas_reportadas'])): ?><span class="error-texto"><?php echo $error_validacion['fallas_reportadas']; ?></span><?php endif; ?>
            </div>

            <div class="form-grupo">
                <label for="estado_fisico">Estado Físico del Equipo (al ingreso):</label>
                <textarea name="estado_fisico" id="estado_fisico" rows="3"><?php echo htmlspecialchars($estado_fisico); ?></textarea>
            </div>

            <div class="form-grupo">
                <label for="observaciones">Observaciones Adicionales (internas):</label>
                <textarea name="observaciones" id="observaciones" rows="3"><?php echo htmlspecialchars($observaciones); ?></textarea>
            </div>

            <div class="form-grupo">
                <label for="accesorios_entregados">Accesorios Entregados por el Cliente:</label>
                <input type="text" name="accesorios_entregados" id="accesorios_entregados" value="<?php echo htmlspecialchars($accesorios_entregados); ?>" placeholder="Ej: Cargador, Cable USB, Funda">
            </div>
        </fieldset>

        <fieldset>
            <legend>Asignación</legend>
            <div class="form-grupo">
                <label for="tecnico_asignado_id">Técnico Asignado:</label>
                <select name="tecnico_asignado_id" id="tecnico_asignado_id" required>
                    <option value="">Seleccione un técnico...</option>
                    <?php foreach ($tecnicos as $tec): ?>
                        <option value="<?php echo $tec['id']; ?>" <?php echo ($tecnico_asignado_id == $tec['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tec['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($error_validacion['tecnico_asignado_id'])): ?><span class="error-texto"><?php echo $error_validacion['tecnico_asignado_id']; ?></span><?php endif; ?>
            </div>
        </fieldset>

        <div>
            <button type="submit" class="btn btn-primario" <?php if($estado_ingresado_id === null) echo 'disabled';?>>Registrar Equipo e Imprimir Ficha</button>
            <?php if($estado_ingresado_id === null): ?>
                 <p class='mensaje-error'>No se puede registrar: Falta el estado inicial 'Ingresado'. Contacte al administrador.</p>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php
require_once '../includes/footer.php';
?>
