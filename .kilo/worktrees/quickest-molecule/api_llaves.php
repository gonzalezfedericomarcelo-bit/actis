<?php
session_start();
require_once 'includes/conexion.php';

header('Content-Type: application/json');

if(!isset($_SESSION['usuario_id']) || !isset($_POST['accion'])) {
    echo json_encode(['status' => 'error', 'mensaje' => 'Acceso denegado.']);
    exit;
}

$accion = $_POST['accion'];

if ($accion == 'validar_restringida') {
    $llave_id = (int)$_POST['llave_id'];
    $dni_escaneado = $conexion->real_escape_string(trim($_POST['dni_escaneado']));

    $q_auth = $conexion->query("SELECT nombre_referencia FROM seguridad_llaves_autorizados WHERE llave_id = $llave_id AND dni = '$dni_escaneado'");
    
    if ($q_auth && $q_auth->num_rows > 0) {
        $nombre = $q_auth->fetch_assoc()['nombre_referencia'];
        echo json_encode(['status' => 'autorizado', 'nombre' => $nombre]);
    } else {
        echo json_encode(['status' => 'denegado', 'mensaje' => 'DNI no autorizado para esta llave.']);
    }
    exit;
}
?>
