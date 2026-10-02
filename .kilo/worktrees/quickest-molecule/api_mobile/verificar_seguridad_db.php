<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';

$log = [];

// 1. Columnas en registro_ingresos
$q = $conexion->query("SHOW COLUMNS FROM registro_ingresos LIKE 'foto_base64'");
if ($q->num_rows == 0) {
    $conexion->query("ALTER TABLE registro_ingresos ADD COLUMN foto_base64 LONGTEXT NULL AFTER dni");
    $log[] = "Columna foto_base64 agregada.";
}

$q = $conexion->query("SHOW COLUMNS FROM registro_ingresos LIKE 'alerta_no_citado'");
if ($q->num_rows == 0) {
    $conexion->query("ALTER TABLE registro_ingresos ADD COLUMN alerta_no_citado TINYINT(1) DEFAULT 0 AFTER motivo");
    $log[] = "Columna alerta_no_citado agregada.";
}

// 2. Tabla seguridad_incidencias
$sql = "CREATE TABLE IF NOT EXISTS seguridad_incidencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ronda_id INT NOT NULL,
    descripcion TEXT NOT NULL,
    nivel_gravedad VARCHAR(20) DEFAULT 'Baja',
    fecha_reporte TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conexion->query($sql);

// 3. Tabla seguridad_llaves_log
$sql = "CREATE TABLE IF NOT EXISTS seguridad_llaves_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    llave_id INT NOT NULL,
    dni_retiro VARCHAR(20) NOT NULL,
    fecha_retiro DATETIME NOT NULL,
    fecha_devolucion DATETIME NULL,
    usuario_seguridad_id INT NOT NULL
)";
$conexion->query($sql);

echo json_encode([
    "status" => "success",
    "message" => "Base de datos verificada y actualizada.",
    "log" => $log
]);
?>
