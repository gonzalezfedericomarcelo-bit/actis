<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

require_once '../includes/conexion.php';

try {
    $id_ascensor = filter_input(INPUT_GET, 'id_ascensor', FILTER_VALIDATE_INT);
    if (!$id_ascensor) {
        echo json_encode(["status" => "error", "message" => "ID de ascensor inválido"]);
        exit;
    }

    $sql_h = "SELECT i.*, u.nombre_completo as usuario_reporta 
              FROM ascensor_incidencias i 
              LEFT JOIN usuarios u ON i.id_usuario_reporta = u.id 
              WHERE i.id_ascensor = ? 
              ORDER BY i.fecha_reporte DESC";
    $stmt_h = $pdo->prepare($sql_h);
    $stmt_h->execute([$id_ascensor]);
    $historial = $stmt_h->fetchAll(PDO::FETCH_ASSOC);

    foreach ($historial as &$h) {
        // Enlace para el PDF en Actis
        $h['url_pdf'] = "https://federicogonzalez.net/actis/ascensor_pdf.php?id=" . $h['id_incidencia'];
        
        $sql_v = "SELECT v.id_visita, v.fecha_visita, v.tecnico_nombre, v.descripcion_trabajo, 
                         r.nombre_completo as guardia 
                  FROM ascensor_visitas_tecnicas v
                  LEFT JOIN usuarios r ON v.id_receptor = r.id
                  WHERE v.id_incidencia = ? ORDER BY v.fecha_visita ASC";
        $stmt_v = $pdo->prepare($sql_v);
        $stmt_v->execute([$h['id_incidencia']]);
        $h['visitas'] = $stmt_v->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($h);

    echo json_encode(["status" => "success", "data" => $historial]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Error interno: " . $e->getMessage()]);
}
?>
