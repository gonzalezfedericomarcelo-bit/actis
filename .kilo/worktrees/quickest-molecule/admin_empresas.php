<?php
// Archivo: admin_empresas.php
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

$mensaje = ''; 
$tipo_alerta = '';

// --- LÓGICA DE ACTUALIZAR / EDITAR PROVEEDOR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar'])) {
    try {
        $sql = "UPDATE empresas_mantenimiento SET nombre = ?, email_contacto = ?, telefono = ? WHERE id_empresa = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            trim($_POST['nombre']), 
            trim($_POST['email']), 
            trim($_POST['telefono']), 
            $_POST['id_empresa']
        ]);
        
        $mensaje = "Los datos de la empresa proveedora fueron actualizados correctamente en el sistema.";
        $tipo_alerta = "success";
    } catch (PDOException $e) {
        $mensaje = "Error al intentar actualizar los datos del proveedor: " . $e->getMessage();
        $tipo_alerta = "error";
    }
}

// --- LÓGICA DE CREAR NUEVO PROVEEDOR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    try {
        $sql = "INSERT INTO empresas_mantenimiento (nombre, email_contacto, telefono) VALUES (?,?,?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            trim($_POST['nombre']), 
            trim($_POST['email']), 
            trim($_POST['telefono'])
        ]);
        
        $mensaje = "El nuevo proveedor técnico fue registrado y dado de alta correctamente en el directorio.";
        $tipo_alerta = "success";
    } catch (PDOException $e) {
        $mensaje = "Error al intentar registrar el nuevo proveedor: " . $e->getMessage();
        $tipo_alerta = "error";
    }
}

// --- LÓGICA DE BORRAR PROVEEDOR (CON VALIDACIÓN DE HISTORIAL) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrar_id'])) {
    $id_borrar = $_POST['borrar_id'];
    
    try {
        $check = $pdo->prepare("SELECT COUNT(*) FROM ascensores WHERE id_empresa = ?");
        $check->execute([$id_borrar]);
        $count = $check->fetchColumn();

        if ($count > 0) {
            $mensaje = "ACCIÓN DENEGADA: No se puede eliminar esta empresa. Actualmente tiene " . $count . " equipo(s) asignado(s) bajo su responsabilidad. Debe reasignar esos equipos primero.";
            $tipo_alerta = "warning";
        } else {
            $sql_delete = "DELETE FROM empresas_mantenimiento WHERE id_empresa = ?";
            $stmt_delete = $pdo->prepare($sql_delete);
            $stmt_delete->execute([$id_borrar]);
            
            $mensaje = "El proveedor fue eliminado de forma permanente del sistema.";
            $tipo_alerta = "success";
        }
    } catch (PDOException $e) {
        $mensaje = "ACCIÓN DENEGADA: No se puede eliminar este proveedor porque posee registros históricos vinculados.";
        $tipo_alerta = "error";
    }
}

// --- OBTENER LISTA DE EMPRESAS CON CONTEO DE EQUIPOS ---
$sql_empresas = "SELECT e.*, 
                 (SELECT COUNT(*) FROM ascensores WHERE id_empresa = e.id_empresa) as total_equipos 
                 FROM empresas_mantenimiento e 
                 ORDER BY e.nombre ASC";
$empresas = $pdo->query($sql_empresas)->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include 'includes/header.php'; ?>
<style>
    .ug-container { padding: 20px; max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
    .ug-panel { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .ug-header { margin-bottom: 20px; }
    .ug-header h2 { margin: 0; color: #1e293b; font-weight: 700; font-size: 1.5rem; display: flex; align-items: center; gap: 10px; }
    .ug-form-group { margin-bottom: 15px; }
    .ug-label { display: block; margin-bottom: 5px; font-weight: 600; color: #475569; font-size: 0.9rem; }
    .ug-input { width: 100%; padding: 10px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 0.95rem; box-sizing: border-box; }
    .ug-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
    .ug-btn-primary { background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
    .ug-btn-primary:hover { background: #2563eb; }
    .ug-table-responsive { overflow-x: auto; }
    .ug-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .ug-table th, .ug-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 0.95rem; }
    .ug-table th { font-weight: 700; color: #475569; background: #f8fafc; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; }
    .ug-table tbody tr:hover { background: #f1f5f9; }
    .ug-badge { padding: 4px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; background: #e0e7ff; color: #4338ca; }
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
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h1 style="margin:0; font-size:1.8rem; color:#0f172a; font-weight:800;">Proveedores Logísticos</h1>
            <p style="margin:0; color:#64748b; font-size:0.95rem;">Directorio de empresas de mantenimiento</p>
        </div>
        <a href="admin_ascensores.php" class="ug-btn-primary" style="background:#f1f5f9; color:#475569; text-decoration:none;"><i class="fas fa-arrow-left"></i> Volver a Ascensores</a>
    </div>

    <div class="ug-grid">
        <div class="ug-panel">
            <div class="ug-header">
                <h2><i class="fas fa-building text-primary"></i> Registrar Empresa</h2>
            </div>
            <form action="" method="POST">
                <div class="ug-form-group">
                    <label class="ug-label">Razón Social</label>
                    <input type="text" name="nombre" class="ug-input" placeholder="Ej: Ascensores Sur S.A." required>
                </div>
                <div class="ug-form-group">
                    <label class="ug-label">Correo Electrónico</label>
                    <input type="email" name="email" class="ug-input" placeholder="tecnica@empresa.com" required>
                </div>
                <div class="ug-form-group">
                    <label class="ug-label">Teléfono de Guardia</label>
                    <input type="text" name="telefono" class="ug-input" placeholder="011 4444-5555" required>
                </div>
                <button type="submit" name="crear" class="ug-btn-primary" style="width:100%; justify-content:center;"><i class="fas fa-plus"></i> Guardar Registro</button>
            </form>
        </div>

        <div class="ug-panel">
            <div class="ug-header">
                <h2><i class="fas fa-list text-primary"></i> Directorio de Red</h2>
            </div>
            <div class="ug-table-responsive">
                <table class="ug-table">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>Contacto</th>
                            <th>Flota Asignada</th>
                            <th style="text-align:right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empresas as $e): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($e['nombre']); ?></strong></td>
                            <td>
                                <div><i class="fas fa-envelope text-muted"></i> <?php echo htmlspecialchars($e['email_contacto']); ?></div>
                                <div style="font-size:0.85rem; color:#64748b;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($e['telefono']); ?></div>
                            </td>
                            <td><span class="ug-badge"><?php echo $e['total_equipos']; ?> equipos</span></td>
                            <td style="text-align:right; white-space:nowrap;">
                                <button class="ug-btn-action ug-btn-edit" onclick="abrirEditar(<?php echo htmlspecialchars(json_encode($e)); ?>)"><i class="fas fa-pen"></i></button>
                                <button class="ug-btn-action ug-btn-delete" onclick="borrar(<?php echo $e['id_empresa']; ?>)"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($empresas)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:20px; color:#64748b;">No hay empresas registradas</td></tr>
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
                <h5 class="modal-title" style="font-weight:700;"><i class="fas fa-pen text-primary"></i> Editar Empresa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body" style="padding:20px;">
                    <input type="hidden" name="id_empresa" id="edit_id">
                    <div class="ug-form-group">
                        <label class="ug-label">Razón Social</label>
                        <input type="text" name="nombre" id="edit_nombre" class="ug-input" required>
                    </div>
                    <div class="ug-form-group">
                        <label class="ug-label">Correo Electrónico</label>
                        <input type="email" name="email" id="edit_email" class="ug-input" required>
                    </div>
                    <div class="ug-form-group">
                        <label class="ug-label">Teléfono de Guardia</label>
                        <input type="text" name="telefono" id="edit_telefono" class="ug-input" required>
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
    <?php if(!empty($mensaje)): ?>
    Swal.fire({
        icon: '<?php echo $tipo_alerta; ?>',
        title: '<?php echo $tipo_alerta === "success" ? "¡Éxito!" : "Atención"; ?>',
        text: '<?php echo $mensaje; ?>',
        confirmButtonColor: '#3b82f6',
        timer: 3000
    });
    <?php endif; ?>

    function abrirEditar(data) {
        document.getElementById('edit_id').value = data.id_empresa;
        document.getElementById('edit_nombre').value = data.nombre;
        document.getElementById('edit_email').value = data.email_contacto;
        document.getElementById('edit_telefono').value = data.telefono;
        new bootstrap.Modal(document.getElementById('modalEditar')).show();
    }

    function borrar(id) {
        Swal.fire({
            title: '¿Eliminar proveedor?',
            text: "Se borrará permanentemente del directorio.",
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
