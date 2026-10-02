<?php
require_once 'includes/conexion.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

// Ahora los recibimos por GET desde la redirección del Tampermonkey
$dni = isset($_GET['dni']) ? $conexion->real_escape_string($_GET['dni']) : '';
$token_iofa = isset($_GET['token']) ? $conexion->real_escape_string($_GET['token']) : '';

if (empty($token_iofa)) {
    header("Location: https://validador.iosfa.gob.ar/ValidadorMejorado");
    exit;
}

// 1. Buscar al paciente o crearlo si no existe
$sql_paciente = "SELECT id, nombre, apellido FROM pacientes WHERE dni = '$dni' LIMIT 1";
$res_paciente = $conexion->query($sql_paciente);

if ($res_paciente && $res_paciente->num_rows > 0) {
    $paciente = $res_paciente->fetch_assoc();
    $paciente_nombre = strtoupper($paciente['nombre'] . ' ' . $paciente['apellido']);
    $paciente_id = $paciente['id'];
} else {
    $conexion->query("INSERT INTO pacientes (dni, nombre, apellido, categoria) VALUES ('$dni', 'AFILIADO', 'IOSFA', 'Nuevo')");
    $paciente_id = $conexion->insert_id;
    $paciente_nombre = "DNI: " . $dni;
}

// 2. Manejar el turno directamente
$fecha_hoy = date('Y-m-d');
$sql_turno = "SELECT id, servicio FROM turnos WHERE paciente_id = '$paciente_id' AND fecha_turno = '$fecha_hoy' AND (estado = 'Programado' OR estado = 'Autorizado' OR estado = 'Pendiente') LIMIT 1";
$res_turno = $conexion->query($sql_turno);

if ($res_turno && $res_turno->num_rows > 0) {
    $turno = $res_turno->fetch_assoc();
    $servicio = strtoupper($turno['servicio']);
    $turno_id = $turno['id'];
    $mensaje_extra = "Turno validado exitosamente.";
    $conexion->query("UPDATE turnos SET estado = 'Presente', recepcionado_el = NOW(), token_iofa = '$token_iofa' WHERE id = '$turno_id'");
} else {
    $servicio = "Atención Espontánea";
    $mensaje_extra = "Validado en Totem.";
    $conexion->query("INSERT INTO turnos (paciente_id, usuario_creador_id, fecha_turno, servicio, tipo_turno, token_iofa, estado, creado_el, recepcionado_el) VALUES ('$paciente_id', 1, '$fecha_hoy', 'DEMANDA ESPONTANEA', 'Sobre-turno', '$token_iofa', 'Presente', NOW(), NOW())");
}

$fecha_hora = date('d/m/Y H:i:s');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Imprimiendo Ticket...</title>
    <style>
        body { background-color: white; margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; height: 100vh; font-family: Arial, sans-serif; }
        .mensaje-pantalla { text-align: center; }
        .mensaje-pantalla h1 { color: #16a34a; font-size: 3rem; }
        .ticket-impresion { display: none; }
        
        @media print {
            @page { margin: 0; }
            body { display: block; }
            .mensaje-pantalla { display: none !important; }
            .ticket-impresion {
                display: block !important; width: 100%; max-width: 80mm; margin: 0 auto; text-align: center; font-family: 'Courier New', Courier, monospace; color: black;
            }
            .ticket-header { font-size: 18px; font-weight: bold; border-bottom: 1px dashed black; padding-bottom: 5px; margin-bottom: 5px; }
            .ticket-body { font-size: 14px; margin-bottom: 5px; }
            .ticket-servicio { font-size: 22px; font-weight: bold; margin: 10px 0; border-top: 1px solid black; border-bottom: 1px solid black; padding: 5px 0; }
            .ticket-footer { font-size: 12px; margin-top: 10px; font-weight: bold; }
        }
    </style>
</head>
<body>

    <div class="mensaje-pantalla">
        <h1>Imprimiendo Ticket...</h1>
    </div>

    <div class="ticket-impresion">
        <div class="ticket-header">
            POLICLÍNICA<br>GENERAL ACTIS
        </div>
        <div class="ticket-body">
            Fecha: <?php echo $fecha_hora; ?><br>
            Paciente:<br>
            <strong><?php echo $paciente_nombre; ?></strong>
        </div>
        <div class="ticket-servicio">
            <?php echo $servicio; ?>
        </div>
        <div class="ticket-footer">
            CÓDIGO: <?php echo $token_iofa; ?><br>
            <?php echo $mensaje_extra; ?><br>
            ---
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
            
            // Espera medio segundo (500ms) después de imprimir para no demorar el tótem
            setTimeout(function() {
                window.location.href = 'https://validador.iosfa.gob.ar/ValidadorMejorado';
            }, 500);
        };
    </script>
</body>
</html>