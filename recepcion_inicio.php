<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';

$paso = 1;
$dni_busqueda = '';
$error_msg = '';
$paciente_info = null;
$turnos_hoy = [];

if (isset($_GET['dni']) && !empty($_GET['dni'])) {
    $dni_busqueda = $conexion->real_escape_string($_GET['dni']);
    
    // Buscar paciente y sus turnos pendientes de HOY
    $sql = "SELECT t.id as turno_id, t.servicio, t.hora_turno, p.nombre, p.apellido, p.dni 
            FROM turnos t 
            INNER JOIN pacientes p ON t.paciente_id = p.id 
            WHERE p.dni = '$dni_busqueda' 
            AND t.fecha_turno = CURDATE() 
            AND t.estado NOT IN ('Presente', 'Atendido', 'Cancelado', 'Finalizado')";
            
    $res = $conexion->query($sql);
    if ($res && $res->num_rows > 0) {
        $paso = 2;
        while($row = $res->fetch_assoc()) {
            if (!$paciente_info) {
                $paciente_info = ['nombre' => $row['nombre'], 'apellido' => $row['apellido'], 'dni' => $row['dni']];
            }
            $turnos_hoy[] = $row;
        }
    } else {
        $error_msg = "El paciente con DNI $dni_busqueda no tiene turnos pendientes para el día de hoy.";
    }
}
?>

<style>
    .rec-container { display: flex; align-items: stretch; justify-content: center; gap: 20px; min-height: calc(100vh - 200px); max-width: 1200px; margin: 0 auto; flex-wrap: wrap; font-family: 'Poppins', sans-serif; padding: 15px; }
    
    .rec-main-card { flex: 2; min-width: 320px; background: #ffffff; border-radius: 20px; padding: 40px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; text-align: center; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
    
    .rec-side-panel { flex: 1; min-width: 280px; background: #f8fafc; border-radius: 20px; padding: 30px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 15px; }

    /* Animación del Radar/Escáner Adaptada al Color Institucional */
    .radar-box { position: relative; width: 120px; height: 120px; border: 4px solid rgba(20, 73, 115, 0.2); border-radius: 50%; margin: 0 auto 25px auto; display: flex; justify-content: center; align-items: center; overflow: hidden; background: #f1f5f9; box-shadow: inset 0 0 15px rgba(20, 73, 115, 0.05); }
    .radar-box i { font-size: 4rem; color: #144973; opacity: 0.9; z-index: 2; }
    .radar-sweep { position: absolute; top: 50%; left: 50%; width: 100px; height: 100px; background: conic-gradient(rgba(20, 73, 115, 0.6) 0deg, transparent 90deg); transform-origin: top left; animation: radarSpin 2s linear infinite; z-index: 1; }
    @keyframes radarSpin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

    /* Input Gigante Flat */
    .rec-input-wrapper { position: relative; margin-bottom: 25px; }
    .rec-input { width: 100%; height: 90px; border-radius: 16px; border: 2px solid #cbd5e1; background: #f8fafc; font-size: 3rem; font-weight: 900; color: #144973; text-align: center; letter-spacing: 4px; outline: none; transition: all 0.3s; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
    .rec-input:focus { border-color: #144973; background: #ffffff; box-shadow: 0 0 0 4px rgba(20, 73, 115, 0.1); }
    .rec-input::placeholder { color: #94a3b8; font-weight: 700; letter-spacing: 1px; font-size: 1.8rem; }

    /* Info Status */
    .status-item { display: flex; align-items: center; justify-content: space-between; padding: 15px; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 0.95rem; font-weight: 700; color: #475569; }
    .status-item i { font-size: 1.2rem; }
    
    .status-badge-blue { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; color: white; background: #144973; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; animation: pulse-blue 2s infinite; }
    @keyframes pulse-blue { 0% { box-shadow: 0 0 0 0 rgba(20, 73, 115, 0.4); } 70% { box-shadow: 0 0 0 8px rgba(20, 73, 115, 0); } 100% { box-shadow: 0 0 0 0 rgba(20, 73, 115, 0); } }

    .btn-main { width: 100%; font-size: 1.3rem; font-weight: 900; padding: 20px; border-radius: 16px; background: #144973; color: white; border: none; cursor: pointer; transition: transform 0.2s ease; text-transform: uppercase; display: flex; justify-content: center; align-items: center; gap: 10px; font-family: 'Poppins', sans-serif; }
    .btn-main:active { transform: scale(0.98); }
    .btn-success { background: #10b981; }
    .btn-back { background: #e2e8f0; color: #475569; text-decoration: none; }
</style>

<div class="rec-container">
    <div class="rec-main-card">
        
        <?php if($error_msg): ?>
            <div style="background: #fef2f2; color: #dc2626; padding: 15px; border-radius: 12px; font-weight: 800; margin-bottom: 20px; border: 1px solid #fecaca; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <i class="fa-solid fa-triangle-exclamation"></i> <?= $error_msg ?>
            </div>
        <?php endif; ?>

        <?php if ($paso == 1): ?>
            <div class="radar-box">
                <div class="radar-sweep"></div>
                <i class="fa-solid fa-users-viewfinder"></i>
            </div>
            
            <h2 style="font-size: 2.2rem; font-weight: 900; color: #0f172a; margin-bottom: 5px; letter-spacing: -1px;">Recepción y Asistencia</h2>
            <p style="font-size: 1rem; color: #64748b; font-weight: 600; margin-bottom: 30px;">Ingrese el DNI del paciente para verificar su turno de hoy.</p>
            
            <form action="recepcion_inicio.php" method="GET">
                <div class="rec-input-wrapper">
                    <input type="text" name="dni" class="rec-input" placeholder="DNI DEL PACIENTE" required autofocus autocomplete="off" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                </div>
                
                <button type="submit" class="btn-main">
                    <i class="fa-solid fa-magnifying-glass"></i> Buscar Paciente
                </button>
            </form>

        <?php elseif ($paso == 2): ?>
            <div style="margin-bottom: 30px;">
                <i class="fa-solid fa-user-check" style="font-size: 3.5rem; color: #10b981; margin-bottom: 15px;"></i>
                <h2 style="font-size: 1.8rem; font-weight: 900; color: #0f172a; margin: 0; text-transform: uppercase;"><?= htmlspecialchars($paciente_info['nombre'] . ' ' . $paciente_info['apellido']) ?></h2>
                <span style="display:inline-block; margin-top:8px; background: #f1f5f9; border: 1px solid #e2e8f0; padding: 6px 16px; border-radius: 20px; font-weight:800; color: #475569; font-size: 1rem;">DNI: <?= htmlspecialchars($paciente_info['dni']) ?></span>
            </div>

            <form action="recepcion_procesar.php" method="POST">
                <?php if (count($turnos_hoy) == 1): ?>
                    <input type="hidden" name="turno_id" value="<?= $turnos_hoy[0]['turno_id'] ?>">
                    <div style="margin-bottom: 20px; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 15px; border-radius: 12px; color: #065f46; font-weight: 800; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="fa-solid fa-stethoscope"></i> <?= htmlspecialchars($turnos_hoy[0]['servicio']) ?> (<?= date('H:i', strtotime($turnos_hoy[0]['hora_turno'])) ?> hs)
                    </div>
                <?php else: ?>
                    <div style="margin-bottom: 20px; text-align: left; background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <label style="font-weight:800; color:#475569; display:block; margin-bottom:12px; font-size: 0.95rem; text-transform: uppercase;">Seleccione el turno a procesar:</label>
                        <?php foreach($turnos_hoy as $idx => $t): ?>
                            <label style="display:flex; align-items:center; gap:12px; margin-bottom:10px; cursor:pointer; font-size: 1.1rem; font-weight: 800; color: #1e293b; background: white; padding: 10px; border-radius: 10px; border: 1px solid #e2e8f0;">
                                <input type="radio" name="turno_id" value="<?= $t['turno_id'] ?>" <?= $idx == 0 ? 'checked' : '' ?> style="width:20px; height:20px; accent-color: #144973;">
                                <?= htmlspecialchars($t['servicio']) ?> <span style="color:#64748b; font-size:0.95rem; font-weight: 700;">(<?= date('H:i', strtotime($t['hora_turno'])) ?> hs)</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="rec-input-wrapper">
                    <input type="text" name="token_validacion" class="rec-input" placeholder="CÓDIGO DE VALIDACIÓN" required autofocus autocomplete="off" style="text-transform: uppercase;">
                </div>
                
                <div style="display: flex; gap: 15px;">
                    <a href="recepcion_inicio.php" class="btn-main btn-back" style="flex: 1;"><i class="fa-solid fa-arrow-left"></i> Volver</a>
                    
                    <button type="submit" class="btn-main btn-success" style="flex: 2;">
                        <i class="fa-solid fa-check-to-slot"></i> Registrar Presente
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="rec-side-panel">
        <h3 style="font-size: 1.1rem; font-weight: 900; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 8px; padding-bottom: 15px; border-bottom: 2px solid #e2e8f0;">
            <i class="fa-solid fa-clipboard-list" style="color: #144973;"></i> Monitor de Recepción
        </h3>
        
        <div class="status-item">
            <span style="display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-calendar-check" style="color:#94a3b8;"></i> Módulo Turnos</span>
            <span class="status-badge-blue">EN LÍNEA</span>
        </div>
        <div class="status-item">
            <span style="display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-users" style="color:#94a3b8;"></i> Base de Datos</span>
            <span class="status-badge-blue" style="background:#10b981; animation:none;">CONECTADA</span>
        </div>
        <div class="status-item">
            <span style="display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-qrcode" style="color:#94a3b8;"></i> Lector Óptico</span>
            <span style="font-size: 0.8rem; color: #144973; font-weight: 900; background: #e0f2fe; padding: 4px 8px; border-radius: 6px;">ACTIVO</span>
        </div>

        <div style="margin-top: auto; padding: 15px; background: #fffbeb; border-radius: 12px; border: 1px dashed #fcd34d; text-align: center;">
            <i class="fa-solid fa-shield-halved" style="font-size: 2rem; color: #f59e0b; margin-bottom: 8px;"></i>
            <p style="margin: 0; font-size: 0.85rem; font-weight: 700; color: #92400e; line-height: 1.4;">Al verificar el código, el paciente figurará inmediatamente como PRESENTE en el consultorio.</p>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>