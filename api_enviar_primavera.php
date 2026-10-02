<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['plan'])) {
    echo json_encode(['status' => 'error', 'message' => 'Falta el plan seleccionado']);
    exit;
}

$plan_elegido = trim($data['plan']);

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
$asunto = "🌸 ¡MILI ELIGIÓ UN PLAN PARA LA PRIMAVERA! 🌸";

$cuerpoHTML = "
<div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; border: 2px solid #f48fb1; border-radius: 15px; padding: 20px; background-color: #fce4ec; text-align: center;'>
    <h1 style='color: #d81b60;'>¡Tenés una respuesta! 💌</h1>
    <p style='font-size: 18px; color: #4a4a4a;'>Mili acaba de escanear el ticket del tótem y seleccionó la siguiente opción para salir:</p>
    
    <div style='background: white; border-radius: 10px; padding: 20px; margin: 20px 0; font-size: 24px; font-weight: bold; color: #d81b60; box-shadow: 0 4px 10px rgba(0,0,0,0.1);'>
        $plan_elegido
    </div>
    
    <p style='font-size: 14px; color: #888;'>¡En cualquier momento te llega el mensaje de WhatsApp!</p>
</div>
";

$exito = enviarEmailNativo($destinatario, $asunto, $cuerpoHTML);

if ($exito) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error al enviar correo']);
}
?>
