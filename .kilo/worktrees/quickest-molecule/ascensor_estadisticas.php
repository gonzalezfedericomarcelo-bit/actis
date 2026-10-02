<?php
// Archivo: ascensor_estadisticas.php
session_start();
require_once 'includes/conexion.php';


// REDIRECCIÓN DINÁMICA
if ($_SESSION['usuario_rol'] != 1) { 
    $ruta_redireccion = 'index.php'; 
    if (isset($_SESSION['rol'])) {
        try {
            $stmt_ruta = $pdo->prepare("SELECT ruta_dashboard FROM roles WHERE nombre_rol = :rol");
            $stmt_ruta->execute([':rol' => $_SESSION['rol']]);
            $rol_data = $stmt_ruta->fetch(PDO::FETCH_ASSOC);
            if ($rol_data && !empty($rol_data['ruta_dashboard'])) { $ruta_redireccion = $rol_data['ruta_dashboard']; }
        } catch (Exception $e) {}
    }
    header("Location: " . $ruta_redireccion);
    exit; 
}

$filtro_estado = $_GET['estado'] ?? null;
$filtro_ascensor = $_GET['id_ascensor'] ?? null;

$total_inc = $pdo->query("SELECT COUNT(*) FROM ascensor_incidencias")->fetchColumn();
$total_act = $pdo->query("SELECT COUNT(*) FROM ascensor_incidencias WHERE estado != 'resuelto'")->fetchColumn();
$total_res = $pdo->query("SELECT COUNT(*) FROM ascensor_incidencias WHERE estado = 'resuelto'")->fetchColumn();

$data_estado = $pdo->query("SELECT estado, COUNT(*) as cantidad FROM ascensor_incidencias GROUP BY estado")->fetchAll(PDO::FETCH_ASSOC);
$data_asc = $pdo->query("SELECT a.id_ascensor, a.nombre, COUNT(i.id_incidencia) as cantidad FROM ascensores a JOIN ascensor_incidencias i ON a.id_ascensor = i.id_ascensor GROUP BY a.id_ascensor, a.nombre ORDER BY cantidad DESC")->fetchAll(PDO::FETCH_ASSOC);

$where = "1=1"; $params = [];
if ($filtro_estado) { $where .= " AND i.estado = ?"; $params[] = $filtro_estado; }
if ($filtro_ascensor) { $where .= " AND i.id_ascensor = ?"; $params[] = $filtro_ascensor; }

$stmt = $pdo->prepare("SELECT i.id_incidencia, i.fecha_reporte, i.titulo, i.estado, i.prioridad, a.nombre as ascensor FROM ascensor_incidencias i JOIN ascensores a ON i.id_ascensor = a.id_ascensor WHERE $where ORDER BY i.fecha_reporte DESC LIMIT 100");
$stmt->execute($params); $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/header.php'; ?>
    <title>Analítica Logística</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <style>
        body { background: #f4f6fb; font-family: 'Outfit', sans-serif; color: #2b3445; }
        .ops-layout { max-width: 1400px; margin: 0 auto; padding: 2rem 1rem; }
        
        .dashboard-header { margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-end; }
        .dashboard-header h1 { font-size: 2.5rem; font-weight: 900; background: linear-gradient(135deg, #2b3445, #4f46e5); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 0; letter-spacing: -1px; }
        .dashboard-header p { font-size: 1.1rem; color: #6b7280; margin: 0; font-weight: 500; }
        .btn-neo { border: none; padding: 0.8rem 1.5rem; border-radius: 16px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; transition: 0.2s; cursor: pointer; text-decoration: none; background: #ffffff; color: #111827; border: 1px solid #e5e7eb; }

        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-box { background: #fff; border-radius: 24px; padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 1.5rem; }
        .stat-icon { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; }
        .stat-info h4 { margin: 0; font-size: 2.5rem; font-weight: 900; color: #111827; line-height: 1; }
        .stat-info span { font-size: 0.9rem; color: #6b7280; text-transform: uppercase; font-weight: 800; letter-spacing: 1px; }

        .charts-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; margin-bottom: 2rem; }
        @media (max-width: 992px) { .charts-grid { grid-template-columns: 1fr; } }
        .glass-panel { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.5); border-radius: 24px; padding: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.03); }

        .table-custom { width: 100%; border-collapse: collapse; }
        .table-custom th { text-align: left; padding: 1rem; color: #6b7280; font-weight: 800; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; border-bottom: 2px solid #f3f4f6; }
        .table-custom td { padding: 1rem; border-bottom: 1px solid #f3f4f6; color: #111827; font-weight: 500; }
        .badge-status { padding: 6px 12px; border-radius: 10px; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="ops-layout">
        
        <div class="dashboard-header">
            <div><h1>Inteligencia Operativa</h1><p>Métricas y telemetría de eventos de la flota.</p></div>
            <div class="d-flex gap-2">
                <?php if($filtro_estado || $filtro_ascensor): ?><a href="ascensor_estadisticas.php" class="btn-neo text-danger"><i class="fas fa-times me-2"></i> Limpiar Filtros</a><?php endif; ?>
                <a href="mantenimiento_ascensores.php" class="btn-neo"><i class="fas fa-arrow-left me-2"></i> Nodos</a>
            </div>
        </div>

        <div class="stat-grid">
            <div class="stat-box">
                <div class="stat-icon" style="background:#e0e7ff; color:#4f46e5;"><i class="fas fa-stream"></i></div>
                <div class="stat-info"><h4><?php echo $total_inc; ?></h4><span>Total Histórico</span></div>
            </div>
            <div class="stat-box">
                <div class="stat-icon" style="background:#fee2e2; color:#ef4444;"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-info"><h4 class="text-danger"><?php echo $total_act; ?></h4><span>Abiertas</span></div>
            </div>
            <div class="stat-box">
                <div class="stat-icon" style="background:#d1fae5; color:#10b981;"><i class="fas fa-check-double"></i></div>
                <div class="stat-info"><h4 class="text-success"><?php echo $total_res; ?></h4><span>Cerradas</span></div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="glass-panel d-flex flex-column">
                <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1rem; color: #111827;">Estados Globales</h3>
                <div style="position: relative; height: 300px; flex-grow: 1;"><canvas id="cEstados"></canvas></div>
            </div>
            <div class="glass-panel d-flex flex-column">
                <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1rem; color: #111827;">Fallas por Unidad</h3>
                <div style="position: relative; height: 300px; flex-grow: 1;"><canvas id="cEquipos"></canvas></div>
            </div>
        </div>

        <div class="glass-panel p-0 overflow-hidden">
            <div style="padding: 1.5rem 2rem; border-bottom: 1px solid #f3f4f6;"><h3 style="font-size: 1.1rem; font-weight: 800; margin: 0; color: #111827;">Desglose de Registros</h3></div>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead><tr><th>Fecha</th><th>Nodo</th><th>Observación</th><th>Estado</th></tr></thead>
                    <tbody>
                        <?php if(empty($lista)): ?><tr><td colspan="4" class="text-center py-5 text-muted fw-bold">No hay registros bajo este filtro.</td></tr><?php else: ?>
                        <?php foreach($lista as $i): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($i['fecha_reporte'])); ?></td>
                                <td><span style="background:#f3f4f6; padding: 4px 10px; border-radius:8px;"><i class="fas fa-elevator text-primary me-1"></i> <?php echo htmlspecialchars($i['ascensor']); ?></span></td>
                                <td><?php echo htmlspecialchars($i['titulo']); ?></td>
                                <td><?php 
                                    $e_col = $i['estado'] == 'resuelto' ? 'background:#d1fae5; color:#10b981;' : 'background:#fee2e2; color:#ef4444;';
                                    echo "<span class='badge-status' style='$e_col'>" . strtoupper(str_replace('_',' ',$i['estado'])) . "</span>";
                                ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        const lbls = <?php echo json_encode(array_column($data_estado, 'estado')); ?>;
        const dats = <?php echo json_encode(array_column($data_estado, 'cantidad')); ?>;
        const eLbls = <?php echo json_encode(array_column($data_asc, 'nombre')); ?>;
        const eDats = <?php echo json_encode(array_column($data_asc, 'cantidad')); ?>;
        const eIds = <?php echo json_encode(array_column($data_asc, 'id_ascensor')); ?>;
        const pFont = { family: "'Outfit', sans-serif", weight: 'bold' };

        new Chart(document.getElementById('cEstados'), {
            type: 'doughnut',
            data: { labels: lbls.map(l => l.replace('_', ' ').toUpperCase()), datasets: [{ data: dats, backgroundColor: ['#ef4444', '#f59e0b', '#10b981', '#4f46e5'], borderWidth: 0 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, font: pFont } } }, onClick: (e, els) => { if (els.length) window.location = `ascensor_estadisticas.php?estado=${lbls[els[0].index]}`; } }
        });

        new Chart(document.getElementById('cEquipos'), {
            type: 'bar',
            data: { labels: eLbls, datasets: [{ label: 'Novedades', data: eDats, backgroundColor: '#4f46e5', borderRadius: 8 }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { font: pFont } }, y: { grid: { color: '#f3f4f6' }, ticks: { font: pFont } } }, onClick: (e, els) => { if (els.length) window.location = `ascensor_estadisticas.php?id_ascensor=${eIds[els[0].index]}`; } }
        });
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>


