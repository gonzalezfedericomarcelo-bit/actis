<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';

// Verificamos el mismo permiso que usabas para ver la planilla general
if(!isset($mis_permisos) || !in_array('modulo_planilla_general', $mis_permisos)) {
    echo "<div style='text-align:center; padding:50px; color:#ef4444;'><h2><i class='fa-solid fa-shield-blank'></i> Acceso Denegado</h2></div>";
    require_once 'includes/footer.php';
    exit;
}

$fecha = isset($_GET['fecha']) ? $conexion->real_escape_string($_GET['fecha']) : date('Y-m-d');
$medico_id = isset($_GET['medico_id']) ? (int)$_GET['medico_id'] : 0;
$busqueda = isset($_GET['q']) ? $conexion->real_escape_string(trim($_GET['q'])) : '';

// Construir SQL de Búsqueda
$sql = "SELECT t.*, p.nombre, p.apellido, p.dni 
        FROM turnos t 
        INNER JOIN pacientes p ON t.paciente_id = p.id 
        WHERE (t.estado = 'Presente' OR t.estado = 'Atendido')";

if (!empty($fecha) && $fecha !== 'todas') {
    $sql .= " AND t.fecha_turno = '$fecha'";
}
if ($medico_id > 0) {
    $sql .= " AND (t.usuario_medico_id = $medico_id OR t.usuario_creador_id = $medico_id)";
}
if (!empty($busqueda)) {
    $sql .= " AND (p.dni LIKE '%$busqueda%' OR p.nombre LIKE '%$busqueda%' OR p.apellido LIKE '%$busqueda%')";
}
$sql .= " ORDER BY t.fecha_turno DESC, t.hora_turno ASC";

$res = $conexion->query($sql);
?>
<style>
    .pb-container { max-width: 1400px; margin: 20px auto; padding: 0 15px; font-family: 'Poppins', sans-serif; }
    .pb-panel { background: #ffffff; border-radius: 20px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); margin-bottom: 25px; }
    .pb-header { font-size: 1.6rem; font-weight: 900; margin-bottom: 20px; color: #0f172a; display: flex; align-items: center; gap: 10px; margin-top: 0; justify-content: space-between; flex-wrap: wrap; }
    
    .pb-filters { display: flex; gap: 15px; flex-wrap: wrap; background: #f8fafc; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; align-items: flex-end; }
    .pb-group { flex: 1; min-width: 200px; display: flex; flex-direction: column; gap: 6px; }
    .pb-label { font-size: 0.8rem; font-weight: 800; color: #64748b; text-transform: uppercase; }
    .pb-input { width: 100%; padding: 12px 15px; border-radius: 10px; border: 2px solid #cbd5e1; font-weight: 600; font-family: 'Poppins', sans-serif; font-size: 0.95rem; outline: none; transition: border 0.2s; box-sizing: border-box; background: white; }
    .pb-input:focus { border-color: #144973; }
    
    .pb-btn { padding: 12px 20px; border: none; border-radius: 10px; font-weight: 800; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: transform 0.2s; text-decoration: none; color: white; white-space: nowrap; }
    .pb-btn:active { transform: scale(0.95); }
    .pb-btn-primary { background: #144973; }
    .pb-btn-danger { background: #ef4444; }
    
    .pb-table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid #e2e8f0; }
    .pb-table { width: 100%; border-collapse: collapse; min-width: 900px; background: white; }
    .pb-table th { padding: 15px; text-align: left; color: #475569; background: #f8fafc; border-bottom: 2px solid #e2e8f0; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; }
    .pb-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .pb-table tr:hover { background: #f8fafc; }
    
    .pb-estado { padding: 6px 12px; border-radius: 20px; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; white-space: nowrap; border: 1px solid transparent; display: inline-block; }
    .pb-empty { padding: 50px 20px; text-align: center; color: #64748b; background: #f8fafc; border-radius: 16px; border: 2px dashed #cbd5e1; font-weight: 600; }
</style>

<div class="pb-container">
    <div class="pb-panel">
        <div class="pb-header">
            <div>
                <i class="fa-solid fa-magnifying-glass-chart" style="color: #144973;"></i> Buscador Histórico de Planillas
            </div>
            <a href="planilla_medico_pdf.php?fecha=<?php echo $fecha; ?>&medico_id=<?php echo $medico_id; ?>&q=<?php echo urlencode($busqueda); ?>" target="_blank" class="pb-btn pb-btn-danger">
                <i class="fa-solid fa-file-pdf"></i> Exportar Filtro a PDF
            </a>
        </div>

        <form method="GET" action="planillas_buscador.php" class="pb-filters">
            <div class="pb-group">
                <label class="pb-label">Fecha de Atención</label>
                <select name="fecha" class="pb-input">
                    <option value="<?php echo date('Y-m-d'); ?>" <?php if($fecha == date('Y-m-d')) echo 'selected'; ?>>Día de Hoy</option>
                    <option value="todas" <?php if($fecha == 'todas') echo 'selected'; ?>>Todo el Historial</option>
                    <?php if($fecha != date('Y-m-d') && $fecha != 'todas'): ?>
                        <option value="<?php echo $fecha; ?>" selected>Fecha Exacta: <?php echo date('d/m/Y', strtotime($fecha)); ?></option>
                    <?php endif; ?>
                </select>
                <?php if($fecha == 'todas'): ?>
                    <input type="date" name="fecha_custom" class="pb-input" style="margin-top: 5px;" onchange="this.form.fecha.options.add(new Option(this.value, this.value, true, true)); this.form.submit();">
                <?php endif; ?>
            </div>

            <div class="pb-group">
                <label class="pb-label">Profesional</label>
                <select name="medico_id" class="pb-input">
                    <option value="0">Todos los Profesionales</option>
                    <?php 
                    $q_med = $conexion->query("SELECT id, nombre_completo FROM usuarios WHERE estado = 1 AND es_medico = 1 ORDER BY nombre_completo ASC");
                    while($m = $q_med->fetch_assoc()):
                    ?>
                        <option value="<?php echo $m['id']; ?>" <?php if($medico_id == $m['id']) echo 'selected'; ?>><?php echo htmlspecialchars($m['nombre_completo']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="pb-group">
                <label class="pb-label">Paciente o DNI</label>
                <input type="text" name="q" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Escribir DNI o Nombre..." class="pb-input">
            </div>

            <button type="submit" class="pb-btn pb-btn-primary"><i class="fa-solid fa-filter"></i> Aplicar Filtros</button>
        </form>

        <?php if ($res && $res->num_rows > 0): ?>
            <div class="pb-table-wrap">
                <table class="pb-table">
                    <thead>
                        <tr>
                            <th>Fecha / Hora</th>
                            <th>Paciente</th>
                            <th style="text-align: center;">DNI</th>
                            <th>Profesional</th>
                            <th style="text-align: center;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $res->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong style="color: #144973; font-size:1.05rem;"><?php echo date('d/m/Y', strtotime($row['fecha_turno'])); ?></strong> <br>
                                <span style="color: #64748b; font-size: 0.85rem; font-weight:600;"><i class="fa-regular fa-clock"></i> <?php echo date('H:i', strtotime($row['hora_turno'])); ?> hs</span>
                            </td>
                            <td>
                                <strong style="color: #0f172a; font-size: 1.05rem;"><?php echo mb_strtoupper($row['apellido'], 'UTF-8') . ', ' . htmlspecialchars($row['nombre']); ?></strong>
                            </td>
                            <td style="text-align: center; color: #475569; font-weight: 600;">
                                <i class="fa-regular fa-id-card"></i> <?php echo htmlspecialchars($row['dni']); ?>
                            </td>
                            <td>
                                <strong style="color: #334155;"><i class="fa-solid fa-user-doctor"></i> <?php echo mb_strtoupper($row['profesional'], 'UTF-8'); ?></strong>
                            </td>
                            <td style="text-align: center;">
                                <?php if($row['estado'] == 'Atendido'): ?>
                                    <span class="pb-estado" style="background: #dcfce7; color: #166534; border-color: #bbf7d0;"><i class="fa-solid fa-check"></i> Atendido</span>
                                <?php else: ?>
                                    <span class="pb-estado" style="background: #fef3c7; color: #92400e; border-color: #fde68a;"><i class="fa-solid fa-clock"></i> Presente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="pb-empty">
                <i class="fa-solid fa-magnifying-glass" style="font-size: 3.5rem; margin-bottom: 15px; color: #cbd5e1; display: block;"></i>
                <h3 style="margin: 0; color: #1e293b; font-weight: 800;">No se encontraron planillas</h3>
                <p>No hay pacientes registrados con los filtros seleccionados.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>