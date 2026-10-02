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

// --- CARGAR DATOS PARA LA VISTA ---
$sql_asc = "SELECT a.*, e.nombre as nombre_empresa 
            FROM ascensores a 
            LEFT JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa 
            WHERE a.id_ascensor = ?";
$stmt = $pdo->prepare($sql_asc);
$stmt->execute([$id_ascensor]);
$asc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$asc) die("Ascensor no encontrado.");

$sql_hist = "SELECT i.*, u.nombre_completo as usuario_reporta 
             FROM ascensor_incidencias i 
             LEFT JOIN usuarios u ON i.id_usuario_reporta = u.id 
             WHERE i.id_ascensor = ? 
             ORDER BY i.fecha_reporte DESC";
$stmt_h = $pdo->prepare($sql_hist);
$stmt_h->execute([$id_ascensor]);
$historial = $stmt_h->fetchAll(PDO::FETCH_ASSOC);

// Buscar las visitas de cada incidencia y el nombre del guardia
foreach ($historial as &$h) {
    $sql_visitas = "SELECT v.*, r.nombre_completo as guardia 
                    FROM ascensor_visitas_tecnicas v
                    LEFT JOIN usuarios r ON v.id_receptor = r.id
                    WHERE v.id_incidencia = ? ORDER BY v.fecha_visita ASC";
    $stmt_v = $pdo->prepare($sql_visitas);
    $stmt_v->execute([$h['id_incidencia']]);
    $h['visitas'] = $stmt_v->fetchAll(PDO::FETCH_ASSOC);
}
unset($h);
?>
<?php include 'includes/header.php'; ?>
<title>BitÃ¡cora - <?php echo htmlspecialchars($asc['nombre']); ?></title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">

<style>
    body { font-family: 'Outfit', sans-serif; background: #f1f5f9; color: #334155; margin: 0; padding: 0; }
    
    /* Layout and Grid */
    .detail-container { max-width: 800px; margin: 0 auto; padding: 15px; }
    
    /* Typography Overrides for Mobile */
    .title-main { font-size: 1.5rem; font-weight: 700; margin: 0; color: #1e293b; letter-spacing: -0.5px; }
    .subtitle-main { font-size: 0.85rem; color: #64748b; margin: 2px 0 0 0; }
    
    .card-list { display: flex; flex-direction: column; gap: 12px; margin-top: 15px; }
    .card-item { background: #fff; border-radius: 12px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); cursor: pointer; border-left: 4px solid transparent; display: flex; align-items: center; justify-content: space-between; gap: 10px; transition: 0.2s; }
    .card-item:active { transform: scale(0.98); }
    
    .date-box { text-align: center; min-width: 65px; border-right: 1px solid #e2e8f0; padding-right: 10px; }
    .date-box .lbl { font-size: 0.65rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; }
    .date-box .val { font-size: 1rem; font-weight: 700; color: #0f172a; }
    
    .info-box { flex-grow: 1; overflow: hidden; }
    .info-box .title { font-size: 1rem; font-weight: 700; color: #1e293b; margin: 0 0 3px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .info-box .reporter { font-size: 0.75rem; color: #64748b; margin: 0; display: flex; align-items: center; gap: 4px; }
    
    .status-badge { font-size: 0.7rem; font-weight: 700; padding: 4px 8px; border-radius: 6px; white-space: nowrap; text-transform: uppercase; }
    
    /* Native Modal Overlay */
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.8); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 15px; backdrop-filter: blur(4px); }
    .modal-box { background: #fff; width: 100%; max-width: 500px; border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; max-height: 90vh; box-shadow: 0 20px 40px rgba(0,0,0,0.2); animation: popIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
    @keyframes popIn { 0% { opacity: 0; transform: scale(0.95) translateY(10px); } 100% { opacity: 1; transform: scale(1) translateY(0); } }
    
    .modal-head { padding: 15px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
    .modal-head h3 { font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0; }
    .modal-close { background: none; border: none; font-size: 1.5rem; color: #94a3b8; padding: 0; line-height: 1; cursor: pointer; }
    
    .modal-body { padding: 20px; overflow-y: auto; flex-grow: 1; }
    .modal-body-form { padding: 15px; overflow-y: auto; }
    
    /* Detail Components */
    .detail-section-title { font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 5px; }
    .detail-desc { font-size: 0.9rem; color: #334155; line-height: 1.5; margin: 0; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; }
    
    .visit-card { background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; margin-bottom: 10px; }
    .visit-card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
    .visit-tech { font-size: 0.85rem; font-weight: 700; color: #0f172a; }
    .visit-date { font-size: 0.7rem; color: #64748b; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }
    .visit-txt { font-size: 0.8rem; color: #475569; margin: 0 0 8px 0; line-height: 1.4; }
    .visit-auth { font-size: 0.7rem; font-weight: 600; color: #d97706; background: #fef3c7; padding: 3px 8px; border-radius: 4px; display: inline-block; }
    
    /* Form & Buttons */
    .form-group { margin-bottom: 12px; }
    .form-label { display: block; font-size: 0.8rem; font-weight: 600; color: #475569; margin-bottom: 4px; }
    .form-control { width: 100%; padding: 10px; font-size: 0.9rem; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; font-family: 'Outfit'; }
    .form-control:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
    
    .btn-actis { width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 0.9rem; font-weight: 600; padding: 12px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; box-sizing: border-box; }
    .btn-primary { background: #4f46e5; color: #fff; }
    .btn-success { background: #10b981; color: #fff; }
    .btn-danger { background: #ef4444; color: #fff; }
    .btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
    
    /* Canvas */
    .canvas-wrap { border: 2px dashed #cbd5e1; border-radius: 8px; background: #fff; overflow: hidden; position: relative; }
    .canvas-pad { width: 100%; height: 160px; touch-action: none; display: block; }
    
    /* Util */
    .d-none { display: none !important; }
    .mt-3 { margin-top: 15px; } .mb-3 { margin-bottom: 15px; } .mb-4 { margin-bottom: 20px; }
    .flex-row-gap { display: flex; gap: 10px; }
</style>

<div class="detail-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 class="title-main"><?php echo htmlspecialchars($asc['nombre']); ?></h1>
            <p class="subtitle-main"><i class="fas fa-map-marker-alt text-danger"></i> <?php echo htmlspecialchars($asc['ubicacion']); ?></p>
        </div>
        <a href="mantenimiento_ascensores.php" class="btn-actis btn-secondary" style="width: auto; padding: 8px 12px; font-size: 0.8rem;">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div style="background: #e0e7ff; border-radius: 8px; padding: 12px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <div style="font-size: 0.7rem; color: #4f46e5; font-weight: 700; text-transform: uppercase;">Proveedor Asignado</div>
            <div style="font-size: 0.95rem; font-weight: 700; color: #312e81;"><i class="fas fa-tools me-1"></i><?php echo htmlspecialchars($asc['nombre_empresa'] ?? 'Sin Asignar'); ?></div>
        </div>
    </div>

    <div class="card-list">
        <?php if (empty($historial)): ?>
            <div style="text-align: center; padding: 40px 20px; background: #fff; border-radius: 12px;">
                <i class="fas fa-check-shield fa-3x text-success mb-2" style="opacity: 0.3;"></i>
                <h3 style="font-size: 1.1rem; margin: 0; color: #1e293b;">Equipo sin reportes</h3>
                <p style="font-size: 0.85rem; color: #64748b; margin: 5px 0 0 0;">El historial estÃ¡ limpio.</p>
            </div>
        <?php else: ?>
            <?php foreach($historial as $h): ?>
                <?php 
                    $estado = trim($h['estado']);
                    $bcolor = '#ef4444'; $bg = '#fee2e2'; $tx = '#ef4444'; $ic = 'fa-exclamation-triangle';
                    if ($estado == 'resuelto') { $bcolor = '#10b981'; $bg = '#d1fae5'; $tx = '#10b981'; $ic = 'fa-check'; }
                    elseif ($estado == 'en_proceso') { $bcolor = '#f59e0b'; $bg = '#fef3c7'; $tx = '#d97706'; $ic = 'fa-tools'; }
                ?>
                <div class="card-item" style="border-left-color: <?php echo $bcolor; ?>;" onclick='abrirModalDetalle(<?php echo json_encode($h); ?>)'>
                    <div class="date-box">
                        <div class="lbl">EmisiÃ³n</div>
                        <div class="val"><?php echo date('d/m', strtotime($h['fecha_reporte'])); ?></div>
                    </div>
                    <div class="info-box">
                        <div class="title">#<?php echo $h['id_incidencia']; ?> - <?php echo htmlspecialchars($h['titulo']); ?></div>
                        <div class="reporter"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($h['usuario_reporta'] ?? 'Sistema'); ?></div>
                    </div>
                    <div>
                        <span class="status-badge" style="background: <?php echo $bg; ?>; color: <?php echo $tx; ?>;">
                            <i class="fas <?php echo $ic; ?>"></i>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Detalle -->
<div class="modal-overlay" id="modalDetalle">
    <div class="modal-box">
        <div class="modal-head">
            <h3><i class="fas fa-ticket-alt text-primary me-2"></i> Ticket #<span id="md_id"></span></h3>
            <button class="modal-close" onclick="cerrarModal('modalDetalle')">&times;</button>
        </div>
        <div class="modal-body">
            <h4 style="font-size: 1.1rem; color: #0f172a; margin: 0 0 15px 0; font-weight: 700;" id="md_titulo"></h4>
            
            <div class="detail-section-title">Falla Reportada</div>
            <div class="detail-desc mb-4" id="md_desc"></div>
            
            <div class="detail-section-title"><i class="fas fa-list text-primary"></i> Visitas TÃ©cnicas</div>
            <div id="md_visitas_container" class="mb-4"></div>
            
            <div class="flex-row-gap">
                <a href="#" id="btn_pdf" target="_blank" class="btn-actis btn-secondary" style="flex: 1;">
                    <i class="fas fa-file-pdf text-danger"></i> PDF
                </a>
                <button type="button" id="btn_registrar" class="btn-actis btn-primary" style="flex: 2;" onclick="abrirModalFirma()">
                    <i class="fas fa-tools"></i> Registrar Visita
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Firma -->
<div class="modal-overlay" id="modalFirma">
    <div class="modal-box">
        <div class="modal-head">
            <h3><i class="fas fa-pen-nib text-success me-2"></i> Reportar Trabajo</h3>
            <button class="modal-close" onclick="cerrarModalFirma()">&times;</button>
        </div>
        <div class="modal-body-form">
            <form action="ascensor_detalle.php?id=<?php echo $id_ascensor; ?>" method="POST" onsubmit="return enviarFirma()">
                <input type="hidden" name="guardar_visita" value="1">
                <input type="hidden" name="id_incidencia" id="mf_id_incidencia">
                <input type="hidden" name="firma_base64" id="firma_base64">
                
                <div class="flex-row-gap form-group">
                    <div style="flex: 1;">
                        <label class="form-label">TÃ©cnico / Empresa</label>
                        <input type="text" name="tecnico" class="form-control" required placeholder="Nombre">
                    </div>
                    <div style="flex: 1;">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-control">
                            <option value="resuelto">ðŸŸ¢ Resuelto</option>
                            <option value="en_proceso">ðŸŸ  En Proceso</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Detalle Realizado</label>
                    <textarea name="detalle_trabajo" class="form-control" rows="2" required placeholder="Repuestos, ajustes..."></textarea>
                </div>
                
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 4px;">
                        <label class="form-label" style="margin: 0;">Firma Digital</label>
                        <button type="button" onclick="signaturePad.clear()" style="background:none; border:none; color:#ef4444; font-size:0.7rem; font-weight:700; padding:0; cursor:pointer;">BORRAR</button>
                    </div>
                    <div class="canvas-wrap">
                        <canvas class="canvas-pad" id="signature-pad"></canvas>
                    </div>
                </div>
                
                <div class="flex-row-gap mt-3">
                    <button type="button" class="btn-actis btn-secondary" onclick="cerrarModalFirma()">Cancelar</button>
                    <button type="submit" class="btn-actis btn-success"><i class="fas fa-paper-plane"></i> Procesar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let actual_id = 0;
    const canvas = document.getElementById('signature-pad');
    const signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgb(255, 255, 255)' });

    function resizeCanvas() {
        const ratio =  Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
        signaturePad.clear();
    }
    window.addEventListener("resize", resizeCanvas);

    function abrirModalDetalle(data) {
        actual_id = data.id_incidencia;
        document.getElementById('md_id').innerText = data.id_incidencia;
        document.getElementById('md_titulo').innerText = data.titulo;
        document.getElementById('md_desc').innerHTML = data.descripcion_problema.replace(/\n/g, "<br>");
        document.getElementById('mf_id_incidencia').value = data.id_incidencia;

        let vHTML = '';
        if (data.visitas && data.visitas.length > 0) {
            data.visitas.forEach(v => {
                let guardia = v.guardia ? v.guardia : 'Sistema';
                let txt = v.descripcion_trabajo.replace(/\n/g, "<br>");
                let fecha = v.fecha_visita.substring(0, 16).replace('T', ' '); // simplified
                vHTML += `
                <div class="visit-card">
                    <div class="visit-card-head">
                        <div class="visit-tech"><i class="fas fa-user-cog text-primary"></i> ${v.tecnico_nombre}</div>
                        <div class="visit-date">${fecha}</div>
                    </div>
                    <p class="visit-txt">${txt}</p>
                    <div class="visit-auth"><i class="fas fa-shield-alt"></i> Auth: ${guardia}</div>
                </div>`;
            });
        } else {
            vHTML = `<div style="text-align:center; padding: 15px; color:#94a3b8; font-size: 0.85rem;">No hay visitas previas.</div>`;
        }
        document.getElementById('md_visitas_container').innerHTML = vHTML;

        let estado = data.estado.trim();
        let btnPdf = document.getElementById('btn_pdf');
        let btnReg = document.getElementById('btn_registrar');

        if (estado === 'resuelto') {
            btnReg.classList.add('d-none');
            btnPdf.href = 'ascensor_pdf.php?id=' + data.id_incidencia;
            btnPdf.style.background = '#10b981'; btnPdf.style.color = '#fff';
        } else {
            btnReg.classList.remove('d-none');
            btnPdf.href = 'ascensor_orden_pdf.php?id=' + data.id_incidencia;
            btnPdf.style.background = '#f1f5f9'; btnPdf.style.color = '#475569';
        }

        document.getElementById('modalDetalle').style.display = 'flex';
    }

    function cerrarModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    function abrirModalFirma() {
        cerrarModal('modalDetalle');
        document.getElementById('modalFirma').style.display = 'flex';
        setTimeout(resizeCanvas, 50);
    }

    function cerrarModalFirma() {
        cerrarModal('modalFirma');
        document.getElementById('modalDetalle').style.display = 'flex';
    }

    function enviarFirma() {
        if (signaturePad.isEmpty()) {
            Swal.fire({ title: 'Firma Requerida', text: 'El tÃ©cnico debe firmar.', icon: 'warning', confirmButtonColor: '#4f46e5' });
            return false;
        }
        document.getElementById('firma_base64').value = signaturePad.toDataURL();
        Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        return true;
    }
</script>

<?php if (isset($_SESSION['swal_msg'])): ?>
<script>
    Swal.fire({
        icon: '<?php echo $_SESSION['swal_type']; ?>',
        title: 'AtenciÃ³n',
        html: `<?php echo $_SESSION['swal_msg']; ?>`,
        confirmButtonColor: '#4f46e5'
    });
</script>
<?php unset($_SESSION['swal_msg'], $_SESSION['swal_type']); ?>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
