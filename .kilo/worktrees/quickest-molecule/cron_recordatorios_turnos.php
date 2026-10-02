<?php
// cron_recordatorios_turnos.php
// Este script debe ser ejecutado por un cron job diariamente, ej. cada hora.
// Envia recordatorios 48hs antes del turno.
date_default_timezone_set('America/Argentina/Buenos_Aires');

// 1. Verificación de Horario (De 09:00 a 19:00)
$hora_actual = (int)date('H');
if ($hora_actual < 9 || $hora_actual >= 19) {
    echo "Fuera de horario comercial. No se envían recordatorios. Hora actual: $hora_actual\n";
    exit;
}

require_once 'includes/conexion.php';
require_once 'envio_correo.php';

// 2. Buscar turnos que sean para PASADO MAÑANA (48hs) y que NO se haya enviado el recordatorio
// Estado debe ser Pendiente o Autorizado.
$sql_turnos = "SELECT t.*, 
               p.nombre, p.apellido, p.dni, p.hc as afiliado, p.telefono, p.email, p.observaciones as obs_paciente
               FROM turnos t 
               INNER JOIN pacientes p ON t.paciente_id = p.id 
               WHERE t.fecha_turno = CURDATE() + INTERVAL 2 DAY 
               AND t.recordatorio_enviado = 0 
               AND t.estado IN ('Pendiente', 'Autorizado')";

$resultado = $conexion->query($sql_turnos);

if (!$resultado) {
    die("Error en la consulta: " . $conexion->error);
}

if ($resultado->num_rows === 0) {
    echo "No hay recordatorios pendientes para enviar.\n";
    exit;
}

$enviados = 0;
$errores = 0;

while ($turno = $resultado->fetch_assoc()) {
    $email = $turno['email'];
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        continue; // No tiene email válido
    }

    $dni = $turno['dni'];
    $hc_afiliado = $turno['afiliado'];
    $fuerza = '';
    if (!empty($turno['obs_paciente']) && strpos($turno['obs_paciente'], 'Fuerza:') !== false) {
        preg_match('/Fuerza:\s*(.*?)(?=\s*\||$)/', $turno['obs_paciente'], $matches);
        if (isset($matches[1])) {
            $fuerza = trim($matches[1]);
        }
    }

    $nombre_format = mb_convert_case($turno['nombre'], MB_CASE_TITLE, 'UTF-8');
    $apellido_format = mb_strtoupper($turno['apellido'], 'UTF-8');
    $servicio = $turno['servicio'];
    $profesional = $turno['profesional'];
    $practicas = $turno['motivo_visita'];
    $numero_turno = $turno['numero_turno'];
    $fecha_turno = date("d/m/Y", strtotime($turno['fecha_turno']));
    $hora_limpia = substr(str_replace(' hs', '', (string)$turno['hora_turno']), 0, 5);
    $op_corto = "Sistema";

    $saludo = "Hola";

    $bloqueFechas = "<div style='background:#f8fafc; border-left:4px solid #f59e0b; padding:10px 15px; margin-bottom:8px; font-weight:bold; color:#0f172a; border-radius:0 8px 8px 0;'>📅 {$fecha_turno} a las ⏰ {$hora_limpia} hs</div>";
    $fuerzaHtml = ($fuerza !== '' && $fuerza !== 'N/A') ? "<tr><td style='padding: 12px 5px; border-bottom: 1px dashed #cbd5e1; color: #64748b;'><strong>Fuerza:</strong></td><td style='padding: 12px 5px; border-bottom: 1px dashed #cbd5e1; color: #0f172a;'><strong>{$fuerza}</strong></td></tr>" : "";

    $titulo_mensaje = "RECORDATORIO DE TURNO";
    $asunto = "Recordatorio de Turno (En 48hs) - Policlínica ACTIS";
    $color_header = "#f59e0b"; // Naranja
    $color_border = "#b45309";
    $logo_img = "osfa.png";
    $texto_principal = "Le recordamos que tiene un turno próximo en nuestro sistema. A continuación, el detalle:";

    $cuerpoHTML = "
    <div style='font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);'>
        <div style='background-color: {$color_header}; padding: 35px 25px; text-align: center; border-bottom: 5px solid {$color_border};'>
            <img src='https://federicogonzalez.net/actis/img/{$logo_img}' alt='OSFA' style='width: 65px; margin-bottom: 15px; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.25));'>
            <h1 style='color: #ffffff; margin: 0; font-size: 26px; font-weight: 900; letter-spacing: 0.5px;'>POLICLÍNICA ACTIS</h1>
            <p style='color: #ffffff; margin: 5px 0 0 0; font-size: 12px; text-transform: uppercase; letter-spacing: 3px; font-weight:bold;'>{$titulo_mensaje}</p>
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
            </table>";

    $cuerpoHTML .= "
        <div style='margin-top: 35px; background-color:#fffbeb; border:1px solid #fde68a; padding:15px; border-radius:8px; font-size: 13px; color: #92400e; line-height: 1.5;'>
            <strong><span style='font-size:16px;'>⚠️</span> ATENCIÓN:</strong> Por favor, recuerde sacar su número de validación el día de su turno en la entrada de la Policlínica.<br><br>Si no puede asistir, le rogamos cancelar su turno utilizando los botones correspondientes más abajo para liberar el espacio.
        </div>
        <div style='margin-top: 20px; background-color: #fef2f2; border: 2px solid #ef4444; padding: 15px; border-radius: 8px; text-align: center;'>
            <strong style='color: #b91c1c; font-size: 16px; display: block; margin-bottom: 5px;'>IMPORTANTE</strong>
            <span style='color: #991b1b; font-size: 14px; font-weight: bold;'>POR FAVOR CONFIRME O RECHACE ESTE TURNO UTILIZANDO LOS BOTONES CORRESPONDIENTES.</span>
        </div>";

    $mailSubj = rawurlencode("Solicitud de cancelacion de turno");
    $mailBody = rawurlencode("Hola, {$saludo}. Soy el afiliado {$nombre_format} {$apellido_format}, DNI {$dni}, número de IOSFA {$hc_afiliado}, por el turno que tenía para el servicio de {$servicio} el día {$fecha_turno} a las {$hora_limpia} hs con el profesional {$profesional}. Solicito cancelarlo.");
    $mailLink = "mailto:turnos.actis@iosfa.gob.ar?subject={$mailSubj}&body={$mailBody}";
    $mailBtnText = "✉️ Cancelar Turno por Correo";
    
    $mailConfirmSubj = rawurlencode("Confirmacion de asistencia de turno");
    $mailConfirmBody = rawurlencode("Hola, {$saludo}. Soy el afiliado {$nombre_format} {$apellido_format}, DNI {$dni}, número de IOSFA {$hc_afiliado}. Confirmo mi asistencia para el turno en el servicio de {$servicio} el día {$fecha_turno} a las {$hora_limpia} hs con el profesional {$profesional}.");
    $mailConfirmLink = "mailto:turnos.actis@iosfa.gob.ar?subject={$mailConfirmSubj}&body={$mailConfirmBody}";
    $mailConfirmBtnText = "✅ Confirmar Turno por Correo";

    $waText = "Hola, {$saludo}. Soy el afiliado {$nombre_format} {$apellido_format}, DNI {$dni}, número de IOSFA {$hc_afiliado}, por el turno que tenía para el servicio de {$servicio} el día {$fecha_turno} a las {$hora_limpia} hs con el profesional {$profesional}. (Aviso de Recordatorio 48hs)";
    $waLink = "https://wa.me/5491123023297?text=" . rawurlencode($waText);

    $cuerpoHTML .= "
        <div style='margin-top:30px; text-align:center;'>
            <a href='{$mailConfirmLink}' style='display:block; background-color:#25D366; color:white; padding:14px; text-decoration:none; border-radius:10px; font-weight:bold; font-size:16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width:100%; max-width:280px; margin: 0 auto 12px auto; box-sizing: border-box;'>
                {$mailConfirmBtnText}
            </a>
            <a href='{$mailLink}' style='display:block; background-color:#ef4444; color:white; padding:14px; text-decoration:none; border-radius:10px; font-weight:bold; font-size:16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width:100%; max-width:280px; margin: 0 auto 12px auto; box-sizing: border-box;'>
                {$mailBtnText}
            </a>
            <span style='color: #94a3b8; font-size: 12px; margin-bottom: 12px; display:inline-block;'>O contactarse por WhatsApp</span>
            <a href='{$waLink}' style='display:block; background-color:#f0fdf4; color:#16a34a; border:2px solid #22c55e; padding:12px 14px; text-decoration:none; border-radius:10px; font-weight:bold; font-size:16px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); width:100%; max-width:280px; margin: 0 auto; box-sizing: border-box;'>
                📲 Contactar por WhatsApp
            </a>
        </div>
        </div>
        <div style='background-color: #f8fafc; padding: 20px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; line-height: 1.6;'>
            <strong>© " . date('Y') . " POLICLÍNICA GENERAL ACTIS - OSFA</strong><br>
           SG Mec Info Federico González - Enc División Informática | ACTIS Core v4.0
        </div>
    </div>";

    $correo_respuesta = 'turnos.actis@iosfa.gob.ar'; 
    $resultado_envio = enviarCorreoNativo($email, $asunto, $cuerpoHTML, $correo_respuesta);

    if ($resultado_envio === true) {
        $enviados++;
        // Marcar como enviado
        $id_turno = $turno['id'];
        $conexion->query("UPDATE turnos SET recordatorio_enviado = 1 WHERE id = $id_turno");
    } else {
        $errores++;
    }
}

echo "Proceso finalizado. Correos enviados: $enviados. Errores: $errores.\n";
?>
