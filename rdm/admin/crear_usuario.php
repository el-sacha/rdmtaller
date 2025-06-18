<?php
// admin/crear_usuario.php
require_once '../includes/funciones.php'; // Para sanitizar_entrada, verificar_login, iniciar_sesion_segura
require_once '../includes/db.php';       // Para $enlace

verificar_login(); // Asegura que esté logueado
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    // Guardar mensaje de error en sesión para mostrarlo en la página de destino
    $_SESSION['mensaje_error_layout'] = 'Acceso denegado. Solo los administradores pueden gestionar usuarios.';
    header('Location: ../dashboard.php');
    return; // Usar return en lugar de exit
}

// iniciar_sesion_segura(); // verificar_login ya debería llamar a iniciar_sesion_segura

$mensaje = ''; // Para mostrar mensajes de éxito o error
$nombre_usuario_form = ''; // Para repopular el formulario
$rol_form = '';
$tecnico_id_form = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_usuario_form = isset($_POST['nombre_usuario']) ? sanitizar_entrada($_POST['nombre_usuario']) : '';
    $contrasena = isset($_POST['contrasena']) ? $_POST['contrasena'] : '';
    $rol_form = isset($_POST['rol']) ? sanitizar_entrada($_POST['rol']) : '';
    $tecnico_id_form = isset($_POST['tecnico_id']) && !empty($_POST['tecnico_id']) ? (int)$_POST['tecnico_id'] : null;

    if (empty($nombre_usuario_form) || empty($contrasena) || empty($rol_form)) {
        $mensaje = "<div class='alert alert-danger'>Todos los campos (Usuario, Contraseña, Rol) son obligatorios.</div>";
    } elseif ($rol_form === 'tecnico' && $tecnico_id_form === null) {
        $mensaje = "<div class='alert alert-danger'>Debe seleccionar un técnico si el rol es 'tecnico'.</div>";
    } else {
        $contrasena_hasheada = password_hash($contrasena, PASSWORD_DEFAULT);
        if (!$contrasena_hasheada) {
            $mensaje = "<div class='alert alert-danger'>Error al hashear la contraseña.</div>";
        } else {
            $stmt_check = mysqli_prepare($enlace, "SELECT id FROM usuarios WHERE nombre_usuario = ?");
            mysqli_stmt_bind_param($stmt_check, "s", $nombre_usuario_form);
            mysqli_stmt_execute($stmt_check);
            $resultado_check = mysqli_stmt_get_result($stmt_check);

            if (mysqli_fetch_assoc($resultado_check)) {
                $mensaje = "<div class='alert alert-warning'>El nombre de usuario '$nombre_usuario_form' ya está en uso.</div>";
                mysqli_stmt_close($stmt_check);
            } else {
                mysqli_stmt_close($stmt_check);
                $sql = "INSERT INTO usuarios (nombre_usuario, contrasena, rol, tecnico_id) VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($enlace, $sql);
                if (!$stmt) {
                    $mensaje = "<div class='alert alert-danger'>Error al preparar la consulta: " . mysqli_error($enlace) . "</div>";
                } else {
                    mysqli_stmt_bind_param($stmt, "sssi", $nombre_usuario_form, $contrasena_hasheada, $rol_form, $tecnico_id_form);
                    if (mysqli_stmt_execute($stmt)) {
                        $mensaje = "<div class='alert alert-success'>Usuario '$nombre_usuario_form' creado exitosamente.</div>";
                        // Limpiar campos para el próximo ingreso
                        $nombre_usuario_form = $rol_form = $tecnico_id_form = '';
                    } else {
                        $mensaje = "<div class='alert alert-danger'>Error al crear el usuario: " . mysqli_stmt_error($stmt) . "</div>";
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

// Obtener lista de técnicos para el dropdown
$lista_tecnicos = [];
$stmt_tecnicos = mysqli_prepare($enlace, "SELECT id, nombre FROM tecnicos ORDER BY nombre ASC");
if ($stmt_tecnicos) {
    mysqli_stmt_execute($stmt_tecnicos);
    $resultado_tecnicos = mysqli_stmt_get_result($stmt_tecnicos);
    while ($tec = mysqli_fetch_assoc($resultado_tecnicos)) {
        $lista_tecnicos[] = $tec;
    }
    mysqli_stmt_close($stmt_tecnicos);
}

require_once '../includes/header.php'; // Incluir el header estándar
?>

<div class="container mt-4"> <?php // Contenedor principal de Bootstrap ya está en header/footer ?>
    <h2>Gestión de Usuarios</h2>
    <p class="text-muted">Crear un nuevo usuario para el sistema.</p>

    <?php if (!empty($mensaje)) echo $mensaje; ?>

    <div class="card">
        <div class="card-header">
            Formulario de Nuevo Usuario
        </div>
        <div class="card-body">
            <form action="crear_usuario.php" method="POST">
                <div class="mb-3">
                    <label for="nombre_usuario" class="form-label">Nombre de Usuario:</label>
                    <input type="text" name="nombre_usuario" id="nombre_usuario" class="form-control" value="<?php echo htmlspecialchars($nombre_usuario_form); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="contrasena" class="form-label">Contraseña:</label>
                    <input type="password" name="contrasena" id="contrasena" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="rol" class="form-label">Rol:</label>
                    <select name="rol" id="rol" class="form-select" required>
                        <option value="">Seleccione un rol</option>
                        <option value="admin" <?php echo ($rol_form === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                        <option value="tecnico" <?php echo ($rol_form === 'tecnico') ? 'selected' : ''; ?>>Técnico</option>
                    </select>
                </div>
                <div class="mb-3" id="campo_tecnico_id" style="<?php echo ($rol_form === 'tecnico') ? 'display:block;' : 'display:none;'; ?>">
                    <label for="tecnico_id" class="form-label">Asignar a Técnico (si el rol es Técnico):</label>
                    <select name="tecnico_id" id="tecnico_id" class="form-select">
                        <option value="">Ninguno</option>
                        <?php foreach ($lista_tecnicos as $tecnico): ?>
                            <option value="<?php echo htmlspecialchars($tecnico['id']); ?>" <?php echo ($tecnico_id_form == $tecnico['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tecnico['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>

    <p class="mt-3"><a href="../dashboard.php" class="btn btn-secondary">Volver al Dashboard</a></p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rolSelect = document.getElementById('rol');
    const tecnicoIdField = document.getElementById('campo_tecnico_id');

    function toggleTecnicoField() {
        if (rolSelect.value === 'tecnico') {
            tecnicoIdField.style.display = 'block';
        } else {
            tecnicoIdField.style.display = 'none';
        }
    }
    rolSelect.addEventListener('change', toggleTecnicoField);
    // toggleTecnicoField(); // Llamar al cargar por si hay valores preseleccionados (ya manejado con style en PHP)
});
</script>

<?php
require_once '../includes/footer.php'; // Incluir el footer estándar
?>
