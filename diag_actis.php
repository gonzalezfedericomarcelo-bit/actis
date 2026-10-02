<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'includes/conexion.php';

try {
    $response = [];
    $stmt = $pdo->query("SHOW TABLES LIKE 'ascensor_%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $response['tables'] = $tables;

    if (in_array('ascensor_visitas_tecnicas', $tables)) {
        $stmt = $pdo->query("SHOW COLUMNS FROM ascensor_visitas_tecnicas");
        $response['cols_visitas'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    if (in_array('ascensor_incidencias', $tables)) {
        $stmt = $pdo->query("SHOW COLUMNS FROM ascensor_incidencias");
        $response['cols_incidencias'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
