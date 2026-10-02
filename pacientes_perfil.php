<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : die("ID no válido");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['actualizar_paciente'])) {
    $email = $conexion->real_escape_string($_POST['email'] ?? '');
    $wa = preg_replace('/[^0-9]/', '', $_POST['whatsapp'] ?? '');
    $obs = $conexion->real_escape_string($_POST['observaciones'] ?? '');
    $cat = $conexion->real_escape_string($_POST['categoria'] ?? '');
    $fnac = !empty($_POST['fecha_nacimiento']) ? "'".$conexion->real_escape_string($_POST['fecha_nacimiento'])."'" : "NULL";
    
    $conexion->query("UPDATE pacientes SET email='$email', whatsapp='$wa', observaciones='$obs', categoria='$cat', fecha_nacimiento=$fnac WHERE id=$id");
    
    $uid = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1;
    $conexion->query("INSERT INTO auditoria_pacientes (paciente_id, usuario_id, accion) VALUES ($id, $uid, 'Perfil actualizado desde dispositivo móvil')");
    echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarExito('Perfil actualizado correctamente'); });</script>";
}

$res_p = $conexion->query("SELECT * FROM pacientes WHERE id = $id");
if(!$res_p || $res_p->num_rows == 0) { die("Paciente no encontrado"); }
$p = $res_p->fetch_assoc();

$edad_str = 'No registrada';
if(!empty($p['fecha_nacimiento'])) {
    $edad_str = date_diff(date_create($p['fecha_nacimiento']), date_create('today'))->y . ' años';
}

// Variables blindadas contra nulos para evitar errores Deprecated en PHP 8.1+
$p_email = $p['email'] ?? '';
$p_whatsapp = $p['whatsapp'] ?? '';
$p_obs = $p['observaciones'] ?? '';
$p_cat = $p['categoria'] ?? '';
$p_fnac = $p['fecha_nacimiento'] ?? '';
$p_dni = $p['dni'] ?? '';
$p_nombre = ($p['apellido'] ?? '') . ', ' . ($p['nombre'] ?? '');
?>
<style>
    /* Estructura Base calcada de turnos_listar.php */
    .pp-container { max-width: 1400px; margin: 20px auto; padding: 0 15px; font-family: 'Poppins', sans-serif; display: flex; flex-direction: column; gap: 20px; }
    .pp-panel { background: #ffffff; border-radius: 20px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); }
    .pp-header { font-size: 1.6rem; font-weight: 900; margin-bottom: 20px; color: #0f172a; display: flex; align-items: center; gap: 10px; margin-top: 0; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; }
    
    /* Grid layout para organizar tarjetas dentro del contenedor */
    .pp-grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
    @media(min-width: 992px) { .pp-grid { grid-template-columns: 1fr 1fr; } }
    
    /* Inputs y Forms calcados */
    .pp-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 15px; }
    .pp-label { font-size: 0.8rem; font-weight: 800; color: #64748b; text-transform: uppercase; }
    .pp-input { width: 100%; padding: 12px 15px; border-radius: 10px; border: 2px solid #cbd5e1; font-weight: 600; font-family: 'Poppins', sans-serif; font-size: 0.95rem; outline: none; transition: border 0.2s; box-sizing: border-box; background: white; }
    .pp-input:focus { border-color: #144973; }
    
    /* Botones calcados */
    .pp-btn { width: 100%; padding: 12px 20px; border: none; border-radius: 10px; font-weight: 800; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: transform 0.2s; text-decoration: none; color: white; white-space: nowrap; }
    .pp-btn:active { transform: scale(0.98); }
    .pp-btn-primary { background: #144973; }
    
    /* Elementos específicos de perfil */
    .info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; align-items: center; }
    .info-row:last-child { border: none; }
    .info-lbl { font-weight: 800; color: #64748b; }
    .info-val { font-weight: 800; color: #0f172a; text-align: right; }
    
    /* Turnos (Historial) integrado al estilo de tabla/tarjeta de turnos_listar */
    .turn-card { background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; padding: 15px; margin-bottom: 15px; display: flex; flex-direction: column; gap: 10px; }
    .turn-header { display: flex; justify-content: space-between; align-items: center; }
    .turn-date { font-weight: 800; color: #0f172a; font-size: 1.05rem; }
    .turn-status { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; border: 1px solid transparent; }
    
    .turn-body { font-size: 0.9rem; color: #475569; font-weight: 600; }
    .turn-actions { display: flex; gap: 10px; margin-top: 5px; flex-wrap: wrap; }
    .btn-action-t { flex: 1; padding: 10px; border-radius: 10px; text-decoration: none; font-size: 0.85rem; font-weight: 800; display: inline-flex; justify-content: center; align-items: center; gap: 6px; border: none; min-width: 150px; transition: transform 0.2s; }
    .btn-action-t:active { transform: scale(0.98); }
</style>

<div class="pp-container">
    <div class="pp-grid">
        <div class="pp-panel">
            <h2 class="pp-header"><i class="fa-solid fa-id-card" style="color:#144973;"></i> Datos del Paciente</h2>
            <div class="info-row"><span class="info-lbl">Apellido y Nombre</span><span class="info-val" style="color: #144973; font-size:1.1rem;"><?php echo htmlspecialchars($p_nombre); ?></span></div>
            <div class="info-row"><span class="info-lbl">DNI Afiliado</span><span class="info-val"><?php echo htmlspecialchars($p_dni); ?></span></div>
            <div class="info-row"><span class="info-lbl">Edad Real</span><span class="info-val"><?php echo $edad_str; ?></span></div>
            <div class="info-row"><span class="info-lbl">Categoría / Plan</span><span class="info-val" style="background:#f1f5f9; padding:4px 8px; border-radius:6px; font-family:monospace;"><?php echo htmlspecialchars($p_cat ?: 'SIN CATEGORIA'); ?></span></div>
        </div>

        <div class="pp-panel">
            <h2 class="pp-header"><i class="fa-solid fa-user-pen" style="color:#144973;"></i> Actualizar Ficha</h2>
            <form method="POST" action="">
                <div class="pp-group">
                    <label class="pp-label">Correo Electrónico</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($p_email); ?>" class="pp-input">
                </div>
                <div class="pp-group">
                    <label class="pp-label">WhatsApp (Con código área)</label>
                    <input type="text" name="whatsapp" value="<?php echo htmlspecialchars($p_whatsapp); ?>" class="pp-input">
                </div>
                <div class="pp-grid" style="gap: 15px; margin-bottom: 15px; grid-template-columns: 1fr 1fr;">
                    <div class="pp-group" style="margin:0;">
                        <label class="pp-label">Nacimiento</label>
                        <input type="date" name="fecha_nacimiento" value="<?php echo htmlspecialchars($p_fnac); ?>" class="pp-input">
                    </div>
                    <div class="pp-group" style="margin:0;">
                        <label class="pp-label">Categoría</label>
                        <select name="categoria" class="pp-input">
                            <option value="GENERAL" <?php echo ($p_cat == 'GENERAL' || empty($p_cat)) ? 'selected' : ''; ?>>GENERAL</option>
                            <option value="AFILIADO" <?php echo ($p_cat == 'AFILIADO') ? 'selected' : ''; ?>>AFILIADO</option>
                            <option value="MILITAR" <?php echo ($p_cat == 'MILITAR') ? 'selected' : ''; ?>>MILITAR</option>
                        </select>
                    </div>
                </div>
                <div class="pp-group">
                    <label class="pp-label">Observaciones Internas</label>
                    <textarea name="observaciones" class="pp-input" style="height:80px; resize:none; font-family:inherit;"><?php echo htmlspecialchars($p_obs); ?></textarea>
                </div>
                <button type="submit" name="actualizar_paciente" class="pp-btn pp-btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar Cambios</button>
            </form>
        </div>
    </div>

    <div class="pp-panel">
        <h2 class="pp-header"><i class="fa-solid fa-clock-rotate-left" style="color:#144973;"></i> Historial Clínico y Turnos</h2>
        <div>
            <?php
            $t_res = $conexion->query("SELECT * FROM turnos WHERE paciente_id = $id ORDER BY fecha_turno DESC, hora_turno DESC");
            if($t_res && $t_res->num_rows > 0): while($t = $t_res->fetch_assoc()):
                $estado = $t['estado'] ?? 'Desconocido';
                
                // Mismos colores que turnos_listar.php
                $bg_estado = "#fef3c7"; $color_estado = "#92400e"; $border_estado = "#fde68a";
                if($estado == 'Autorizado') { $bg_estado = "#eff6ff"; $color_estado = "#1e40af"; $border_estado = "#bfdbfe"; }
                if($estado == 'Presente') { $bg_estado = "#10b981"; $color_estado = "#ffffff"; $border_estado = "#059669"; }
                if($estado == 'Cancelado') { $bg_estado = "#fee2e2"; $color_estado = "#dc2626"; $border_estado = "#fecaca"; }
                if($estado == 'Atendido') { $bg_estado = "#dcfce7"; $color_estado = "#166534"; $border_estado = "#bbf7d0"; }
                
                $fecha_formateada = !empty($t['fecha_turno']) ? date("d/m/Y", strtotime($t['fecha_turno'])) : '--/--/----';
                $hora_formateada = !empty($t['hora_turno']) ? date("H:i", strtotime($t['hora_turno'])) : '--:--';
            ?>
            <div class="turn-card">
                <div class="turn-header">
                    <span class="turn-date"><i class="fa-regular fa-clock"></i> <?php echo $fecha_formateada; ?> - <?php echo $hora_formateada; ?> hs</span>
                    <span class="turn-status" style="background:<?php echo $bg_estado; ?>; color:<?php echo $color_estado; ?>; border-color:<?php echo $border_estado; ?>;"><?php echo htmlspecialchars($estado); ?></span>
                </div>
                <div class="turn-body">
                    <div><i class="fa-solid fa-user-doctor"></i> Servicio/Especialidad: <span style="font-weight:800; color:#0f172a;"><?php echo htmlspecialchars($t['especialidad'] ?? 'GENERAL'); ?></span></div>
                </div>
                <div class="turn-actions">
                    <?php if(($estado == 'Autorizado' || $estado == 'Validado') && !empty($p_whatsapp)): 
                        $url_tkt = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/ticket_publico.php?id=" . $t['id'];
                        $wa_link = "https://wa.me/" . $p_whatsapp . "?text=" . urlencode("¡Hola! Tu turno validado está listo aquí: $url_tkt");
                    ?>
                        <a href="<?php echo $wa_link; ?>" target="_blank" class="btn-action-t" style="background:#25D366; color:white;"><i class="fa-brands fa-whatsapp"></i> Enviar Ticket por WP</a>
                    <?php endif; ?>

                    <?php if($estado == 'Presente' || $estado == 'Atendido'): ?>
                        <a href="recepcion_salida.php?id=<?php echo $t['id']; ?>" class="btn-action-t" style="background:#144973; color:white;"><i class="fa-solid fa-pen-nib"></i> Gestionar Firma</a>
                        <a href="certificado_asistencia.php?id=<?php echo $t['id']; ?>" target="_blank" class="btn-action-t" style="background:#64748b; color:white;"><i class="fa-solid fa-file-pdf"></i> PDF Constancia</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; else: ?>
                <div style="text-align:center; padding:60px 20px; background:#f8fafc; border-radius:16px; border:2px dashed #cbd5e1; color:#475569;">
                    <i class="fa-solid fa-inbox" style="font-size:4rem; color:#cbd5e1; margin-bottom:15px; display:block;"></i>
                    <h3 style="margin:0; font-weight:800;">Sin Historial Clínico</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>