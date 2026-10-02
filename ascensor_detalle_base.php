<?php
// Archivo: ascensor_detalle.php (REDISEÃ‘O PREMIUM ESTANDARIZADO - ESTILO LOGÃSTICA NEO)
session_start();
require_once 'includes/conexion.php';
// require_once 'funciones_permisos.php';
require_once 'envio_correo.php'; 

if (!isset($_SESSION['usuario_id']) || !in_array('modulo_totem_ascensores', isset($_SESSION['permisos']) ? $_SESSION['permisos'] : []) && !in_array('modulo_ascensores', isset($_SESSION['permisos']) ? $_SESSION['permisos'] : [])) {
    header("Location: dashboard.php");
    exit;
}

$id_ascensor = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_ascensor) { header("Location: mantenimiento_ascensores.php"); exit; }

// --- LÃ“GICA PARA GUARDAR VISITA TÃ‰CNICA, FIRMA Y CORREO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_visita'])) {
    try {
        $id_inc = filter_input(INPUT_POST, 'id_incidencia', FILTER_VALIDATE_INT);
        $tecnico = trim($_POST['tecnico']);
        $detalle = trim($_POST['detalle_trabajo']);
        $nuevo_estado = trim($_POST['estado']);
        $id_receptor = $_SESSION['usuario_id'];

        // 1. Manejo del archivo adjunto (Foto / Remito)
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

        // 2. Manejo de la Firma Digital (Base64 a PNG)
        $directorio_firmas = "uploads/firmas_tecnicos/";
        if (!is_dir($directorio_firmas)) mkdir($directorio_firmas, 0777, true);
        
        $ruta_firma = null;
        $firma_base64 = $_POST['firma_base64'] ?? '';
        if (!empty($firma_base64)) {
            $firma_base64 = str_replace('data:image/png;base64,', '', $firma_base64);
            $firma_base64 = str_replace(' ', '+', $firma_base64);
            $firma_data = base64_decode($firma_base64);
            $nombre_firma = "firma_tec_" . $id_inc . "_" . time() . ".png";
            if (file_put_contents($directorio_firmas . $nombre_firma, $firma_data)) {
                $ruta_firma = $nombre_firma;
            }
        }

        // 3. Guardar en Base de Datos
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO ascensor_visitas_tecnicas 
            (id_incidencia, fecha_visita, tecnico_nombre, descripcion_trabajo, adjunto_tecnico, firma_tecnico_path, id_receptor) 
            VALUES (?, NOW(), ?, ?, ?, ?, ?)");
        $stmt->execute([$id_inc, $tecnico, $detalle, $ruta_adjunto, $ruta_firma, $id_receptor]);

        $stmt_upd = $pdo->prepare("UPDATE ascensor_incidencias SET estado = ? WHERE id_incidencia = ?");
        $stmt_upd->execute([$nuevo_estado, $id_inc]);

        $pdo->commit();

        // 4. NUEVO: GENERAR PDF FINAL Y ENVIAR CORREOS
        $msg_correo = "";
        try {
            // Buscar datos para el correo (Empresa y Emisor)
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
                // Generar PDF FINAL en segundo plano
                $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
                $url_pdf = $protocolo . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/ascensor_pdf.php?id=" . $id_inc;
                
                $ch = curl_init($url_pdf);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_exec($ch);
                curl_close($ch);

                // Ruta del PDF generado
                $ruta_pdf_fisico = __DIR__ . "/pdfs_publicos/ascensores/Orden_Servicio_" . $id_inc . ".pdf";

                // Armar destinatarios (evitar correos repetidos o vacÃ­os)
                $destinatarios = [];
                if (!empty($mail_data['email_contacto'])) $destinatarios[] = $mail_data['email_contacto'];
                if (!empty($mail_data['email_emisor'])) $destinatarios[] = $mail_data['email_emisor'];
                $destinatarios = array_unique(array_filter($destinatarios));

                if (count($destinatarios) > 0) {
                    $asunto = "ActualizaciÃ³n de Ticket #" . str_pad($id_inc, 5, '0', STR_PAD_LEFT) . " - " . $mail_data['nombre_ascensor'];
                    $estado_fmt = strtoupper(str_replace('_', ' ', $mail_data['estado']));
                    
                    // DiseÃ±o del correo
                    $cuerpoHTML = "
                    <!DOCTYPE html>
                    <html>
                    <body style='font-family: Arial, sans-serif; color: #333; background-color: #f4f6f9; padding: 20px; margin: 0;'>
                        <div style='max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-top: 5px solid #198754; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>
                            <h2 style='color: #2c3e50; margin-top: 0;'>ActualizaciÃ³n de Ticket TÃ©cnico</h2>
                            <p>Se ha registrado una nueva visita y actualizaciÃ³n para el equipo <strong>{$mail_data['nombre_ascensor']}</strong>.</p>
                            
                            <table style='width:100%; border-collapse: collapse; margin-top: 15px;'>
                                <tr><td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>TÃ©cnico / Empresa:</strong></td><td style='padding: 10px; border-bottom: 1px solid #eee;'>{$tecnico}</td></tr>
                                <tr><td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Estado Actual:</strong></td><td style='padding: 10px; border-bottom: 1px solid #eee; color: #198754; font-weight:bold;'>{$estado_fmt}</td></tr>
                                <tr><td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Detalle del trabajo:</strong></td><td style='padding: 10px; border-bottom: 1px solid #eee;'>" . nl2br(htmlspecialchars($detalle)) . "</td></tr>
                            </table>
                            
                            <div style='background-color: #fff3cd; color: #856404; padding: 15px; margin-top: 20px; border-radius: 5px; border-left: 4px solid #ffc107;'>
                                <strong>Documento Adjunto:</strong><br>
                                Encuentren adjunto a este correo el <strong>Reporte TÃ©cnico Oficial</strong> actualizado en formato PDF, conteniendo la bitÃ¡cora y las firmas correspondientes.
                            </div>
                        </div>
                    </body>
                    </html>";

                    $enviados = 0;
                    foreach ($destinatarios as $correo_dest) {
                        $res = enviarCorreoNativo($correo_dest, $asunto, $cuerpoHTML, null, $ruta_pdf_fisico);
                        if ($res === true) $enviados++;
                    }
                    
                    if ($enviados > 0) {
                        $msg_correo = "<br><br><small class='text-success fw-bold'><i class='fas fa-envelope'></i> Se adjuntÃ³ y enviÃ³ el PDF a $enviados destinatario(s).</small>";
                    } else {
                        $msg_correo = "<br><br><small class='text-danger'><i class='fas fa-exclamation-triangle'></i> FallÃ³ el envÃ­o de correos.</small>";
                    }
                } else {
                    $msg_correo = "<br><br><small class='text-muted'><i class='fas fa-info-circle'></i> Sin correos destino para notificar.</small>";
                }
            }
        } catch (Exception $eMail) {
            $msg_correo = "<br><br><small class='text-danger'>Error correo: " . htmlspecialchars($eMail->getMessage()) . "</small>";
        }

        // Mensaje final en SweetAlert
        $_SESSION['swal_msg'] = "Trabajo de <b>$tecnico</b> registrado con Ã©xito.<br>Estado: <b>" . strtoupper(str_replace('_', ' ', $nuevo_estado)) . "</b>" . $msg_correo;
        $_SESSION['swal_type'] = "success";
        
        header("Location: ascensor_detalle.php?id=" . $id_ascensor);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['swal_msg'] = "Error al registrar la visita: " . $e->getMessage();
        $_SESSION['swal_type'] = "error";
        header("Location: ascensor_detalle.php?id=" . $id_ascensor);
        exit;
    }
}

