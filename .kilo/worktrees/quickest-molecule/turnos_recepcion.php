<?php
require_once 'includes/conexion.php'; 
require_once 'includes/header.php';

// --- Configuración e Idioma ---
$fecha_actual_sql = date('Y-m-d');
$fecha_actual_legible = date('d/m/Y');
setlocale(LC_TIME, 'es_ES.UTF-8'); 

// --- Lógica de Búsqueda/Filtro ---
$filtro_dni = isset($_GET['search_dni']) ? $conexion->real_escape_string($_GET['search_dni']) : '';

// --- Consulta a la Base de Datos (Segura, uniendo tablas) ---
$sql = "SELECT 
            t.id as turno_id,
            t.fecha_turno,
            t.hora_turno,
            t.servicio,
            t.especialidad,
            t.profesional,
            t.motivo_visita as practicas,
            t.comentario_paciente,
            t.estado,
            t.codigo_ticket_totem,
            p.nombre,
            p.apellido,
            p.dni,
            p.hc as afiliado,
            p.telefono,
            p.email
        FROM turnos t
        INNER JOIN pacientes p ON t.paciente_id = p.id
        WHERE t.fecha_turno = '$fecha_actual_sql'";

// Aplicamos filtro de búsqueda si existe
if ($filtro_dni != '') {
    $sql .= " AND (p.dni LIKE '%$filtro_dni%' OR p.nombre LIKE '%$filtro_dni%' OR p.apellido LIKE '%$filtro_dni%')";
}

// Ordenamos por hora para la recepción
$sql .= " ORDER BY t.hora_turno ASC";

$resultado = $conexion->query($sql);
?>

<style>
    .rec-wrapper { font-family: 'Poppins', sans-serif; background: #f8fafc; padding: 15px; margin: 0 auto; max-width: 1400px; }
    .rec-panel { background: #fff; padding: 25px; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); margin-bottom: 25px; border: 1px solid #e2e8f0; }
    .rec-header { font-size: 1.3rem; font-weight: 800; color: #0f172a; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
    
    .btn { padding: 12px 20px; border: none; border-radius: 10px; cursor: pointer; text-decoration: none; font-size: 0.9rem; font-weight: 800; color: white; display: inline-flex; align-items: center; gap: 8px; transition: transform 0.2s; white-space: nowrap; }
    .btn:active { transform: scale(0.98); }
    .btn-primary { background-color: #144973; } 
    .btn-success { background-color: #10b981; }
    .btn-gray { background-color: #64748b; }
    
    .table-container { overflow-x: auto; width: 100%; border-radius: 12px; border: 1px solid #e2e8f0; }
    table { width: 100%; border-collapse: collapse; font-size: 0.9rem; background: white; min-width: 900px; }
    th, td { text-align: left; padding: 16px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    th { background-color: #f8fafc; color: #475569; font-weight: 800; text-transform: uppercase; font-size: 0.8rem; border-bottom: 2px solid #e2e8f0; }
    tr:hover { background-color: #f1f5f9; }
    
    .status { font-weight: 900; padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; text-transform: uppercase; white-space: nowrap; border: 1px solid transparent; }
    .status-programado { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
    .status-autorizado { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
    .status-atendido { background: #dcfce7; color: #166534; border-color: #bbf7d0; } 
    .status-presente { background: #10b981; color: white; border-color: #059669; }
    
    .search-box { display: flex; gap: 10px; margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 16px; border: 1px solid #e2e8f0; flex-wrap: wrap; }
    .input-text { padding: 12px 16px; border: 2px solid #cbd5e1; border-radius: 10px; flex-grow: 1; font-size: 0.95rem; font-weight: 600; outline: none; transition: border 0.2s; min-width: 250px; font-family: 'Poppins', sans-serif; }
    .input-text:focus { border-color: #144973; }
    
    .alert-none { padding: 40px; text-align: center; color: #64748b; background: white; border-radius: 16px; border: 2px dashed #cbd5e1; margin-top: 10px; font-weight: 700; font-size: 1.1rem; }
</style>

<div class="rec-wrapper">
    <div class="rec-panel">
        <div class="rec-header">
            <div>
                <i class="fa-solid fa-bell-concierge" style="color:#144973;"></i> Recepción y Asistencia 
                <span style="font-size:0.85rem; color: #64748b; font-weight:700; margin-left:10px; background:#f1f5f9; padding:4px 8px; border-radius:8px;">SGPS IOSFA</span>
            </div>
            <span style="font-size:0.95rem; color: #475569;">Hoy: <strong style="color:#0f172a;"><?php echo $fecha_actual_legible; ?></strong></span>
        </div>

        <form method="GET" action="turnos_recepcion.php" class="search-box">
            <input type="text" name="search_dni" placeholder="Buscar paciente por DNI, Apellido o Nombre..." value="<?php echo htmlspecialchars($filtro_dni); ?>" class="input-text">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
            <?php if ($filtro_dni != ''): ?>
                <a href="turnos_recepcion.php" class="btn btn-gray"><i class="fa-solid fa-eraser"></i> Limpiar</a>
            <?php endif; ?>
            <a href="planilla_medico_pdf.php?fecha=<?php echo $fecha_actual_sql; ?>" target="_blank" class="btn btn-success" style="margin-left:auto;"><i class="fa-solid fa-print"></i> Planilla Diaria PDF</a>
        </form>

        <?php if ($resultado && $resultado->num_rows > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Paciente / DNI</th>
                            <th>Servicio / Profesional</th>
                            <th>Prácticas / Motivo</th>
                            <th style="text-align: center;">Estado</th>
                            <th style="text-align: center;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $resultado->fetch_assoc()): 
                            $clase_estado = 'status-programado';
                            if($row['estado'] == 'Autorizado') $clase_estado = 'status-autorizado';
                            if($row['estado'] == 'Presente') $clase_estado = 'status-presente';
                            if($row['estado'] == 'Atendido') $clase_estado = 'status-atendido';
                        ?>
                        <tr>
                            <td><strong style="color:#144973; font-size:1.1rem;"><?php echo date('H:i', strtotime($row['hora_turno'])); ?></strong></td>
                            <td>
                                <strong style="color:#0f172a; font-size:1.05rem; display:block;"><?php echo htmlspecialchars($row['apellido'].', '.$row['nombre']); ?></strong>
                                <span style="color:#64748b; font-weight:600; font-size:0.85rem;"><i class="fa-regular fa-id-card"></i> <?php echo htmlspecialchars($row['dni']); ?></span>
                            </td>
                            <td>
                                <strong style="color:#334155; display:block;"><?php echo htmlspecialchars($row['servicio']); ?></strong>
                                <span style="color:#64748b; font-size:0.85rem;"><i class="fa-solid fa-user-doctor"></i> <?php echo htmlspecialchars($row['profesional']); ?></span>
                            </td>
                            <td>
                                <span style="font-size:0.85rem; color:#475569; font-weight:500; display:block; max-width: 250px;"><?php echo htmlspecialchars($row['practicas']); ?></span>
                            </td>
                            <td style="text-align: center;">
                                <span class="status <?php echo $clase_estado; ?>"><?php echo htmlspecialchars($row['estado']); ?></span>
                            </td>
                            <td style="text-align: center;">
                                <?php if($row['estado'] == 'Autorizado' || $row['estado'] == 'Pendiente'): ?>
                                    <a href="recepcion_procesar.php?id=<?php echo $row['turno_id']; ?>" class="btn btn-primary" style="padding:8px 12px; font-size:0.8rem;"><i class="fa-solid fa-check"></i> Dar Presente</a>
                                <?php else: ?>
                                    <span style="color:#94a3b8; font-size:0.8rem; font-weight:700;"><i class="fa-solid fa-lock"></i> Procesado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert-none">
                <i class="fa-solid fa-mug-hot" style="font-size: 3rem; margin-bottom: 15px; color: #cbd5e1; display: block;"></i>
                No se encontraron turnos para hoy bajo estos filtros.
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>