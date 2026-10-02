<?php
// Archivo: admin_ascensores.php
session_start();
require_once 'includes/conexion.php';

// --- REDIRECCIÓN DINÁMICA SIN HARDCODEAR ---
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

// --- LÓGICA DE CREACIÓN DE EQUIPO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    try {
        $nombre = trim($_POST['nombre']);
        $ubicacion = trim($_POST['ubicacion']);
        $serie = trim($_POST['serie']);
        $id_empresa = !empty($_POST['id_empresa']) ? $_POST['id_empresa'] : null;
        
        $sql = "INSERT INTO ascensores (nombre, ubicacion, nro_serie, id_empresa) VALUES (?,?,?,?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombre, $ubicacion, $serie, $id_empresa]);
        
        $_SESSION['swal_msg'] = "La nueva unidad fue registrada correctamente en la base de datos."; 
        $_SESSION['swal_type'] = "success";
    } catch (PDOException $e) { 
        $_SESSION['swal_msg'] = "Fallo crítico al intentar crear el registro: " . $e->getMessage(); 
        $_SESSION['swal_type'] = "error"; 
    }
    header("Location: admin_ascensores.php"); 
    exit;
}

// --- LÓGICA DE EDICIÓN DE EQUIPO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    try {
        $id_ascensor = $_POST['id_ascensor'];
        $nombre = trim($_POST['nombre']);
        $ubicacion = trim($_POST['ubicacion']);
        $serie = trim($_POST['serie']);
        $id_empresa = !empty($_POST['id_empresa']) ? $_POST['id_empresa'] : null;

        $sql = "UPDATE ascensores SET nombre = ?, ubicacion = ?, nro_serie = ?, id_empresa = ? WHERE id_ascensor = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombre, $ubicacion, $serie, $id_empresa, $id_ascensor]);
        
        $_SESSION['swal_msg'] = "La configuración de la unidad ha sido actualizada correctamente."; 
        $_SESSION['swal_type'] = "success";
    } catch (PDOException $e) { 
        $_SESSION['swal_msg'] = "Fallo al actualizar el registro: " . $e->getMessage(); 
        $_SESSION['swal_type'] = "error"; 
    }
    header("Location: admin_ascensores.php"); 
    exit;
}

// --- LÓGICA DE BORRADO DE EQUIPO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrar_id'])) {
    try {
        $id_borrar = $_POST['borrar_id'];
        $sql = "DELETE FROM ascensores WHERE id_ascensor = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_borrar]);
        
        $_SESSION['swal_msg'] = "La unidad fue dada de baja y eliminada del sistema."; 
        $_SESSION['swal_type'] = "success";
    } catch (PDOException $e) { 
        $_SESSION['swal_msg'] = "ACCIÓN BLOQUEADA: No se puede eliminar esta unidad. Posee historial técnico asociado (tickets o visitas). Elimine el historial previo si desea continuar."; 
        $_SESSION['swal_type'] = "error"; 
    }
    header("Location: admin_ascensores.php"); 
    exit;
}

// --- CONSULTAS A LA BASE DE DATOS ---
$sql_lista = "SELECT a.*, e.nombre as nombre_empresa 
              FROM ascensores a 
              LEFT JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa 
              ORDER BY a.nombre ASC";
$lista = $pdo->query($sql_lista)->fetchAll(PDO::FETCH_ASSOC);

$sql_empresas = "SELECT * FROM empresas_mantenimiento WHERE activo = 1 ORDER BY nombre ASC";
$empresas = $pdo->query($sql_empresas)->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include 'includes/header.php'; ?>
<style>
    .ug-container { padding: 20px; max-width: 1300px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
    .ug-panel { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .ug-header { margin-bottom: 20px; }
    .ug-header h2 { margin: 0; color: #1e293b; font-weight: 700; font-size: 1.5rem; display: flex; align-items: center; gap: 10px; }
    .ug-form-group { margin-bottom: 15px; }
    .ug-label { display: block; margin-bottom: 5px; font-weight: 600; color: #475569; font-size: 0.9rem; }
    .ug-input, .ug-select { width: 100%; padding: 10px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 0.95rem; box-sizing: border-box; }
    .ug-input:focus, .ug-select:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
    .ug-btn-primary { background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
    .ug-btn-primary:hover { background: #2563eb; }
    .ug-table-responsive { overflow-x: auto; }
    .ug-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .ug-table th, .ug-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 0.95rem; }
    .ug-table th { font-weight: 700; color: #475569; background: #f8fafc; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; }
    .ug-table tbody tr:hover { background: #f1f5f9; }
    .ug-badge { padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; display: inline-block; }
    .ug-badge-default { background: #e2e8f0; color: #475569; }
    .ug-badge-primary { background: #e0e7ff; color: #4338ca; }
    .ug-btn-action { background: none; border: none; cursor: pointer; padding: 6px; border-radius: 6px; transition: 0.2s; }
    .ug-btn-edit { color: #3b82f6; background: #eff6ff; }
    .ug-btn-edit:hover { background: #bfdbfe; }
    .ug-btn-delete { color: #ef4444; background: #fef2f2; margin-left: 5px; }
    .ug-btn-delete:hover { background: #fecaca; }
    
    @media (min-width: 992px) {
        .ug-grid { display: grid; grid-template-columns: 350px 1fr; gap: 20px; align-items: start; }
    }
</style>

<div class="ug-container">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div>
            <h1 style="margin:0; font-size:1.8rem; color:#0f172a; font-weight:800;">Gestión de Nodos</h1>
            <p style="margin:0; color:#64748b; font-size:0.95rem;">Configuración de ascensores y vinculación de empresas</p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="admin_empresas.php" class="ug-btn-primary" style="background:#8b5cf6; text-decoration:none;"><i class="fas fa-building"></i> Empresas Proveedoras</a>
            <a href="mantenimiento_ascensores.php" class="ug-btn-primary" style="text-decoration:none;"><i class="fas fa-tools"></i> Operaciones</a>
        </div>
    </div>

    <div class="ug-grid">
        <div class="ug-panel">
            <div class="ug-header">
                <h2><i class="fas fa-elevator text-primary"></i> Alta de Equipo</h2>
            </div>
            <form action="" method="POST">
                <div class="ug-form-group">
                    <label class="ug-label">Identificador (Ej: Ascensor A)</label>
                    <input type="text" name="nombre" class="ug-input" required>
                </div>
                <div class="ug-form-group">
                    <label class="ug-label">Ubicación (Ej: Torre Norte)</label>
                    <input type="text" name="ubicacion" class="ug-input" required>
                </div>
                <div class="ug-form-group">
                    <label class="ug-label">Nro. de Serie (Opcional)</label>
                    <input type="text" name="serie" class="ug-input">
                </div>
                <div class="ug-form-group">
                    <label class="ug-label">Empresa Asignada (Mantenimiento)</label>
                    <select name="id_empresa" class="ug-select">
                        <option value="">-- Sin empresa asignada --</option>
                        <?php foreach($empresas as $e): ?>
                            <option value="<?php echo $e['id_empresa']; ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="crear" class="ug-btn-primary" style="width:100%; justify-content:center;"><i class="fas fa-plus"></i> Registrar Unidad</button>
            </form>
        </div>

        <div class="ug-panel">
            <div class="ug-header">
                <h2><i class="fas fa-list text-primary"></i> Flota Instalada</h2>
            </div>
            <div class="ug-table-responsive">
                <table class="ug-table">
                    <thead>
                        <tr>
                            <th>Equipo</th>
                            <th>Ubicación</th>
                            <th>Contratista</th>
                            <th style="text-align:right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista as $a): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($a['nombre']); ?></strong>
                                <?php if(!empty($a['nro_serie'])): ?>
                                    <div style="font-size:0.8rem; color:#64748b;">SN: <?php echo htmlspecialchars($a['nro_serie']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($a['ubicacion']); ?></td>
                            <td>
                                <?php if($a['nombre_empresa']): ?>
                                    <span class="ug-badge ug-badge-primary"><i class="fas fa-building"></i> <?php echo htmlspecialchars($a['nombre_empresa']); ?></span>
                                <?php else: ?>
                                    <span class="ug-badge ug-badge-default">Interno / Sin Asignar</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right; white-space:nowrap;">
                                <a href="ascensor_detalle.php?id=<?php echo $a['id_ascensor']; ?>" class="ug-btn-action" style="color:#0ea5e9; background:#f0f9ff; display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; text-decoration:none;" title="Ver Historial"><i class="fas fa-eye"></i></a>
                                <button class="ug-btn-action ug-btn-edit" onclick="abrirEditar(<?php echo htmlspecialchars(json_encode($a)); ?>)" style="width:30px; height:30px;" title="Editar"><i class="fas fa-pen"></i></button>
                                <button class="ug-btn-action ug-btn-delete" onclick="borrar(<?php echo $a['id_ascensor']; ?>)" style="width:30px; height:30px;" title="Eliminar"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($lista)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:20px; color:#64748b;">No hay ascensores registrados</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edición -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px; border:none; box-shadow:0 10px 30px rgba(0,0,0,0.1);">
            <div class="modal-header" style="background:#f8fafc; border-bottom:1px solid #e2e8f0; border-radius: 16px 16px 0 0;">
                <h5 class="modal-title" style="font-weight:700;"><i class="fas fa-pen text-primary"></i> Modificar Equipo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body" style="padding:20px;">
                    <input type="hidden" name="id_ascensor" id="edit_id">
                    <div class="ug-form-group">
                        <label class="ug-label">Identificador</label>
                        <input type="text" name="nombre" id="edit_nombre" class="ug-input" required>
                    </div>
                    <div class="ug-form-group">
                        <label class="ug-label">Ubicación</label>
                        <input type="text" name="ubicacion" id="edit_ubicacion" class="ug-input" required>
                    </div>
                    <div class="ug-form-group">
                        <label class="ug-label">Nro. de Serie (Opcional)</label>
                        <input type="text" name="serie" id="edit_serie" class="ug-input">
                    </div>
                    <div class="ug-form-group">
                        <label class="ug-label">Empresa Asignada</label>
                        <select name="id_empresa" id="edit_empresa" class="ug-select">
                            <option value="">-- Sin empresa asignada --</option>
                            <?php foreach($empresas as $e): ?>
                                <option value="<?php echo $e['id_empresa']; ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e2e8f0;">
                    <button type="button" class="ug-btn-primary" style="background:#94a3b8;" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="editar" class="ug-btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="formBorrar" action="" method="POST" style="display:none;">
    <input type="hidden" name="borrar_id" id="borrar_id">
</form>

<script>
    <?php if(isset($_SESSION['swal_msg'])): ?>
    Swal.fire({
        icon: '<?php echo $_SESSION['swal_type']; ?>',
        title: '<?php echo $_SESSION['swal_type'] === "success" ? "¡Atención!" : "Atención"; ?>',
        text: '<?php echo $_SESSION['swal_msg']; ?>',
        confirmButtonColor: '#3b82f6'
    });
    <?php unset($_SESSION['swal_msg']); unset($_SESSION['swal_type']); ?>
    <?php endif; ?>

    function abrirEditar(data) {
        document.getElementById('edit_id').value = data.id_ascensor;
        document.getElementById('edit_nombre').value = data.nombre;
        document.getElementById('edit_ubicacion').value = data.ubicacion;
        document.getElementById('edit_serie').value = data.nro_serie;
        document.getElementById('edit_empresa').value = data.id_empresa ? data.id_empresa : '';
        new bootstrap.Modal(document.getElementById('modalEditar')).show();
    }

    function borrar(id) {
        Swal.fire({
            title: '¿Eliminar ascensor?',
            text: "Se borrará permanentemente de la base de datos.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('borrar_id').value = id;
                document.getElementById('formBorrar').submit();
            }
        });
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'includes/footer.php'; ?>
