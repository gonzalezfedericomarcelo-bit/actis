<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'includes/conexion.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['dni']) || empty($data['dni'])) {
    echo json_encode(['status' => 'error', 'message' => 'No se recibieron datos válidos']);
    exit;
}

$dni = $conexion->real_escape_string($data['dni']);
$hc_afiliado = isset($data['afiliado']) ? $conexion->real_escape_string($data['afiliado']) : '';
$fuerza = isset($data['fuerza']) ? $conexion->real_escape_string($data['fuerza']) : '';
$operador = isset($data['operador']) ? $conexion->real_escape_string($data['operador']) : '';
$nombre = isset($data['nombre']) ? $conexion->real_escape_string($data['nombre']) : '';
$apellido = isset($data['apellido']) ? $conexion->real_escape_string($data['apellido']) : '';
$email = isset($data['email']) ? trim($conexion->real_escape_string($data['email'])) : '';
$telefono = isset($data['telefono']) ? $conexion->real_escape_string($data['telefono']) : '';
$fecha_nac = isset($data['fecha_nac']) ? $conexion->real_escape_string($data['fecha_nac']) : '';
$servicio = isset($data['servicio']) ? $conexion->real_escape_string($data['servicio']) : '';
$especialidad = isset($data['especialidad']) ? $conexion->real_escape_string($data['especialidad']) : '';
$fecha_turno = isset($data['fecha']) ? $conexion->real_escape_string($data['fecha']) : '';
$hora_turno = isset($data['hora']) ? $conexion->real_escape_string($data['hora']) : '';
$profesional = isset($data['profesional']) ? $conexion->real_escape_string($data['profesional']) : '';
$practicas = isset($data['practicas']) ? $conexion->real_escape_string($data['practicas']) : '';
$observaciones = isset($data['observaciones']) ? $conexion->real_escape_string($data['observaciones']) : '';
$numero_turno = isset($data['numero_turno']) ? $conexion->real_escape_string($data['numero_turno']) : '';

$numero_turno_anterior = isset($data['numero_turno_anterior']) ? $conexion->real_escape_string($data['numero_turno_anterior']) : '';

// 1. Paciente: Verificar si existe
$q_paciente = $conexion->query("SELECT id, hc, nombre, apellido, email FROM pacientes WHERE dni = '$dni' LIMIT 1");

$nombre_correo = $nombre;
$apellido_correo = $apellido;

if ($q_paciente && $q_paciente->num_rows > 0) {
    $row = $q_paciente->fetch_assoc();
    $paciente_id = $row['id'];
    
    // Capturamos el nombre real de la base de datos por si no vino completo en el payload
    $nombre_correo = $row['nombre'];
    $apellido_correo = $row['apellido'];
    
    // Si el paciente ya tenía un email registrado en ACTIS y el payload no trajo uno, lo usamos
    if (empty($email) && !empty($row['email'])) {
        $email = $row['email'];
    }

    // SOLO actualizamos contacto, afiliado y obra_social. EL NOMBRE Y APELLIDO QUEDAN INTACTOS
    $updates = [];
    if (empty($row['hc']) && !empty($hc_afiliado)) $updates[] = "hc = '$hc_afiliado'";
    if (!empty($telefono)) $updates[] = "telefono = '$telefono'";
    if (!empty($email)) $updates[] = "email = '$email'";
    if (!empty($fecha_nac)) $updates[] = "fecha_nacimiento = '$fecha_nac'";
    if (!empty($fuerza) && $fuerza !== 'N/A') $updates[] = "obra_social = '$fuerza'";

    if (count($updates) > 0) {
        $sql_upd = "UPDATE pacientes SET " . implode(", ", $updates) . " WHERE id = $paciente_id";
        $conexion->query($sql_upd);
    }
} else {
    // Si no existe, lo insertamos con nombre y apellido
    $fuerza_val = (!empty($fuerza) && $fuerza !== 'N/A') ? "'$fuerza'" : "NULL";
    $sql_insert_pac = "INSERT INTO pacientes (dni, hc, nombre, apellido, email, telefono, fecha_nacimiento, obra_social, categoria) 
                       VALUES ('$dni', '$hc_afiliado', '$nombre', '$apellido', '$email', '$telefono', " . ($fecha_nac ? "'$fecha_nac'" : "NULL") . ", $fuerza_val, 'Nuevo')";
    $conexion->query($sql_insert_pac);
    $paciente_id = $conexion->insert_id;
}

// 2. Preparar el array de turnos (Uno o Múltiples)
$turnos_a_guardar = [];
if (isset($data['turnos_multiples']) && is_array($data['turnos_multiples']) && count($data['turnos_multiples']) > 0) {
    foreach ($data['turnos_multiples'] as $t) {
        $turnos_a_guardar[] = [
            'fecha' => $conexion->real_escape_string($t['fecha']),
            'hora' => $conexion->real_escape_string($t['hora'])
        ];
    }
} else {
    $turnos_a_guardar[] = [
        'fecha' => $fecha_turno,
        'hora' => $hora_turno
    ];
}

// Agregar fuerza a observaciones de ACTIS para no perderla en la Base de Datos
if ($fuerza !== '' && $fuerza !== 'N/A') {
    $observaciones = "Fuerza: " . $fuerza . " | " . $observaciones;
}

$todos_insertados = true;
$ids_insertados = [];

// Insertar o actualizar CADA turno en la base de datos de ACTIS
foreach ($turnos_a_guardar as $turno_item) {
    $f = $turno_item['fecha'];
    $h = $turno_item['hora'];
    
    $existe_turno = false;
    $numero_a_buscar = !empty($numero_turno_anterior) ? $numero_turno_anterior : $numero_turno;

    if (!empty($numero_a_buscar)) {
        $q_check = $conexion->query("SELECT id FROM turnos WHERE numero_turno = '$numero_a_buscar' LIMIT 1");
        if ($q_check && $q_check->num_rows > 0) {
            $existe_turno = true;
        }
    }

    if ($existe_turno) {
        // ACTUALIZAMOS el turno existente
        $sql_turno = "UPDATE turnos SET 
                        fecha_turno = '$f', 
                        hora_turno = '$h', 
                        servicio = '$servicio', 
                        especialidad = '$especialidad', 
                        profesional = '$profesional', 
                        motivo_visita = '$practicas', 
                        comentario_paciente = '$observaciones', ";
        
        $es_reprog = isset($data['es_reprogramacion']) && $data['es_reprogramacion'] == true;
        if ($es_reprog) {
            $sql_turno .= "estado = 'Reprogramado', ";
            // Reemplazamos el numero_turno viejo por el nuevo
            if (!empty($numero_turno_anterior) && !empty($numero_turno)) {
                $sql_turno .= "numero_turno = '$numero_turno', ";
            }
        }
        
        $sql_turno .= "operador_externo = '$operador',
                        paciente_id = $paciente_id
                      WHERE numero_turno = '$numero_a_buscar'";
        
        if ($conexion->query($sql_turno)) {
            $ids_insertados[] = 0; // Marcador de éxito para update
        } else {
            $todos_insertados = false;
        }
    } else {
        // ES UN TURNO NUEVO: Insertamos
        $sql_turno = "INSERT INTO turnos (paciente_id, usuario_creador_id, fecha_turno, hora_turno, servicio, especialidad, profesional, motivo_visita, comentario_paciente, tipo_turno, estado, operador_externo, numero_turno) 
                      VALUES ($paciente_id, 1, '$f', '$h', '$servicio', '$especialidad', '$profesional', '$practicas', '$observaciones', 'Programado', 'Autorizado', '$operador', '$numero_turno')";
        
        if ($conexion->query($sql_turno)) {
            $ids_insertados[] = $conexion->insert_id;
        } else {
            $todos_insertados = false;
        }
    }
}

if ($todos_insertados) {
    // (El envío de correo ya no es automático. Ahora se hace manual mediante api_enviar_email.php)

    echo json_encode(['status' => 'success', 'message' => 'Turnos guardados en ACTIS correctamente']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error al guardar los turnos: ' . $conexion->error]);
}
?>