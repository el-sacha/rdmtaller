<?php
// clientes/listar.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login();

$termino_busqueda = isset($_GET['q']) ? sanitizar_entrada($_GET['q']) : '';
$mensaje_accion = '';

// Para mensajes de eliminar.php u otras acciones
if (isset($_SESSION['mensaje_accion_cliente'])) {
    $mensaje_accion = "<p class='" . ($_SESSION['mensaje_accion_cliente_tipo'] ?? 'mensaje-info') . "'>" . htmlspecialchars($_SESSION['mensaje_accion_cliente']) . "</p>";
    unset($_SESSION['mensaje_accion_cliente']);
    unset($_SESSION['mensaje_accion_cliente_tipo']);
}


$sql = "SELECT id, nombre, dni, email, telefono, direccion FROM clientes";
$params = [];
$types = "";

if (!empty($termino_busqueda)) {
    $sql .= " WHERE nombre LIKE ? OR dni LIKE ?";
    $like_termino = "%" . $termino_busqueda . "%";
    $params[] = $like_termino;
    $params[] = $like_termino;
    $types .= "ss";
}
$sql .= " ORDER BY nombre ASC";

$stmt = mysqli_prepare($enlace, $sql);

if ($stmt && !empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

if ($stmt) {
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $clientes = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $clientes = [];
    $mensaje_accion = "<p class='mensaje-error'>Error al preparar la consulta: " . mysqli_error($enlace) . "</p>";
}

require_once '../includes/header.php';
?>

<div class="container-listado">
    <h2>Listado de Clientes</h2>

    <?php if (!empty($mensaje_accion)) echo $mensaje_accion; ?>

    <div class="acciones-listado">
        <a href="agregar.php" class="btn btn-primario">Agregar Nuevo Cliente</a>
        <form action="listar.php" method="GET" class="form-busqueda">
            <input type="text" name="q" placeholder="Buscar por Nombre o DNI..." value="<?php echo htmlspecialchars($termino_busqueda); ?>">
            <button type="submit" class="btn">Buscar</button>
            <?php if (!empty($termino_busqueda)): ?>
                <a href="listar.php" class="btn btn-secondary">Limpiar Búsqueda</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($clientes) && !empty($termino_busqueda)): ?>
        <p>No se encontraron clientes que coincidan con "<?php echo htmlspecialchars($termino_busqueda); ?>".</p>
    <?php elseif (empty($clientes)): ?>
        <p>No hay clientes registrados. <a href="agregar.php">Agrega el primero</a>.</p>
    <?php else: ?>
        <table class="tabla-listado">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>DNI/CIF</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Dirección</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $cliente): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($cliente['dni']); ?></td>
                        <td><?php echo htmlspecialchars($cliente['email'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($cliente['telefono'] ?? '-'); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($cliente['direccion'] ?? '-')); ?></td>
                        <td class="acciones-tabla">
                            <a href="editar.php?id=<?php echo $cliente['id']; ?>" class="btn btn-editar">Editar</a>
                            <a href="eliminar.php?id=<?php echo $cliente['id']; ?>" class="btn btn-eliminar" onclick="return confirm('¿Está seguro de que desea eliminar este cliente? ESTA ACCIÓN ES IRREVERSIBLE.');">Eliminar</a>
                            <!-- Se añade confirmación JS básica aquí, pero eliminar.php debe ser POST o tener token CSRF -->
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php
    // Aquí se podría añadir la lógica de paginación si se implementa.
    // Ejemplo:
    // if ($total_paginas > 1) {
    //     echo "<div class='paginacion'>";
    //     for ($i = 1; $i <= $total_paginas; $i++) {
    //         echo "<a href='?q=" . urlencode($termino_busqueda) . "&pagina=$i' class='" . ($pagina_actual == $i ? 'activo' : '') . "'>$i</a> ";
    //     }
    //     echo "</div>";
    // }
    ?>

</div>

<?php
require_once '../includes/footer.php';
// mysqli_close($enlace);
?>
