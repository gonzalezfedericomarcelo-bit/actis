<?php
/**
 * api_check_session_staff.php
 * Verifica si hay una sesión ACTIS activa.
 * Responde: {"active": true/false}
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');
session_start();
$active = isset($_SESSION['usuario_id']);
$url_redirect = 'dashboard.php';

if ($active) {
    require_once 'includes/conexion.php';
    $res_p = $conexion->query("SELECT p.nombre_permiso FROM rol_permiso rp JOIN permisos p ON rp.permiso_id = p.id WHERE rp.rol_id = " . (int)$_SESSION['rol_id']);
    $mis_permisos = [];
    if ($res_p) {
        while($p = $res_p->fetch_assoc()){
            $mis_permisos[] = $p['nombre_permiso'];
        }
    }
    
    $modulos_activos = [];
    if (in_array('modulo_totem_ascensores', $mis_permisos)) $modulos_activos[] = 'totem_ascensores.php';
    if (in_array('modulo_totem_pantalla', $mis_permisos)) $modulos_activos[] = 'dashboard_totem.php';
    if (in_array('modulo_totem_descargas', $mis_permisos)) $modulos_activos[] = 'descargas.php';
    
    // Si tiene algún permiso de configuración, su módulo base es el staff dashboard
    if (in_array('modulo_totem_admin', $mis_permisos) || in_array('modulo_totem_estado', $mis_permisos) || in_array('modulo_totem_papel', $mis_permisos) || in_array('modulo_totem_diseno', $mis_permisos) || in_array('modulo_totem_seguridad', $mis_permisos)) {
        $modulos_activos[] = 'admin_totem_staff.php';
    }
    
    $modulos_activos = array_unique($modulos_activos);
    
    if (count($modulos_activos) > 1) {
        $url_redirect = 'admin_totem_staff.php';
    } elseif (count($modulos_activos) === 1) {
        $url_redirect = $modulos_activos[0];
    }
}

echo json_encode(['active' => $active, 'redirect' => $url_redirect]);
