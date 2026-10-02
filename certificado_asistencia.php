<?php
require_once 'includes/conexion.php';
require_once 'fpdf/fpdf.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
// Permitimos que se genere si el paciente está Presente o Atendido
$sql = "SELECT t.*, p.nombre, p.apellido, p.dni FROM turnos t INNER JOIN pacientes p ON t.paciente_id = p.id WHERE t.id = $id AND (t.estado = 'Presente' OR t.estado = 'Atendido')";
$res = $conexion->query($sql);

if($res && $res->num_rows > 0) {
    $t = $res->fetch_assoc();
    
    // Motor de búsqueda de la firma del médico asociado a la atención
    $medico_user_id = isset($t['usuario_medico_id']) ? (int)$t['usuario_medico_id'] : 0;
    $id_fallback = isset($t['usuario_recepcion_id']) ? (int)$t['usuario_recepcion_id'] : (isset($t['usuario_creador_id']) ? (int)$t['usuario_creador_id'] : 0);
    $medico_esc = $conexion->real_escape_string($t['profesional']);
    
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
    
    $firma_doctor = '';
    if ($q_doc && $q_doc->num_rows > 0) {
        $firma_doctor = $q_doc->fetch_assoc()['firma_imagen_path'];
    }
    
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    
    // 1. Marca de agua institucional (Centrada en la hoja)
    if(file_exists('img/osfa-marca-de-agua.png')) {
        $pdf->Image('img/osfa-marca-de-agua.png', 45, 80, 120);
    }
    
    // 2. Logo Superior Izquierdo
    if(file_exists('img/osfa_negro.png')) {
        $pdf->Image('img/osfa_negro.png', 10, 10, 30);
    }
    
    // 3. Títulos Institucionales
    $pdf->SetY(15);
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->Cell(0, 10, mb_convert_encoding('POLICLÍNICA GENERAL ACTIS', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 8, mb_convert_encoding('CONSTANCIA DE ASISTENCIA MÉDICA', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
    
    $pdf->Ln(20);
    
    // 4. Texto del Certificado (Acentos y Ñ corregidos)
    $pdf->SetFont('Arial', '', 12);
    $apellido_iso = mb_convert_encoding(mb_strtoupper($t['apellido'], 'UTF-8'), 'ISO-8859-1', 'UTF-8');
    $nombre_iso = mb_convert_encoding($t['nombre'], 'ISO-8859-1', 'UTF-8');
    $servicio_iso = mb_convert_encoding(mb_strtoupper($t['servicio'], 'UTF-8'), 'ISO-8859-1', 'UTF-8');
    
    $texto = mb_convert_encoding("Por la presente se deja constancia que el/la paciente ", 'ISO-8859-1', 'UTF-8') . $apellido_iso . ", " . $nombre_iso . mb_convert_encoding(" con DNI Nro ", 'ISO-8859-1', 'UTF-8') . $t['dni'] . mb_convert_encoding(", se ha presentado en el día de la fecha en nuestra institución para recibir atención en el servicio de ", 'ISO-8859-1', 'UTF-8') . $servicio_iso . ".";
    
    $pdf->MultiCell(0, 8, $texto);
    
    $pdf->Ln(15);
    
    // 5. Datos precisos de la consulta
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(50, 8, mb_convert_encoding('Fecha del Turno:', 'ISO-8859-1', 'UTF-8'), 0, 0);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, date("d/m/Y", strtotime($t['fecha_turno'])), 0, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(50, 8, mb_convert_encoding('Hora de Ingreso:', 'ISO-8859-1', 'UTF-8'), 0, 0);
    $pdf->SetFont('Arial', '', 12);
    $hora_ingreso = !empty($t['recepcionado_el']) ? date("H:i", strtotime($t['recepcionado_el'])) . " hs" : "Hora no registrada";
    $pdf->Cell(0, 8, $hora_ingreso, 0, 1);
    
    // NÚMERO DE ORDEN TICKET
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(50, 8, mb_convert_encoding('N° de Orden:', 'ISO-8859-1', 'UTF-8'), 0, 0);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(37, 99, 235); // Azul para que resalte
    $orden = !empty($t['codigo_ticket_totem']) ? $t['codigo_ticket_totem'] : "NO ASIGNADO";
    $pdf->Cell(0, 8, $orden, 0, 1);
    $pdf->SetTextColor(0, 0, 0); // Restaurar a negro

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(50, 8, mb_convert_encoding('Token Validación:', 'ISO-8859-1', 'UTF-8'), 0, 0);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $t['token_iofa'], 0, 1);
    
    $pdf->Ln(20);
    
    // 6. Firmas en Fila: Paciente y Médico
    $pdf->Ln(5);
    $y_firmas = $pdf->GetY();
    
    // Firma del Paciente (Trazo capturado en Recepción Salida)
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

    // Firma del Médico Profesional
    if (!empty($firma_doctor) && file_exists('uploads/firmas/' . $firma_doctor)) {
        $pdf->Image('uploads/firmas/' . $firma_doctor, 135, $y_firmas, 40, 15);
    }
    
    $pdf->Ln(18); // Espacio físico bajo las imágenes
    
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

    // 7. Bloque inferior de Criptografía y Código QR
    $pdf->SetY(245);
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->Cell(0, 5, mb_convert_encoding('DOCUMENTO AUDITABLE - CENTRAL DE TURNOS', 'ISO-8859-1', 'UTF-8'), 0, 1, 'L');
    
    $hash_verificacion = md5($t['id'] . $t['dni'] . 'ACTIS_SECURE_TOKEN_2026');
    
    // QR APUNTANDO AL NUEVO ARCHIVO DE VALIDACIÓN (Paso 2)
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

    $pdf->Output('I', 'Constancia_'.$t['dni'].'.pdf');
} else {
    die("Error: Turno no válido o el paciente aún no tiene asistencia marcada.");
}
?>