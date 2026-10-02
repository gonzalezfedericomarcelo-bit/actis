<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'includes/conexion.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['dni']) || empty($data['dni']) || !isset($data['tipo_accion'])) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan parámetros (DNI o tipo_accion)']);
    exit;
}

$dni = $conexion->real_escape_string($data['dni']);
$tipo_accion = $data['tipo_accion']; // "asignado" o "cancelado"

$email = isset($data['email']) ? trim($conexion->real_escape_string($data['email'])) : '';
$hc_afiliado = isset($data['afiliado']) ? $data['afiliado'] : '';
$fuerza = isset($data['fuerza']) ? $data['fuerza'] : '';

// Si no pasaron el email desde el Frontend, lo buscamos en la base de datos
$q_paciente = $conexion->query("SELECT email, hc, observaciones FROM pacientes WHERE dni = '$dni' LIMIT 1");
if ($q_paciente && $q_paciente->num_rows > 0) {
    $row = $q_paciente->fetch_assoc();
    if (empty($email)) $email = $row['email'];
    if (empty($hc_afiliado) || strpos($hc_afiliado, 'IOSFA') !== false) {
        $hc_afiliado = $row['hc'];
    }
    if (empty($fuerza) && !empty($row['observaciones']) && strpos($row['observaciones'], 'Fuerza:') !== false) {
        preg_match('/Fuerza:\s*(.*?)(?=\s*\||$)/', $row['observaciones'], $matches);
        if (isset($matches[1])) {
            $fuerza = trim($matches[1]);
        }
    }
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'El paciente no tiene un correo electrónico válido']);
    exit;
}

// Variables comunes
$nombre_correo = isset($data['nombre']) ? $data['nombre'] : '';
$apellido_correo = isset($data['apellido']) ? $data['apellido'] : '';
$servicio = isset($data['servicio']) ? $data['servicio'] : '';
$profesional = isset($data['profesional']) ? $data['profesional'] : '';
$practicas = isset($data['practicas']) ? $data['practicas'] : '';
$motivo = isset($data['motivo']) ? $data['motivo'] : 'Cancelación a pedido';
$numero_turno = isset($data['numero_turno']) ? $data['numero_turno'] : '';

$operador_crudo = isset($data['operador']) && !empty(trim($data['operador'])) ? trim($data['operador']) : 'ACTIS';
$primer_nombre_op = explode(' ', $operador_crudo)[0];
$op_corto = ucfirst(strtolower($primer_nombre_op));

$apellido_format = mb_strtoupper($apellido_correo, 'UTF-8');
$nombre_format = mb_convert_case($nombre_correo, MB_CASE_TITLE, 'UTF-8');

// Si es desde historial (cancelación), la fecha y hora vienen directamente
$fecha = isset($data['fecha']) ? $data['fecha'] : '';
if (is_array($fecha)) $fecha = ''; // Safety check

$hora = isset($data['hora']) ? $data['hora'] : '';
if (is_array($hora)) $hora = ''; // Safety check

if (strpos($fecha, ' ') !== false) {
    $parts = explode(' ', $fecha);
    $fecha = $parts[0];
    if (empty($hora)) {
        $hora = $parts[1];
    }
}

$hora_limpia = substr(str_replace(' hs', '', (string)$hora), 0, 5); // Ej: 14:20

// Armar la fecha formateada
$fechaFormateada = $fecha;
if (strpos($fecha, '-') !== false) {
    $fParts = explode('-', $fecha);
    if (count($fParts) == 3) $fechaFormateada = "{$fParts[2]}/{$fParts[1]}/{$fParts[0]}";
}

$horaActual = (int)date('H');
if ($horaActual >= 6 && $horaActual < 13) {
    $saludo = "Buenos días";
} elseif ($horaActual >= 13 && $horaActual < 20) {
    $saludo = "Buenas tardes";
} else {
    $saludo = "Buenas noches";
}
$bloqueFechas = "<div style='background:#f8fafc; border-left:4px solid ".($tipo_accion == 'cancelado' ? '#ef4444' : '#10b981')."; padding:10px 15px; margin-bottom:8px; font-weight:bold; color:#0f172a; border-radius:0 8px 8px 0;'>📅 {$fechaFormateada} a las ⏰ {$hora_limpia} hs</div>";

$fuerzaHtml = ($fuerza !== '' && $fuerza !== 'N/A') ? "<tr><td style='padding: 12px 5px; border-bottom: 1px dashed #cbd5e1; color: #64748b;'><strong>Fuerza:</strong></td><td style='padding: 12px 5px; border-bottom: 1px dashed #cbd5e1; color: #0f172a;'><strong>{$fuerza}</strong></td></tr>" : "";

$titulo_mensaje = ($tipo_accion == 'cancelado') ? "AVISO DE CANCELACIÓN" : (($tipo_accion == 'reprogramado') ? "AVISO DE REPROGRAMACIÓN" : "Comprobante Oficial de Atención");
$asunto = ($tipo_accion == 'cancelado') ? "Cancelación de Turno - Policlínica ACTIS" : (($tipo_accion == 'reprogramado') ? "Reprogramación de Turno - Policlínica ACTIS" : "Confirmación de Turno - Policlínica ACTIS");
$color_header = ($tipo_accion == 'cancelado') ? "#7f1d1d" : "#154a74";
$color_border = ($tipo_accion == 'cancelado') ? "#450a0a" : "#0a2640";
$logo_img = ($tipo_accion == 'cancelado') ? "osfa_blanco.png" : "osfa.png";
$texto_principal = ($tipo_accion == 'cancelado') ? "Le informamos que el siguiente turno ha sido <strong>CANCELADO</strong> en nuestro sistema:" : (($tipo_accion == 'reprogramado') ? "Su turno ha sido <strong>REPROGRAMADO</strong> exitosamente. A continuación, el detalle actualizado:" : "Hemos registrado exitosamente su solicitud en nuestro sistema. A continuación, el detalle de su cronograma asignado:");

if ($tipo_accion == 'cancelado' && !empty($motivo)) {
    $texto_principal .= "<br><br><strong>Motivo de baja:</strong> " . $motivo;
}

$cuerpoHTML = "
<div style='font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);'>
    <div style='background-color: {$color_header}; padding: 35px 25px; text-align: center; border-bottom: 5px solid {$color_border};'>
        <img src='https://federicogonzalez.net/actis/img/{$logo_img}' alt='OSFA' style='width: 65px; margin-bottom: 15px; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.25));'>
        <h1 style='color: #ffffff; margin: 0; font-size: 26px; font-weight: 900; letter-spacing: 0.5px;'>POLICLÍNICA ACTIS</h1>
        <p style='color: #38bdf8; margin: 5px 0 0 0; font-size: 12px; text-transform: uppercase; letter-spacing: 3px; font-weight:bold;'>{$titulo_mensaje}</p>
    </div>
    
    <div style='background-color: #ffffff; padding: 35px 30px;'>
        <h2 style='color: #0f172a; margin-top: 0; font-size: 22px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px;'>Hola, {$nombre_format} {$apellido_format}</h2>
        <p style='color: #475569; font-size: 16px; line-height: 1.6; margin-bottom: 25px;'>{$texto_principal}</p>
        
        <div style='margin-bottom: 25px;'>
            {$bloqueFechas}
        </div>

        <table style='width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 14px; margin-bottom: 25px;'>
            " . ($dni ? "<tr><td style='padding: 12px 5px; border-bottom: 1px dashed #cbd5e1; color: #64748b; width: 35%;'><strong>DNI:</strong></td><td style='padding: 12px 5px; border-bottom: 1px dashed #cbd5e1; color: #0f172a;'>{$dni}</td></tr>" : "") . "
            " . ($hc_afiliado ? "<tr><td style='padding: 12px 5px; border-bottom: 1px dashed #cbd5e1; color: #64748b; width: 35%;'><strong>Nro. Afiliado:</strong></td><td style='padding: 12px 5px; border-bottom: 1px dashed #cbd5e1; color: #0f172a;'>{$hc_afiliado}</td></tr>" : "") . "
            {$fuerzaHtml}
            " . ($numero_turno ? "<tr><td style='padding: 12px 5px; border-bottom: 1px dashed #cbd5e1; color: #64748b;'><strong>Nro. Turno:</strong></td><td style='padding: 12px 5px; border-bottom: 1px dashed #cbd5e1; color: #144973; font-weight:900;'>{$numero_turno}</td></tr>" : "") . "
            " . ($servicio ? "<tr><td style='padding: 12px 5px; border-bottom: 1px dashed #cbd5e1; color: #64748b;'><strong>Servicio:</strong></td><td style='padding: 12px 5px; border-bottom: 1px dashed #cbd5e1; color: #0f172a;'>{$servicio}</td></tr>" : "") . "
            " . ($profesional ? "<tr><td style='padding: 12px 5px; border-bottom: 1px dashed #cbd5e1; color: #64748b;'><strong>Profesional:</strong></td><td style='padding: 12px 5px; border-bottom: 1px dashed #cbd5e1; color: #0f172a;'>{$profesional}</td></tr>" : "") . "
            " . ($practicas ? "<tr><td style='padding: 12px 5px; border-bottom: 1px dashed #cbd5e1; color: #64748b;'><strong>Práctica:</strong></td><td style='padding: 12px 5px; border-bottom: 1px dashed #cbd5e1; color: #144973; font-weight:900;'>{$practicas}</td></tr>" : "") . "
            <tr><td style='padding: 12px 5px; border-bottom: 1px dashed #cbd5e1; color: #64748b;'><strong>Emitido por:</strong></td><td style='padding: 12px 5px; border-bottom: 1px dashed #cbd5e1; color: #0f172a;'>{$op_corto}</td></tr>
        </table>";
        
if ($tipo_accion != 'cancelado') {
    $cuerpoHTML .= "
        <div style='margin-top: 35px; background-color:#fffbeb; border:1px solid #fde68a; padding:15px; border-radius:8px; font-size: 13px; color: #92400e; line-height: 1.5;'>
            <strong><span style='font-size:16px;'>⚠️</span> ATENCIÓN:</strong> Por favor, recuerde sacar su número de validación el día de su turno en la entrada de la Policlínica (desde la ventanilla o el Tótem interactivo).<br><br>Si no puede asistir, le rogamos cancelar su turno utilizando los botones correspondientes más abajo para liberar el espacio.
        </div>
        <div style='margin-top: 20px; background-color: #fef2f2; border: 2px solid #ef4444; padding: 15px; border-radius: 8px; text-align: center;'>
            <strong style='color: #b91c1c; font-size: 16px; display: block; margin-bottom: 5px;'>IMPORTANTE</strong>
            <span style='color: #991b1b; font-size: 14px; font-weight: bold;'>POR FAVOR CONFIRME O RECHACE ESTE TURNO UTILIZANDO LOS BOTONES CORRESPONDIENTES.</span>
        </div>";
    
    $mailSubj = rawurlencode("Solicitud de cancelacion de turno");
    $mailBody = rawurlencode("Hola, {$saludo}. Soy el afiliado {$nombre_format} {$apellido_format}, DNI {$dni}, número de IOSFA {$hc_afiliado}, por el turno que tenía para el servicio de {$servicio} el día {$fechaFormateada} a las {$hora_limpia} hs con el profesional {$profesional}. Solicito cancelarlo.");
    $mailLink = "mailto:turnos.actis@iosfa.gob.ar?subject={$mailSubj}&body={$mailBody}";
    $mailBtnText = "✉️ Cancelar Turno por Correo";
    
    $mailConfirmSubj = rawurlencode("Confirmacion de asistencia de turno");
    $mailConfirmBody = rawurlencode("Hola, {$saludo}. Soy el afiliado {$nombre_format} {$apellido_format}, DNI {$dni}, número de IOSFA {$hc_afiliado}. Confirmo mi asistencia para el turno en el servicio de {$servicio} el día {$fechaFormateada} a las {$hora_limpia} hs con el profesional {$profesional}.");
    $mailConfirmLink = "mailto:turnos.actis@iosfa.gob.ar?subject={$mailConfirmSubj}&body={$mailConfirmBody}";
    $mailConfirmBtnText = "✅ Confirmar Turno por Correo";
} else {
    $cuerpoHTML .= "
        <div style='margin-top: 35px; background-color:#fef2f2; border:1px solid #fecaca; padding:15px; border-radius:8px; font-size: 13px; color: #991b1b; line-height: 1.5;'>
            Si considera que esta cancelación es un error, por favor comuníquese con nosotros utilizando los botones más abajo a la brevedad.
        </div>";
        
    $mailSubj = rawurlencode("Error en cancelacion de turno");
    $mailBody = rawurlencode("Hola, {$saludo}. Soy el afiliado {$nombre_format} {$apellido_format}, DNI {$dni}, número de IOSFA {$hc_afiliado}, por el turno que tenía para el servicio de {$servicio} el día {$fechaFormateada} a las {$hora_limpia} hs con el profesional {$profesional}. Considero que la cancelación es un error.");
    $mailLink = "mailto:turnos.actis@iosfa.gob.ar?subject={$mailSubj}&body={$mailBody}";
    $mailBtnText = "✉️ Reclamar Error por Correo";
    $mailConfirmLink = "";
}

$waText = "Hola, {$saludo}. Soy el afiliado {$nombre_format} {$apellido_format}, DNI {$dni}, número de IOSFA {$hc_afiliado}, por el turno que tenía para el servicio de {$servicio} el día {$fechaFormateada} a las {$hora_limpia} hs con el profesional {$profesional}. (" . ($tipo_accion == 'cancelado' ? 'Aviso de Cancelación' : (($tipo_accion == 'reprogramado') ? 'Aviso de Reprogramación' : 'Aviso de Asignación')) . ")";

$waLink = "https://wa.me/5491123023297?text=" . rawurlencode($waText);

$cuerpoHTML .= "
    <div style='margin-top:30px; text-align:center;'>";

if ($tipo_accion != 'cancelado' && !empty($mailConfirmLink)) {
    $cuerpoHTML .= "
        <a href='{$mailConfirmLink}' style='display:block; background-color:#25D366; color:white; padding:14px; text-decoration:none; border-radius:10px; font-weight:bold; font-size:16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width:100%; max-width:280px; margin: 0 auto 12px auto; box-sizing: border-box;'>
            {$mailConfirmBtnText}
        </a>";
}

$cuerpoHTML .= "
        <a href='{$mailLink}' style='display:block; background-color:#ef4444; color:white; padding:14px; text-decoration:none; border-radius:10px; font-weight:bold; font-size:16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width:100%; max-width:280px; margin: 0 auto 12px auto; box-sizing: border-box;'>
            {$mailBtnText}
        </a>
        <span style='color: #94a3b8; font-size: 12px; margin-bottom: 12px; display:inline-block;'>O contactarse por WhatsApp</span>
        <a href='{$waLink}' style='display:block; background-color:#f0fdf4; color:#16a34a; border:2px solid #22c55e; padding:12px 14px; text-decoration:none; border-radius:10px; font-weight:bold; font-size:16px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); width:100%; max-width:280px; margin: 0 auto; box-sizing: border-box;'>
            📲 Contactar por WhatsApp
        </a>
    </div>";


$cuerpoHTML .= "
    </div>
    
    <div style='background-color: #f8fafc; padding: 20px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; line-height: 1.6;'>
        <strong>© " . date('Y') . " POLICLÍNICA GENERAL ACTIS - OSFA</strong><br>
       SG Mec Info Federico González - Enc División Informática | ACTIS Core v4.0
    </div>
</div>";

require_once 'envio_correo.php';
$correo_respuesta = 'turnos.actis@iosfa.gob.ar'; 
$resultado = enviarCorreoNativo($email, $asunto, $cuerpoHTML, $correo_respuesta);

if ($resultado === true) {
    echo json_encode(['status' => 'success', 'message' => 'Correo enviado correctamente a ' . $email]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error al enviar el correo: ' . $resultado]);
}
?>
