<?php
/**
 * update_usuarios_pin.php
 * Script para añadir la columna pin_totem a la tabla usuarios
 */
require_once 'includes/conexion.php';

// Verificar si la columna existe
$result = $conexion->query("SHOW COLUMNS FROM usuarios LIKE 'pin_totem'");
if ($result && $result->num_rows == 0) {
    // La columna no existe, la creamos
    $sql = "ALTER TABLE usuarios ADD COLUMN pin_totem VARCHAR(20) DEFAULT NULL";
    if ($conexion->query($sql) === TRUE) {
        echo "Columna 'pin_totem' agregada exitosamente a la tabla 'usuarios'.<br>";
    } else {
        echo "Error al agregar la columna: " . $conexion->error . "<br>";
    }
} else {
    echo "La columna 'pin_totem' ya existe en la tabla 'usuarios'.<br>";
}

// Agregar índice para búsquedas más rápidas en el login del tótem
$result_idx = $conexion->query("SHOW INDEX FROM usuarios WHERE Key_name = 'idx_pin_totem'");
if ($result_idx && $result_idx->num_rows == 0) {
    $sql_idx = "ALTER TABLE usuarios ADD INDEX idx_pin_totem (pin_totem)";
    if ($conexion->query($sql_idx) === TRUE) {
        echo "Índice 'idx_pin_totem' agregado exitosamente.<br>";
    }
}

echo "<br><b>Actualización de base de datos finalizada.</b>";
?>
