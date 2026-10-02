<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';

if (!isset($mis_permisos) || !in_array('modulo_seguridad', $mis_permisos)) { 
    echo "<script>window.location='dashboard.php';</script>"; 
    exit; 
}
?>
<style>
    .llaves-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
    .llave-card { background: #fff; border-radius: 16px; padding: 20px; border: 1px solid #e2e8f0; position: relative; transition: all 0.2s; }
    .llave-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    .llave-status { position: absolute; top: 15px; right: 15px; width: 12px; height: 12px; border-radius: 50%; }
    .status-disponible { background: #10b981; box-shadow: 0 0 8px #10b981; }
    .status-en-uso { background: #ef4444; box-shadow: 0 0 8px #ef4444; }
    .llave-icon { font-size: 2rem; color: #94a3b8; margin-bottom: 10px; }
    .llave-title { font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0 0 5px 0; }
    .llave-restricted { font-size: 0.75rem; background: #fee2e2; color: #dc2626; padding: 3px 8px; border-radius: 20px; font-weight: 800; display: inline-block; margin-bottom: 15px; }
    .btn-llave { width: 100%; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 700; color: #3b82f6; cursor: pointer; transition: 0.2s; }
    .btn-llave:hover { background: #eff6ff; border-color: #bfdbfe; }
    .btn-llave.devolver { color: #dc2626; }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
    <h2 style="margin:0; color:#0f172a;"><i class="fa-solid fa-key"></i> Tablero de Llaves</h2>
    <a href="seguridad_admin_llaves.php" style="background:#f1f5f9; padding:8px 15px; border-radius:8px; color:#475569; text-decoration:none; font-weight:bold;"><i class="fa-solid fa-gear"></i> Configurar Llaves</a>
</div>

<?php
$hora_actual = (int)date('H');
$bloqueo_horario = ($hora_actual < 6 || $hora_actual >= 20);

if ($bloqueo_horario) {
    echo '<div style="background:#fee2e2; border:1px solid #fca5a5; padding:15px; border-radius:12px; margin-bottom:20px; color:#dc2626; font-weight:bold;">
            <i class="fa-solid fa-lock"></i> BLOQUEO DE SEGURIDAD ACTIVO: No se permite el retiro de llaves fuera del horario operativo (06:00 a 20:00 hs). Las devoluciones sí están permitidas.
          </div>';
}
?>

<div class="llaves-grid">
    <?php
    $q_llaves = $conexion->query("SELECT * FROM seguridad_llaves ORDER BY nombre_llave ASC");
    if($q_llaves && $q_llaves->num_rows > 0) {
        while($llave = $q_llaves->fetch_assoc()) {
            $es_restringida = $llave['requiere_autorizacion'] == 1;
            $en_uso = $llave['estado'] !== 'Disponible';
            ?>
            <div class="llave-card">
                <div class="llave-status <?php echo $en_uso ? 'status-en-uso' : 'status-disponible'; ?>"></div>
                <div class="llave-icon"><i class="fa-solid fa-key"></i></div>
                <h3 class="llave-title"><?php echo htmlspecialchars($llave['nombre_llave']); ?></h3>
                
                <?php if($es_restringida): ?>
                    <div class="llave-restricted"><i class="fa-solid fa-triangle-exclamation"></i> Acceso Restringido</div>
                <?php else: ?>
                    <div style="height:25px;"></div>
                <?php endif; ?>

                <?php if($en_uso): ?>
                    <button class="btn-llave devolver" onclick="devolverLlave(<?php echo $llave['id']; ?>)">Registrar Devolución</button>
                <?php else: ?>
                    <?php if ($bloqueo_horario): ?>
                        <button class="btn-llave" style="background:#e2e8f0; color:#94a3b8; cursor:not-allowed;" disabled>Bloqueado por Horario</button>
                    <?php else: ?>
                        <button class="btn-llave" onclick="entregarLlave(<?php echo $llave['id']; ?>, <?php echo $es_restringida ? 'true' : 'false'; ?>)">Entregar Llave</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php
        }
    } else {
        echo "<p style='color:#64748b;'>No hay llaves creadas. Use el botón 'Configurar Llaves' para agregar una.</p>";
    }
    ?>
</div>

<script>
    function entregarLlave(id_llave, restringida) {
        if(restringida) {
            Swal.fire({
                title: 'Llave Restringida',
                text: 'Escanee el DNI autorizado:',
                input: 'text',
                showCancelButton: true,
                confirmButtonText: 'Verificar'
            }).then((result) => {
                if (result.isConfirmed && result.value) validarDniLlave(id_llave, result.value);
            });
        } else {
            Swal.fire({
                title: 'Entregar Llave',
                text: 'Escanee DNI de quien retira:',
                input: 'text',
                showCancelButton: true,
                confirmButtonText: 'Registrar'
            }).then((result) => {
                if (result.isConfirmed && result.value) mostrarExito('Entregada a DNI: ' + result.value);
            });
        }
    }

    function validarDniLlave(id_llave, dni_escaneado) {
        let formData = new FormData();
        formData.append('accion', 'validar_restringida');
        formData.append('llave_id', id_llave);
        formData.append('dni_escaneado', dni_escaneado);

        fetch('api_llaves.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'autorizado') mostrarExito('Autorizado: ' + data.nombre);
            else mostrarError(data.mensaje);
        }).catch(error => mostrarError('Error de red'));
    }

    function devolverLlave(id) {
        Swal.fire({ title: '¿Devolución?', icon: 'question', showCancelButton: true, confirmButtonText: 'Sí' })
        .then((result) => { if (result.isConfirmed) mostrarExito('Devolución registrada'); });
    }
</script>
<?php require_once 'includes/footer.php'; ?>
