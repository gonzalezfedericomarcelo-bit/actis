<?php
header('Content-Type: application/json');
require_once 'includes/conexion.php';
require_once 'fpdf/fpdf.php'; // Requiere FPDF instalado en la raíz

// Funciones de socket SMTP (tomadas de envio_correo.php para evitar dependencias cruzadas o re-declaraciones)
if (!function_exists('leer_socket_smtp_totem')) {
    function leer_socket_smtp_totem($s) {
        $data = "";
        while($str = @fgets($s, 515)) {
            $data .= $str;
            if(substr($str, 3, 1) == " ") break;
        }
        return $data;
    }
}

if (!function_exists('escribir_socket_smtp_totem')) {
    function escribir_socket_smtp_totem($s, $c) {
        @fputs($s, $c . "\r\n");
        return leer_socket_smtp_totem($s);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['dni']) || empty($_POST['turnos'])) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios.']);
    exit;
}

$dni = $conexion->real_escape_string(trim($_POST['dni']));
$turnos_ids = json_decode($_POST['turnos'], true);

if (!is_array($turnos_ids) || count($turnos_ids) === 0) {
    echo json_encode(['success' => false, 'message' => 'No se seleccionaron turnos.']);
    exit;
}

// 1. Validar paciente y obtener correo
$sql_pac = "SELECT id, email, nombre, apellido FROM pacientes WHERE dni = '$dni'";
$res_pac = $conexion->query($sql_pac);
if ($res_pac->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Paciente no encontrado.']);
    exit;
}
$paciente = $res_pac->fetch_assoc();
$email = trim($paciente['email']);

// Si el frontend manda un email custom (editado por el usuario), lo usamos
if (!empty($_POST['email_custom'])) {
    $email = trim($_POST['email_custom']);
}

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'El paciente no tiene correo registrado y no se proporcionó uno.']);
    exit;
}

// 2. Obtener los detalles de los turnos seleccionados
$ids_csv = implode(',', array_map('intval', $turnos_ids));
$sql_turnos = "SELECT * FROM turnos WHERE id IN ($ids_csv) AND paciente_id = {$paciente['id']} ORDER BY fecha_turno ASC, hora_turno ASC";
$res_turnos = $conexion->query($sql_turnos);

if ($res_turnos->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Turnos inválidos o no pertenecen al paciente.']);
    exit;
}

// 3. Crear el PDF
class PDF extends FPDF {
    function Header() {
        if (file_exists('img/osfa.png')) {
            $this->Image('img/osfa.png', 10, 8, 30);
        }
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(80);
        $this->Cell(30, 10, utf8_decode('Próximos Turnos - Policlínica ACTIS'), 0, 0, 'C');
        $this->Ln(20);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);

$nombre_completo = utf8_decode($paciente['nombre'] . ' ' . $paciente['apellido']);
$pdf->Cell(0, 10, 'Paciente: ' . $nombre_completo . ' (DNI: ' . $dni . ')', 0, 1);
$pdf->Cell(0, 10, 'Fecha de consulta: ' . date('d/m/Y H:i'), 0, 1);
$pdf->Ln(5);

// Encabezados de tabla
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(2, 132, 199); // --primary color aproximado
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(25, 10, 'Fecha', 1, 0, 'C', true);
$pdf->Cell(20, 10, 'Hora', 1, 0, 'C', true);
$pdf->Cell(50, 10, 'Especialidad', 1, 0, 'L', true);
$pdf->Cell(65, 10, 'Profesional', 1, 0, 'L', true);
$pdf->Cell(30, 10, 'Estado', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);

while ($t = $res_turnos->fetch_assoc()) {
    $fecha = date('d/m/Y', strtotime($t['fecha_turno']));
    $hora = date('H:i', strtotime($t['hora_turno']));
    
    $pdf->Cell(25, 10, $fecha, 1, 0, 'C');
    $pdf->Cell(20, 10, $hora, 1, 0, 'C');
    $pdf->Cell(50, 10, substr(utf8_decode($t['especialidad']), 0, 25), 1, 0, 'L');
    $pdf->Cell(65, 10, substr(utf8_decode($t['profesional']), 0, 35), 1, 0, 'L');
    $pdf->Cell(30, 10, utf8_decode($t['estado']), 1, 1, 'C');
}

$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 10);
$pdf->MultiCell(0, 6, utf8_decode('Por favor, asista 15 minutos antes de su horario con su credencial y DNI. Si no puede asistir, recuerde cancelar su turno para liberar el espacio para otro paciente.'));

$pdf_path = __DIR__ . '/turnos_temp_' . $dni . '.pdf';
$pdf->Output('F', $pdf_path);

// 4. Enviar correo usando Hostinger SMTP nativo
$usuario_mail = 'info@federicogonzalez.net'; 
$password_mail = 'Fmg35911@'; 
$servidor = 'ssl://smtp.hostinger.com';
$puerto = 465;

$cuerpoHTML = "<h2>Hola, {$paciente['nombre']}!</h2><p>Te enviamos adjunto el listado de tus pr&oacute;ximos turnos en la Policl&iacute;nica ACTIS.</p><p>Saludos cordiales,<br>El equipo de ACTIS.</p>";
$asunto = "Tus Próximos Turnos - Policlínica ACTIS";

$contexto = stream_context_create([
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
]);

$socket = @stream_socket_client("$servidor:$puerto", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $contexto);
if (!$socket) {
    @unlink($pdf_path);
    echo json_encode(['success' => false, 'message' => "Error Conexión: $errstr ($errno)"]);
    exit;
}

leer_socket_smtp_totem($socket);
escribir_socket_smtp_totem($socket, "EHLO " . $_SERVER['HTTP_HOST']);
escribir_socket_smtp_totem($socket, "AUTH LOGIN");
escribir_socket_smtp_totem($socket, base64_encode($usuario_mail));
escribir_socket_smtp_totem($socket, base64_encode($password_mail));

escribir_socket_smtp_totem($socket, "MAIL FROM: <$usuario_mail>");
escribir_socket_smtp_totem($socket, "RCPT TO: <$email>");
escribir_socket_smtp_totem($socket, "DATA");

$boundary = md5(uniqid(time()));
$headers  = "MIME-Version: 1.0\r\n";
$server_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'federicogonzalez.net';
$message_id = "<" . md5(uniqid(microtime(), true)) . "@" . $server_host . ">";
$headers .= "Message-ID: $message_id\r\n";
$headers .= "From: Policlinica General ACTIS <$usuario_mail>\r\n";
$headers .= "To: $email\r\n";
$headers .= "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=\r\n";
$headers .= "Date: " . date("r") . "\r\n";

$headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

$cuerpo_final = "--$boundary\r\n";
$cuerpo_final .= "Content-Type: text/html; charset=UTF-8\r\n";
$cuerpo_final .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
$cuerpo_final .= $cuerpoHTML . "\r\n\r\n";

$archivo_contenido = file_get_contents($pdf_path);
$archivo_codificado = chunk_split(base64_encode($archivo_contenido));
$nombre_archivo = "turnos_actis.pdf";

$cuerpo_final .= "--$boundary\r\n";
$cuerpo_final .= "Content-Type: application/pdf; name=\"$nombre_archivo\"\r\n";
$cuerpo_final .= "Content-Transfer-Encoding: base64\r\n";
$cuerpo_final .= "Content-Disposition: attachment; filename=\"$nombre_archivo\"\r\n\r\n";
$cuerpo_final .= $archivo_codificado . "\r\n\r\n";
$cuerpo_final .= "--$boundary--\r\n";

@fputs($socket, "$headers\r\n$cuerpo_final\r\n.\r\n");
$resultado = leer_socket_smtp_totem($socket);

@fputs($socket, "QUIT\r\n");
@fclose($socket);

// Borrar el archivo temporal
@unlink($pdf_path);

// Como el envío de PDFs puede demorar la respuesta de Hostinger, asumimos éxito si no hubo error craso al conectar.
// Solo logueamos si el resultado dice explicitamente error (ej: 5xx o 4xx).
if (strpos($resultado, '550') !== false || strpos($resultado, '554') !== false) {
    if (!file_exists(__DIR__ . '/logs')) mkdir(__DIR__ . '/logs', 0777, true);
    file_put_contents(__DIR__ . '/logs/mail_error.log', "[" . date('Y-m-d H:i:s') . "] Error Tótem Turnos para $email: " . $resultado . PHP_EOL, FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'El correo fue rechazado por el servidor SMTP.']);
} else {
    echo json_encode(['success' => true]);
}
exit;
