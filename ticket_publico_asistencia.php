<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Recolección estricta de parámetros sin solapamientos
$servicio = isset($_GET['servicio']) ? htmlspecialchars($_GET['servicio']) : 'ASISTENCIA MÉDICA GENERAL';
$orden_raw = isset($_GET['orden']) ? htmlspecialchars($_GET['orden']) : '000';
$dni = isset($_GET['dni']) ? htmlspecialchars($_GET['dni']) : '';
$nombre = isset($_GET['nombre']) ? htmlspecialchars($_GET['nombre']) : '';
$token_iosfa = isset($_GET['token']) ? htmlspecialchars($_GET['token']) : '';
$fecha = isset($_GET['fecha']) ? htmlspecialchars($_GET['fecha']) : date('d/m/Y');
$hora = isset($_GET['hora']) ? htmlspecialchars($_GET['hora']) : date('H:i');

// Asegurar formato de 3 dígitos estrictos para el número de llamado
$orden_final = is_numeric($orden_raw) ? str_pad($orden_raw, 3, "0", STR_PAD_LEFT) : $orden_raw;

// Metadatos Criptográficos Reales de Auditoría
$hash_asistencia = hash('sha256', $orden_final . $servicio . $fecha . $hora . 'ACTIS_SPONTANEOUS_SALT_2026');
$ip_solicitud = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$id_operacion = "TXA-" . strtoupper(substr(md5($orden_final . $servicio . $fecha), 0, 10));
$nodo_servidor = php_uname('n') ? substr(php_uname('n'), 0, 12) : 'SRV-ACTIS-PROD';
$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 45) . '...' : 'Unknown/Kiosco';
$timestamp_utc = gmdate("Y-m-d\TH:i:s\Z");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Certificado Oficial - ACTIS Asistencia</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: #f1f5f9; color: #334155; padding: 8px; min-height: 100vh; display: flex; justify-content: center; align-items: flex-start; }
        .cert-container { width: 100%; max-width: 480px; background: #ffffff; border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; overflow: hidden; position: relative; }
        
        /* Encabezado Principal Actualizado */
        .cert-header { background: linear-gradient(135deg, #144973 0%, #0d3252 100%); padding: 25px 16px; text-align: center; border-bottom: 5px solid #0a2640; }
        .cert-header img { height: 75px; margin-bottom: 12px; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.25)); }
        .cert-header h1 { font-size: 1.25rem; font-weight: 900; color: #ffffff; text-transform: uppercase; margin: 0; letter-spacing: 0.5px; }
        .cert-header p { font-size: 0.75rem; color: #e2e8f0; font-weight: 600; margin-top: 5px; text-transform: uppercase; letter-spacing: 1px; }
        
        .status-banner { background: #fff7ed; border-bottom: 1px solid #ffedd5; padding: 12px; display: flex; align-items: center; justify-content: center; gap: 8px; color: #c2410c; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; text-align: center; }
        
        .cert-body { padding: 16px; display: flex; flex-direction: column; gap: 14px; width: 100%; }
        .section-title { font-size: 0.75rem; font-weight: 900; color: #144973; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 6px; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; margin-left: 8px; }
        
        .data-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px; display: flex; flex-direction: column; gap: 10px; width: 100%; }
        .data-row { display: flex; flex-direction: column; border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px; width: 100%; overflow: hidden; }
        .data-row:last-child { border-bottom: none; padding-bottom: 0; }
        .data-label { font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; }
        .data-value { font-size: 0.95rem; font-weight: 800; color: #0f172a; word-wrap: break-word; overflow-wrap: break-word; }
        
        .order-showcase { background: linear-gradient(145deg, #ffffff, #f8fafc); border: 2px dashed #144973; border-radius: 16px; padding: 16px; text-align: center; width: 100%; }
        .order-number { font-size: 3.5rem; font-weight: 900; color: #144973; line-height: 1; font-family: 'JetBrains Mono', monospace; letter-spacing: -2px; margin-top: 4px; }
        
        .tech-grid { display: grid; grid-template-columns: 1fr; gap: 8px; width: 100%; }
        .tech-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; overflow: hidden; display: flex; flex-direction: column; }
        .tech-lbl { font-size: 0.62rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 2px; }
        .tech-val { font-size: 0.8rem; font-weight: 700; color: #334155; font-family: 'JetBrains Mono', monospace; word-break: break-word; white-space: normal; }
        
        .crypt-box { background: #0f172a; border-radius: 12px; padding: 12px; color: #38bdf8; font-family: 'JetBrains Mono', monospace; font-size: 0.7rem; word-break: break-all; border-left: 4px solid #10b981; width: 100%; white-space: normal; box-shadow: inset 0 0 10px rgba(0,0,0,0.5); }
        .legal-box { padding: 12px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; font-size: 0.7rem; color: #1e40af; text-align: justify; line-height: 1.4; width: 100%; font-weight: 600; position: relative; z-index: 2; }
        
        .watermark-img { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 80%; opacity: 0.04; pointer-events: none; z-index: 0; filter: grayscale(100%); }
    </style>
</head>
<body>
    <div class="cert-container">
        <img src="https://federicogonzalez.net/actis/img/osfa.png" class="watermark-img" alt="Watermark">
        <div class="cert-header">
            <img src="https://federicogonzalez.net/actis/img/osfa.svg" alt="Logo">
            <h1>Policlínica General ACTIS</h1>
            <p>Comprobante de Admisión Espontánea</p>
        </div>
        <div class="status-banner">
            <i class="fa-solid fa-clock-rotate-left"></i> Turno Generado por Demanda Espontánea
        </div>
        <div class="cert-body">
            <div class="section-title"><i class="fa-solid fa-hospital-user"></i> Datos del Paciente Registrados</div>
            <div class="data-box">
                <div class="data-row">
                    <div class="data-label">Afiliado Concurrente</div>
                    <div class="data-value"><?php echo (!empty($nombre) && $nombre !== 'AFILIADO') ? mb_strtoupper($nombre, 'UTF-8') : 'PACIENTE POR DEMANDA ESPONTÁNEA'; ?></div>
                </div>
                
                <?php if (!empty($dni)): ?>
                <div class="data-row">
                    <div class="data-label">Documento Nacional de Identidad</div>
                    <div class="data-value"><i class="fa-regular fa-id-card" style="color:#144973;"></i> <?php echo $dni; ?></div>
                </div>
                <?php endif; ?>

                <div class="data-row">
                    <div class="data-label">Servicio Médico de Destino Asignado</div>
                    <div class="data-value"><i class="fa-solid fa-stethoscope" style="color:#144973;"></i> <?php echo mb_strtoupper($servicio, 'UTF-8'); ?></div>
                </div>
                <div class="data-row">
                    <div class="data-label">Instante de Emisión Física en Tótem</div>
                    <div class="data-value">
                        <i class="fa-regular fa-calendar" style="color:#144973;"></i> <?php echo $fecha; ?> &nbsp;&bull;&nbsp; 
                        <i class="fa-regular fa-clock" style="color:#144973;"></i> <?php echo $hora; ?> hs
                    </div>
                </div>
            </div>

            <div class="section-title"><i class="fa-solid fa-arrow-up-9-1"></i> Prioridad de Turno Asignada</div>
            <div class="order-showcase">
                <div class="data-label" style="color: #144973; font-weight: 800;">Número de Orden para Llamado por Pantalla</div>
                <div class="order-number"><?php echo $orden_final; ?></div>
            </div>

            <?php if (!empty($token_iosfa)): ?>
            <div class="section-title"><i class="fa-solid fa-key"></i> Código Token Validation IOSFA</div>
            <div style="background: #f8fafc; border: 2px solid #144973; border-radius: 12px; padding: 12px; text-align: center; font-family: 'JetBrains Mono', monospace; font-size: 1.4rem; font-weight: 900; color: #144973; letter-spacing: 2px; width: 100%;">
                <?php echo strtoupper($token_iosfa); ?>
            </div>
            <?php endif; ?>

            <div class="section-title" style="position: relative; z-index: 2;"><i class="fa-solid fa-terminal"></i> Trazabilidad Estricta de Infraestructura</div>
            <div class="tech-grid" style="position: relative; z-index: 2;">
                <div class="tech-card">
                    <div class="tech-lbl">Código Operación & Nodo Emisor</div>
                    <div class="tech-val"><?php echo $id_operacion; ?> | <?php echo $nodo_servidor; ?></div>
                </div>
                <div class="tech-card">
                    <div class="tech-lbl">IP Remota Registro (Tótem/Dispositivo)</div>
                    <div class="tech-val" style="color: #144973;"><?php echo $ip_solicitud; ?></div>
                </div>
                <div class="tech-card">
                    <div class="tech-lbl">Agente de Cliente (User-Agent)</div>
                    <div class="tech-val"><?php echo $user_agent; ?></div>
                </div>
                <div class="tech-card">
                    <div class="tech-lbl">Clase de Entrada & Sello de Tiempo UTC</div>
                    <div class="tech-val">DEMANDA_ESPONTANEA | <?php echo $timestamp_utc; ?></div>
                </div>
            </div>

            <div class="section-title" style="position: relative; z-index: 2;"><i class="fa-solid fa-signature"></i> Verificación de Integridad de Cadena</div>
            <div class="crypt-box" style="position: relative; z-index: 2;">
                <div style="color: #94a3b8; font-size: 0.65rem; font-weight: bold; text-transform: uppercase; margin-bottom: 4px; border-bottom: 1px solid #334155; padding-bottom: 4px;">Algoritmo SHA-256 (Hash Criptográfico Asimétrico):</div>
                <?php echo $hash_asistencia; ?>
            </div>

            <div class="section-title"><i class="fa-solid fa-bell"></i> Directivas de Sala de Espera</div>
            <div class="legal-box">
                Aguarde a ser llamado a través del sistema de pantallas principal de la sala utilizando el <b>Número de Orden</b> provisto en la sección central de este certificado.
            </div>

            <?php include 'includes/footer_ticket_digital.php'; ?>

        </div>
    </div>
</body>
</html>
