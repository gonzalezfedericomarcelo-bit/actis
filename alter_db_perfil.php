<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'includes/conexion.php';

try {
    if (!isset($pdo)) {
        echo "PDO not found!\n";
        exit;
    }
    
    // Check and add foto_perfil
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'foto_perfil'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE usuarios ADD foto_perfil VARCHAR(255) DEFAULT 'default.png' AFTER pin_totem");
        echo "Columna foto_perfil añadida.<br>\n";
    } else {
        echo "Columna foto_perfil ya existe.<br>\n";
    }

    // Check and add firma_imagen_path
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'firma_imagen_path'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE usuarios ADD firma_imagen_path VARCHAR(255) NULL AFTER foto_perfil");
        echo "Columna firma_imagen_path añadida.<br>\n";
    } else {
        echo "Columna firma_imagen_path ya existe.<br>\n";
    }

    // Check and add telefono
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'telefono'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE usuarios ADD telefono VARCHAR(50) NULL AFTER email");
        echo "Columna telefono añadida.<br>\n";
    } else {
        echo "Columna telefono ya existe.<br>\n";
    }
    
    // Check and add genero
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'genero'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE usuarios ADD genero VARCHAR(20) DEFAULT 'otro' AFTER telefono");
        echo "Columna genero añadida.<br>\n";
    } else {
        echo "Columna genero ya existe.<br>\n";
    }
    
    // Check and add fecha_nacimiento
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'fecha_nacimiento'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE usuarios ADD fecha_nacimiento DATE NULL AFTER genero");
        echo "Columna fecha_nacimiento añadida.<br>\n";
    } else {
        echo "Columna fecha_nacimiento ya existe.<br>\n";
    }

    echo "<b>¡Base de datos actualizada con éxito!</b><br>\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>\n";
}
?>
