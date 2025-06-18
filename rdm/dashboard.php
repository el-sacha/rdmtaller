<?php
// dashboard.php
require_once 'includes/funciones.php';
require_once 'includes/db.php';

verificar_login();

// --- Data Fetching for Charts ---

// 1. Equipos Ingresados por Mes (Últimos 6 meses)
$equipos_por_mes_labels = [];
$equipos_por_mes_data = [];
$current_month = date('Y-m');
for ($i = 5; $i >= 0; $i--) {
    $month_year = date('Y-m', strtotime($current_month . " -$i months"));
    // Usar nombres de meses en español para etiquetas
    $date_obj = DateTime::createFromFormat('!Y-m', $month_year);
    $month_name_es = strftime('%B %Y', $date_obj->getTimestamp()); // Necesita locale es_ES configurado en el sistema
    // Fallback si strftime no da español o no está configurado el locale:
    if (strpos($month_name_es, ' ') === false) { // Simple check si no tradujo
        $month_name_es = $date_obj->format('F Y'); // English month name
    }
    $equipos_por_mes_labels[] = $month_name_es;

    $sql_equipos_mes = "SELECT COUNT(id) as count FROM equipos WHERE DATE_FORMAT(fecha_ingreso, '%Y-%m') = ?";
    $stmt_eq_mes = mysqli_prepare($enlace, $sql_equipos_mes);
    if ($stmt_eq_mes) {
        mysqli_stmt_bind_param($stmt_eq_mes, "s", $month_year);
        mysqli_stmt_execute($stmt_eq_mes);
        $res_eq_mes = mysqli_stmt_get_result($stmt_eq_mes);
        $data_eq_mes = mysqli_fetch_assoc($res_eq_mes);
        $equipos_por_mes_data[] = $data_eq_mes['count'] ?? 0;
        mysqli_stmt_close($stmt_eq_mes);
    } else {
        $equipos_por_mes_data[] = 0; // Error case
    }
}

// 2. Estado Actual de Equipos (Todos)
$estado_equipos_labels = [];
$estado_equipos_data = [];
$sql_estado_equipos = "SELECT er.nombre_estado, COUNT(e.id) as count
                       FROM equipos e
                       JOIN estados_reparacion er ON e.estado_reparacion_id = er.id
                       GROUP BY er.nombre_estado
                       ORDER BY count DESC";
$res_estado_eq = mysqli_query($enlace, $sql_estado_equipos);
if ($res_estado_eq) {
    while ($row = mysqli_fetch_assoc($res_estado_eq)) {
        $estado_equipos_labels[] = $row['nombre_estado'];
        $estado_equipos_data[] = $row['count'];
    }
}

// 3. Facturación Mensual (Últimos 6 meses) - Admin only
$facturacion_mensual_labels = [];
$facturacion_mensual_data = [];
if ($_SESSION['rol'] === 'admin') {
    for ($i = 5; $i >= 0; $i--) {
        $month_year_fac = date('Y-m', strtotime($current_month . " -$i months"));
        $date_obj_fac = DateTime::createFromFormat('!Y-m', $month_year_fac);
        $month_name_es_fac = strftime('%B %Y', $date_obj_fac->getTimestamp());
        if (strpos($month_name_es_fac, ' ') === false) {
             $month_name_es_fac = $date_obj_fac->format('F Y');
        }
        $facturacion_mensual_labels[] = $month_name_es_fac;

        $sql_fac_mes = "SELECT SUM(total) as sum_total FROM facturas WHERE DATE_FORMAT(fecha_emision, '%Y-%m') = ?";
        $stmt_fac_mes = mysqli_prepare($enlace, $sql_fac_mes);
        if ($stmt_fac_mes) {
            mysqli_stmt_bind_param($stmt_fac_mes, "s", $month_year_fac);
            mysqli_stmt_execute($stmt_fac_mes);
            $res_fac_mes = mysqli_stmt_get_result($stmt_fac_mes);
            $data_fac_mes = mysqli_fetch_assoc($res_fac_mes);
            $facturacion_mensual_data[] = $data_fac_mes['sum_total'] ?? 0;
            mysqli_stmt_close($stmt_fac_mes);
        } else {
            $facturacion_mensual_data[] = 0;
        }
    }
}

// Quick Stats
$total_clientes = mysqli_fetch_assoc(mysqli_query($enlace, "SELECT COUNT(id) as count FROM clientes"))['count'] ?? 0;
$equipos_pendientes = mysqli_fetch_assoc(mysqli_query($enlace, "SELECT COUNT(e.id) as count FROM equipos e JOIN estados_reparacion er ON e.estado_reparacion_id = er.id WHERE er.nombre_estado NOT IN ('Entregado', 'No reparado', 'Presupuesto rechazado')"))['count'] ?? 0;
$total_tecnicos = mysqli_fetch_assoc(mysqli_query($enlace, "SELECT COUNT(id) as count FROM tecnicos"))['count'] ?? 0;


require_once 'includes/header.php';
// Nota: El path a chart.umd.min.js en el footer se ajustará por la lógica de $path_prefix_footer
?>

<div class="container mt-4">
    <h1 class="mb-4">Dashboard General</h1>

    <!-- Quick Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-white bg-primary mb-3">
                <div class="card-header">Total Clientes</div>
                <div class="card-body"><h5 class="card-title"><?php echo $total_clientes; ?></h5></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-warning mb-3">
                <div class="card-header">Equipos Pendientes/En Proceso</div>
                <div class="card-body"><h5 class="card-title"><?php echo $equipos_pendientes; ?></h5></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-info mb-3">
                <div class="card-header">Total Técnicos</div>
                <div class="card-body"><h5 class="card-title"><?php echo $total_tecnicos; ?></h5></div>
            </div>
        </div>
    </div>


    <div class="row">
        <div class="col-lg-7 mb-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Equipos Ingresados por Mes (Últimos 6 Meses)</h5></div>
                <div class="card-body">
                    <canvas id="equiposPorMesChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-5 mb-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Estado Actual de Equipos</h5></div>
                <div class="card-body">
                    <canvas id="estadoEquiposChart" style="max-height: 300px;"></canvas> <!-- Max height para pie/doughnut -->
                </div>
            </div>
        </div>
    </div>

    <?php if ($_SESSION['rol'] === 'admin' && !empty($facturacion_mensual_data)): ?>
    <div class="row mt-4">
        <div class="col-lg-12 mb-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Facturación Mensual (Últimos 6 Meses)</h5></div>
                <div class="card-body">
                    <canvas id="facturacionMensualChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php
// Para pasar datos a JS. Es crucial sanitizar/validar estos datos si vinieran de input de usuario.
// Aquí vienen de la BD y cálculos, así que json_encode es seguro.
$equipos_por_mes_labels_json = json_encode($equipos_por_mes_labels);
$equipos_por_mes_data_json = json_encode($equipos_por_mes_data);
$estado_equipos_labels_json = json_encode($estado_equipos_labels);
$estado_equipos_data_json = json_encode($estado_equipos_data);
if ($_SESSION['rol'] === 'admin') {
    $facturacion_mensual_labels_json = json_encode($facturacion_mensual_labels);
    $facturacion_mensual_data_json = json_encode($facturacion_mensual_data);
}
?>

<script>
// El script de Chart.js se incluye en el footer.php
document.addEventListener('DOMContentLoaded', function () {
    // Chart: Equipos Ingresados por Mes
    const ctxEquiposMes = document.getElementById('equiposPorMesChart');
    if (ctxEquiposMes) {
        new Chart(ctxEquiposMes, {
            type: 'bar',
            data: {
                labels: <?php echo $equipos_por_mes_labels_json; ?>,
                datasets: [{
                    label: 'Nº de Equipos Ingresados',
                    data: <?php echo $equipos_por_mes_data_json; ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: { scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    }

    // Chart: Estado Actual de Equipos
    const ctxEstadoEquipos = document.getElementById('estadoEquiposChart');
    if (ctxEstadoEquipos) {
        new Chart(ctxEstadoEquipos, {
            type: 'doughnut', // o 'pie'
            data: {
                labels: <?php echo $estado_equipos_labels_json; ?>,
                datasets: [{
                    label: 'Estado de Equipos',
                    data: <?php echo $estado_equipos_data_json; ?>,
                    backgroundColor: [ // Colores de ejemplo, se pueden generar dinámicamente
                        'rgba(255, 99, 132, 0.7)', 'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)', 'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)', 'rgba(255, 159, 64, 0.7)',
                        'rgba(100, 100, 100, 0.7)'
                    ],
                    hoverOffset: 4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    <?php if ($_SESSION['rol'] === 'admin' && !empty($facturacion_mensual_data)): ?>
    // Chart: Facturación Mensual
    const ctxFacturacionMes = document.getElementById('facturacionMensualChart');
    if (ctxFacturacionMes) {
        new Chart(ctxFacturacionMes, {
            type: 'line', // o 'bar'
            data: {
                labels: <?php echo $facturacion_mensual_labels_json; ?>,
                datasets: [{
                    label: 'Facturación Total ($)',
                    data: <?php echo $facturacion_mensual_data_json; ?>,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    fill: true,
                    tension: 0.1
                }]
            },
            options: { scales: { y: { beginAtZero: true } } }
        });
    }
    <?php endif; ?>
});
</script>

<?php
require_once 'includes/footer.php';
// Chart.js se incluye en footer.php
?>
