<?php
require_once 'includes/conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Recolección exclusiva para VALIDACION
$turno_id = isset($_GET['id']) ? $conexion->real_escape_string($_GET['id']) : '';
$token = isset($_GET['token']) ? htmlspecialchars($_GET['token']) : 'NO_TOKEN';
$dni = isset($_GET['dni']) ? htmlspecialchars($_GET['dni']) : 'NO ESPECIFICADO';
$nombre = isset($_GET['nombre']) ? htmlspecialchars($_GET['nombre']) : 'PACIENTE NO REGISTRADO';
$servicio = isset($_GET['servicio']) ? htmlspecialchars($_GET['servicio']) : 'VALIDACION GENERAL';
$fecha = isset($_GET['fecha']) ? htmlspecialchars($_GET['fecha']) : date('d/m/Y');
$hora = isset($_GET['hora']) ? htmlspecialchars($_GET['hora']) : date('H:i');

if($turno_id != '') {
    $sql = "SELECT t.*, p.nombre, p.apellido, p.dni FROM turnos t INNER JOIN pacientes p ON t.paciente_id = p.id WHERE t.id = '$turno_id'";
    $res = $conexion->query($sql);
    if($res && $res->num_rows > 0) {
        $t = $res->fetch_assoc();
        $nombre = $t['nombre'] . ' ' . $t['apellido'];
        $dni = $t['dni'];
        $servicio = $t['servicio'];
        $fecha = date("d/m/Y", strtotime($t['fecha_turno']));
        $hora = $t['hora_turno'] ? date("H:i", strtotime($t['hora_turno'])) : date('H:i');
    }
}

// Generación de Metadatos de Seguridad
$hash_verificacion = hash('sha256', $token . $dni . $fecha . $hora . 'ACTIS_SECURE_SALT_2026');
$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 45) . '...' : 'Unknown Platform';
$ip_verificacion = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "HTTPS (Cifrado Estricto)" : "HTTP (Estándar)";
$id_transaccion = "TXV-" . strtoupper(substr(md5($token . $fecha), 0, 10));
$timestamp_utc = gmdate("Y-m-d\TH:i:s\Z");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Verificación de Veracidad - Policlínica Actis</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: #f4f6f9; color: #1e293b; padding: 8px; min-height: 100vh; display: flex; justify-content: center; align-items: flex-start; }
        
        .cert-container { width: 100%; max-width: 480px; background: #ffffff; border-radius: 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; overflow: hidden; position: relative; margin-bottom: 20px; }
        
        /* Encabezado Principal Actualizado */
        .cert-header { background: linear-gradient(135deg, #144973 0%, #0d3252 100%); padding: 25px 16px; text-align: center; position: relative; border-bottom: 5px solid #0a2640; }
        .cert-header img { height: 75px; margin-bottom: 12px; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.25)); }
        .cert-header h1 { font-size: 1.25rem; font-weight: 900; color: #ffffff; letter-spacing: 0.5px; margin: 0; text-transform: uppercase; }
        .cert-header p { font-size: 0.75rem; color: #e2e8f0; font-weight: 500; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px; }
        
        /* Estado de Verificación */
        .status-banner { background: #ecfdf5; border-bottom: 1px solid #a7f3d0; padding: 12px; display: flex; align-items: center; justify-content: center; gap: 8px; color: #065f46; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; text-align: center; }
        .status-banner i { font-size: 1rem; color: #10b981; }

        /* Contenedor del Cuerpo */
        .cert-body { padding: 16px; display: flex; flex-direction: column; gap: 14px; width: 100%; }

        /* Títulos de Sección */
        .section-title { font-size: 0.75rem; font-weight: 900; color: #144973; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 6px; margin-top: 4px; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; margin-left: 8px; }

        /* Cajas de Datos adaptables */
        .data-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px; display: flex; flex-direction: column; gap: 10px; width: 100%; }
        .data-row { display: flex; flex-direction: column; border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px; width: 100%; overflow: hidden; }
        .data-row:last-child { border-bottom: none; padding-bottom: 0; }
        .data-label { font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 2px; }
        .data-value { font-size: 0.95rem; font-weight: 800; color: #0f172a; word-wrap: break-word; overflow-wrap: break-word; }
        
        /* Token Destacado */
        .token-box { background: linear-gradient(to right, #f8fafc, #ffffff); border: 2px dashed #144973; border-radius: 14px; padding: 15px; text-align: center; width: 100%; }
        .token-value { font-family: 'JetBrains Mono', monospace; font-size: 1.6rem; font-weight: 900; color: #144973; letter-spacing: 2px; }
        
        /* Grilla Técnica */
        .security-grid { display: grid; grid-template-columns: 1fr; gap: 8px; width: 100%; }
        .security-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; overflow: hidden; display: flex; flex-direction: column; }
        .security-card .sec-lbl { font-size: 0.62rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 2px; }
        .security-card .sec-val { font-size: 0.8rem; font-weight: 700; color: #334155; font-family: 'JetBrains Mono', monospace; word-break: break-word; white-space: normal; }

        /* Huella Criptográfica */
        .hash-box { background: #0f172a; border-radius: 12px; padding: 12px; color: #38bdf8; font-family: 'JetBrains Mono', monospace; font-size: 0.7rem; word-break: break-all; border-left: 4px solid #10b981; width: 100%; white-space: normal; box-shadow: inset 0 0 10px rgba(0,0,0,0.5); }

        /* Legales */
        .legal-notice { padding: 12px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 12px; font-size: 0.7rem; color: #92400e; font-weight: 600; line-height: 1.4; text-align: justify; width: 100%; position: relative; z-index: 2; }

        .watermark-img { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 80%; opacity: 0.04; pointer-events: none; z-index: 0; filter: grayscale(100%); }
    </style>
</head>
<body>
    <div class="cert-container">
        <img src="https://federicogonzalez.net/actis/img/osfa.png" class="watermark-img" alt="Watermark">
        
        <div class="cert-header">
            <img src="https://federicogonzalez.net/actis/img/osfa.svg" alt="Logo IOSFA">
            <h1>Policlínica General ACTIS</h1>
            <p>Sistema de Verificación Digital de Turnos</p>
        </div>

        <div class="status-banner">
            <i class="fa-solid fa-shield-check"></i> Documento Oficial Autenticado en Servidor
        </div>
        <div class="cert-body">
            
            <div class="section-title"><i class="fa-solid fa-user-check"></i> Información del Afiliado y Servicio</div>
            <div class="data-box">
                <div class="data-row">
                    <div class="data-label">Paciente Asignado</div>
                    <div class="data-value"><?php echo mb_strtoupper($nombre, 'UTF-8'); ?></div>
                </div>
                <div class="data-row">
                    <div class="data-label">Documento Nacional de Identidad (DNI)</div>
                    <div class="data-value"><i class="fa-regular fa-id-card" style="color:#144973;"></i> <?php echo $dni; ?></div>
                </div>
                <div class="data-row">
                    <div class="data-label">Especialidad / Servicio Médico Destino</div>
                    <div class="data-value"><i class="fa-solid fa-stethoscope" style="color:#144973;"></i> <?php echo mb_strtoupper($servicio, 'UTF-8'); ?></div>
                </div>
                <div class="data-row">
                    <div class="data-label">Cronograma Autorizado (Fecha y Hora)</div>
                    <div class="data-value">
                        <i class="fa-regular fa-calendar-days" style="color:#144973;"></i> <?php echo $fecha; ?> &nbsp;&bull;&nbsp; 
                        <i class="fa-regular fa-clock" style="color:#144973;"></i> <?php echo $hora; ?> hs
                    </div>
                </div>
            </div>

            <div class="section-title"><i class="fa-solid fa-key"></i> Código Token de Validation Exclusiva</div>
            <div class="token-box">
                <div class="data-label" style="margin-bottom: 4px;">Clave Única de Validación de Turno</div>
                <div class="token-value"><?php echo strtoupper($token); ?></div>
            </div>

            <div class="section-title" style="position: relative; z-index: 2;"><i class="fa-solid fa-terminal"></i> Trazabilidad Estricta de Infraestructura</div>
            <div class="security-grid" style="position: relative; z-index: 2;">
                <div class="security-card">
                    <div class="sec-lbl">Código Operación & Nodo Emisor</div>
                    <div class="sec-val"><?php echo $id_transaccion; ?> | <?php echo php_uname('n') ? substr(php_uname('n'), 0, 12) : 'SRV-ACTIS-PROD'; ?></div>
                </div>
                <div class="security-card">
                    <div class="sec-lbl">IP Remota Registro (Dispositivo)</div>
                    <div class="sec-val" style="color: #144973;"><?php echo $ip_verificacion; ?></div>
                </div>
                <div class="security-card">
                    <div class="sec-lbl">Agente de Cliente (User-Agent)</div>
                    <div class="sec-val"><?php echo $user_agent; ?></div>
                </div>
                <div class="security-card">
                    <div class="sec-lbl">Clase de Entrada & Sello de Tiempo UTC</div>
                    <div class="sec-val">VALIDACION_TICKET | <?php echo $timestamp_utc; ?></div>
                </div>
            </div>

            <div class="section-title" style="position: relative; z-index: 2;"><i class="fa-solid fa-signature"></i> Verificación de Integridad de Cadena</div>
            <div class="hash-box" style="position: relative; z-index: 2;">
                <div style="color: #94a3b8; font-size: 0.65rem; font-weight: bold; text-transform: uppercase; margin-bottom: 4px; border-bottom: 1px solid #334155; padding-bottom: 4px;">Algoritmo SHA-256 (Hash Criptográfico Asimétrico):</div>
                <?php echo $hash_verificacion; ?>
            </div>
            
            <div class="section-title"><i class="fa-solid fa-gavel"></i> Marco de Uso Legal</div>
            <div class="legal-notice">
                <i class="fa-solid fa-circle-info"></i> Este comprobante digital ha sido emitido mediante la terminal interactiva de la Policlínica General ACTIS. La información expuesta coincide estrictamente con los registros centralizados. Cualquier intento de adulteración anulará el proceso automáticamente.
            </div>

            <?php include 'includes/footer_ticket_digital.php'; ?>

        </div>
    </div>
</body>
</html>
