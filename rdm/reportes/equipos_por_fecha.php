<?php
// reportes/equipos_por_fecha.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

// verificar_login(); // Se llamará más tarde si no es exportación o en puntos de entrada de exportación

$fecha_desde = isset($_REQUEST['fecha_desde']) ? sanitizar_entrada($_REQUEST['fecha_desde']) : '';
$fecha_hasta = isset($_REQUEST['fecha_hasta']) ? sanitizar_entrada($_REQUEST['fecha_hasta']) : '';
$tecnico_id_filtro = isset($_REQUEST['tecnico_id_filtro']) ? (int)sanitizar_entrada($_REQUEST['tecnico_id_filtro']) : 0;
$export_type = isset($_REQUEST['export']) ? sanitizar_entrada($_REQUEST['export']) : '';

// Lógica de consulta (se usa tanto para HTML como para exportación)
$equipos_reporte = [];
$params = [];
$types = "";
$sql = "SELECT
            e.id AS equipo_id,
            c.nombre AS cliente_nombre,
            e.tipo_equipo, e.marca, e.modelo,
            t.nombre AS tecnico_nombre,
            e.fecha_ingreso,
            er.nombre_estado AS estado_reparacion
        FROM equipos e
        JOIN clientes c ON e.cliente_id = c.id
        LEFT JOIN tecnicos t ON e.tecnico_asignado_id = t.id
        LEFT JOIN estados_reparacion er ON e.estado_reparacion_id = er.id
        WHERE 1=1";

if (!empty($fecha_desde)) {
    $sql .= " AND DATE(e.fecha_ingreso) >= ?";
    $params[] = $fecha_desde;
    $types .= "s";
}
if (!empty($fecha_hasta)) {
    $sql .= " AND DATE(e.fecha_ingreso) <= ?";
    $params[] = $fecha_hasta;
    $types .= "s";
}
if ($tecnico_id_filtro > 0) {
    $sql .= " AND e.tecnico_asignado_id = ?";
    $params[] = $tecnico_id_filtro;
    $types .= "i";
}
$sql .= " ORDER BY e.fecha_ingreso DESC";

// Solo ejecutar si hay algún filtro o es una exportación con filtros válidos
$execute_query = false;
if (!empty($export_type)) { // Para exportar, los filtros podrían estar vacíos si se quiere exportar todo (aunque el form actual no lo facilita)
    $execute_query = true;
} elseif (!empty($fecha_desde) || !empty($fecha_hasta) || $tecnico_id_filtro > 0) { // Para vista HTML, requiere al menos un filtro
    $execute_query = true;
}


if ($execute_query) {
    $stmt = mysqli_prepare($enlace, $sql);
    if ($stmt) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $resultado = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($resultado)) {
            $equipos_reporte[] = $row;
        }
        mysqli_stmt_close($stmt);
    } else {
        $mensaje_error_consulta = "Error al preparar la consulta: " . mysqli_error($enlace);
    }
}

// --- Lógica de Exportación ---
if ($export_type === 'csv' && !isset($mensaje_error_consulta)) {
    verificar_login();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_equipos_' . date('Ymd') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, array('ID Equipo', 'Cliente', 'Tipo Equipo', 'Marca', 'Modelo', 'Tecnico Asignado', 'Fecha Ingreso', 'Estado Actual'));
    foreach ($equipos_reporte as $equipo) {
        fputcsv($output, [
            $equipo['equipo_id'], $equipo['cliente_nombre'], $equipo['tipo_equipo'], $equipo['marca'],
            $equipo['modelo'], $equipo['tecnico_nombre'] ?? 'No asignado',
            date("d/m/Y H:i", strtotime($equipo['fecha_ingreso'])), $equipo['estado_reparacion'] ?? 'N/A'
        ]);
    }
    fclose($output);
    // exit; // Comentado para la herramienta
    return;
}

if ($export_type === 'pdf' && !isset($mensaje_error_consulta)) {
    verificar_login();
    error_reporting(0); // Suppress PHP errors from breaking PDF output
    @ini_set('display_errors', 0); // Suppress PHP errors from breaking PDF output
    require_once '../includes/fpdf/fpdf.php';

    class PDF_Reporte_Equipos extends FPDF {
        function Header() {
            $this->SetFont('Arial','B',12); $this->Cell(0,10,iconv('UTF-8', 'windows-1252//TRANSLIT','Reporte de Equipos'),0,1,'C'); $this->Ln(5);
        }
        function Footer() {
            $this->SetY(-15); $this->SetFont('Arial','I',8); $this->Cell(0,10,iconv('UTF-8', 'windows-1252//TRANSLIT','Página ').$this->PageNo().'/{nb}',0,0,'C');
        }
        function TableEquipos($header, $data) {
            $this->SetFillColor(200,220,255); $this->SetTextColor(0); $this->SetDrawColor(128,0,0);
            $this->SetLineWidth(.3); $this->SetFont('','B', 8);
            $w = array(15, 45, 30, 25, 25, 35, 25, 25); // Ajustar anchos para Landscape A4 (total ~190-277)
            for($i=0;$i<count($header);$i++) $this->Cell($w[$i],7,iconv('UTF-8', 'windows-1252//TRANSLIT',$header[$i]),1,0,'C',true);
            $this->Ln();
            $this->SetFont('','',7); $fill = false;
            foreach($data as $row) {
                $this->Cell($w[0],6,$row['equipo_id'],'LR',0,'C',$fill); // ID is numeric, no iconv needed
                $this->Cell($w[1],6,iconv('UTF-8', 'windows-1252//TRANSLIT',substr($row['cliente_nombre'],0,28)),'LR',0,'L',$fill);
                $this->Cell($w[2],6,iconv('UTF-8', 'windows-1252//TRANSLIT',substr($row['tipo_equipo'],0,18)),'LR',0,'L',$fill);
                $this->Cell($w[3],6,iconv('UTF-8', 'windows-1252//TRANSLIT',substr($row['marca'],0,15)),'LR',0,'L',$fill);
                $this->Cell($w[4],6,iconv('UTF-8', 'windows-1252//TRANSLIT',substr($row['modelo'],0,15)),'LR',0,'L',$fill);
                $this->Cell($w[5],6,iconv('UTF-8', 'windows-1252//TRANSLIT',substr($row['tecnico_nombre'] ?? 'N/A',0,20)),'LR',0,'L',$fill);
                $this->Cell($w[6],6,date("d/m/y H:i", strtotime($row['fecha_ingreso'])),'LR',0,'C',$fill); // Date is ASCII
                $this->Cell($w[7],6,iconv('UTF-8', 'windows-1252//TRANSLIT',$row['estado_reparacion'] ?? 'N/A'),'LR',0,'L',$fill);
                $this->Ln(); $fill = !$fill;
            }
            $this->Cell(array_sum($w),0,'','T');
        }
    }

    $pdf = new PDF_Reporte_Equipos('L','mm','A4');
    $pdf->AliasNbPages(); $pdf->AddPage();
    $header_pdf = array('ID', 'Cliente', 'Tipo', 'Marca', 'Modelo', 'Tecnico', 'F. Ingreso', 'Estado');
    $pdf->TableEquipos($header_pdf, $equipos_reporte);
    $pdf->Output('D', 'reporte_equipos_' . date('Ymd') . '.pdf');
    exit; // Ensure clean exit after PDF output
}


// --- Lógica para la vista HTML ---
verificar_login();

$tecnicos_dropdown = []; // Recargar para la vista HTML si no se hizo para export
$stmt_tecnicos_all = mysqli_prepare($enlace, "SELECT id, nombre FROM tecnicos ORDER BY nombre ASC");
if($stmt_tecnicos_all){
    mysqli_stmt_execute($stmt_tecnicos_all);
    $res_tecnicos_all = mysqli_stmt_get_result($stmt_tecnicos_all);
    while($row = mysqli_fetch_assoc($res_tecnicos_all)){ $tecnicos_dropdown[] = $row; }
    mysqli_stmt_close($stmt_tecnicos_all);
}

require_once '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Reporte de Equipos por Fecha y Técnico</h2>

    <form action="equipos_por_fecha.php" method="GET" class="mb-4 p-3 border rounded bg-light">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="fecha_desde" class="form-label">Fecha Desde:</label>
                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" value="<?php echo htmlspecialchars($fecha_desde); ?>">
            </div>
            <div class="col-md-3">
                <label for="fecha_hasta" class="form-label">Fecha Hasta:</label>
                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
            </div>
            <div class="col-md-3">
                <label for="tecnico_id_filtro" class="form-label">Técnico (Opcional):</label>
                <select name="tecnico_id_filtro" id="tecnico_id_filtro" class="form-select">
                    <option value="0">-- Todos --</option>
                    <?php foreach ($tecnicos_dropdown as $tec): ?>
                        <option value="<?php echo $tec['id']; ?>" <?php echo ($tecnico_id_filtro == $tec['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tec['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">Generar Reporte</button>
            </div>
        </div>
         <div class="row g-3 mt-2">
            <div class="col-md-12 text-md-end">
                 <button type="submit" name="export" value="csv" class="btn btn-success btn-sm">Exportar a CSV</button>
                 <button type="submit" name="export" value="pdf" class="btn btn-danger btn-sm">Exportar a PDF</button>
            </div>
        </div>
    </form>

    <?php if (isset($mensaje_error_consulta)) echo "<div class='alert alert-danger'>".$mensaje_error_consulta."</div>"; ?>

    <?php if ($execute_query && empty($export_type)): // Solo mostrar tabla si se ejecutó la consulta y no es exportación ?>
        <?php if (!empty($equipos_reporte)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>ID Equipo</th><th>Cliente</th><th>Tipo Equipo</th><th>Marca</th><th>Modelo</th>
                            <th>Técnico Asignado</th><th>Fecha Ingreso</th><th>Estado Actual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($equipos_reporte as $equipo): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($equipo['equipo_id']); ?></td>
                                <td><?php echo htmlspecialchars($equipo['cliente_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($equipo['tipo_equipo']); ?></td>
                                <td><?php echo htmlspecialchars($equipo['marca']); ?></td>
                                <td><?php echo htmlspecialchars($equipo['modelo']); ?></td>
                                <td><?php echo htmlspecialchars($equipo['tecnico_nombre'] ?? 'No asignado'); ?></td>
                                <td><?php echo date("d/m/Y H:i", strtotime($equipo['fecha_ingreso'])); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($equipo['estado_reparacion'] ?? 'N/A'); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No se encontraron equipos para los filtros seleccionados.</div>
        <?php endif; ?>
    <?php elseif(empty($export_type)): // No es exportación y no se presionó "Generar Reporte" (o no hay filtros) ?>
        <div class="alert alert-secondary">Seleccione filtros para generar el reporte.</div>
    <?php endif; ?>
</div>

<?php
if (empty($export_type)) { // Solo incluir footer si no es exportación
    require_once '../includes/footer.php';
}
mysqli_close($enlace); // Cerrar conexión al final del script
?>
