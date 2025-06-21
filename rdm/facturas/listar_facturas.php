<?php
// facturas/listar_facturas.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login();

// --- Lógica de autorización ---
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    $_SESSION['mensaje_error_layout'] = 'Acceso denegado. Solo los administradores pueden ver el listado de facturas.';
    // Asumiendo que listar_facturas.php está en rdm/facturas/, dashboard.php está en rdm/
    header('Location: ../dashboard.php');
    return; // Terminar ejecución
}
// --- Fin lógica de autorización ---

$termino_busqueda = isset($_GET['q']) ? sanitizar_entrada($_GET['q']) : '';
$mensaje_accion = ''; // Para mensajes de otras acciones si se implementan

// Manejar mensajes de sesión (ej. después de crear una factura)
if (isset($_SESSION['mensaje_accion_factura'])) {
    $mensaje_accion = "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($_SESSION['mensaje_accion_factura']) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    unset($_SESSION['mensaje_accion_factura']);
}
if (isset($_SESSION['error_accion_factura'])) {
     $mensaje_accion = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . htmlspecialchars($_SESSION['error_accion_factura']) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    unset($_SESSION['error_accion_factura']);
}

$sql = "SELECT f.id, f.numero_factura, f.fecha_emision, f.total,
               c.nombre as cliente_nombre, c.dni as cliente_dni
        FROM facturas f
        JOIN clientes c ON f.cliente_id = c.id";

$params = [];
$types = "";

if (!empty($termino_busqueda)) {
    $sql .= " WHERE (f.numero_factura LIKE ? OR c.nombre LIKE ? OR c.dni LIKE ?)";
    $like_termino = "%" . $termino_busqueda . "%";
    $params[] = $like_termino; // numero_factura
    $params[] = $like_termino; // cliente_nombre
    $params[] = $like_termino; // cliente_dni
    $types .= "sss";
}
$sql .= " ORDER BY f.fecha_emision DESC, f.id DESC";

$stmt = mysqli_prepare($enlace, $sql);

if ($stmt && !empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

if ($stmt) {
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $facturas = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $facturas = [];
    // Considerar registrar este error en un log en lugar de (o además de) mostrarlo al usuario
    $mensaje_accion .= "<div class='alert alert-danger'>Error al preparar la consulta de facturas: " . mysqli_error($enlace) . "</div>";
}

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Listado de Facturas Emitidas</h2>

    <?php if (!empty($mensaje_accion)) echo $mensaje_accion; ?>

    <div class="mb-3">
        <a href="generar_factura.php" class="btn btn-primary">Crear Nueva Factura Manual</a>
        <!-- Podríamos tener un link a generar factura desde equipo también -->
    </div>

    <form action="listar_facturas.php" method="GET" class="mb-4">
        <div class="input-group">
            <input type="text" name="q" class="form-control" placeholder="Buscar por Nº Factura, Cliente o DNI..." value="<?php echo htmlspecialchars($termino_busqueda); ?>">
            <button type="submit" class="btn btn-outline-secondary">Buscar</button>
            <?php if (!empty($termino_busqueda)): ?>
                <a href="listar_facturas.php" class="btn btn-outline-danger">Limpiar</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($facturas) && !empty($termino_busqueda)): ?>
        <div class="alert alert-warning">No se encontraron facturas que coincidan con "<?php echo htmlspecialchars($termino_busqueda); ?>".</div>
    <?php elseif (empty($facturas)): ?>
        <div class="alert alert-info">No hay facturas emitidas. <a href="generar_factura.php">Cree la primera</a>.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Nº Factura</th>
                        <th>Cliente</th>
                        <th>DNI Cliente</th>
                        <th>Fecha Emisión</th>
                        <th class="text-end">Total</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($facturas as $factura): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($factura['numero_factura']); ?></td>
                            <td><?php echo htmlspecialchars($factura['cliente_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($factura['cliente_dni']); ?></td>
                            <td><?php echo date("d/m/Y H:i", strtotime($factura['fecha_emision'])); ?></td>
                            <td class="text-end">$<?php echo number_format($factura['total'], 2, ',', '.'); ?></td>
                            <td>
                                <a href="ver_factura.php?id=<?php echo $factura['id']; ?>" class="btn btn-sm btn-info">Ver</a>
                                <a href="descargar_pdf.php?id=<?php echo $factura['id']; ?>" class="btn btn-sm btn-success" target="_blank">PDF</a>
                                <!-- Podríamos añadir editar/anular si la lógica de negocio lo permite -->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php // PAGINACIÓN (Placeholder) ?>

</div>

<?php
require_once '../includes/footer.php';
?>
