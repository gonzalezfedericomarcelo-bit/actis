<?php
require_once 'includes/conexion.php';
require_once 'includes/header.php';

// Validar que tenga permiso de recepción
if (!isset($mis_permisos) || !in_array('modulo_recepcion', $mis_permisos)) { 
    echo "<div style='padding:40px 20px;text-align:center;font-weight:bold;color:#ef4444;font-size:1.2rem;'><i class='fa-solid fa-triangle-exclamation'></i> No tienes permisos para acceder a esta sección.</div>"; 
    require_once 'includes/footer.php';
    exit; 
}

$fecha_filtro = isset($_GET['fecha']) ? $conexion->real_escape_string($_GET['fecha']) : date('Y-m-d');
$fecha_legible = date('d/m/Y', strtotime($fecha_filtro));

// Buscar turnos presentes o atendidos
$sql = "SELECT t.*, p.nombre, p.apellido, p.dni 
        FROM turnos t 
        INNER JOIN pacientes p ON t.paciente_id = p.id 
        WHERE t.fecha_turno = '$fecha_filtro' 
        AND (t.estado = 'Presente' OR t.estado = 'Atendido')
        ORDER BY t.hora_turno DESC";
        
$resultado = $conexion->query($sql);
?>
<style>
    .rc-container { max-width: 1400px; margin: 20px auto; padding: 0 15px; font-family: 'Poppins', sans-serif; }
    .rc-panel { background: #ffffff; border-radius: 20px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); margin-bottom: 25px; }
    .rc-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
    .rc-title { font-size: 1.6rem; font-weight: 900; margin: 0; color: #0f172a; display: flex; align-items: center; gap: 10px; }
    
    .rc-filters { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .rc-input { padding: 12px 15px; border: 2px solid #cbd5e1; border-radius: 10px; font-family: 'Poppins', sans-serif; font-weight: 600; outline: none; transition: border 0.2s; color: #1e293b; }
    .rc-input:focus { border-color: #144973; }
    
    .rc-btn { background: #144973; color: white; border: none; padding: 12px 20px; border-radius: 10px; font-weight: 800; cursor: pointer; transition: transform 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.95rem; }
    .rc-btn:active { transform: scale(0.95); }
    
    .rc-table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid #e2e8f0; }
    .rc-table { width: 100%; border-collapse: collapse; min-width: 900px; background: white; }
    .rc-table th { padding: 15px; text-align: left; color: #475569; background: #f8fafc; border-bottom: 2px solid #e2e8f0; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; }
    .rc-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .rc-table tr:hover { background: #f8fafc; }
    
    .rc-estado { padding: 6px 12px; border-radius: 20px; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; white-space: nowrap; border: 1px solid transparent; display: inline-block;}
    .rc-btn-pdf { background: #144973; color: white; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; gap: 6px; transition: transform 0.2s; border: none; }
    .rc-btn-pdf:active { transform: scale(0.95); }
    
    .rc-empty { padding: 50px 20px; text-align: center; color: #64748b; background: #f8fafc; border-radius: 16px; border: 2px dashed #cbd5e1; font-weight: 600; }
</style>

<div class="rc-container">
    <div class="rc-panel">
        <div class="rc-header">
            <h2 class="rc-title">
                <i class="fa-solid fa-file-contract" style="color: #144973;"></i> Historial de Constancias
            </h2>
            
            <form method="GET" action="recepcion_constancias.php" class="rc-filters">
                <input type="date" name="fecha" value="<?php echo $fecha_filtro; ?>" class="rc-input">
                <button type="submit" class="rc-btn"><i class="fa-solid fa-filter"></i> Filtrar</button>
            </form>
        </div>

        <?php if ($resultado && $resultado->num_rows > 0): ?>
            <div class="rc-table-wrap">
                <table class="rc-table">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Paciente</th>
                            <th style="text-align: center;">DNI</th>
                            <th>Profesional</th>
                            <th style="text-align: center;">Servicio</th>
                            <th style="text-align: center;">Estado</th>
                            <th style="text-align: center;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong style="color: #144973; font-size: 1.05rem;"><i class="fa-regular fa-clock"></i> <?php echo date('H:i', strtotime($row['hora_turno'])); ?> hs</strong>
                            </td>
                            <td>
                                <strong style="color: #0f172a; font-size: 1.05rem;"><?php echo mb_strtoupper($row['apellido'], 'UTF-8') . ', ' . htmlspecialchars($row['nombre']); ?></strong>
                            </td>
                            <td style="text-align: center; color: #475569; font-weight: 600;">
                                <i class="fa-regular fa-id-card"></i> <?php echo htmlspecialchars($row['dni']); ?>
                            </td>
                            <td>
                                <strong style="color: #334155;"><i class="fa-solid fa-user-doctor"></i> <?php echo htmlspecialchars($row['profesional']); ?></strong>
                            </td>
                            <td style="text-align: center;">
                                <span style="background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 800; border: 1px solid #e2e8f0;">
                                    <?php echo htmlspecialchars($row['servicio']); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <?php if($row['estado'] == 'Atendido'): ?>
                                    <span class="rc-estado" style="background: #dcfce7; color: #166534; border-color: #bbf7d0;"><i class="fa-solid fa-check"></i> Atendido</span>
                                <?php else: ?>
                                    <span class="rc-estado" style="background: #fef3c7; color: #92400e; border-color: #fde68a;"><i class="fa-solid fa-clock"></i> Presente</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if($row['estado'] == 'Atendido' && !empty($row['firma_paciente'])): ?>
                                    <a href="certificado_asistencia.php?id=<?php echo $row['id']; ?>" target="_blank" class="rc-btn-pdf">
                                        <i class="fa-solid fa-print"></i> Ver PDF
                                    </a>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 0.85rem; font-weight: 700; background: #f8fafc; padding: 8px 12px; border-radius: 8px; border: 1px dashed #cbd5e1; display: inline-block;">
                                        <i class="fa-solid fa-pen-nib"></i> Falta Firma
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="rc-empty">
                <i class="fa-regular fa-folder-open" style="font-size: 3.5rem; margin-bottom: 15px; color: #cbd5e1; display: block;"></i>
                <h3 style="margin: 0; color: #1e293b; font-weight: 800;">Sin Constancias</h3>
                <p>No hay constancias de pacientes registrados para el día <b><?php echo $fecha_legible; ?></b>.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>