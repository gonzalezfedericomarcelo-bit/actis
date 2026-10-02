<?php
require_once 'includes/conexion.php';

// Leer el JSON entrante
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !is_array($data)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing data']);
    exit;
}

$updated_count = 0;

// Preparar el UPDATE
// Se busca por fecha, hora, servicio y profesional. Solo se actualiza si numero_turno está vacío o es NULL.
$stmt = $conexion->prepare("
    UPDATE turnos 
    SET numero_turno = ? 
    WHERE fecha_turno = ? 
      AND hora_turno LIKE ? 
      AND servicio = ? 
      AND profesional = ? 
      AND (numero_turno IS NULL OR numero_turno = '')
");

if ($stmt) {
    foreach ($data as $turno) {
        if (!isset($turno['numero_turno']) || empty(trim($turno['numero_turno']))) {
            continue; // No hay número para sincronizar
        }
        
        $nro = trim($turno['numero_turno']);
        
        // Formatear la fecha de DD/MM/YYYY a YYYY-MM-DD
        $fecha_raw = trim($turno['fecha']);
        $fecha_parts = explode('/', $fecha_raw);
        if (count($fecha_parts) == 3) {
            $fecha_sql = $fecha_parts[2] . '-' . $fecha_parts[1] . '-' . $fecha_parts[0];
        } else {
            $fecha_sql = $fecha_raw; // Fallback
        }
        
        // La hora a veces viene como HH:MM, a veces como HH:MM:SS. Usaremos LIKE 'HH:MM%' para mayor seguridad.
        $hora_raw = trim($turno['hora']);
        $hora_like = substr($hora_raw, 0, 5) . '%'; // Agarra "HH:MM"
        
        $servicio = trim($turno['servicio']);
        $profesional = trim($turno['profesional']);
        
        $stmt->bind_param("sssss", $nro, $fecha_sql, $hora_like, $servicio, $profesional);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $updated_count += $stmt->affected_rows;
        }
    }
    $stmt->close();
}

echo json_encode([
    'status' => 'success',
    'message' => "Sincronizacion completada",
    'updated' => $updated_count
]);
?>
