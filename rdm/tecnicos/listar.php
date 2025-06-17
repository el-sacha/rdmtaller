<?php
// tecnicos/listar.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login();

$termino_busqueda = isset($_GET['q']) ? sanitizar_entrada($_GET['q']) : '';
$mensaje_accion = '';

if (isset($_SESSION['mensaje_accion_tecnico'])) {
    $mensaje_accion = "<p class='" . ($_SESSION['mensaje_accion_tecnico_tipo'] ?? 'mensaje-info') . "'>" . htmlspecialchars($_SESSION['mensaje_accion_tecnico']) . "</p>";
    unset($_SESSION['mensaje_accion_tecnico']);
    unset($_SESSION['mensaje_accion_tecnico_tipo']);
}

$sql = "SELECT id, nombre, especialidad FROM tecnicos";
$params = [];
$types = "";

if (!empty($termino_busqueda)) {
    $sql .= " WHERE nombre LIKE ? OR especialidad LIKE ?";
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
    $tecnicos = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $tecnicos = [];
    $mensaje_accion = "<p class='mensaje-error'>Error al preparar la consulta: " . mysqli_error($enlace) . "</p>";
}

require_once '../includes/header.php';
?>

<div class="container-listado">
    <h2>Listado de Técnicos</h2>

    <?php if (!empty($mensaje_accion)) echo $mensaje_accion; ?>

    <div class="acciones-listado">
        <a href="agregar.php" class="btn btn-primario">Agregar Nuevo Técnico</a>
        <form action="listar.php" method="GET" class="form-busqueda">
            <input type="text" name="q" placeholder="Buscar por Nombre o Especialidad..." value="<?php echo htmlspecialchars($termino_busqueda); ?>">
            <button type="submit" class="btn">Buscar</button>
            <?php if (!empty($termino_busqueda)): ?>
                <a href="listar.php" class="btn btn-secondary">Limpiar Búsqueda</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($tecnicos) && !empty($termino_busqueda)): ?>
        <p>No se encontraron técnicos que coincidan con "<?php echo htmlspecialchars($termino_busqueda); ?>".</p>
    <?php elseif (empty($tecnicos)): ?>
        <p>No hay técnicos registrados. <a href="agregar.php">Agrega el primero</a>.</p>
    <?php else: ?>
        <table class="tabla-listado">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Especialidad</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tecnicos as $tecnico): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($tecnico['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($tecnico['especialidad']); ?></td>
                        <td class="acciones-tabla">
                            <a href="editar.php?id=<?php echo $tecnico['id']; ?>" class="btn btn-editar">Editar</a>
                            <a href="eliminar.php?id=<?php echo $tecnico['id']; ?>" class="btn btn-eliminar" onclick="return confirm('¿Está seguro de que desea eliminar este técnico? Si el técnico está asignado a usuarios, su asignación se anulará.');">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
require_once '../includes/footer.php';
?>
