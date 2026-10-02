<?php
require_once 'includes/conexion.php';
try {
    $stmt = $pdo->query('SHOW TABLES LIKE "permisos"');
    if ($stmt->fetch()) {
        print_r($pdo->query('SELECT * FROM permisos')->fetchAll(PDO::FETCH_ASSOC));
    } else {
        echo "Tabla permisos no existe\n";
    }
    
    $stmt2 = $pdo->query('SHOW TABLES LIKE "rol_permiso"');
    if ($stmt2->fetch()) {
        print_r($pdo->query('SELECT * FROM rol_permiso')->fetchAll(PDO::FETCH_ASSOC));
    } else {
        echo "Tabla rol_permiso no existe\n";
    }
} catch (Exception $e) { echo $e->getMessage(); }
?>
