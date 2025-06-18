<?php
// rdm/global_search_results.php
require_once 'includes/funciones.php';
require_once 'includes/db.php';

verificar_login();

$termino = isset($_GET['q_global']) ? sanitizar_entrada(trim($_GET['q_global'])) : '';

$resultados_clientes = [];
$resultados_equipos = [];
$resultados_tecnicos = [];
$resultados_facturas = [];
$total_resultados = 0;

if (!empty($termino)) {
    $like_termino = "%" . $termino . "%";

    // 1. Search Clients
    $sql_clientes = "SELECT id, nombre, dni, email FROM clientes WHERE nombre LIKE ? OR dni LIKE ? OR email LIKE ?";
    $stmt_clientes = mysqli_prepare($enlace, $sql_clientes);
    if ($stmt_clientes) {
        mysqli_stmt_bind_param($stmt_clientes, "sss", $like_termino, $like_termino, $like_termino);
        mysqli_stmt_execute($stmt_clientes);
        $res_clientes = mysqli_stmt_get_result($stmt_clientes);
        while ($row = mysqli_fetch_assoc($res_clientes)) {
            $resultados_clientes[] = $row;
        }
        mysqli_stmt_close($stmt_clientes);
        $total_resultados += count($resultados_clientes);
    }

    // 2. Search Equipment
    // Handle numeric search for ID separately from LIKE for strings
    $sql_equipos = "SELECT e.id, e.tipo_equipo, e.marca, e.modelo, e.numero_serie_imei, c.nombre as cliente_nombre
                    FROM equipos e JOIN clientes c ON e.cliente_id = c.id
                    WHERE e.numero_serie_imei LIKE ? OR e.marca LIKE ? OR e.modelo LIKE ? OR e.tipo_equipo LIKE ?";
    $params_equipos = [$like_termino, $like_termino, $like_termino, $like_termino];
    $types_equipos = "ssss";

    if (is_numeric($termino)) {
        $sql_equipos_id = "SELECT e.id, e.tipo_equipo, e.marca, e.modelo, e.numero_serie_imei, c.nombre as cliente_nombre
                           FROM equipos e JOIN clientes c ON e.cliente_id = c.id
                           WHERE e.id = ?";
        $stmt_equipos_id = mysqli_prepare($enlace, $sql_equipos_id);
        if($stmt_equipos_id) {
            mysqli_stmt_bind_param($stmt_equipos_id, "i", $termino_int);
            $termino_int = (int)$termino;
            mysqli_stmt_execute($stmt_equipos_id);
            $res_equipos_id = mysqli_stmt_get_result($stmt_equipos_id);
            while($row = mysqli_fetch_assoc($res_equipos_id)){ // Podría ser solo una fila
                // Evitar duplicados si la búsqueda por texto también lo encuentra
                $found = false;
                foreach($resultados_equipos as $re) { if($re['id'] == $row['id']) $found=true; }
                if(!$found) $resultados_equipos[] = $row;
            }
            mysqli_stmt_close($stmt_equipos_id);
        }
    }

    $stmt_equipos_text = mysqli_prepare($enlace, $sql_equipos);
    if ($stmt_equipos_text) {
        mysqli_stmt_bind_param($stmt_equipos_text, $types_equipos, ...$params_equipos);
        mysqli_stmt_execute($stmt_equipos_text);
        $res_equipos_text = mysqli_stmt_get_result($stmt_equipos_text);
        while ($row = mysqli_fetch_assoc($res_equipos_text)) {
             $found = false;
             foreach($resultados_equipos as $re) { if($re['id'] == $row['id']) $found=true; }
             if(!$found) $resultados_equipos[] = $row;
        }
        mysqli_stmt_close($stmt_equipos_text);
        $total_resultados += count($resultados_equipos); // Contar solo una vez después de unificar
    }


    // 3. Search Technicians
    $sql_tecnicos = "SELECT id, nombre, especialidad FROM tecnicos WHERE nombre LIKE ? OR especialidad LIKE ?";
    $stmt_tecnicos = mysqli_prepare($enlace, $sql_tecnicos);
    if ($stmt_tecnicos) {
        mysqli_stmt_bind_param($stmt_tecnicos, "ss", $like_termino, $like_termino);
        mysqli_stmt_execute($stmt_tecnicos);
        $res_tecnicos = mysqli_stmt_get_result($stmt_tecnicos);
        while ($row = mysqli_fetch_assoc($res_tecnicos)) {
            $resultados_tecnicos[] = $row;
        }
        mysqli_stmt_close($stmt_tecnicos);
        $total_resultados += count($resultados_tecnicos);
    }

    // 4. Search Invoices
    $sql_facturas = "SELECT f.id, f.numero_factura, c.nombre as cliente_nombre, f.fecha_emision, f.total
                     FROM facturas f JOIN clientes c ON f.cliente_id = c.id
                     WHERE f.numero_factura LIKE ? OR c.nombre LIKE ?";
    $stmt_facturas = mysqli_prepare($enlace, $sql_facturas);
     if ($stmt_facturas) {
        mysqli_stmt_bind_param($stmt_facturas, "ss", $like_termino, $like_termino);
        mysqli_stmt_execute($stmt_facturas);
        $res_facturas = mysqli_stmt_get_result($stmt_facturas);
        while ($row = mysqli_fetch_assoc($res_facturas)) {
            $resultados_facturas[] = $row;
        }
        mysqli_stmt_close($stmt_facturas);
        $total_resultados += count($resultados_facturas);
    }
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <h2>Resultados de la Búsqueda Global</h2>

    <?php if (empty($termino)): ?>
        <div class="alert alert-info">Por favor ingrese un término de búsqueda en la barra superior.</div>
    <?php else: ?>
        <p class="lead">Resultados para: <strong>"<?php echo htmlspecialchars($termino); ?>"</strong> (Total: <?php echo $total_resultados; ?> coincidencias)</p>
        <hr>

        <?php if (!empty($resultados_clientes)): ?>
            <div class="mb-4">
                <h4>Clientes Encontrados (<?php echo count($resultados_clientes); ?>)</h4>
                <ul class="list-group">
                    <?php foreach ($resultados_clientes as $cliente): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo htmlspecialchars($cliente['nombre']); ?></strong><br>
                                <small class="text-muted">DNI: <?php echo htmlspecialchars($cliente['dni']); ?> | Email: <?php echo htmlspecialchars($cliente['email']); ?></small>
                            </div>
                            <a href="clientes/editar.php?id=<?php echo $cliente['id']; ?>" class="btn btn-sm btn-outline-primary">Ver/Editar</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($resultados_equipos)): ?>
            <div class="mb-4">
                <h4>Equipos Encontrados (<?php echo count($resultados_equipos); ?>)</h4>
                <ul class="list-group">
                    <?php foreach ($resultados_equipos as $equipo): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong>ID: <?php echo htmlspecialchars($equipo['id']); ?> - <?php echo htmlspecialchars($equipo['tipo_equipo'] . " " . $equipo['marca'] . " " . $equipo['modelo']); ?></strong><br>
                                <small class="text-muted">Serie/IMEI: <?php echo htmlspecialchars($equipo['numero_serie_imei'] ?? 'N/A'); ?> | Cliente: <?php echo htmlspecialchars($equipo['cliente_nombre']); ?></small>
                            </div>
                            <a href="equipos/editar_estado.php?id=<?php echo $equipo['id']; ?>" class="btn btn-sm btn-outline-primary">Gestionar</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($resultados_tecnicos)): ?>
            <div class="mb-4">
                <h4>Técnicos Encontrados (<?php echo count($resultados_tecnicos); ?>)</h4>
                <ul class="list-group">
                    <?php foreach ($resultados_tecnicos as $tecnico): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                             <div>
                                <strong><?php echo htmlspecialchars($tecnico['nombre']); ?></strong><br>
                                <small class="text-muted">Especialidad: <?php echo htmlspecialchars($tecnico['especialidad']); ?></small>
                            </div>
                            <a href="tecnicos/editar.php?id=<?php echo $tecnico['id']; ?>" class="btn btn-sm btn-outline-primary">Ver/Editar</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($resultados_facturas)): ?>
            <div class="mb-4">
                <h4>Facturas Encontradas (<?php echo count($resultados_facturas); ?>)</h4>
                <ul class="list-group">
                    <?php foreach ($resultados_facturas as $factura): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                             <div>
                                <strong>Nº: <?php echo htmlspecialchars($factura['numero_factura']); ?></strong> | Total: $<?php echo number_format($factura['total'],2,',','.'); ?><br>
                                <small class="text-muted">Cliente: <?php echo htmlspecialchars($factura['cliente_nombre']); ?> | Fecha: <?php echo date("d/m/Y", strtotime($factura['fecha_emision'])); ?></small>
                            </div>
                            <a href="facturas/ver_factura.php?id=<?php echo $factura['id']; ?>" class="btn btn-sm btn-outline-primary">Ver Factura</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($total_resultados === 0): ?>
            <div class="alert alert-warning">No se encontraron resultados para "<?php echo htmlspecialchars($termino); ?>".</div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
mysqli_close($enlace);
?>
