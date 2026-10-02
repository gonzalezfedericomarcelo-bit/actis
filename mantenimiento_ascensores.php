<?php
// Archivo: mantenimiento_ascensores.php
session_start();
require_once 'includes/conexion.php';

// REDIRECCIÓN DINÁMICA SIN HARDCODEAR
if (!isset($_SESSION['usuario_id']) || !in_array('modulo_totem_ascensores', isset($_SESSION['permisos']) ? $_SESSION['permisos'] : []) && !in_array('modulo_ascensores', isset($_SESSION['permisos']) ? $_SESSION['permisos'] : [])) {
    $ruta_redireccion = 'index.php'; 
    if (isset($_SESSION['rol'])) {
        try {
            $stmt_ruta = $pdo->prepare("SELECT ruta_dashboard FROM roles WHERE nombre_rol = :rol");
            $stmt_ruta->execute([':rol' => $_SESSION['rol']]);
            $rol_data = $stmt_ruta->fetch(PDO::FETCH_ASSOC);
            if ($rol_data && !empty($rol_data['ruta_dashboard'])) {
                $ruta_redireccion = $rol_data['ruta_dashboard'];
            }
        } catch (Exception $e) {}
    }
    header("Location: " . $ruta_redireccion);
    exit;
}

$sql = "SELECT a.*, 
        e.nombre as nombre_empresa,
        (SELECT COUNT(*) FROM ascensor_incidencias WHERE id_ascensor = a.id_ascensor AND estado != 'resuelto') as fallas_activas,
        (SELECT COUNT(*) FROM ascensor_incidencias WHERE id_ascensor = a.id_ascensor) as total_historico,
        (SELECT MAX(fecha_reporte) FROM ascensor_incidencias WHERE id_ascensor = a.id_ascensor) as ultima_falla,
        (SELECT titulo FROM ascensor_incidencias WHERE id_ascensor = a.id_ascensor ORDER BY fecha_reporte DESC LIMIT 1) as ultimo_motivo
        FROM ascensores a 
        LEFT JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa 
        ORDER BY a.nombre ASC";
$ascensores = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$total_equipos = count($ascensores);
$equipos_con_falla = 0;
$total_tickets = 0;

foreach ($ascensores as $a) { 
    if ($a['fallas_activas'] > 0) $equipos_con_falla++; 
    $total_tickets += $a['total_historico'];
}
$equipos_operativos = $total_equipos - $equipos_con_falla;
$eficiencia_global = $total_equipos > 0 ? round(($equipos_operativos / $total_equipos) * 100) : 0;
?>
<?php include 'includes/header.php'; ?>
<style>
    .ug-container { padding: 20px; max-width: 1400px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
    .ug-panel { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .ug-header { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
    .ug-header h2 { margin: 0; color: #1e293b; font-weight: 700; font-size: 1.5rem; display: flex; align-items: center; gap: 10px; }
    .ug-btn-primary { background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
    .ug-btn-primary:hover { background: #2563eb; color: white; }
    .ug-btn-danger { background: #ef4444; color: white; }
    .ug-btn-danger:hover { background: #dc2626; color: white; }
    
    .ug-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
    .ug-stat-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; display: flex; align-items: center; gap: 15px; }
    .ug-stat-icon { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .ug-stat-val { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0; line-height: 1; }
    .ug-stat-lbl { font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase; margin: 5px 0 0 0; }
    
    .ug-list-item { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin-bottom: 10px; display: flex; flex-direction: column; gap: 15px; transition: 0.2s; }
    .ug-list-item:hover { border-color: #cbd5e1; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
    .ug-item-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
    .ug-item-title { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 8px; }
    .ug-item-subtitle { font-size: 0.85rem; color: #64748b; margin: 0; }
    .ug-badge { padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; display: inline-block; }
    .ug-badge-success { background: #dcfce7; color: #166534; }
    .ug-badge-danger { background: #fee2e2; color: #991b1b; }
    
    .ug-item-body { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; font-size: 0.85rem; color: #475569; }
    .ug-item-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px; }
    .ug-btn-action { padding: 8px 12px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; border: none; cursor: pointer; transition: 0.2s; }
    .ug-btn-report { background: #fee2e2; color: #dc2626; }
    .ug-btn-report:hover { background: #fca5a5; }
    .ug-btn-history { background: #e0e7ff; color: #4338ca; }
    .ug-btn-history:hover { background: #c7d2fe; }

    .ug-feed-item { padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
    .ug-feed-item:last-child { border-bottom: none; }
    .ug-feed-time { font-size: 0.75rem; color: #64748b; font-weight: 600; }
    .ug-feed-text { font-size: 0.85rem; color: #1e293b; margin: 3px 0 0 0; }
    
    @media (min-width: 992px) {
        .ug-grid { display: grid; grid-template-columns: 1fr 300px; gap: 20px; align-items: start; }
        .ug-list-item { flex-direction: row; align-items: center; justify-content: space-between; }
        .ug-item-header { border-bottom: none; padding-bottom: 0; flex-direction: column; align-items: flex-start; width: 200px; }
        .ug-item-body { display: flex; flex: 1; justify-content: space-around; }
        .ug-item-actions { margin-top: 0; }
    }

    /* Modal Styles */
    .ug-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.8); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
    .ug-modal-box { background: #fff; width: 100%; max-width: 500px; border-radius: 16px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); animation: modalPop 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
    @keyframes modalPop { 0% { opacity: 0; transform: scale(0.95) translateY(20px); } 100% { opacity: 1; transform: scale(1) translateY(0); } }
    .ug-modal-header { padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: flex-start; background: #f8fafc; }
    .ug-modal-title { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; }
    .ug-modal-subtitle { font-size: 0.85rem; color: #64748b; margin: 5px 0 0 0; }
    .ug-modal-close { background: none; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer; line-height: 1; padding: 0; }
    
    /* Form Styles */
    .ug-form-group { margin-bottom: 16px; }
    .ug-form-label { display: block; font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 6px; }
    .ug-form-control { width: 100%; padding: 12px; font-size: 0.95rem; color: #1e293b; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; background: #fff; font-family: inherit; transition: border-color 0.2s; }
    .ug-form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
</style>

<div class="ug-container">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div>
            <h1 style="margin:0; font-size:1.8rem; color:#0f172a; font-weight:800;">Operaciones</h1>
            <p style="margin:0; color:#64748b; font-size:0.95rem;">Monitoreo logístico y control de unidades en tiempo real</p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="admin_ascensores.php" class="ug-btn-primary" style="background:#f1f5f9; color:#475569;"><i class="fas fa-cog"></i> Configuración</a>
        </div>
    </div>

    <div class="ug-stats-grid">
        <div class="ug-stat-card">
            <div class="ug-stat-icon" style="background:#e0e7ff; color:#4f46e5;"><i class="fas fa-building"></i></div>
            <div>
                <p class="ug-stat-val"><?php echo $total_equipos; ?></p>
                <p class="ug-stat-lbl">Unidades Totales</p>
            </div>
        </div>
        <div class="ug-stat-card">
            <div class="ug-stat-icon" style="background:#dcfce7; color:#10b981;"><i class="fas fa-check-circle"></i></div>
            <div>
                <p class="ug-stat-val"><?php echo $equipos_operativos; ?></p>
                <p class="ug-stat-lbl">En Servicio</p>
            </div>
        </div>
        <div class="ug-stat-card">
            <div class="ug-stat-icon" style="background:#fee2e2; color:#ef4444;"><i class="fas fa-tools"></i></div>
            <div>
                <p class="ug-stat-val"><?php echo $equipos_con_falla; ?></p>
                <p class="ug-stat-lbl">Con Fallas</p>
            </div>
        </div>
        <div class="ug-stat-card">
            <div class="ug-stat-icon" style="background:#fef3c7; color:#f59e0b;"><i class="fas fa-chart-line"></i></div>
            <div>
                <p class="ug-stat-val"><?php echo $eficiencia_global; ?>%</p>
                <p class="ug-stat-lbl">Eficiencia Operativa</p>
            </div>
        </div>
    </div>

    <div class="ug-grid">
        <div class="ug-panel">
            <div class="ug-header">
                <h2><i class="fas fa-elevator text-primary"></i> Matriz de Unidades</h2>
            </div>
            
            <div class="ug-list">
                <?php foreach ($ascensores as $a): ?>
                    <?php 
                    $en_falla = $a['fallas_activas'] > 0;
                    $badge_class = $en_falla ? 'ug-badge-danger' : 'ug-badge-success';
                    $txt_salud = $en_falla ? 'REVISIÓN REQUERIDA' : 'ÓPTIMO';
                    $icon_color = $en_falla ? 'text-danger' : 'text-success';
                    ?>
                    <div class="ug-list-item">
                        <div class="ug-item-header">
                            <h3 class="ug-item-title"><i class="fas fa-elevator <?php echo $icon_color; ?>"></i> <?php echo htmlspecialchars($a['nombre']); ?></h3>
                            <p class="ug-item-subtitle"><i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($a['ubicacion']); ?></p>
                        </div>
                        
                        <div class="ug-item-body">
                            <div>
                                <span style="display:block; font-size:0.75rem; font-weight:700; color:#94a3b8; text-transform:uppercase;">Estado</span>
                                <span class="ug-badge <?php echo $badge_class; ?>"><?php echo $txt_salud; ?></span>
                            </div>
                            <div>
                                <span style="display:block; font-size:0.75rem; font-weight:700; color:#94a3b8; text-transform:uppercase;">Empresa Asignada</span>
                                <strong><?php echo htmlspecialchars($a['nombre_empresa'] ?: 'Sin Asignar'); ?></strong>
                            </div>
                        </div>
                        
                        <div class="ug-item-actions">
                            <button class="ug-btn-action ug-btn-report" onclick="abrirModalReporte(<?php echo $a['id_ascensor']; ?>, '<?php echo addslashes($a['nombre']); ?>')"><i class="fas fa-exclamation-triangle"></i> Reportar</button>
                            <a href="ascensor_detalle.php?id=<?php echo $a['id_ascensor']; ?>" class="ug-btn-action ug-btn-history"><i class="fas fa-folder-open"></i> Historial</a>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($ascensores)): ?>
                    <p style="text-align:center; padding:20px; color:#64748b;">No hay unidades registradas en el sistema.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="ug-panel">
            <div class="ug-header">
                <h2><i class="fas fa-bell text-primary"></i> Actividad Reciente</h2>
            </div>
            <div>
                <?php 
                $eventos = 0;
                foreach ($ascensores as $a) {
                    if ($a['ultima_falla']) {
                        echo '<div class="ug-feed-item">';
                        echo '<div class="ug-feed-time"><i class="far fa-clock"></i> ' . date('d/m/Y H:i', strtotime($a['ultima_falla'])) . '</div>';
                        echo '<p class="ug-feed-text">Novedad en <strong>' . htmlspecialchars($a['nombre']) . '</strong>: ' . htmlspecialchars($a['ultimo_motivo'] ?? 'Ticket Generado') . '</p>';
                        echo '</div>';
                        $eventos++;
                    }
                }
                if ($eventos == 0) { echo '<div style="text-align:center; padding:20px; color:#94a3b8;"><i class="fas fa-check-circle fa-2x mb-2" style="color:#dcfce7;"></i><br>No hay eventos recientes.</div>'; }
                ?>
            </div>
        </div>
    </div>
</div>

<div class="ug-modal-overlay" id="modalReporte">
    <div class="ug-modal-box">
        <div class="ug-modal-header">
            <div>
                <h5 class="ug-modal-title"><i class="fas fa-bolt text-danger"></i> Emitir Orden de Servicio</h5>
                <p id="modal_nombre_ascensor" class="ug-modal-subtitle">Unidad</p>
            </div>
            <button type="button" class="ug-modal-close" onclick="cerrarModalReporte()">&times;</button>
        </div>
        <form id="formReporteIncidencia" action="ascensor_crear_incidencia.php" method="POST">
            <div style="padding: 20px;">
                <input type="hidden" name="id_ascensor" id="modal_id_ascensor">
                
                <div class="ug-form-group">
                    <label class="ug-form-label">Motivo Principal</label>
                    <input type="text" name="titulo" id="fInput" class="ug-form-control" required placeholder="Ej: Falla en display interno">
                </div>
                
                <div class="ug-form-group">
                    <label class="ug-form-label">Observaciones / Detalles</label>
                    <textarea name="descripcion" id="fText" class="ug-form-control" rows="3" required placeholder="Pisos afectados, ruidos, comportamiento..."></textarea>
                </div>
                
                <div class="ug-form-group" style="margin-bottom: 0;">
                    <label class="ug-form-label">Clasificación de Novedad</label>
                    <select name="prioridad" class="ug-form-control">
                        <option value="baja">🔵 Mantenimiento Preventivo</option>
                        <option value="media" selected>🟡 Falla Menor (Operativo)</option>
                        <option value="alta">🟠 Falla Grave (Fuera de servicio)</option>
                        <option value="critica">🔴 EMERGENCIA (Riesgo / Atrapamiento)</option>
                    </select>
                </div>
            </div>
            <div style="border-top: 1px solid #e2e8f0; padding: 20px; display: flex; justify-content: flex-end; gap: 10px; background: #f8fafc;">
                <button type="button" class="ug-btn-primary" style="background:#cbd5e1; color:#334155; border:none;" onclick="cerrarModalReporte()">Cancelar</button>
                <button type="submit" class="ug-btn-danger" style="border:none;"><i class="fas fa-paper-plane me-2"></i> Generar Orden</button>
            </div>
        </form>
    </div>
</div>

<script>
    <?php if(isset($_SESSION['mensaje'])): ?>
    Swal.fire({
        icon: '<?php echo $_SESSION['tipo_mensaje'] ?? "info"; ?>',
        title: 'Atención',
        html: `<?php echo $_SESSION['mensaje']; ?>`,
        confirmButtonColor: '#3b82f6'
    });
    <?php unset($_SESSION['mensaje']); unset($_SESSION['tipo_mensaje']); ?>
    <?php endif; ?>

    function abrirModalReporte(id_ascensor, nombre_ascensor) {
        document.getElementById('modal_id_ascensor').value = id_ascensor;
        document.getElementById('modal_nombre_ascensor').innerText = nombre_ascensor;
        document.getElementById('fInput').value = '';
        document.getElementById('fText').value = '';
        document.getElementById('modalReporte').style.display = 'flex';
    }

    function cerrarModalReporte() {
        document.getElementById('modalReporte').style.display = 'none';
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'includes/footer.php'; ?>
