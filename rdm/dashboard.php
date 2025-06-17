<?php
// dashboard.php
// Este script debe llamar a funciones de funciones.php para verificar login
// y luego incluir el header.

// Incluir funciones primero para usar verificar_login()
require_once 'includes/funciones.php';

// Verificar si el usuario está logueado. La función se encargará de redirigir si no.
// Esta función debe iniciar la sesión si aún no está iniciada.
verificar_login(); // Esta función debe existir en funciones.php

// Incluir el header después de la lógica de sesión y verificación
require_once 'includes/header.php';

?>

<div class="container-dashboard">
    <h2>Dashboard</h2>
    <p>Bienvenido al panel de control, <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?>!</p>
    <p>Tu rol es: <?php echo htmlspecialchars($_SESSION['rol']); ?></p>

    <p>Aquí podrás ver un resumen de la actividad reciente, estadísticas y accesos directos a las funcionalidades principales.</p>

    <ul>
        <li><a href="equipos/agregar.php">Registrar Nuevo Equipo</a></li>
        <li><a href="clientes/agregar.php">Agregar Nuevo Cliente</a></li>
        <li>Ver Equipos Pendientes</li>
        <li>Últimas Facturas Emitidas</li>
    </ul>
</div>

<?php
require_once 'includes/footer.php';
?>
