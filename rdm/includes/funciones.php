<?php
// includes/funciones.php

// Este archivo contendrá funciones PHP comunes utilizadas en toda la aplicación.

/**
 * Inicia la sesión si no está ya iniciada.
 */
function iniciar_sesion_segura() {
    if (session_status() == PHP_SESSION_NONE) {
        // Configuración de cookies de sesión para mayor seguridad
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $cookieParams['lifetime'],
            'path' => $cookieParams['path'],
            'domain' => $cookieParams['domain'],
            'secure' => true, // Solo enviar cookie sobre HTTPS (ajustar si no usas HTTPS en desarrollo)
            'httponly' => true, // Prevenir acceso a la cookie vía JavaScript
            'samesite' => 'Lax' // Ayuda a prevenir CSRF
        ]);
        session_start();
    }
}

/**
 * Sanitiza una cadena de texto para prevenir XSS simple.
 * Para una sanitización más robusta, considera librerías como HTML Purifier.
 * @param string $data La cadena a sanitizar.
 * @return string La cadena sanitizada.
 */
function sanitizar_entrada($data) {
    if (is_array($data)) {
        return array_map('sanitizar_entrada', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Verifica si hay un usuario logueado.
 * Si no está logueado, redirige a la página de login (index.php por defecto).
 * Esta función debe llamarse al inicio de cualquier script que requiera autenticación.
 * @param string $redirect_url La URL a la que redirigir si no está logueado.
 */
function verificar_login($redirect_url = 'index.php') {
    iniciar_sesion_segura(); // Asegura que la sesión esté iniciada

    if (!isset($_SESSION['usuario_id'])) {
        // Guardar la URL a la que se intentaba acceder para redirigir después del login (opcional)
        // $_SESSION['redirect_url_despues_login'] = $_SERVER['REQUEST_URI'];

        header("Location: " . $redirect_url);
        // exit; // Comentado para la herramienta, pero importante en producción
        return; // Usar return para la herramienta
    }
    // Si se quiere, se puede añadir una comprobación de actividad reciente aquí
    // para cerrar sesión por inactividad.
}


// Alias para la función de sanitización anterior si se usó 'sanitizar'
if (!function_exists('sanitizar')) {
    function sanitizar($data) {
        return sanitizar_entrada($data);
    }
}

// Otras funciones útiles podrían ser:
// - function es_admin() { return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'; }
// - function formatear_fecha($fecha, $formato = 'd/m/Y H:i:s') { ... }
// - function generar_token_csrf() { ... }
// - function validar_token_csrf() { ... }

?>
