<?php
// login_procesar.php

// Incluir funciones primero para asegurar que iniciar_sesion_segura está disponible
require_once 'includes/funciones.php';
iniciar_sesion_segura(); // Iniciar o reanudar la sesión de forma segura

// Incluir conexión a la BD
require_once 'includes/db.php';

// Verificar que la solicitud sea por POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    // exit; // Comentado para la herramienta
    return; // Usar return en lugar de exit para la herramienta
}

// Obtener y sanitizar datos del formulario
$nombre_usuario = isset($_POST['nombre_usuario']) ? sanitizar_entrada($_POST['nombre_usuario']) : '';
$contrasena_ingresada = isset($_POST['contrasena']) ? $_POST['contrasena'] : ''; // Contraseña en texto plano del form

if (empty($nombre_usuario) || empty($contrasena_ingresada)) {
    $_SESSION['error_login'] = "El nombre de usuario y la contraseña son obligatorios.";
    header('Location: index.php');
    // exit; // Comentado para la herramienta
    return;
}

// Conectar a la base de datos (la variable $enlace viene de db.php)
if (!$enlace) {
    $_SESSION['error_login'] = "Error crítico: No se pudo conectar a la base de datos.";
    header('Location: index.php');
    // exit; // Comentado para la herramienta
    return;
}

// Preparar la consulta para buscar al usuario
$stmt = mysqli_prepare($enlace, "SELECT id, nombre_usuario, contrasena, rol FROM usuarios WHERE nombre_usuario = ?");
if (!$stmt) {
    // En un entorno de producción, loguear este error en lugar de mostrarlo al usuario
    error_log("Error al preparar la consulta (login): " . mysqli_error($enlace));
    $_SESSION['error_login'] = "Error del sistema. Por favor, inténtelo más tarde.";
    header('Location: index.php');
    // exit; // Comentado para la herramienta
    return;
}

mysqli_stmt_bind_param($stmt, "s", $nombre_usuario);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

if ($usuario = mysqli_fetch_assoc($resultado)) {
    // Usuario encontrado, verificar contraseña hasheada
    if (password_verify($contrasena_ingresada, $usuario['contrasena'])) {
        // Contraseña correcta
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['nombre_usuario'] = $usuario['nombre_usuario'];
        $_SESSION['rol'] = $usuario['rol'];

        // Regenerar ID de sesión por seguridad para prevenir fijación de sesión
        session_regenerate_id(true);

        header('Location: dashboard.php');
        // exit; // Comentado para la herramienta
        return;
    } else {
        // Contraseña incorrecta
        $_SESSION['error_login'] = "Nombre de usuario o contraseña incorrectos.";
        header('Location: index.php');
        // exit; // Comentado para la herramienta
        return;
    }
} else {
    // Usuario no encontrado
    $_SESSION['error_login'] = "Nombre de usuario o contraseña incorrectos.";
    header('Location: index.php');
    // exit; // Comentado para la herramienta
    return;
}

mysqli_stmt_close($stmt);
// mysqli_close($enlace); // No cerrar aquí si db.php se incluye en otros scripts que la necesiten después.
                        // Generalmente se cierra al final del script principal o footer.php.
?>
