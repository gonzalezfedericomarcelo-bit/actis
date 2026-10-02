<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';
require_once 'fpdf/fpdf.php';
require_once 'envio_correo.php';

$turno_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['turno_id']) ? (int)$_POST['turno_id'] : 0);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['turno_id'])) {
    $tid = (int)$_POST['turno_id'];
    $firma_base64 = $conexion->real_escape_string($_POST['firma_base64']);
    
    // 1. Guardar la firma en la base de datos
    $conexion->query("UPDATE turnos SET firma_paciente = '$firma_base64' WHERE id = $tid");
    
    // 2. Traer todos los datos del paciente para generar la constancia unificada
    $q = $conexion->query("SELECT t.*, p.nombre, p.apellido, p.dni, p.email FROM turnos t INNER JOIN pacientes p ON t.paciente_id = p.id WHERE t.id = $tid");
    $t = $q->fetch_assoc();
    
    // 3. Buscar la firma del profesional (idéntico a certificado_asistencia.php)
    $medico_user_id = isset($t['usuario_medico_id']) ? (int)$t['usuario_medico_id'] : 0;
    $id_fallback = isset($t['usuario_recepcion_id']) ? (int)$t['usuario_recepcion_id'] : (isset($t['usuario_creador_id']) ? (int)$t['usuario_creador_id'] : 0);
    $medico_esc = $conexion->real_escape_string($t['profesional']);
    
    $q_doc = false;
    if ($medico_user_id > 0) $q_doc = $conexion->query("SELECT firma_imagen_path FROM usuarios WHERE id = $medico_user_id LIMIT 1");
    if ((!$q_doc || $q_doc->num_rows == 0) && $id_fallback > 0) $q_doc = $conexion->query("SELECT firma_imagen_path FROM usuarios WHERE id = $id_fallback LIMIT 1");
    if ((!$q_doc || $q_doc->num_rows == 0) && !empty($medico_esc)) $q_doc = $conexion->query("SELECT firma_imagen_path FROM usuarios WHERE nombre_completo = '$medico_esc' LIMIT 1");
    
    $firma_doctor = '';
    if ($q_doc && $q_doc->num_rows > 0) {
        $firma_doctor = $q_doc->fetch_assoc()['firma_imagen_path'];
    }

    // 4. GENERAR EL PDF EXACTAMENTE IGUAL AL CERTIFICADO OFICIAL
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    
    if(file_exists('img/osfa-marca-de-agua.png')) {
        $pdf->Image('img/osfa-marca-de-agua.png', 45, 80, 120);
    }
    if(file_exists('img/osfa_negro.png')) {
        $pdf->Image('img/osfa_negro.png', 10, 10, 30);
    }
    
    $pdf->SetY(15);
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->Cell(0, 10, mb_convert_encoding('POLICLÍNICA GENERAL ACTIS', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 8, mb_convert_encoding('CONSTANCIA DE ASISTENCIA MÉDICA', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
    
    $pdf->Ln(20);
    $pdf->SetFont('Arial', '', 12);
    $apellido_iso = mb_convert_encoding(mb_strtoupper($t['apellido'], 'UTF-8'), 'ISO-8859-1', 'UTF-8');
    $nombre_iso = mb_convert_encoding($t['nombre'], 'ISO-8859-1', 'UTF-8');
    $servicio_iso = mb_convert_encoding(mb_strtoupper($t['servicio'], 'UTF-8'), 'ISO-8859-1', 'UTF-8');
    
    $texto = mb_convert_encoding("Por la presente se deja constancia que el/la paciente ", 'ISO-8859-1', 'UTF-8') . $apellido_iso . ", " . $nombre_iso . mb_convert_encoding(" con DNI Nro ", 'ISO-8859-1', 'UTF-8') . $t['dni'] . mb_convert_encoding(", se ha presentado en el día de la fecha en nuestra institución para recibir atención en el servicio de ", 'ISO-8859-1', 'UTF-8') . $servicio_iso . ".";
    $pdf->MultiCell(0, 8, $texto);
    
    $pdf->Ln(15);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(50, 8, mb_convert_encoding('Fecha del Turno:', 'ISO-8859-1', 'UTF-8'), 0, 0);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, date("d/m/Y", strtotime($t['fecha_turno'])), 0, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(50, 8, mb_convert_encoding('Hora de Ingreso:', 'ISO-8859-1', 'UTF-8'), 0, 0);
    $pdf->SetFont('Arial', '', 12);
    $hora_ingreso = !empty($t['recepcionado_el']) ? date("H:i", strtotime($t['recepcionado_el'])) . " hs" : "Hora no registrada";
    $pdf->Cell(0, 8, $hora_ingreso, 0, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(50, 8, mb_convert_encoding('N° de Orden:', 'ISO-8859-1', 'UTF-8'), 0, 0);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(37, 99, 235);
    $orden = !empty($t['codigo_ticket_totem']) ? $t['codigo_ticket_totem'] : "NO ASIGNADO";
    $pdf->Cell(0, 8, $orden, 0, 1);
    $pdf->SetTextColor(0, 0, 0);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(50, 8, mb_convert_encoding('Token Validación:', 'ISO-8859-1', 'UTF-8'), 0, 0);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $t['token_iofa'], 0, 1);
    
    $pdf->Ln(20);
    
    // Inyectar ambas firmas en la misma fila
    $y_firmas = $pdf->GetY();
    if (!empty($t['firma_paciente']) && strpos($t['firma_paciente'], 'data:image') === 0) {
        $b64 = $t['firma_paciente'];
        list($type, $b64) = explode(';', $b64);
        list(, $b64)      = explode(',', $b64);
        $tmp_file = 'tickets/tmp_paciente_'.uniqid().'.png';
        if (!file_exists('tickets')) { mkdir('tickets', 0777, true); }
        file_put_contents($tmp_file, base64_decode($b64));
        $pdf->Image($tmp_file, 35, $y_firmas, 40, 15);
        unlink($tmp_file);
    }

    if (!empty($firma_doctor) && file_exists('uploads/firmas/' . $firma_doctor)) {
        $pdf->Image('uploads/firmas/' . $firma_doctor, 135, $y_firmas, 40, 15);
    }
    
    $pdf->Ln(18);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetX(25);
    $pdf->Cell(60, 5, mb_convert_encoding('___________________________', 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    $pdf->SetX(125);
    $pdf->Cell(60, 5, mb_convert_encoding('___________________________', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetX(25);
    $nombre_paciente_aclaracion = mb_strtoupper($t['apellido'], 'UTF-8') . ', ' . $t['nombre'];
    $pdf->Cell(60, 5, mb_convert_encoding('Firma del Paciente / Titular', 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    $pdf->SetX(125);
    $nombre_medico = !empty($t['profesional']) ? mb_strtoupper($t['profesional'], 'UTF-8') : 'POLICLÍNICA ACTIS';
    $pdf->Cell(60, 5, mb_convert_encoding('Firma y Sello: ' . $nombre_medico, 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');

    $pdf->SetFont('Arial', 'I', 7);
    $pdf->SetX(25);
    $pdf->Cell(60, 4, mb_convert_encoding('Aclaración: ' . $nombre_paciente_aclaracion, 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

    // Bloque Inferior y QR
    $pdf->SetY(245);
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->Cell(0, 5, mb_convert_encoding('DOCUMENTO AUDITABLE - CENTRAL DE TURNOS', 'ISO-8859-1', 'UTF-8'), 0, 1, 'L');
    
    $hash_verificacion = md5($t['id'] . $t['dni'] . 'ACTIS_SECURE_TOKEN_2026');
    $url_validacion = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/validacion_constancia.php?id=" . $t['id'] . "&h=" . $hash_verificacion;
    $url_qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($url_validacion);
    $pdf->Image($url_qr_api, 10, 252, 22, 22, 'PNG');
    
    $pdf->SetXY(35, 253);
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetTextColor(20, 73, 115);
    $pdf->Cell(160, 4, mb_convert_encoding('CERTIFICADO DIGITAL DE ASISTENCIA - ACTIS CORE SECURE', 'ISO-8859-1', 'UTF-8'), 0, 1, 'L');
    $pdf->SetX(35);
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(160, 4, mb_convert_encoding('Escanee el código QR para verificar la autenticidad, trazabilidad y estado de este certificado en tiempo real.', 'ISO-8859-1', 'UTF-8'), 0, 1, 'L');
    $pdf->SetX(35);
    $pdf->Cell(160, 4, mb_convert_encoding('La adulteración de este documento constituye un delito penado por la ley. Protegido bajo Ley N° 25.326.', 'ISO-8859-1', 'UTF-8'), 0, 1, 'L');
    $pdf->SetX(35);
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell(160, 4, 'HASH CRIPTOGRÁFICO (MD5): ' . strtoupper($hash_verificacion), 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);

    // 5. GUARDAR Y ENVIAR EL CORREO AL PACIENTE CON SU CONSTANCIA OFICIAL
    if(!file_exists('tickets')) { mkdir('tickets', 0777, true); }
    $pdf_file = 'tickets/constancia_asistencia_' . $tid . '.pdf';
    $pdf->Output('F', $pdf_file);

    if (!empty($t['email']) && filter_var($t['email'], FILTER_VALIDATE_EMAIL)) {
        $asunto = "Constancia de Asistencia Médica - ACTIS";
        $cuerpoHTML = "
        <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;'>
            <div style='background-color: #144973; padding: 20px; text-align: center;'>
                <h2 style='color: #ffffff; margin: 0;'>Policlínica General ACTIS</h2>
            </div>
            <div style='padding: 20px;'>
                <p>Hola <strong>" . mb_strtoupper($t['apellido'], 'UTF-8') . ", " . htmlspecialchars($t['nombre']) . "</strong>,</p>
                <p>Adjunto a este correo electrónico enviamos su <strong>Constancia de Asistencia Médica</strong> firmada digitalmente, correspondiente a su atención del día " . date('d/m/Y', strtotime($t['fecha_turno'])) . ".</p>
                <p>El documento cuenta con un código de trazabilidad único y un código QR para su validación electrónica en caso de ser requerido por su obra social o empleador.</p>
                <p>Saludos cordiales,<br><strong>Departamento de Admisión - ACTIS</strong></p>
            </div>
        </div>";
        enviarCorreoNativo($t['email'], $asunto, $cuerpoHTML, null, $pdf_file);
    }
    
    if(file_exists($pdf_file)) { unlink($pdf_file); }

    echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Firma registrada con éxito',
                            text: 'La constancia ha sido actualizada y el correo enviado al paciente.',
                            confirmButtonColor: '#2563eb'
                        }).then(() => {
                            window.location.href = 'recepcion_salidas_lista.php';
                        });
                    });
                </script>";
                
                $q = $conexion->query("SELECT t.*, p.nombre, p.apellido, p.dni, p.email FROM turnos t INNER JOIN pacientes p ON t.paciente_id = p.id WHERE t.id = $tid");
    $turno = $q->fetch_assoc();
    
    $firma_medico_b64 = '';
    $id_medico_turno = (int)($turno['usuario_medico_id'] ?? 0);
    $q_doc = $conexion->query("SELECT firma_imagen_path FROM usuarios WHERE id = $id_medico_turno LIMIT 1");
    if($q_doc && $q_doc->num_rows > 0) {
        $img_name = $q_doc->fetch_assoc()['firma_imagen_path'];
        if(!empty($img_name) && file_exists('uploads/firmas/' . $img_name)) {
            $tipo = pathinfo('uploads/firmas/' . $img_name, PATHINFO_EXTENSION);
            $data = file_get_contents('uploads/firmas/' . $img_name);
            $firma_medico_b64 = 'data:image/' . $tipo . ';base64,' . base64_encode($data);
        }
    }

    if (!file_exists('tickets')) { mkdir('tickets', 0777, true); }
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->Cell(0, 15, mb_convert_encoding('POLICLÍNICA GENERAL ACTIS', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, mb_convert_encoding('CONSTANCIA DE ATENCIÓN FINAL', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 12);
    $texto = "Se deja constancia que el paciente " . $turno['nombre'] . " " . $turno['apellido'] . " (DNI " . $turno['dni'] . ") finalizó su atención en el servicio de " . $turno['servicio'] . " con el profesional " . $turno['profesional'] . ".";
    $pdf->MultiCell(0, 8, mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8'));
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, mb_convert_encoding('DIAGNÓSTICO REGISTRADO:', 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 8, mb_convert_encoding($turno['diagnostico'], 'ISO-8859-1', 'UTF-8'));
    $pdf->Ln(20);
    
    function inyectarFirma($pdf, $b64, $x, $y, $w, $h) {
        if(!empty($b64) && strpos($b64, 'data:image') === 0) {
            list($t, $b64) = explode(';', $b64); list(, $b64) = explode(',', $b64);
            $tmp = 'tickets/tmp_'.uniqid().'.png';
            file_put_contents($tmp, base64_decode($b64));
            $pdf->Image($tmp, $x, $y, $w, $h);
            unlink($tmp);
        }
    }
    
    $pdf->Ln(20);
    $y_firmas = $pdf->GetY();
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(95, 5, mb_convert_encoding('___________________________', 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    $pdf->Cell(95, 5, mb_convert_encoding('___________________________', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
    $pdf->Cell(95, 5, mb_convert_encoding('Firma del Paciente', 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    $pdf->Cell(95, 5, mb_convert_encoding('Firma del Profesional', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(95, 5, mb_convert_encoding('Aclaración: ' . $turno['nombre'] . ' ' . $turno['apellido'], 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    $pdf->Cell(95, 5, mb_convert_encoding('Aclaración: ' . $turno['profesional'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
    $pdf->Cell(95, 5, mb_convert_encoding('DNI: ' . $turno['dni'], 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    $pdf->Cell(95, 5, mb_convert_encoding('Médico Autorizante', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');

    inyectarFirma($pdf, $firma_base64, 32, $y_firmas - 18, 42, 16);
    inyectarFirma($pdf, $firma_medico_b64, 127, $y_firmas - 18, 42, 16);

    $ruta_pdf = 'tickets/checkout_' . $tid . '.pdf';
    $pdf->Output('F', $ruta_pdf);

    if(!empty($turno['email'])) {
        $url_pdf = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/" . $ruta_pdf;
        $cuerpo = "<div style='font-family: Arial; padding: 20px;'><h2>Atención Finalizada</h2><p>Hola ".$turno['nombre'].", adjuntamos tu constancia final firmada.</p><a href='$url_pdf' style='background:#2563eb; color:white; padding:10px 20px; text-decoration:none;'>Ver Documento Firmado</a>";
        if(!empty($turno['receta_ruta'])) {
            $url_rec = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/" . $turno['receta_ruta'];
            $cuerpo .= "<br><br><p>El médico te ha adjuntado una receta/indicación:</p><a href='$url_rec' style='background:#059669; color:white; padding:10px 20px; text-decoration:none;'>Descargar Receta</a>";
        }
        $cuerpo .= "</div>";
        enviarCorreoNativo($turno['email'], "Tu Constancia y Receta Médica - ACTIS", $cuerpo);
    }
    
    echo "<script>document.addEventListener('DOMContentLoaded', () => { Swal.fire({icon: 'success', title: '¡Firma Guardada!', text: 'El paciente firmó y se envió el correo.'}).then(() => { window.location = 'recepcion_salidas_lista.php'; }); });</script>";
}

// Interfaz Gráfica protegida contra errores
$q = $conexion->query("SELECT t.*, p.nombre, p.apellido, p.dni FROM turnos t INNER JOIN pacientes p ON t.paciente_id = p.id WHERE t.id = $turno_id");
if(!$q || $q->num_rows == 0) {
    echo "<div style='text-align:center; padding:50px;'><h2>Error: Turno no encontrado o ID incorrecto.</h2><a href='recepcion_salidas_lista.php' class='btn btn-primary'>Volver a la lista</a></div>";
    require_once 'includes/footer.php';
    exit;
}
$turno = $q->fetch_assoc();
?>
<div style="max-width: 800px; margin: 30px auto; background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
    <h2 style="color: #0f172a; text-align:center;"><i class="fa-solid fa-pen-nib" style="color: #3b82f6;"></i> Checkout de Paciente</h2>
    <p style="text-align:center; font-size:1.2rem; margin-bottom:20px;"><strong><?php echo $turno['nombre'].' '.$turno['apellido']; ?></strong> (DNI: <?php echo $turno['dni']; ?>)</p>
    
    <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
        <label style="font-weight:bold; color: #64748b; display:block; margin-bottom:5px;">Diagnóstico / Anotaciones del Médico:</label>
        <p style="margin:0; font-size:1.1rem; color:#0f172a;"><?php echo nl2br(htmlspecialchars($turno['diagnostico'])); ?></p>
        <?php if(!empty($turno['receta_ruta'])): ?>
            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #cbd5e1;">
                <span style="color:#059669; font-weight:bold;"><i class="fa-solid fa-paperclip"></i> Receta adjuntada por el profesional.</span>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
        /* Estilos del modal y canvas de Perfil adaptados */
        .modal-vanguardia { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 15px; box-sizing: border-box; }
        .modal-vanguardia.active { display: flex; }
        .modal-vanguardia-content { background: #ffffff; width: 100%; max-width: 700px; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.2); display: flex; flex-direction: column; animation: slideUp 0.2s ease-out; max-height: 90vh; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .modal-header-v { background: #f8fafc; color: #0f172a; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; font-size: 1.1rem; font-weight: 900; border-bottom: 2px solid #e2e8f0; }
        
        #canvasContainerPerfil { width: 100%; height: 35vh; min-height: 200px; background: #ffffff; position: relative; cursor: crosshair; overflow: hidden; }
        .firma-linea { position: absolute; top: 75%; left: 10%; right: 10%; border-bottom: 2px dashed #cbd5e1; z-index: 1; pointer-events: none; }
        .firma-texto { position: absolute; top: 78%; width: 100%; text-align: center; color: #94a3b8; font-weight: 800; font-size: 0.85rem; pointer-events: none; letter-spacing: 2px; }
        
        .btn-close-modal { background: #f1f5f9; border: none; color: #64748b; width: 36px; height: 36px; border-radius: 10px; font-size: 1.1rem; cursor: pointer; display: flex; justify-content: center; align-items: center; transition: background 0.2s; }
        .btn-close-modal:hover { background: #fee2e2; color: #ef4444; }
        .btn-outline { border: 2px solid #e2e8f0; background: white; color: #475569; padding: 10px 16px; border-radius: 10px; font-size: 0.95rem; font-weight: 800; cursor: pointer; display: inline-flex; gap: 8px; align-items: center; transition: 0.2s; }
        .btn-outline:hover { border-color: #144973; color: #144973; }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/signature_pad/1.5.3/signature_pad.min.js"></script>

    <form method="POST" id="formCheckout">
        <input type="hidden" name="turno_id" value="<?php echo $turno_id; ?>">
        <input type="hidden" name="firma_base64" id="firma_base64">
        
        <div style="background: #f8fafc; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; width: 100%; box-sizing: border-box; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <h4 style="margin: 0; font-size: 1.1rem; color: #0f172a; font-weight: 800;"><i class="fa-solid fa-pen-nib" style="color:#144973;"></i> Firma del Paciente</h4>
                <button type="button" class="btn-outline" onclick="abrirModalFirma()"><i class="fa-solid fa-signature"></i> Tomar Firma</button>
            </div>
            <div id="preview_firma_actual" style="height: 120px; background: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 2px dashed #cbd5e1; width: 100%; box-sizing: border-box;">
                <span style="color: #94a3b8; font-weight: 600; font-size: 0.9rem; text-align: center; padding: 10px;">El paciente aún no ha firmado.</span>
            </div>
        </div>
        
        <div style="display:flex; justify-content:center;">
            <button type="button" class="btn" style="background:#059669; color:white; padding:15px 40px; font-size:1.1rem; border-radius:8px; border: none; cursor:pointer; font-weight: bold; width: 100%; text-transform: uppercase;" onclick="enviarFormulario()"><i class="fa-solid fa-paper-plane"></i> Finalizar y Enviar</button>
        </div>
    </form>

    <div id="modalFirma" class="modal-vanguardia">
        <div class="modal-vanguardia-content">
            <div class="modal-header-v">
                <span><i class="fa-solid fa-pen-fancy" style="color: #144973;"></i> Registre su Firma</span>
                <button class="btn-close-modal" onclick="cerrarModalFirma()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="canvasContainerPerfil">
                <canvas id="pad" style="width:100%; height:100%; display:block; touch-action: none;"></canvas>
                <div class="firma-linea"></div>
                <div class="firma-texto">FIRME SOBRE LA LÍNEA</div>
            </div>
            <div style="padding: 15px; background: #f8fafc; display: flex; flex-wrap: wrap; gap: 10px; border-top: 2px solid #e2e8f0; width: 100%; box-sizing: border-box;">
                <button type="button" class="btn" style="background: #ef4444; color: white; flex: 1; margin: 0; padding:12px; border-radius:8px; border:none; cursor:pointer; font-weight:bold; font-size:1rem;" onclick="clearPad()"><i class="fa-solid fa-eraser"></i> Limpiar</button>
                <button type="button" class="btn" style="background: #10b981; color: white; flex: 1; margin: 0; padding:12px; border-radius:8px; border:none; cursor:pointer; font-weight:bold; font-size:1rem;" onclick="guardarFirma()"><i class="fa-solid fa-check"></i> Aceptar</button>
            </div>
        </div>
    </div>
</div>

<script>
    const canvas = document.getElementById('pad');
    const container = document.getElementById('canvasContainerPerfil');
    let signaturePad = null;

    function resizeCanvas() {
        if (!container || !canvas) return;
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = container.offsetWidth * ratio;
        canvas.height = container.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
        if (signaturePad) signaturePad.clear();
    }

    // Aseguramos que la firma no se desproporcione al girar el celular o pantalla
    window.addEventListener("resize", () => { 
        if (document.getElementById('modalFirma').classList.contains('active')) resizeCanvas(); 
    });

    function abrirModalFirma() {
        document.getElementById('modalFirma').classList.add('active');
        
        // Timeout vital para smartphones: espera a que el modal exista antes de medir su alto/ancho
        setTimeout(() => {
            resizeCanvas();
            if (!signaturePad) {
                signaturePad = new SignaturePad(canvas, { minWidth: 1.5, maxWidth: 3, penColor: "#0f172a" });
            }
        }, 50);
    }
    
    function cerrarModalFirma() { document.getElementById('modalFirma').classList.remove('active'); }
    
    function clearPad() { if(signaturePad) signaturePad.clear(); }
    
    function guardarFirma() {
        if (signaturePad.isEmpty()) { Swal.fire('Firma vacía', 'Por favor firme en el recuadro.', 'warning'); return; }
        const dataURL = signaturePad.toDataURL('image/png');
        // Guardamos en el formulario invisible y lo mostramos en la vista previa
        document.getElementById('firma_base64').value = dataURL;
        document.getElementById('preview_firma_actual').innerHTML = `<img src="${dataURL}" style="max-height: 100px; max-width: 100%; object-fit: contain;">`;
        cerrarModalFirma();
    }

    function enviarFormulario() {
        const firmaInput = document.getElementById('firma_base64').value;
        if (!firmaInput) {
            Swal.fire('Atención', 'El paciente debe registrar su firma primero haciendo clic en "Tomar Firma".', 'warning'); 
            return;
        }
        if (typeof mostrarLoader === 'function') mostrarLoader();
        document.getElementById('formCheckout').submit();
    }
</script>
<?php require_once 'includes/footer.php'; ?>