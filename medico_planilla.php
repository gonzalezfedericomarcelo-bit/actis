<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';

if(!isset($_SESSION['permisos']) || !in_array('modulo_medico_planilla', $_SESSION['permisos'])) {
    echo "<div style='text-align:center; padding:50px;'><h2>Acceso Denegado</h2><p>No tienes el rol necesario para ver la planilla médica.</p></div>";
    require_once 'includes/footer.php';
    exit;
}

$medico_id = (int)$_SESSION['usuario_id'];
$fecha = date('Y-m-d');
$nombre_medico = $conexion->real_escape_string($_SESSION['nombre']);

$sql = "SELECT t.*, p.nombre, p.apellido, p.dni FROM turnos t INNER JOIN pacientes p ON t.paciente_id = p.id WHERE (t.usuario_medico_id = $medico_id OR (t.profesional = '$nombre_medico' AND t.estado IN ('Presente', 'Atendido'))) AND t.fecha_turno = '$fecha' ORDER BY t.hora_turno ASC";
$res = $conexion->query($sql);
?>
<style>
    .mp-container { max-width: 1000px; margin: 30px auto; font-family: 'Poppins', sans-serif; padding: 0 15px; }
    .mp-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
    .mp-title { margin: 0; color: #0f172a; font-weight: 900; font-size: 1.6rem; display: flex; align-items: center; gap: 10px; }
    .mp-btn-print { background: #10b981; color: white; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 800; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 8px; transition: transform 0.2s; }
    .mp-btn-print:active { transform: scale(0.95); }
    
    .mp-table-wrap { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow-x: auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); }
    .mp-table { width: 100%; border-collapse: collapse; text-align: left; min-width: 800px; }
    .mp-table th { padding: 16px; color: #475569; font-weight: 800; font-size: 0.85rem; text-transform: uppercase; background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
    .mp-table td { padding: 16px; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; vertical-align: middle; }
    .mp-table tr:hover td { background: #f8fafc; }
    
    .mp-estado { background: #dcfce7; color: #166534; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; border: 1px solid #bbf7d0; display: inline-block; white-space: nowrap; }
    .mp-empty { padding: 50px; text-align: center; color: #64748b; background: #f8fafc; }
</style>

<div class="mp-container">
    <div class="mp-header">
        <h2 class="mp-title"><i class="fa-solid fa-file-invoice" style="color:#144973;"></i> Mis Atenciones del Día</h2>
        <a href="planilla_medico_pdf.php?fecha=<?php echo $fecha; ?>&medico_id=<?php echo $medico_id; ?>" target="_blank" class="mp-btn-print">
            <i class="fa-solid fa-print"></i> Imprimir Mi Planilla PDF
        </a>
    </div>

    <div class="mp-table-wrap">
        <table class="mp-table">
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Paciente</th>
                    <th>DNI</th>
                    <th>Diagnóstico Registrado</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if($res && $res->num_rows > 0): while($row = $res->fetch_assoc()): ?>
                <tr>
                    <td style="font-weight: 800; color: #144973; font-size: 1.05rem;">
                        <?php echo date('H:i', strtotime($row['hora_turno'])); ?>
                    </td>
                    <td style="font-weight: 800; color: #1e293b; font-size: 1.05rem;">
                        <?php echo htmlspecialchars($row['apellido'].', '.$row['nombre']); ?>
                    </td>
                    <td style="font-weight: 600; color: #64748b;">
                        <i class="fa-regular fa-id-card"></i> <?php echo $row['dni']; ?>
                    </td>
                    <td style="color: #475569; font-weight: 500; font-size: 0.95rem; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?php echo htmlspecialchars($row['diagnostico']); ?>
                    </td>
                    <td>
                        <span class="mp-estado"><?php echo $row['estado']; ?></span>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="5" class="mp-empty">
                        <i class="fa-solid fa-folder-open" style="font-size: 3rem; display: block; margin-bottom: 15px; color: #cbd5e1;"></i> 
                        <span style="font-weight: 700; font-size: 1.1rem;">Aún no has registrado firmas ni atenciones hoy.</span>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>