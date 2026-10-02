<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

require_once '../includes/conexion.php';
require_once '../envio_correo.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) $data = $_POST;

    $id_inc = filter_var($data['id_incidencia'] ?? 0, FILTER_VALIDATE_INT);
    $tecnico = trim($data['tecnico'] ?? '');
    $detalle = trim($data['detalle_trabajo'] ?? '');
    $nuevo_estado = trim($data['estado'] ?? '');
    $id_receptor = filter_var($data['id_receptor'] ?? 0, FILTER_VALIDATE_INT);
    $firma_base64 = $data['firma_base64'] ?? '';

    if (!$id_inc || !$tecnico || !$detalle || !$nuevo_estado || !$id_receptor) {
        echo json_encode(["status" => "error", "message" => "Faltan datos obligatorios"]);
        exit;
    }

    $ruta_firma = null;
    if (!empty($firma_base64)) {
        $directorio_firmas = "../uploads/firmas_tecnicos/";
        if (!is_dir($directorio_firmas)) mkdir($directorio_firmas, 0777, true);
        
        $firma_base64 = str_replace('data:image/png;base64,', '', $firma_base64);
        $firma_base64 = str_replace(' ', '+', $firma_base64);
        $firma_data = base64_decode($firma_base64);
        
        $nombre_firma = "firma_tec_" . $id_inc . "_" . time() . ".png";
        if (file_put_contents($directorio_firmas . $nombre_firma, $firma_data)) {
            $ruta_firma = $nombre_firma;
        }
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO ascensor_visitas_tecnicas 
        (id_incidencia, fecha_visita, tecnico_nombre, descripcion_trabajo, firma_tecnico_path, id_receptor) 
        VALUES (?, NOW(), ?, ?, ?, ?)");
    $stmt->execute([$id_inc, $tecnico, $detalle, $ruta_firma, $id_receptor]);

    $stmt_upd = $pdo->prepare("UPDATE ascensor_incidencias SET estado = ? WHERE id_incidencia = ?");
    $stmt_upd->execute([$nuevo_estado, $id_inc]);

    $pdo->commit();

    // Generar PDF y enviar correo en segundo plano
    $msg_correo = "";
    try {
        $sql_mail = "SELECT i.estado, a.nombre as nombre_ascensor, 
                            e.nombre as nombre_empresa, e.email_contacto, 
                            u.nombre_completo as nombre_emisor, u.email as email_emisor
                     FROM ascensor_incidencias i
                     JOIN ascensores a ON i.id_ascensor = a.id_ascensor
                     LEFT JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa
                     LEFT JOIN usuarios u ON i.id_usuario_reporta = u.id
                     WHERE i.id_incidencia = ?";
        $stmt_mail = $pdo->prepare($sql_mail);
        $stmt_mail->execute([$id_inc]);
        $mail_data = $stmt_mail->fetch(PDO::FETCH_ASSOC);

        if ($mail_data) {
            $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
            $url_pdf = $protocolo . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . "/ascensor_orden_pdf.php?id=" . $id_inc;
            
            $ch = curl_init($url_pdf);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_exec($ch);
            curl_close($ch);

            $ruta_pdf_fisico = dirname(__DIR__) . "/pdfs_publicos/ascensores/Orden_Ascensor_" . $id_inc . ".pdf";

            $destinatarios = [];
            if (!empty($mail_data['email_contacto'])) $destinatarios[] = $mail_data['email_contacto'];
            if (!empty($mail_data['email_emisor'])) $destinatarios[] = $mail_data['email_emisor'];
            $destinatarios = array_unique(array_filter($destinatarios));

            if (count($destinatarios) > 0) {
                $asunto = "Actualización de Ticket #" . str_pad($id_inc, 5, '0', STR_PAD_LEFT) . " - " . $mail_data['nombre_ascensor'];
                $estado_fmt = strtoupper(str_replace('_', ' ', $mail_data['estado']));
                $email_usuario = $mail_data['email_emisor'] ?? '';
                
                $cuerpoHTML = "
                <!DOCTYPE html>
                <html>
                <body style='font-family: \"Segoe UI\", Arial, sans-serif; color: #333; background-color: #f4f6f9; padding: 20px; margin: 0;'>
                    <div style='max-width: 550px; margin: 0 auto; background: #ffffff; padding: 30px; border-top: 5px solid #198754; border-radius: 4px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
                        <h2 style='color: #2c3e50; margin-top: 0; font-size: 20px; text-transform: uppercase;'>Actualización de Ticket Técnico</h2>
                        <p style='font-size: 15px; line-height: 1.6;'>Estimados <strong>{$mail_data['nombre_empresa']}</strong>,</p>
                        <p style='font-size: 15px; line-height: 1.6;'>Se ha registrado una nueva visita y actualización para el equipo <strong>{$mail_data['nombre_ascensor']}</strong>.</p>
                        
                        <table style='width:100%; border-collapse: collapse; margin-top: 15px;'>
                            <tr><td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Técnico / Empresa:</strong></td><td style='padding: 10px; border-bottom: 1px solid #eee;'>{$tecnico}</td></tr>
                            <tr><td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Estado Actual:</strong></td><td style='padding: 10px; border-bottom: 1px solid #eee; color: #198754; font-weight:bold;'>{$estado_fmt}</td></tr>
                            <tr><td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Detalle del trabajo:</strong></td><td style='padding: 10px; border-bottom: 1px solid #eee;'>" . nl2br(htmlspecialchars($detalle)) . "</td></tr>
                        </table>
                        
                        <div style='background-color: #fff3cd; color: #856404; padding: 15px; border-left: 4px solid #ffc107; margin: 25px 0; border-radius: 3px;'>
                            <strong><img src='https://cdn-icons-png.flaticon.com/128/732/732220.png' width='16' style='vertical-align: middle;'> Documento Adjunto:</strong><br>
                            Encuentren adjunto a este correo el <strong>Reporte Técnico Oficial</strong> actualizado en formato PDF, conteniendo la bitácora y las firmas correspondientes.
                        </div>
                        <p style='font-size: 12px; color: #95a5a6; margin-top: 30px; border-top: 1px solid #eee; padding-top: 15px;'>
                            Sistema Automatizado de Logística Institucional.<br>
                            <em>Por favor, siéntase libre de responder este correo para establecer comunicación directa con el usuario emisor ({$email_usuario}).</em>
                        </p>
                    </div>
                </body>
                </html>";

                $enviados = 0;
                foreach ($destinatarios as $correo_dest) {
                    $res = enviarCorreoNativo($correo_dest, $asunto, $cuerpoHTML, null, $ruta_pdf_fisico);
                    if ($res === true) $enviados++;
                }
                $msg_correo = "Correo enviado a $enviados destinatarios con PDF adjunto.";
            }
        }
    } catch (Exception $eMail) {
        $msg_correo = "Error de correo: " . $eMail->getMessage();
    }

    echo json_encode(["status" => "success", "message" => "Visita registrada correctamente. $msg_correo"]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => "Error interno: " . $e->getMessage()]);
}
?>
