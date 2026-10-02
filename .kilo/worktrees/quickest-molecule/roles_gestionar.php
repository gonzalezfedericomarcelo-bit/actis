<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';

// Verificación de seguridad (Bloqueo por URL)
if (!isset($mis_permisos) || !in_array('modulo_roles', $mis_permisos)) { 
    echo "<div style='padding:50px 20px; text-align:center; color:#ef4444; background:#ffffff; border-radius:20px; border: 1px solid #e2e8f0; box-shadow:0 4px 6px -1px rgba(0,0,0,0.03); max-width: 600px; margin: 40px auto;'>
            <i class='fa-solid fa-shield-blank' style='font-size:4rem; margin-bottom:15px; color:#cbd5e1;'></i>
            <h2 style='margin:0; font-weight:900; font-size:1.6rem; color:#0f172a;'>Acceso Bloqueado</h2>
            <p style='color:#64748b; font-size:1rem; margin-top:10px; font-weight: 500;'>Tu rol actual no tiene privilegios para modificar la matriz de permisos.</p>
          </div>";
    require_once 'includes/footer.php';
    exit; 
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['crear_rol'])) {
    $nuevo_rol = $conexion->real_escape_string(trim($_POST['nombre_rol']));
    if (!empty($nuevo_rol)) {
        $conexion->query("INSERT INTO roles (nombre) VALUES ('$nuevo_rol')");
        echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarExito('Rol creado exitosamente'); });</script>";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guardar_permisos'])) {
    $rid = $conexion->real_escape_string($_POST['rol_id']);
    $conexion->query("DELETE FROM rol_permiso WHERE rol_id = $rid");
    if (isset($_POST['permisos']) && is_array($_POST['permisos'])) {
        foreach ($_POST['permisos'] as $pid) { 
            $conexion->query("INSERT INTO rol_permiso (rol_id, permiso_id) VALUES ($rid, ".$conexion->real_escape_string($pid).")"); 
        }
    }
    echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarExito('Permisos actualizados correctamente'); });</script>";
}

// Obtener todos los permisos y categorizarlos
$res_permisos = $conexion->query("SELECT * FROM permisos ORDER BY nombre_permiso ASC");
$lista_p = [];
while($p = $res_permisos->fetch_assoc()) {
    $lista_p[] = $p;
}

// Definición de las categorías visuales
$categorias_ui = [
    'Atención y Área Médica' => ['modulo_turnos', 'modulo_medico', 'modulo_medico_planilla', 'modulo_planilla_general', 'modulo_pacientes'],
    'Recepción y Salidas' => ['modulo_recepcion'],
    'Control de Seguridad' => ['modulo_seguridad', 'modulo_seguridad_admin'],
    'Gestión, Reportes y Tótem' => ['modulo_reportes', 'modulo_validador'],
    'Administración del Tótem' => ['modulo_totem_admin', 'modulo_totem_ascensores', 'modulo_totem_pantalla', 'modulo_totem_descargas', 'modulo_totem_estadisticas', 'modulo_totem_papel', 'modulo_totem_estado', 'modulo_totem_diseno', 'modulo_totem_seguridad'],
    'Administración del Sistema' => ['modulo_usuarios', 'modulo_roles']
];

// Agrupar los permisos dinámicamente según la categoría
$permisos_agrupados = [];
foreach ($lista_p as $p) {
    $asignado = false;
    foreach ($categorias_ui as $cat_nombre => $modulos) {
        if (in_array($p['nombre_permiso'], $modulos)) {
            $permisos_agrupados[$cat_nombre][] = $p;
            $asignado = true;
            break;
        }
    }
    if (!$asignado) {
        $permisos_agrupados['Otros Módulos'][] = $p;
    }
}
?>

<style>
    /* Estructura Base 1400px / Flat */
    .rol-container { max-width: 1400px; margin: 20px auto; padding: 0 15px; font-family: 'Poppins', sans-serif; display: flex; flex-direction: column; gap: 20px; }
    
    .rol-panel-top { background: #ffffff; border-radius: 20px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); display: flex; flex-direction: column; gap: 20px; }
    .rol-header { font-size: 1.6rem; font-weight: 900; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; }
    
    .new-rol-form { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; background: #f8fafc; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; }
    .new-rol-label { font-weight: 900; color: #0f172a; font-size: 1.1rem; display: flex; align-items: center; gap: 8px; }
    .new-rol-input { flex: 1; min-width: 250px; padding: 14px 16px; border: 2px solid #cbd5e1; border-radius: 12px; font-size: 1rem; font-weight: 600; outline: none; transition: border-color 0.2s; font-family: 'Poppins', sans-serif; color: #1e293b; background: white; }
    .new-rol-input:focus { border-color: #144973; }
    .btn-create { background: #10b981; color: white; border: none; padding: 14px 25px; border-radius: 12px; font-weight: 800; cursor: pointer; transition: transform 0.2s; font-size: 1rem; display: flex; align-items: center; gap: 8px; white-space: nowrap; text-transform: uppercase; }
    .btn-create:active { transform: scale(0.98); }

    .roles-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 20px; }
    
    .matriz-card { background: #ffffff; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; display: flex; flex-direction: column; overflow: hidden; transition: transform 0.2s, border-color 0.2s; }
    .matriz-card:hover { transform: translateY(-3px); border-color: #cbd5e1; }
    
    .matriz-header { background: #f8fafc; padding: 20px 25px; border-bottom: 2px solid #e2e8f0; font-size: 1.3rem; font-weight: 900; color: #0f172a; display: flex; align-items: center; gap: 10px; }
    
    .matriz-body { padding: 25px; flex: 1; display: flex; flex-direction: column; gap: 25px; }
    
    .cat-section { display: flex; flex-direction: column; gap: 10px; }
    .cat-title { font-size: 0.8rem; font-weight: 900; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px dotted #e2e8f0; padding-bottom: 6px; margin-bottom: 4px; }
    
    .permiso-item { display: flex; align-items: flex-start; gap: 12px; background: #f8fafc; padding: 14px; border-radius: 12px; cursor: pointer; border: 2px solid transparent; transition: all 0.2s; margin: 0; }
    .permiso-item:hover { background: #ffffff; border-color: #cbd5e1; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
    /* Se aplica un check azul cuando está seleccionado para resaltar */
    .permiso-item:has(input:checked) { background: #f0f9ff; border-color: #bfdbfe; }
    
    .permiso-checkbox { width: 20px; height: 20px; cursor: pointer; margin-top: 2px; accent-color: #144973; flex-shrink: 0; }
    .permiso-text { display: flex; flex-direction: column; }
    .permiso-name { font-weight: 800; font-size: 0.9rem; color: #1e293b; }
    .permiso-desc { font-size: 0.8rem; color: #64748b; line-height: 1.4; margin-top: 2px; font-weight: 500; }
    
    .matriz-footer { padding: 20px 25px; background: #f8fafc; border-top: 1px solid #e2e8f0; }
    .btn-update { width: 100%; background: #144973; color: white; border: none; padding: 14px; border-radius: 12px; font-weight: 800; cursor: pointer; transition: transform 0.2s; font-size: 1rem; display: flex; justify-content: center; align-items: center; gap: 8px; text-transform: uppercase; }
    .btn-update:active { transform: scale(0.98); }

    @media (max-width: 768px) {
        .roles-grid { grid-template-columns: 1fr; }
        .new-rol-form { flex-direction: column; align-items: stretch; }
        .btn-create { justify-content: center; }
    }
</style>

<div class="rol-container">
    <div class="rol-panel-top">
        <h2 class="rol-header"><i class="fa-solid fa-key" style="color: #144973;"></i> Matriz de Permisos y Roles</h2>
        
        <form method="POST" action="" class="new-rol-form">
            <div class="new-rol-label"><i class="fa-solid fa-plus-circle" style="color: #10b981;"></i> Nuevo Rol:</div>
            <input type="text" name="nombre_rol" class="new-rol-input" placeholder="Ej: Médico Pediatra, Recepcionista Noche..." required autocomplete="off">
            <button type="submit" name="crear_rol" class="btn-create"><i class="fa-solid fa-floppy-disk"></i> Crear Rol</button>
        </form>
    </div>

    <div class="roles-grid">
        <?php 
        $res_roles = $conexion->query("SELECT * FROM roles ORDER BY id ASC");
        while($rol = $res_roles->fetch_assoc()):
            $act = []; 
            $ra = $conexion->query("SELECT permiso_id FROM rol_permiso WHERE rol_id = " . $rol['id']); 
            while($a = $ra->fetch_assoc()) $act[] = $a['permiso_id'];
        ?>
        <form method="POST" action="" class="matriz-card">
            <input type="hidden" name="rol_id" value="<?php echo $rol['id']; ?>">
            
            <div class="matriz-header">
                <i class="fa-solid fa-shield-halved" style="color: #144973;"></i> <?php echo htmlspecialchars($rol['nombre']); ?>
            </div>
            
            <div class="matriz-body">
                <?php foreach ($categorias_ui as $cat_nombre => $modulos): ?>
                    <?php if(isset($permisos_agrupados[$cat_nombre])): ?>
                        <div class="cat-section">
                            <div class="cat-title"><?php echo $cat_nombre; ?></div>
                            <?php foreach ($permisos_agrupados[$cat_nombre] as $p): ?>
                                <?php $chk = in_array($p['id'], $act) ? "checked" : ""; ?>
                                <label class="permiso-item">
                                    <input type="checkbox" name="permisos[]" value="<?php echo $p['id']; ?>" <?php echo $chk; ?> class="permiso-checkbox">
                                    <div class="permiso-text">
                                        <span class="permiso-name"><?php echo htmlspecialchars($p['nombre_permiso']); ?></span>
                                        <span class="permiso-desc"><?php echo htmlspecialchars($p['descripcion']); ?></span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if(isset($permisos_agrupados['Otros Módulos'])): ?>
                    <div class="cat-section">
                        <div class="cat-title">Otros Módulos</div>
                        <?php foreach ($permisos_agrupados['Otros Módulos'] as $p): ?>
                            <?php $chk = in_array($p['id'], $act) ? "checked" : ""; ?>
                            <label class="permiso-item">
                                <input type="checkbox" name="permisos[]" value="<?php echo $p['id']; ?>" <?php echo $chk; ?> class="permiso-checkbox">
                                <div class="permiso-text">
                                    <span class="permiso-name"><?php echo htmlspecialchars($p['nombre_permiso']); ?></span>
                                    <span class="permiso-desc"><?php echo htmlspecialchars($p['descripcion']); ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="matriz-footer">
                <button type="submit" name="guardar_permisos" class="btn-update">
                    <i class="fa-solid fa-lock"></i> Guardar Configuración
                </button>
            </div>
        </form>
        <?php endwhile; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
