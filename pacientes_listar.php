<?php
session_start();
require_once 'includes/conexion.php';

if(isset($_GET['exportar']) && $_GET['exportar'] == 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=padron_pacientes_".date('Ymd').".xls");
    echo "<table border='1'><tr><th>Paciente</th><th>DNI</th><th>Email</th><th>WhatsApp</th><th>Categoria</th></tr>";
    $ex = $conexion->query("SELECT * FROM pacientes ORDER BY apellido ASC");
    while($e = $ex->fetch_assoc()){ echo "<tr><td>".mb_convert_encoding($e['apellido'].", ".$e['nombre'],"ISO-8859-1","UTF-8")."</td><td>".$e['dni']."</td><td>".$e['email']."</td><td>".$e['whatsapp']."</td><td>".$e['categoria']."</td></tr>"; }
    echo "</table>"; exit;
}

require_once 'includes/header.php';

if(isset($_POST['eliminar_paciente']) && in_array('modulo_pacientes_eliminar', $_SESSION['permisos'])) {
    $id_eliminar = (int)$_POST['eliminar_paciente'];
    $conexion->query("DELETE FROM turnos WHERE paciente_id = $id_eliminar");
    $conexion->query("DELETE FROM auditoria_pacientes WHERE paciente_id = $id_eliminar");
    if($conexion->query("DELETE FROM pacientes WHERE id = $id_eliminar")) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarExito('Paciente eliminado correctamente.'); });</script>";
    } else {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarError('Error al eliminar el paciente.'); });</script>";
    }
}

$busqueda = isset($_POST['buscar']) ? $conexion->real_escape_string($_POST['buscar']) : '';
$where = "";

if($busqueda != '') {
    $where = "WHERE SOUNDEX(nombre) = SOUNDEX('$busqueda') OR SOUNDEX(apellido) = SOUNDEX('$busqueda') OR nombre LIKE '%$busqueda%' OR apellido LIKE '%$busqueda%' OR dni LIKE '%$busqueda%'";
}

if(isset($_GET['f']) && $_GET['f'] == 'ausentes') {
    $where = "WHERE id IN (SELECT paciente_id FROM turnos WHERE estado='Autorizado' AND fecha_turno < CURDATE())";
}

$sql = "SELECT * FROM pacientes $where ORDER BY apellido ASC";
$res = $conexion->query($sql);
?>
<style>
    .pl-container { max-width: 1400px; margin: 20px auto; padding: 0 15px; font-family: 'Poppins', sans-serif; }
    .pl-panel { background: #ffffff; border-radius: 20px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); }
    .pl-header { font-size: 1.6rem; font-weight: 900; margin-bottom: 20px; color: #0f172a; display: flex; align-items: center; gap: 10px; margin-top: 0; }
    
    .pl-filters { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; background: #f8fafc; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; align-items: flex-end; justify-content: space-between; }
    .pl-search-row { display: flex; gap: 10px; flex: 1; min-width: 300px; }
    
    .pl-input { width: 100%; padding: 12px 15px; border-radius: 10px; border: 2px solid #cbd5e1; font-weight: 600; font-family: 'Poppins', sans-serif; font-size: 0.95rem; outline: none; transition: border 0.2s; box-sizing: border-box; background: white; }
    .pl-input:focus { border-color: #144973; }
    
    .pl-btn-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .pl-btn { padding: 12px 20px; border: none; border-radius: 10px; font-weight: 800; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: transform 0.2s; text-decoration: none; color: white; white-space: nowrap; }
    .pl-btn:active { transform: scale(0.95); }
    .pl-btn-primary { background: #144973; }
    .pl-btn-warning { background: #f59e0b; }
    .pl-btn-success { background: #10b981; }
    .pl-btn-clear { background: #64748b; }
    
    .paciente-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 15px; }
    .paciente-card { background: #f8fafc; border-radius: 16px; padding: 20px; border: 1px solid #e2e8f0; position: relative; display: flex; flex-direction: column; gap: 15px; transition: transform 0.2s, border-color 0.2s; }
    .paciente-card:hover { transform: translateY(-2px); border-color: #144973; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    .p-header { display: flex; justify-content: space-between; align-items: flex-start; }
    .p-name { font-size: 1.15rem; font-weight: 900; color: #1e293b; line-height: 1.2; margin-bottom: 4px; }
    .p-badge { background: #ffffff; color: #144973; padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; border: 1px solid #cbd5e1; }
    
    .p-data { display: flex; flex-direction: column; gap: 8px; font-size: 0.9rem; color: #475569; }
    .p-data div { display: flex; align-items: center; gap: 8px; font-weight: 600; }
    .p-dni { color: #0f172a; font-weight: 800; font-size: 1rem; }
    
    .score-box { background: #ffffff; padding: 12px; border-radius: 10px; border: 1px solid #e2e8f0; }
    .score-header { display: flex; justify-content: space-between; font-size: 0.8rem; font-weight: 800; color: #64748b; margin-bottom: 6px; text-transform: uppercase; }
    .score-bar-bg { width: 100%; height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden; border: 1px solid #e2e8f0; }
    .score-bar-fill { height: 100%; border-radius: 4px; }
    
    .card-actions { display: flex; gap: 8px; margin-top: auto; }
    .btn-profile { flex: 1; background: #ffffff; color: #144973; padding: 12px; border-radius: 10px; text-align: center; text-decoration: none; font-weight: 800; font-size: 0.9rem; border: 1px solid #cbd5e1; transition: transform 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px; }
    .btn-profile:active { transform: scale(0.95); }
    .btn-icon-small { width: 44px; height: 44px; border-radius: 10px; display: inline-flex; justify-content: center; align-items: center; color: white; border: none; cursor: pointer; text-decoration: none; transition: transform 0.2s; }
    .btn-icon-small:active { transform: scale(0.95); }
    .bg-edit { background: #144973; }
    .bg-del { background: #ef4444; }

    .paciente-list.modo-lista { display: flex; flex-direction: column; }
    .paciente-list.modo-lista .paciente-card { flex-direction: row; align-items: center; justify-content: space-between; padding: 15px 20px; flex-wrap: wrap; }
    .paciente-list.modo-lista .p-header { flex-direction: column; width: 250px; gap: 5px; }
    .paciente-list.modo-lista .p-data { display: flex; flex-direction: row; gap: 20px; flex: 1; }
    .paciente-list.modo-lista .score-box { width: 150px; margin: 0 15px; }
    .paciente-list.modo-lista .card-actions { width: auto; }
</style>

<div class="pl-container">
    <div class="pl-panel">
        <h2 class="pl-header">
            <i class="fa-solid fa-hospital-user" style="color:#144973;"></i> Directorio de Pacientes
        </h2>

        <div class="pl-filters">
            <form method="POST" class="pl-search-row">
                <input type="text" name="buscar" placeholder="Buscar por DNI, Nombre o Apellido..." value="<?php echo htmlspecialchars($busqueda); ?>" class="pl-input">
                <button type="submit" class="pl-btn pl-btn-primary" style="padding: 12px 15px;"><i class="fa-solid fa-magnifying-glass"></i></button>
                <?php if($busqueda != '' || isset($_GET['f'])): ?>
                    <a href="pacientes_listar.php" class="pl-btn pl-btn-clear" style="padding: 12px 15px;"><i class="fa-solid fa-eraser"></i></a>
                <?php endif; ?>
            </form>
            
            <div class="pl-btn-actions">
                <a href="pacientes_listar.php?f=ausentes" class="pl-btn pl-btn-warning"><i class="fa-solid fa-user-slash"></i> No-Shows</a>
                <a href="pacientes_listar.php?exportar=excel" class="pl-btn pl-btn-success"><i class="fa-solid fa-file-excel"></i> Excel</a>
                <button type="button" class="pl-btn pl-btn-clear" onclick="toggleVista()" id="btnVista"><i class="fa-solid fa-list"></i> Vista</button>
            </div>
        </div>

        <div class="paciente-list">
            <?php if($res && $res->num_rows > 0): while($p = $res->fetch_assoc()): ?>
            <div class="paciente-card">
                <div class="p-header">
                    <div>
                        <div class="p-name"><?php echo htmlspecialchars($p['apellido'] . ", " . $p['nombre']); ?></div>
                        <div class="p-dni"><i class="fa-regular fa-id-card"></i> <?php echo htmlspecialchars($p['dni']); ?></div>
                    </div>
                    <div class="p-badge"><?php echo htmlspecialchars($p['categoria']); ?></div>
                </div>
                
                <div class="p-data">
                    <div><i class="fa-solid fa-envelope" style="color: #94a3b8;"></i> <?php echo htmlspecialchars($p['email'] ?: 'Sin email'); ?></div>
                    <div><i class="fa-brands fa-whatsapp" style="color: #94a3b8;"></i> <?php echo htmlspecialchars($p['whatsapp'] ?: 'Sin teléfono'); ?></div>
                </div>

                <?php
                $pid = $p['id'];
                $total = $conexion->query("SELECT COUNT(*) as t FROM turnos WHERE paciente_id = $pid AND fecha_turno <= CURDATE()")->fetch_assoc()['t'];
                $vinieron = $conexion->query("SELECT COUNT(*) as v FROM turnos WHERE paciente_id = $pid AND estado = 'Presente'")->fetch_assoc()['v'];
                $porcentaje = $total > 0 ? round(($vinieron / $total) * 100) : 0;
                $color = $porcentaje >= 70 ? '#10b981' : ($porcentaje >= 40 ? '#f59e0b' : '#ef4444');
                ?>
                <div class="score-box">
                    <div class="score-header">
                        <span>Asistencia Histórica</span>
                        <span style="color: <?php echo $color; ?>;"><?php echo $total > 0 ? $porcentaje.'%' : 'N/A'; ?></span>
                    </div>
                    <div class="score-bar-bg">
                        <div class="score-bar-fill" style="width: <?php echo $porcentaje; ?>%; background: <?php echo $color; ?>;"></div>
                    </div>
                </div>

                <div class="card-actions">
                    <a href="pacientes_perfil.php?id=<?php echo $p['id']; ?>" class="btn-profile"><i class="fa-solid fa-address-card"></i> Ficha Clínica</a>
                    <?php if(isset($_SESSION['permisos']) && in_array('modulo_pacientes_editar', $_SESSION['permisos'])): ?>
                        <a href="pacientes_editar.php?id=<?php echo $p['id']; ?>" class="btn-icon-small bg-edit" title="Editar"><i class="fa-solid fa-pen"></i></a>
                    <?php endif; ?>
                    <?php if(isset($_SESSION['permisos']) && in_array('modulo_pacientes_eliminar', $_SESSION['permisos'])): ?>
                        <form method="POST" style="margin:0;" onsubmit="return confirm('¿Estás seguro de eliminar este paciente definitivamente?');">
                            <input type="hidden" name="eliminar_paciente" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn-icon-small bg-del" title="Eliminar"><i class="fa-solid fa-trash-can"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; else: ?>
                <div style="grid-column: 1/-1; text-align:center; padding:60px 20px; background:#f8fafc; border-radius:16px; border:2px dashed #cbd5e1; color:#475569;">
                    <i class="fa-solid fa-folder-open" style="font-size:4rem; color:#cbd5e1; margin-bottom:15px; display:block;"></i>
                    <h3 style="margin:0; font-weight:800;">No se encontraron resultados</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const vistaGuardada = localStorage.getItem('vista_pacientes') || 'tarjeta';
        aplicarVista(vistaGuardada);
    });

    function toggleVista() {
        const lista = document.querySelector('.paciente-list');
        const vistaActual = lista.classList.contains('modo-lista') ? 'lista' : 'tarjeta';
        const nuevaVista = vistaActual === 'lista' ? 'tarjeta' : 'lista';
        aplicarVista(nuevaVista);
        localStorage.setItem('vista_pacientes', nuevaVista);
    }

    function aplicarVista(vista) {
        const lista = document.querySelector('.paciente-list');
        const btnIcon = document.querySelector('#btnVista i');
        if(vista === 'lista') {
            lista.classList.add('modo-lista');
            btnIcon.className = 'fa-solid fa-table-cells-large';
        } else {
            lista.classList.remove('modo-lista');
            btnIcon.className = 'fa-solid fa-list';
        }
    }
</script>
<?php require_once 'includes/footer.php'; ?>