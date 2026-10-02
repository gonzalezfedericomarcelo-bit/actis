<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'fpdf/fpdf.php';

$fecha = isset($_GET['fecha']) ? $conexion->real_escape_string($_GET['fecha']) : date('Y-m-d');
$filtro_medico_id = isset($_GET['medico_id']) ? (int)$_GET['medico_id'] : 0;
$busqueda = isset($_GET['q']) ? $conexion->real_escape_string(trim($_GET['q'])) : '';
$nombre_medico = isset($_SESSION['nombre']) ? $conexion->real_escape_string($_SESSION['nombre']) : '';
$id_logueado = (int)$_SESSION['usuario_id'];

// Filtramos los presentes y atendidos dinámicamente
$sql = "SELECT t.*, p.nombre, p.apellido, p.dni FROM turnos t INNER JOIN pacientes p ON t.paciente_id = p.id WHERE (t.estado = 'Presente' OR t.estado = 'Atendido')";

if (!empty($fecha) && $fecha !== 'todas') {
    $sql .= " AND t.fecha_turno = '$fecha'";
}
if ($filtro_medico_id > 0) { 
    $sql .= " AND (t.usuario_medico_id = $filtro_medico_id OR t.profesional = '$nombre_medico')"; 
}
if (!empty($busqueda)) {
    $sql .= " AND (p.dni LIKE '%$busqueda%' OR p.nombre LIKE '%$busqueda%' OR p.apellido LIKE '%$busqueda%')";
}
$sql .= " ORDER BY t.profesional, t.hora_turno ASC";

$res = $conexion->query($sql);

$turnos_por_medico = [];
while($row = $res->fetch_assoc()){
    $turnos_por_medico[$row['profesional']][] = $row;
}

// Inicializamos FPDF en Formato Apaisado (L)
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetAutoPageBreak(true, 5);

// Motor para inyectar las firmas digitales de la tablet (Base64)
function inyectarFirmaPaciente($pdf, $b64, $x, $y, $w, $h) {
    if(!empty($b64) && strpos($b64, 'data:image') === 0) {
        list($t, $b64) = explode(';', $b64); 
        list(, $b64) = explode(',', $b64);
        $tmp = 'tickets/tmp_paciente_'.uniqid().'.png';
        file_put_contents($tmp, base64_decode($b64));
        $pdf->Image($tmp, $x, $y, $w, $h);
        unlink($tmp);
    }
}

if(empty($turnos_por_medico)) {
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'SIN ATENCIONES REGISTRADAS PARA LA FECHA SELECCIONADA', 0, 1, 'C');
}

foreach($turnos_por_medico as $medico => $turnos) {
    $pdf->AddPage();
    
    // 1. Marca de agua institucional PROPORCIONAL y centrada en ambos ejes (X e Y)
    if(file_exists('img/osfa-marca-de-agua.png')) {
        // Aumentamos el ancho a 150mm para que sea más grande (más alta y ancha).
        // Nuevo cálculo horizontal para mantener el centro: (297 - 150) / 2 = 73.5
        // Ajustamos Y a 60 para compensar el nuevo tamaño y mantenerla centrada verticalmente.
        $pdf->Image('img/osfa-marca-de-agua.png', 73.5, 75, 150);
    }
    
    // 2. Logo Principal
    if(file_exists('img/osfa_negro.png')) {
        $pdf->Image('img/osfa_negro.png', 10, 10, 30);
    }
    
    // 3. Encabezado de Centro Médico (Centrado absoluto en la hoja)
    // Se baja a Y=18 para quedar a la misma altura visual del centro del logo
    $pdf->SetY(18);
    $pdf->SetFont('Arial', 'B', 13); // Un punto más grande para que destaque
    $pdf->Cell(0, 8, mb_convert_encoding('CENTRO MÉDICO: POLICLÍNICA GENERAL ACTIS', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
    
    // 4. Datos de Control (Bajamos la coordenada Y de 26 a 35 para generar el espacio en blanco)
    $pdf->SetXY(10, 35);
    $especialidad = isset($turnos[0]['especialidad']) ? $turnos[0]['especialidad'] : 'GENERAL';
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(105, 6, mb_convert_encoding('PROFESIONAL: ' . strtoupper($medico), 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
    $pdf->Cell(95, 6, mb_convert_encoding('ESPECIALIDAD: ' . strtoupper($especialidad), 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
    $pdf->Cell(77, 6, mb_convert_encoding('FECHA: ' . date('d/m/Y', strtotime($fecha)), 'ISO-8859-1', 'UTF-8'), 0, 1, 'R');
    
    $pdf->Ln(2);

    // 5. Cabecera Tabla (Ancho Total = 277mm)
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(10, 6, mb_convert_encoding('Nº', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C');
    $pdf->Cell(15, 6, 'HORA', 1, 0, 'C');
    $pdf->Cell(65, 6, 'NOMBRE Y APELLIDO', 1, 0, 'C');
    $pdf->Cell(25, 6, 'DNI', 1, 0, 'C');
    $pdf->Cell(40, 6, mb_convert_encoding('COD. VALIDACIÓN', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C');
    $pdf->Cell(82, 6, mb_convert_encoding('DIAGNÓSTICO', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C');
    $pdf->Cell(40, 6, mb_convert_encoding('FIRMA Y ACLAR.', 'ISO-8859-1', 'UTF-8'), 1, 1, 'C');

    // 6. Filas Compactas (Altura 7mm. La tabla termina exactamente en Y=153mm)
    $pdf->SetFont('Arial', '', 7.5);
    $row_height = 7.0;
    
    for($i = 0; $i < 16; $i++) {
        $x = $pdf->GetX(); $y = $pdf->GetY();
        
        $num = $i + 1;
        $h = isset($turnos[$i]) ? date('H:i', strtotime($turnos[$i]['hora_turno'])) : '';
        $nom = isset($turnos[$i]) ? mb_convert_encoding(substr($turnos[$i]['apellido'].', '.$turnos[$i]['nombre'], 0, 38), 'ISO-8859-1', 'UTF-8') : '';
        $dni = isset($turnos[$i]) ? $turnos[$i]['dni'] : '';
        $cod = isset($turnos[$i]) ? strtoupper($turnos[$i]['token_iofa']) : '';
        $diag = isset($turnos[$i]) ? mb_convert_encoding(substr($turnos[$i]['diagnostico'] ?? '', 0, 68), 'ISO-8859-1', 'UTF-8') : '';
        
        $pdf->Cell(10, $row_height, $num, 1, 0, 'C');
        $pdf->Cell(15, $row_height, $h, 1, 0, 'C');
        $pdf->Cell(65, $row_height, $nom, 1, 0, 'L');
        $pdf->Cell(25, $row_height, $dni, 1, 0, 'C');
        $pdf->Cell(40, $row_height, $cod, 1, 0, 'C');
        $pdf->Cell(82, $row_height, $diag, 1, 0, 'L');
        $pdf->Cell(40, $row_height, '', 1, 1, 'C');
        
        // Firma digital del paciente capturada en tablet
        if(isset($turnos[$i]['firma_paciente'])) {
            inyectarFirmaPaciente($pdf, $turnos[$i]['firma_paciente'], $x + 238, $y + 0.5, 36, 6.0);
        }
    }

    // 7. Pie de Página Seguro con Posicionamiento Absoluto (Y=176mm)
    $pdf->SetY(176);
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->Cell(120, 5, 'PACIENTES VALIDADOS POR CENTRAL DE TURNOS', 0, 0, 'L');
    $pdf->Cell(157, 5, 'FIRMA Y SELLO DEL PROFESIONAL', 0, 1, 'R');
    
    $pdf->SetFont('Arial', '', 8.5);
    $pdf->Cell(277, 5, mb_convert_encoding('Aclaración: ' . strtoupper($medico), 'ISO-8859-1', 'UTF-8'), 0, 1, 'R');
    $pdf->Line(220, 176, 287, 176); // Línea de firma firme
    
    // Bloque Criptográfico de Seguridad Pública e Inyección de QR hacia URL Web
    $hash_verificacion = md5($medico . $fecha . 'ACTIS_SECURE_TOKEN_2026');
    
    // Generar URL que leerá el QR (Apunta a la nueva página pública)
    $url_validacion = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/planilla_validacion.php?m=" . urlencode($medico) . "&f=" . $fecha . "&h=" . $hash_verificacion;
    $url_qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($url_validacion);
    
    // QR ligeramente ampliado a 18mm para mejor lectura por celulares
    $pdf->Image($url_qr_api, 10, 182, 18, 18, 'PNG');
    
    // Textos Legales y de Seguridad Institucional
    $pdf->SetXY(30, 183);
    $pdf->SetFont('Arial', 'B', 6.5);
    $pdf->SetTextColor(20, 73, 115); // Azul oscuro institucional
    $pdf->Cell(160, 3, mb_convert_encoding('CERTIFICADO DIGITAL DE ATENCIÓN MÉDICA - ACTIS CORE SECURE', 'ISO-8859-1', 'UTF-8'), 0, 1, 'L');
    
    $pdf->SetX(30);
    $pdf->SetFont('Arial', '', 6);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(160, 3, mb_convert_encoding('Documento auditable. Escanee el código QR para verificar la autenticidad, trazabilidad y estado de esta hoja de ruta en tiempo real.', 'ISO-8859-1', 'UTF-8'), 0, 1, 'L');
    
    $pdf->SetX(30);
    $pdf->Cell(160, 3, mb_convert_encoding('La adulteración de este documento constituye un delito penado por la ley. Protegido bajo Ley de Protección de Datos Personales N° 25.326.', 'ISO-8859-1', 'UTF-8'), 0, 1, 'L');
    
    $pdf->SetX(30);
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Cell(160, 3, 'HASH CRIPTOGRAFICO (MD5-HEX): ' . strtoupper($hash_verificacion), 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0); // Restaurar color

    // Renderizado físico de la Firma Digital de la médica sobre su línea
    $medico_user_id = (isset($turnos[0]['usuario_medico_id']) && $turnos[0]['usuario_medico_id'] > 0) ? (int)$turnos[0]['usuario_medico_id'] : $id_logueado;
    $q_doc = $conexion->query("SELECT firma_imagen_path FROM usuarios WHERE id = $medico_user_id LIMIT 1");

    if((!$q_doc || $q_doc->num_rows == 0) && !empty($medico)) {
        $nombre_escapado = $conexion->real_escape_string($medico);
        $q_doc = $conexion->query("SELECT firma_imagen_path FROM usuarios WHERE nombre_completo = '$nombre_escapado' LIMIT 1");
    }

    if($q_doc && $q_doc->num_rows > 0) {
        $img_name = $q_doc->fetch_assoc()['firma_imagen_path'];
        if(!empty($img_name) && file_exists('uploads/firmas/' . $img_name)) {
            // Posicionada quirúrgicamente a los 158mm de altura (Perfectamente despegada de la tabla)
            $pdf->Image('uploads/firmas/' . $img_name, 232, 158, 40, 16);
        }
    }
}

$pdf->Output('I', 'Planilla_Diaria_ACTIS.pdf');
?>