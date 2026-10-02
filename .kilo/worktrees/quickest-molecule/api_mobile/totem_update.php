<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once '../includes/conexion.php';

$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? '';

if (empty($action)) {
    echo json_encode(["status" => "error", "message" => "Acción no especificada"]);
    exit;
}

if ($action === 'cambiar_estado_manual') {
    $nuevo = $conexion->real_escape_string($data['estado'] ?? 'automatico');
    $conexion->query("UPDATE totem_config SET estado = '$nuevo' WHERE tipo = 'estado_manual'");
    echo json_encode(["status" => "success", "message" => "Estado actualizado"]);
} 
elseif ($action === 'resetear_papel_totem') {
    $conexion->query("UPDATE totem_config SET estado = '0' WHERE tipo = 'tickets_impresos'");
    $conexion->query("INSERT INTO historial_rollos (origen) VALUES ('TOTEM')");
    echo json_encode(["status" => "success", "message" => "Rollo de Tótem reseteado"]);
}
elseif ($action === 'resetear_papel_ventanilla') {
    $conexion->query("UPDATE totem_config SET estado = '0' WHERE tipo = 'tickets_impresos_ventanilla'");
    $conexion->query("INSERT INTO historial_rollos (origen) VALUES ('VENTANILLA')");
    echo json_encode(["status" => "success", "message" => "Rollo de Ventanilla reseteado"]);
}
elseif ($action === 'update_config') {
    $key = $conexion->real_escape_string($data['key'] ?? '');
    $value = $conexion->real_escape_string($data['value'] ?? '');
    if (!empty($key)) {
        $conexion->query("UPDATE totem_config SET estado = '$value' WHERE tipo = '$key'");
        echo json_encode(["status" => "success", "message" => "Configuración actualizada"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Falta la clave a actualizar"]);
    }
}
elseif ($action === 'agregar_feriado') {
    $fecha = $conexion->real_escape_string($data['fecha'] ?? '');
    if(!empty($fecha)) {
        $conexion->query("INSERT INTO totem_config (tipo, fecha) VALUES ('feriado', '$fecha')");
    }
    echo json_encode(["status" => "success"]);
}
elseif ($action === 'eliminar_feriado') {
    $id = (int)($data['id'] ?? 0);
    $conexion->query("DELETE FROM totem_config WHERE id = $id AND tipo = 'feriado'");
    echo json_encode(["status" => "success"]);
}
else {
    echo json_encode(["status" => "error", "message" => "Acción desconocida"]);
}
?>
