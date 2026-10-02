<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'includes/conexion.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

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

function enviarAlertaNativa($destinatario, $asunto, $cuerpoHTML) {
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
        if(!is_dir(__DIR__ . '/logs')) mkdir(__DIR__ . '/logs', 0755, true);
        file_put_contents(__DIR__ . '/logs/mail_error.log', "[" . date('Y-m-d H:i:s') . "] Error Socket: $errstr ($errno)" . PHP_EOL, FILE_APPEND);
        return "Error de conexión SMTP.";
    }

    leer_socket_smtp($socket);
    escribir_socket_smtp($socket, "EHLO federicogonzalez.net");
    escribir_socket_smtp($socket, "AUTH LOGIN");
    escribir_socket_smtp($socket, base64_encode($usuario_mail));
    escribir_socket_smtp($socket, base64_encode($password_mail));

   escribir_socket_smtp($socket, "MAIL FROM: <$usuario_mail>");
    $lista_destinos = array_map('trim', explode(',', $destinatario));
    foreach($lista_destinos as $dest) {
        if(!empty($dest)) {
            escribir_socket_smtp($socket, "RCPT TO: <$dest>");
        }
    }
    escribir_socket_smtp($socket, "DATA");

    $asunto_codificado = "=?UTF-8?B?" . base64_encode($asunto) . "?=";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "From: Totem Policlínica ACTIS <$usuario_mail>\r\n";
    $headers .= "To: $destinatario\r\n";
    $headers .= "Subject: $asunto_codificado\r\n";
    $headers .= "Date: " . date("r") . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    @fputs($socket, "$headers\r\n$cuerpoHTML\r\n.\r\n");
    $resultado = leer_socket_smtp($socket);
    
    @fputs($socket, "QUIT\r\n");
    @fclose($socket);

    if (strpos($resultado, '250') !== false) {
        return "OK";
    } else {
        if(!is_dir(__DIR__ . '/logs')) mkdir(__DIR__ . '/logs', 0755, true);
        file_put_contents(__DIR__ . '/logs/mail_error.log', "[" . date('Y-m-d H:i:s') . "] Error SMTP Alerta: " . $resultado . PHP_EOL, FILE_APPEND);
        return "Error SMTP: " . $resultado;
    }
}

// Consultamos los correos configurados dinámicamente desde la base de datos
$res_sop = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'email_soporte'");
$email_soporte = $res_sop ? $res_sop->fetch_assoc()['estado'] : '';

$res_pap = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'email_papel'");
$email_papel = $res_pap ? $res_pap->fetch_assoc()['estado'] : '';

// Configuración del correo a enviar
$email_destino = 'gonzalezmarcelo159@gmail.com'; 
$asunto = "⚠️ ALERTA DE SOPORTE - TÓTEM POLICLÍNICA";
// Consultamos los correos configurados dinámicamente desde la base de datos
$res_sop = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'email_soporte'");
$email_soporte = $res_sop ? $res_sop->fetch_assoc()['estado'] : 'info@federicogonzalez.net';

$res_pap = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'email_papel'");
$email_papel = $res_pap ? $res_pap->fetch_assoc()['estado'] : 'info@federicogonzalez.net';

$fecha_hora = date('d/m/Y H:i:s');

// Validamos si el parámetro "alerta" viene definido como "papel" o "papel_ventanilla"
if (isset($_GET['alerta']) && ($_GET['alerta'] === 'papel' || $_GET['alerta'] === 'papel_ventanilla')) {
    $destinatario = $email_papel;
    
    $es_ventanilla = ($_GET['alerta'] === 'papel_ventanilla');
    $origen_texto = $es_ventanilla ? 'la Ventanilla' : 'el Tótem';
    
    $asunto = "⚠️ AVISO: Nivel de papel bajo en $origen_texto";
    if(isset($_GET['prueba'])) { $asunto = "🛠️ PRUEBA DE SISTEMA: Nivel de papel en $origen_texto"; }
    
    $tipo_impresos = $es_ventanilla ? 'tickets_impresos_ventanilla' : 'tickets_impresos';
    $tipo_capacidad = $es_ventanilla ? 'capacidad_rollo_ventanilla' : 'capacidad_rollo';
    
    // Obtenemos los datos actuales del rollo para informarlos en el texto
    $res_p_count = $conexion->query("SELECT estado FROM totem_config WHERE tipo = '$tipo_impresos'");
    $impresos = $res_p_count ? (int)$res_p_count->fetch_assoc()['estado'] : 100;
    
    $res_c_count = $conexion->query("SELECT estado FROM totem_config WHERE tipo = '$tipo_capacidad'");
    $capacidad = $res_c_count ? (int)$res_c_count->fetch_assoc()['estado'] : 120;
    
    $restantes = $capacidad - $impresos;

    $cuerpoHTML = "
    <div style='max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;'>
        <div style='background-color: #ea580c; color: white; padding: 15px; text-align: center;'>
            <h2 style='margin: 0;'>⚠️ ALERTA DE INSUMOS: POCO PAPEL</h2>
        </div>
        <div style='padding: 20px; background-color: #f8fafc;'>
            <p style='font-size: 16px; color: #334155;'>Estimado Administrador,</p>
            <p style='font-size: 16px; color: #334155;'>El contador automático de <strong>$origen_texto</strong> ha detectado que el rollo de papel térmico está cerca de agotarse.</p>
            
            <div style='background-color: #fff; border-left: 4px solid #ea580c; padding: 15px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                <p style='margin: 0 0 10px 0;'><strong>🕒 Fecha y Hora:</strong> $fecha_hora</p>
                <p style='margin: 0 0 10px 0;'><strong>🎫 Tickets Impresos ($origen_texto):</strong> $impresos / $capacidad</p>
                <p style='margin: 0; color: #dc2626;'><strong>📉 Quedan aproximadamente:</strong> $restantes tickets disponibles.</p>
            </div>
            
            <p style='font-size: 15px; color: #334155;'>Por favor, realice el reemplazo del rollo a la brevedad y recuerde registrar el cambio en el panel de administración para reiniciar el contador a cero.</p>
            
            <p style='font-size: 14px; color: #64748b; text-align: center; margin-top: 30px;'>
                Policlínica General Actis - OSFA<br>
                Soporte Automatizado de Insumos
            </p>
        </div>
    </div>
    ";
} else {
    // Alerta por defecto: Asistencia Técnica Manual
    $destinatario = $email_soporte;
    $asunto = '⚠️ ALERTA: Solicitud de Asistencia Técnica en Tótem';
    
    $html_captura = "";
    if (isset($_POST['captura']) && !empty($_POST['captura'])) {
        $captura_b64 = $_POST['captura'];
        if (strpos($captura_b64, 'data:image') !== false) {
            list($tipo, $captura_b64) = explode(';', $captura_b64);
            list(, $captura_b64)      = explode(',', $captura_b64);
        }
        $datos_img = base64_decode($captura_b64);
        $dir_capturas = __DIR__ . '/capturas';
        if (!is_dir($dir_capturas)) @mkdir($dir_capturas, 0755, true);
        $nom_img = 'captura_' . time() . '_' . rand(1000, 9999) . '.jpg';
        file_put_contents($dir_capturas . '/' . $nom_img, $datos_img);
        
        $url_img = 'https://federicogonzalez.net/actis/capturas/' . $nom_img;
        $html_captura = "<div style='margin-top: 20px; background: #fff; padding: 10px; border: 2px dashed #cbd5e1; border-radius: 10px; text-align: center;'>
                            <h3 style='color: #dc2626; margin-top: 0;'>📸 CAPTURA DE PANTALLA DEL TÓTEM:</h3>
                            <a href='$url_img' target='_blank'>
                                <img src='$url_img' style='max-width: 100%; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.2);'>
                            </a>
                         </div>";
    }
    
    $cuerpoHTML = "
    <div style='max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;'>
        <div style='background-color: #dc2626; color: white; padding: 15px; text-align: center;'>
            <h2 style='margin: 0;'>⚠️ SOLICITUD DE ASISTENCIA TÉCNICA</h2>
        </div>
        <div style='padding: 20px; background-color: #f8fafc;'>
            <p style='font-size: 16px; color: #334155;'>Hola <strong>SG MEC INFO Federico Gonzalez</strong>,</p>
            <p style='font-size: 16px; color: #334155;'>El personal del Tótem ha presionado el botón de alerta solicitando asistencia técnica urgente en el equipo de Validación.</p>
    
            <div style='background-color: #fff; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                <p style='margin: 0 0 10px 0;'><strong>🕒 Fecha y Hora:</strong> $fecha_hora</p>
                <p style='margin: 0;'><strong>📍 Ubicación del equipo:</strong> Entrada - Policlínica General Actis</p>
            </div>
            
            $html_captura
    
            <p style='font-size: 14px; color: #64748b; text-align: center; margin-top: 30px;'>
                Policlínica General Actis - OSFA<br>
                Sistema de Alertas en Tiempo Real
            </p>
        </div>
    </div>
    ";
}

$resultado = enviarAlertaNativa($destinatario, $asunto, $cuerpoHTML);

if ($resultado === "OK") {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "mensaje" => $resultado]);
}
?>