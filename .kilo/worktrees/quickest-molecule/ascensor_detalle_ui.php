<?php include 'includes/header.php'; ?>
<title>Bitácora - <?php echo htmlspecialchars($asc['nombre']); ?></title>
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
                <p style="font-size: 0.85rem; color: #64748b; margin: 5px 0 0 0;">El historial está limpio.</p>
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
                        <div class="lbl">Emisión</div>
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
            
            <div class="detail-section-title"><i class="fas fa-list text-primary"></i> Visitas Técnicas</div>
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
                        <label class="form-label">Técnico / Empresa</label>
                        <input type="text" name="tecnico" class="form-control" required placeholder="Nombre">
                    </div>
                    <div style="flex: 1;">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-control">
                            <option value="resuelto">🟢 Resuelto</option>
                            <option value="en_proceso">🟠 En Proceso</option>
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
            Swal.fire({ title: 'Firma Requerida', text: 'El técnico debe firmar.', icon: 'warning', confirmButtonColor: '#4f46e5' });
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
        title: 'Atención',
        html: `<?php echo $_SESSION['swal_msg']; ?>`,
        confirmButtonColor: '#4f46e5'
    });
</script>
<?php unset($_SESSION['swal_msg'], $_SESSION['swal_type']); ?>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
