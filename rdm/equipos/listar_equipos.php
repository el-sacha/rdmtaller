<?php
// equipos/listar_equipos.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login();

$termino_busqueda = isset($_GET['q']) ? sanitizar_entrada($_GET['q']) : '';
$mensaje_accion = '';

// Mensajes de sesión para acciones CRUD o errores
if (isset($_SESSION['mensaje_accion_equipo'])) {
    $mensaje_accion = "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($_SESSION['mensaje_accion_equipo']) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    unset($_SESSION['mensaje_accion_equipo']);
}
if (isset($_SESSION['error_accion_equipo'])) {
     $mensaje_accion = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . htmlspecialchars($_SESSION['error_accion_equipo']) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    unset($_SESSION['error_accion_equipo']);
}
// Mensaje de error general del layout (ej. por acceso denegado)
if (isset($_SESSION['mensaje_error_layout'])) {
    $mensaje_accion .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . htmlspecialchars($_SESSION['mensaje_error_layout']) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    unset($_SESSION['mensaje_error_layout']);
}


$sql = "SELECT
            e.id AS equipo_id,
            c.nombre AS cliente_nombre,
            e.tipo_equipo, e.marca, e.modelo,
            t.nombre AS tecnico_nombre,
            e.fecha_ingreso,
            er.nombre_estado AS estado_reparacion,
            e.numero_serie_imei
        FROM equipos e
        JOIN clientes c ON e.cliente_id = c.id
        LEFT JOIN tecnicos t ON e.tecnico_asignado_id = t.id
        LEFT JOIN estados_reparacion er ON e.estado_reparacion_id = er.id";

$params = [];
$types = "";

if (!empty($termino_busqueda)) {
    $sql .= " WHERE (e.id = ? OR c.nombre LIKE ? OR e.numero_serie_imei LIKE ? OR e.marca LIKE ? OR e.modelo LIKE ?)";
    $like_termino = "%" . $termino_busqueda . "%";
    if (is_numeric($termino_busqueda)) {
        $params[] = (int)$termino_busqueda; $types .= "i";
    } else {
        $params[] = -1; $types .= "i"; // ID imposible
    }
    $params[] = $like_termino; $params[] = $like_termino; $params[] = $like_termino; $params[] = $like_termino;
    $types .= "ssss";
}
$sql .= " ORDER BY e.fecha_ingreso DESC";

$stmt = mysqli_prepare($enlace, $sql);

if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $equipos = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $equipos = [];
    // Guardar error para mostrarlo después de incluir el header
    $_SESSION['error_accion_equipo'] = "Error al preparar la consulta de equipos: " . mysqli_error($enlace);
}

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Listado General de Equipos</h2>

    <?php if (!empty($mensaje_accion)) echo $mensaje_accion; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="ingresar.php" class="btn btn-primary">Registrar Nuevo Ingreso</a>
        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSearch" aria-expanded="false" aria-controls="collapseSearch">
            <i class="bi bi-search"></i> Mostrar/Ocultar Búsqueda
        </button>
    </div>

    <div class="collapse <?php if(!empty($termino_busqueda)) echo 'show'; ?>" id="collapseSearch">
        <form action="listar_equipos.php" method="GET" class="mb-4 p-3 border rounded bg-light">
            <div class="input-group">
                <input type="text" name="q" class="form-control" placeholder="Buscar por Nº Orden, Cliente, Serie/IMEI, Marca, Modelo..." value="<?php echo htmlspecialchars($termino_busqueda); ?>">
                <button type="submit" class="btn btn-info">Buscar</button>
                <?php if (!empty($termino_busqueda)): ?>
                    <a href="listar_equipos.php" class="btn btn-danger">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>


    <?php if (empty($equipos) && !empty($termino_busqueda)): ?>
        <div class="alert alert-warning">No se encontraron equipos que coincidan con "<?php echo htmlspecialchars($termino_busqueda); ?>".</div>
    <?php elseif (empty($equipos)): ?>
        <div class="alert alert-info">No hay equipos registrados. <a href="ingresar.php">Registre el primero</a>.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Equipo</th>
                        <th>Serie/IMEI</th>
                        <th>Técnico</th>
                        <th>F. Ingreso</th>
                        <th>Estado</th>
                        <th style="width: 200px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equipos as $equipo):
                        $estado_reparacion_trim = trim($equipo['estado_reparacion'] ?? '');
                        $puede_facturar = in_array($estado_reparacion_trim, ['Reparado', 'Listo para entregar']);
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($equipo['equipo_id']); ?></td>
                            <td><?php echo htmlspecialchars($equipo['cliente_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($equipo['tipo_equipo'] . " " . $equipo['marca'] . " " . $equipo['modelo']); ?></td>
                            <td><?php echo htmlspecialchars($equipo['numero_serie_imei'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($equipo['tecnico_nombre'] ?? 'No asignado'); ?></td>
                            <td><?php echo date("d/m/y H:i", strtotime($equipo['fecha_ingreso'])); ?></td>
                            <td>
                                <?php
                                $badge_class = 'bg-secondary'; // Default
                                if ($estado_reparacion_trim === 'Ingresado') $badge_class = 'bg-primary';
                                elseif ($estado_reparacion_trim === 'En revisión') $badge_class = 'bg-warning text-dark';
                                elseif ($estado_reparacion_trim === 'Reparado') $badge_class = 'bg-success';
                                elseif ($estado_reparacion_trim === 'Listo para entregar') $badge_class = 'bg-info text-dark';
                                elseif ($estado_reparacion_trim === 'Entregado') $badge_class = 'bg-dark';
                                elseif ($estado_reparacion_trim === 'No reparado' || $estado_reparacion_trim === 'Presupuesto rechazado') $badge_class = 'bg-danger';
                                ?>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($estado_reparacion_trim ?: 'N/A'); ?></span>
                            </td>
                            <td>
                                <div class="btn-group" role="group" aria-label="Acciones Equipo">
                                    <a href="editar_estado.php?id=<?php echo $equipo['equipo_id']; ?>" class="btn btn-sm btn-warning" title="Gestionar Estado/Notas"><i class="bi bi-pencil-square"></i> Gestionar</a>
                                    <a href="imprimir_ficha.php?id=<?php echo $equipo['equipo_id']; ?>" class="btn btn-sm btn-info" target="_blank" title="Ver Ficha"><i class="bi bi-eye-fill"></i> Ficha</a>
                                    <?php if ($puede_facturar): ?>
                                        <a href="../facturas/generar_factura.php?equipo_id=<?php echo $equipo['equipo_id']; ?>" class="btn btn-sm btn-success" title="Generar Factura"><i class="bi bi-receipt"></i> Facturar</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<?php
// Bootstrap Icons (si se usan como <i class="bi bi-..."></i>, necesitarían el CSS de Bootstrap Icons)
// Podríamos añadir el link al CDN en header.php o descargar y servir localmente.
// <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
// Por ahora, los botones solo tendrán texto.
require_once '../includes/footer.php';
?>
