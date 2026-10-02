<?php
// Archivo temporal para migrar tablas de logística a actis
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Iniciando Migración de Ascensores...</h2>";

// Conexión LOGISTICA (Origen)
$log_user = "u415354546_logistica";
$log_pass = "l0g15t1C@!";
$log_db = "u415354546_logistica";

try {
    $pdo_log = new PDO("mysql:host=localhost;dbname=$log_db;charset=utf8mb4", $log_user, $log_pass);
    $pdo_log->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Conexión a Logística OK.<br>";
} catch (Exception $e) {
    die("Error conectando a Logística: " . $e->getMessage());
}

// Conexión ACTIS (Destino)
require_once 'includes/conexion.php';
// $pdo ya existe desde actis/includes/conexion.php
echo "Conexión a Actis OK.<br>";

$tablas_a_migrar = ['empresas_mantenimiento', 'ascensores', 'ascensor_incidencias'];

// Deshabilitar FK checks para evitar errores de orden de creación
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

foreach ($tablas_a_migrar as $tabla) {
    echo "<h3>Migrando tabla: $tabla</h3>";
    
    // 1. Obtener estructura
    try {
        $stmt = $pdo_log->query("SHOW CREATE TABLE `$tabla`");
        $create_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($create_row && isset($create_row['Create Table'])) {
            $create_sql = $create_row['Create Table'];
            
            // 2. Crear en destino (si no existe)
            // Cambiamos CREATE TABLE por CREATE TABLE IF NOT EXISTS
            $create_sql = str_replace("CREATE TABLE", "CREATE TABLE IF NOT EXISTS", $create_sql);
            
            // Eliminar constraints de llaves foráneas para evitar el error 150 por diferencias estructurales (ej: tabla usuarios)
            $create_sql = preg_replace('/,\s*CONSTRAINT\s+`[^`]+`\s+FOREIGN KEY\s*\([^)]+\)\s*REFERENCES\s+`[^`]+`\s*\([^)]+\)(?:\s+ON DELETE[^,\n]+)?(?:\s+ON UPDATE[^,\n]+)?/i', '', $create_sql);
            
            $pdo->exec($create_sql);
            echo "Estructura de $tabla creada en Actis.<br>";
            
            // 3. Copiar datos
            $datos = $pdo_log->query("SELECT * FROM `$tabla`")->fetchAll(PDO::FETCH_ASSOC);
            if (count($datos) > 0) {
                $columnas = array_keys($datos[0]);
                $columnas_str = implode("`, `", $columnas);
                $placeholders = implode(", ", array_fill(0, count($columnas), "?"));
                
                $insert_sql = "INSERT IGNORE INTO `$tabla` (`$columnas_str`) VALUES ($placeholders)";
                $stmt_insert = $pdo->prepare($insert_sql);
                
                $count = 0;
                foreach ($datos as $fila) {
                    $stmt_insert->execute(array_values($fila));
                    $count++;
                }
                echo "Copiados $count registros a $tabla.<br>";
            } else {
                echo "La tabla $tabla está vacía en Logística.<br>";
            }
        }
    } catch (Exception $e) {
        echo "<span style='color:red'>Error procesando tabla $tabla: " . $e->getMessage() . "</span><br>";
    }
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "<h2>¡Migración Finalizada! Ya puedes eliminar este archivo (migrar_ascensores.php).</h2>";
?>
