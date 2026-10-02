<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';

if (!isset($mis_permisos) || !in_array('modulo_seguridad', $mis_permisos)) { 
    echo "<script>window.location='dashboard.php';</script>"; 
    exit; 
}
?>
<style>
    .rondas-container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .reader-box { width: 100%; height: 350px; background: #0f172a; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: center; align-items: center; color: #fff; overflow: hidden; position: relative; }
    .btn-scanner { width: 100%; padding: 15px; font-size: 1.1rem; background: #2563eb; color: #fff; border: none; border-radius: 12px; font-weight: 800; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 10px; }
    .timeline { margin-top: 30px; padding-left: 20px; border-left: 2px solid #e2e8f0; }
    .timeline-item { position: relative; padding-bottom: 20px; }
    .timeline-item::before { content: ''; position: absolute; left: -27px; top: 0; width: 12px; height: 12px; background: #10b981; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 0 2px #10b981; }
    .time-badge { font-size: 0.8rem; font-weight: 800; color: #64748b; background: #f1f5f9; padding: 2px 8px; border-radius: 12px; }
</style>

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

<div class="rondas-container">
    <h2 style="text-align:center; color:#0f172a; margin-top:0;"><i class="fa-solid fa-qrcode"></i> Rondas Nocturnas</h2>
    <p style="text-align:center; color:#64748b; font-size:0.9rem; margin-bottom:25px;">Escanee los códigos QR ubicados en los sectores del edificio.</p>

    <div class="reader-box" id="reader">
        <div id="caja-estado" style="text-align:center;">
            <i class="fa-solid fa-camera" style="font-size:3rem; margin-bottom:10px; opacity:0.5;"></i>
            <p>Cámara apagada</p>
        </div>
    </div>

    <button class="btn-scanner" id="btnStartScan" onclick="iniciarScanner()"><i class="fa-solid fa-expand"></i> Activar Lector QR</button>

    <h3 style="margin-top: 40px; font-size:1.1rem; color:#1e293b;">Registro de Hoy</h3>
    <div class="timeline">
        <?php
        $hoy = date('Y-m-d');
        $q_rondas = $conexion->query("SELECT * FROM seguridad_rondas WHERE DATE(fecha_hora) = '$hoy' ORDER BY id DESC LIMIT 10");
        if($q_rondas && $q_rondas->num_rows > 0) {
            while($r = $q_rondas->fetch_assoc()) {
                echo "<div class='timeline-item'>
                        <span class='time-badge'>".date('H:i', strtotime($r['fecha_hora']))."</span>
                        <p style='margin:5px 0 0 0; font-weight:600; color:#334155;'>Punto Control: ".htmlspecialchars($r['punto_qr'])."</p>
                      </div>";
            }
        } else {
            echo "<p style='color:#94a3b8; font-size:0.9rem;'>No hay rondas registradas hoy.</p>";
        }
        ?>
    </div>
</div>

<script>
    let html5QrCode;

    function iniciarScanner() {
        document.getElementById('btnStartScan').style.display = 'none';
        document.getElementById('caja-estado').style.display = 'none';
        
        // Usamos la API pura para no cargar la interfaz en inglés
        html5QrCode = new Html5Qrcode("reader");
        
        // Forzamos la cámara trasera ("environment")
        html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            (decodedText, decodedResult) => {
                // Éxito en lectura
                html5QrCode.stop().then((ignore) => {
                    document.getElementById('reader').innerHTML = '<div style="color:#10b981; text-align:center; font-weight:bold;"><i class="fa-solid fa-circle-check fa-3x"></i><br><br>Procesando QR...</div>';
                    guardarRonda(decodedText);
                });
            },
            (errorMessage) => { /* Silencioso en errores de frame */ }
        ).catch((err) => {
            mostrarError("No se pudo acceder a la cámara trasera.");
            document.getElementById('btnStartScan').style.display = 'flex';
        });
    }

    function guardarRonda(qr_texto) {
        let formData = new FormData();
        formData.append('punto_qr', qr_texto);

        fetch('api_rondas.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'ok') {
                mostrarExito('Punto Registrado: ' + qr_texto);
                setTimeout(() => location.reload(), 1500);
            } else {
                mostrarError(data.mensaje);
                document.getElementById('btnStartScan').style.display = 'flex';
            }
        })
        .catch(error => {
            mostrarError('Error de conexión.');
        });
    }
</script>
<?php require_once 'includes/footer.php'; ?>
