<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';

if (!isset($mis_permisos) || !in_array('modulo_seguridad', $mis_permisos)) { 
    echo "<script>window.location='dashboard.php';</script>"; 
    exit; 
}

$msg_exito = '';
$msg_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['dni_escaneado'])) {
    $dni = trim($_POST['dni_escaneado']);
    $motivo = isset($_POST['motivo_ingreso']) ? $conexion->real_escape_string($_POST['motivo_ingreso']) : 'Ingreso Peatonal';
    $foto_b64 = isset($_POST['foto_base64']) ? $conexion->real_escape_string($_POST['foto_base64']) : '';
    $alerta_no_citado = 0;
    
    // ALGORITMO ANTI-BASURA: Solo números, entre 7 y 10 dígitos (Latam)
    if (!preg_match('/^[0-9]{7,10}$/', $dni)) {
        $msg_error = "DNI Inválido: Solo se permiten números (sin puntos, letras ni espacios).";
    } else {
        $dni_seguro = $conexion->real_escape_string($dni);
        $uid = (int)$_SESSION['usuario_id'];
        
        // 2. Alarma de "Paciente No Citado"
        if ($motivo == 'Ingreso Peatonal') {
            $hoy = date('Y-m-d');
            $q_turno = $conexion->query("SELECT id FROM turnos WHERE documento_paciente = '$dni_seguro' AND fecha_turno = '$hoy'");
            if ($q_turno && $q_turno->num_rows == 0) {
                $alerta_no_citado = 1;
                $msg_error = "ALERTA AMARILLA: El DNI " . number_format($dni_seguro, 0, '', '.') . " no tiene turnos programados para hoy.";
            }
        }
        
        // Intentar guardar con las nuevas columnas. Si falla, intentar guardar sin ellas por si no corrieron el ALTER TABLE.
        $sql = "INSERT INTO registro_ingresos (dni, motivo, derivado_a, foto_base64, alerta_no_citado, usuario_seguridad_id) VALUES ('$dni_seguro', '$motivo', 'Recepción', '$foto_b64', $alerta_no_citado, $uid)";
        $result = $conexion->query($sql);
        
        if(!$result) {
            // Fallback si no están creadas las columnas en DB
            $sql_fallback = "INSERT INTO registro_ingresos (dni, motivo, derivado_a, usuario_seguridad_id) VALUES ('$dni_seguro', '$motivo', 'Recepción', $uid)";
            $result = $conexion->query($sql_fallback);
        }

        if($result) {
            $msg_exito = "Ingreso registrado: " . number_format($dni_seguro, 0, '', '.');
        } else {
            $msg_error = "Error DB: " . $conexion->error;
        }
    }
}
?>
<style>
    .seg-container { display: grid; grid-template-columns: 1fr 350px; gap: 20px; }
    .seg-card { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
    .escaner-box { text-align: center; padding: 40px 20px; background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; margin-bottom: 20px; }
    .escaner-input { width: 100%; padding: 15px; font-size: 1.2rem; border: 2px solid #3b82f6; border-radius: 10px; text-align: center; font-weight: bold; outline: none; transition: box-shadow 0.2s; }
    .escaner-input:focus { box-shadow: 0 0 0 4px rgba(59,130,246,0.2); }
    .webcam-box { width: 100%; height: 260px; background: #0f172a; border-radius: 12px; overflow: hidden; position: relative; margin-bottom: 15px; }
    #video { width: 100%; height: 100%; object-fit: cover; }
    .btn-action { width: 100%; padding: 12px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; }
    .btn-blue { background: #2563eb; color: #fff; }
    .btn-blue:hover { background: #1d4ed8; }
    .historial-list { list-style: none; padding: 0; margin: 0; }
    .historial-item { padding: 12px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; }
    @media (max-width: 992px) { .seg-container { grid-template-columns: 1fr; } }
</style>

<div class="seg-container">
    <div class="seg-card">
        <h2 style="margin-top:0; color:#0f172a; font-size:1.3rem;"><i class="fa-solid fa-barcode text-blue-600"></i> Escáner de Acceso</h2>
        <p style="color:#64748b; font-size:0.9rem;">El campo de texto debe estar seleccionado para leer el DNI.</p>
        
        <form method="POST" id="formIngreso">
            <div style="margin-bottom: 20px;">
                <label style="font-weight: 800; color:#64748b; font-size:0.85rem; text-transform:uppercase; margin-bottom:10px; display:block;">Motivo del Ingreso</label>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <label style="flex:1; background:#f8fafc; padding:10px; border-radius:8px; border:1px solid #cbd5e1; cursor:pointer; font-weight:700; text-align:center;">
                        <input type="radio" name="motivo_ingreso" value="Ingreso Peatonal" checked style="accent-color:#2563eb;"> Peatonal / Paciente
                    </label>
                    <label style="flex:1; background:#f8fafc; padding:10px; border-radius:8px; border:1px solid #cbd5e1; cursor:pointer; font-weight:700; text-align:center;">
                        <input type="radio" name="motivo_ingreso" value="Proveedor / Mantenimiento" style="accent-color:#2563eb;"> Proveedor
                    </label>
                    <label style="flex:1; background:#f8fafc; padding:10px; border-radius:8px; border:1px solid #cbd5e1; cursor:pointer; font-weight:700; text-align:center;">
                        <input type="radio" name="motivo_ingreso" value="Visita Autorizada" style="accent-color:#2563eb;"> Visita / Familiar
                    </label>
                </div>
            </div>

            <div class="escaner-box">
                <i class="fa-solid fa-id-card" style="font-size:3rem; color:#94a3b8; margin-bottom:15px;"></i>
                <input type="number" inputmode="numeric" pattern="[0-9]*" name="dni_escaneado" id="dni_escaneado" class="escaner-input" placeholder="Esperando lectura de DNI..." autofocus autocomplete="off" required>
            </div>
            
            <input type="hidden" name="foto_base64" id="foto_base64" value="">
            <button type="submit" class="btn-action btn-blue" style="display:none;" id="btnSubmit">Registrar Ingreso</button>
        </form>

        <div style="margin-top: 30px;">
            <h3 style="font-size:1rem; color:#1e293b; border-bottom:2px solid #f1f5f9; padding-bottom:10px;">Últimos Ingresos</h3>
            <ul class="historial-list">
                <?php
                $q_ultimos = $conexion->query("SELECT dni, fecha_hora FROM registro_ingresos ORDER BY id DESC LIMIT 5");
                if($q_ultimos) {
                    while($u = $q_ultimos->fetch_assoc()){
                        echo "<li class='historial-item'><span><i class='fa-solid fa-user text-gray-400'></i> DNI: " . htmlspecialchars($u['dni'] ?? '') . "</span> <span style='color:#64748b; font-size:0.8rem;'>".date('H:i', strtotime($u['fecha_hora']))."</span></li>";
                    }
                } else {
                    echo "<li class='historial-item' style='color:#ef4444;'>Falta ejecutar SQL de columnas nuevas.</li>";
                }
                ?>
            </ul>
        </div>
    </div>

    <div class="seg-card">
        <h2 style="margin-top:0; color:#0f172a; font-size:1.1rem;"><i class="fa-solid fa-camera"></i> Captura Facial</h2>
        <div class="webcam-box">
            <video id="video" autoplay muted></video>
        </div>
        <button type="button" class="btn-action btn-blue" id="btnCapturar"><i class="fa-solid fa-camera-retro"></i> Forzar Captura Manual</button>
        <canvas id="canvas" style="display:none;"></canvas>
    </div>
</div>

<script>
    <?php if($msg_exito != ''): ?>
        mostrarExito('<?php echo $msg_exito; ?>');
    <?php endif; ?>
    <?php if($msg_error != ''): ?>
        mostrarError('<?php echo addslashes($msg_error); ?>');
    <?php endif; ?>

    document.addEventListener('click', function(e) { if(e.target.id !== 'dni_escaneado' && e.target.type !== 'radio') document.getElementById('dni_escaneado').focus(); });
    
    document.getElementById('dni_escaneado').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            capturarFotoSilenciosa();
            document.getElementById('formIngreso').submit();
        }
    });

    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');

    navigator.mediaDevices.getUserMedia({ video: true }).then(stream => {
        video.srcObject = stream;
    }).catch(err => {
        console.error("Error webcam: ", err);
    });

    function capturarFotoSilenciosa() {
        if (video.videoWidth > 0) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            document.getElementById('foto_base64').value = canvas.toDataURL('image/jpeg', 0.6); // Comprimido
        }
    }

    document.getElementById('btnCapturar').addEventListener('click', function() {
        capturarFotoSilenciosa();
        mostrarExito('Captura facial forzada registrada en caché.');
        document.getElementById('dni_escaneado').focus();
    });
</script>
<?php require_once 'includes/footer.php'; ?>
