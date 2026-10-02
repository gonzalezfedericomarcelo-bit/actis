<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Para pruebas desde Tampermonkey
require_once 'includes/conexion.php';

if (!isset($_GET['dni']) || empty(trim($_GET['dni']))) {
    echo json_encode(['error' => 'DNI no proporcionado']);
    exit;
}

$dni = $conexion->real_escape_string(trim($_GET['dni']));
$hoy = date('Y-m-d');

// Buscamos si el paciente tiene un turno hoy (que no esté cancelado ni atendido)
// Nota: Puedes ajustar los estados permitidos según las reglas de negocio. Asumimos Pendiente o Autorizado.
$sql = "SELECT t.id, t.numero_turno, t.servicio, t.profesional, t.hora_turno, t.estado 
        FROM turnos t 
        INNER JOIN pacientes p ON t.paciente_id = p.id 
        WHERE p.dni = '$dni' 
          AND t.fecha_turno = '$hoy' 
          AND t.estado IN ('Pendiente', 'Autorizado')
        ORDER BY t.hora_turno ASC";

$result = $conexion->query($sql);

if ($result && $result->num_rows > 0) {
    $turnos = [];
    while ($row = $result->fetch_assoc()) {
        $turnos[] = [
            'id' => $row['numero_turno'],
            'servicio' => $row['servicio'],
            'profesional' => $row['profesional'],
            'hora' => $row['hora_turno'],
            'estado' => $row['estado']
        ];
    }
    echo json_encode([
        'tiene_turno' => true,
        'turnos' => $turnos
    ]);
} else {
    $origen = isset($_GET['origen']) ? strtoupper($_GET['origen']) : 'TOTEM';

    if ($origen === 'VENTANILLA') {
        $q_demanda = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'ventanilla_demanda_espontanea_habilitada'");
    } else {
        $q_demanda = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'totem_demanda_espontanea_habilitado'");
    }
    $demanda_habilitada = ($q_demanda && $q_demanda->num_rows > 0) ? ((int)$q_demanda->fetch_assoc()['estado'] >= 1) : true;

    // Verificar si tiene turno cancelado hoy
    $sql_cancelado = "SELECT t.hora_turno, t.servicio, t.profesional, t.comentario_paciente, t.creado_el, u.nombre_completo AS creador_nombre, t.operador_externo 
                      FROM turnos t 
                      INNER JOIN pacientes p ON t.paciente_id = p.id 
                      LEFT JOIN usuarios u ON t.usuario_creador_id = u.id
                      WHERE p.dni = '$dni' 
                        AND t.fecha_turno = '$hoy' 
                        AND t.estado = 'Cancelado'
                      ORDER BY t.hora_turno DESC LIMIT 1";
    
    $res_cancelado = $conexion->query($sql_cancelado);
    
    $turno_cancelado_data = null;
    if ($res_cancelado && $res_cancelado->num_rows > 0) {
        $row_c = $res_cancelado->fetch_assoc();
        $operador = !empty($row_c['operador_externo']) ? $row_c['operador_externo'] : (!empty($row_c['creador_nombre']) ? $row_c['creador_nombre'] : 'Sistema');
        $turno_cancelado_data = [
            'servicio' => $row_c['servicio'],
            'profesional' => $row_c['profesional'],
            'hora' => $row_c['hora_turno'],
            'motivo' => $row_c['comentario_paciente'],
            'cargado_por' => $operador,
            'cargado_el' => date('d/m/Y H:i', strtotime($row_c['creado_el']))
        ];
    }

    echo json_encode([
        'tiene_turno' => false,
        'turnos' => [],
        'demanda_espontanea_habilitada' => $demanda_habilitada,
        'turno_cancelado' => $turno_cancelado_data
    ]);
}
?>
