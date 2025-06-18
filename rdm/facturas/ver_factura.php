<?php
// facturas/ver_factura.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login();

$factura_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($factura_id <= 0) {
    $_SESSION['error_accion_factura'] = "ID de factura no válido."; // Usar un nuevo tipo de mensaje de sesión
    header("Location: listar_facturas.php"); // Crear esta página después
    return;
}

// Fetch Factura Data
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
if (!$stmt_factura) {
    die("Error al preparar consulta de factura: " . mysqli_error($enlace));
}
mysqli_stmt_bind_param($stmt_factura, "i", $factura_id);
mysqli_stmt_execute($stmt_factura);
$res_factura = mysqli_stmt_get_result($stmt_factura);
$factura = mysqli_fetch_assoc($res_factura);
mysqli_stmt_close($stmt_factura);

if (!$factura) {
    $_SESSION['error_accion_factura'] = "Factura no encontrada.";
    header("Location: listar_facturas.php");
    return;
}

// Fetch Factura Items
$factura_items = [];
$sql_items = "SELECT fi.descripcion, fi.cantidad, fi.precio_unitario, fi.subtotal_item
              FROM factura_items fi
              WHERE fi.factura_id = ?";
$stmt_items = mysqli_prepare($enlace, $sql_items);
if (!$stmt_items) {
    die("Error al preparar consulta de items: " . mysqli_error($enlace));
}
mysqli_stmt_bind_param($stmt_items, "i", $factura_id);
mysqli_stmt_execute($stmt_items);
$res_items = mysqli_stmt_get_result($stmt_items);
while ($item = mysqli_fetch_assoc($res_items)) {
    $factura_items[] = $item;
}
mysqli_stmt_close($stmt_items);

require_once '../includes/header.php';
?>
<div class="container mt-4" id="invoice-container">
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <?php if (!empty($factura['empresa_logo'])): ?>
                        <img src="<?php echo htmlspecialchars($factura['empresa_logo']); ?>" alt="Logo Empresa" style="max-height: 80px; max-width: 200px;">
                    <?php else: ?>
                        <h3><?php echo htmlspecialchars($factura['nombre_empresa']); ?></h3>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 text-md-end">
                    <h4>FACTURA TIPO "C"</h4>
                    <p class="mb-0"><strong>Nº:</strong> <?php echo htmlspecialchars($factura['numero_factura']); ?></p>
                    <p class="mb-0"><strong>Fecha Emisión:</strong> <?php echo date("d/m/Y", strtotime($factura['fecha_emision'])); ?></p>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5>Datos Empresa Emisora:</h5>
                    <p class="mb-1"><strong><?php echo htmlspecialchars($factura['nombre_empresa']); ?></strong></p>
                    <p class="mb-1"><?php echo htmlspecialchars($factura['empresa_domicilio']); ?></p>
                    <p class="mb-1"><strong>CUIT:</strong> <?php echo htmlspecialchars($factura['cuit']); ?></p>
                    <p class="mb-1"><strong>Cond. IVA:</strong> <?php echo htmlspecialchars($factura['empresa_condicion_iva']); ?></p>
                    <p class="mb-1"><strong>Ing. Brutos:</strong> <?php echo htmlspecialchars($factura['empresa_iibb'] ?? '-'); ?></p>
                    <p class="mb-1"><strong>Inicio Actividades:</strong> <?php echo $factura['empresa_inicio_act'] ? date("d/m/Y", strtotime($factura['empresa_inicio_act'])) : '-'; ?></p>
                </div>
                <div class="col-md-6">
                    <h5>Datos Cliente Receptor:</h5>
                    <p class="mb-1"><strong>Nombre:</strong> <?php echo htmlspecialchars($factura['cliente_nombre']); ?></p>
                    <p class="mb-1"><strong>DNI/CUIT:</strong> <?php echo htmlspecialchars($factura['cliente_dni']); ?></p>
                    <p class="mb-1"><strong>Domicilio:</strong> <?php echo htmlspecialchars($factura['cliente_direccion'] ?? '-'); ?></p>
                    <p class="mb-1"><strong>Teléfono:</strong> <?php echo htmlspecialchars($factura['cliente_telefono'] ?? '-'); ?></p>
                    <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($factura['cliente_email'] ?? '-'); ?></p>
                </div>
            </div>

            <?php if ($factura['equipo_id']): ?>
            <div class="row mb-3">
                <div class="col">
                    <h5>Equipo Asociado:</h5>
                     <p class="mb-1"><strong>Tipo:</strong> <?php echo htmlspecialchars($factura['tipo_equipo'] ?? '-'); ?> <strong>Marca/Modelo:</strong> <?php echo htmlspecialchars($factura['equipo_marca'] . ' ' . $factura['equipo_modelo']); ?> <strong>Serie/IMEI:</strong> <?php echo htmlspecialchars($factura['equipo_serie'] ?? '-'); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <div class="table-responsive mb-4">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Descripción</th>
                            <th class="text-end">Cantidad</th>
                            <th class="text-end">P. Unitario</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($factura_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['descripcion']); ?></td>
                            <td class="text-end"><?php echo htmlspecialchars($item['cantidad']); ?></td>
                            <td class="text-end"><?php echo number_format($item['precio_unitario'], 2, ',', '.'); ?></td>
                            <td class="text-end"><?php echo number_format($item['subtotal_item'], 2, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                            <td class="text-end">$<?php echo number_format($factura['subtotal'], 2, ',', '.'); ?></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end"><strong>IVA (<?php echo htmlspecialchars($factura['iva_porcentaje']); ?>%):</strong></td>
                            <td class="text-end">$<?php echo number_format($factura['iva_monto'], 2, ',', '.'); ?></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end"><strong>TOTAL:</strong></td>
                            <td class="text-end"><strong>$<?php echo number_format($factura['total'], 2, ',', '.'); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <p><strong>Método de Pago:</strong> <?php echo htmlspecialchars($factura['metodo_pago'] ?? '-'); ?></p>
                    <p><strong>Condición de Venta:</strong> <?php echo htmlspecialchars($factura['condicion_venta'] ?? '-'); ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p><strong>CAE N°:</strong> <?php echo htmlspecialchars($factura['cae'] ?? 'N/A (Comprobante no fiscal)'); ?></p>
                    <p><strong>Fecha Vto. CAE:</strong> <?php echo $factura['fecha_vto_cae'] ? date("d/m/Y", strtotime($factura['fecha_vto_cae'])) : 'N/A'; ?></p>
                </div>
            </div>
            <?php if(!empty($factura['notas'])): ?>
            <div class="mt-3">
                <strong>Notas Adicionales:</strong>
                <p><?php echo nl2br(htmlspecialchars($factura['notas'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-footer text-center">
            <button class="btn btn-primary" onclick="window.print();">Imprimir Factura</button>
            <a href="descargar_pdf.php?id=<?php echo $factura_id; ?>" class="btn btn-success">Descargar PDF</a>
            <a href="listar_facturas.php" class="btn btn-secondary">Volver al Listado</a>
        </div>
    </div>
</div>
<style>
    @media print {
        body * { visibility: hidden; }
        #invoice-container, #invoice-container * { visibility: visible; }
        #invoice-container { position: absolute; left: 0; top: 0; width:100%; margin:0; padding:0; }
        .card-footer { display: none; } /* Ocultar botones al imprimir */
    }
</style>
<?php
require_once '../includes/footer.php';
?>
