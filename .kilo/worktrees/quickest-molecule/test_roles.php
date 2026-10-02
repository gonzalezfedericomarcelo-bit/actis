<?php
require_once 'includes/conexion.php';

try {
    $stmt = $pdo->query('SHOW CREATE TABLE roles');
    print_r($stmt->fetch(PDO::FETCH_ASSOC));
    
    $stmt2 = $pdo->query('SHOW CREATE TABLE usuarios');
    print_r($stmt2->fetch(PDO::FETCH_ASSOC));
    
    $stmt3 = $pdo->query('SELECT * FROM roles');
    print_r($stmt3->fetchAll(PDO::FETCH_ASSOC));
    
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
