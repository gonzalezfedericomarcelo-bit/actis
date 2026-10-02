<?php
require_once 'includes/conexion.php';

// Recolección de datos por GET (Desde QR del Kiosco)
$turno_id = isset($_GET['id']) ? $conexion->real_escape_string($_GET['id']) : '';
$modo = isset($_GET['modo']) ? htmlspecialchars($_GET['modo']) : '';
$token = isset($_GET['token']) ? htmlspecialchars($_GET['token']) : '';
$dni = isset($_GET['dni']) ? htmlspecialchars($_GET['dni']) : 'NO ESPECIFICADO';
$nombre = isset($_GET['nombre']) ? htmlspecialchars($_GET['nombre']) : 'PACIENTE NO REGISTRADO';
$servicio = isset($_GET['servicio']) ? htmlspecialchars($_GET['servicio']) : 'ASISTENCIA';
$orden = isset($_GET['orden']) ? htmlspecialchars($_GET['orden']) : '';
$fecha = isset($_GET['fecha']) ? htmlspecialchars($_GET['fecha']) : date('d/m/Y');
$hora = isset($_GET['hora']) ? htmlspecialchars($_GET['hora']) : date('H:i');

// Si el QR viene del sistema de Base de Datos y no del Kiosco directamente
if($turno_id != '') {
    $sql = "SELECT t.*, p.nombre, p.apellido, p.dni FROM turnos t INNER JOIN pacientes p ON t.paciente_id = p.id WHERE t.id = '$turno_id'";
    $res = $conexion->query($sql);
    if($res && $res->num_rows > 0) {
        $t = $res->fetch_assoc();
        $nombre = $t['nombre'] . ' ' . $t['apellido'];
        $dni = $t['dni'];
        $servicio = $t['servicio'];
        $fecha = date("d/m/Y", strtotime($t['fecha_turno']));
        $hora = $t['hora_turno'] ? date("H:i", strtotime($t['hora_turno'])) : '--:--';
        $token = $t['token_iofa'];
        $modo = 'VALIDACION';
        $estado_db = $t['estado'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado Oficial - ACTIS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap');
        body { margin: 0; padding: 0; font-family: 'Inter', sans-serif; background-color: #f1f5f9; color: #1e293b; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
        .cert-container { background: #ffffff; width: 100%; max-width: 500px; margin: 20px auto; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.08); overflow: hidden; position: relative; border: 1px solid #e2e8f0; display: flex; flex-direction: column; }
        
        /* Header */
        .cert-header { background: #0f172a; padding: 25px 20px; text-align: center; position: relative; }
        .cert-header img { height: 70px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.5)); position: relative; z-index: 2; }
        .cert-header h1 { color: #ffffff; margin: 15px 0 0 0; font-size: 1.4rem; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; position: relative; z-index: 2; }
        .cert-header::after { content: ''; position: absolute; bottom: -15px; left: 0; width: 100%; height: 30px; background: #0f172a; transform: skewY(-3deg); z-index: 1; }

        /* Status Badge */
        .status-badge { display: flex; align-items: center; justify-content: center; gap: 10px; background: #22c55e; color: white; padding: 15px; text-align: center; font-weight: 900; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 1px; margin-top: 10px; }
        .status-badge i { font-size: 1.5rem; }
        .status-error { background: #dc2626; }

        /* Body */
        .cert-body { padding: 30px 25px; flex-grow: 1; }
        
        .info-grid { display: grid; grid-template-columns: 1fr; gap: 15px; margin-bottom: 25px; }
        .info-item { background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .info-item.full { grid-column: span 1; }
        .info-label { font-size: 0.75rem; color: #64748b; font-weight: 800; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.5px; }
        .info-value { font-size: 1.1rem; color: #0f172a; font-weight: 800; word-break: break-word; }
        
        /* Highlight Box */
        .highlight-box { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 2px dashed #0284c7; border-radius: 15px; padding: 25px; text-align: center; margin-top: 10px; }
        .highlight-label { font-size: 0.9rem; color: #0369a1; font-weight: 900; text-transform: uppercase; margin-bottom: 10px; letter-spacing: 1px; }
        .highlight-value { font-size: 3rem; color: #0284c7; font-weight: 900; letter-spacing: 3px; line-height: 1; }

        /* Footer */
        .cert-footer { background: #e2e8f0; padding: 20px; text-align: center; border-top: 1px solid #cbd5e1; font-size: 0.85rem; font-weight: 700; color: #475569; margin-top: auto; }
        .cert-footer strong { color: #0284c7; }
    </style>
</head>
<body>

    <div class="cert-container">
        <div class="cert-header">
            <img src="https://federicogonzalez.net/actis/img/osfa.png" alt="Logo">
            <h1>Policlínica General Actis</h1>
        </div>

        <?php if(isset($estado_db) && $estado_db == 'Pendiente'): ?>
            <div class="status-badge status-error"><i class="fa-solid fa-triangle-exclamation"></i> TURNO NO VALIDADO</div>
            <div class="cert-body">
                <p style="text-align:center; font-weight:800; color:#334155; font-size: 1.2rem;">Este turno existe, pero debe validarlo en el kiosco o ventanilla.</p>
            </div>
        <?php else: ?>
            <div class="status-badge"><i class="fa-solid fa-shield-check"></i> CERTIFICADO VÁLIDO</div>
            <div class="cert-body">
                <div class="info-grid">
                    <div class="info-item full">
                        <div class="info-label">Paciente</div>
                        <div class="info-value"><?php echo mb_strtoupper($nombre, 'UTF-8'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">DNI</div>
                        <div class="info-value"><?php echo $dni; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Servicio</div>
                        <div class="info-value"><?php echo mb_strtoupper($servicio, 'UTF-8'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Fecha</div>
                        <div class="info-value"><i class="fa-regular fa-calendar"></i> <?php echo $fecha; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Hora</div>
                        <div class="info-value"><i class="fa-regular fa-clock"></i> <?php echo $hora; ?></div>
                    </div>
                </div>

                <div class="highlight-box">
                    <?php if (strtoupper($modo) === 'ASISTENCIA' || $orden !== ''): ?>
                        <div class="highlight-label">NÚMERO DE ORDEN / LLAMADO</div>
                        <div class="highlight-value" style="color: #16a34a;"><?php echo str_pad($orden, 3, "0", STR_PAD_LEFT); ?></div>
                    <?php elseif (strtoupper($modo) === 'VALIDACION' || !empty($token)): ?>
                        <div class="highlight-label">CÓDIGO DE VALIDACIÓN IOSFA</div>
                        <div class="highlight-value"><?php echo strtoupper($token); ?></div>
                    <?php else: ?>
                        <div class="highlight-label">REGISTRO DE ATENCIÓN</div>
                        <div class="highlight-value" style="font-size: 2rem;">COMPLETADO</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="cert-footer">
            SG Mec Info <strong>Federico GONZÁLEZ</strong><br>
            Enc Info - Policlínica General ACTIS
        </div>
    </div>

</body>
</html>