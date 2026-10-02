<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/conexion.php';

echo "<div style='font-family:sans-serif; padding:20px; max-width:800px; margin:auto;'>";
echo "<h2><span style='color:#0284c7;'>⚙️ Antigravity:</span> Actualizador Automático de Base de Datos</h2>";

$queries = [
    "ALTER TABLE registro_ingresos ADD COLUMN foto_base64 LONGTEXT NULL AFTER dni" => "Agregando columna biométrica...",
    "ALTER TABLE registro_ingresos ADD COLUMN alerta_no_citado TINYINT(1) DEFAULT 0 AFTER motivo" => "Agregando columna de alertas de turno...",
    "CREATE TABLE IF NOT EXISTS seguridad_incidencias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ronda_id INT NOT NULL,
        descripcion TEXT NOT NULL,
        nivel_gravedad VARCHAR(20) DEFAULT 'Baja',
        fecha_reporte TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )" => "Creando tabla de Incidencias de Rondas...",
    "CREATE TABLE IF NOT EXISTS seguridad_llaves_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        llave_id INT NOT NULL,
        dni_retiro VARCHAR(20) NOT NULL,
        fecha_retiro DATETIME NOT NULL,
        fecha_devolucion DATETIME NULL,
        usuario_seguridad_id INT NOT NULL
    )" => "Creando tabla de Trazabilidad de Llaves..."
];

foreach ($queries as $sql => $mensaje) {
    echo "<p><strong>{$mensaje}</strong><br>";
    if ($conexion->query($sql)) {
        echo "<span style='color:green;'>✅ Éxito.</span></p>";
    } else {
        $error = $conexion->error;
        if (strpos($error, 'Duplicate column') !== false || strpos($error, 'already exists') !== false) {
            echo "<span style='color:orange;'>⚠️ Ya existía (Omitido).</span></p>";
        } else {
            echo "<span style='color:red;'>❌ Error: {$error}</span></p>";
        }
    }
}

echo "<hr><h3 style='color:green;'>¡Actualización Completada! 🎉</h3>";
echo "<p>Ya podés cerrar esta página y volver al sistema.</p>";
echo "<a href='seguridad_dashboard.php' style='display:inline-block; padding:10px 20px; background:#0284c7; color:white; text-decoration:none; border-radius:5px;'>Volver a Seguridad</a>";
echo "</div>";
?>
