<?php
/**
 * api_limpieza.php
 * Procesa las peticiones AJAX del Tótem para el personal de limpieza.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

require_once 'includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$action = $_POST['action'];

if ($action === 'buscar_dni') {
    $dni = $conexion->real_escape_string(trim($_POST['dni'] ?? ''));
    if (empty($dni)) {
        echo json_encode(['success' => false, 'message' => 'DNI vacío.']);
        exit;
    }

    // Buscar personal activo
    $res = $conexion->query("SELECT id, nombre_completo FROM personal_limpieza WHERE dni = '$dni' AND estado = 1");
    if ($res && $res->num_rows > 0) {
        $personal = $res->fetch_assoc();
        $personal_id = $personal['id'];
        $fecha_hoy = date('Y-m-d');

        // Chequear estado de asistencia hoy
        $res_asistencia = $conexion->query("SELECT id, hora_entrada, hora_salida FROM asistencia_limpieza WHERE personal_id = $personal_id AND fecha = '$fecha_hoy'");
        
        $estado = 'sin_entrada';
        $asistencia_id = 0;

        if ($res_asistencia && $res_asistencia->num_rows > 0) {
            $asistencia = $res_asistencia->fetch_assoc();
            $asistencia_id = $asistencia['id'];
            if (!empty($asistencia['hora_salida'])) {
                $estado = 'finalizado';
            } else {
                $estado = 'sin_salida';
            }
        }

        echo json_encode([
            'success' => true,
            'nombre' => $personal['nombre_completo'],
            'estado_asistencia' => $estado,
            'personal_id' => $personal_id,
            'asistencia_id' => $asistencia_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'DNI no encontrado o inactivo.']);
    }
    exit;
}

if ($action === 'registrar_asistencia') {
    $personal_id = (int)($_POST['personal_id'] ?? 0);
    $tipo = $_POST['tipo'] ?? ''; // 'entrada' o 'salida'
    $firma = $_POST['firma'] ?? ''; // Base64
    $fecha_hoy = date('Y-m-d');
    $hora_ahora = date('H:i:s');

    if ($personal_id <= 0 || empty($tipo) || empty($firma)) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios o firma.']);
        exit;
    }

    $firma = $conexion->real_escape_string($firma);

    if ($tipo === 'entrada') {
        // Registrar Entrada
        $sql = "INSERT INTO asistencia_limpieza (personal_id, fecha, hora_entrada, firma_entrada) 
                VALUES ($personal_id, '$fecha_hoy', '$hora_ahora', '$firma')";
        if ($conexion->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Entrada registrada con éxito.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al registrar entrada: ' . $conexion->error]);
        }
    } elseif ($tipo === 'salida') {
        // Registrar Salida
        $sql = "UPDATE asistencia_limpieza 
                SET hora_salida = '$hora_ahora', firma_salida = '$firma' 
                WHERE personal_id = $personal_id AND fecha = '$fecha_hoy' AND hora_salida IS NULL";
        if ($conexion->query($sql)) {
            if ($conexion->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Salida registrada con éxito.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No se encontró una entrada abierta para registrar la salida hoy.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al registrar salida: ' . $conexion->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Tipo de registro inválido.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
