<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';

// Bloqueo de seguridad: Si no tiene permiso, lo saca
if(!isset($_SESSION['permisos']) || !in_array('modulo_turnos_editar', $_SESSION['permisos'])) {
    echo "<div style='text-align:center; padding:50px;'><h2>Acceso Denegado</h2><p>No tienes permiso para editar turnos.</p></div>";
    require_once 'includes/footer.php';
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['editar_turno'])) {
    $fecha = $conexion->real_escape_string($_POST['fecha_turno']);
    $hora = $conexion->real_escape_string($_POST['hora_turno']);
    $servicio = $conexion->real_escape_string($_POST['servicio']);
    $especialidad = $conexion->real_escape_string($_POST['especialidad']);
    $profesional = $conexion->real_escape_string($_POST['profesional']);
    $estado = $conexion->real_escape_string($_POST['estado']);
    
    $conexion->query("UPDATE turnos SET fecha_turno='$fecha', hora_turno='$hora', servicio='$servicio', especialidad='$especialidad', profesional='$profesional', estado='$estado' WHERE id=$id");
    
    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({icon: 'success', title: 'Guardado', text: 'El turno fue actualizado correctamente.', confirmButtonColor: '#10b981'}).then(() => { window.location = 'turnos_listar.php'; }); });</script>";
}

$q = $conexion->query("SELECT t.*, p.nombre, p.apellido, p.dni FROM turnos t INNER JOIN pacientes p ON t.paciente_id = p.id WHERE t.id = $id");
if(!$q || $q->num_rows == 0) { echo "<div style='text-align:center; padding:50px;'><h2>Turno no encontrado.</h2></div>"; require_once 'includes/footer.php'; exit; }
$turno = $q->fetch_assoc();
?>
<style>
    .edit-container { max-width: 800px; margin: 30px auto; background: #ffffff; padding: 30px; border-radius: 20px; border: 1px solid #e2e8f0; font-family: 'Poppins', sans-serif; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); }
    .edit-header { color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 20px; font-size: 1.5rem; font-weight: 800; display: flex; align-items: center; gap: 10px; margin-top: 0; }
    .edit-info { font-size: 1rem; color: #475569; background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #f1f5f9; margin-bottom: 25px; }
    .edit-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
    .edit-group { display: flex; flex-direction: column; gap: 6px; }
    .edit-label { font-weight: 800; color: #475569; font-size: 0.85rem; text-transform: uppercase; }
    .edit-input { width: 100%; padding: 12px 15px; border-radius: 10px; border: 2px solid #cbd5e1; outline: none; font-family: 'Poppins', sans-serif; font-size: 0.95rem; font-weight: 600; color: #1e293b; background: #f8fafc; transition: border 0.2s; box-sizing: border-box; }
    .edit-input:focus { border-color: #144973; background: #ffffff; }
    .edit-actions { display: flex; gap: 15px; justify-content: flex-end; border-top: 1px solid #e2e8f0; padding-top: 20px; flex-wrap: wrap; }
    .btn-edit-action { padding: 12px 25px; border-radius: 10px; font-weight: 800; font-size: 0.95rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; transition: transform 0.2s; justify-content: center; }
    .btn-edit-action:active { transform: scale(0.98); }
    .btn-cancelar { background: #e2e8f0; color: #475569; }
    .btn-guardar { background: #144973; color: white; }
    @media (max-width: 768px) { .edit-form-grid { grid-template-columns: 1fr; } .btn-edit-action { width: 100%; } }
</style>

<div class="edit-container">
    <h2 class="edit-header">
        <i class="fa-solid fa-pen-to-square" style="color: #144973;"></i> Editar Turno Manualmente
    </h2>
    <div class="edit-info">
        Paciente: <strong style="color: #1e293b; font-size: 1.1rem;"><?php echo htmlspecialchars($turno['apellido'].', '.$turno['nombre']); ?></strong> (DNI: <?php echo htmlspecialchars($turno['dni']); ?>)
    </div>
    
    <form method="POST">
        <input type="hidden" name="editar_turno" value="1">
        
        <div class="edit-form-grid">
            <div class="edit-group">
                <label class="edit-label">Fecha del Turno</label>
                <input type="date" name="fecha_turno" value="<?php echo htmlspecialchars($turno['fecha_turno']); ?>" class="edit-input" required>
            </div>
            <div class="edit-group">
                <label class="edit-label">Hora del Turno</label>
                <input type="time" name="hora_turno" value="<?php echo htmlspecialchars($turno['hora_turno']); ?>" class="edit-input" required>
            </div>
            <div class="edit-group">
                <label class="edit-label">Servicio</label>
                <input type="text" name="servicio" value="<?php echo htmlspecialchars($turno['servicio']); ?>" class="edit-input">
            </div>
            <div class="edit-group">
                <label class="edit-label">Especialidad</label>
                <input type="text" name="especialidad" value="<?php echo htmlspecialchars($turno['especialidad']); ?>" class="edit-input">
            </div>
            <div class="edit-group">
                <label class="edit-label">Profesional Asignado</label>
                <input type="text" name="profesional" value="<?php echo htmlspecialchars($turno['profesional']); ?>" class="edit-input">
            </div>
            <div class="edit-group">
                <label class="edit-label">Estado del Turno</label>
                <select name="estado" class="edit-input">
                    <option value="Pendiente" <?php echo ($turno['estado'] == 'Pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                    <option value="Autorizado" <?php echo ($turno['estado'] == 'Autorizado') ? 'selected' : ''; ?>>Autorizado</option>
                    <option value="Presente" <?php echo ($turno['estado'] == 'Presente') ? 'selected' : ''; ?>>Presente</option>
                    <option value="Atendido" <?php echo ($turno['estado'] == 'Atendido') ? 'selected' : ''; ?>>Atendido</option>
                    <option value="Cancelado" <?php echo ($turno['estado'] == 'Cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                </select>
            </div>
        </div>
        
        <div class="edit-actions">
            <a href="turnos_listar.php" class="btn-edit-action btn-cancelar">Cancelar</a>
            <button type="submit" class="btn-edit-action btn-guardar" onclick="mostrarLoader()"><i class="fa-solid fa-floppy-disk"></i> Guardar Cambios</button>
        </div>
    </form>
</div>
<?php require_once 'includes/footer.php'; ?>