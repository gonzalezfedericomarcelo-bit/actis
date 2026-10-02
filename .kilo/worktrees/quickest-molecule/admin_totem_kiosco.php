<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header('Location: index.php'); exit; }
require_once 'includes/conexion.php';

// Obtener permisos del usuario para el Tótem
$mis_permisos = [];
if (isset($_SESSION['rol_id'])) {
    $res_p = $conexion->query("SELECT p.nombre_permiso FROM rol_permiso rp JOIN permisos p ON rp.permiso_id = p.id WHERE rp.rol_id = " . (int)$_SESSION['rol_id']);
    if ($res_p) {
        while($p = $res_p->fetch_assoc()){
            $mis_permisos[] = $p['nombre_permiso'];
        }
    }
}

// Variables de permisos booleanas
$tiene_admin = in_array('modulo_totem_admin', $mis_permisos);

$tiene_estado = $tiene_admin || in_array('modulo_totem_estado', $mis_permisos);
$tiene_papel = $tiene_admin || in_array('modulo_totem_papel', $mis_permisos);
$tiene_diseno = $tiene_admin || in_array('modulo_totem_diseno', $mis_permisos);
$tiene_seguridad = $tiene_admin || in_array('modulo_totem_seguridad', $mis_permisos);

// BLOQUEO TOTAL SI NO TIENE NINGÚN PERMISO
if (!$tiene_estado && !$tiene_papel && !$tiene_diseno && !$tiene_seguridad && !$tiene_admin) {
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Acceso Denegado</title><style>body{font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#0f172a; color:#fff; text-align:center; padding-top:100px;} .box{background:rgba(255,255,255,0.1); border-radius:16px; padding:40px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); max-width:500px; margin:auto;} h1{color:#ef4444;}</style></head><body><div class='box'><h1>⚠️ Acceso Denegado</h1><p>Tu rol de usuario no tiene permisos para acceder a la administración del Tótem.</p><br><a href='admin_totem_staff.php' style='display:inline-block; padding:10px 20px; background:#38bdf8; color:#0f172a; text-decoration:none; border-radius:8px; font-weight:bold;'>Volver al Dashboard</a></div></body></html>";
    exit;
}

// Lógica de guardado (identica a admin_totem.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cambiar_estado'])) {
        $nuevo_estado = $conexion->real_escape_string($_POST['estado_totem']);
        $conexion->query("UPDATE totem_config SET estado = '$nuevo_estado' WHERE tipo = 'estado_manual'");
        header('Location: admin_totem_kiosco.php?msg=estado'); exit;
    }
    if (isset($_POST['guardar_horarios'])) {
        $apertura = $conexion->real_escape_string($_POST['hora_apertura']);
        $cierre = $conexion->real_escape_string($_POST['hora_cierre']);
        $conexion->query("UPDATE totem_config SET estado = '$apertura' WHERE tipo = 'hora_apertura'");
        $conexion->query("UPDATE totem_config SET estado = '$cierre' WHERE tipo = 'hora_cierre'");
        header('Location: admin_totem_kiosco.php?msg=horarios'); exit;
    }
    if (isset($_POST['guardar_reinicio'])) {
        $hora_reinicio = $conexion->real_escape_string($_POST['hora_reinicio_totem']);
        $conexion->query("UPDATE totem_config SET estado = '$hora_reinicio' WHERE tipo = 'hora_reinicio_totem'");
        header('Location: admin_totem_kiosco.php?msg=reinicio_actualizado'); exit;
    }
    if (isset($_POST['forzar_reinicio'])) {
        $conexion->query("UPDATE totem_config SET estado = '1' WHERE tipo = 'forzar_reinicio_totem'");
        header('Location: admin_totem_kiosco.php?msg=reinicio_forzado'); exit;
    }
    if (isset($_POST['guardar_correos'])) {
        $email_soporte = $conexion->real_escape_string(trim($_POST['email_soporte']));
        $email_papel = $conexion->real_escape_string(trim($_POST['email_papel']));
        $alerta_min = (int)$_POST['alerta_min_papel'];
        $alerta_min_ven = (int)$_POST['alerta_min_papel_ventanilla'];
        $bloqueo_min = (int)$_POST['bloqueo_min_papel'];
        $bloqueo_min_ven = (int)$_POST['bloqueo_min_papel_ventanilla'];

        $conexion->query("UPDATE totem_config SET estado = '$email_soporte' WHERE tipo = 'email_soporte'");
        $conexion->query("UPDATE totem_config SET estado = '$email_papel' WHERE tipo = 'email_papel'");
        $conexion->query("UPDATE totem_config SET estado = '$alerta_min' WHERE tipo = 'alerta_min_papel'");
        $conexion->query("UPDATE totem_config SET estado = '$alerta_min_ven' WHERE tipo = 'alerta_min_papel_ventanilla'");
        $conexion->query("UPDATE totem_config SET estado = '$bloqueo_min' WHERE tipo = 'bloqueo_min_papel'");
        $conexion->query("UPDATE totem_config SET estado = '$bloqueo_min_ven' WHERE tipo = 'bloqueo_min_papel_ventanilla'");
        header('Location: admin_totem_kiosco.php?msg=correos'); exit;
    }
    if (isset($_POST['resetear_papel'])) {
        $conexion->query("UPDATE totem_config SET estado = '0' WHERE tipo = 'tickets_impresos'");
        $conexion->query("INSERT INTO historial_rollos (origen) VALUES ('TOTEM')");
        header('Location: admin_totem_kiosco.php?msg=papel_reseteado'); exit;
    }
    if (isset($_POST['resetear_papel_ventanilla'])) {
        $conexion->query("UPDATE totem_config SET estado = '0' WHERE tipo = 'tickets_impresos_ventanilla'");
        $conexion->query("INSERT INTO historial_rollos (origen) VALUES ('VENTANILLA')");
        header('Location: admin_totem_kiosco.php?msg=papel_reseteado_ven'); exit;
    }
    if (isset($_POST['toggle_silenciar'])) {
        $tipo = $conexion->real_escape_string($_POST['tipo_silencio']);
        $estado_actual = (int)$_POST['estado_actual'];
        $nuevo_estado = $estado_actual === 1 ? '0' : '1';
        $conexion->query("UPDATE totem_config SET estado = '$nuevo_estado' WHERE tipo = '$tipo'");
        header('Location: admin_totem_kiosco.php?msg=silencio_toggled'); exit;
    }
    if (isset($_POST['guardar_tickets_manual'])) {
        $cantidad_manual = (int)$_POST['cantidad_tickets_manual'];
        $conexion->query("UPDATE totem_config SET estado = '$cantidad_manual' WHERE tipo = 'tickets_impresos'");
        header('Location: admin_totem_kiosco.php?msg=tickets_manual'); exit;
    }
    if (isset($_POST['guardar_tickets_manual_ventanilla'])) {
        $cantidad_manual = (int)$_POST['cantidad_tickets_manual_ventanilla'];
        $conexion->query("UPDATE totem_config SET estado = '$cantidad_manual' WHERE tipo = 'tickets_impresos_ventanilla'");
        header('Location: admin_totem_kiosco.php?msg=tickets_manual_ven'); exit;
    }
    if (isset($_POST['guardar_capacidad_manual'])) {
        $capacidad_nueva = (int)$_POST['capacidad_rollo_manual'];
        $conexion->query("UPDATE totem_config SET estado = '$capacidad_nueva' WHERE tipo = 'capacidad_rollo'");
        header('Location: admin_totem_kiosco.php?msg=capacidad_manual'); exit;
    }
    if (isset($_POST['guardar_capacidad_manual_ventanilla'])) {
        $capacidad_nueva = (int)$_POST['capacidad_rollo_manual_ventanilla'];
        $conexion->query("UPDATE totem_config SET estado = '$capacidad_nueva' WHERE tipo = 'capacidad_rollo_ventanilla'");
        header('Location: admin_totem_kiosco.php?msg=capacidad_manual_ven'); exit;
    }
    if (isset($_POST['toggle_header_footer'])) {
        $nuevo_estado = (int)$_POST['toggle_header_footer_val'];
        $conexion->query("UPDATE totem_config SET estado = '$nuevo_estado' WHERE tipo = 'mostrar_header_footer'");
        header('Location: admin_totem_kiosco.php?msg=header_footer_toggled'); exit;
    }
    if (isset($_POST['guardar_pines_seguridad'])) {
        $pin_soporte = $conexion->real_escape_string(trim($_POST['pin_soporte']));
        $pin_rollo = $conexion->real_escape_string(trim($_POST['pin_rollo']));
        $pin_rollo_ventanilla = $conexion->real_escape_string(trim($_POST['pin_rollo_ventanilla']));
        $pin_dem_totem = $conexion->real_escape_string(trim($_POST['pin_demanda_espontanea_totem']));
        $pin_dem_ventanilla = $conexion->real_escape_string(trim($_POST['pin_demanda_espontanea_ventanilla']));
        $pin_dashboard_staff = $conexion->real_escape_string(trim($_POST['pin_dashboard_staff']));
        
        $conexion->query("UPDATE totem_config SET estado = '$pin_soporte' WHERE tipo = 'pin_soporte'");
        $conexion->query("UPDATE totem_config SET estado = '$pin_rollo' WHERE tipo = 'pin_rollo'");
        $conexion->query("UPDATE totem_config SET estado = '$pin_rollo_ventanilla' WHERE tipo = 'pin_rollo_ventanilla'");
        $conexion->query("UPDATE totem_config SET estado = '$pin_dem_totem' WHERE tipo = 'pin_demanda_espontanea_totem'");
        $conexion->query("UPDATE totem_config SET estado = '$pin_dem_ventanilla' WHERE tipo = 'pin_demanda_espontanea_ventanilla'");
        $conexion->query("UPDATE totem_config SET estado = '$pin_dashboard_staff' WHERE tipo = 'pin_dashboard_staff'");
        
        if (isset($_POST['totem_demanda_espontanea_habilitado'])) {
            $demanda_hab = (int)$_POST['totem_demanda_espontanea_habilitado'];
            $conexion->query("UPDATE totem_config SET estado = '$demanda_hab' WHERE tipo = 'totem_demanda_espontanea_habilitado'");
        }
        if (isset($_POST['ventanilla_demanda_espontanea_habilitada'])) {
            $demanda_hab_ven = (int)$_POST['ventanilla_demanda_espontanea_habilitada'];
            $conexion->query("UPDATE totem_config SET estado = '$demanda_hab_ven' WHERE tipo = 'ventanilla_demanda_espontanea_habilitada'");
        }
        header('Location: admin_totem_kiosco.php?msg=pines_actualizados'); exit;
    }
    if (isset($_POST['agregar_feriado'])) {
        $fecha_feriado = $conexion->real_escape_string($_POST['fecha_feriado']);
        if(!empty($fecha_feriado)) $conexion->query("INSERT INTO totem_config (tipo, fecha) VALUES ('feriado', '$fecha_feriado')");
        header('Location: admin_totem_kiosco.php?msg=feriado'); exit;
    }
    if (isset($_POST['toggle_simulacion_test'])) {
        $estado_actual = (int)$_POST['estado_actual'];
        $nuevo_estado = $estado_actual === 1 ? '0' : '1';
        $conexion->query("INSERT IGNORE INTO `totem_config` (`tipo`, `estado`) SELECT 'simular_sin_papel_test', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM totem_config WHERE tipo = 'simular_sin_papel_test');");
        $conexion->query("UPDATE totem_config SET estado = '$nuevo_estado' WHERE tipo = 'simular_sin_papel_test'");
        header('Location: admin_totem_kiosco.php?msg=simulacion_toggled'); exit;
    }

    if (isset($_POST['guardar_tipografia'])) {
        $campos_tipografia = [
            'fs_btn_titulo'    => [5, 60, 22],
            'fs_btn_desc'      => [5, 60, 14],
            'fs_btn_icono'     => [5, 60, 30],
            'fs_widget_titulo' => [5, 60, 20],
            'fs_widget_desc'   => [5, 60, 14],
            'fs_reloj_hora'    => [5, 60, 32],
            'fs_reloj_fecha'   => [5, 60, 11],
            'fs_modal_titulo'  => [5, 60, 40],
            'fs_header_nombre' => [5, 60, 28],
            'fs_header_dir'    => [5, 60, 14],
        ];
        foreach ($campos_tipografia as $campo => [$min, $max, $def]) {
            $val = isset($_POST[$campo]) ? max($min, min($max, (int)$_POST[$campo])) : $def;
            $val = (int)$val;
            $existe = $conexion->query("SELECT id FROM totem_config WHERE tipo = '$campo' LIMIT 1");
            if ($existe && $existe->num_rows > 0) {
                $conexion->query("UPDATE totem_config SET estado = '$val' WHERE tipo = '$campo'");
            } else {
                $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('$campo', '$val')");
            }
        }
        header('Location: admin_totem_kiosco.php?msg=tipografia_guardada'); exit;
    }
    
    if (isset($_POST['agregar_carrusel'])) {
        $titulo = $conexion->real_escape_string($_POST['titulo_carrusel']);
        $descripcion = $conexion->real_escape_string($_POST['descripcion_carrusel']);
        $orden = (int)$_POST['orden_carrusel'];
        
        $upload_dir = 'uploads/carrusel/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        if (isset($_FILES['imagen_carrusel'])) {
            $total = is_array($_FILES['imagen_carrusel']['name']) ? count($_FILES['imagen_carrusel']['name']) : 1;
            
            for ($i = 0; $i < $total; $i++) {
                $error = is_array($_FILES['imagen_carrusel']['error']) ? $_FILES['imagen_carrusel']['error'][$i] : $_FILES['imagen_carrusel']['error'];
                if ($error == 0) {
                    $name = is_array($_FILES['imagen_carrusel']['name']) ? $_FILES['imagen_carrusel']['name'][$i] : $_FILES['imagen_carrusel']['name'];
                    $tmp_name = is_array($_FILES['imagen_carrusel']['tmp_name']) ? $_FILES['imagen_carrusel']['tmp_name'][$i] : $_FILES['imagen_carrusel']['tmp_name'];
                    
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $filename = time() . '_' . uniqid() . '.' . $ext;
                    $dest = $upload_dir . $filename;
                    
                    if (move_uploaded_file($tmp_name, $dest)) {
                        $conexion->query("INSERT INTO totem_carrusel (imagen, titulo, descripcion, orden) VALUES ('$filename', '$titulo', '$descripcion', $orden)");
                    }
                }
            }
        }
        header('Location: admin_totem_kiosco.php?msg=carrusel_agregado'); exit;
    }
    
    if (isset($_POST['guardar_boton'])) {
        $id_boton = (int)$_POST['id_boton'];
        $titulo = $conexion->real_escape_string($_POST['titulo']);
        $bajada = $conexion->real_escape_string($_POST['bajada']);
        $icono = $conexion->real_escape_string($_POST['icono']);
        $color = $conexion->real_escape_string($_POST['color']);
        $orden = (int)$_POST['orden'];
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        $conexion->query("UPDATE totem_botones SET titulo='$titulo', bajada='$bajada', icono='$icono', color='$color', orden=$orden, activo=$activo WHERE id=$id_boton");
        header('Location: admin_totem_kiosco.php?msg=boton_guardado'); exit;
    }

    if (isset($_POST['agregar_boton_nuevo'])) {
        $conexion->query("INSERT INTO totem_botones (grupo, identificador, titulo, bajada, icono, color, accion_tipo, accion_data, orden, activo) VALUES ('widget', 'nuevo_" . time() . "', 'Nuevo Widget', 'Descripción', 'fa-star', '#cbd5e1', 'modal', '', 99, 1)");
        header('Location: admin_totem_kiosco.php?msg=boton_agregado'); exit;
    }
}

if (isset($_GET['eliminar_carrusel'])) {
    $id_eliminar = (int)$_GET['eliminar_carrusel'];
    $img_res = $conexion->query("SELECT imagen FROM totem_carrusel WHERE id = $id_eliminar");
    if ($img_res && $img_res->num_rows > 0) {
        $img_name = $img_res->fetch_assoc()['imagen'];
        $path = 'uploads/carrusel/' . $img_name;
        if (file_exists($path)) {
            unlink($path);
        }
        $conexion->query("DELETE FROM totem_carrusel WHERE id = $id_eliminar");
    }
    header('Location: admin_totem_kiosco.php?msg=carrusel_eliminado'); exit;
}

if (isset($_POST['agregar_documento'])) {
    $titulo = $conexion->real_escape_string($_POST['titulo_documento']);
    $cats_validas = ['recetas', 'odontologia', 'otro'];
    $categoria = in_array($_POST['categoria_documento'] ?? '', $cats_validas) ? $_POST['categoria_documento'] : 'otro';
    
    $upload_dir = 'uploads/documentos/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (isset($_FILES['archivo_documento']) && $_FILES['archivo_documento']['error'] == 0) {
        $name = $_FILES['archivo_documento']['name'];
        $tmp_name = $_FILES['archivo_documento']['tmp_name'];
        
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $filename = time() . '_' . uniqid() . '.' . $ext;
            $dest = $upload_dir . $filename;
            
            if (move_uploaded_file($tmp_name, $dest)) {
                $conexion->query("INSERT INTO totem_documentos (titulo, archivo, categoria, activo) VALUES ('$titulo', '$filename', '$categoria', 1)");
            }
        }
    }
    header('Location: admin_totem_kiosco.php?msg=documento_agregado'); exit;
}

if (isset($_POST['toggle_documento'])) {
    $id_doc = (int)$_POST['id_documento'];
    $activo = isset($_POST['activo_documento']) ? 1 : 0;
    $conexion->query("UPDATE totem_documentos SET activo=$activo WHERE id=$id_doc");
    header('Location: admin_totem_kiosco.php?msg=documento_actualizado'); exit;
}

if (isset($_GET['eliminar_documento'])) {
    $id_eliminar = (int)$_GET['eliminar_documento'];
    $doc_res = $conexion->query("SELECT archivo FROM totem_documentos WHERE id = $id_eliminar");
    if ($doc_res && $doc_res->num_rows > 0) {
        $doc_name = $doc_res->fetch_assoc()['archivo'];
        $path = 'uploads/documentos/' . $doc_name;
        if (file_exists($path)) {
            unlink($path);
        }
        $conexion->query("DELETE FROM totem_documentos WHERE id = $id_eliminar");
    }
    header('Location: admin_totem_kiosco.php?msg=documento_eliminado'); exit;
}

if (isset($_GET['eliminar'])) {
    $conexion->query("DELETE FROM totem_config WHERE id = ".(int)$_GET['eliminar']." AND tipo = 'feriado'");
    header('Location: admin_totem_kiosco.php'); exit;
}

// Lectura de estado
$estado_actual = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'estado_manual'")->fetch_assoc()['estado'] ?? 'automatico';
$feriados = $conexion->query("SELECT * FROM totem_config WHERE tipo = 'feriado' ORDER BY fecha ASC");
$hora_apertura = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'hora_apertura'")->fetch_assoc()['estado'] ?? '06:00';
$hora_cierre = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'hora_cierre'")->fetch_assoc()['estado'] ?? '20:00';
$hora_reinicio_totem = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'hora_reinicio_totem'")->fetch_assoc()['estado'] ?? '';
$tickets_impresos = (int)($conexion->query("SELECT estado FROM totem_config WHERE tipo = 'tickets_impresos'")->fetch_assoc()['estado'] ?? 0);
$capacidad_rollo = (int)($conexion->query("SELECT estado FROM totem_config WHERE tipo = 'capacidad_rollo'")->fetch_assoc()['estado'] ?? 120);
$porcentaje_papel = ($capacidad_rollo > 0) ? round((($capacidad_rollo - $tickets_impresos) / $capacidad_rollo) * 100) : 0;
$email_soporte_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'email_soporte'")->fetch_assoc()['estado'] ?? '';
$email_papel_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'email_papel'")->fetch_assoc()['estado'] ?? '';
$alerta_min_papel_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'alerta_min_papel'")->fetch_assoc()['estado'] ?? '20';
$bloqueo_min_papel_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'bloqueo_min_papel'")->fetch_assoc()['estado'] ?? '5';

$tickets_impresos_ven = (int)($conexion->query("SELECT estado FROM totem_config WHERE tipo = 'tickets_impresos_ventanilla'")->fetch_assoc()['estado'] ?? 0);
$capacidad_rollo_ven = (int)($conexion->query("SELECT estado FROM totem_config WHERE tipo = 'capacidad_rollo_ventanilla'")->fetch_assoc()['estado'] ?? 120);
$porcentaje_papel_ven = ($capacidad_rollo_ven > 0) ? round((($capacidad_rollo_ven - $tickets_impresos_ven) / $capacidad_rollo_ven) * 100) : 0;
$alerta_min_papel_ven_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'alerta_min_papel_ventanilla'")->fetch_assoc()['estado'] ?? '20';
$bloqueo_min_papel_ven_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'bloqueo_min_papel_ventanilla'")->fetch_assoc()['estado'] ?? '5';
$mostrar_hf_ven = (int)($conexion->query("SELECT estado FROM totem_config WHERE tipo = 'mostrar_header_footer'")->fetch_assoc()['estado'] ?? 0);
$silenciado_totem = (int)($conexion->query("SELECT estado FROM totem_config WHERE tipo = 'alertas_silenciadas'")->fetch_assoc()['estado'] ?? 0);
$silenciado_ven = (int)($conexion->query("SELECT estado FROM totem_config WHERE tipo = 'alertas_silenciadas_ventanilla'")->fetch_assoc()['estado'] ?? 0);
$pin_soporte_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_soporte'")->fetch_assoc()['estado'] ?? '35911';
$pin_rollo_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_rollo'")->fetch_assoc()['estado'] ?? '88888';
$pin_rollo_ventanilla_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_rollo_ventanilla'")->fetch_assoc()['estado'] ?? '77777';
$pin_dem_totem_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_demanda_espontanea_totem'")->fetch_assoc()['estado'] ?? '1234';
$pin_dem_ventanilla_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_demanda_espontanea_ventanilla'")->fetch_assoc()['estado'] ?? '77777';
$pin_dashboard_staff_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_dashboard_staff'")->fetch_assoc()['estado'] ?? '12345';
$totem_demanda_esp_hab = (int)($conexion->query("SELECT estado FROM totem_config WHERE tipo = 'totem_demanda_espontanea_habilitado'")->fetch_assoc()['estado'] ?? '1');
$ventanilla_demanda_esp_hab = (int)($conexion->query("SELECT estado FROM totem_config WHERE tipo = 'ventanilla_demanda_espontanea_habilitada'")->fetch_assoc()['estado'] ?? '1');
$simular_sin_papel_test = (int)($conexion->query("SELECT estado FROM totem_config WHERE tipo = 'simular_sin_papel_test'")->fetch_assoc()['estado'] ?? '0');

$admin_fs_defaults = [
    'fs_btn_titulo'    => 22,
    'fs_btn_desc'      => 14,
    'fs_btn_icono'     => 30,
    'fs_widget_titulo' => 20,
    'fs_widget_desc'   => 14,
    'fs_reloj_hora'    => 32,
    'fs_reloj_fecha'   => 11,
    'fs_modal_titulo'  => 40,
    'fs_header_nombre' => 28,
    'fs_header_dir'    => 14,
];
$admin_fs = [];
foreach ($admin_fs_defaults as $_ak => $_adef) {
    $_ar = $conexion->query("SELECT estado FROM totem_config WHERE tipo = '$_ak' ORDER BY id DESC LIMIT 1");
    $admin_fs[$_ak] = ($_ar && $_ar->num_rows > 0) ? (int)$_ar->fetch_assoc()['estado'] : $_adef;
}
$botones_lista = $conexion->query("SELECT * FROM totem_botones ORDER BY grupo ASC, orden ASC");
$documentos_lista = $conexion->query("SELECT * FROM totem_documentos ORDER BY id DESC");

$historial_totem = $conexion->query("SELECT COUNT(*) as c FROM historial_rollos WHERE origen = 'TOTEM'")->fetch_assoc()['c'] ?? 0;
$historial_ven = $conexion->query("SELECT COUNT(*) as c FROM historial_rollos WHERE origen = 'VENTANILLA'")->fetch_assoc()['c'] ?? 0;
$carrusel_slides = $conexion->query("SELECT * FROM totem_carrusel ORDER BY orden ASC, id DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kiosco - Configuración Tótem</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    * { box-sizing: border-box; }
    body {
        font-family: 'Poppins', sans-serif;
        background: #0f172a; /* Fondo oscuro Staff Dashboard */
        color: #fff; margin: 0; padding: 0; min-height: 100vh;
    }
    
    .kiosco-header {
        background: rgba(255,255,255,0.05); backdrop-filter: blur(12px);
        border-bottom: 1px solid rgba(255,255,255,0.1);
        padding: 20px 30px; display: flex; justify-content: space-between;
        align-items: center; position: sticky; top: 0; z-index: 100;
    }
    .kiosco-title {
        margin: 0; font-size: 1.5rem; font-weight: 900;
        display: flex; align-items: center; gap: 12px; color: #38bdf8;
    }
    .btn-volver {
        background: rgba(255,255,255,0.1); color: #fff;
        border: 1px solid rgba(255,255,255,0.2); padding: 12px 24px;
        border-radius: 12px; font-weight: 700; font-size: 1rem;
        text-decoration: none; display: flex; align-items: center; gap: 8px;
    }
    
    .kiosco-container {
        padding: 30px; max-width: 1400px; margin: 0 auto;
        display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px;
    }

    .k-card {
        background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1);
        border-radius: 20px; padding: 30px;
    }
    .k-card-title {
        font-size: 1.25rem; font-weight: 800; margin-top: 0; margin-bottom: 25px;
        color: #f1f5f9; display: flex; align-items: center; gap: 10px;
        border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px;
    }

    .k-group { margin-bottom: 20px; }
    .k-label { display: block; font-size: 0.85rem; color: #94a3b8; font-weight: 700; margin-bottom: 8px; text-transform: uppercase; }
    .k-input, .k-select {
        width: 100%; background: rgba(0,0,0,0.3); border: 2px solid rgba(255,255,255,0.1);
        color: #fff; padding: 15px 20px; border-radius: 12px;
        font-size: 1.1rem; font-family: 'Poppins', sans-serif; font-weight: 600; outline: none;
    }
    .k-input:focus, .k-select:focus { border-color: #38bdf8; }
    
    .k-btn {
        width: 100%; padding: 16px; border-radius: 12px; font-weight: 800; font-size: 1.1rem;
        cursor: pointer; border: none; display: flex; justify-content: center; align-items: center; gap: 10px;
        color: #fff; text-decoration: none; margin-top: 10px;
    }
    .k-btn-primary { background: #0ea5e9; }
    .k-btn-success { background: #10b981; }
    .k-btn-danger { background: #ef4444; }

    /* Barras Hardware */
    .hw-status { text-align: center; margin-bottom: 25px; }
    .hw-counter { font-size: 3rem; font-weight: 900; line-height: 1; }
    .hw-bar-bg { background: rgba(255,255,255,0.1); height: 16px; border-radius: 8px; margin: 20px 0; overflow: hidden; }
    .hw-bar-fill { height: 100%; }

    /* Mensajes */
    .msg-toast {
        position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
        background: #10b981; color: white; padding: 15px 30px; border-radius: 30px;
        font-weight: 700; box-shadow: 0 10px 25px rgba(16,185,129,0.3); z-index: 9999;
    }
</style>
</head>
<body>

<?php if(isset($_GET['msg'])): ?>
    <div class="msg-toast" id="msgToast"><i class="fa-solid fa-check-circle"></i> Configuración guardada</div>
    <script>setTimeout(()=>document.getElementById('msgToast').style.display='none', 3000);</script>
<?php endif; ?>

<div class="kiosco-header">
    <h1 class="kiosco-title"><i class="fa-solid fa-sliders"></i> Configuración del Tótem</h1>
    <a href="admin_totem_staff.php" class="btn-volver"><i class="fa-solid fa-arrow-left"></i> Volver al Dashboard</a>
</div>

<div class="kiosco-container">

    <!-- CONTROL GENERAL -->
    <?php if ($tiene_estado): ?>
    <div class="k-card">
        <h2 class="k-card-title"><i class="fa-solid fa-power-off" style="color:#38bdf8;"></i> Control General</h2>
        <form method="POST" action="">
            <div class="k-group">
                <label class="k-label">Modo de Funcionamiento</label>
                <select name="estado_totem" class="k-select">
                    <option value="automatico" <?php echo ($estado_actual == 'automatico') ? 'selected' : ''; ?>>🤖 AUTOMÁTICO (Respeta horarios)</option>
                    <option value="abierto" <?php echo ($estado_actual == 'abierto') ? 'selected' : ''; ?>>🟢 FORZAR ABIERTO</option>
                    <option value="cerrado" <?php echo ($estado_actual == 'cerrado') ? 'selected' : ''; ?>>🔴 FORZAR CERRADO</option>
                </select>
            </div>
            <button type="submit" name="cambiar_estado" class="k-btn k-btn-primary"><i class="fa-solid fa-check"></i> Aplicar Estado</button>
        </form>

        <form method="POST" action="" style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
            <h3 style="font-size: 1rem; color: #94a3b8; margin-top: 0;">Horarios Fijos de Apertura</h3>
            <div style="display:flex; gap:15px;">
                <div class="k-group" style="flex:1;">
                    <label class="k-label">Apertura</label>
                    <input type="time" name="hora_apertura" value="<?php echo htmlspecialchars($hora_apertura); ?>" required class="k-input">
                </div>
                <div class="k-group" style="flex:1;">
                    <label class="k-label">Cierre</label>
                    <input type="time" name="hora_cierre" value="<?php echo htmlspecialchars($hora_cierre); ?>" required class="k-input">
                </div>
            </div>
            <button type="submit" name="guardar_horarios" class="k-btn k-btn-success"><i class="fa-solid fa-floppy-disk"></i> Guardar Horarios</button>
        </form>
    </div>
    <?php endif; ?>


    <!-- ROL DE PAPEL Y HARDWARE -->
    <?php if ($tiene_papel): ?>
    <div class="k-card" style="border-color: #f59e0b;">
        <h2 class="k-card-title"><i class="fa-solid fa-receipt" style="color:#f59e0b;"></i> Hardware (Rollo Tótem SGPS)</h2>
        <div class="hw-status">
            <div class="hw-counter"><?php echo $tickets_impresos; ?> <span style="font-size:1.5rem; color:#94a3b8;">/ <?php echo $capacidad_rollo; ?></span></div>
            <div style="font-size: 0.9rem; color: #64748b; font-weight: 700; margin-top:10px;">TICKETS IMPRESOS</div>
            
            <?php $color_bar = ($porcentaje_papel < 20) ? '#ef4444' : '#10b981'; ?>
            <div class="hw-bar-bg">
                <div class="hw-bar-fill" style="background: <?php echo $color_bar; ?>; width: <?php echo $porcentaje_papel; ?>%;"></div>
            </div>
            <div style="color: <?php echo $color_bar; ?>; font-weight: 900; font-size: 1.2rem; margin-bottom: 20px;">RESTANTE: <?php echo $porcentaje_papel; ?>%</div>
            
            <form method="POST" action="">
                <button type="submit" name="resetear_papel" class="k-btn k-btn-success" onclick="return confirm('¿Colocaste un rollo nuevo en la máquina? Esto pondrá el contador en 0.')"><i class="fa-solid fa-rotate-left"></i> Registrar Rollo Nuevo (Reset)</button>
            </form>
        </div>
        
        <hr style="border-color: rgba(255,255,255,0.1); margin: 30px 0;">
        <h2 class="k-card-title"><i class="fa-solid fa-print" style="color:#f59e0b;"></i> Hardware (Rollo Ventanilla)</h2>
        <div class="hw-status">
            <div class="hw-counter"><?php echo $tickets_impresos_ven; ?> <span style="font-size:1.5rem; color:#94a3b8;">/ <?php echo $capacidad_rollo_ven; ?></span></div>
            
            <?php $color_bar_ven = ($porcentaje_papel_ven < 20) ? '#ef4444' : '#10b981'; ?>
            <div class="hw-bar-bg">
                <div class="hw-bar-fill" style="background: <?php echo $color_bar_ven; ?>; width: <?php echo $porcentaje_papel_ven; ?>%;"></div>
            </div>
            <div style="color: <?php echo $color_bar_ven; ?>; font-weight: 900; font-size: 1.2rem; margin-bottom: 20px;">RESTANTE: <?php echo $porcentaje_papel_ven; ?>%</div>
            
            <form method="POST" action="">
                <button type="submit" name="resetear_papel_ventanilla" class="k-btn k-btn-success" onclick="return confirm('¿Colocaste un rollo nuevo en Ventanilla? Esto pondrá el contador en 0.')"><i class="fa-solid fa-rotate-left"></i> Registrar Rollo Ventanilla</button>
            </form>
        </div>
    </div>
    <?php endif; ?>


    <!-- PINES DE SEGURIDAD -->
    <?php if ($tiene_seguridad): ?>
    <div class="k-card" style="border-color: #8b5cf6;">
        <h2 class="k-card-title"><i class="fa-solid fa-key" style="color:#8b5cf6;"></i> Seguridad y Pines</h2>
        <form method="POST" action="">
            <div class="k-group">
                <label class="k-label">PIN Staff Dashboard</label>
                <input type="number" name="pin_dashboard_staff" class="k-input" value="<?php echo htmlspecialchars($pin_dashboard_staff_val); ?>" required>
            </div>
            <div class="k-group">
                <label class="k-label">PIN Necesito Ayuda (Tótem)</label>
                <input type="number" name="pin_soporte" class="k-input" value="<?php echo htmlspecialchars($pin_soporte_val); ?>" required>
            </div>
            
            <h3 style="font-size: 1rem; color: #94a3b8; margin-top: 30px; margin-bottom:10px;">Configuración de Demanda Espontánea</h3>
            <div class="k-group">
                <label class="k-label">Acceso Tótem</label>
                <select name="totem_demanda_espontanea_habilitado" class="k-select">
                    <option value="2" <?php if($totem_demanda_esp_hab == 2) echo 'selected'; ?>>Habilitada SIN PIN</option>
                    <option value="1" <?php if($totem_demanda_esp_hab == 1) echo 'selected'; ?>>Habilitada CON PIN</option>
                    <option value="0" <?php if($totem_demanda_esp_hab == 0) echo 'selected'; ?>>Deshabilitada</option>
                </select>
            </div>
            <div class="k-group">
                <label class="k-label">PIN Demanda Espontánea (Tótem)</label>
                <input type="number" name="pin_demanda_espontanea_totem" class="k-input" value="<?php echo htmlspecialchars($pin_dem_totem_val); ?>" required>
            </div>

            <!-- Campos Ocultos para que la query no tire nulos -->
            <input type="hidden" name="pin_rollo" value="<?php echo htmlspecialchars($pin_rollo_val); ?>">
            <input type="hidden" name="pin_rollo_ventanilla" value="<?php echo htmlspecialchars($pin_rollo_ventanilla_val); ?>">
            <input type="hidden" name="pin_demanda_espontanea_ventanilla" value="<?php echo htmlspecialchars($pin_dem_ventanilla_val); ?>">
            <input type="hidden" name="ventanilla_demanda_espontanea_habilitada" value="<?php echo $ventanilla_demanda_esp_hab; ?>">

            <button type="submit" name="guardar_pines_seguridad" class="k-btn k-btn-primary"><i class="fa-solid fa-save"></i> Guardar Pines</button>
        </form>
    </div>
    <?php endif; ?>


    <!-- DISEÑO Y WIDGETS -->
    <?php if ($tiene_diseno): ?>
    <div class="k-card" style="grid-column: 1 / -1; border-color: #ec4899;">
        <h2 class="k-card-title"><i class="fa-solid fa-palette" style="color:#ec4899;"></i> Diseño y Botones</h2>
        
        <form method="POST" action="" style="margin-bottom: 30px;">
            <button type="submit" name="agregar_boton_nuevo" class="k-btn k-btn-success" style="width:auto; padding: 12px 24px;"><i class="fa-solid fa-plus"></i> Crear Nuevo Botón</button>
        </form>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
        <?php if($botones_lista->num_rows > 0): while($btn = $botones_lista->fetch_assoc()): ?>
            <div style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 20px;">
                <form method="POST" action="">
                    <input type="hidden" name="id_boton" value="<?php echo $btn['id']; ?>">
                    <input type="hidden" name="icono" value="<?php echo htmlspecialchars($btn['icono']); ?>">
                    <input type="hidden" name="color" value="<?php echo htmlspecialchars($btn['color']); ?>">
                    <input type="hidden" name="orden" value="<?php echo $btn['orden']; ?>">
                    
                    <div class="k-group">
                        <label class="k-label">Título</label>
                        <input type="text" name="titulo" value="<?php echo htmlspecialchars($btn['titulo']); ?>" class="k-input" style="padding: 10px; font-size: 1rem;">
                    </div>
                    <div class="k-group">
                        <label class="k-label">Subtítulo (Bajada)</label>
                        <input type="text" name="bajada" value="<?php echo htmlspecialchars($btn['bajada']); ?>" class="k-input" style="padding: 10px; font-size: 1rem;">
                    </div>
                    
                    <div style="display:flex; justify-content: space-between; align-items:center; margin-top: 15px;">
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-size:1.1rem; font-weight:700;">
                            <input type="checkbox" name="activo" value="1" <?php echo ($btn['activo']==1)?'checked':''; ?> style="width:24px; height:24px;">
                            Habilitado
                        </label>
                        <button type="submit" name="guardar_boton" class="k-btn k-btn-primary" style="width:auto; padding: 10px 20px; font-size: 0.9rem; margin:0;">Guardar</button>
                    </div>
                </form>
            </div>
        <?php endwhile; endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

</body>
</html>
