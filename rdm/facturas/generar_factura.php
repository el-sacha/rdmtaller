<?php
// facturas/generar_factura.php
require_once '../includes/funciones.php';
require_once '../includes/db.php';

verificar_login();

$mensaje = '';
$error_validacion = [];
$equipo_id = isset($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : null;

$equipo_info = null;
$cliente_info = null;
$items_factura_temp = [['descripcion' => '', 'cantidad' => 1, 'precio_unitario' => '']]; // Para un item inicial
$metodo_pago = 'Efectivo';
$condicion_venta = 'Contado';
$iva_porcentaje_defecto = 21.00;


if ($equipo_id) {
    $stmt_eq = mysqli_prepare($enlace, "SELECT e.*, c.nombre as cliente_nombre, c.id as cliente_id_eq, c.dni as cliente_dni, c.direccion as cliente_direccion, c.email as cliente_email, c.telefono as cliente_telefono
                                       FROM equipos e JOIN clientes c ON e.cliente_id = c.id
                                       WHERE e.id = ?");
    if ($stmt_eq) {
        mysqli_stmt_bind_param($stmt_eq, "i", $equipo_id);
        mysqli_stmt_execute($stmt_eq);
        $res_eq = mysqli_stmt_get_result($stmt_eq);
        $equipo_info = mysqli_fetch_assoc($res_eq);
        mysqli_stmt_close($stmt_eq);
        if ($equipo_info) {
            $cliente_info = [ // Llenar datos del cliente desde el equipo
                'id' => $equipo_info['cliente_id_eq'],
                'nombre' => $equipo_info['cliente_nombre'],
                'dni' => $equipo_info['cliente_dni'],
                'direccion' => $equipo_info['cliente_direccion'],
                'email' => $equipo_info['cliente_email'],
                'telefono' => $equipo_info['cliente_telefono']
            ];
            // Sugerir items basados en el equipo
            $items_factura_temp = [
                ['descripcion' => "Reparación equipo: " . htmlspecialchars($equipo_info['tipo_equipo'] . " " . $equipo_info['marca'] . " " . $equipo_info['modelo']), 'cantidad' => 1, 'precio_unitario' => htmlspecialchars($equipo_info['costo_total_reparacion'] ?? '0.00')],
                ['descripcion' => "Mano de Obra", 'cantidad' => 1, 'precio_unitario' => '0.00'], // Ejemplo
            ];
        }
    } else {
        $mensaje = "<div class='alert alert-danger'>Error al cargar datos del equipo.</div>";
    }
}

// Cargar clientes para el dropdown (si no viene equipo_id o no se encontró)
$clientes_dropdown = [];
$stmt_clientes_all = mysqli_prepare($enlace, "SELECT id, nombre, dni FROM clientes ORDER BY nombre ASC");
if($stmt_clientes_all){
    mysqli_stmt_execute($stmt_clientes_all);
    $res_clientes_all = mysqli_stmt_get_result($stmt_clientes_all);
    while($row = mysqli_fetch_assoc($res_clientes_all)){
        $clientes_dropdown[] = $row;
    }
    mysqli_stmt_close($stmt_clientes_all);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id_form = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;
    $equipo_id_form = isset($_POST['equipo_id_hidden']) ? (int)$_POST['equipo_id_hidden'] : null; // ID del equipo original si vino por GET

    $metodo_pago = sanitizar_entrada($_POST['metodo_pago']);
    $condicion_venta = sanitizar_entrada($_POST['condicion_venta']);
    $iva_porcentaje = isset($_POST['iva_porcentaje']) ? (float)$_POST['iva_porcentaje'] : $iva_porcentaje_defecto;

    // Items de la factura
    $descripciones = $_POST['descripcion'] ?? [];
    $cantidades = $_POST['cantidad'] ?? [];
    $precios_unitarios = $_POST['precio_unitario'] ?? [];

    if (empty($cliente_id_form)) {
        $error_validacion['cliente_id'] = "Debe seleccionar un cliente.";
    }
    if (empty($descripciones) || empty($descripciones[0])) { // Al menos un item
         $error_validacion['items'] = "Debe agregar al menos un ítem a la factura.";
    }
    // Más validaciones aquí (ej. cantidades y precios numéricos > 0)

    if (empty($error_validacion)) {
        mysqli_begin_transaction($enlace);
        try {
            // 1. Obtener datos fiscales de la empresa (asumimos ID=1 o el primero)
            $stmt_df = mysqli_prepare($enlace, "SELECT id, punto_venta FROM datos_fiscales_empresa ORDER BY id LIMIT 1");
            mysqli_stmt_execute($stmt_df);
            $res_df = mysqli_stmt_get_result($stmt_df);
            $datos_fiscales_actuales = mysqli_fetch_assoc($res_df);
            mysqli_stmt_close($stmt_df);
            if (!$datos_fiscales_actuales) throw new Exception("Datos fiscales de la empresa no configurados.");

            $punto_venta_actual = $datos_fiscales_actuales['punto_venta'];
            $datos_fiscales_id_actual = $datos_fiscales_actuales['id'];

            // 2. Generar número de factura (simplificado, mejorar en producción)
            // Formato: PUNTO_VENTA-NUMERO_SECUENCIAL (ej: 0001-00000001)
            $stmt_max_num = mysqli_prepare($enlace, "SELECT MAX(CAST(SUBSTRING_INDEX(numero_factura, '-', -1) AS UNSIGNED)) AS max_seq FROM facturas WHERE numero_factura LIKE ?");
            $prefijo_busqueda = $punto_venta_actual . "-%";
            mysqli_stmt_bind_param($stmt_max_num, "s", $prefijo_busqueda);
            mysqli_stmt_execute($stmt_max_num);
            $res_max_num = mysqli_stmt_get_result($stmt_max_num);
            $row_max_num = mysqli_fetch_assoc($res_max_num);
            mysqli_stmt_close($stmt_max_num);
            $siguiente_secuencial = ($row_max_num['max_seq'] ?? 0) + 1;
            $numero_factura_generado = $punto_venta_actual . "-" . str_pad($siguiente_secuencial, 8, '0', STR_PAD_LEFT);

            // 3. Calcular totales
            $subtotal_factura = 0;
            foreach ($descripciones as $key => $desc) {
                $cant = (int)$cantidades[$key];
                $pu = (float)$precios_unitarios[$key];
                if (!empty($desc) && $cant > 0 && $pu >= 0) {
                    $subtotal_factura += $cant * $pu;
                }
            }
            $iva_monto = round($subtotal_factura * ($iva_porcentaje / 100), 2);
            $total_factura = $subtotal_factura + $iva_monto;

            // 4. Insertar en `facturas`
            $sql_factura = "INSERT INTO facturas (cliente_id, equipo_id, datos_fiscales_empresa_id, numero_factura, subtotal, iva_porcentaje, iva_monto, total, metodo_pago, condicion_venta, fecha_emision)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt_factura = mysqli_prepare($enlace, $sql_factura);
            if (!$stmt_factura) throw new Exception("Error al preparar factura: " . mysqli_error($enlace));
            mysqli_stmt_bind_param($stmt_factura, "iiisddddss", $cliente_id_form, $equipo_id_form, $datos_fiscales_id_actual, $numero_factura_generado, $subtotal_factura, $iva_porcentaje, $iva_monto, $total_factura, $metodo_pago, $condicion_venta);
            if (!mysqli_stmt_execute($stmt_factura)) throw new Exception("Error al guardar factura: " . mysqli_stmt_error($stmt_factura));
            $nueva_factura_id = mysqli_insert_id($enlace);
            mysqli_stmt_close($stmt_factura);

            // 5. Insertar en `factura_items`
            $sql_item = "INSERT INTO factura_items (factura_id, descripcion, cantidad, precio_unitario, subtotal_item) VALUES (?, ?, ?, ?, ?)";
            $stmt_item = mysqli_prepare($enlace, $sql_item);
            if (!$stmt_item) throw new Exception("Error al preparar items de factura: " . mysqli_error($enlace));

            foreach ($descripciones as $key => $desc) {
                $cant = (int)$cantidades[$key];
                $pu = (float)$precios_unitarios[$key];
                if (!empty($desc) && $cant > 0 && $pu >= 0) {
                    $sub_item = $cant * $pu;
                    mysqli_stmt_bind_param($stmt_item, "isidd", $nueva_factura_id, $desc, $cant, $pu, $sub_item);
                    if (!mysqli_stmt_execute($stmt_item)) throw new Exception("Error al guardar item de factura: " . mysqli_stmt_error($stmt_item));
                }
            }
            mysqli_stmt_close($stmt_item);

            mysqli_commit($enlace);
            header("Location: ver_factura.php?id=" . $nueva_factura_id);
            return;

        } catch (Exception $e) {
            mysqli_rollback($enlace);
            $mensaje = "<div class='alert alert-danger'>Error al generar la factura: " . $e->getMessage() . "</div>";
        }
    } else {
         $mensaje = "<div class='alert alert-danger'>Por favor corrija los errores del formulario.</div>";
         // Repopular items_factura_temp para mostrar errores
         $items_factura_temp = [];
         foreach ($descripciones as $key => $desc) {
             $items_factura_temp[] = ['descripcion' => $desc, 'cantidad' => $cantidades[$key], 'precio_unitario' => $precios_unitarios[$key]];
         }
         if(empty($items_factura_temp)) $items_factura_temp = [['descripcion' => '', 'cantidad' => 1, 'precio_unitario' => '']];
    }
}


require_once '../includes/header.php';
?>
<div class="container mt-4">
    <h2>Generar Nueva Factura</h2>
    <?php if (!empty($mensaje)) echo $mensaje; ?>

    <form action="generar_factura.php<?php if($equipo_id) echo '?equipo_id='.$equipo_id; ?>" method="POST" id="form-factura">
        <input type="hidden" name="equipo_id_hidden" value="<?php echo htmlspecialchars($equipo_id ?? ''); ?>">

        <fieldset class="mb-3 p-3 border">
            <legend class="w-auto px-2">Datos del Cliente</legend>
            <?php if ($cliente_info): ?>
                <input type="hidden" name="cliente_id" value="<?php echo htmlspecialchars($cliente_info['id']); ?>">
                <p><strong>Nombre:</strong> <?php echo htmlspecialchars($cliente_info['nombre']); ?></p>
                <p><strong>DNI/CIF:</strong> <?php echo htmlspecialchars($cliente_info['dni']); ?></p>
                <p><strong>Dirección:</strong> <?php echo htmlspecialchars($cliente_info['direccion'] ?? 'N/A'); ?></p>
                 <p><a href="../clientes/editar.php?id=<?php echo $cliente_info['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Editar Cliente</a>
                    <a href="generar_factura.php" class="btn btn-sm btn-outline-info">Cambiar Cliente/Factura Manual</a></p>
            <?php else: ?>
                <div class="mb-3">
                    <label for="cliente_id" class="form-label">Seleccionar Cliente:</label>
                    <select name="cliente_id" id="cliente_id" class="form-select <?php if(isset($error_validacion['cliente_id'])) echo 'is-invalid'; ?>" required>
                        <option value="">-- Seleccione --</option>
                        <?php foreach ($clientes_dropdown as $cli): ?>
                            <option value="<?php echo $cli['id']; ?>" <?php echo (isset($_POST['cliente_id']) && $_POST['cliente_id'] == $cli['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cli['nombre']) . ' (DNI: ' . htmlspecialchars($cli['dni']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if(isset($error_validacion['cliente_id'])): ?><div class="invalid-feedback"><?php echo $error_validacion['cliente_id']; ?></div><?php endif; ?>
                </div>
            <?php endif; ?>
        </fieldset>

        <?php if ($equipo_info): ?>
        <fieldset class="mb-3 p-3 border">
            <legend class="w-auto px-2">Datos del Equipo Asociado</legend>
            <p><strong>Tipo:</strong> <?php echo htmlspecialchars($equipo_info['tipo_equipo']); ?></p>
            <p><strong>Marca/Modelo:</strong> <?php echo htmlspecialchars($equipo_info['marca'] . " " . $equipo_info['modelo']); ?></p>
            <p><strong>Serie/IMEI:</strong> <?php echo htmlspecialchars($equipo_info['numero_serie_imei'] ?? 'N/A'); ?></p>
        </fieldset>
        <?php endif; ?>

        <fieldset class="mb-3 p-3 border">
            <legend class="w-auto px-2">Items de la Factura</legend>
            <div id="items-container">
                <?php foreach ($items_factura_temp as $index => $item): ?>
                <div class="row item-row mb-2 align-items-end">
                    <div class="col-md-5">
                        <label for="descripcion_<?php echo $index; ?>" class="form-label">Descripción:</label>
                        <input type="text" name="descripcion[]" id="descripcion_<?php echo $index; ?>" class="form-control" value="<?php echo htmlspecialchars($item['descripcion']); ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label for="cantidad_<?php echo $index; ?>" class="form-label">Cantidad:</label>
                        <input type="number" name="cantidad[]" id="cantidad_<?php echo $index; ?>" class="form-control" value="<?php echo htmlspecialchars($item['cantidad']); ?>" min="1" required>
                    </div>
                    <div class="col-md-3">
                        <label for="precio_unitario_<?php echo $index; ?>" class="form-label">P. Unitario:</label>
                        <input type="number" name="precio_unitario[]" id="precio_unitario_<?php echo $index; ?>" class="form-control" value="<?php echo htmlspecialchars($item['precio_unitario']); ?>" step="0.01" min="0" required>
                    </div>
                    <div class="col-md-2">
                        <?php if ($index > 0): ?>
                            <button type="button" class="btn btn-danger btn-sm remove-item">Quitar</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if(isset($error_validacion['items'])): ?><div class="text-danger small mb-2"><?php echo $error_validacion['items']; ?></div><?php endif; ?>
            <button type="button" id="add-item" class="btn btn-success btn-sm mt-2">Agregar Item</button>
        </fieldset>

        <fieldset class="mb-3 p-3 border">
            <legend class="w-auto px-2">Condiciones y Totales</legend>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="metodo_pago" class="form-label">Método de Pago:</label>
                    <input type="text" name="metodo_pago" id="metodo_pago" class="form-control" value="<?php echo htmlspecialchars($metodo_pago); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="condicion_venta" class="form-label">Condición de Venta:</label>
                    <input type="text" name="condicion_venta" id="condicion_venta" class="form-control" value="<?php echo htmlspecialchars($condicion_venta); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="iva_porcentaje" class="form-label">IVA (%):</label>
                    <input type="number" name="iva_porcentaje" id="iva_porcentaje" class="form-control" value="<?php echo htmlspecialchars($iva_porcentaje_defecto); ?>" step="0.01" required>
                </div>
            </div>
        </fieldset>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary">Generar Factura</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const itemsContainer = document.getElementById('items-container');
    const addItemButton = document.getElementById('add-item');
    let itemIndex = <?php echo count($items_factura_temp); ?>;

    addItemButton.addEventListener('click', function () {
        const newItemRow = document.createElement('div');
        newItemRow.classList.add('row', 'item-row', 'mb-2', 'align-items-end');
        newItemRow.innerHTML = `
            <div class="col-md-5">
                <label for="descripcion_${itemIndex}" class="form-label visually-hidden">Descripción:</label>
                <input type="text" name="descripcion[]" id="descripcion_${itemIndex}" class="form-control" placeholder="Descripción" required>
            </div>
            <div class="col-md-2">
                <label for="cantidad_${itemIndex}" class="form-label visually-hidden">Cantidad:</label>
                <input type="number" name="cantidad[]" id="cantidad_${itemIndex}" class="form-control" value="1" min="1" placeholder="Cant." required>
            </div>
            <div class="col-md-3">
                <label for="precio_unitario_${itemIndex}" class="form-label visually-hidden">P. Unitario:</label>
                <input type="number" name="precio_unitario[]" id="precio_unitario_${itemIndex}" class="form-control" step="0.01" min="0" placeholder="P. Unit." required>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger btn-sm remove-item">Quitar</button>
            </div>
        `;
        itemsContainer.appendChild(newItemRow);
        itemIndex++;
    });

    itemsContainer.addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('remove-item')) {
            e.target.closest('.item-row').remove();
        }
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>
