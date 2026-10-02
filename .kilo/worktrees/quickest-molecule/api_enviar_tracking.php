<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
$userAgent = $data['userAgent'] ?? 'Desconocido';
$platform = $data['platform'] ?? 'Desconocida';
$language = $data['language'] ?? 'Desconocido';
$screenResolution = $data['screenResolution'] ?? 'Desconocida';
$timezone = $data['timezone'] ?? 'Desconocida';

function enviarEmailNativo($destinatario, $asunto, $cuerpoHTML) {
    $usuario_mail = 'info@federicogonzalez.net'; 
    $password_mail = 'Fmg35911@'; 
    $servidor = 'ssl://smtp.hostinger.com';
    $puerto = 465;

    $contexto = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $socket = @stream_socket_client("$servidor:$puerto", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $contexto);
    if (!$socket) {
        return false;
    }

    function leer_socket($s) {
        $data = "";
        while($str = @fgets($s, 515)) {
            $data .= $str;
            if(substr($str, 3, 1) == " ") break;
        }
        return $data;
    }

    function escribir_socket($s, $c) {
        @fputs($s, $c . "\r\n");
        return leer_socket($s);
    }

    leer_socket($socket);
    escribir_socket($socket, "EHLO federicogonzalez.net");
    escribir_socket($socket, "AUTH LOGIN");
    escribir_socket($socket, base64_encode($usuario_mail));
    escribir_socket($socket, base64_encode($password_mail));

    escribir_socket($socket, "MAIL FROM: <$usuario_mail>");
    escribir_socket($socket, "RCPT TO: <$destinatario>");
    escribir_socket($socket, "DATA");

    $asunto_codificado = "=?UTF-8?B?" . base64_encode($asunto) . "?=";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "From: Primavera <$usuario_mail>\r\n";
    $headers .= "To: $destinatario\r\n";
    $headers .= "Subject: $asunto_codificado\r\n";
    $headers .= "Date: " . date("r") . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    @fputs($socket, "$headers\r\n$cuerpoHTML\r\n.\r\n");
    $resultado = leer_socket($socket);
    
    @fputs($socket, "QUIT\r\n");
    @fclose($socket);

    return strpos($resultado, '250') !== false;
}

$destinatario = 'gonzalezmarcelo159@gmail.com';
$asunto = "👀 ¡MILI ESCANEÓ EL QR! 👀 (Alerta Temprana)";

$cuerpoHTML = "
<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 2px solid #6a1b9a; border-radius: 15px; padding: 20px; background-color: #f3e5f5;'>
    <h1 style='color: #4a148c; text-align: center;'>¡Alerta Temprana! 📱</h1>
    <p style='font-size: 16px; color: #4a4a4a;'>El QR del ticket acaba de ser escaneado. La página de primavera.php ha sido abierta.</p>
    
    <div style='background: white; border-radius: 10px; padding: 15px; margin: 20px 0; font-size: 14px; color: #333; box-shadow: 0 4px 10px rgba(0,0,0,0.1);'>
        <h3 style='margin-top: 0; color: #6a1b9a;'>Datos del Dispositivo Escaneador:</h3>
        <ul style='list-style-type: none; padding: 0; margin: 0;'>
            <li style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>IP:</strong> $ip</li>
            <li style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Resolución de pantalla:</strong> $screenResolution</li>
            <li style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Sistema / Plataforma:</strong> $platform</li>
            <li style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Navegador / Dispositivo:</strong> $userAgent</li>
            <li style='padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Idioma:</strong> $language</li>
            <li style='padding: 8px 0;'><strong>Zona Horaria:</strong> $timezone</li>
        </ul>
    </div>
    
    <p style='font-size: 13px; color: #888; text-align: center;'>Esta es una notificación automática silenciosa del backend de Actis.</p>
</div>
";

$exito = enviarEmailNativo($destinatario, $asunto, $cuerpoHTML);

if ($exito) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error al enviar correo']);
}
?>
