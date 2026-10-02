<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';

// Busca pacientes que el médico ya vio (estado Atendido) pero que aún no firmaron (firma nula)
$sql = "SELECT t.*, p.nombre, p.apellido, p.dni FROM turnos t INNER JOIN pacientes p ON t.paciente_id = p.id WHERE t.fecha_turno = CURDATE() AND t.estado = 'Atendido' AND (t.firma_paciente IS NULL OR t.firma_paciente = '') ORDER BY t.hora_turno ASC";
$res = $conexion->query($sql);
?>
<style>
    .rs-container { max-width: 1400px; margin: 20px auto; padding: 0 15px; font-family: 'Poppins', sans-serif; }
    .rs-panel { background: #ffffff; border-radius: 20px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); margin-bottom: 25px; }
    .rs-header { font-size: 1.6rem; font-weight: 900; margin-bottom: 5px; color: #0f172a; display: flex; align-items: center; gap: 10px; margin-top: 0; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; }
    .rs-subtitle { color: #64748b; font-size: 0.95rem; font-weight: 600; margin-bottom: 20px; }
    
    .rs-grid { display: grid; gap: 15px; }
    .rs-card { background: #f8fafc; padding: 20px; border-radius: 16px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #e2e8f0; flex-wrap: wrap; gap: 15px; transition: transform 0.2s; }
    .rs-card:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.05); border-color: #144973; }
    
    .rs-name { margin: 0 0 5px 0; color: #1e293b; font-weight: 900; font-size: 1.25rem; }
    .rs-info { color: #64748b; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
    
    .rs-btn { background: #144973; color: white; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 800; font-size: 0.95rem; transition: transform 0.2s; white-space: nowrap; display: inline-flex; align-items: center; gap: 8px; border: none; }
    .rs-btn:active { transform: scale(0.95); }
    
    .rs-empty { text-align: center; padding: 50px 20px; background: #f8fafc; border-radius: 16px; color: #64748b; border: 2px dashed #cbd5e1; }
</style>

<div class="rs-container">
    <div class="rs-panel">
        <h2 class="rs-header">
            <i class="fa-solid fa-person-walking-arrow-right" style="color: #144973;"></i> Recepción: Checkout y Firmas
        </h2>
        <p class="rs-subtitle">Pacientes que ya salieron del consultorio y deben firmar su constancia.</p>
        
        <div class="rs-grid">
        <?php if($res && $res->num_rows > 0): while($t = $res->fetch_assoc()): ?>
            <div class="rs-card">
                <div>
                    <h3 class="rs-name"><?php echo htmlspecialchars($t['nombre'].' '.$t['apellido']); ?></h3>
                    <span class="rs-info">
                        <i class="fa-regular fa-id-card"></i> DNI: <?php echo htmlspecialchars($t['dni']); ?> &nbsp;|&nbsp; 
                        <i class="fa-solid fa-user-doctor"></i> Médico: <?php echo htmlspecialchars($t['profesional']); ?>
                    </span>
                </div>
                <a href="recepcion_salida.php?id=<?php echo $t['id']; ?>" class="rs-btn">
                    <i class="fa-solid fa-pen-nib"></i> Tomar Firma
                </a>
            </div>
        <?php endwhile; else: ?>
            <div class="rs-empty">
                <i class="fa-solid fa-check-double" style="font-size:3.5rem; margin-bottom:15px; color: #cbd5e1;"></i><br>
                <span style="font-weight: 700; font-size: 1.1rem; color: #475569;">No hay pacientes pendientes de firma en este momento.</span>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>