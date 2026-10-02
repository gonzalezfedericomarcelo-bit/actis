<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$hash_recibido = isset($_GET['h']) ? htmlspecialchars($_GET['h']) : '';

// Conexión a la base de datos
if (file_exists('includes/conexion.php')) {
    require_once 'includes/conexion.php';
} else {
    require_once 'conexion.php';
}

$es_valido = false;
$turno = null;
$firma_doctor = '';

if ($id > 0 && !empty($hash_recibido) && isset($conexion)) {
    // Buscar el turno
    $sql = "SELECT t.*, p.nombre, p.apellido, p.dni 
            FROM turnos t 
            INNER JOIN pacientes p ON t.paciente_id = p.id 
            WHERE t.id = $id";
    $res = $conexion->query($sql);
    
    if ($res && $res->num_rows > 0) {
        $turno = $res->fetch_assoc();
        
        // Verificar el Hash Criptográfico
        $hash_calculado = md5($turno['id'] . $turno['dni'] . 'ACTIS_SECURE_TOKEN_2026');
        
        if ($hash_calculado === $hash_recibido) {
            $es_valido = true;
            
            // Buscar la firma del doctor
            $medico_user_id = isset($turno['usuario_medico_id']) ? (int)$turno['usuario_medico_id'] : 0;
            $id_fallback = isset($turno['usuario_recepcion_id']) ? (int)$turno['usuario_recepcion_id'] : (isset($turno['usuario_creador_id']) ? (int)$turno['usuario_creador_id'] : 0);
            $medico_esc = $conexion->real_escape_string($turno['profesional']);
            
            $q_doc = false;
            if ($medico_user_id > 0) {
                $q_doc = $conexion->query("SELECT firma_imagen_path FROM usuarios WHERE id = $medico_user_id LIMIT 1");
            }
            if ((!$q_doc || $q_doc->num_rows == 0) && $id_fallback > 0) {
                $q_doc = $conexion->query("SELECT firma_imagen_path FROM usuarios WHERE id = $id_fallback LIMIT 1");
            }
            if ((!$q_doc || $q_doc->num_rows == 0) && !empty($medico_esc)) {
                $q_doc = $conexion->query("SELECT firma_imagen_path FROM usuarios WHERE nombre_completo = '$medico_esc' LIMIT 1");
            }
            
            if ($q_doc && $q_doc->num_rows > 0) {
                $firma_doctor = $q_doc->fetch_assoc()['firma_imagen_path'];
            }
        }
    }
}

// Generación de Metadatos de Trazabilidad
$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 45) . '...' : 'Unknown Platform';
$ip_verificacion = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$id_transaccion = "VAL-" . strtoupper(substr(md5($hash_recibido . time()), 0, 10));
$timestamp_utc = gmdate("Y-m-d\TH:i:s\Z");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Validación de Constancia - ACTIS Core</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: #f4f6f9; color: #1e293b; padding: 15px; min-height: 100vh; display: flex; justify-content: center; align-items: flex-start; }
        
        .cert-container { width: 100%; max-width: 500px; background: #ffffff; border-radius: 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; overflow: hidden; position: relative; margin-bottom: 20px; }
        
        /* Encabezado */
        .cert-header { background: #144973; padding: 25px 20px; text-align: center; border-bottom: 5px solid #0d304c; }
        .cert-header i { font-size: 2.5rem; color: #60a5fa; margin-bottom: 10px; }
        .cert-header h1 { font-size: 1.3rem; font-weight: 900; color: #ffffff; margin: 0; text-transform: uppercase; }
        .cert-header p { font-size: 0.8rem; color: #e0e7ff; font-weight: 500; margin-top: 5px; text-transform: uppercase; letter-spacing: 1px; }
        
        /* Banners de Estado */
        .status-banner { padding: 12px; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; text-align: center; }
        .status-valid { background: #ecfdf5; border-bottom: 1px solid #a7f3d0; color: #065f46; }
        .status-valid i { color: #10b981; font-size: 1.2rem; }
        .status-invalid { background: #fef2f2; border-bottom: 1px solid #fecaca; color: #991b1b; }
        .status-invalid i { color: #ef4444; font-size: 1.2rem; }

        .cert-body { padding: 20px; display: flex; flex-direction: column; gap: 15px; width: 100%; }

        .section-title { font-size: 0.75rem; font-weight: 900; color: #144973; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 6px; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; margin-left: 8px; }

        /* Cajas de Datos */
        .data-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 15px; display: flex; flex-direction: column; gap: 12px; }
        .data-row { display: flex; flex-direction: column; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px; }
        .data-row:last-child { border-bottom: none; padding-bottom: 0; }
        .data-label { font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 2px; }
        .data-value { font-size: 1rem; font-weight: 800; color: #0f172a; word-wrap: break-word; }
        .data-value-highlight { font-size: 1.2rem; font-weight: 900; color: #2563eb; font-family: 'JetBrains Mono', monospace; }

        /* Grilla Técnica */
        .security-grid { display: grid; grid-template-columns: 1fr; gap: 8px; }
        .security-card { background: #fafafa; border: 1px solid #f1f5f9; border-radius: 10px; padding: 10px; }
        .sec-lbl { font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 2px; }
        .sec-val { font-size: 0.8rem; font-weight: 700; color: #334155; font-family: 'JetBrains Mono', monospace; word-break: break-all; }

        .hash-box { background: #1e293b; border-radius: 12px; padding: 12px; color: #38bdf8; font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; word-break: break-all; border-left: 4px solid #10b981; }

        .legal-notice { padding: 12px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 12px; font-size: 0.75rem; color: #92400e; font-weight: 500; line-height: 1.4; text-align: justify; }

        .signature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .signature-box { border: 1px dashed #cbd5e1; border-radius: 12px; padding: 10px; background: #ffffff; text-align: center; }
        .signature-box img { max-width: 100%; height: auto; max-height: 80px; }
        .no-signature { font-size: 0.75rem; color: #94a3b8; font-style: italic; padding: 20px 0; }
    </style>
</head>
<body>
    <div class="cert-container">
        
        <div class="cert-header">
            <i class="fa-solid fa-file-contract"></i>
            <h1>Policlínica General ACTIS</h1>
            <p>Auditoría de Constancia y Atención</p>
        </div>

        <?php if($es_valido && $turno): ?>
            <div class="status-banner status-valid">
                <i class="fa-solid fa-circle-check"></i> Documento Oficial Autenticado
            </div>
            
            <div class="cert-body">
                
                <div class="section-title"><i class="fa-solid fa-user"></i> Datos del Paciente</div>
                <div class="data-box">
                    <div class="data-row">
                        <div class="data-label">Paciente</div>
                        <div class="data-value"><?php echo mb_strtoupper($turno['apellido'], 'UTF-8') . ', ' . htmlspecialchars($turno['nombre']); ?></div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">DNI</div>
                        <div class="data-value"><i class="fa-regular fa-id-card"></i> <?php echo htmlspecialchars($turno['dni']); ?></div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">Fecha de Ingreso</div>
                        <div class="data-value"><i class="fa-regular fa-calendar-days"></i> <?php echo date("d/m/Y", strtotime($turno['fecha_turno'])); ?></div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">Estado Actual</div>
                        <div class="data-value">
                            <?php if($turno['estado'] == 'Atendido'): ?>
                                <span style="color: #166534;"><i class="fa-solid fa-check-double"></i> ATENDIDO (Salida Confirmada)</span>
                            <?php else: ?>
                                <span style="color: #b45309;"><i class="fa-solid fa-clock"></i> PRESENTE (En Recepción/Sala)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="section-title"><i class="fa-solid fa-stethoscope"></i> Detalle de la Atención</div>
                <div class="data-box">
                    <div class="data-row">
                        <div class="data-label">Número de Orden (Ticket)</div>
                        <div class="data-value-highlight"><?php echo !empty($turno['codigo_ticket_totem']) ? htmlspecialchars($turno['codigo_ticket_totem']) : 'NO ASIGNADO'; ?></div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">Servicio</div>
                        <div class="data-value"><?php echo mb_strtoupper($turno['servicio'], 'UTF-8'); ?></div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">Profesional</div>
                        <div class="data-value"><?php echo mb_strtoupper($turno['profesional'], 'UTF-8'); ?></div>
                    </div>
                    
                    <?php if(!empty($turno['diagnostico'])): ?>
                    <div class="data-row">
                        <div class="data-label">Diagnóstico de Salida</div>
                        <div class="data-value" style="font-weight: 500; font-size: 0.9rem;"><?php echo nl2br(htmlspecialchars($turno['diagnostico'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="section-title"><i class="fa-solid fa-signature"></i> Firmas Digitalizadas</div>
                <div class="signature-grid">
                    <div class="signature-box">
                        <div class="data-label">Firma del Profesional</div>
                        <?php if(!empty($firma_doctor) && file_exists('uploads/firmas/' . $firma_doctor)): ?>
                            <img src="uploads/firmas/<?php echo htmlspecialchars($firma_doctor); ?>" alt="Firma Médico">
                        <?php else: ?>
                            <div class="no-signature">No disponible</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="signature-box">
                        <div class="data-label">Firma del Paciente</div>
                        <?php if(!empty($turno['firma_paciente']) && strpos($turno['firma_paciente'], 'data:image') === 0): ?>
                            <img src="<?php echo $turno['firma_paciente']; ?>" alt="Firma Paciente">
                        <?php else: ?>
                            <div class="no-signature">No registrada en salida</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section-title"><i class="fa-solid fa-server"></i> Trazabilidad de la Auditoría</div>
                <div class="security-grid">
                    <div class="security-card">
                        <div class="sec-lbl">ID de Transacción & Nodo Servidor</div>
                        <div class="sec-val"><?php echo $id_transaccion; ?> | SRV-ACTIS</div>
                    </div>
                    <div class="security-card">
                        <div class="sec-lbl">IP Remota del Lector</div>
                        <div class="sec-val" style="color: #2563eb;"><?php echo $ip_verificacion; ?></div>
                    </div>
                    <div class="security-card">
                        <div class="sec-lbl">Sello de Tiempo UTC (Escaneo)</div>
                        <div class="sec-val"><?php echo $timestamp_utc; ?></div>
                    </div>
                </div>

                <div class="section-title"><i class="fa-solid fa-fingerprint"></i> Certificación Criptográfica</div>
                <div class="hash-box">
                    <div style="color: #94a3b8; font-size: 0.65rem; font-weight: bold; text-transform: uppercase; margin-bottom: 4px; border-bottom: 1px solid #334155; padding-bottom: 4px;">Algoritmo MD5-HEX (Firma Estática):</div>
                    <?php echo strtoupper($hash_recibido); ?>
                </div>

                <div class="section-title"><i class="fa-solid fa-scale-balanced"></i> Aviso Legal</div>
                <div class="legal-notice">
                    <i class="fa-solid fa-circle-info"></i> Esta página garantiza que la constancia física o digital coincide con los registros inalterables de los servidores de la Policlínica General ACTIS.
                </div>

            </div>

        <?php else: ?>
            <div class="status-banner status-invalid">
                <i class="fa-solid fa-triangle-exclamation"></i> Alerta: Documento Inválido o Adulterado
            </div>
            <div class="cert-body">
                <div style="text-align: center; padding: 40px 20px; color: #64748b;">
                    <i class="fa-solid fa-file-circle-xmark" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 15px;"></i>
                    <p>El código criptográfico no coincide con nuestros registros o el turno no existe en la base de datos central.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>