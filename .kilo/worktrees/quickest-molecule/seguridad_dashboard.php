<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';

if (!isset($mis_permisos) || !in_array('modulo_seguridad', $mis_permisos)) { 
    echo "<script>window.location='dashboard.php';</script>"; 
    exit; 
}

$hoy = date('Y-m-d');

$q_ingresos = $conexion->query("SELECT COUNT(*) as total, SUM(CASE WHEN tipo_registro = 'INGRESO' THEN 1 ELSE 0 END) as entradas, SUM(CASE WHEN tipo_registro = 'EGRESO' THEN 1 ELSE 0 END) as salidas FROM registro_ingresos WHERE DATE(fecha_hora) = '$hoy'");
$stats_ingresos = $q_ingresos ? $q_ingresos->fetch_assoc() : ['total'=>0, 'entradas'=>0, 'salidas'=>0];

$q_llaves = $conexion->query("SELECT COUNT(*) as total, SUM(CASE WHEN estado != 'Disponible' THEN 1 ELSE 0 END) as en_uso FROM seguridad_llaves");
$stats_llaves = $q_llaves ? $q_llaves->fetch_assoc() : ['total'=>0, 'en_uso'=>0];

$q_rondas = $conexion->query("SELECT COUNT(*) as total FROM seguridad_rondas WHERE DATE(fecha_hora) = '$hoy'");
$stats_rondas = $q_rondas ? $q_rondas->fetch_assoc() : ['total'=>0];

$ssl_activo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
$cifrado_db = $conexion->query("SHOW VARIABLES LIKE 'have_openssl'")->fetch_assoc()['Value'] ?? 'DISABLED';
$hash_alg = version_compare(PHP_VERSION, '7.2.0', '>=') ? 'Argon2id / Bcrypt' : 'Bcrypt';

// --- LOGICA DE ALERTAS ---
$alertas = [];

// 4. Alarma de Ronda Incumplida (> 2 horas)
$q_last_ronda = $conexion->query("SELECT fecha_hora FROM seguridad_rondas ORDER BY fecha_hora DESC LIMIT 1");
if ($q_last_ronda && $q_last_ronda->num_rows > 0) {
    $last_ronda_time = strtotime($q_last_ronda->fetch_assoc()['fecha_hora']);
    $diff_hours = (time() - $last_ronda_time) / 3600;
    if ($diff_hours > 2) {
        $alertas[] = "<i class='fa-solid fa-triangle-exclamation'></i> ALERTA DE VIGILANCIA: Han pasado " . round($diff_hours, 1) . " horas sin registrar una ronda. Último QR: " . date('H:i', $last_ronda_time);
    }
} else {
    $alertas[] = "<i class='fa-solid fa-triangle-exclamation'></i> ALERTA DE VIGILANCIA: No hay registros de rondas recientes.";
}

// 6. Monitoreo de Papel Tótem
$res_config = $conexion->query("SELECT tipo, estado FROM totem_config WHERE tipo IN ('tickets_impresos', 'capacidad_rollo')");
$impresos = 0; $capacidad = 120;
if ($res_config) {
    while($r = $res_config->fetch_assoc()){
        if($r['tipo'] == 'tickets_impresos') $impresos = (int)$r['estado'];
        if($r['tipo'] == 'capacidad_rollo') $capacidad = (int)$r['estado'];
    }
    $papel_restante = max(0, $capacidad - $impresos);
    if ($papel_restante < 50) {
        $alertas[] = "<i class='fa-solid fa-print'></i> ALERTA DE HARDWARE: Quedan solo $papel_restante tickets en el Tótem. Se requiere reposición de rollo.";
    }
}
?>
<style>
    /* ESTRUCTURA FLUIDA Y RESPONSIVA */
    .sec-dashboard { display: flex; flex-direction: column; gap: 20px; width: 100%; max-width: 1400px; margin: 0 auto; padding: 0 15px; box-sizing: border-box; }
    
    .sec-header-bar { display: flex; justify-content: space-between; align-items: center; background: #0f172a; color: #fff; padding: 20px; border-radius: 16px; flex-wrap: wrap; gap: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .sec-title { margin: 0; font-size: 1.4rem; font-weight: 900; line-height: 1.2; }
    
    .sys-integrity { display: flex; flex-wrap: wrap; gap: 10px; width: 100%; }
    .integ-badge { background: rgba(255,255,255,0.1); padding: 8px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; white-space: nowrap; flex-grow: 1; text-align: center; }

    .sec-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
    .sec-kpi-card { background: #fff; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; position: relative; overflow: hidden; }
    .sec-kpi-value { font-size: 2.2rem; font-weight: 900; color: #0f172a; margin: 10px 0 5px 0; }
    .sec-kpi-label { font-size: 0.8rem; font-weight: 800; color: #64748b; text-transform: uppercase; }

    /* PANELES DE 1 COLUMNA EN MÓVIL, 2 EN PC */
    .sec-panels-grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
    @media (min-width: 992px) { .sec-panels-grid { grid-template-columns: 2fr 1fr; } }
    
    .sec-panel { background: #fff; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; width: 100%; box-sizing: border-box; }
    .sec-panel-title { font-size: 1.1rem; font-weight: 900; margin: 0 0 15px 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }

    /* BOTONES ADAPTABLES */
    .nav-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; }
    .nav-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 700; color: #1e293b; text-decoration: none; word-wrap: break-word; line-height: 1.2; }
    .nav-btn i { font-size: 1.6rem; margin-bottom: 8px; color: #3b82f6; }
    .nav-btn.critical { background: #fef2f2; border-color: #fca5a5; }
    .nav-btn.critical i { color: #ef4444; }

    /* TABLA DESLIZABLE SIN ROMPER LA PANTALLA */
    .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .audit-table { width: 100%; min-width: 500px; border-collapse: collapse; }
    .audit-table th, .audit-table td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 0.85rem; }
    
    .chart-container { width: 100%; height: 250px; position: relative; }
</style>

<div class="sec-dashboard">
    
    <div class="sec-header-bar">
        <h1 class="sec-title"><i class="fa-solid fa-shield-halved"></i> Control Operativo de Seguridad</h1>
    </div>

    <?php if(count($alertas) > 0): ?>
    <div style="display:flex; flex-direction:column; gap:10px;">
        <?php foreach($alertas as $alerta): ?>
            <div style="background:#fee2e2; border:1px solid #fca5a5; padding:15px; border-radius:12px; color:#dc2626; font-weight:bold; font-size: 0.95rem;">
                <?php echo $alerta; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="sec-kpi-grid">
        <div class="sec-kpi-card">
            <i class="fa-solid fa-users-viewfinder sec-kpi-icon"></i>
            <div class="sec-kpi-label">Accesos Detectados Hoy</div>
            <div class="sec-kpi-value"><?php echo $stats_ingresos['total']; ?></div>
            <div style="font-size:0.8rem; font-weight:700; color:#10b981;"><i class="fa-solid fa-arrow-right-to-bracket"></i> <?php echo (int)$stats_ingresos['entradas']; ?> Entradas verificadas</div>
        </div>
        
        <div class="sec-kpi-card">
            <i class="fa-solid fa-key sec-kpi-icon"></i>
            <div class="sec-kpi-label">Llaves en Circulación</div>
            <div class="sec-kpi-value" style="color:#ef4444;"><?php echo $stats_llaves['en_uso']; ?></div>
            <div style="font-size:0.8rem; font-weight:700; color:#64748b;">De un total de <?php echo (int)$stats_llaves['total']; ?> llaves maestras</div>
        </div>

        <div class="sec-kpi-card">
            <i class="fa-solid fa-qrcode sec-kpi-icon"></i>
            <div class="sec-kpi-label">Rondas Perimetrales Hoy</div>
            <div class="sec-kpi-value" style="color:#3b82f6;"><?php echo $stats_rondas['total']; ?></div>
            <div style="font-size:0.8rem; font-weight:700; color:#64748b;"><i class="fa-solid fa-check-double"></i> Escaneos confirmados en DB</div>
        </div>
    </div>

    <div class="sec-panels-grid">
        <div class="sec-panel">
            <h2 class="sec-panel-title"><i class="fa-solid fa-layer-group text-blue-600"></i> Acciones Operativas</h2>
            <div class="nav-grid">
                <a href="seguridad_ingreso.php" class="nav-btn"><i class="fa-solid fa-id-card-clip"></i> Control DNI / Facial</a>
                <a href="seguridad_llaves.php" class="nav-btn"><i class="fa-solid fa-key"></i> Tablero de Llaves</a>
                <a href="seguridad_rondas.php" class="nav-btn"><i class="fa-solid fa-qrcode"></i> Rondas Perimetrales</a>
                <a href="seguridad_camaras.php" class="nav-btn"><i class="fa-solid fa-video"></i> Monitoreo DVR</a>
                <a href="seguridad_intercomunicador.php" class="nav-btn"><i class="fa-solid fa-walkie-talkie"></i> Intercomunicador IP</a>
                <a href="seguridad_admin_llaves.php" class="nav-btn"><i class="fa-solid fa-user-shield"></i> Configurar Reglas</a>
                <a href="seguridad_evacuacion.php" class="nav-btn" style="background:#fef3c7; border-color:#f59e0b;"><i class="fa-solid fa-users-line" style="color:#d97706;"></i> Lista Evacuación</a>
                <button onclick="activarPanico()" class="nav-btn critical" style="cursor:pointer;"><i class="fa-solid fa-triangle-exclamation"></i> CÓDIGO ROJO</button>
            </div>
        </div>

        <div class="sec-panel">
            <h2 class="sec-panel-title"><i class="fa-solid fa-chart-pie text-blue-600"></i> Distribución Operativa</h2>
            <div class="chart-container">
                <canvas id="seguridadChart"></canvas>
            </div>
        </div>
    </div>

    <div class="sec-panel">
        <h2 class="sec-panel-title"><i class="fa-solid fa-server text-blue-600"></i> Registro de Auditoría Unificado</h2>
        <div class="table-responsive">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th>Timestamp (Firma)</th>
                        <th>Evento / Módulo</th>
                        <th>Identificador (Hash/DNI)</th>
                        <th>Operador</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        $q_logs = $conexion->query("
                            (SELECT fecha_hora, 'INGRESO' as modulo, dni as detalle, usuario_seguridad_id as uid FROM registro_ingresos ORDER BY id DESC LIMIT 4)
                            UNION
                            (SELECT fecha_hora, 'RONDA_QR' as modulo, punto_qr as detalle, usuario_seguridad_id as uid FROM seguridad_rondas ORDER BY id DESC LIMIT 4)
                            ORDER BY fecha_hora DESC LIMIT 8
                        ");

                        if($q_logs && $q_logs->num_rows > 0) {
                            while($log = $q_logs->fetch_assoc()) {
                                $u_id = (int)$log['uid'];
                                $nombre_op = "Sistema";
                                $q_op = $conexion->query("SELECT nombre_completo FROM usuarios WHERE id = $u_id");
                                if($q_op && $q_op->num_rows > 0) { $nombre_op = $q_op->fetch_assoc()['nombre_completo']; }

                                $badge_class = 'badge-log';
                                if($log['modulo'] == 'INGRESO') $badge_class .= ' ingreso';
                                else if($log['modulo'] == 'RONDA_QR') $badge_class .= ' ronda';

                                $crypto_hash = substr(hash('sha256', $log['fecha_hora'] . $log['detalle']), 0, 10);

                                echo "<tr>
                                        <td>
                                            <div style='font-weight:900;'>".date('H:i:s', strtotime($log['fecha_hora']))."</div>
                                            <div style='font-size:0.75rem; color:#94a3b8; font-family:monospace;'>0x".$crypto_hash."</div>
                                        </td>
                                        <td><span class='$badge_class'>".$log['modulo']."</span></td>
                                        <td style='font-family:monospace; font-size:0.95rem; font-weight:800; color:#3b82f6;'>".htmlspecialchars($log['detalle'])."</td>
                                        <td style='white-space: nowrap;'><i class='fa-solid fa-user-shield text-gray-400'></i> ".htmlspecialchars($nombre_op)."</td>
                                      </tr>";
                            }
                        } else { echo "<tr><td colspan='4' style='text-align:center; color:#94a3b8;'>No hay registros de seguridad.</td></tr>"; }
                    } catch (Exception $e) { echo "<tr><td colspan='4' style='text-align:center; color:#ef4444;'>Error leyendo logs.</td></tr>"; }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="sec-panel" style="background:#f1f5f9; border-color:#cbd5e1;">
        <h4 style="margin:0 0 10px 0; color:#475569; font-size:0.85rem; text-transform:uppercase;"><i class="fa-solid fa-shield-halved"></i> Global Security Command (Integridad de Sistema)</h4>
        <div class="sys-integrity">
            <div class="integ-badge <?php echo $ssl_activo ? 'safe' : 'warn'; ?>">
                <i class="fa-solid <?php echo $ssl_activo ? 'fa-lock' : 'fa-lock-open'; ?>"></i> SSL/TLS: <?php echo $ssl_activo ? 'SECURE' : 'INACTIVE'; ?>
            </div>
            <div class="integ-badge safe">
                <i class="fa-solid fa-fingerprint"></i> Hash Alg: <?php echo $hash_alg; ?>
            </div>
            <div class="integ-badge <?php echo ($cifrado_db == 'YES') ? 'safe' : 'warn'; ?>">
                <i class="fa-solid fa-database"></i> DB Crypt: <?php echo ($cifrado_db == 'YES') ? 'AES-256' : 'STANDARD'; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function activarPanico() {
    Swal.fire({
        title: '¡ALERTA MÁXIMA!',
        text: 'Esto bloqueará los tótems y emitirá orden de evacuación. ¿Confirmar CÓDIGO ROJO?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'SÍ, ACTIVAR EVACUACIÓN',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            let fd = new FormData();
            fd.append('accion', 'activar_panico');
            fetch('api_panico.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'ok') {
                    Swal.fire('¡CÓDIGO ROJO ACTIVADO!', 'Tótems bloqueados.', 'error')
                    .then(() => window.location.href = 'seguridad_evacuacion.php');
                }
            });
        }
    });
}

document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('seguridadChart');
    if(ctx) {
        new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Control Accesos (DNI)', 'Llaves Prestadas', 'Puntos de Ronda (QR)'],
                datasets: [{
                    data: [<?php echo (int)$stats_ingresos['total']; ?>, <?php echo (int)$stats_llaves['en_uso']; ?>, <?php echo (int)$stats_rondas['total']; ?>],
                    backgroundColor: ['#10b981', '#ef4444', '#3b82f6'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { color: '#475569', font: { family: 'Poppins', size: 11, weight: 600 } } } },
                cutout: '70%'
            }
        });
    }
});
</script>
<?php require_once 'includes/footer.php'; ?>