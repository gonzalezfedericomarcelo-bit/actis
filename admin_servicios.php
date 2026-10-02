<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['agregar'])) {
        $nombre = strtoupper($conexion->real_escape_string(trim($_POST['nombre_servicio'])));
        $imprime = isset($_POST['imprime_numero']) ? 1 : 0;
        if (!empty($nombre)) {
            $conexion->query("INSERT INTO servicios (nombre, imprime_numero, consultas) VALUES ('$nombre', $imprime, 0)");
        }
        header("Location: admin_servicios.php?msg=agregado"); exit;
    }
    if (isset($_POST['editar_servicio_swal'])) {
        $id = (int)$_POST['id_servicio_swal'];
        $nombre = strtoupper($conexion->real_escape_string(trim($_POST['nombre_servicio_swal'])));
        $imprime = isset($_POST['imprime_numero_swal']) ? 1 : 0;
        $conexion->query("UPDATE servicios SET nombre = '$nombre', imprime_numero = $imprime WHERE id = $id");
        header("Location: admin_servicios.php?msg=editado"); exit;
    }
    if (isset($_POST['reset_todos'])) {
        $conexion->query("UPDATE servicios SET consultas = 0");
        header("Location: admin_servicios.php?msg=reset"); exit;
    }
}

if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    $conexion->query("DELETE FROM servicios WHERE id = $id");
    header("Location: admin_servicios.php?msg=eliminado"); exit;
}

// Obtener todos los servicios
$resultado = $conexion->query("SELECT * FROM servicios ORDER BY nombre ASC");
$servicios = [];
while ($row = $resultado->fetch_assoc()) { $servicios[] = $row; }

require_once 'includes/header.php';
?>

<style>
    /* Estructura Base 1400px / Flat */
    .as-container { max-width: 1400px; margin: 20px auto; padding: 0 15px; font-family: 'Poppins', sans-serif; }
    
    .as-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; }
    .as-title { font-size: 1.6rem; font-weight: 900; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 10px; }
    .as-title i { color: #144973; }

    .as-grid { display: grid; grid-template-columns: 1fr; gap: 20px; align-items: start; }
    @media (min-width: 992px) { .as-grid { grid-template-columns: 1fr 2fr; } }
    
    .as-col { display: flex; flex-direction: column; gap: 20px; }
    
    .as-panel { background: #ffffff; border-radius: 20px; padding: 25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; display: flex; flex-direction: column; }
    .as-panel h3 { font-size: 1.2rem; font-weight: 900; color: #0f172a; margin-top: 0; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
    
    .as-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 15px; }
    .as-label { font-size: 0.8rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .as-input { width: 100%; padding: 12px 15px; font-size: 0.95rem; border: 2px solid #cbd5e1; border-radius: 10px; background: #ffffff; outline: none; font-weight: 600; font-family: 'Poppins', sans-serif; color: #0f172a; box-sizing: border-box; transition: border 0.2s; }
    .as-input:focus { border-color: #144973; }
    
    .as-btn { width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 20px; border-radius: 10px; font-weight: 800; font-size: 0.95rem; border: none; cursor: pointer; color: white; transition: transform 0.2s; box-sizing: border-box; }
    .as-btn:active { transform: scale(0.98); }
    .btn-primary { background: #144973; } 
    .btn-success { background: #10b981; } 
    .btn-danger { background: #ef4444; } 
    
    /* Listado de Servicios */
    .servicios-lista { display: flex; flex-direction: column; gap: 12px; max-height: 500px; overflow-y: auto; padding-right: 5px; }
    .servicio-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; transition: background 0.2s; }
    .servicio-item:hover { background: #f1f5f9; }
    
    .info-badge { display: inline-block; padding: 4px 8px; font-size: 0.75rem; font-weight: 800; border-radius: 6px; text-transform: uppercase; margin-top: 4px; border: 1px solid transparent; }
    .badge-asis { background: #fef3c7; color: #d97706; border-color: #fde68a; }
    .badge-val { background: #e0f2fe; color: #0284c7; border-color: #bae6fd; }

    .input-row { display: flex; gap: 8px; align-items: center; }
    .btn-accion { width: 40px; height: 40px; border-radius: 10px; display: flex; justify-content: center; align-items: center; border: none; color: white; cursor: pointer; font-size: 0.95rem; transition: transform 0.2s; flex-shrink: 0; }
    .btn-accion:active { transform: scale(0.95); }

    .switch-container { display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 700; color: #475569; font-size: 0.95rem; margin-top: 5px; }
    .highlight { background-color: #fef08a; padding: 0 2px; border-radius: 4px; }
    
    /* Filtros Superiores */
    .filtros-servicios { display: grid; grid-template-columns: 1fr; gap: 10px; margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 16px; border: 1px solid #e2e8f0; }
    @media (min-width: 768px) { .filtros-servicios { grid-template-columns: 2fr 1fr 1fr; } }

    /* === RESPONSIVO MÓVIL ESTRICTO === */
    @media (max-width: 768px) {
        body { overflow-x: hidden !important; }
        .as-container { padding: 0 10px !important; padding-bottom: 40px !important; margin-top: 10px !important; overflow-x: hidden !important; }
        .as-panel { padding: 15px !important; width: 100% !important; box-sizing: border-box !important; border-radius: 16px !important; }
        .as-header { flex-direction: column; align-items: flex-start; gap: 10px; }
        .as-title { font-size: 1.3rem !important; flex-wrap: wrap !important; }
        
        .filtros-servicios { display: flex !important; flex-direction: column !important; padding: 15px !important; gap: 10px !important; }
        .filtros-servicios div, .filtros-servicios input, .filtros-servicios select { width: 100% !important; box-sizing: border-box !important; }
        
        .servicio-item { flex-direction: column !important; align-items: flex-start !important; gap: 15px !important; }
        .input-row { width: 100% !important; justify-content: flex-end !important; border-top: 1px dashed #cbd5e1; padding-top: 10px; }
        
        .as-input { width: 100% !important; box-sizing: border-box !important; }
        .as-btn { width: 100% !important; box-sizing: border-box !important; margin-top: 5px; }
    }
</style>

<div class="as-container">
    <div class="as-header">
        <h1 class="as-title"><i class="fa-solid fa-briefcase-medical"></i> Gestión de Servicios Médicos</h1>
    </div>

    <div class="as-grid">
        
        <div class="as-col">
            <div class="as-panel">
                <h3><i class="fa-solid fa-circle-plus" style="color: #10b981;"></i> Nuevo Servicio</h3>
                <form method="POST" action="">
                    <div class="as-group">
                        <label class="as-label">Nombre del Servicio / Especialidad:</label>
                        <input type="text" name="nombre_servicio" required class="as-input" placeholder="Ej: TRAUMATOLOGÍA" autocomplete="off">
                    </div>
                    <div class="as-group" style="margin-bottom: 20px;">
                        <label class="switch-container">
                            <input type="checkbox" name="imprime_numero" value="1" style="transform: scale(1.2); cursor:pointer; accent-color: #144973;">
                            Aparece en Asistencia Espontánea
                        </label>
                    </div>
                    <button type="submit" name="agregar" class="as-btn btn-success"><i class="fa-solid fa-plus"></i> Registrar Servicio</button>
                </form>
            </div>

            <div class="as-panel">
                <h3><i class="fa-solid fa-gears" style="color: #64748b;"></i> Acciones del Sistema</h3>
                <form method="POST" action="" id="form-reset-contadores">
                    <input type="hidden" name="reset_todos" value="1">
                    <button type="button" class="as-btn btn-danger" onclick="confirmarResetTotal()"><i class="fa-solid fa-arrow-rotate-left"></i> Reiniciar Consultas a 0</button>
                </form>
            </div>
        </div>

        <div class="as-col">
            <div class="as-panel">
                <h3><i class="fa-solid fa-list-check" style="color: #144973;"></i> Servicios Activos</h3>
                
                <div class="filtros-servicios">
                    <div>
                        <input type="text" id="buscador-predictivo" class="as-input" placeholder="🔍 Buscar servicio en tiempo real..." onkeyup="filtrarServicios()">
                    </div>
                    <div>
                        <select id="filtro-tipo" class="as-input" onchange="filtrarServicios()" style="appearance: none; cursor:pointer;">
                            <option value="todos">📋 Todos</option>
                            <option value="asistencia">🏥 Solo Asistencia</option>
                            <option value="validacion">✅ Solo Validación</option>
                        </select>
                    </div>
                    <div>
                        <select id="filtro-orden" class="as-input" onchange="filtrarServicios()" style="appearance: none; cursor:pointer;">
                            <option value="alfa">🔤 A-Z</option>
                            <option value="mas">📈 Más Vistos</option>
                            <option value="menos">📉 Menos Vistos</option>
                        </select>
                    </div>
                </div>

                <div class="servicios-lista" id="lista-servicios-dinamica">
                    <?php if(!empty($servicios)): foreach($servicios as $s): ?>
                        <div class="servicio-item" 
                             data-nombre="<?php echo htmlspecialchars($s['nombre']); ?>" 
                             data-tipo="<?php echo ($s['imprime_numero'] == 1) ? 'asistencia' : 'validacion'; ?>"
                             data-consultas="<?php echo (int)$s['consultas']; ?>">
                            <div>
                                <div class="servicio-nombre-txt" style="font-weight: 900; color: #0f172a; font-size: 1.1rem;"><?php echo htmlspecialchars($s['nombre']); ?></div>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <?php if($s['imprime_numero'] == 1): ?>
                                        <span class="info-badge badge-asis">🏥 Asistencia</span>
                                    <?php else: ?>
                                        <span class="info-badge badge-val">✅ Validación</span>
                                    <?php endif; ?>
                                    <span style="font-size: 0.8rem; color: #64748b; font-weight: 700; margin-top:4px;"><i class="fa-solid fa-chart-simple"></i> <?php echo $s['consultas']; ?> consultas</span>
                                </div>
                            </div>
                            <div class="input-row">
                                <button type="button" class="btn-accion btn-primary" onclick="abrirEditar(<?php echo $s['id']; ?>, '<?php echo addslashes($s['nombre']); ?>', <?php echo $s['imprime_numero']; ?>)"><i class="fa-solid fa-pen"></i></button>
                                <button type="button" class="btn-accion btn-danger" onclick="confirmarEliminar(<?php echo $s['id']; ?>)"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </div>
                    <?php endforeach; else: ?>
                        <div id="no-servicios-msg" style="text-align:center; padding:40px; color:#94a3b8; font-weight:700; border: 2px dashed #cbd5e1; border-radius: 12px;">No hay servicios médicos cargados.</div>
                    <?php endif; ?>
                    <div id="busqueda-vacia-msg" style="text-align:center; padding:40px; color:#94a3b8; font-weight:700; display:none; border: 2px dashed #cbd5e1; border-radius: 12px;">Ningún servicio coincide con los filtros aplicados.</div>
                </div>

            </div>
        </div>

    </div>
</div>

<form method="POST" action="" id="form_swal_edit" style="display:none;">
    <input type="hidden" name="editar_servicio_swal" value="1">
    <input type="hidden" id="swal_id" name="id_servicio_swal">
    <input type="hidden" id="swal_nombre" name="nombre_servicio_swal">
    <input type="checkbox" id="swal_imprime" name="imprime_numero_swal" value="1">
</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// BUSCADOR PREDICTIVO Y MULTI-FILTROS SIMULTÁNEOS (Rápido, del lado del cliente)
function filtrarServicios() {
    let query = document.getElementById('buscador-predictivo').value.trim().toUpperCase();
    let tipoFiltro = document.getElementById('filtro-tipo').value;
    let ordenFiltro = document.getElementById('filtro-orden').value;
    
    let lista = document.getElementById('lista-servicios-dinamica');
    let items = Array.from(lista.getElementsByClassName('servicio-item'));
    let visibles = 0;

    items.forEach(item => {
        let nombre = item.getAttribute('data-nombre');
        let tipo = item.getAttribute('data-tipo');
        let txtElement = item.getElementsByClassName('servicio-nombre-txt')[0];

        // Verificar coincidencia de texto y de tipo simultáneamente
        $matchTexto = (nombre.indexOf(query) > -1);
        $matchTipo = (tipoFiltro === 'todos' || tipo === tipoFiltro);

        if ($matchTexto && $matchTipo) {
            item.style.display = 'flex';
            visibles++;
            
            // Resaltar coincidencias de forma dinámica
            if (query !== '') {
                let regEx = new RegExp(query, "g");
                txtElement.innerHTML = nombre.replace(regEx, `<span class="highlight">${query}</span>`);
            } else {
                txtElement.innerHTML = nombre;
            }
        } else {
            item.style.display = 'none';
        }
    });

    document.getElementById('busqueda-vacia-msg').style.display = (visibles === 0 && items.length > 0) ? 'block' : 'none';

    // Aplicar ordenamiento dinámico del DOM
    if (ordenFiltro === 'mas') {
        items.sort((a, b) => parseInt(b.getAttribute('data-consultas')) - parseInt(a.getAttribute('data-consultas')));
    } else if (ordenFiltro === 'menos') {
        items.sort((a, b) => parseInt(a.getAttribute('data-consultas')) - parseInt(b.getAttribute('data-consultas')));
    } else {
        items.sort((a, b) => a.getAttribute('data-nombre').localeCompare(b.getAttribute('data-nombre')));
    }

    items.forEach(item => lista.appendChild(item));
}

function abrirEditar(id, nombreActual, imprimeActual) {
    Swal.fire({
        title: 'Editar Servicio Médico',
        html: `
            <div style="text-align:left; font-family:'Poppins',sans-serif;">
                <label style="font-weight:800; font-size:0.85rem; color:#64748b; display:block; margin-bottom:5px;">NOMBRE DE LA ESPECIALIDAD:</label>
                <input type="text" id="swal_inp_nombre" class="swal2-input" value="${nombreActual}" style="width:100%; margin:0 0 20px 0; box-sizing:border-box; border-radius:10px; font-weight:600; border: 2px solid #cbd5e1; outline: none; text-transform:uppercase;">
                <label class="switch-container">
                    <input type="checkbox" id="swal_chk_imprime" ${imprimeActual == 1 ? 'checked' : ''} style="transform: scale(1.2); accent-color:#144973;">
                    Aparece en Asistencia Espontánea
                </label>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#144973',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fa-solid fa-floppy-disk"></i> Guardar Cambios',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            let n = document.getElementById('swal_inp_nombre').value.trim();
            if(!n) { Swal.showValidationMessage('El nombre no puede estar vacío'); return false; }
            return { nombre: n, imprime: document.getElementById('swal_chk_imprime').checked };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('swal_id').value = id;
            document.getElementById('swal_nombre').value = result.value.nombre;
            document.getElementById('swal_imprime').checked = result.value.imprime;
            document.getElementById('form_swal_edit').submit();
        }
    });
}

function confirmarEliminar(id) {
    Swal.fire({
        title: '¿Eliminar servicio?',
        text: "Esta acción quitará el servicio del tótem de forma permanente.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fa-solid fa-trash"></i> Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'admin_servicios.php?eliminar=' + id;
        }
    });
}

function confirmarResetTotal() {
    Swal.fire({
        title: '¿Reiniciar contadores?',
        text: "Todas las métricas de consultas de los servicios volverán a cero.",
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fa-solid fa-arrow-rotate-left"></i> Sí, reiniciar todo',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('form-reset-contadores').submit();
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>