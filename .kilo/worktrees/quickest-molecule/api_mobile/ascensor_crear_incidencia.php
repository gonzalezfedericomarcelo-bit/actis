<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../includes/conexion.php';

$data = json_decode(file_get_contents("php://input"), true);
$id_ascensor = $data['id_ascensor'] ?? 0;
$titulo = $data['titulo'] ?? '';
$descripcion = $data['descripcion'] ?? '';
$prioridad = $data['prioridad'] ?? 'media';
$usuario_id = $data['usuario_id'] ?? 0;

if (!$id_ascensor || !$titulo || !$usuario_id) {
    echo json_encode(["status" => "error", "message" => "Datos incompletos"]);
    exit;
}

// 1. Obtener datos del ascensor y la empresa mantenedora
$id_ascensor_esc = $conexion->real_escape_string($id_ascensor);
$sql_asc = "SELECT a.nombre, a.ubicacion, e.email_contacto, e.nombre AS empresa_nombre 
            FROM ascensores a 
            LEFT JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa 
            WHERE a.id_ascensor = '$id_ascensor_esc'";
$res_asc = $conexion->query($sql_asc);

if (!$res_asc || $res_asc->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Ascensor no encontrado"]);
    exit;
}
$ascensor = $res_asc->fetch_assoc();

// 2. Obtener firma del usuario (nombre)
$usuario_id_esc = $conexion->real_escape_string($usuario_id);
$sql_usu = "SELECT nombre_completo, email FROM usuarios WHERE id = '$usuario_id_esc'";
$res_usu = $conexion->query($sql_usu);
$usuario_nombre = "Usuario Desconocido";
if ($res_usu && $res_usu->num_rows > 0) {
    $u = $res_usu->fetch_assoc();
    $usuario_nombre = $u['nombre_completo'];
}

// 3. Insertar incidencia
$titulo_esc = $conexion->real_escape_string($titulo);
$descripcion_esc = $conexion->real_escape_string($descripcion);
$prioridad_esc = $conexion->real_escape_string($prioridad);
$fecha_reporte = date('Y-m-d H:i:s');

$sql_ins = "INSERT INTO ascensor_incidencias (id_ascensor, id_usuario_reporta, titulo, descripcion_problema, prioridad, fecha_reporte, estado) 
            VALUES ('$id_ascensor_esc', '$usuario_id_esc', '$titulo_esc', '$descripcion_esc', '$prioridad_esc', '$fecha_reporte', 'abierta')";

if ($conexion->query($sql_ins)) {
    $id_incidencia = $conexion->insert_id;
    $email_usuario = $u['email'] ?? '';

    // D. Generar PDF Silenciosamente y Enviar Correo
    if (!empty($ascensor['email_contacto'])) {
        require_once '../envio_correo.php';
        
        // 1. Llamada local (cURL) para generar y guardar el PDF oficial en el servidor
        $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
        $url_generador_pdf = $protocolo . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . "/ascensor_orden_pdf.php?id=" . $id_incidencia;
        
        $ch = curl_init($url_generador_pdf);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        curl_close($ch);
        
        // 2. Ruta exacta donde se acaba de guardar el PDF generado
        $ruta_pdf_fisico = dirname(__DIR__) . "/pdfs_publicos/ascensores/Orden_Ascensor_" . $id_incidencia . ".pdf";

        // 3. Crear un correo formal
        $asunto = "Orden de Trabajo #" . str_pad($id_incidencia, 5, '0', STR_PAD_LEFT) . " - Equipo: " . $ascensor['nombre'];
        
        $cuerpoHTML = "
        <!DOCTYPE html>
        <html>
        <body style='font-family: \"Segoe UI\", Arial, sans-serif; color: #333; background-color: #f4f6f9; padding: 20px; margin: 0;'>
            <div style='max-width: 550px; margin: 0 auto; background: #ffffff; padding: 30px; border-top: 5px solid #dc3545; border-radius: 4px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
                <h2 style='color: #2c3e50; margin-top: 0; font-size: 20px; text-transform: uppercase;'>Notificación de Servicio</h2>
                <p style='font-size: 15px; line-height: 1.6;'>Estimados <strong>{$ascensor['empresa_nombre']}</strong>,</p>
                <p style='font-size: 15px; line-height: 1.6;'>Por medio de la presente les notificamos la generación de una nueva orden de trabajo correspondiente a una falla reportada en el equipo <strong>{$ascensor['nombre']}</strong>.</p>
                <div style='background-color: #fff3cd; color: #856404; padding: 15px; border-left: 4px solid #ffc107; margin: 25px 0; border-radius: 3px;'>
                    <strong><img src='https://cdn-icons-png.flaticon.com/128/732/732220.png' width='16' style='vertical-align: middle;'> Documento Adjunto:</strong><br>
                    Encuentren adjunto a este correo el <strong>PDF Oficial</strong> de la Orden de Servicio con el detalle de la falla, firma del reportante, ubicación exacta y nivel de prioridad operativa.
                </div>
                <p style='font-size: 12px; color: #95a5a6; margin-top: 30px; border-top: 1px solid #eee; padding-top: 15px;'>
                    Sistema Automatizado de Logística Institucional.<br>
                    <em>Por favor, siéntase libre de responder este correo para establecer comunicación directa con el usuario emisor ({$email_usuario}).</em>
                </p>
            </div>
        </body>
        </html>";
        
        // 4. Enviar el correo PASÁNDOLE el PDF como quinto parámetro
        enviarCorreoNativo($ascensor['email_contacto'], $asunto, $cuerpoHTML, $email_usuario, $ruta_pdf_fisico);
        
        // Actualizamos estado si se envió
        $conexion->query("UPDATE ascensor_incidencias SET estado = 'reclamo_enviado', fecha_reclamo_enviado = NOW() WHERE id_incidencia = '$id_incidencia'");
    }

    echo json_encode([
        "status" => "success", 
        "message" => "Falla reportada correctamente por $usuario_nombre."
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Error al guardar en la base de datos."]);
}
?>
