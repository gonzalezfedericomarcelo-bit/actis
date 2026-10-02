<?php
// Archivo: ascensor_crear_incidencia.php (CORREGIDO - ERROR 500 SOLUCIONADO)
session_start();
require_once 'includes/conexion.php';
// require_once 'funciones_permisos.php';
require_once 'envio_correo.php'; 

if (!isset($_SESSION['usuario_id']) || !in_array('modulo_totem_ascensores', isset($_SESSION['permisos']) ? $_SESSION['permisos'] : []) && !in_array('modulo_ascensores', isset($_SESSION['permisos']) ? $_SESSION['permisos'] : [])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_ascensor = filter_input(INPUT_POST, 'id_ascensor', FILTER_VALIDATE_INT);
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    $prioridad = $_POST['prioridad'];
    $usuario_id = $_SESSION['usuario_id'];

    if (!$id_ascensor || empty($titulo) || empty($descripcion)) {
        $_SESSION['mensaje'] = "Por favor complete todos los campos.";
        $_SESSION['tipo_mensaje'] = "warning";
        header("Location: mantenimiento_ascensores.php");
        exit;
    }

    try {
        // A. Datos del Usuario (Reply-To)
        $stmt_user = $pdo->prepare("SELECT nombre_completo, email FROM usuarios WHERE id = ?");
        $stmt_user->execute([$usuario_id]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
        $nombre_usuario = $user_data['nombre_completo'] ?? 'Usuario Sistema';
        $email_usuario = $user_data['email'] ?? '';

        // B. Datos Ascensor
        $sql_datos = "SELECT a.nombre as nombre_ascensor, a.ubicacion, e.id_empresa, e.email_contacto, e.nombre as nombre_empresa 
                      FROM ascensores a 
                      LEFT JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa 
                      WHERE a.id_ascensor = ?";
        $stmt_datos = $pdo->prepare($sql_datos);
        $stmt_datos->execute([$id_ascensor]);
        $info = $stmt_datos->fetch(PDO::FETCH_ASSOC);

        if (!$info) throw new Exception("Error datos ascensor.");

        // C. Insertar
        $sql_insert = "INSERT INTO ascensor_incidencias 
                       (id_ascensor, id_empresa, id_usuario_reporta, titulo, descripcion_problema, prioridad, estado, fecha_reporte) 
                       VALUES (?, ?, ?, ?, ?, ?, 'reportado', NOW())";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([$id_ascensor, $info['id_empresa'], $usuario_id, $titulo, $descripcion, $prioridad]);
        $id_incidencia = $pdo->lastInsertId();

        // D. Generar PDF Silenciosamente y Enviar Correo
        $aviso_correo = " (Correo no configurado en empresa)";
        if (!empty($info['email_contacto'])) {
            
            // 1. Llamada local (cURL) para generar y guardar el PDF oficial en el servidor
            $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
            $url_generador_pdf = $protocolo . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/ascensor_orden_pdf.php?id=" . $id_incidencia;
            
            $ch = curl_init($url_generador_pdf);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_exec($ch);
            curl_close($ch);
            
            // 2. Ruta exacta donde se acaba de guardar el PDF generado
            $ruta_pdf_fisico = __DIR__ . "/pdfs_publicos/ascensores/Orden_Ascensor_" . $id_incidencia . ".pdf";

            // 3. Crear un correo formal, elegante y muy corto (la info fuerte ya está en el PDF)
            $asunto = "Orden de Trabajo #" . str_pad($id_incidencia, 5, '0', STR_PAD_LEFT) . " - Equipo: " . $info['nombre_ascensor'];
            
            $cuerpoHTML = "
            <!DOCTYPE html>
            <html>
            <body style='font-family: \"Segoe UI\", Arial, sans-serif; color: #333; background-color: #f4f6f9; padding: 20px; margin: 0;'>
                <div style='max-width: 550px; margin: 0 auto; background: #ffffff; padding: 30px; border-top: 5px solid #dc3545; border-radius: 4px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
                    <h2 style='color: #2c3e50; margin-top: 0; font-size: 20px; text-transform: uppercase;'>Notificación de Servicio</h2>
                    <p style='font-size: 15px; line-height: 1.6;'>Estimados <strong>{$info['nombre_empresa']}</strong>,</p>
                    <p style='font-size: 15px; line-height: 1.6;'>Por medio de la presente les notificamos la generación de una nueva orden de trabajo correspondiente a una falla reportada en el equipo <strong>{$info['nombre_ascensor']}</strong>.</p>
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
            $resultado_envio = enviarCorreoNativo($info['email_contacto'], $asunto, $cuerpoHTML, $email_usuario, $ruta_pdf_fisico);
            
            if ($resultado_envio === true) {
                $pdo->prepare("UPDATE ascensor_incidencias SET estado = 'reclamo_enviado', fecha_reclamo_enviado = NOW() WHERE id_incidencia = ?")->execute([$id_incidencia]);
                
                // CARTEL DETALLADO SOLICITADO
                $_SESSION['mensaje'] = "
                <div class='text-start'>
                    <div class='alert alert-success border-0 bg-light text-center mb-3 fw-bold'>El PDF oficial fue adjuntado y enviado con éxito.</div>
                    <ul class='list-group mb-4 shadow-sm text-dark'>
                        <li class='list-group-item'><b><i class='fas fa-hashtag text-secondary me-2'></i> Operación:</b> #" . str_pad($id_incidencia, 5, '0', STR_PAD_LEFT) . "</li>
                        <li class='list-group-item'><b><i class='fas fa-building text-secondary me-2'></i> Empresa:</b> " . htmlspecialchars($info['nombre_empresa']) . "</li>
                        <li class='list-group-item'><b><i class='fas fa-envelope text-secondary me-2'></i> Enviado a:</b> " . htmlspecialchars($info['email_contacto']) . "</li>
                    </ul>
                    <a href='ascensor_orden_pdf.php?id=$id_incidencia' target='_blank' class='btn btn-danger w-100 fw-bold shadow-sm'><i class='fas fa-file-pdf me-2'></i> Ver PDF Oficial</a>
                </div>";
                $_SESSION['tipo_mensaje'] = "success";

            } else {
                $_SESSION['mensaje'] = "La orden #" . str_pad($id_incidencia, 5, '0', STR_PAD_LEFT) . " se guardó, pero falló el envío del correo.<br><br><small class='text-danger'>Error: $resultado_envio</small>";
                $_SESSION['tipo_mensaje'] = "error";
            }
        } else {
            $_SESSION['mensaje'] = "Orden #" . str_pad($id_incidencia, 5, '0', STR_PAD_LEFT) . " guardada.<br><br><b>Aviso:</b> No se envió correo porque la empresa no tiene email configurado.";
            $_SESSION['tipo_mensaje'] = "warning";
        }

        header("Location: mantenimiento_ascensores.php");
        exit;

    } catch (Exception $e) {
        // ACÁ ESTABA EL ERROR: ESTE BLOQUE CATCH ESTABA BORRADO
        $_SESSION['mensaje'] = "Error inesperado al procesar la incidencia: " . $e->getMessage();
        $_SESSION['tipo_mensaje'] = "error";
        header("Location: mantenimiento_ascensores.php");
        exit;
    }
} else {
    header("Location: mantenimiento_ascensores.php");
    exit;
}
?>
