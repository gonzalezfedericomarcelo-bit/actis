<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';

$res_llaves = $conexion->query("SELECT id, num_llave, nombre, estado, asignada_a FROM seguridad_llaves ORDER BY nombre ASC");
$llaves = [];
if ($res_llaves) {
    while ($row = $res_llaves->fetch_assoc()) {
        $llaves[] = $row;
    }
}

$res_rondas = $conexion->query("SELECT r.id, r.fecha_hora, r.novedades, r.estado, u.nombre as guardia FROM seguridad_rondas r LEFT JOIN usuarios u ON r.usuario_id = u.id ORDER BY r.id DESC LIMIT 50");
$rondas = [];
$alerta_rondas = false;
$ultima_ronda_ts = 0;

if ($res_rondas) {
    $first = true;
    while ($row = $res_rondas->fetch_assoc()) {
        $rondas[] = $row;
        if ($first) {
            $ultima_ronda_ts = strtotime($row['fecha_hora']);
            $first = false;
        }
    }
}

if ($ultima_ronda_ts > 0) {
    $diff_horas = (time() - $ultima_ronda_ts) / 3600;
    if ($diff_horas > 2) {
        $alerta_rondas = true;
    }
}

$res_ingresos = $conexion->query("SELECT id, dni, apellido, nombre, tipo_registro, destino, fecha_hora, foto_base64, alerta_no_citado FROM registro_ingresos ORDER BY id DESC LIMIT 50");
$ingresos = [];
if ($res_ingresos) {
    while ($row = $res_ingresos->fetch_assoc()) {
        $ingresos[] = $row;
    }
}

// Estado del Tótem (Botón de pánico)
$q_estado = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'estado_manual'");
$estado_totem = ($q_estado && $q_estado->num_rows > 0) ? $q_estado->fetch_assoc()['estado'] : 'ACTIVO';

// Alerta de Papel
$q_papel = $conexion->query("SELECT tipo, estado FROM totem_config WHERE tipo IN ('capacidad_rollo', 'tickets_impresos')");
$capacidad = 100;
$impresos = 0;
while($r = $q_papel->fetch_assoc()){
    if($r['tipo'] == 'capacidad_rollo') $capacidad = (int)$r['estado'];
    if($r['tipo'] == 'tickets_impresos') $impresos = (int)$r['estado'];
}
$alerta_papel = ($capacidad - $impresos) < 50;

echo json_encode([
    "status" => "success",
    "data" => [
        "llaves" => $llaves,
        "rondas" => $rondas,
        "ingresos" => $ingresos,
        "estado_totem" => $estado_totem,
        "alerta_rondas" => $alerta_rondas,
        "alerta_papel" => $alerta_papel
    ]
]);
?>
