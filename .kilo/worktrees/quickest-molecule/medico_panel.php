<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';

// Procesar el formulario cuando el médico atiende al paciente
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['turno_id'])) {
    $tid = (int)$_POST['turno_id'];
    $diagnostico = $conexion->real_escape_string($_POST['diagnostico']);
    
    // Subir receta si hay archivo
    $ruta_receta = '';
    if(isset($_FILES['receta_file']) && $_FILES['receta_file']['error'] == 0) {
        $dir = 'uploads/recetas/';
        if(!file_exists($dir)) mkdir($dir, 0777, true);
        $ext = pathinfo($_FILES['receta_file']['name'], PATHINFO_EXTENSION);
        $filename = 'receta_'.$tid.'_'.time().'.'.$ext;
        if(move_uploaded_file($_FILES['receta_file']['tmp_name'], $dir.$filename)) {
            $ruta_receta = $dir.$filename;
        }
    }
    
    $medico_logueado_id = (int)$_SESSION['usuario_id'];
    $sql_upd = "UPDATE turnos SET diagnostico = '$diagnostico', estado = 'Atendido', usuario_medico_id = $medico_logueado_id";
    if($ruta_receta != '') { $sql_upd .= ", receta_ruta = '$ruta_receta'"; }
    $sql_upd .= " WHERE id = $tid";
    
    if($conexion->query($sql_upd)) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarExito('Paciente marcado como Atendido. Enviado a recepción.'); });</script>";
    }
}

// Buscar pacientes en estado "Presente" para hoy
$sql = "SELECT t.*, p.nombre, p.apellido, p.dni FROM turnos t INNER JOIN pacientes p ON t.paciente_id = p.id WHERE t.fecha_turno = CURDATE() AND t.estado = 'Presente' ORDER BY t.hora_turno ASC";
$res = $conexion->query($sql);
?>
<style>
    .panel-consultorio { max-width: 1200px; margin: 30px auto; font-family: 'Poppins', sans-serif; padding: 0 15px; }
    .header-consultorio { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
    .header-consultorio h2 { color: #0f172a; margin: 0; font-weight: 900; font-size: 1.6rem; display: flex; align-items: center; gap: 10px; }
    .search-bar { width: 100%; max-width: 400px; padding: 14px 20px; border-radius: 12px; border: 2px solid #e2e8f0; background: #f8fafc; font-size: 0.95rem; font-weight: 600; font-family: 'Poppins', sans-serif; outline: none; transition: border 0.2s; box-sizing: border-box; }
    .search-bar:focus { border-color: #144973; background: #fff; }
    
    .grid-pacientes { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
    .card-paciente { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s; cursor: pointer; display: flex; flex-direction: column; justify-content: space-between; }
    .card-paciente:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); border-color: #144973; }
    .cp-hora { background: #f8fafc; color: #144973; padding: 6px 14px; border-radius: 20px; font-weight: 800; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px; border: 1px solid #e2e8f0; }
    .cp-nombre { font-size: 1.25rem; font-weight: 900; color: #1e293b; margin: 0 0 5px 0; }
    .cp-dni { font-size: 0.9rem; font-weight: 600; color: #64748b; margin-bottom: 15px; display: flex; align-items: center; gap: 6px; }
    .cp-motivo { font-size: 0.9rem; font-weight: 500; color: #475569; background: #f8fafc; padding: 12px; border-radius: 10px; flex-grow: 1; border: 1px solid #f1f5f9; }
    
    /* Modal de Atención (Flat) */
    .modal-atencion { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 15px; box-sizing: border-box; }
    .modal-atencion.active { display: flex; }
    .modal-content { background: #fff; width: 100%; max-width: 600px; border-radius: 24px; overflow: hidden; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.2); display: flex; flex-direction: column; animation: slideUp 0.2s ease-out; }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .modal-header { background: #f8fafc; padding: 20px 25px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; }
    .modal-header h3 { margin: 0; color: #0f172a; font-weight: 900; font-size: 1.3rem; }
    .btn-close { background: #f1f5f9; border: none; color: #64748b; width: 36px; height: 36px; border-radius: 10px; font-size: 1.2rem; cursor: pointer; transition: 0.2s; display: flex; justify-content: center; align-items: center; }
    .btn-close:hover { background: #fee2e2; color: #ef4444; }
    .modal-body { padding: 25px; }
    .input-group label { display: flex; align-items: center; gap: 8px; font-weight: 800; color: #475569; margin-bottom: 8px; font-size: 0.95rem; }
    .input-textarea { width: 100%; padding: 15px; border-radius: 12px; border: 2px solid #e2e8f0; font-family: 'Poppins', sans-serif; font-size: 0.95rem; font-weight: 500; margin-bottom: 20px; outline: none; transition: border 0.2s; resize: vertical; box-sizing: border-box; background: #f8fafc; }
    .input-textarea:focus { border-color: #144973; background: white; }
    .input-file { width: 100%; padding: 10px; background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; margin-bottom: 25px; cursor: pointer; font-family: 'Poppins', sans-serif; font-weight: 600; color: #64748b; }
    .btn-submit { width: 100%; padding: 16px; background: #10b981; color: white; border: none; border-radius: 12px; font-weight: 800; font-size: 1.05rem; cursor: pointer; transition: transform 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px; text-transform: uppercase; }
    .btn-submit:hover { transform: scale(0.98); }
</style>

<div class="panel-consultorio">
    <div class="header-consultorio">
        <h2><i class="fa-solid fa-user-doctor" style="color: #144973;"></i> Consultorio en Vivo</h2>
        <input type="text" id="buscadorPacientes" class="search-bar" placeholder="🔍 Buscar por nombre, DNI o motivo..." onkeyup="filtrarPacientes()">
    </div>

    <div class="grid-pacientes" id="listaPacientes">
        <?php if($res && $res->num_rows > 0): while($t = $res->fetch_assoc()): ?>
        <div class="card-paciente item-paciente" data-nombre="<?php echo strtolower($t['nombre'].' '.$t['apellido']); ?>" data-dni="<?php echo $t['dni']; ?>" data-motivo="<?php echo strtolower($t['motivo_visita']); ?>" onclick="abrirAtencion(<?php echo $t['id']; ?>, '<?php echo addslashes($t['nombre'].' '.$t['apellido']); ?>', '<?php echo addslashes($t['motivo_visita']); ?>')">
            <div>
                <span class="cp-hora"><i class="fa-regular fa-clock"></i> <?php echo date('H:i', strtotime($t['hora_turno'])); ?></span>
                <h3 class="cp-nombre"><?php echo htmlspecialchars($t['nombre'].' '.$t['apellido']); ?></h3>
                <div class="cp-dni"><i class="fa-regular fa-id-card"></i> DNI: <?php echo $t['dni']; ?></div>
            </div>
            <div class="cp-motivo"><strong style="color: #334155;">Motivo:</strong> <?php echo htmlspecialchars($t['motivo_visita']); ?></div>
        </div>
        <?php endwhile; else: ?>
            <div style="grid-column: 1/-1; text-align:center; padding:60px 20px; background:white; border-radius:20px; border:2px dashed #cbd5e1; color:#64748b;">
                <i class="fa-solid fa-mug-hot" style="font-size:4rem; margin-bottom:20px; color:#cbd5e1;"></i>
                <h3 style="margin:0; font-weight:800; font-size:1.5rem; color:#475569;">Consultorio Vacío</h3>
                <p style="margin-top:8px; font-weight:500; font-size:1rem;">No hay pacientes en sala de espera.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="modalAtencion" class="modal-atencion">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h3 id="modalPacienteNombre">Nombre Paciente</h3>
                <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;" id="modalPacienteMotivo">Motivo</span>
            </div>
            <button class="btn-close" onclick="cerrarAtencion()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" enctype="multipart/form-data" id="formAtencion">
                <input type="hidden" name="turno_id" id="modalTurnoId" value="">
                
                <div class="input-group">
                    <label><i class="fa-solid fa-stethoscope" style="color: #144973;"></i> Diagnóstico y Evolución</label>
                    <textarea name="diagnostico" rows="4" class="input-textarea" placeholder="Escriba aquí las observaciones clínicas..." required></textarea>
                </div>
                
                <div class="input-group">
                    <label><i class="fa-solid fa-file-prescription" style="color: #144973;"></i> Adjuntar Receta / Indicaciones (Opcional)</label>
                    <input type="file" name="receta_file" accept=".pdf,.jpg,.jpeg,.png" class="input-file">
                </div>
                
                <button type="submit" class="btn-submit" onclick="mostrarLoader()"><i class="fa-solid fa-check-double"></i> Finalizar y Enviar a Salida</button>
            </form>
        </div>
    </div>
</div>

<script>
    function filtrarPacientes() {
        const query = document.getElementById('buscadorPacientes').value.toLowerCase();
        const items = document.querySelectorAll('.item-paciente');
        items.forEach(item => {
            const nombre = item.getAttribute('data-nombre');
            const dni = item.getAttribute('data-dni');
            const motivo = item.getAttribute('data-motivo');
            if(nombre.includes(query) || dni.includes(query) || motivo.includes(query)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    function abrirAtencion(id, nombre, motivo) {
        document.getElementById('modalTurnoId').value = id;
        document.getElementById('modalPacienteNombre').textContent = nombre;
        document.getElementById('modalPacienteMotivo').textContent = "Motivo: " + motivo;
        document.getElementById('modalAtencion').classList.add('active');
    }

    function cerrarAtencion() {
        document.getElementById('modalAtencion').classList.remove('active');
        document.getElementById('formAtencion').reset();
    }
</script>
<?php require_once 'includes/footer.php'; ?>