<?php
// facturas/descargar_pdf.php
require_once '../includes/db.php';       // Para $enlace
require_once '../includes/funciones.php'; // Para sanitizar_entrada y verificar_login (si se decide proteger)
// verificar_login(); // Descomentar si se quiere proteger la descarga directa del PDF

// Incluir FPDF
require_once '../includes/fpdf/fpdf.php';

$factura_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($factura_id <= 0) {
    // Podríamos generar un PDF de error o redirigir
    die("ID de factura no válido."); // die() es problemático para la herramienta también, pero es un caso de error crítico.
}

// --- Fetch Factura Data (similar to ver_factura.php) ---
$sql_factura = "SELECT f.*,
                       c.nombre as cliente_nombre, c.dni as cliente_dni, c.direccion as cliente_direccion, c.email as cliente_email, c.telefono as cliente_telefono,
                       df.nombre_empresa, df.cuit, df.domicilio_comercial as empresa_domicilio, df.condicion_iva as empresa_condicion_iva, df.punto_venta as empresa_punto_venta, df.ingresos_brutos as empresa_iibb, df.fecha_inicio_actividades as empresa_inicio_act, df.logo_url as empresa_logo,
                       eq.tipo_equipo, eq.marca as equipo_marca, eq.modelo as equipo_modelo, eq.numero_serie_imei as equipo_serie
                FROM facturas f
                JOIN clientes c ON f.cliente_id = c.id
                JOIN datos_fiscales_empresa df ON f.datos_fiscales_empresa_id = df.id
                LEFT JOIN equipos eq ON f.equipo_id = eq.id
                WHERE f.id = ?";
$stmt_factura = mysqli_prepare($enlace, $sql_factura);
if (!$stmt_factura) { /*die("Error DB L1: " . mysqli_error($enlace));*/ return; } // Modificado para la herramienta
mysqli_stmt_bind_param($stmt_factura, "i", $factura_id);
mysqli_stmt_execute($stmt_factura);
$res_factura = mysqli_stmt_get_result($stmt_factura);
$factura = mysqli_fetch_assoc($res_factura);
mysqli_stmt_close($stmt_factura);

if (!$factura) { /*die("Factura no encontrada.");*/ return; } // Modificado

$factura_items = [];
$sql_items = "SELECT fi.descripcion, fi.cantidad, fi.precio_unitario, fi.subtotal_item
              FROM factura_items fi WHERE fi.factura_id = ?";
$stmt_items = mysqli_prepare($enlace, $sql_items);
if (!$stmt_items) { /*die("Error DB L2: " . mysqli_error($enlace));*/ return; } // Modificado
mysqli_stmt_bind_param($stmt_items, "i", $factura_id);
mysqli_stmt_execute($stmt_items);
$res_items = mysqli_stmt_get_result($stmt_items);
while ($item = mysqli_fetch_assoc($res_items)) {
    $factura_items[] = $item;
}
mysqli_stmt_close($stmt_items);
mysqli_close($enlace); // Cerramos conexión aquí ya que no hay footer.

// --- PDF Generation ---
class PDF extends FPDF {
    private $datos_empresa_pdf;
    private $factura_num_pdf;
    private $fecha_emision_pdf;

    function setHeaderInfo($datos_empresa, $factura_num, $fecha_emision) {
        $this->datos_empresa_pdf = $datos_empresa;
        $this->factura_num_pdf = $factura_num;
        $this->fecha_emision_pdf = $fecha_emision;
    }

    function Header() {
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, iconv('UTF-8', 'windows-1252',$this->datos_empresa_pdf['nombre_empresa'] ?? 'RDM Solutions'), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, iconv('UTF-8', 'windows-1252',$this->datos_empresa_pdf['empresa_domicilio'] ?? 'Domicilio no especificado'), 0, 1, 'C');
        $this->Cell(0, 5, iconv('UTF-8', 'windows-1252',"CUIT: " . ($this->datos_empresa_pdf['cuit'] ?? '') . " - Cond. IVA: " . ($this->datos_empresa_pdf['empresa_condicion_iva'] ?? '')), 0, 1, 'C');
        $this->Ln(5);

        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'FACTURA TIPO "C"', 0, 1, 'R');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, iconv('UTF-8', 'windows-1252',"Factura N°: " . $this->factura_num_pdf), 0, 1, 'R');
        $this->Cell(0, 5, iconv('UTF-8', 'windows-1252',"Fecha Emisión: " . date("d/m/Y", strtotime($this->fecha_emision_pdf))), 0, 1, 'R');
        $this->Ln(10);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, iconv('UTF-8', 'windows-1252','Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function FancyTable($header, $data_items) {
        $this->SetFillColor(230, 230, 230); $this->SetTextColor(0); $this->SetDrawColor(128, 128, 128);
        $this->SetLineWidth(.3); $this->SetFont('', 'B');
        $w = array(110, 25, 25, 30);
        for($i=0; $i<count($header); $i++) $this->Cell($w[$i], 7, iconv('UTF-8', 'windows-1252',$header[$i]), 1, 0, 'C', true);
        $this->Ln();
        $this->SetFont(''); $this->SetFillColor(245, 245, 245); $this->SetTextColor(0); $fill = false;
        foreach($data_items as $row) {
            $this->Cell($w[0], 6, iconv('UTF-8', 'windows-1252',substr($row['descripcion'],0,60)), 'LR', 0, 'L', $fill);
            $this->Cell($w[1], 6, $row['cantidad'], 'LR', 0, 'R', $fill);
            $this->Cell($w[2], 6, number_format($row['precio_unitario'], 2, ',', '.'), 'LR', 0, 'R', $fill);
            $this->Cell($w[3], 6, number_format($row['subtotal_item'], 2, ',', '.'), 'LR', 0, 'R', $fill);
            $this->Ln(); $fill = !$fill;
        }
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->setHeaderInfo($factura, $factura['numero_factura'], $factura['fecha_emision']);
$pdf->AliasNbPages(); $pdf->AddPage(); $pdf->SetFont('Arial', '', 10);

$pdf->SetFont('', 'B'); $pdf->Cell(0, 7, iconv('UTF-8', 'windows-1252','Datos del Cliente:'), 0, 1); $pdf->SetFont('');
$pdf->Cell(0, 5, iconv('UTF-8', 'windows-1252',"Nombre: " . $factura['cliente_nombre']), 0, 1);
$pdf->Cell(0, 5, iconv('UTF-8', 'windows-1252',"DNI/CUIT: " . $factura['cliente_dni']), 0, 1);
$pdf->Cell(0, 5, iconv('UTF-8', 'windows-1252',"Domicilio: " . ($factura['cliente_direccion'] ?? '')), 0, 1); $pdf->Ln(5);

if ($factura['equipo_id']) {
    $pdf->SetFont('', 'B'); $pdf->Cell(0, 7, iconv('UTF-8', 'windows-1252','Equipo Asociado:'), 0, 1); $pdf->SetFont('');
    $pdf->Cell(0, 5, iconv('UTF-8', 'windows-1252',"Tipo: " . ($factura['tipo_equipo'] ?? '') . " - Marca/Modelo: " . ($factura['equipo_marca'] ?? '') . " " . ($factura['equipo_modelo'] ?? '')), 0, 1);
    $pdf->Cell(0, 5, iconv('UTF-8', 'windows-1252',"Serie/IMEI: " . ($factura['equipo_serie'] ?? '')), 0, 1); $pdf->Ln(5);
}

$header_items = array('Descripción', 'Cantidad', 'P. Unit.', 'Subtotal');
$pdf->FancyTable($header_items, $factura_items); $pdf->Ln(2);

$pdf->SetFont('', 'B'); $pdf->Cell(135, 6, 'Subtotal:', 0, 0, 'R'); $pdf->SetFont('');
$pdf->Cell(25, 6, '$' . number_format($factura['subtotal'], 2, ',', '.'), 0, 1, 'R');
$pdf->SetFont('', 'B'); $pdf->Cell(135, 6, 'IVA (' . number_format($factura['iva_porcentaje'], 2, ',', '.') . '%):', 0, 0, 'R'); $pdf->SetFont('');
$pdf->Cell(25, 6, '$' . number_format($factura['iva_monto'], 2, ',', '.'), 0, 1, 'R');
$pdf->SetFont('', 'B', 12); $pdf->Cell(135, 8, 'TOTAL:', 0, 0, 'R'); $pdf->SetFont('', 'B', 12);
$pdf->Cell(25, 8, '$' . number_format($factura['total'], 2, ',', '.'), 0, 1, 'R'); $pdf->Ln(5);

$pdf->SetFont('', '');
$pdf->Cell(0,5, iconv('UTF-8', 'windows-1252',"Condición de Venta: " . ($factura['condicion_venta'] ?? '')),0,1);
$pdf->Cell(0,5, iconv('UTF-8', 'windows-1252',"Método de Pago: " . ($factura['metodo_pago'] ?? '')),0,1); $pdf->Ln(5);

$pdf->SetFont('', 'B', 8);
$pdf->Cell(0,5, iconv('UTF-8', 'windows-1252',"CAE N°: " . ($factura['cae'] ?? 'Comprobante no fiscal')),0,0,'L');
$pdf->Cell(0,5, iconv('UTF-8', 'windows-1252',"Fecha Vto. CAE: " . ($factura['fecha_vto_cae'] ? date("d/m/Y", strtotime($factura['fecha_vto_cae'])) : '')),0,1,'R');

$pdf_filename = "Factura_RDM_" . str_replace('-', '_', $factura['numero_factura']) . ".pdf";
$pdf->Output('I', $pdf_filename);
// exit; // Comentado para la herramienta
?>
