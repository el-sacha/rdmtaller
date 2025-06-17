<?php
// admin/crear_usuario.php

// IMPORTANTE: Esta página DEBE estar protegida para que solo administradores puedan acceder.
// Implementar verificación de rol aquí. Ejemplo:
/*
require_once '../includes/funciones.php';
verificar_login(); // Asegura que esté logueado
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    // Si no es admin, redirigir o mostrar error
    // header('Location: ../dashboard.php');
    die('Acceso denegado. Solo administradores.'); // Usar return/exit en producción
}
*/
// Por ahora, se deja accesible para desarrollo.

require_once '../includes/db.php'; // Para $enlace
require_once '../includes/funciones.php'; // Para sanitizar_entrada y (eventualmente) verificar_login, es_admin, etc.

// Iniciar sesión si no está activa (necesario para mensajes flash, etc.)
iniciar_sesion_segura();

$mensaje = ''; // Para mostrar mensajes de éxito o error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Procesar el formulario
    $nombre_usuario = isset($_POST['nombre_usuario']) ? sanitizar_entrada($_POST['nombre_usuario']) : '';
    $contrasena = isset($_POST['contrasena']) ? $_POST['contrasena'] : ''; // Contraseña en texto plano
    $rol = isset($_POST['rol']) ? sanitizar_entrada($_POST['rol']) : '';
    $tecnico_id = isset($_POST['tecnico_id']) && !empty($_POST['tecnico_id']) ? (int)$_POST['tecnico_id'] : null;


    // Validaciones básicas
    if (empty($nombre_usuario) || empty($contrasena) || empty($rol)) {
        $mensaje = "<p class='mensaje-error'>Todos los campos (Usuario, Contraseña, Rol) son obligatorios.</p>";
    } elseif ($rol === 'tecnico' && $tecnico_id === null) {
        $mensaje = "<p class='mensaje-error'>Debe seleccionar un técnico si el rol es 'tecnico'.</p>";
    } else {
        // Hashear la contraseña
        $contrasena_hasheada = password_hash($contrasena, PASSWORD_DEFAULT);

        if (!$contrasena_hasheada) {
            $mensaje = "<p class='mensaje-error'>Error al hashear la contraseña.</p>";
        } else {
            // Verificar si el nombre de usuario ya existe
            $stmt_check = mysqli_prepare($enlace, "SELECT id FROM usuarios WHERE nombre_usuario = ?");
            mysqli_stmt_bind_param($stmt_check, "s", $nombre_usuario);
            mysqli_stmt_execute($stmt_check);
            $resultado_check = mysqli_stmt_get_result($stmt_check);

            if (mysqli_fetch_assoc($resultado_check)) {
                $mensaje = "<p class='mensaje-error'>El nombre de usuario '$nombre_usuario' ya está en uso.</p>";
                mysqli_stmt_close($stmt_check);
            } else {
                mysqli_stmt_close($stmt_check); // Cerrar la sentencia de verificación

                // Preparar la consulta para insertar el nuevo usuario
                // Asegurarse que $tecnico_id sea NULL si no es un técnico o no se proveyó
                $sql = "INSERT INTO usuarios (nombre_usuario, contrasena, rol, tecnico_id) VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($enlace, $sql);

                if (!$stmt) {
                    $mensaje = "<p class='mensaje-error'>Error al preparar la consulta de inserción: " . mysqli_error($enlace) . "</p>";
                } else {
                    mysqli_stmt_bind_param($stmt, "sssi", $nombre_usuario, $contrasena_hasheada, $rol, $tecnico_id);

                    if (mysqli_stmt_execute($stmt)) {
                        $mensaje = "<p class='mensaje-exito'>Usuario '$nombre_usuario' creado exitosamente.</p>";
                    } else {
                        $mensaje = "<p class='mensaje-error'>Error al crear el usuario: " . mysqli_stmt_error($stmt) . "</p>";
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

// Obtener lista de técnicos para el dropdown (si el rol es técnico)
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


// Incluir header (simplificado o uno específico para admin si se desea)
// Para este ejemplo, asumimos que el header general puede funcionar o se adaptará.
// Si se usa el header general, asegurarse que las rutas a CSS/JS sean correctas desde /admin/
// require_once '../includes/header.php'; // Puede causar problemas de rutas relativas si no se maneja bien
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Nuevo Usuario</title>
    <!-- Enlazar a un CSS general o uno específico para admin -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Estilos básicos para el formulario */
        .container-crear-usuario { max-width: 500px; margin: 20px auto; padding: 20px; border: 1px solid #ccc; border-radius: 5px; }
        .form-grupo { margin-bottom: 15px; }
        .form-grupo label { display: block; margin-bottom: 5px; }
        .form-grupo input, .form-grupo select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; box-sizing: border-box; }
        .mensaje-exito { color: green; border: 1px solid green; padding: 10px; margin-bottom:15px; }
        .mensaje-error { color: red; border: 1px solid red; padding: 10px; margin-bottom:15px; }
        /* Añadir comentario sobre protección de esta página */
        .comentario-seguridad { color: #900; font-weight: bold; margin-bottom: 15px; padding:10px; border:1px solid #900; background-color: #fdd; }
    </style>
</head>
<body>
    <div class="container-crear-usuario">
        <h1>Crear Nuevo Usuario</h1>
        <div class="comentario-seguridad">
            ADVERTENCIA: Esta página es para la creación de usuarios y actualmente no está protegida.
            En un sistema en producción, se debe restringir el acceso solo a usuarios administradores.
        </div>

        <?php if (!empty($mensaje)) echo $mensaje; ?>

        <form action="crear_usuario.php" method="POST">
            <div class="form-grupo">
                <label for="nombre_usuario">Nombre de Usuario:</label>
                <input type="text" name="nombre_usuario" id="nombre_usuario" required>
            </div>
            <div class="form-grupo">
                <label for="contrasena">Contraseña:</label>
                <input type="password" name="contrasena" id="contrasena" required>
            </div>
            <div class="form-grupo">
                <label for="rol">Rol:</label>
                <select name="rol" id="rol" required>
                    <option value="">Seleccione un rol</option>
                    <option value="admin">Administrador</option>
                    <option value="tecnico">Técnico</option>
                </select>
            </div>
            <div class="form-grupo" id="campo_tecnico_id" style="display:none;"> <!-- Oculto por defecto -->
                <label for="tecnico_id">Asignar a Técnico (si el rol es Técnico):</label>
                <select name="tecnico_id" id="tecnico_id">
                    <option value="">Ninguno</option>
                    <?php foreach ($lista_tecnicos as $tecnico): ?>
                        <option value="<?php echo htmlspecialchars($tecnico['id']); ?>">
                            <?php echo htmlspecialchars($tecnico['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit">Crear Usuario</button>
            </div>
        </form>
        <p><a href="../dashboard.php">Volver al Dashboard</a></p>
    </div>

    <script>
        // Mostrar/ocultar campo de técnico_id basado en el rol seleccionado
        document.getElementById('rol').addEventListener('change', function() {
            var campoTecnico = document.getElementById('campo_tecnico_id');
            if (this.value === 'tecnico') {
                campoTecnico.style.display = 'block';
            } else {
                campoTecnico.style.display = 'none';
            }
        });
        // Ejecutar al cargar por si hay valores pre-seleccionados (ej. después de un error de envío)
        if (document.getElementById('rol').value === 'tecnico') {
             document.getElementById('campo_tecnico_id').style.display = 'block';
        }
    </script>
</body>
</html>
<?php
// mysqli_close($enlace); // Considerar cerrar la conexión aquí o en un footer si se usa uno.
?>
