<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/conexion.php'; 

try {
    $output = [];
    
    // 1. Delete test incidencias for ascensor 4, 7, 9
    // Due to ON DELETE CASCADE on ascensor_visitas_tecnicas, this will auto-delete visits!
    // But wait, ascensor_historial does not have ON DELETE CASCADE in ACTIS (we just created it without FKs)
    
    // Delete from ascensor_historial for test incidencias first
    $stmt = $pdo->query("SELECT id_incidencia FROM ascensor_incidencias WHERE id_ascensor IN (4, 7, 9)");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($ids) > 0) {
        $ids_str = implode(',', $ids);
        $pdo->exec("DELETE FROM ascensor_historial WHERE id_incidencia IN ($ids_str)");
        $output['historial_deleted'] = "Historiales de prueba eliminados.";
    }
    
    // Now delete incidencias
    $deleted = $pdo->exec("DELETE FROM ascensor_incidencias WHERE id_ascensor IN (4, 7, 9)");
    $output['incidencias_deleted'] = "$deleted incidencias de prueba eliminadas.";

    // 2. Copiar firmas de tecnicos
    $src_dir = "../logistica/uploads/firmas_tecnicos/";
    $dst_dir = __DIR__ . "/uploads/firmas_tecnicos/";
    
    if (!is_dir($dst_dir)) {
        mkdir($dst_dir, 0777, true);
    }
    
    $copied = 0;
    if (is_dir($src_dir)) {
        $files = scandir($src_dir);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                if (copy($src_dir . $file, $dst_dir . $file)) {
                    $copied++;
                }
            }
        }
    }
    $output['firmas_copiadas'] = "$copied firmas de técnicos migradas.";
    
    // Suicidio del script
    unlink(__FILE__);
    $output['limpieza'] = "Script temporal destruido.";

    echo json_encode(["status" => "success", "data" => $output]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
