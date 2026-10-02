<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';

if (!isset($mis_permisos) || !in_array('modulo_seguridad_admin', $mis_permisos)) { 
    echo "<div style='padding:50px 20px; text-align:center; color:#ef4444; background:#ffffff; border-radius:15px; box-shadow:0 4px 15px rgba(0,0,0,0.05); margin:20px;'>
            <i class='fa-solid fa-shield-blank' style='font-size:4rem; margin-bottom:15px; color:#cbd5e1;'></i>
            <h2 style='margin:0; font-weight:800; font-size:1.5rem;'>Acceso Bloqueado</h2>
            <p style='color:#64748b; font-size:1rem; margin-top:10px;'>Tu rol actual no tiene privilegios de administrador para configurar llaves y DNI.</p>
          </div>";
    require_once 'includes/footer.php';
    exit; 
}

$msg_exito = '';
$msg_error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nueva_llave'])) {
    $nombre = $conexion->real_escape_string(trim($_POST['nombre_llave']));
    $req_auth = isset($_POST['req_auth']) ? 1 : 0;
    
    if($conexion->query("INSERT INTO seguridad_llaves (nombre_llave, requiere_autorizacion) VALUES ('$nombre', $req_auth)")) {
        $msg_exito = 'Consultorio / Llave creada correctamente.';
    } else {
        $msg_error = 'Error creando la llave: ' . $conexion->error;
    }
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nuevo_autorizado'])) {
    $llave_id = (int)$_POST['llave_id'];
    $dni = $conexion->real_escape_string(trim($_POST['dni']));
    $nombre_ref = $conexion->real_escape_string(trim($_POST['nombre_referencia']));
    
    if($conexion->query("INSERT INTO seguridad_llaves_autorizados (llave_id, dni, nombre_referencia) VALUES ($llave_id, '$dni', '$nombre_ref')")) {
        $msg_exito = 'Autorización vinculada al DNI correctamente.';
    } else {
        $msg_error = 'Error agregando autorización: ' . $conexion->error;
    }
}
?>
<style>
    .admin-container { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; max-width: 1200px; margin: 0 auto; }
    @media (max-width: 992px) { .admin-container { grid-template-columns: 1fr; } }
    .admin-card { background: #fff; border-radius: 16px; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
    .admin-title { font-size: 1.2rem; font-weight: 800; color: #0f172a; margin-top: 0; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; }
    .form-group { margin-bottom: 20px; }
    .form-label { display: block; font-size: 0.85rem; font-weight: 800; color: #64748b; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
    .form-control { width: 100%; padding: 14px 15px; font-size: 1rem; border: 2px solid #e2e8f0; border-radius: 10px; outline: none; transition: 0.2s; box-sizing: border-box; font-family: 'Poppins', sans-serif; background: #f8fafc; color: #1e293b; }
    .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59,130,246,0.15); background: #fff; }
    .btn-submit { width: 100%; padding: 14px; font-size: 1rem; font-weight: 800; border: none; border-radius: 10px; cursor: pointer; transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px; }
    .btn-primary { background: #2563eb; color: #fff; }
    .btn-primary:hover { background: #1d4ed8; }
    .btn-success { background: #10b981; color: #fff; }
    .btn-success:hover { background: #059669; }
    .checkbox-wrapper { display: flex; align-items: center; gap: 12px; margin-top: 5px; padding: 15px; background: #eff6ff; border-radius: 10px; border: 1px solid #bfdbfe; }
    .checkbox-wrapper input[type="checkbox"] { width: 22px; height: 22px; accent-color: #2563eb; cursor: pointer; margin: 0; }
    .checkbox-wrapper label { font-weight: 700; color: #1d4ed8; cursor: pointer; font-size: 0.95rem; margin: 0; }
</style>

<div style="margin-bottom: 25px;">
    <h2 style="margin:0; color:#0f172a; font-size: 1.5rem;"><i class="fa-solid fa-gear text-blue-600"></i> Configuración de Accesos</h2>
    <p style="color:#64748b; font-size:0.95rem; margin-top:5px;">Gestión de llaves y permisos de seguridad vinculados a DNI.</p>
</div>

<div class="admin-container">
    
    <div class="admin-card">
        <h3 class="admin-title"><i class="fa-solid fa-plus" style="color:#2563eb;"></i> Dar de alta Sector / Llave</h3>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Nombre del Sector o Llave</label>
                <input type="text" name="nombre_llave" class="form-control" placeholder="Ej: Consultorio 1, Depósito, etc." required autocomplete="off">
            </div>
            
            <div class="form-group checkbox-wrapper">
                <input type="checkbox" name="req_auth" id="req_auth" value="1"> 
                <label for="req_auth">Requiere Autorización Estricta (Escáner DNI)</label>
            </div>
            
            <button type="submit" name="nueva_llave" class="btn-submit btn-primary" style="margin-top: 30px;">
                <i class="fa-solid fa-floppy-disk"></i> Guardar Sector
            </button>
        </form>
    </div>

    <div class="admin-card">
        <h3 class="admin-title"><i class="fa-solid fa-id-card" style="color:#10b981;"></i> Autorizar DNI a una Llave</h3>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Seleccionar Llave Restringida</label>
                <select name="llave_id" class="form-control" required>
                    <option value="" disabled selected>Seleccione el sector...</option>
                    <?php
                    $q = $conexion->query("SELECT id, nombre_llave FROM seguridad_llaves WHERE requiere_autorizacion = 1 ORDER BY nombre_llave ASC");
                    if($q && $q->num_rows > 0) {
                        while($row = $q->fetch_assoc()) {
                            echo "<option value='".$row['id']."'>".htmlspecialchars($row['nombre_llave'])."</option>";
                        }
                    } else {
                        echo "<option value='' disabled>No hay llaves estrictas creadas</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Número de DNI</label>
                <input type="number" name="dni" class="form-control" placeholder="Sin puntos ni espacios" required autocomplete="off">
            </div>

            <div class="form-group">
                <label class="form-label">Nombre de Referencia</label>
                <input type="text" name="nombre_referencia" class="form-control" placeholder="Ej: Dr. Pérez / Limpieza" required autocomplete="off">
            </div>
            
            <button type="submit" name="nuevo_autorizado" class="btn-submit btn-success" style="margin-top: 15px;">
                <i class="fa-solid fa-user-check"></i> Vincular DNI
            </button>
        </form>
    </div>

</div>

<script>
    <?php if($msg_exito != ''): ?>
        mostrarExito('<?php echo $msg_exito; ?>');
    <?php endif; ?>
    <?php if($msg_error != ''): ?>
        mostrarError('<?php echo addslashes($msg_error); ?>');
    <?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>
