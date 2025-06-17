<?php
// includes/header.php
// Iniciar sesión si no está iniciada (esto es común en headers)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Determinar la página actual para la lógica del enlace de Login
$pagina_actual = basename($_SERVER['PHP_SELF']);
$directorio_actual = basename(dirname($_SERVER['PHP_SELF']));

// Base URL para enlaces y assets
// Asumimos que la raíz del sitio es /app/rdm/ si los includes están en /app/rdm/includes/
// Si la estructura cambia o el servidor web apunta a /app/rdm/ directamente, esto podría ser solo "/"
$base_url = '/app/rdm';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RDM - Reparación de Dispositivos Móviles</title>
    <!-- Ajustar la ruta de style.css según dónde esté header.php y la estructura de assets -->
    <?php
        // Determinar la ruta correcta para el CSS
        $css_path = "assets/css/style.css"; // Por defecto para páginas en la raíz de RDM
        if ($directorio_actual !== 'rdm' && $directorio_actual !== 'app') { // Si estamos en un subdirectorio como 'clientes', 'equipos'
            $css_path = "../assets/css/style.css";
        } elseif (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) { // Si estamos en el subdirectorio 'admin'
             $css_path = "../assets/css/style.css";
        }
    ?>
    <link rel="stylesheet" href="<?php echo $css_path; ?>">
    <!-- Aquí podrías agregar más enlaces a CSS, fuentes, etc. -->
</head>
<body>
    <header>
        <h1><a href="<?php echo $base_url; ?>/index.php">RDM - Gestión de Reparaciones</a></h1>
        <nav>
            <ul>
                <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li><a href="<?php echo $base_url; ?>/dashboard.php">Dashboard</a></li>
                    <li><a href="<?php echo $base_url; ?>/equipos/ingresar.php">Ingresar Equipo</a></li>
                    <li><a href="<?php echo $base_url; ?>/clientes/listar.php">Clientes</a></li>
                    <li><a href="<?php echo $base_url; ?>/tecnicos/listar.php">Técnicos</a></li>
                    <li><a href="<?php echo $base_url; ?>/equipos/listar.php">Equipos</a></li>
                    <li><a href="<?php echo $base_url; ?>/facturas/listar.php">Facturas</a></li>
                    <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                        <li><a href="<?php echo $base_url; ?>/admin/crear_usuario.php">Admin Usuarios</a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo $base_url; ?>/logout.php">Cerrar Sesión</a></li>
                <?php else: ?>
                    <?php if ($pagina_actual != 'index.php' && $pagina_actual != 'login.php'): ?>
                        <li><a href="<?php echo $base_url; ?>/index.php">Iniciar Sesión</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main>
        <!-- El contenido principal de cada página irá aquí -->
