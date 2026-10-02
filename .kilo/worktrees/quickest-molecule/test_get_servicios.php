<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'includes/conexion.php';

if ($conexion->connect_error) {
    echo json_encode([["nombre" => "GUARDIA", "consultas" => 0, "imprime_numero" => 1]]);
    exit;
}

date_default_timezone_set('America/Argentina/Buenos_Aires');

// CREAR TABLA DE CONFIGURACION AUTOMATICAMENTE SI NO EXISTE
$conexion->query("CREATE TABLE IF NOT EXISTS `totem_config` ( `id` int(11) NOT NULL AUTO_INCREMENT, `tipo` varchar(50) NOT NULL, `fecha` date DEFAULT NULL, `estado` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$conexion->query("INSERT IGNORE INTO `totem_config` (`id`, `tipo`, `estado`) VALUES (1, 'estado_manual', 'automatico');");

// --- TEST TAMP88: VARIABLE DE SIMULACION ---
$conexion->query("INSERT IGNORE INTO `totem_config` (`tipo`, `estado`) SELECT 'simular_sin_papel_test', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM totem_config WHERE tipo = 'simular_sin_papel_test');");

$hora_actual = (int)date('H');
$dia_semana = (int)date('N'); // 1 = Lunes, 7 = Domingo
$fecha_hoy = date('Y-m-d');

// REINICIO AUTOMATICO DE CONTADORES AL COMENZAR EL DIA
$conexion->query("INSERT IGNORE INTO `totem_config` (`id`, `tipo`, `fecha`) VALUES (2, 'ultimo_reinicio', '2020-01-01');");
$res_reinicio = $conexion->query("SELECT fecha FROM totem_config WHERE tipo = 'ultimo_reinicio'");
if ($res_reinicio && $fila_reinicio = $res_reinicio->fetch_assoc()) {
    if ($fila_reinicio['fecha'] != $fecha_hoy) {
        $conexion->query("UPDATE servicios SET numero_actual = 0");
        $conexion->query("UPDATE totem_config SET fecha = '$fecha_hoy' WHERE tipo = 'ultimo_reinicio'");
    }
}

$estado_totem = "automatico";
$es_feriado = false;
$hora_apertura = "06:00";
$hora_cierre = "20:00";
$simular_sin_papel_test = "0";

$res_conf = $conexion->query("SELECT * FROM totem_config WHERE tipo IN ('estado_manual', 'hora_apertura', 'hora_cierre', 'simular_sin_papel_test') OR (tipo = 'feriado' AND fecha = '$fecha_hoy')");
if($res_conf) {
    while($row = $res_conf->fetch_assoc()) {
        if($row['tipo'] == 'estado_manual') $estado_totem = $row['estado'];
        if($row['tipo'] == 'feriado') $es_feriado = true;
        if($row['tipo'] == 'hora_apertura') $hora_apertura = $row['estado'];
        if($row['tipo'] == 'hora_cierre') $hora_cierre = $row['estado'];
        if($row['tipo'] == 'simular_sin_papel_test') $simular_sin_papel_test = $row['estado'];
    }
}

$hora_actual_str = date('H:i');

$cerrado = false;
$mensaje_cierre = "";

$res_papel = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'tickets_impresos'");
$tickets_impresos = ($res_papel && $res_papel->num_rows > 0) ? (int)$res_papel->fetch_assoc()['estado'] : 0;

$res_capa = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'capacidad_rollo'");
$capacidad_rollo = ($res_capa && $res_capa->num_rows > 0) ? (int)$res_capa->fetch_assoc()['estado'] : 1500;

$res_bloqueo = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'bloqueo_min_papel'");
$bloqueo_min_papel = ($res_bloqueo && $res_bloqueo->num_rows > 0) ? (int)$res_bloqueo->fetch_assoc()['estado'] : 0;

$papel_restante = $capacidad_rollo - $tickets_impresos;

// --- APLICAR SIMULACION DE SIN PAPEL (SOLO TEST) ---
if ($simular_sin_papel_test === "1") {
    $papel_restante = 0;
}
// ---------------------------------------------------

$sin_papel_flag = false;

if ($estado_totem == "cerrado") {
    $cerrado = true;
    $mensaje_cierre = "El Tótem se encuentra fuera de servicio.";
} elseif ($papel_restante <= $bloqueo_min_papel) {
    $cerrado = true;
    $sin_papel_flag = true;
    $mensaje_cierre = "TÓTEM BLOQUEADO POR FALTA DE PAPEL.<br><span style='font-size: 2.5vh; color:#94a3b8; display:block; margin-top:2vh;'>Por favor aguarde a que un operador reponga el rollo térmico.</span>";
} elseif ($estado_totem == "automatico") {
    if ($dia_semana >= 6) { 
        $cerrado = true;
        $mensaje_cierre = "Fuera de horario de atención (Fin de semana).";
    } elseif ($es_feriado) {
        $cerrado = true;
        $mensaje_cierre = "Fuera de horario de atención (Feriado).";
    } else {
        // CORRECCIÓN: Soporte para horarios invertidos o cruces de medianoche
        $ts_actual = strtotime($hora_actual_str);
        $ts_apertura = strtotime($hora_apertura);
        $ts_cierre = strtotime($hora_cierre);
        
        if ($ts_apertura < $ts_cierre) {
            // Horario normal en el mismo día (ej: 07:00 a 19:00)
            if ($ts_actual >= $ts_cierre || $ts_actual < $ts_apertura) {
                $cerrado = true;
                $mensaje_cierre = "Fuera de horario de atención.<br><span style='font-size: 2.5vh; color:#94a3b8; display:block; margin-top:2vh;'>Horario habilitado: Lunes a Viernes de $hora_apertura a $hora_cierre hs</span>";
            }
        } else {
            // Horario invertido / Prueba nocturna (ej: 14:52 a 05:49)
            if ($ts_actual >= $ts_cierre && $ts_actual < $ts_apertura) {
                $cerrado = true;
                $mensaje_cierre = "Fuera de horario de atención.<br><span style='font-size: 2.5vh; color:#94a3b8; display:block; margin-top:2vh;'>Horario habilitado: Lunes a Viernes de $hora_apertura a $hora_cierre hs</span>";
            }
        }
    }
}

$res_pin_rollo = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_rollo'");
$pin_rollo = ($res_pin_rollo && $res_pin_rollo->num_rows > 0) ? $res_pin_rollo->fetch_assoc()['estado'] : '88888';

if ($cerrado) {
    echo json_encode(["cerrado" => true, "mensaje" => $mensaje_cierre, "sin_papel" => $sin_papel_flag, "pin_rollo" => $pin_rollo]);
    exit;
}

$query = "SELECT id, nombre, consultas, imprime_numero FROM servicios ORDER BY nombre ASC";
$resultado = $conexion->query($query);

$servicios = [];
if ($resultado) {
    while ($fila = $resultado->fetch_assoc()) {
        $servicios[] = [
            "id" => (int)$fila['id'],
            "nombre" => strtoupper($fila['nombre']),
            "consultas" => (int)$fila['consultas'],
            "imprime_numero" => (int)$fila['imprime_numero']
        ];
    }
}

echo json_encode($servicios);
$conexion->close();
?>
