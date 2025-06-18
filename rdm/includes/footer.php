<?php
// includes/footer.php

$directorio_actual_footer = basename(dirname($_SERVER['PHP_SELF']));
// Ajuste para la lógica de path_prefix en el footer
// Si el footer es llamado desde /rdm/index.php, $directorio_actual_footer será 'rdm'
// Si es llamado desde /rdm/clientes/listar.php, $directorio_actual_footer será 'clientes'
$path_prefix_footer = '';
if (in_array($directorio_actual_footer, ['clientes', 'tecnicos', 'equipos', 'facturas', 'admin', 'reportes']) && $directorio_actual_footer !== 'rdm') {
    $path_prefix_footer = '../';
} else if ($directorio_actual_footer === 'rdm' || $directorio_actual_footer === 'app' || $directorio_actual_footer === '') {
    // Si está en la raíz de /rdm/ (ej. index.php) o en /app/ (menos probable para un include web)
    $path_prefix_footer = './';
} else {
     // Fallback si la estructura es inesperada, asume que está un nivel abajo de rdm (ej. rdm/includes/footer.php)
     // y es llamado desde rdm/pagina.php o rdm/subfolder/pagina.php.
     // Este caso es complejo de generalizar perfectamente sin conocer la estructura exacta del servidor.
     // La lógica en header.php para $nav_base_path es más robusta para la navegación.
     // Para assets, es más simple si asumimos que el path es relativo a la raíz del proyecto RDM.
     // Si header/footer están en /rdm/includes/, y assets en /rdm/assets/,
     // entonces desde una página en /rdm/clientes/, el path a assets sería '../assets/'.
     // Desde una página en /rdm/, el path sería './assets/'.

     // Re-evaluando la lógica de $path_prefix para assets desde el footer:
     // Si la página actual (PHP_SELF) está en un subdirectorio de RDM (e.g., /rdm/clientes/page.php),
     // entonces $path_prefix_footer debe ser '../'.
     // Si la página actual está en la raíz de RDM (e.g., /rdm/index.php),
     // entonces $path_prefix_footer debe ser './'.
    if (strpos($_SERVER['PHP_SELF'], '/rdm/') !== false && dirname($_SERVER['PHP_SELF']) !== '/rdm') {
        $path_prefix_footer = '../'; // Estamos en un subdirectorio de /rdm
    } else {
        $path_prefix_footer = './'; // Estamos en /rdm/ o una estructura no reconocida
    }
}


$bootstrap_js_path = $path_prefix_footer . "assets/js/bootstrap.bundle.min.js";
$chart_js_path = $path_prefix_footer . "assets/js/chart.umd.min.js";
$custom_js_path = $path_prefix_footer . "assets/js/main.js";
?>
</div> <!-- Cierre del <div class="container mt-4"> abierto en header.php -->
</main> <!-- Cierre de la etiqueta <main> abierta en header.php -->

<footer class="bg-dark text-white text-center text-lg-start mt-auto py-3">
    <div class="container">
        <p class="text-center mb-0">&copy; <?php echo date("Y"); ?> RDM - Reparación de Dispositivos Móviles. Todos los derechos reservados.</p>
    </div>
</footer>

<script src="<?php echo $bootstrap_js_path; ?>"></script>
<script src="<?php echo $chart_js_path; ?>"></script>

<?php
// Construir la ruta absoluta al archivo main.js para la comprobación file_exists
// Asumiendo que rdm es el directorio raíz accesible por el servidor web.
$doc_root = $_SERVER['DOCUMENT_ROOT'];
$path_to_rdm_from_doc_root = ''; // Si rdm está en la raíz del doc_root
                               // Si rdm está en un subdirectorio, ej: /proyectos/rdm, esto sería '/proyectos'

// Intentar una lógica más simple para file_exists, asumiendo que $path_prefix_footer es correcto
// relativo a la ubicación del script PHP que incluye el footer.
$potential_main_js_path = dirname(__FILE__) . '/' . $path_prefix_footer . 'assets/js/main.js';
// Normalizar la ruta para eliminar '..' etc.
$real_main_js_path = realpath($potential_main_js_path);


// Carga main.js solo si existe. La ruta para file_exists debe ser absoluta del servidor.
// La lógica de $path_prefix_footer es para la URL web. Para el sistema de archivos, necesitamos ser más precisos.
// Esto sigue siendo un poco frágil. Una constante global BASE_PATH definida en un config.php sería más robusta.
$main_js_server_path = $_SERVER['DOCUMENT_ROOT'] . str_replace('./', '/', $path_prefix_footer . 'assets/js/main.js');
// Simplificación: si el archivo es referenciado con ../assets, y header/footer están en includes,
// y la página está en rdm/subdir/, entonces la ruta desde doc_root sería /rdm_base/assets/...
// Esta parte de la lógica de paths es la más compleja de hacer universalmente correcta.
// Para el sandbox, la estructura es /app/rdm/....
// Si PHP_SELF es /app/rdm/clientes/listar.php, $path_prefix_footer es '../'
// Entonces $path_prefix_footer . 'assets/js/main.js' es '../assets/js/main.js'
// Esta es una URL relativa. Para file_exists, necesitamos una ruta de servidor.
// Vamos a asumir que rdm/ es la raíz web para simplificar el file_exists.
// $main_js_for_file_exists = 'assets/js/main.js'; // Si rdm/ es la raíz web
// if ($path_prefix_footer === '../') $main_js_for_file_exists = '../assets/js/main.js'; // No, esto es para URL

// Una forma más simple para file_exists dentro del contexto del script actual:
$path_to_main_js_from_footer_php = $path_prefix_footer . 'assets/js/main.js';
// Esta ruta es relativa al directorio del script que INCLUYE el footer.
// Para ser más preciso, la ruta debería ser construida desde la raíz del proyecto RDM.
// Asumiendo que footer.php está en rdm/includes/
// y main.js está en rdm/assets/js/
// y el script que incluye footer.php está en rdm/clientes/
// El path desde rdm/clientes/ a rdm/assets/js/main.js es '../assets/js/main.js'
// El path desde rdm/includes/ a rdm/assets/js/main.js es '../assets/js/main.js' (si footer es llamado desde rdm/ o rdm/subdir)

// Corregir la lógica de path_prefix_footer y la comprobación de file_exists
// Asumimos que footer.php está en rdm/includes
// assets están en rdm/assets
// Páginas pueden estar en rdm/ o rdm/subdir/
$current_script_depth = substr_count(str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']), '/');
// DOCUMENT_ROOT para el sandbox es /app
// SCRIPT_FILENAME es /app/rdm/pagina.php (depth 2 desde /app) o /app/rdm/subdir/pagina.php (depth 3 desde /app)
// Si rdm es la raíz web, entonces SCRIPT_FILENAME sería /pagina.php o /subdir/pagina.php
// La lógica de $path_prefix y $nav_base_path en header.php es más adecuada.
// Reutilizar esa lógica o simplificar.

// Simplificación para assets en footer:
// Si la página actual está en un subdirectorio de rdm (clientes, equipos, etc.)
// la ruta a assets es ../assets. Si está en la raíz de rdm, es ./assets.
$assets_base_path = (in_array($directorio_actual_footer, ['clientes', 'tecnicos', 'equipos', 'facturas', 'admin', 'reportes']) && $directorio_actual_footer !== 'rdm') ? '../assets' : './assets';

$bootstrap_js_path_corrected = $assets_base_path . "/js/bootstrap.bundle.min.js";
$chart_js_path_corrected = $assets_base_path . "/js/chart.umd.min.js";
$custom_js_path_corrected = $assets_base_path . "/js/main.js";
$custom_js_server_check_path = $_SERVER['DOCUMENT_ROOT'] . str_replace('./', '/', $custom_js_path_corrected);
// Si DOCUMENT_ROOT es /app, y rdm es la raíz web, esta línea no es correcta.
// La manera más simple es que el path sea relativo a la página que incluye el footer.
// $path_prefix_footer de header.php es más fiable.
// $path_prefix_footer = ($directorio_actual_footer === 'rdm' || $directorio_actual_footer === 'app' || $directorio_actual_footer === '') ? './' : '../';
// Esta lógica es para cuando el archivo (header/footer) está en la misma carpeta que las páginas o un nivel arriba.
// Pero nuestros includes están en rdm/includes/ y las páginas están en rdm/ o rdm/subdir/
// Entonces desde rdm/clientes/listar.php: path_prefix_footer debería ser '../' para llegar a rdm/
// y luego assets/js/...
// La lógica del header: $path_prefix = '../'; if ($directorio_actual === 'rdm') { $path_prefix = './'; }
// $path_prefix es relativo al script actual.

// Re-uso de la lógica de $path_prefix del header.php (asumiendo que $directorio_actual es el mismo)
$js_path_prefix = (in_array($directorio_actual_footer, ['clientes', 'tecnicos', 'equipos', 'facturas', 'admin', 'reportes']) && $directorio_actual_footer !== 'rdm') ? '../' : './';


$final_bootstrap_js_path = $js_path_prefix . "assets/js/bootstrap.bundle.min.js";
$final_chart_js_path = $js_path_prefix . "assets/js/chart.umd.min.js";
$final_custom_js_path = $js_path_prefix . "assets/js/main.js";
// Para file_exists, necesitamos la ruta desde la raíz del servidor o una ruta relativa al script footer.php
// Si footer.php está en rdm/includes/
// Y main.js está en rdm/assets/js/
// La ruta relativa desde footer.php a main.js es ../assets/js/main.js
$main_js_check_path_from_footer = dirname(__FILE__) . '/../assets/js/main.js';


?>
<script src="<?php echo $final_bootstrap_js_path; ?>" defer></script>
<script src="<?php echo $final_chart_js_path; ?>" defer></script>

<?php if (file_exists($main_js_check_path_from_footer)): ?>
    <script src="<?php echo $final_custom_js_path; ?>" defer></script>
<?php endif; ?>
</body>
</html>
