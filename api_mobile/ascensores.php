<?php
header('Content-Type: application/json');
require_once '../includes/conexion.php';

try {
    $sql = "SELECT a.*, 
            e.nombre as nombre_empresa,
            (SELECT COUNT(*) FROM ascensor_incidencias WHERE id_ascensor = a.id_ascensor AND estado != 'resuelto') as fallas_activas,
            (SELECT MAX(fecha_reporte) FROM ascensor_incidencias WHERE id_ascensor = a.id_ascensor) as ultima_falla,
            (SELECT titulo FROM ascensor_incidencias WHERE id_ascensor = a.id_ascensor ORDER BY fecha_reporte DESC LIMIT 1) as ultimo_motivo
            FROM ascensores a 
            LEFT JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa 
            ORDER BY a.nombre ASC";
    
    $ascensores = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    // Mapeo para estructurar el JSON para la app Flutter
    $data = [];
    foreach ($ascensores as $a) {
        $estado = 'Operativo';
        if ($a['fallas_activas'] > 0) {
            $estado = 'Falla';
        }

        $data[] = [
            'id' => $a['id_ascensor'],
            'nombre' => $a['nombre'],
            'estado' => $estado,
            'ultima_revision' => $a['ultima_falla'] ? date('Y-m-d', strtotime($a['ultima_falla'])) : 'Sin registro',
            'marca' => $a['nombre_empresa'] ? $a['nombre_empresa'] : 'Sin Asignar',
            'ubicacion' => $a['ubicacion'],
            'nro_serie' => $a['nro_serie']
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
