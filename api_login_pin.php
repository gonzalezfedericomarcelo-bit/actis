<?php
/**
 * api_login_pin.php
 * Valida un PIN de 4 dígitos (u otra longitud) para iniciar sesión en la administración del Tótem.
 * POST: pin
 * Responde: {"success": true/false, "message": "..."}
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');
session_start();

require_once 'includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$pin = $_POST['pin'] ?? '';

if (empty($pin)) {
    echo json_encode(['success' => false, 'message' => 'Ingrese el PIN.']);
    exit;
}

// 1. VERIFICAR PIN DE LIMPIEZA
try {
    $res_limpieza = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_limpieza'");
    if ($res_limpieza && $res_limpieza->num_rows > 0) {
        $pin_limpieza = $res_limpieza->fetch_assoc()['estado'];
        if ($pin === $pin_limpieza) {
            echo json_encode(['success' => true, 'redirect' => 'totem_limpieza.php']);
            exit;
        }
    }
} catch (Exception $e) {
    // Ignorar y seguir con validación normal
}


// Escapar el PIN
$pin = $conexion->real_escape_string($pin);

// Buscar un usuario con ese PIN (que esté activo)
$sql = "SELECT id, nombre_completo, rol_id FROM usuarios WHERE pin_totem = '$pin' AND estado = 1";
$res = $conexion->query($sql);

if ($res && $res->num_rows > 0) {
    // PIN correcto. Iniciar sesión.
    $row = $res->fetch_assoc();
    $_SESSION['usuario_id'] = $row['id'];
    $_SESSION['nombre']     = $row['nombre_completo'];
    $_SESSION['rol_id']     = $row['rol_id'];
    
    // Obtener permisos
    $res_p = $conexion->query("SELECT p.nombre_permiso FROM rol_permiso rp JOIN permisos p ON rp.permiso_id = p.id WHERE rp.rol_id = " . (int)$row['rol_id']);
    $mis_permisos = [];
    if ($res_p) {
        while($p = $res_p->fetch_assoc()){
            $mis_permisos[] = $p['nombre_permiso'];
        }
    }
    
    // Determinar a qué pantalla va directo (Ruteo Dinámico Real)
    $url_redirect = 'dashboard.php'; // fallback general
    
    // Identificamos a qué MÓDULOS DEL TÓTEM (sin menú web) tiene acceso este PIN.
    $modulos_activos = [];
    if (in_array('modulo_totem_ascensores', $mis_permisos)) $modulos_activos[] = 'totem_ascensores.php';
    if (in_array('modulo_totem_pantalla', $mis_permisos)) $modulos_activos[] = 'dashboard_totem.php';
    if (in_array('modulo_totem_descargas', $mis_permisos)) $modulos_activos[] = 'descargas.php';
    
    // Si tiene algún permiso de configuración, su módulo base es el staff dashboard
    if (in_array('modulo_totem_admin', $mis_permisos) || in_array('modulo_totem_estado', $mis_permisos) || in_array('modulo_totem_papel', $mis_permisos) || in_array('modulo_totem_diseno', $mis_permisos) || in_array('modulo_totem_seguridad', $mis_permisos)) {
        $modulos_activos[] = 'admin_totem_staff.php';
    }
    
    // Quitamos duplicados por si acaso
    $modulos_activos = array_unique($modulos_activos);
    
    // Si tiene MÁS DE UNO, necesita el menú para elegir.
    if (count($modulos_activos) > 1) {
        $url_redirect = 'admin_totem_staff.php';
    } 
    // Si tiene EXACTAMENTE UNO, va directo sin pasar por el menú.
    elseif (count($modulos_activos) === 1) {
        $url_redirect = $modulos_activos[0];
    }

    echo json_encode(['success' => true, 'redirect' => $url_redirect]);
} else {
    echo json_encode(['success' => false, 'message' => 'PIN incorrecto o usuario inactivo.']);
}
?>
