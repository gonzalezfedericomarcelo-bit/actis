<?php
require_once 'includes/conexion.php';
require_once 'includes/header.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = $conexion->real_escape_string($_POST['dni']);
    $hc = $conexion->real_escape_string($_POST['hc']);
    $nombre = $conexion->real_escape_string($_POST['nombre']);
    $apellido = $conexion->real_escape_string($_POST['apellido']);
    $email = $conexion->real_escape_string($_POST['email']);
    $whatsapp = preg_replace('/[^0-9]/', '', $_POST['whatsapp']);
    
    $turno_nro = $conexion->real_escape_string($_POST['turno_nro']);
    $fecha_turno = $conexion->real_escape_string($_POST['fecha_turno']);
    $hora_turno = $conexion->real_escape_string($_POST['hora_turno']);
    $profesional = $conexion->real_escape_string($_POST['profesional']);
    $servicio = $conexion->real_escape_string($_POST['servicio']);
    $especialidad = $conexion->real_escape_string($_POST['especialidad']);
    
    $usuario_creador_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1;

    // Validación de Email
    if(!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarError('Email inválido'); });</script>";
        echo "<div class='tarjeta glass-panel' style='text-align: center;'><h2 style='color: var(--color-peligro);'>Error</h2><p>El formato del email es incorrecto.</p><a href='turnos_crear.php' class='btn btn-primario'>Volver</a></div>";
        require_once 'includes/footer.php'; exit;
    }

    // Gestionar Paciente (Update o Insert)
    $resultado_paciente = $conexion->query("SELECT id FROM pacientes WHERE dni = '$dni'");
    if ($resultado_paciente->num_rows > 0) {
        $paciente = $resultado_paciente->fetch_assoc();
        $paciente_id = $paciente['id'];
        $conexion->query("UPDATE pacientes SET hc='$hc', nombre='$nombre', apellido='$apellido', email='$email', whatsapp='$whatsapp' WHERE id=$paciente_id");
    } else {
        $conexion->query("INSERT INTO pacientes (dni, hc, nombre, apellido, email, whatsapp) VALUES ('$dni', '$hc', '$nombre', '$apellido', '$email', '$whatsapp')");
        $paciente_id = $conexion->insert_id;
    }

    // Insertar Turno con todos los datos
    $sql_turno = "INSERT INTO turnos (paciente_id, usuario_creador_id, turno_nro_externo, fecha_turno, hora_turno, profesional, servicio, especialidad, estado) 
                  VALUES ($paciente_id, $usuario_creador_id, '$turno_nro', '$fecha_turno', '$hora_turno', '$profesional', '$servicio', '$especialidad', 'Pendiente')";
    
    if ($conexion->query($sql_turno) === TRUE) {
        echo "<div class='tarjeta glass-panel' style='text-align: center;'>";
        echo "<i class='fa-solid fa-calendar-check' style='font-size: 5rem; color: var(--color-exito); margin-bottom: 20px;'></i>";
        echo "<h2 style='font-size: 2.2rem; font-weight: 700;'>¡Turno Registrado!</h2>";
        echo "<p style='font-size: 1.2rem;'>El turno #$turno_nro de las $hora_turno para $servicio ha sido guardado.</p>";
        echo "<a href='turnos_crear.php' class='btn btn-primario' style='margin-top:20px;'><i class='fa-solid fa-rotate-left'></i> Nuevo Turno</a>";
        echo "</div>";
        echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarExito('Turno procesado correctamente'); });</script>";
    } else {
        echo "<div class='tarjeta glass-panel' style='text-align: center;'><h2 style='color: var(--color-peligro);'>Error</h2><p>".$conexion->error."</p><a href='turnos_crear.php' class='btn btn-primario'>Volver</a></div>";
    }
}
require_once 'includes/footer.php';
?>