<?php
/**
 * totem_procesar_visita.php
 * Procesa la finalización de un trabajo de ascensor desde el Tótem
 * Valida el PIN de un usuario con permisos para autorizar.
 */
ob_start(); // Prevenir que cualquier warning o espacio corrompa el JSON

header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'includes/conexion.php';
require_once 'envio_correo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$id_inc = filter_input(INPUT_POST, 'id_incidencia', FILTER_VALIDATE_INT);
$tecnico = trim($_POST['tecnico'] ?? '');
$estado = trim($_POST['estado'] ?? '');
$detalle = trim($_POST['detalle_trabajo'] ?? '');
$pin_guardia = trim($_POST['pin_guardia'] ?? '');
$firma_base64 = $_POST['firma_base64'] ?? '';

if (!$id_inc || empty($tecnico) || empty($detalle) || empty($pin_guardia) || empty($firma_base64)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios (Firma, PIN, Técnico o Detalle).']);
    exit;
}

try {
    // 1. Validar PIN Dinámico del Guardia/Personal
    $stmt_pin = $pdo->prepare("SELECT id, nombre_completo, rol_id FROM usuarios WHERE pin_totem = ? AND estado = 1");
    $stmt_pin->execute([$pin_guardia]);
    $usuario = $stmt_pin->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'PIN incorrecto o inactivo.']);
        exit;
    }

    $id_receptor = $usuario['id'];
    $nombre_receptor = $usuario['nombre_completo'];
    $rol_id = $usuario['rol_id'];

    // 2. Validar que el usuario tenga el permiso "modulo_totem_ascensores"
    $stmt_perm = $pdo->prepare("SELECT 1 FROM rol_permiso rp JOIN permisos p ON rp.permiso_id = p.id WHERE rp.rol_id = ? AND p.nombre_permiso = 'modulo_totem_ascensores'");
    $stmt_perm->execute([$rol_id]);
    if (!$stmt_perm->fetchColumn()) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => "El usuario {$nombre_receptor} no tiene permisos para autorizar esto."]);
        exit;
    }

    // 3. Manejo de la Firma Digital (Base64 a PNG)
    $directorio_firmas = "uploads/firmas_tecnicos/";
    if (!is_dir($directorio_firmas)) mkdir($directorio_firmas, 0777, true);
    
    $ruta_firma = null;
    $firma_base64 = str_replace('data:image/png;base64,', '', $firma_base64);
    $firma_base64 = str_replace(' ', '+', $firma_base64);
    $firma_data = base64_decode($firma_base64);
    $nombre_firma = "firma_tec_" . $id_inc . "_" . time() . ".png";
    if (file_put_contents($directorio_firmas . $nombre_firma, $firma_data)) {
        $ruta_firma = $nombre_firma;
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Error al guardar la firma digital.']);
        exit;
    }

    // 4. Manejo del archivo adjunto (Foto desde el Tótem)
    $directorio_adjuntos = "uploads/ascensores/";
    if (!is_dir($directorio_adjuntos)) mkdir($directorio_adjuntos, 0777, true);
    
    $ruta_adjunto = null;
    if (isset($_FILES['adjunto']) && $_FILES['adjunto']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['adjunto']['name'], PATHINFO_EXTENSION));
        $nombre_archivo = "visita_" . $id_inc . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['adjunto']['tmp_name'], $directorio_adjuntos . $nombre_archivo)) {
            $ruta_adjunto = $directorio_adjuntos . $nombre_archivo;
        }
    }

    // 5. Guardar en Base de Datos
    $pdo->beginTransaction();

    $stmt_visita = $pdo->prepare("INSERT INTO ascensor_visitas_tecnicas 
        (id_incidencia, fecha_visita, tecnico_nombre, descripcion_trabajo, adjunto_tecnico, firma_tecnico_path, id_receptor) 
        VALUES (?, NOW(), ?, ?, ?, ?, ?)");
    $stmt_visita->execute([$id_inc, $tecnico, $detalle, $ruta_adjunto, $ruta_firma, $id_receptor]);

    $stmt_upd = $pdo->prepare("UPDATE ascensor_incidencias SET estado = ? WHERE id_incidencia = ?");
    $stmt_upd->execute([$estado, $id_inc]);

    $pdo->commit();

    // 6. Generar PDF y Enviar Correos en segundo plano
    $msg_correo = "";
    try {
        $sql_mail = "SELECT i.estado, a.nombre as nombre_ascensor, 
                            e.nombre as nombre_empresa, e.email_contacto, 
                            u.nombre_completo as nombre_emisor, u.email as email_emisor
                     FROM ascensor_incidencias i
                     JOIN ascensores a ON i.id_ascensor = a.id_ascensor
                     LEFT JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa
                     LEFT JOIN usuarios u ON i.id_usuario_reporta = u.id_usuario
                     WHERE i.id_incidencia = ?";
        $stmt_mail = $pdo->prepare($sql_mail);
        $stmt_mail->execute([$id_inc]);
        $mail_data = $stmt_mail->fetch(PDO::FETCH_ASSOC);

        if ($mail_data) {
            $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
            $url_pdf = $protocolo . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/ascensor_pdf.php?id=" . $id_inc;
            
            $ch = curl_init($url_pdf);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
            curl_exec($ch);
            curl_close($ch);

            $ruta_pdf_fisico = __DIR__ . "/pdfs_publicos/ascensores/Orden_Servicio_" . $id_inc . ".pdf";

            $destinatarios = [];
            if (!empty($mail_data['email_contacto'])) $destinatarios[] = $mail_data['email_contacto'];
            if (!empty($mail_data['email_emisor'])) $destinatarios[] = $mail_data['email_emisor'];
            $destinatarios = array_unique(array_filter($destinatarios));

            if (count($destinatarios) > 0 && file_exists($ruta_pdf_fisico)) {
                $asunto = "Actualización de Ticket #" . str_pad($id_inc, 5, '0', STR_PAD_LEFT) . " - " . $mail_data['nombre_ascensor'];
                $estado_fmt = strtoupper(str_replace('_', ' ', $mail_data['estado']));
                
                $cuerpoHTML = "
                <!DOCTYPE html>
                <html>
                <body style='font-family: Arial, sans-serif; color: #333; background-color: #f4f6f9; padding: 20px; margin: 0;'>
                    <div style='max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-top: 5px solid #198754; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>
                        <h2 style='color: #2c3e50; margin-top: 0;'>Actualización de Ticket Técnico (Vía Tótem)</h2>
                        <p>Se ha registrado una nueva visita y actualización para el equipo <strong>{$mail_data['nombre_ascensor']}</strong>.</p>
                        
                        <table style='width:100%; border-collapse: collapse; margin-top: 15px;'>
                            <tr><td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Técnico / Empresa:</strong></td><td style='padding: 10px; border-bottom: 1px solid #eee;'>{$tecnico}</td></tr>
                            <tr><td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Autorizado por:</strong></td><td style='padding: 10px; border-bottom: 1px solid #eee;'>{$nombre_receptor} (PIN Valido)</td></tr>
                            <tr><td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Estado Actual:</strong></td><td style='padding: 10px; border-bottom: 1px solid #eee; color: #198754; font-weight:bold;'>{$estado_fmt}</td></tr>
                            <tr><td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Detalle del trabajo:</strong></td><td style='padding: 10px; border-bottom: 1px solid #eee;'>" . nl2br(htmlspecialchars($detalle)) . "</td></tr>
                        </table>
                    </div>
                </body>
                </html>";

                foreach ($destinatarios as $correo_dest) {
                    enviarCorreoNativo($correo_dest, $asunto, $cuerpoHTML, null, $ruta_pdf_fisico);
                }
            }
        }
    } catch (Exception $eMail) {
        // Ignorar errores de correo para no romper la UX del tótem
    }

    $estado_msg = strtoupper(str_replace('_', ' ', $estado));
    
    ob_clean(); // Limpiar el buffer antes de devolver JSON
    echo json_encode([
        'success' => true, 
        'message' => "Operación autorizada por <b>{$nombre_receptor}</b>.<br>Estado: <b>{$estado_msg}</b>."
    ]);
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ob_clean(); // Limpiar el buffer si hubo error crítico
    echo json_encode(['success' => false, 'message' => 'Error de Base de Datos: ' . $e->getMessage()]);
    exit;
}
