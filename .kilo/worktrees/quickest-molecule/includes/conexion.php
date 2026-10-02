<?php
// Forzar a PHP a mostrar el error real en vez de dejar la pantalla en blanco (Error 500)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Datos de conexión reales en Hostinger
$servidor = "localhost"; // En Hostinger el servidor de BD suele ser localhost
$usuario = "u415354546_clinica_actis_"; 
$password = "Fmg35911@"; 
$base_datos = "u415354546_clinica_actis_"; 

try {
    // Crear la conexión
    $conexion = new mysqli($servidor, $usuario, $password, $base_datos);
    // Forzar el uso de caracteres UTF-8
    $conexion->set_charset("utf8mb4");
    
    // Sincronizar el horario de la base de datos y de PHP con Argentina (-03:00) para todo el sistema
    $conexion->query("SET time_zone = '-03:00';");
    date_default_timezone_set('America/Argentina/Buenos_Aires');
    
    // --- SOPORTE PDO PARA MÓDULO ASCENSORES (MIGRADO DE LOGÍSTICA) ---
    $dsn = "mysql:host=$servidor;dbname=$base_datos;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
    ];
    $pdo = new PDO($dsn, $usuario, $password, $options);
    $pdo->exec("SET time_zone = '-03:00';");
    
} catch (Exception $e) {
    // Si falla, muestra una caja roja con el motivo exacto
    die("<div style='background:#f8d7da; padding:20px; border:1px solid #f5c6cb; color:#721c24; margin:20px; border-radius:5px;'>
         <strong>Error crítico de conexión a la Base de Datos:</strong><br>" . $e->getMessage() . 
         "</div>");
}
?>