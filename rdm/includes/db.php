<?php
// includes/db.php

// Definición de constantes para la conexión a la base de datos
// Se priorizan las variables de entorno, con fallbacks para desarrollo local.
// EN PRODUCCIÓN: Asegúrese de que las variables de entorno estén configuradas y NUNCA dependa de los fallbacks.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');         // Host de la base de datos
define('DB_USER', getenv('DB_USER') ?: 'rdm_user');          // Usuario de la base de datos
define('DB_PASS', getenv('DB_PASS') ?: 'password');          // Contraseña del usuario de la base de datos
define('DB_NAME', getenv('DB_NAME') ?: 'rdm_db');            // Nombre de la base de datos

// Comentario para el desarrollador:
// Para que getenv() funcione correctamente en entornos como Apache/Nginx,
// las variables de entorno deben ser pasadas explícitamente por el servidor web
// (ej. a través de FPM config, SetEnv en Apache, fastcgi_param en Nginx)
// o mediante un archivo .htaccess (si Apache está configurado para permitirlo).
// Alternativamente, para desarrollo local o algunos entornos de despliegue,
// se puede usar una librería como PHP dotenv para cargar variables desde un archivo .env.
// Esta implementación NO incluye una librería dotenv por defecto.

// Intentar conectar a la base de datos usando MySQLi
$enlace = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar si la conexión fue exitosa
if (!$enlace) {
    // Si la conexión falla, mostrar un mensaje de error y terminar la ejecución del script.
    // En un entorno de producción, es recomendable registrar este error en un archivo de logs
    // en lugar de mostrarlo directamente al usuario por razones de seguridad.
    error_log("Error de conexión a la base de datos: " . mysqli_connect_error());
    die("Error de conexión: No se pudo conectar a la base de datos. Por favor, inténtelo más tarde.");
}

// Opcional: Establecer el juego de caracteres a UTF-8 (recomendado)
if (!mysqli_set_charset($enlace, "utf8mb4")) {
    // Si falla el establecimiento del charset, registrar el error.
    // No es crítico para la conexión inicial, pero sí para el manejo de datos.
    error_log("Error al establecer el juego de caracteres UTF-8: " . mysqli_error($enlace));
}

// La variable $enlace contiene el objeto de conexión y estará disponible
// para otros scripts que incluyan este archivo.

// Ejemplo de cómo se usaría en otro archivo:
// include 'includes/db.php';
// $resultado = mysqli_query($enlace, "SELECT * FROM clientes");
// ... etc.

?>
