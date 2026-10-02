<?php
require_once 'includes/conexion.php';
try {
    $stmt = $pdo->query('SELECT id_ascensor, nombre, ubicacion FROM ascensores');
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
