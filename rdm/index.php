<?php
// index.php (Página de Login)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Si el usuario ya está logueado, redirigir a dashboard.php
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    // exit; // Comentado para evitar problemas con la herramienta de ejecución de bash
}

require_once 'includes/header.php';
?>

<div class="container-login">
    <h2>Iniciar Sesión</h2>

    <?php
    // Mostrar mensajes de error si existen
    if (isset($_SESSION['error_login'])) {
        echo '<p class="mensaje-error">' . htmlspecialchars($_SESSION['error_login']) . '</p>';
        unset($_SESSION['error_login']); // Limpiar el mensaje después de mostrarlo
    }
    ?>

    <form action="login_procesar.php" method="POST">
        <div>
            <label for="nombre_usuario">Usuario:</label>
            <input type="text" name="nombre_usuario" id="nombre_usuario" required>
        </div>
        <div>
            <label for="contrasena">Contraseña:</label>
            <input type="password" name="contrasena" id="contrasena" required>
        </div>
        <div>
            <button type="submit">Entrar</button>
        </div>
    </form>
</div>

<?php
require_once 'includes/footer.php';
?>
