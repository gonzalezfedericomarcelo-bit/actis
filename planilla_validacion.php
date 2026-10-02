<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Recibir variables desde el QR de la Planilla
$medico = isset($_GET['m']) ? htmlspecialchars($_GET['m']) : 'DESCONOCIDO';
$fecha_raw = isset($_GET['f']) ? htmlspecialchars($_GET['f']) : date('Y-m-d');
$hash_recibido = isset($_GET['h']) ? htmlspecialchars($_GET['h']) : '';

$fecha_formateada = date('d/m/Y', strtotime($fecha_raw));

// Lógica de Verificación (Mismo algoritmo del PDF)
$hash_calculado = md5($_GET['m'] . $_GET['f'] . 'ACTIS_SECURE_TOKEN_2026');
$es_valido = ($hash_calculado === $hash_recibido && !empty($hash_recibido));

// Conexión a la base de datos y obtención de datos
if (file_exists('includes/conexion.php')) {
    require_once 'includes/conexion.php';
} else {
    require_once 'conexion.php';
}

$pacientes_list = [];
$firma_doctor = '';
$especialidad_doctor = 'NO ESPECIFICADA';
$medico_user_id = 0;

if ($es_valido && isset($conexion)) {
    $medico_esc = $conexion->real_escape_string($medico);
    $fecha_esc = $conexion->real_escape_string($fecha_raw);

    $id_fallback = 0;
    // 1. Obtener lista de pacientes PRIMERO para sacar el ID real del médico
    $sql = "SELECT t.*, p.nombre, p.apellido, p.dni 
            FROM turnos t 
            INNER JOIN pacientes p ON t.paciente_id = p.id 
            WHERE t.fecha_turno = '$fecha_esc' 
            AND t.profesional = '$medico_esc' 
            AND (t.estado = 'Presente' OR t.estado = 'Atendido')
            ORDER BY t.hora_turno ASC";
    
    $res = $conexion->query($sql);
    if ($res && $res->num_rows > 0) {
        while($row = $res->fetch_assoc()){
            $pacientes_list[] = $row;
            if ($especialidad_doctor === 'NO ESPECIFICADA' && !empty($row['especialidad'])) {
                $especialidad_doctor = $row['especialidad'];
            }
            // Capturar el ID del médico desde el turno, igual que hace el PDF
            if ($medico_user_id === 0 && !empty($row['usuario_medico_id'])) {
                $medico_user_id = (int)$row['usuario_medico_id'];
            }
            // NUEVO: Fallback para atrapar al usuario asociado (creador o recepción) como hace el PDF con la sesión
            if ($id_fallback === 0) {
                if (!empty($row['usuario_recepcion_id'])) $id_fallback = (int)$row['usuario_recepcion_id'];
                elseif (!empty($row['usuario_creador_id'])) $id_fallback = (int)$row['usuario_creador_id'];
            }
        }
    }

    // 2. Obtener firma del médico usando el mismo motor exacto del PDF
    $q_doc = false;
    
    // Intento A: Buscar la firma por el ID del médico (si estuviera en la tabla de turnos)
    if ($medico_user_id > 0) {
        $q_doc = $conexion->query("SELECT firma_imagen_path FROM usuarios WHERE id = $medico_user_id LIMIT 1");
    }
    
    // Intento B: (El truco del PDF) Usar el ID de fallback como si fuera el usuario logueado en sesión
    if ((!$q_doc || $q_doc->num_rows == 0) && $id_fallback > 0) {
        $q_doc = $conexion->query("SELECT firma_imagen_path FROM usuarios WHERE id = $id_fallback LIMIT 1");
    }

    // Intento C: Buscar por el nombre completo en texto plano (como hace el PDF)
    if ((!$q_doc || $q_doc->num_rows == 0) && !empty($medico_esc)) {
        $q_doc = $conexion->query("SELECT firma_imagen_path FROM usuarios WHERE nombre_completo = '$medico_esc' LIMIT 1");
    }

    if ($q_doc && $q_doc->num_rows > 0) {
        $row_doc = $q_doc->fetch_assoc();
        $firma_doctor = $row_doc['firma_imagen_path'];
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
    <title>Validación de Planilla - ACTIS Core</title>
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

        /* Grilla Técnica */
        .security-grid { display: grid; grid-template-columns: 1fr; gap: 8px; }
        .security-card { background: #fafafa; border: 1px solid #f1f5f9; border-radius: 10px; padding: 10px; }
        .sec-lbl { font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 2px; }
        .sec-val { font-size: 0.8rem; font-weight: 700; color: #334155; font-family: 'JetBrains Mono', monospace; word-break: break-all; }

        .hash-box { background: #1e293b; border-radius: 12px; padding: 12px; color: #38bdf8; font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; word-break: break-all; border-left: 4px solid #10b981; }

        .legal-notice { padding: 12px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 12px; font-size: 0.75rem; color: #92400e; font-weight: 500; line-height: 1.4; text-align: justify; }
        /* Estilos para lista de pacientes y modales */
        .patient-list { display: flex; flex-direction: column; gap: 10px; margin-top: 10px; }
        .patient-card { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .patient-card:hover { border-color: #3b82f6; background: #eff6ff; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .patient-info-basic { display: flex; flex-direction: column; }
        .patient-name { font-size: 0.85rem; font-weight: 700; color: #0f172a; }
        .patient-dni { font-size: 0.7rem; color: #64748b; font-weight: 600; }
        .patient-action i { color: #3b82f6; font-size: 1.2rem; }
        
        .btn-firma-doc { background: #144973; color: white; border: none; border-radius: 10px; padding: 10px 15px; font-family: 'Poppins', sans-serif; font-size: 0.8rem; font-weight: 600; cursor: pointer; width: 100%; margin-top: 10px; display: flex; justify-content: center; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-firma-doc:hover { background: #0d304c; }

        /* Modal Base */
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.7); display: none; justify-content: center; align-items: center; z-index: 9999; padding: 20px; backdrop-filter: blur(4px); }
        .modal-overlay.active { display: flex; }
        .modal-content { background: #ffffff; width: 100%; max-width: 400px; border-radius: 20px; overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.25); animation: modalIn 0.3s ease-out; display: flex; flex-direction: column; max-height: 90vh; }
        @keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .modal-header { background: #f8fafc; padding: 15px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .modal-title { font-size: 0.95rem; font-weight: 800; color: #1e293b; }
        .modal-close { background: none; border: none; color: #94a3b8; font-size: 1.2rem; cursor: pointer; transition: 0.2s; }
        .modal-close:hover { color: #ef4444; }
        .modal-body { padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; }
        .modal-signature { width: 100%; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 10px; background: #f8fafc; text-align: center; }
        .modal-signature img { max-width: 100%; height: auto; max-height: 120px; }
        .no-signature { font-size: 0.75rem; color: #94a3b8; font-style: italic; padding: 20px 0; }
    </style>
</head>
<body>
    <div class="cert-container">
        
        <div class="cert-header">
            <i class="fa-solid fa-file-shield"></i>
            <h1>Policlínica General ACTIS</h1>
            <p>Auditoría de Planilla Médica</p>
        </div>

        <?php if($es_valido): ?>
            <div class="status-banner status-valid">
                <i class="fa-solid fa-circle-check"></i> Documento Oficial Autenticado
            </div>
        <?php else: ?>
            <div class="status-banner status-invalid">
                <i class="fa-solid fa-triangle-exclamation"></i> Alerta: Documento Inválido o Adulterado
            </div>
        <?php endif; ?>

        <div class="cert-body">
            
            <div class="section-title"><i class="fa-solid fa-user-doctor"></i> Detalle del Documento</div>
            <div class="data-box">
                <div class="data-row">
                    <div class="data-label">Tipo de Documento</div>
                    <div class="data-value" style="color: #144973;"><i class="fa-solid fa-file-invoice"></i> PLANILLA DIARIA DE ATENCIÓN MÉDICA</div>
                </div>
                <div class="data-row">
                    <div class="data-label">Profesional Responsable</div>
                    <div class="data-value"><?php echo mb_strtoupper($medico, 'UTF-8'); ?></div>
                </div>
                <div class="data-row">
                    <div class="data-label">Fecha del Documento</div>
                    <div class="data-value"><i class="fa-regular fa-calendar-days"></i> <?php echo $fecha_formateada; ?></div>
                </div>
                <div class="data-row">
                    <div class="data-label">Especialidad</div>
                    <div class="data-value"><?php echo htmlspecialchars($especialidad_doctor); ?></div>
                </div>
                <div class="data-row">
                    <div class="data-label">Institución Emisora</div>
                    <div class="data-value">POLICLÍNICA GENERAL ACTIS</div>
                </div>
            </div>

            <?php if($es_valido): ?>
            <div class="section-title"><i class="fa-solid fa-signature"></i> Firma del Profesional</div>
            <div class="data-box" style="align-items: center; text-align: center; padding: 20px;">
                <?php if(!empty($firma_doctor)): ?>
                    <img src="uploads/firmas/<?php echo htmlspecialchars($firma_doctor); ?>" alt="Firma Profesional" style="max-width: 100%; max-height: 120px;">
                <?php else: ?>
                    <div style="padding: 15px; color: #94a3b8; font-style: italic; font-size: 0.85rem;">
                        <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.5rem; margin-bottom: 8px; color: #cbd5e1; display:block;"></i>
                        Firma no encontrada en la base de datos para este profesional.
                    </div>
                <?php endif; ?>
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #cbd5e1; width: 100%;">
                    <div style="font-size: 0.85rem; font-weight: 800; color: #0f172a;"><?php echo mb_strtoupper($medico, 'UTF-8'); ?></div>
                    <div style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase;"><?php echo htmlspecialchars($especialidad_doctor); ?></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if($es_valido && count($pacientes_list) > 0): ?>
            <div class="section-title"><i class="fa-solid fa-users"></i> Pacientes Atendidos (<?php echo count($pacientes_list); ?>)</div>
            <div class="patient-list">
                <?php foreach($pacientes_list as $paciente): 
                    $nombreCompleto = htmlspecialchars($paciente['apellido'] . ', ' . $paciente['nombre']);
                    $dni = htmlspecialchars($paciente['dni']);
                    $hora = date('H:i', strtotime($paciente['hora_turno']));
                    $codigo = htmlspecialchars($paciente['token_iofa']);
                    $diag = htmlspecialchars($paciente['diagnostico']);
                    $firma_b64 = isset($paciente['firma_paciente']) ? $paciente['firma_paciente'] : '';
                ?>
                <div class="patient-card" onclick="abrirModalPaciente('<?php echo addslashes($nombreCompleto); ?>', '<?php echo addslashes($dni); ?>', '<?php echo addslashes($hora); ?>', '<?php echo addslashes($codigo); ?>', '<?php echo addslashes($diag); ?>', '<?php echo addslashes($firma_b64); ?>')">
                    <div class="patient-info-basic">
                        <span class="patient-name"><?php echo $nombreCompleto; ?></span>
                        <span class="patient-dni"><i class="fa-regular fa-id-card"></i> DNI: <?php echo $dni; ?></span>
                    </div>
                    <div class="patient-action">
                        <i class="fa-solid fa-chevron-right"></i>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <br>
            <?php endif; ?>

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
                    <div class="sec-lbl">Dispositivo (User-Agent)</div>
                    <div class="sec-val"><?php echo $user_agent; ?></div>
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
                <i class="fa-solid fa-circle-info"></i> Esta página garantiza que la planilla física impresa coincide con los registros inalterables de los servidores de la Policlínica General ACTIS. La adulteración, falsificación o modificación de la información contenida en la hoja física constituye un delito federal penal.
            </div>

        </div>
    </div>

    

    <div class="modal-overlay" id="modalPaciente" onclick="cerrarModal(event, 'modalPaciente')">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title"><i class="fa-solid fa-user"></i> Detalle de Atención</div>
                <button class="modal-close" onclick="document.getElementById('modalPaciente').classList.remove('active')"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="data-row">
                    <div class="data-label">Paciente</div>
                    <div class="data-value" id="mp_nombre"></div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="data-row">
                        <div class="data-label">DNI</div>
                        <div class="data-value" id="mp_dni" style="font-size: 0.85rem;"></div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">Hora</div>
                        <div class="data-value" id="mp_hora" style="font-size: 0.85rem;"></div>
                    </div>
                </div>
                <div class="data-row">
                    <div class="data-label">Cód. Validación</div>
                    <div class="data-value" id="mp_codigo" style="font-size: 0.85rem; font-family: 'JetBrains Mono', monospace; color: #2563eb;"></div>
                </div>
                <div class="data-row">
                    <div class="data-label">Diagnóstico</div>
                    <div class="data-value" id="mp_diag" style="font-size: 0.85rem; font-weight: 500;"></div>
                </div>
                
                <div class="data-label" style="margin-top: 10px;">Firma del Paciente</div>
                <div class="modal-signature" id="mp_firma_container">
                    </div>
            </div>
        </div>
    </div>

    <script>
        function abrirModalDoctor() {
            document.getElementById('modalDoctor').classList.add('active');
        }

        function abrirModalPaciente(nombre, dni, hora, codigo, diag, firmaB64) {
            document.getElementById('mp_nombre').textContent = nombre;
            document.getElementById('mp_dni').textContent = dni;
            document.getElementById('mp_hora').textContent = hora;
            document.getElementById('mp_codigo').textContent = codigo || 'N/A';
            document.getElementById('mp_diag').textContent = diag || 'Sin diagnóstico registrado';
            
            const firmaContainer = document.getElementById('mp_firma_container');
            if (firmaB64 && firmaB64.startsWith('data:image')) {
                firmaContainer.innerHTML = '<img src="' + firmaB64 + '" alt="Firma Paciente">';
            } else {
                firmaContainer.innerHTML = '<div class="no-signature">Firma no registrada</div>';
            }

            document.getElementById('modalPaciente').classList.add('active');
        }

        function cerrarModal(event, modalId) {
            if (event.target.id === modalId) {
                document.getElementById(modalId).classList.remove('active');
            }
        }
    </script>
</body>
</html>