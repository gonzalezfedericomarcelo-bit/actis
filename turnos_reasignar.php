<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }

require_once 'includes/conexion.php';
require_once 'envio_correo.php';
require_once 'includes/header.php';

$mis_permisos = isset($_SESSION['permisos']) ? $_SESSION['permisos'] : [];
if(!in_array('modulo_turnos_editar', $mis_permisos)) {
    echo "<script>Swal.fire({icon: 'error', title: 'Acceso Denegado', text: 'No tienes permiso para editar turnos.'}).then(() => { window.location = 'dashboard.php'; });</script>";
    require_once 'includes/footer.php';
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['turno_id'])) {
    $turno_id = $conexion->real_escape_string($_POST['turno_id']);
    $paciente_id = $conexion->real_escape_string($_POST['paciente_id']);
    
    $email = $conexion->real_escape_string($_POST['email']);
    $whatsapp = $conexion->real_escape_string($_POST['whatsapp']);
    $servicio = $conexion->real_escape_string($_POST['servicio']);
    $fecha_turno = $conexion->real_escape_string($_POST['fecha_turno']);
    
    // Traemos datos del paciente para el correo
    $res_pac = $conexion->query("SELECT nombre, apellido, dni FROM pacientes WHERE id = $paciente_id");
    $pac = $res_pac->fetch_assoc();
    $nombre = $pac['nombre'];
    $apellido = $pac['apellido'];
    $dni = $pac['dni'];

    // Actualizamos Paciente
    $conexion->query("UPDATE pacientes SET email='$email', whatsapp='$whatsapp' WHERE id=$paciente_id");
    
    // Actualizamos Turno
    $sql_upd_turno = "UPDATE turnos SET fecha_turno='$fecha_turno', servicio='$servicio' WHERE id=$turno_id";
    
    if ($conexion->query($sql_upd_turno) === TRUE) {
        
        $datos_qr = "Turno Control: " . $turno_id . " - DNI: " . $dni . " - Fecha: " . $fecha_turno;
        $url_qr_google = "https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=" . urlencode($datos_qr) . "&choe=UTF-8";

        $asunto = "REASIGNACIÓN de Turno - Policlínica General ACTIS";
        $cuerpo_mensaje = "
        <div style='background-color: #f0f4f8; padding: 40px; font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.05);'>
                <div style='background-color: #f59e0b; padding: 30px; text-align: center; color: white;'>
                    <h1 style='margin: 0; font-size: 24px;'>Modificación de Turno</h1>
                </div>
                <div style='padding: 30px; color: #2d3436;'>
                    <p style='font-size: 16px;'>Hola <strong>$nombre $apellido</strong>,</p>
                    <p style='font-size: 16px;'>Te informamos que tu turno en la Policlínica General ACTIS ha sido reprogramado/reasignado. Nuevos detalles:</p>
                    <div style='background: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                        <p style='margin: 8px 0; font-size: 15px;'><strong>Servicio:</strong> $servicio</p>
                        <p style='margin: 8px 0; font-size: 15px;'><strong>Nueva Fecha:</strong> $fecha_turno</p>
                    </div>
                    <p style='text-align: center; margin-top: 30px;'>
                        <img src='$url_qr_google' alt='Código QR' style='border-radius: 10px; border: 1px solid #eee; padding: 10px;'>
                    </p>
                    <p style='font-size: 14px; color: #636e72; text-align: center; margin-top: 20px;'>Por favor, presenta este código actualizado al llegar.</p>
                </div>
            </div>
        </div>";

        if (!empty($email)) { enviarCorreoNativo($email, $asunto, $cuerpo_mensaje); }

        $texto_wa = "ACTUALIZACIÓN: Hola $nombre $apellido. Tu turno en la Policlínica General ACTIS ha sido REASIGNADO. Nuevo turno para $servicio el día $fecha_turno. Por favor, presenta tu código QR al llegar.";
        $link_whatsapp = "https://wa.me/" . preg_replace('/[^0-9]/', '', $whatsapp) . "?text=" . urlencode($texto_wa);

        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Turno Reasignado',
                text: 'Los cambios fueron guardados y se notificó al paciente.',
                showCancelButton: true,
                confirmButtonText: '<i class=\"fa-solid fa-list-check\"></i> Ver Listado',
                cancelButtonText: '<i class=\"fa-brands fa-whatsapp\"></i> Enviar WhatsApp',
                cancelButtonColor: '#25D366',
                reverseButtons: true
            }).then((result) => {
                if (result.dismiss === Swal.DismissReason.cancel) {
                    window.open('$link_whatsapp', '_blank');
                    window.location.href = 'turnos_listar.php';
                } else {
                    window.location.href = 'turnos_listar.php';
                }
            });
        </script>";
    }
}

// Cargar los datos a editar
if(!isset($_GET['id'])) { echo "<script>window.location='turnos_listar.php';</script>"; exit; }
$id_editar = $conexion->real_escape_string($_GET['id']);
$sql = "SELECT t.*, p.nombre, p.apellido, p.dni, p.email, p.whatsapp FROM turnos t INNER JOIN pacientes p ON t.paciente_id = p.id WHERE t.id = $id_editar";
$res = $conexion->query($sql);
if($res->num_rows == 0) { echo "<script>window.location='turnos_listar.php';</script>"; exit; }
$datos = $res->fetch_assoc();
?>

<div class="tarjeta glass-panel" style="max-width: 900px; margin: 0 auto;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; margin-bottom: 30px;">
        <div>
            <h2 style="font-size: 2rem; font-weight: 800; color: #0f172a; margin: 0;"><i class="fa-solid fa-pen-to-square" style="color: #f59e0b;"></i> Reasignar Turno</h2>
            <p style="color: #64748b; margin-top: 5px; font-size: 1.05rem;">
                Modificando el turno de: <strong style="color: var(--color-primario);"><?php echo $datos['nombre'] . ' ' . $datos['apellido']; ?> (DNI: <?php echo $datos['dni']; ?>)</strong>
            </p>
        </div>
        <a href="turnos_listar.php" class="btn" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;"><i class="fa-solid fa-arrow-left"></i> Volver al listado</a>
    </div>
    
    <form method="POST" action="">
        <input type="hidden" name="turno_id" value="<?php echo $datos['id']; ?>">
        <input type="hidden" name="paciente_id" value="<?php echo $datos['paciente_id']; ?>">
        
        <div style="background: rgba(255,255,255,0.8); border-radius: 16px; padding: 25px; border: 1px solid #e2e8f0; margin-bottom: 25px;">
            <h4 style="font-size: 1.1rem; color: #1e293b; margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;"><i class="fa-regular fa-calendar-check" style="color: var(--color-primario);"></i> Detalles del Turno</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div class="form-floating" style="margin-bottom: 0;">
                    <input type="text" name="servicio" id="servicio" value="<?php echo htmlspecialchars($datos['servicio']); ?>" placeholder=" " required>
                    <label for="servicio">Servicio / Especialidad</label>
                </div>
                <div class="form-floating" style="margin-bottom: 0;">
                    <input type="date" name="fecha_turno" id="fecha_turno" value="<?php echo $datos['fecha_turno']; ?>" placeholder=" " required>
                    <label for="fecha_turno">Fecha de Asignación</label>
                </div>
            </div>
        </div>

        <div style="background: rgba(255,255,255,0.8); border-radius: 16px; padding: 25px; border: 1px solid #e2e8f0; margin-bottom: 30px;">
            <h4 style="font-size: 1.1rem; color: #1e293b; margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;"><i class="fa-regular fa-address-book" style="color: #10b981;"></i> Vías de Notificación</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div class="form-floating" style="margin-bottom: 0;">
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($datos['email']); ?>" placeholder=" ">
                    <label for="email">Correo Electrónico</label>
                </div>
                <div class="form-floating" style="margin-bottom: 0;">
                    <input type="text" name="whatsapp" id="whatsapp" value="<?php echo htmlspecialchars($datos['whatsapp']); ?>" placeholder=" ">
                    <label for="whatsapp">Teléfono / WhatsApp</label>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primario" style="width: 100%; font-size: 1.25rem; padding: 20px; border-radius: 15px; box-shadow: 0 10px 20px rgba(37,99,235,0.2);">
            <i class="fa-solid fa-floppy-disk"></i> Confirmar y Enviar Notificaciones
        </button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>