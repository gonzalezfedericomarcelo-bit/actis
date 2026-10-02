<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['turno_id']) && isset($_POST['token_validacion'])) {
    
    $turno_id = $conexion->real_escape_string($_POST['turno_id']);
    $token_validacion = trim($conexion->real_escape_string($_POST['token_validacion']));
    $usuario_recepcion_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1;
    
    // Primero, traemos los datos del turno que seleccionamos
    $check_turno = $conexion->query("SELECT t.*, p.nombre, p.apellido, p.observaciones, p.dni, p.email, p.whatsapp 
                                     FROM turnos t 
                                     INNER JOIN pacientes p ON t.paciente_id = p.id 
                                     WHERE t.id = '$turno_id'");
    
    echo "<div class='tarjeta glass-panel' style='text-align: center;'>";
    
    if($check_turno->num_rows > 0) {
        $turno = $check_turno->fetch_assoc();
        $dni_paciente = $turno['dni'];
        
        if($turno['estado'] == 'Presente') {
            echo "<i class='fa-solid fa-triangle-exclamation' style='font-size: 6rem; color: var(--color-alerta); margin-bottom: 20px;'></i>";
            echo "<h2 style='font-size: 2.5rem; font-weight: 800;'>El paciente ya estaba presente</h2>";
            echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarInfo('Este turno ya fue escaneado anteriormente.'); });</script>";
        } else {
            // VERIFICACIÓN CLAVE: Buscamos si el ticket (token) ingresado existe HOY y corresponde al DNI
            $sql_totem = "SELECT * FROM estadisticas_totem 
                          WHERE UPPER(token_iofa) = UPPER('$token_validacion') 
                          AND dni = '$dni_paciente' 
                          AND DATE(fecha_hora) = CURDATE()";
            
            $check_totem = $conexion->query($sql_totem);
            
            if($check_totem->num_rows > 0) {
                // EXITO: El paciente se validó realmente en el Tótem
                
                // 1. Generar número de orden (ticket) dinámico según servicio
                $servicio = strtoupper(trim($turno['servicio']));
                $fecha_turno = $turno['fecha_turno'];
                
                $prefijo = "MED"; // Por defecto
                if(strlen($servicio) >= 3) {
                    $prefijo = substr(preg_replace('/[^A-Z]/', '', $servicio), 0, 3);
                }
                if(empty($prefijo)) $prefijo = "TUR";

                // Calcular el siguiente número de orden para ese servicio en el día de hoy
                $q_num = $conexion->query("SELECT count(*) as total FROM turnos WHERE fecha_turno = '$fecha_turno' AND servicio = '" . $conexion->real_escape_string($turno['servicio']) . "' AND (estado = 'Presente' OR estado = 'Atendido')");
                $row_num = $q_num->fetch_assoc();
                $siguiente_numero = str_pad($row_num['total'] + 1, 3, "0", STR_PAD_LEFT);
                
                $ticket_generado = $prefijo . "-" . $siguiente_numero;

                // 2. Guardamos la hora exacta, el token, la recepción y el nuevo TICKET DE ORDEN
                $conexion->query("UPDATE turnos SET estado = 'Presente', token_iofa = '$token_validacion', usuario_recepcion_id = $usuario_recepcion_id, recepcionado_el = NOW(), codigo_ticket_totem = '$ticket_generado' WHERE id = '" . $turno['id'] . "'");
                
                echo "<i class='fa-solid fa-clipboard-user' style='font-size: 6rem; color: var(--color-exito); margin-bottom: 20px;'></i>";
                echo "<h2 style='font-size: 2.5rem; font-weight: 800; color: var(--color-exito);'>¡Paciente en Sala de Espera!</h2>";
                echo "<p style='font-size: 1.5rem; font-weight: 600; margin: 20px 0;'>" . $turno['nombre'] . " " . $turno['apellido'] . "</p>";
                echo "<p style='font-size: 1.2rem; color: #64748b;'>Servicio: " . $turno['servicio'] . "<br>Código de Validación: " . strtoupper($token_validacion) . "<br>";
                echo "<strong>N° DE ORDEN ASIGNADO: <span style='color:#2563eb;font-size:1.4rem;'>" . $ticket_generado . "</span></strong><br><br>El paciente ya figura en el consultorio del médico.</p>";

                if(!empty($turno['observaciones'])) {
                    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({icon: 'warning', title: 'Notas del Paciente', text: '".$turno['observaciones']."', confirmButtonColor: '#f59e0b', background: 'rgba(255,255,255,0.95)'}); });</script>";
                } else {
                    echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarExito('Asistencia Confirmada con Éxito'); });</script>";
                }
                
            } else {
                // ERROR: El código o DNI no coincide en la tabla del tótem
                echo "<i class='fa-solid fa-circle-xmark' style='font-size: 6rem; color: var(--color-peligro); margin-bottom: 20px;'></i>";
                echo "<h2 style='font-size: 2.5rem; font-weight: 800; color: var(--color-peligro);'>Código Inválido</h2>";
                echo "<p style='font-size: 1.2rem;'>El código ingresado no existe en el Tótem para el día de hoy, o no corresponde al DNI de este paciente.</p>";
                echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarError('Ticket inexistente o expirado'); });</script>";
            }
        }
    } else {
        echo "<i class='fa-solid fa-circle-question' style='font-size: 6rem; color: var(--color-peligro); margin-bottom: 20px;'></i>";
        echo "<h2 style='font-size: 2.5rem; font-weight: 800; color: var(--color-peligro);'>Turno no encontrado</h2>";
        echo "<script>document.addEventListener('DOMContentLoaded', function() { mostrarError('Error en la búsqueda'); });</script>";
    }
    
    echo "<br><br><a href='recepcion_inicio.php' class='btn' style='background: #64748b; color: white;'><i class='fa-solid fa-rotate-left'></i> Volver al Escáner</a>";
    echo "</div>";
} else {
    header("Location: recepcion_inicio.php");
}
require_once 'includes/footer.php';
?>