<?php
// reportes/recaudacion_por_fecha.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

$fecha_desde_rec = isset($_REQUEST['fecha_desde_rec']) ? sanitizar_entrada($_REQUEST['fecha_desde_rec']) : '';
$fecha_hasta_rec = isset($_REQUEST['fecha_hasta_rec']) ? sanitizar_entrada($_REQUEST['fecha_hasta_rec']) : '';
$export_type_rec = isset($_REQUEST['export_rec']) ? sanitizar_entrada($_REQUEST['export_rec']) : '';

// Solo admins pueden ver este reporte
verificar_login();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    $_SESSION['error_mensaje'] = "Acceso denegado. Solo administradores pueden ver este reporte.";
    header("Location: ../dashboard.php");
    return;
}

$summary = ['total_recaudado' => 0, 'total_iva' => 0, 'total_subtotal' => 0, 'cantidad_facturas' => 0];
$facturas_periodo = [];
$params_rec = [];
$types_rec = "";

$sql_rec = "SELECT SUM(total) as total_recaudado, SUM(iva_monto) as total_iva, SUM(subtotal) as total_subtotal, COUNT(id) as cantidad_facturas
            FROM facturas
            WHERE 1=1";

$sql_facturas_list = "SELECT id, numero_factura, fecha_emision, total, cliente_id FROM facturas WHERE 1=1";


if (!empty($fecha_desde_rec)) {
    $sql_rec .= " AND DATE(fecha_emision) >= ?";
    $sql_facturas_list .= " AND DATE(fecha_emision) >= ?";
    $params_rec[] = $fecha_desde_rec;
    $types_rec .= "s";
}
if (!empty($fecha_hasta_rec)) {
    $sql_rec .= " AND DATE(fecha_emision) <= ?";
    $sql_facturas_list .= " AND DATE(fecha_emision) <= ?";
    $params_rec[] = $fecha_hasta_rec;
    $types_rec .= "s";
}
$sql_facturas_list .= " ORDER BY fecha_emision DESC";


$execute_query_rec = false;
if (!empty($export_type_rec)){
    $execute_query_rec = true;
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && (!empty($fecha_desde_rec) || !empty($fecha_hasta_rec))) {
    $execute_query_rec = true;
}


if ($execute_query_rec) {
    // Consulta de resumen
    $stmt_summary = mysqli_prepare($enlace, $sql_rec);
    if ($stmt_summary) {
        if (!empty($params_rec)) {
            mysqli_stmt_bind_param($stmt_summary, $types_rec, ...$params_rec);
        }
        mysqli_stmt_execute($stmt_summary);
        $res_summary = mysqli_stmt_get_result($stmt_summary);
        $summary_data = mysqli_fetch_assoc($res_summary);
        if($summary_data && $summary_data['cantidad_facturas'] > 0) {
            $summary = $summary_data;
        }
        mysqli_stmt_close($stmt_summary);
    } else {
        $mensaje_error_consulta_rec = "Error al preparar resumen: " . mysqli_error($enlace);
    }

    // Consulta de listado de facturas (solo si hay facturas en el resumen)
    if ($summary['cantidad_facturas'] > 0) {
        $stmt_list = mysqli_prepare($enlace, $sql_facturas_list);
        if ($stmt_list) {
            if (!empty($params_rec)) { // Re-usar params_rec, ya que las condiciones de fecha son las mismas
                mysqli_stmt_bind_param($stmt_list, $types_rec, ...$params_rec);
            }
            mysqli_stmt_execute($stmt_list);
            $res_list = mysqli_stmt_get_result($stmt_list);
            while ($row = mysqli_fetch_assoc($res_list)) {
                // Para el listado, podríamos querer el nombre del cliente
                $stmt_cliente_fac = mysqli_prepare($enlace, "SELECT nombre FROM clientes WHERE id = ?");
                mysqli_stmt_bind_param($stmt_cliente_fac, "i", $row['cliente_id']);
                mysqli_stmt_execute($stmt_cliente_fac);
                $res_cli_fac = mysqli_stmt_get_result($stmt_cliente_fac);
                $cli_fac_data = mysqli_fetch_assoc($res_cli_fac);
                $row['cliente_nombre_factura'] = $cli_fac_data['nombre'] ?? 'N/A';
                mysqli_stmt_close($stmt_cliente_fac);
                $facturas_periodo[] = $row;
            }
            mysqli_stmt_close($stmt_list);
        } else {
             $mensaje_error_consulta_rec = (isset($mensaje_error_consulta_rec) ? $mensaje_error_consulta_rec . "; " : '') . "Error al preparar listado de facturas: " . mysqli_error($enlace);
        }
    }
}


// --- Lógica de Exportación ---
if ($execute_query_rec && !isset($mensaje_error_consulta_rec)) {
    if ($export_type_rec === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reporte_recaudacion_' . date('Ymd') . '.csv"');
        $output_rec = fopen('php://output', 'w');
        fputcsv($output_rec, array('Concepto', 'Monto'));
        fputcsv($output_rec, array('Total Recaudado', number_format($summary['total_recaudado'], 2, ',', '.')));
        fputcsv($output_rec, array('Total IVA', number_format($summary['total_iva'], 2, ',', '.')));
        fputcsv($output_rec, array('Total Subtotal (Neto)', number_format($summary['total_subtotal'], 2, ',', '.')));
        fputcsv($output_rec, array('Cantidad de Facturas', $summary['cantidad_facturas']));
        if (!empty($facturas_periodo)) {
            fputcsv($output_rec, array('---', '---')); // Separador
            fputcsv($output_rec, array('Nro Factura', 'Fecha', 'Cliente', 'Total Factura'));
            foreach ($facturas_periodo as $fact) {
                fputcsv($output_rec, [
                    $fact['numero_factura'], date("d/m/Y", strtotime($fact['fecha_emision'])),
                    $fact['cliente_nombre_factura'], number_format($fact['total'], 2, ',', '.')
                ]);
            }
        }
        fclose($output_rec);
        // exit; // Comentado para la herramienta
        return;
    }

    if ($export_type_rec === 'pdf') {
        require_once '../includes/fpdf/fpdf.php';
        class PDF_Reporte_Recaudacion extends FPDF {
            function Header() { $this->SetFont('Arial','B',12); $this->Cell(0,10,iconv('UTF-8', 'windows-1252','Reporte de Recaudación'),0,1,'C'); $this->Ln(5); }
            function Footer() { $this->SetY(-15); $this->SetFont('Arial','I',8); $this->Cell(0,10,iconv('UTF-8', 'windows-1252','Página ').$this->PageNo().'/{nb}',0,0,'C');}
        }
        $pdf_rec = new PDF_Reporte_Recaudacion('P','mm','A4');
        $pdf_rec->AliasNbPages(); $pdf_rec->AddPage(); $pdf_rec->SetFont('Arial','',10);
        $pdf_rec->SetFont('','B'); $pdf_rec->Cell(60,7,'Concepto',1); $pdf_rec->Cell(40,7,'Monto',1,1,'R'); $pdf_rec->SetFont('');
        $pdf_rec->Cell(60,7,iconv('UTF-8', 'windows-1252','Total Recaudado:'),'LR'); $pdf_rec->Cell(40,7,'$'.number_format($summary['total_recaudado'],2,',','.'),'R',1,'R');
        $pdf_rec->Cell(60,7,iconv('UTF-8', 'windows-1252','Total IVA:'),'LR'); $pdf_rec->Cell(40,7,'$'.number_format($summary['total_iva'],2,',','.'),'R',1,'R');
        $pdf_rec->Cell(60,7,iconv('UTF-8', 'windows-1252','Total Subtotal (Neto):'),'LRB'); $pdf_rec->Cell(40,7,'$'.number_format($summary['total_subtotal'],2,',','.'),'RB',1,'R');
        $pdf_rec->Cell(60,7,iconv('UTF-8', 'windows-1252','Cantidad de Facturas:'),'LRB'); $pdf_rec->Cell(40,7,$summary['cantidad_facturas'],'RB',1,'R');
        if(!empty($facturas_periodo)){
            $pdf_rec->Ln(10); $pdf_rec->SetFont('','B'); $pdf_rec->Cell(0,7,iconv('UTF-8', 'windows-1252','Detalle de Facturas del Período'),0,1);
            $w_fac = array(40,30,80,30); $pdf_rec->SetFont('','B',9);
            $pdf_rec->Cell($w_fac[0],7,'Nro Factura',1); $pdf_rec->Cell($w_fac[1],7,'Fecha',1); $pdf_rec->Cell($w_fac[2],7,'Cliente',1); $pdf_rec->Cell($w_fac[3],7,'Total',1,1,'R');
            $pdf_rec->SetFont('','',8); $fill_fac = false;
            foreach($facturas_periodo as $fact){
                $pdf_rec->SetFillColor(240,240,240);
                $pdf_rec->Cell($w_fac[0],6,iconv('UTF-8', 'windows-1252',$fact['numero_factura']),'LR',0,'L',$fill_fac);
                $pdf_rec->Cell($w_fac[1],6,date("d/m/Y", strtotime($fact['fecha_emision'])),'LR',0,'C',$fill_fac);
                $pdf_rec->Cell($w_fac[2],6,iconv('UTF-8', 'windows-1252',substr($fact['cliente_nombre_factura'],0,45)),'LR',0,'L',$fill_fac);
                $pdf_rec->Cell($w_fac[3],6,'$'.number_format($fact['total'],2,',','.'),'LR',1,'R',$fill_fac);
                $fill_fac = !$fill_fac;
            }
             $pdf_rec->Cell(array_sum($w_fac),0,'','T');
        }
        $pdf_rec->Output('D', 'reporte_recaudacion_' . date('Ymd') . '.pdf');
        // exit; // Comentado para la herramienta
        return;
    }
}


require_once '../includes/header.php';
?>
<div class="container mt-4">
    <h2>Reporte de Recaudación por Fecha</h2>
    <?php if (isset($_SESSION['error_mensaje'])) { echo "<div class='alert alert-danger'>".$_SESSION['error_mensaje']."</div>"; unset($_SESSION['error_mensaje']); } ?>

    <form action="recaudacion_por_fecha.php" method="GET" class="mb-4 p-3 border rounded bg-light">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="fecha_desde_rec" class="form-label">Fecha Desde:</label>
                <input type="date" name="fecha_desde_rec" id="fecha_desde_rec" class="form-control" value="<?php echo htmlspecialchars($fecha_desde_rec); ?>" required>
            </div>
            <div class="col-md-4">
                <label for="fecha_hasta_rec" class="form-label">Fecha Hasta:</label>
                <input type="date" name="fecha_hasta_rec" id="fecha_hasta_rec" class="form-control" value="<?php echo htmlspecialchars($fecha_hasta_rec); ?>" required>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">Generar Reporte</button>
            </div>
        </div>
        <div class="row g-3 mt-2">
            <div class="col-md-12 text-md-end">
                 <button type="submit" name="export_rec" value="csv" class="btn btn-success btn-sm">Exportar Resumen CSV</button>
                 <button type="submit" name="export_rec" value="pdf" class="btn btn-danger btn-sm">Exportar Resumen PDF</button>
            </div>
        </div>
    </form>

    <?php if (isset($mensaje_error_consulta_rec) && empty($export_type_rec)) echo "<div class='alert alert-danger'>".$mensaje_error_consulta_rec."</div>"; ?>

    <?php if ($execute_query_rec && empty($export_type_rec)): ?>
        <div class="row">
            <div class="col-md-4">
                <div class="card text-white bg-success mb-3">
                    <div class="card-header">Total Recaudado</div>
                    <div class="card-body"><h5 class="card-title">$<?php echo number_format($summary['total_recaudado'], 2, ',', '.'); ?></h5></div>
                </div>
            </div>
            <div class="col-md-4">
                 <div class="card text-dark bg-light mb-3">
                    <div class="card-header">Total IVA</div>
                    <div class="card-body"><h5 class="card-title">$<?php echo number_format($summary['total_iva'], 2, ',', '.'); ?></h5></div>
                </div>
            </div>
            <div class="col-md-4">
                 <div class="card text-dark bg-info mb-3">
                    <div class="card-header">Total Subtotal (Neto)</div>
                    <div class="card-body"><h5 class="card-title">$<?php echo number_format($summary['total_subtotal'], 2, ',', '.'); ?></h5></div>
                </div>
            </div>
             <div class="col-md-4">
                 <div class="card text-dark bg-warning mb-3">
                    <div class="card-header">Cantidad de Facturas</div>
                    <div class="card-body"><h5 class="card-title"><?php echo htmlspecialchars($summary['cantidad_facturas']); ?></h5></div>
                </div>
            </div>
        </div>

        <?php if (!empty($facturas_periodo)): ?>
            <h4 class="mt-4">Detalle de Facturas en el Período:</h4>
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover">
                    <thead class="table-light">
                        <tr><th>Nº Factura</th><th>Fecha</th><th>Cliente</th><th class="text-end">Total</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($facturas_periodo as $fact): ?>
                        <tr>
                            <td><a href="../facturas/ver_factura.php?id=<?php echo $fact['id']; ?>"><?php echo htmlspecialchars($fact['numero_factura']); ?></a></td>
                            <td><?php echo date("d/m/Y", strtotime($fact['fecha_emision'])); ?></td>
                            <td><?php echo htmlspecialchars($fact['cliente_nombre_factura']); ?></td>
                            <td class="text-end">$<?php echo number_format($fact['total'], 2, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($summary['cantidad_facturas'] > 0): ?>
             <div class="alert alert-info mt-3">No hay detalle de facturas para mostrar (posible error en carga de detalle).</div>
        <?php endif; ?>

    <?php elseif(empty($export_type_rec)): ?>
        <div class="alert alert-secondary">Seleccione un rango de fechas para ver la recaudación.</div>
    <?php endif; ?>
</div>

<?php
if (empty($export_type_rec)) {
    require_once '../includes/footer.php';
}
mysqli_close($enlace);
?>
