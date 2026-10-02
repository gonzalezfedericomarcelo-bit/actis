<?php
// Archivo: envio_correo.php

if (!function_exists('leer_socket_smtp')) {
    function leer_socket_smtp($s) {
        $data = "";
        while($str = @fgets($s, 515)) {
            $data .= $str;
            if(substr($str, 3, 1) == " ") break;
        }
        return $data;
    }
}

if (!function_exists('escribir_socket_smtp')) {
    function escribir_socket_smtp($s, $c) {
        @fputs($s, $c . "\r\n");
        return leer_socket_smtp($s);
    }
}

function enviarCorreoNativo($destinatario, $asunto, $cuerpoHTML, $replyTo = null, $adjuntoPath = null) {
    // --- TUS DATOS REALES ---
    $usuario_mail = 'info@federicogonzalez.net'; 
    $password_mail = 'Fmg35911@'; 
    $servidor = 'ssl://smtp.hostinger.com';
    $puerto = 465;
    // ------------------------

    $contexto = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $socket = @stream_socket_client("$servidor:$puerto", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $contexto);

    if (!$socket) {
        return "Error Conexión: $errstr ($errno)";
    }

    leer_socket_smtp($socket);
    escribir_socket_smtp($socket, "EHLO " . $_SERVER['HTTP_HOST']);
    escribir_socket_smtp($socket, "AUTH LOGIN");
    escribir_socket_smtp($socket, base64_encode($usuario_mail));
    escribir_socket_smtp($socket, base64_encode($password_mail));

    escribir_socket_smtp($socket, "MAIL FROM: <$usuario_mail>");
    escribir_socket_smtp($socket, "RCPT TO: <$destinatario>");
    escribir_socket_smtp($socket, "DATA");

    $boundary = md5(uniqid(time()));
    $headers  = "MIME-Version: 1.0\r\n";
    
    // --- INICIO FIX PARA YAHOO ---
    // Generamos un ID de mensaje único estándar RFC
    $server_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'federicogonzalez.net';
    $message_id = "<" . md5(uniqid(microtime(), true)) . "@" . $server_host . ">";
    $headers .= "Message-ID: $message_id\r\n";
    // --- FIN FIX PARA YAHOO ---

    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers .= "Reply-To: $replyTo\r\n";
    }

    // MODIFICADO: Quitamos la tilde a 'Policlinica' para evitar bloqueos de codificación MIME en Yahoo
    $headers .= "From: Policlinica General ACTIS <$usuario_mail>\r\n";
    $headers .= "To: $destinatario\r\n";
    $headers .= "Subject: $asunto\r\n";
    $headers .= "Date: " . date("r") . "\r\n";

    if ($adjuntoPath && file_exists($adjuntoPath)) {
        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
        
        $cuerpo_final = "--$boundary\r\n";
        $cuerpo_final .= "Content-Type: text/html; charset=UTF-8\r\n";
        $cuerpo_final .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $cuerpo_final .= $cuerpoHTML . "\r\n\r\n";
        
        $archivo_contenido = file_get_contents($adjuntoPath);
        $archivo_codificado = chunk_split(base64_encode($archivo_contenido));
        $nombre_archivo = basename($adjuntoPath);
        
        $cuerpo_final .= "--$boundary\r\n";
        $cuerpo_final .= "Content-Type: application/pdf; name=\"$nombre_archivo\"\r\n";
        $cuerpo_final .= "Content-Transfer-Encoding: base64\r\n";
        $cuerpo_final .= "Content-Disposition: attachment; filename=\"$nombre_archivo\"\r\n\r\n";
        $cuerpo_final .= $archivo_codificado . "\r\n\r\n";
        $cuerpo_final .= "--$boundary--\r\n";
    } else {
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $cuerpo_final = $cuerpoHTML;
    }

    @fputs($socket, "$headers\r\n$cuerpo_final\r\n.\r\n");
    $resultado = leer_socket_smtp($socket);
    
    @fputs($socket, "QUIT\r\n");
    @fclose($socket);

    if (strpos($resultado, '250') !== false) {
        return true;
    } else {
        if (!file_exists(__DIR__ . '/logs')) {
            mkdir(__DIR__ . '/logs', 0777, true);
        }
        file_put_contents(__DIR__ . '/logs/mail_error.log', "[" . date('Y-m-d H:i:s') . "] Error para $destinatario: " . $resultado . PHP_EOL, FILE_APPEND);
        return "Error SMTP: " . $resultado;
    }
}
?>