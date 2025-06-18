<?php
// includes/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$pagina_actual = basename($_SERVER['PHP_SELF']);
$directorio_actual = basename(dirname($_SERVER['PHP_SELF']));

$path_prefix = '../';
if ($directorio_actual === 'rdm' || $directorio_actual === 'app' || $directorio_actual === '') {
    $path_prefix = './';
}

$bootstrap_css_path = $path_prefix . "assets/css/bootstrap.min.css";
$custom_css_path = $path_prefix . "assets/css/style.css";

// $nav_base_path determina si los enlaces principales deben usar '../' o './'
// Si la página actual está en un subdirectorio de rdm (clientes, equipos, etc.), $nav_base_path es '..'
// Si la página actual está en la raíz de rdm (index.php, dashboard.php), $nav_base_path es '.'
$nav_base_path = (in_array($directorio_actual, ['clientes', 'tecnicos', 'equipos', 'facturas', 'admin', 'reportes']) && $directorio_actual !== 'rdm' && $directorio_actual !== 'app') ? '..' : '.';

// global_search_results.php siempre estará en la raíz de rdm, por lo que el action del form necesita $nav_base_path
$global_search_action_path = $nav_base_path . "/global_search_results.php";
// Si $nav_base_path es '.',  action será "./global_search_results.php"
// Si $nav_base_path es '..', action será "../global_search_results.php"
// Esto es correcto porque header.php es incluido desde diferentes niveles.

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RDM - Reparación de Dispositivos Móviles</title>
    <link href="<?php echo $bootstrap_css_path; ?>" rel="stylesheet">
    <?php
        // Para file_exists, necesitamos una ruta relativa al script actual (header.php) o absoluta del servidor.
        // Asumiendo que header.php está en rdm/includes/ y style.css en rdm/assets/css/
        $custom_css_server_path = dirname(__FILE__) . '/../assets/css/style.css';
        if (file_exists($custom_css_server_path)):
    ?>
        <link href="<?php echo $path_prefix . 'assets/css/style.css'; ?>" rel="stylesheet">
    <?php endif; ?>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $nav_base_path; ?>/index.php">RDM S.A.</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNavDropdown">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($pagina_actual == 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo $nav_base_path; ?>/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item dropdown <?php echo ($directorio_actual == 'equipos') ? 'active' : ''; ?>">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownEquipos" role="button" data-bs-toggle="dropdown" aria-expanded="false">Equipos</a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownEquipos">
                            <li><a class="dropdown-item" href="<?php echo $nav_base_path; ?>/equipos/ingresar.php">Ingresar Equipo</a></li>
                            <li><a class="dropdown-item" href="<?php echo $nav_base_path; ?>/equipos/listar_equipos.php">Listar Equipos</a></li>
                        </ul>
                    </li>
                     <li class="nav-item dropdown <?php echo ($directorio_actual == 'facturas') ? 'active' : ''; ?>">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownFacturas" role="button" data-bs-toggle="dropdown" aria-expanded="false">Facturación</a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownFacturas">
                            <li><a class="dropdown-item" href="<?php echo $nav_base_path; ?>/facturas/generar_factura.php">Nueva Factura</a></li>
                            <li><a class="dropdown-item" href="<?php echo $nav_base_path; ?>/facturas/listar_facturas.php">Listar Facturas</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($directorio_actual == 'clientes') ? 'active' : ''; ?>" href="<?php echo $nav_base_path; ?>/clientes/listar.php">Clientes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($directorio_actual == 'tecnicos') ? 'active' : ''; ?>" href="<?php echo $nav_base_path; ?>/tecnicos/listar.php">Técnicos</a>
                    </li>
                    <li class="nav-item dropdown <?php echo ($directorio_actual == 'reportes') ? 'active' : ''; ?>">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownReportes" role="button" data-bs-toggle="dropdown" aria-expanded="false">Reportes</a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownReportes">
                            <li><a class="dropdown-item" href="<?php echo $nav_base_path; ?>/reportes/equipos_por_fecha.php">Equipos por Fecha</a></li>
                            <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                                <li><a class="dropdown-item" href="<?php echo $nav_base_path; ?>/reportes/recaudacion_por_fecha.php">Recaudación por Fecha</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                        <li class="nav-item dropdown <?php echo ($directorio_actual == 'admin') ? 'active' : ''; ?>">
                             <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownAdmin" role="button" data-bs-toggle="dropdown" aria-expanded="false">Administración</a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdownAdmin">
                                <li><a class="dropdown-item" href="<?php echo $nav_base_path; ?>/admin/crear_usuario.php">Gestionar Usuarios</a></li>
                                <li><a class="dropdown-item" href="<?php echo $nav_base_path; ?>/admin/datos_fiscales.php">Datos Fiscales</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <?php if (isset($_SESSION['usuario_id'])): ?>
                <form class="d-flex me-2" role="search" action="<?php echo $global_search_action_path; ?>" method="GET">
                    <input class="form-control form-control-sm me-2" type="search" name="q_global" placeholder="Búsqueda Global..." aria-label="Buscar" value="<?php echo isset($_GET['q_global']) ? htmlspecialchars($_GET['q_global']) : ''; ?>">
                    <button class="btn btn-outline-success btn-sm" type="submit">Buscar</button>
                </form>
            <?php endif; ?>

            <ul class="navbar-nav">
                 <?php if (isset($_SESSION['usuario_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white" href="#" id="navbarUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?> (<?php echo htmlspecialchars($_SESSION['rol']); ?>)
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarUserDropdown">
                            <li><a class="dropdown-item" href="#">Mi Perfil</a></li> <!-- Placeholder -->
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $nav_base_path; ?>/logout.php">Cerrar Sesión</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                         <a class="nav-link <?php echo ($pagina_actual == 'seguimiento.php' && $directorio_actual == 'equipos') ? 'active' : ''; ?>" href="<?php echo $nav_base_path; ?>/equipos/seguimiento.php">Seguimiento Pedido</a>
                    </li>
                    <?php if ($pagina_actual != 'index.php' && $pagina_actual != 'login.php'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $nav_base_path; ?>/index.php">Iniciar Sesión</a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4"> <!-- Inicio del container principal -->
    <?php
    // Mostrar mensajes de error globales del layout si existen (ej. acceso denegado)
    if (isset($_SESSION['mensaje_error_layout'])) {
        echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . htmlspecialchars($_SESSION['mensaje_error_layout']) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        unset($_SESSION['mensaje_error_layout']);
    }
    ?>
    <!-- El contenido principal de cada página irá aquí -->
