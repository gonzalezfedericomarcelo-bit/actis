<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';

if(!in_array('modulo_usuarios', isset($_SESSION['permisos']) ? $_SESSION['permisos'] : [])) {
    echo "<script>mostrarError('No tienes permisos.'); setTimeout(()=>window.location='dashboard.php',2000);</script>"; 
    require_once 'includes/footer.php'; 
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_medico'])) {
    $id_u = (int)$_POST['id_usuario_medico'];
    $estado_medico = (int)$_POST['estado_medico'];
    $conexion->query("UPDATE usuarios SET es_medico = $estado_medico WHERE id = $id_u");
    echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarExito('Estado de profesional actualizado correctamente'); });</script>";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['crear_usuario'])) {
    $nombre = $conexion->real_escape_string($_POST['nombre_completo']);
    $user = $conexion->real_escape_string($_POST['usuario']);
    $pass = $conexion->real_escape_string($_POST['password']);
    $pin_totem = !empty($_POST['pin_totem']) ? "'" . $conexion->real_escape_string($_POST['pin_totem']) . "'" : "NULL";
    $rol_id = (int)$_POST['rol_id'];
    
    if($conexion->query("SELECT id FROM usuarios WHERE usuario = '$user'")->num_rows > 0) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarError('El usuario ya existe'); });</script>";
    } else {
        if($conexion->query("INSERT INTO usuarios (nombre_completo, usuario, password, rol_id, estado, pin_totem) VALUES ('$nombre', '$user', '$pass', $rol_id, 1, $pin_totem)") === TRUE) {
            echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarExito('Usuario creado exitosamente'); });</script>";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['resetear_clave'])) {
    $id_reset = (int)$_POST['id_usuario_reset'];
    $nueva_clave = $conexion->real_escape_string($_POST['nueva_clave']);
    if (!empty($nueva_clave)) {
        $conexion->query("UPDATE usuarios SET password = '$nueva_clave' WHERE id = $id_reset");
        echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarExito('Contraseña actualizada correctamente'); });</script>";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['resetear_pin'])) {
    $id_pin = (int)$_POST['id_usuario_pin'];
    $nuevo_pin = $conexion->real_escape_string($_POST['nuevo_pin']);
    $val_pin = !empty($nuevo_pin) ? "'$nuevo_pin'" : "NULL";
    $conexion->query("UPDATE usuarios SET pin_totem = $val_pin WHERE id = $id_pin");
    echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarExito('PIN de Tótem actualizado'); });</script>";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cambiar_rol'])) {
    $id_user_rol = (int)$_POST['id_usuario_rol'];
    $nuevo_rol_id = (int)$_POST['nuevo_rol_id'];
    $conexion->query("UPDATE usuarios SET rol_id = $nuevo_rol_id WHERE id = $id_user_rol");
    echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarExito('Rol de usuario actualizado'); });</script>";
}
?>

<style>
    /* Estructura Base 1400px / Flat */
    .ug-container { max-width: 1400px; margin: 20px auto; padding: 0 15px; font-family: 'Poppins', sans-serif; display: flex; flex-direction: column; gap: 20px; }
    
    .ug-panel { background: #ffffff; border-radius: 20px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); display: flex; flex-direction: column; }
    .ug-header { margin-top: 0; color: #0f172a; font-weight: 900; font-size: 1.5rem; display: flex; align-items: center; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px;}
    .ug-header i { color: #144973; }
    
    .ug-input { width: 100%; padding: 12px 15px; border: 2px solid #cbd5e1; border-radius: 10px; font-size: 0.95rem; background: #ffffff; outline: none; transition: all 0.2s; font-weight: 600; font-family: 'Poppins', sans-serif; box-sizing: border-box; color: #0f172a; }
    .ug-input:focus { border-color: #144973; }
    
    .ug-grid { display: grid; grid-template-columns: 1fr; gap: 15px; margin-bottom: 20px; }
    @media(min-width: 768px){ .ug-grid { grid-template-columns: repeat(2, 1fr); } }
    @media(min-width: 1100px){ .ug-grid { grid-template-columns: repeat(4, 1fr); } }
    
    .ug-group { display: flex; flex-direction: column; gap: 6px; }
    .ug-label { font-size: 0.8rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }

    .ug-btn-full { width: 100%; padding: 14px; border-radius: 10px; font-weight: 800; font-size: 0.95rem; border: none; cursor: pointer; color: white; display: flex; justify-content: center; align-items: center; gap: 8px; transition: transform 0.2s; text-decoration: none; }
    .ug-btn-full:active { transform: scale(0.98); }
    .bg-primary { background: #144973; }
    
    /* Lista de Usuarios */
    .user-list { display: grid; grid-template-columns: 1fr; gap: 15px; }
    @media(min-width: 992px){ .user-list { grid-template-columns: repeat(2, 1fr); } }
    
    .user-card { display: flex; flex-direction: column; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; position: relative; transition: transform 0.2s, border-color 0.2s; }
    .user-card:hover { transform: translateY(-2px); border-color: #cbd5e1; }
    
    .user-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; }
    .user-name { font-weight: 900; color: #1e293b; font-size: 1.15rem; }
    
    .user-badge { padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; border: 1px solid transparent; }
    .badge-active { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
    .badge-inactive { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
    
    .user-detail { display: flex; justify-content: space-between; font-size: 0.9rem; color: #475569; margin-bottom: 8px; }
    .user-detail span { font-weight: 700; color: #0f172a; background: #ffffff; padding: 2px 8px; border-radius: 6px; border: 1px solid #e2e8f0; }

    .action-row { margin-top: auto; padding-top: 15px; border-top: 1px dashed #cbd5e1; display: grid; grid-template-columns: 1fr; gap: 10px; }
    @media(min-width: 1200px){ .action-row { grid-template-columns: 1fr 1fr; } }
    
    .action-form { display: flex; gap: 8px; align-items: center; width: 100%; }
    .btn-action-small { border: none; padding: 10px; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 0.85rem; display: flex; justify-content: center; align-items: center; gap: 6px; color: white; transition: transform 0.2s; white-space: nowrap; flex-shrink: 0; }
    .btn-action-small:active { transform: scale(0.95); }
    .bg-role { background: #144973; }
    .bg-pass { background: #f59e0b; }
    .bg-med-yes { background: #10b981; }
    .bg-med-no { background: #64748b; }
</style>

<div class="ug-container">
    <div class="ug-panel">
        <h2 class="ug-header"><i class="fa-solid fa-user-shield"></i> Registrar Nuevo Usuario</h2>
        <form method="POST" action="">
            <div class="ug-grid">
                <div class="ug-group">
                    <label class="ug-label">Nombre Completo</label>
                    <input type="text" name="nombre_completo" required class="ug-input" autocomplete="off">
                </div>
                <div class="ug-group">
                    <label class="ug-label">Login ID (Usuario)</label>
                    <input type="text" name="usuario" required class="ug-input" autocomplete="off">
                </div>
                <div class="ug-group">
                    <label class="ug-label">Clave de Acceso</label>
                    <input type="text" name="password" required class="ug-input">
                </div>
                <div class="ug-group">
                    <label class="ug-label">PIN del Tótem (Opcional)</label>
                    <input type="text" name="pin_totem" class="ug-input" placeholder="Ej: 1234">
                </div>
                <div class="ug-group">
                    <label class="ug-label">Asignar Rol Inicial</label>
                    <select name="rol_id" required class="ug-input" style="cursor: pointer; appearance: none;">
                        <option value="">Seleccione un Rol...</option>
                        <?php 
                        $rr = $conexion->query("SELECT * FROM roles"); 
                        while($r = $rr->fetch_assoc()) echo "<option value='".$r['id']."'>".$r['nombre']."</option>"; 
                        ?>
                    </select>
                </div>
            </div>
            <button type="submit" name="crear_usuario" class="ug-btn-full bg-primary"><i class="fa-solid fa-user-plus"></i> Guardar Perfil en el Sistema</button>
        </form>
    </div>
    
    <div class="ug-panel">
        <h2 class="ug-header"><i class="fa-solid fa-users"></i> Directorio Activo de Empleados</h2>
        <div class="user-list">
            <?php
            $res = $conexion->query("SELECT u.*, r.nombre AS nombre_rol FROM usuarios u INNER JOIN roles r ON u.rol_id = r.id ORDER BY u.id DESC");
            if($res && $res->num_rows > 0): 
                while($u = $res->fetch_assoc()): 
                    $badgeClass = $u['estado'] == 1 ? 'badge-active' : 'badge-inactive';
                    $badgeText = $u['estado'] == 1 ? 'Activo' : 'Inactivo';
            ?>
            <div class="user-card">
                <div class="user-header">
                    <span class="user-name"><?php echo htmlspecialchars($u['nombre_completo']); ?></span>
                    <span class="user-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                </div>
                <div class="user-detail">Rol Asignado: <span><i class="fa-solid fa-key" style="color: #64748b; font-size: 0.7rem;"></i> <?php echo htmlspecialchars($u['nombre_rol']); ?></span></div>
                <div class="user-detail">Usuario Login: <span><?php echo htmlspecialchars($u['usuario']); ?></span></div>
                <div class="user-detail">PIN de Tótem: <span><?php echo !empty($u['pin_totem']) ? '<i class="fa-solid fa-check" style="color: #10b981;"></i> Establecido' : '<i class="fa-solid fa-xmark" style="color: #ef4444;"></i> Ninguno'; ?></span></div>
                <div class="user-detail">ID de Sistema: <span>#<?php echo $u['id']; ?></span></div>
                
                <div class="action-row">
                    <form method="POST" class="action-form">
                        <input type="hidden" name="id_usuario_rol" value="<?php echo $u['id']; ?>">
                        <select name="nuevo_rol_id" required class="ug-input" style="padding: 10px; font-size: 0.85rem; cursor: pointer; appearance: none;">
                            <?php 
                            $q_roles = $conexion->query("SELECT * FROM roles");
                            while($rl = $q_roles->fetch_assoc()): 
                                $sel = ($rl['id'] == $u['rol_id']) ? "selected" : "";
                            ?>
                            <option value="<?php echo $rl['id']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($rl['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <button type="submit" name="cambiar_rol" class="btn-action-small bg-role"><i class="fa-solid fa-user-tag"></i> Cambiar</button>
                    </form>

                    <form method="POST" class="action-form">
                        <input type="hidden" name="id_usuario_reset" value="<?php echo $u['id']; ?>">
                        <input type="text" name="nueva_clave" placeholder="Nueva clave..." required class="ug-input" style="padding: 10px; font-size: 0.85rem;">
                        <button type="submit" name="resetear_clave" class="btn-action-small bg-pass"><i class="fa-solid fa-key"></i> Reset</button>
                    </form>

                    <form method="POST" class="action-form">
                        <input type="hidden" name="id_usuario_pin" value="<?php echo $u['id']; ?>">
                        <input type="text" name="nuevo_pin" placeholder="PIN... (vacío borra)" class="ug-input" style="padding: 10px; font-size: 0.85rem;" value="<?php echo htmlspecialchars($u['pin_totem'] ?? ''); ?>">
                        <button type="submit" name="resetear_pin" class="btn-action-small" style="background: #8b5cf6;"><i class="fa-solid fa-hashtag"></i> Guardar PIN</button>
                    </form>

                    <form method="POST" class="action-form" style="grid-column: 1 / -1;">
                        <input type="hidden" name="id_usuario_medico" value="<?php echo $u['id']; ?>">
                        <input type="hidden" name="estado_medico" value="<?php echo $u['es_medico'] ? 0 : 1; ?>">
                        <button type="submit" name="toggle_medico" class="btn-action-small <?php echo $u['es_medico'] ? 'bg-med-yes' : 'bg-med-no'; ?>" style="width: 100%;">
                            <i class="fa-solid <?php echo $u['es_medico'] ? 'fa-user-doctor' : 'fa-user'; ?>"></i> 
                            <?php echo $u['es_medico'] ? 'Profesional Médico Registrado (SÍ)' : 'Marcar como Médico Profesional'; ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php 
                endwhile; 
            else: 
            ?>
                <div style="text-align:center; padding:40px; color:#64748b; font-weight:800; border: 2px dashed #cbd5e1; border-radius: 16px; grid-column: 1 / -1;">No hay usuarios registrados.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>