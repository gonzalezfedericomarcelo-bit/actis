<?php
if (isset($_POST['api_reset_ventanilla']) && isset($_POST['pin'])) {
    header("Access-Control-Allow-Origin: *");
    require_once 'includes/conexion.php';
    $pin_db = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_rollo_ventanilla'")->fetch_assoc()['estado'] ?? '77777';
    if ($_POST['pin'] === $pin_db) {
        $conexion->query("UPDATE totem_config SET estado = '0' WHERE tipo = 'tickets_impresos_ventanilla'");
        $conexion->query("INSERT INTO historial_rollos (origen) VALUES ('VENTANILLA')");
        echo "OK";
    } else {
        http_response_code(403);
        echo "PIN Incorrecto";
    }
    exit;
}

session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
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
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Acceso Denegado</title><style>body{font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f8fafc; color:#0f172a; text-align:center; padding-top:100px;} .box{background:white; border-radius:16px; padding:40px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); max-width:500px; margin:auto;} h1{color:#ef4444;}</style></head><body><div class='box'><h1>⚠️ Acceso Denegado</h1><p>Tu rol de usuario no tiene permisos para acceder a la administración del Tótem.</p><br><a href='dashboard_totem.php' style='display:inline-block; padding:10px 20px; background:#144973; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>Volver al Tótem</a></div></body></html>";
    exit;
}

// Asegurar que la tabla exista
$conexion->query("CREATE TABLE IF NOT EXISTS `totem_config` ( `id` int(11) NOT NULL AUTO_INCREMENT, `tipo` varchar(50) NOT NULL, `fecha` date DEFAULT NULL, `estado` varchar(255) DEFAULT NULL, PRIMARY KEY (`id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$conexion->query("ALTER TABLE `totem_config` MODIFY `estado` VARCHAR(255) DEFAULT NULL;");
$conexion->query("INSERT IGNORE INTO `totem_config` (`id`, `tipo`, `estado`) VALUES (1, 'estado_manual', 'automatico');");
$conexion->query("CREATE TABLE IF NOT EXISTS `totem_carrusel` ( `id` int(11) NOT NULL AUTO_INCREMENT, `imagen` varchar(255) NOT NULL, `titulo` varchar(255) DEFAULT NULL, `descripcion` text DEFAULT NULL, `orden` int(11) DEFAULT 0, `activo` tinyint(1) DEFAULT 1, PRIMARY KEY (`id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$conexion->query("CREATE TABLE IF NOT EXISTS `totem_botones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grupo` varchar(50) NOT NULL DEFAULT 'principal',
  `identificador` varchar(50) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `bajada` varchar(255) DEFAULT NULL,
  `icono` varchar(100) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `accion_tipo` varchar(50) DEFAULT 'modal',
  `accion_data` text DEFAULT NULL,
  `orden` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

if($conexion->query("SELECT * FROM totem_botones")->num_rows == 0) {
    $sql_seed = "INSERT INTO totem_botones (grupo, identificador, titulo, bajada, icono, color, accion_tipo, accion_data, orden) VALUES 
    ('principal', 'directorio', 'Directorio Médico', 'Especialidades y profesionales.', 'fa-user-doctor', '#0284c7', 'modal', 'modal-directorio', 10),
    ('principal', 'mapa', 'Mapa Interactivo', 'Encuentra los consultorios.', 'fa-map-location-dot', '#8b5cf6', 'modal', 'modal-mapa', 20),
    ('principal', 'contacto', 'Contacto y Turnos', 'Teléfonos y WhatsApp.', 'fa-address-book', '#f59e0b', 'modal', 'modal-contacto', 30),
    ('principal', 'novedades', 'Novedades y Coberturas', 'Vademécum y prevención.', 'fa-newspaper', '#10b981', 'qr', 'https://iosfa.gob.ar/novedades', 40),
    ('principal', 'turismo', 'Turismo y Beneficios', 'Hoteles de IOSFA.', 'fa-umbrella-beach', '#0891b2', 'qr', 'https://turismo.iosfa.gob.ar/', 50),
    ('principal', 'quejas', 'Sugerencias y Quejas', 'Ayúdanos a mejorar.', 'fa-comment-dots', '#ec4899', 'qr', 'https://federicogonzalez.net/actis/formulario_quejas.php', 60),
    ('principal', 'turnos', 'Consultar Mis Turnos', 'Ver próximos turnos y enviarlos por correo.', 'fa-calendar-check', '#0284c7', 'js', 'openConsultarTurnos()', 70),
    ('widget', 'autismo', 'Hablemos de autismo', 'Plataforma gratuita para ayudar a niños con autismo. Toca aquí para verla.', 'fa-hands-holding-child', '#0369a1', 'js', 'openPapadebauty()', 10),
    ('widget', 'wifi', 'Red Invitados', 'actiswifi', 'fa-wifi', '#0284c7', 'wifi_qr', 'actiswifi', 20)";
    $conexion->query($sql_seed);
}

// Validar existencia de datos base
if($conexion->query("SELECT * FROM totem_config WHERE tipo = 'hora_apertura'")->num_rows == 0) {
    $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('hora_apertura', '06:00'), ('hora_cierre', '20:00')");
}
if($conexion->query("SELECT * FROM totem_config WHERE tipo = 'tickets_impresos'")->num_rows == 0) {
    $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('tickets_impresos', '0'), ('capacidad_rollo', '120')");
}
if($conexion->query("SELECT * FROM totem_config WHERE tipo = 'email_soporte'")->num_rows == 0) {
    $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('email_soporte', 'info@federicogonzalez.net'), ('email_papel', 'info@federicogonzalez.net')");
}
if($conexion->query("SELECT * FROM totem_config WHERE tipo = 'pin_soporte'")->num_rows == 0) {
    $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('pin_soporte', '35911'), ('pin_rollo', '88888')");
}
if($conexion->query("SELECT * FROM totem_config WHERE tipo = 'pin_rollo_ventanilla'")->num_rows == 0) {
    $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('pin_rollo_ventanilla', '77777')");
}
if($conexion->query("SELECT * FROM totem_config WHERE tipo = 'totem_demanda_espontanea_habilitado'")->num_rows == 0) {
    $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('totem_demanda_espontanea_habilitado', '1'), ('pin_demanda_espontanea_totem', '1234')");
}
if($conexion->query("SELECT * FROM totem_config WHERE tipo = 'ventanilla_demanda_espontanea_habilitada'")->num_rows == 0) {
    $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('ventanilla_demanda_espontanea_habilitada', '1'), ('pin_demanda_espontanea_ventanilla', '77777')");
}
if($conexion->query("SELECT * FROM totem_config WHERE tipo = 'alerta_min_papel'")->num_rows == 0) {
    $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('alerta_min_papel', '20')");
}
if($conexion->query("SELECT * FROM totem_config WHERE tipo = 'bloqueo_min_papel'")->num_rows == 0) {
    $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('bloqueo_min_papel', '5')");
}
if($conexion->query("SELECT * FROM totem_config WHERE tipo = 'tickets_impresos_ventanilla'")->num_rows == 0) {
    $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('tickets_impresos_ventanilla', '0'), ('capacidad_rollo_ventanilla', '120'), ('alerta_min_papel_ventanilla', '20')");
}
if($conexion->query("SELECT * FROM totem_config WHERE tipo = 'bloqueo_min_papel_ventanilla'")->num_rows == 0) {
    $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('bloqueo_min_papel_ventanilla', '5')");
}
if($conexion->query("SELECT * FROM totem_config WHERE tipo = 'mostrar_header_footer'")->num_rows == 0) {
    $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('mostrar_header_footer', '0')");
}
if($conexion->query("SELECT * FROM totem_config WHERE tipo = 'alertas_silenciadas'")->num_rows == 0) {
    $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('alertas_silenciadas', '0'), ('alertas_silenciadas_ventanilla', '0')");
}
if($conexion->query("SELECT * FROM totem_config WHERE tipo = 'hora_reinicio_totem'")->num_rows == 0) {
    $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('hora_reinicio_totem', '')");
}
if($conexion->query("SELECT * FROM totem_config WHERE tipo = 'forzar_reinicio_totem'")->num_rows == 0) {
    $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('forzar_reinicio_totem', '0')");
}
if($conexion->query("SELECT * FROM totem_config WHERE tipo = 'pin_dashboard_staff'")->num_rows == 0) {
    $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('pin_dashboard_staff', '12345')");
}
if($conexion->query("SELECT * FROM totem_config WHERE tipo = 'pin_limpieza'")->num_rows == 0) {
    $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('pin_limpieza', '5555')");
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cambiar_estado'])) {
        $nuevo_estado = $conexion->real_escape_string($_POST['estado_totem']);
        $conexion->query("UPDATE totem_config SET estado = '$nuevo_estado' WHERE tipo = 'estado_manual'");
        header("Location: admin_totem.php?msg=estado"); exit;
    }
    if (isset($_POST['guardar_horarios'])) {
        $apertura = $conexion->real_escape_string($_POST['hora_apertura']);
        $cierre = $conexion->real_escape_string($_POST['hora_cierre']);
        $conexion->query("UPDATE totem_config SET estado = '$apertura' WHERE tipo = 'hora_apertura'");
        $conexion->query("UPDATE totem_config SET estado = '$cierre' WHERE tipo = 'hora_cierre'");
        header("Location: admin_totem.php?msg=horarios"); exit;
    }
    if (isset($_POST['guardar_reinicio'])) {
        $hora_reinicio = $conexion->real_escape_string($_POST['hora_reinicio_totem']);
        $conexion->query("UPDATE totem_config SET estado = '$hora_reinicio' WHERE tipo = 'hora_reinicio_totem'");
        header("Location: admin_totem.php?msg=reinicio_actualizado"); exit;
    }
    if (isset($_POST['forzar_reinicio'])) {
        $conexion->query("UPDATE totem_config SET estado = '1' WHERE tipo = 'forzar_reinicio_totem'");
        header("Location: admin_totem.php?msg=reinicio_forzado"); exit;
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
        header("Location: admin_totem.php?msg=correos"); exit;
    }
    if (isset($_POST['resetear_papel'])) {
        $conexion->query("UPDATE totem_config SET estado = '0' WHERE tipo = 'tickets_impresos'");
        $conexion->query("INSERT INTO historial_rollos (origen) VALUES ('TOTEM')");
        header("Location: admin_totem.php?msg=papel_reseteado"); exit;
    }
    if (isset($_POST['resetear_papel_ventanilla'])) {
        $conexion->query("UPDATE totem_config SET estado = '0' WHERE tipo = 'tickets_impresos_ventanilla'");
        $conexion->query("INSERT INTO historial_rollos (origen) VALUES ('VENTANILLA')");
        header("Location: admin_totem.php?msg=papel_reseteado_ven"); exit;
    }
    if (isset($_POST['toggle_silenciar'])) {
        $tipo = $conexion->real_escape_string($_POST['tipo_silencio']);
        $estado_actual = (int)$_POST['estado_actual'];
        $nuevo_estado = $estado_actual === 1 ? '0' : '1';
        $conexion->query("UPDATE totem_config SET estado = '$nuevo_estado' WHERE tipo = '$tipo'");
        header("Location: admin_totem.php?msg=silencio_toggled"); exit;
    }
    if (isset($_POST['guardar_tickets_manual'])) {
        $cantidad_manual = (int)$_POST['cantidad_tickets_manual'];
        $conexion->query("UPDATE totem_config SET estado = '$cantidad_manual' WHERE tipo = 'tickets_impresos'");
        header("Location: admin_totem.php?msg=tickets_manual"); exit;
    }
    if (isset($_POST['guardar_tickets_manual_ventanilla'])) {
        $cantidad_manual = (int)$_POST['cantidad_tickets_manual_ventanilla'];
        $conexion->query("UPDATE totem_config SET estado = '$cantidad_manual' WHERE tipo = 'tickets_impresos_ventanilla'");
        header("Location: admin_totem.php?msg=tickets_manual_ven"); exit;
    }
    if (isset($_POST['guardar_capacidad_manual'])) {
        $capacidad_nueva = (int)$_POST['capacidad_rollo_manual'];
        $conexion->query("UPDATE totem_config SET estado = '$capacidad_nueva' WHERE tipo = 'capacidad_rollo'");
        header("Location: admin_totem.php?msg=capacidad_manual"); exit;
    }
    if (isset($_POST['guardar_capacidad_manual_ventanilla'])) {
        $capacidad_nueva = (int)$_POST['capacidad_rollo_manual_ventanilla'];
        $conexion->query("UPDATE totem_config SET estado = '$capacidad_nueva' WHERE tipo = 'capacidad_rollo_ventanilla'");
        header("Location: admin_totem.php?msg=capacidad_manual_ven"); exit;
    }
    if (isset($_POST['toggle_header_footer'])) {
        $nuevo_estado = (int)$_POST['toggle_header_footer_val'];
        $conexion->query("UPDATE totem_config SET estado = '$nuevo_estado' WHERE tipo = 'mostrar_header_footer'");
        header("Location: admin_totem.php?msg=header_footer_toggled"); exit;
    }
    if (isset($_POST['guardar_pines_seguridad'])) {
        $pin_soporte = $conexion->real_escape_string(trim($_POST['pin_soporte']));
        $pin_rollo = $conexion->real_escape_string(trim($_POST['pin_rollo']));
        $pin_rollo_ventanilla = $conexion->real_escape_string(trim($_POST['pin_rollo_ventanilla']));
        $pin_dem_totem = $conexion->real_escape_string(trim($_POST['pin_demanda_espontanea_totem']));
        $pin_dem_ventanilla = $conexion->real_escape_string(trim($_POST['pin_demanda_espontanea_ventanilla']));
        $pin_dashboard_staff = $conexion->real_escape_string(trim($_POST['pin_dashboard_staff']));
        $pin_limpieza = $conexion->real_escape_string(trim($_POST['pin_limpieza']));
        
        $conexion->query("UPDATE totem_config SET estado = '$pin_soporte' WHERE tipo = 'pin_soporte'");
        $conexion->query("UPDATE totem_config SET estado = '$pin_rollo' WHERE tipo = 'pin_rollo'");
        $conexion->query("UPDATE totem_config SET estado = '$pin_rollo_ventanilla' WHERE tipo = 'pin_rollo_ventanilla'");
        $conexion->query("UPDATE totem_config SET estado = '$pin_dem_totem' WHERE tipo = 'pin_demanda_espontanea_totem'");
        $conexion->query("UPDATE totem_config SET estado = '$pin_dem_ventanilla' WHERE tipo = 'pin_demanda_espontanea_ventanilla'");
        $conexion->query("UPDATE totem_config SET estado = '$pin_dashboard_staff' WHERE tipo = 'pin_dashboard_staff'");
        $conexion->query("UPDATE totem_config SET estado = '$pin_limpieza' WHERE tipo = 'pin_limpieza'");
        
        if (isset($_POST['totem_demanda_espontanea_habilitado'])) {
            $demanda_hab = (int)$_POST['totem_demanda_espontanea_habilitado'];
            $conexion->query("UPDATE totem_config SET estado = '$demanda_hab' WHERE tipo = 'totem_demanda_espontanea_habilitado'");
        }
        if (isset($_POST['ventanilla_demanda_espontanea_habilitada'])) {
            $demanda_hab_ven = (int)$_POST['ventanilla_demanda_espontanea_habilitada'];
            $conexion->query("UPDATE totem_config SET estado = '$demanda_hab_ven' WHERE tipo = 'ventanilla_demanda_espontanea_habilitada'");
        }
        header("Location: admin_totem.php?msg=pines_actualizados"); exit;
    }
    if (isset($_POST['agregar_feriado'])) {
        $fecha_feriado = $conexion->real_escape_string($_POST['fecha_feriado']);
        if(!empty($fecha_feriado)) $conexion->query("INSERT INTO totem_config (tipo, fecha) VALUES ('feriado', '$fecha_feriado')");
        header("Location: admin_totem.php?msg=feriado"); exit;
    }
    if (isset($_POST['toggle_simulacion_test'])) {
        $estado_actual = (int)$_POST['estado_actual'];
        $nuevo_estado = $estado_actual === 1 ? '0' : '1';
        $conexion->query("INSERT IGNORE INTO `totem_config` (`tipo`, `estado`) SELECT 'simular_sin_papel_test', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM totem_config WHERE tipo = 'simular_sin_papel_test');");
        $conexion->query("UPDATE totem_config SET estado = '$nuevo_estado' WHERE tipo = 'simular_sin_papel_test'");
        header("Location: admin_totem.php?msg=simulacion_toggled"); exit;
    }

    if (isset($_POST['agregar_personal_limpieza'])) {
        $dni = $conexion->real_escape_string(trim($_POST['dni_personal']));
        $nombre = $conexion->real_escape_string(trim($_POST['nombre_personal']));
        if (!empty($dni) && !empty($nombre)) {
            $conexion->query("INSERT IGNORE INTO personal_limpieza (dni, nombre_completo, estado) VALUES ('$dni', '$nombre', 1)");
        }
        header("Location: admin_totem.php?msg=personal_limpieza_agregado"); exit;
    }

    if (isset($_POST['eliminar_personal_limpieza'])) {
        $id_eliminar = (int)$_POST['eliminar_personal_limpieza'];
        $conexion->query("DELETE FROM personal_limpieza WHERE id = $id_eliminar");
        header("Location: admin_totem.php?msg=personal_limpieza_eliminado"); exit;
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
        header("Location: admin_totem.php?msg=tipografia_guardada"); exit;
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
            // Count total files
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
        header("Location: admin_totem.php?msg=carrusel_agregado"); exit;
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
        header("Location: admin_totem.php?msg=boton_guardado"); exit;
    }

    if (isset($_POST['agregar_boton_nuevo'])) {
        $conexion->query("INSERT INTO totem_botones (grupo, identificador, titulo, bajada, icono, color, accion_tipo, accion_data, orden, activo) VALUES ('widget', 'nuevo_" . time() . "', 'Nuevo Widget', 'Descripción', 'fa-star', '#cbd5e1', 'modal', '', 99, 1)");
        header("Location: admin_totem.php?msg=boton_agregado"); exit;
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
    header("Location: admin_totem.php?msg=carrusel_eliminado"); exit;
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
    header("Location: admin_totem.php?msg=documento_agregado"); exit;
}

if (isset($_POST['toggle_documento'])) {
    $id_doc = (int)$_POST['id_documento'];
    $activo = isset($_POST['activo_documento']) ? 1 : 0;
    $conexion->query("UPDATE totem_documentos SET activo=$activo WHERE id=$id_doc");
    header("Location: admin_totem.php?msg=documento_actualizado"); exit;
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
    header("Location: admin_totem.php?msg=documento_eliminado"); exit;
}

if (isset($_GET['eliminar'])) {
    $conexion->query("DELETE FROM totem_config WHERE id = ".(int)$_GET['eliminar']." AND tipo = 'feriado'");
    header("Location: admin_totem.php"); exit;
}

$estado_actual = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'estado_manual'")->fetch_assoc()['estado'];
$feriados = $conexion->query("SELECT * FROM totem_config WHERE tipo = 'feriado' ORDER BY fecha ASC");
$hora_apertura = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'hora_apertura'")->fetch_assoc()['estado'];
$hora_cierre = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'hora_cierre'")->fetch_assoc()['estado'];
$hora_reinicio_totem = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'hora_reinicio_totem'")->fetch_assoc()['estado'] ?? '';
$tickets_impresos = (int)$conexion->query("SELECT estado FROM totem_config WHERE tipo = 'tickets_impresos'")->fetch_assoc()['estado'];
$capacidad_rollo = (int)$conexion->query("SELECT estado FROM totem_config WHERE tipo = 'capacidad_rollo'")->fetch_assoc()['estado'];
$porcentaje_papel = ($capacidad_rollo > 0) ? round((($capacidad_rollo - $tickets_impresos) / $capacidad_rollo) * 100) : 0;
$email_soporte_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'email_soporte'")->fetch_assoc()['estado'];
$email_papel_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'email_papel'")->fetch_assoc()['estado'];
$alerta_min_papel_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'alerta_min_papel'")->fetch_assoc()['estado'];
$bloqueo_min_papel_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'bloqueo_min_papel'")->fetch_assoc()['estado'] ?? '5';

$tickets_impresos_ven = (int)$conexion->query("SELECT estado FROM totem_config WHERE tipo = 'tickets_impresos_ventanilla'")->fetch_assoc()['estado'];
$capacidad_rollo_ven = (int)$conexion->query("SELECT estado FROM totem_config WHERE tipo = 'capacidad_rollo_ventanilla'")->fetch_assoc()['estado'];
$porcentaje_papel_ven = ($capacidad_rollo_ven > 0) ? round((($capacidad_rollo_ven - $tickets_impresos_ven) / $capacidad_rollo_ven) * 100) : 0;
$alerta_min_papel_ven_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'alerta_min_papel_ventanilla'")->fetch_assoc()['estado'];
$bloqueo_min_papel_ven_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'bloqueo_min_papel_ventanilla'")->fetch_assoc()['estado'] ?? '5';
$mostrar_hf_ven = (int)$conexion->query("SELECT estado FROM totem_config WHERE tipo = 'mostrar_header_footer'")->fetch_assoc()['estado'];
$silenciado_totem = (int)$conexion->query("SELECT estado FROM totem_config WHERE tipo = 'alertas_silenciadas'")->fetch_assoc()['estado'];
$silenciado_ven = (int)$conexion->query("SELECT estado FROM totem_config WHERE tipo = 'alertas_silenciadas_ventanilla'")->fetch_assoc()['estado'];
$pin_soporte_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_soporte'")->fetch_assoc()['estado'] ?? '35911';
$pin_rollo_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_rollo'")->fetch_assoc()['estado'] ?? '88888';
$pin_rollo_ventanilla_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_rollo_ventanilla'")->fetch_assoc()['estado'] ?? '77777';
$pin_dem_totem_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_demanda_espontanea_totem'")->fetch_assoc()['estado'] ?? '1234';
$pin_dem_ventanilla_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_demanda_espontanea_ventanilla'")->fetch_assoc()['estado'] ?? '77777';
$pin_dashboard_staff_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_dashboard_staff'")->fetch_assoc()['estado'] ?? '12345';
$pin_limpieza_val = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_limpieza'")->fetch_assoc()['estado'] ?? '5555';
$totem_demanda_esp_hab = (int)($conexion->query("SELECT estado FROM totem_config WHERE tipo = 'totem_demanda_espontanea_habilitado'")->fetch_assoc()['estado'] ?? '1');
$ventanilla_demanda_esp_hab = (int)($conexion->query("SELECT estado FROM totem_config WHERE tipo = 'ventanilla_demanda_espontanea_habilitada'")->fetch_assoc()['estado'] ?? '1');
$simular_sin_papel_test = (int)($conexion->query("SELECT estado FROM totem_config WHERE tipo = 'simular_sin_papel_test'")->fetch_assoc()['estado'] ?? '0');
// Leer tipografía independiente por sección
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

require_once 'includes/header.php';
?>
<style>
    /* Estructura Base calcada de turnos_listar.php */
    .at-container { max-width: 1400px; margin: 20px auto; padding: 0 15px; font-family: 'Poppins', sans-serif; }
    
    .at-grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
    @media (min-width: 992px) { .at-grid { grid-template-columns: repeat(2, 1fr); } }
    
    .at-panel { background: #ffffff; border-radius: 20px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); }
    .at-header { font-size: 1.4rem; font-weight: 900; margin-bottom: 20px; color: #0f172a; display: flex; align-items: center; gap: 10px; margin-top: 0; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; }
    
    /* Inputs y Forms calcados */
    .at-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 15px; flex: 1; }
    .at-label { font-size: 0.8rem; font-weight: 800; color: #64748b; text-transform: uppercase; }
    .at-input { width: 100%; padding: 12px 15px; border-radius: 10px; border: 2px solid #cbd5e1; font-weight: 600; font-family: 'Poppins', sans-serif; font-size: 0.95rem; outline: none; transition: border 0.2s; box-sizing: border-box; background: white; }
    .at-input:focus { border-color: #144973; }
    
    /* Configuración flex base para escritorio */
    .at-input-row { display: flex; gap: 10px; align-items: flex-end; margin-bottom: 15px; flex-wrap: wrap; }
    
    /* Grid específica para Pines */
    .pines-grid { display: flex; flex-direction: column; gap: 15px; align-items: stretch; }
    @media (min-width: 1200px) { 
        .pines-grid { display: grid; grid-template-columns: repeat(3, 1fr) 280px; align-items: end; }
        .pines-span-2 { grid-column: span 2; }
    }
    @media (min-width: 768px) and (max-width: 1199px) {
        .pines-grid { display: grid; grid-template-columns: repeat(2, 1fr); align-items: end; }
        .pines-span-2 { grid-column: span 2; }
    }
    
    /* Botones calcados */
    .at-btn { width: 100%; padding: 12px 20px; border: none; border-radius: 10px; font-weight: 800; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: transform 0.2s; text-decoration: none; color: white; white-space: nowrap; }
    .at-btn:active { transform: scale(0.98); }
    .at-btn-primary { background: #144973; }
    .at-btn-success { background: #10b981; }
    .at-btn-danger { background: #ef4444; }
    .at-btn-clear { background: #64748b; }
    .at-btn-icon { width: 46px; height: 46px; border-radius: 10px; display: inline-flex; justify-content: center; align-items: center; border: none; color: white; cursor: pointer; font-size: 1.1rem; flex-shrink: 0; transition: transform 0.2s; }
    .at-btn-icon:active { transform: scale(0.95); }

    /* Feriados */
    .list-feriados { display: flex; flex-direction: column; gap: 8px; }
    .item-feriado { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; font-weight: 800; color: #334155; font-size: 0.95rem; }
    
    /* Visualizador hardware */
    .hw-status { text-align:center; padding-bottom: 25px; border-bottom: 2px dashed #e2e8f0; margin-bottom: 20px; }
    .hw-counter { font-size: 3.5rem; font-weight: 900; color: #0f172a; line-height: 1; margin-bottom: 5px; }
    .hw-total { font-size:1.5rem; color:#94a3b8; }
    .hw-bar-bg { background: #f1f5f9; height: 12px; border-radius: 6px; margin: 15px 0; overflow: hidden; width: 100%; border: 1px solid #e2e8f0; }
    .hw-bar-fill { height: 100%; border-radius: 6px; }

    /* === RESPONSIVO MÓVIL ESTRICTO (MODO FORZADO) === */
    @media (max-width: 768px) {
        body { overflow-x: hidden !important; }
        .at-container { padding: 0 10px !important; padding-bottom: 40px !important; overflow-x: hidden !important; }
        .at-panel { padding: 15px !important; border-radius: 16px !important; width: 100% !important; box-sizing: border-box !important; overflow: hidden !important; }
        .at-header { font-size: 1.15rem !important; flex-wrap: wrap !important; }
        
        .at-input-row { display: block !important; width: 100% !important; margin-bottom: 0 !important; }
        
        .at-input-row .at-group, 
        .at-input-row form { 
            display: block !important;
            width: 100% !important; 
            min-width: 0 !important; 
            margin: 0 0 15px 0 !important; 
            box-sizing: border-box !important;
        }
        
        .at-btn { width: 100% !important; padding: 12px 10px !important; white-space: normal !important; box-sizing: border-box !important; margin-top: 10px !important; }
        
        .hw-counter { font-size: 2.5rem !important; }
        .hw-total { font-size: 1.2rem !important; }
        
        /* CORRECCIÓN: Mantenemos el input y el botón de ajustes juntos sin que rompan nada */
        .at-input-with-button { display: flex !important; flex-direction: row !important; width: 100% !important; box-sizing: border-box !important; gap: 10px !important; }
        .at-input-with-button .at-input { flex: 1 !important; width: auto !important; }
        .at-input-with-button .at-btn-icon { width: 46px !important; height: 46px !important; margin-top: 0 !important; flex-shrink: 0 !important; }

        /* CORRECCIÓN: Feriados 100% en su lugar, botón tacho en tamaño original */
        .item-feriado { display: flex !important; flex-direction: row !important; justify-content: space-between !important; align-items: center !important; }
        .item-feriado .at-btn-icon { width: 36px !important; height: 36px !important; margin-top: 0 !important; display: inline-flex !important; }
        
        /* CORRECCIÓN: Elementos espaciados que pasan a bloque completo */
        .at-flex-between { display: flex !important; flex-direction: column !important; align-items: stretch !important; gap: 15px !important; width: 100% !important; box-sizing: border-box !important; }
        .at-flex-between > div { margin-bottom: 0 !important; text-align: center !important; }
        .at-flex-between form, .at-flex-between button, .at-flex-between a { width: 100% !important; }

        /* CORRECCIÓN EXTREMA: Pines Grid 100% apilado en móviles */
        .pines-grid { display: flex !important; flex-direction: column !important; width: 100% !important; gap: 15px !important; }
        .pines-grid .at-group { width: 100% !important; margin-bottom: 0 !important; }
    }
</style>

<div class="at-container">
    <div class="at-grid">
<?php if ($tiene_estado): ?>
        <div class="at-panel">
            <h2 class="at-header"><i class="fa-solid fa-power-off" style="color: #144973;"></i> Control General</h2>
            <form method="POST" action="">
                <div class="at-group">
                    <label class="at-label">Modo de Funcionamiento:</label>
                    <select name="estado_totem" class="at-input" style="appearance: none; cursor:pointer;">
                        <option value="automatico" <?php echo ($estado_actual == 'automatico') ? 'selected' : ''; ?>>🤖 AUTOMÁTICO (Respeta horarios)</option>
                        <option value="abierto" <?php echo ($estado_actual == 'abierto') ? 'selected' : ''; ?>>🟢 FORZAR ABIERTO</option>
                        <option value="cerrado" <?php echo ($estado_actual == 'cerrado') ? 'selected' : ''; ?>>🔴 FORZAR CERRADO</option>
                    </select>
                </div>
                <button type="submit" name="cambiar_estado" class="at-btn at-btn-primary"><i class="fa-solid fa-check"></i> Aplicar Estado</button>
            </form>
        </div>

        <div class="at-panel">
            <h2 class="at-header"><i class="fa-solid fa-clock" style="color: #144973;"></i> Horarios Fijos</h2>
            <form method="POST" action="">
                <div class="at-input-row">
                    <div class="at-group" style="margin:0;">
                        <label class="at-label">Apertura (HH:MM)</label>
                        <input type="time" name="hora_apertura" value="<?php echo htmlspecialchars($hora_apertura); ?>" required class="at-input">
                    </div>
                    <div class="at-group" style="margin:0;">
                        <label class="at-label">Cierre (HH:MM)</label>
                        <input type="time" name="hora_cierre" value="<?php echo htmlspecialchars($hora_cierre); ?>" required class="at-input">
                    </div>
                </div>
                <button type="submit" name="guardar_horarios" class="at-btn at-btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar Horarios</button>
            </form>
        </div>

        <div class="at-panel">
            <h2 class="at-header"><i class="fa-solid fa-power-off" style="color: #ef4444;"></i> Reinicio Automático (Tótem Windows)</h2>
            <form method="POST" action="" class="at-input-row" style="margin-bottom: 15px;">
                <div class="at-group" style="margin:0; flex:1;">
                    <label class="at-label">Hora de Reinicio Diario (HH:MM)</label>
                    <input type="time" name="hora_reinicio_totem" value="<?php echo htmlspecialchars($hora_reinicio_totem); ?>" class="at-input">
                    <small style="color:#64748b; font-size:0.8rem; margin-top:5px; font-weight:600;">Dejar vacío para desactivar. El equipo físico del Tótem se reiniciará automáticamente a esta hora todos los días.</small>
                </div>
                <button type="submit" name="guardar_reinicio" class="at-btn at-btn-success" style="width: auto; height: 46px;"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
            </form>
            <form method="POST" action="" style="margin-top: 10px; border-top: 1px dashed #cbd5e1; padding-top: 15px;">
                <button type="submit" name="forzar_reinicio" class="at-btn at-btn-danger" style="width: 100%;" onclick="return confirm('ATENCIÓN: Esto apagará y reiniciará INMEDIATAMENTE el Tótem físico. Cualquier persona que lo esté usando perderá su progreso. ¿Continuar?')"><i class="fa-solid fa-bolt"></i> FORZAR REINICIO AHORA MISMO</button>
            </form>
        </div>

        <div class="at-panel">
            <h2 class="at-header"><i class="fa-solid fa-calendar-xmark" style="color: #144973;"></i> Días Bloqueados</h2>
            <form method="POST" action="" class="at-input-row" style="margin-bottom: 15px;">
                <div class="at-group at-input-with-button" style="display:flex; gap:10px; margin:0; width: 100%;">
                    <input type="date" name="fecha_feriado" required class="at-input" style="flex: 1;">
                    <button type="submit" name="agregar_feriado" class="at-btn-icon at-btn-primary"><i class="fa-solid fa-plus"></i></button>
                </div>
            </form>
            <div class="list-feriados">
                <?php if($feriados->num_rows > 0): while($f = $feriados->fetch_assoc()): ?>
                    <div class="item-feriado">
                        <span><i class="fa-regular fa-calendar" style="color:#144973; margin-right:8px;"></i> <?php echo date("d/m/Y", strtotime($f['fecha'])); ?></span>
                        <a href="admin_totem.php?eliminar=<?php echo $f['id']; ?>" class="at-btn-icon at-btn-danger" style="width:36px; height:36px; font-size:0.9rem;" onclick="return confirm('¿Quitar feriado?')"><i class="fa-solid fa-trash"></i></a>
                    </div>
                <?php endwhile; else: ?>
                    <div style="text-align:center; padding:20px; background:#f8fafc; border-radius:10px; color:#64748b; font-weight:800; border: 1px dashed #cbd5e1;">Sin fechas bloqueadas.</div>
                <?php endif; ?>
            </div>
        </div>
<?php endif; ?>

<?php if ($tiene_seguridad): ?>
        <div class="at-panel">
            <h2 class="at-header"><i class="fa-solid fa-envelope" style="color: #144973;"></i> Alertas por Correo</h2>
            <form method="POST" action="">
                <div class="at-group">
                    <label class="at-label">Soporte Técnico (Separados por coma)</label>
                    <input type="text" name="email_soporte" value="<?php echo htmlspecialchars($email_soporte_val); ?>" required class="at-input">
                </div>
                <div class="at-group">
                    <label class="at-label">Responsable de Papel (Separados por coma)</label>
                    <input type="text" name="email_papel" value="<?php echo htmlspecialchars($email_papel_val); ?>" required class="at-input">
                </div>
                <div class="at-input-row">
                    <div class="at-group" style="margin:0;">
                        <label class="at-label">Alerta Tótem (%)</label>
                        <input type="number" name="alerta_min_papel" value="<?php echo htmlspecialchars($alerta_min_papel_val); ?>" min="1" max="100" required class="at-input">
                    </div>
                    <div class="at-group" style="margin:0;">
                        <label class="at-label">Alerta Ventanilla (%)</label>
                        <input type="number" name="alerta_min_papel_ventanilla" value="<?php echo htmlspecialchars($alerta_min_papel_ven_val); ?>" min="1" max="100" required class="at-input">
                    </div>
                </div>
                <div class="at-input-row" style="margin-top: 15px;">
                    <div class="at-group" style="margin:0;">
                        <label class="at-label" style="color: #ef4444;">Bloquear Tótem al llegar a (Tickets)</label>
                        <input type="number" name="bloqueo_min_papel" value="<?php echo htmlspecialchars($bloqueo_min_papel_val); ?>" min="0" max="1000" required class="at-input">
                    </div>
                    <div class="at-group" style="margin:0;">
                        <label class="at-label" style="color: #ef4444;">Bloquear Ventanilla al llegar a (Tickets)</label>
                        <input type="number" name="bloqueo_min_papel_ventanilla" value="<?php echo htmlspecialchars($bloqueo_min_papel_ven_val); ?>" min="0" max="1000" required class="at-input">
                    </div>
                </div>
                <button type="submit" name="guardar_correos" class="at-btn at-btn-primary"><i class="fa-solid fa-floppy-disk"></i> Actualizar Configuración</button>
            </form>
            
            <div style="display: flex; gap: 15px; margin-top: 15px; flex-wrap: wrap;">
                <form method="POST" action="" style="flex: 1; margin: 0; min-width: 250px;">
                    <input type="hidden" name="toggle_silenciar" value="1">
                    <input type="hidden" name="tipo_silencio" value="alertas_silenciadas">
                    <input type="hidden" name="estado_actual" value="<?php echo $silenciado_totem; ?>">
                    <?php if($silenciado_totem === 1): ?>
                        <button type="submit" class="at-btn at-btn-success" style="background:#10b981;"><i class="fa-solid fa-bell"></i> Reanudar Alertas Tótem</button>
                    <?php else: ?>
                        <button type="submit" class="at-btn at-btn-danger" style="background:#ef4444;"><i class="fa-solid fa-bell-slash"></i> Silenciar Alertas Tótem</button>
                    <?php endif; ?>
                </form>
                <form method="POST" action="" style="flex: 1; margin: 0; min-width: 250px;">
                    <input type="hidden" name="toggle_silenciar" value="1">
                    <input type="hidden" name="tipo_silencio" value="alertas_silenciadas_ventanilla">
                    <input type="hidden" name="estado_actual" value="<?php echo $silenciado_ven; ?>">
                    <?php if($silenciado_ven === 1): ?>
                        <button type="submit" class="at-btn at-btn-success" style="background:#10b981;"><i class="fa-solid fa-bell"></i> Reanudar Alertas Vent. </button>
                    <?php else: ?>
                        <button type="submit" class="at-btn at-btn-danger" style="background:#ef4444;"><i class="fa-solid fa-bell-slash"></i> Silenciar Alertas Vent. </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="at-panel" style="grid-column: 1 / -1;">
            <h2 class="at-header"><i class="fa-solid fa-key" style="color: #144973;"></i> Seguridad / Pines de Acceso</h2>
            <form method="POST" action="" class="pines-grid">
                <div class="at-group" style="margin-bottom:0;">
                    <label class="at-label">PIN Necesito Ayuda (Tótem)</label>
                    <input type="text" name="pin_soporte" class="at-input" value="<?php echo htmlspecialchars($pin_soporte_val); ?>" required>
                </div>
                <div class="at-group" style="margin-bottom:0;">
                    <label class="at-label">PIN Cambio Rollo (Tótem)</label>
                    <input type="text" name="pin_rollo" class="at-input" value="<?php echo htmlspecialchars($pin_rollo_val); ?>" required>
                </div>
                <div class="at-group" style="margin-bottom:0;">
                    <label class="at-label">PIN Cambio Rollo (Ventanilla)</label>
                    <input type="text" name="pin_rollo_ventanilla" class="at-input" value="<?php echo htmlspecialchars($pin_rollo_ventanilla_val); ?>" required>
                </div>
                <div class="at-group" style="margin-bottom:0;">
                    <label class="at-label">PIN Staff (Dashboard)</label>
                    <input type="text" name="pin_dashboard_staff" class="at-input" value="<?php echo htmlspecialchars($pin_dashboard_staff_val); ?>" required>
                </div>
                <div class="at-group" style="margin-bottom:0;">
                    <label class="at-label" style="color: #0284c7;"><i class="fa-solid fa-broom"></i> PIN Limpieza (Tótem)</label>
                    <input type="text" name="pin_limpieza" class="at-input" value="<?php echo htmlspecialchars($pin_limpieza_val); ?>" required>
                </div>
                <div class="at-group" style="margin-bottom:0; background: #fffbeb; padding: 10px; border-radius: 8px; border: 1px dashed #f59e0b;">
                    <label class="at-label" style="color: #d97706;"><i class="fa-solid fa-triangle-exclamation"></i> Demanda Espontánea en Tótem (Sin Turno)</label>
                    <select name="totem_demanda_espontanea_habilitado" class="at-input" style="margin-bottom: 10px;">
                        <option value="2" <?php if($totem_demanda_esp_hab == 2) echo 'selected'; ?>>Habilitada SIN PIN (Acceso Libre)</option>
                        <option value="1" <?php if($totem_demanda_esp_hab == 1) echo 'selected'; ?>>Habilitada (Permitir Ingreso Libre con PIN)</option>
                        <option value="0" <?php if($totem_demanda_esp_hab == 0) echo 'selected'; ?>>Deshabilitada (Enviar siempre a Ventanilla)</option>
                    </select>
                    <label class="at-label" style="font-size:0.8rem;">PIN de Demanda Espontánea (Tótem)</label>
                    <input type="text" name="pin_demanda_espontanea_totem" class="at-input" value="<?php echo htmlspecialchars($pin_dem_totem_val); ?>" required>
                </div>
                <div class="at-group pines-span-2" style="margin-bottom:0; background: #e0e7ff; padding: 10px; border-radius: 8px; border: 1px dashed #4338ca;">
                    <label class="at-label" style="color: #4338ca;"><i class="fa-solid fa-clipboard-user"></i> Demanda Espontánea en Ventanilla (Sin Turno)</label>
                    <select name="ventanilla_demanda_espontanea_habilitada" class="at-input" style="margin-bottom: 10px;">
                        <option value="2" <?php if($ventanilla_demanda_esp_hab == 2) echo 'selected'; ?>>Habilitada SIN PIN (Acceso Libre)</option>
                        <option value="1" <?php if($ventanilla_demanda_esp_hab == 1) echo 'selected'; ?>>Habilitada (Permitir Asignar Turno con PIN)</option>
                        <option value="0" <?php if($ventanilla_demanda_esp_hab == 0) echo 'selected'; ?>>Deshabilitada (Bloquear siempre si no tiene turno)</option>
                    </select>
                    <label class="at-label" style="font-size:0.8rem;">PIN de Demanda Espontánea (Ventanilla)</label>
                    <input type="text" name="pin_demanda_espontanea_ventanilla" class="at-input" value="<?php echo htmlspecialchars($pin_dem_ventanilla_val); ?>" required>
                </div>
                <div class="at-group" style="margin-bottom:0; grid-column: 1 / -1; display:flex; justify-content:flex-end;">
                    <button type="submit" name="guardar_pines_seguridad" class="at-btn at-btn-guardar"><i class="fa-solid fa-floppy-disk"></i> Guardar Pines</button>
                </div>
            </form>
        </div>

        <div class="at-panel" style="grid-column: 1 / -1;">
            <h2 class="at-header"><i class="fa-solid fa-users" style="color: #10b981;"></i> Gestión Personal de Limpieza</h2>
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
                <!-- Formulario para agregar -->
                <div style="background: rgba(16, 185, 129, 0.05); padding: 20px; border-radius: 12px; border: 1px dashed #10b981;">
                    <h3 style="font-size: 14px; margin-top:0; color:#10b981;">Agregar Nuevo</h3>
                    <form method="POST" action="">
                        <div class="at-group">
                            <label class="at-label">DNI</label>
                            <input type="text" name="dni_personal" class="at-input" required placeholder="Ej: 12345678">
                        </div>
                        <div class="at-group">
                            <label class="at-label">Nombre Completo</label>
                            <input type="text" name="nombre_personal" class="at-input" required placeholder="Ej: Juan Perez">
                        </div>
                        <button type="submit" name="agregar_personal_limpieza" class="at-btn" style="background:#10b981; color:white; width: 100%;">
                            <i class="fa-solid fa-plus"></i> Agregar
                        </button>
                    </form>
                </div>
                <!-- Lista de personal -->
                <div style="background: white; padding: 0; border-radius: 12px; overflow:hidden; border: 1px solid #e2e8f0;">
                    <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
                        <thead style="background:#f1f5f9; color:#475569; font-weight:bold; border-bottom:1px solid #e2e8f0;">
                            <tr>
                                <th style="padding:10px 15px;">DNI</th>
                                <th style="padding:10px 15px;">Nombre Completo</th>
                                <th style="padding:10px 15px; text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $res_limpieza = $conexion->query("SELECT * FROM personal_limpieza ORDER BY nombre_completo ASC");
                            if($res_limpieza && $res_limpieza->num_rows > 0): 
                                while($row_lim = $res_limpieza->fetch_assoc()):
                            ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding:10px 15px; color:#0f172a; font-weight:600;"><?php echo htmlspecialchars($row_lim['dni']); ?></td>
                                <td style="padding:10px 15px; color:#475569;"><?php echo htmlspecialchars($row_lim['nombre_completo']); ?></td>
                                <td style="padding:10px 15px; text-align:center;">
                                    <form method="POST" action="" onsubmit="return confirm('¿Seguro que querés eliminar a este empleado?');" style="display:inline;">
                                        <input type="hidden" name="eliminar_personal_limpieza" value="<?php echo $row_lim['id']; ?>">
                                        <button type="submit" class="at-btn" style="background:#ef4444; color:white; padding:5px 10px; font-size:12px;">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                            <tr><td colspan="3" style="padding:20px; text-align:center; color:#94a3b8;">No hay personal registrado. Agregalo desde la izquierda.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
<?php endif; ?>

<?php if ($tiene_papel): ?>
        <div class="at-panel" style="grid-column: 1 / -1; border: 2px dashed #f59e0b; background: #fffbeb;">
            <h2 class="at-header"><i class="fa-solid fa-flask" style="color: #d97706;"></i> Entorno de Pruebas (test_tamp88.php)</h2>
            <div class="at-flex-between" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div>
                    <strong style="color: #b45309; font-size: 1.1rem; display: block; margin-bottom: 5px;">Simular Tótem Sin Papel</strong>
                    <span style="font-size: 0.9rem; color: #92400e;">Al activar esto, test_get_servicios.php devolverá que no hay papel. No afecta a producción.</span>
                </div>
                <form method="POST" action="" style="margin: 0;">
                    <input type="hidden" name="toggle_simulacion_test" value="1">
                    <input type="hidden" name="estado_actual" value="<?php echo $simular_sin_papel_test; ?>">
                    <?php if($simular_sin_papel_test === 1): ?>
                        <button type="submit" class="at-btn at-btn-danger" style="background:#ef4444;"><i class="fa-solid fa-ban"></i> Desactivar Simulación</button>
                    <?php else: ?>
                        <button type="submit" class="at-btn at-btn-success" style="background:#10b981;"><i class="fa-solid fa-play"></i> Activar Simulación</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="at-panel" style="grid-column: 1 / -1;">
            <h2 class="at-header"><i class="fa-solid fa-receipt" style="color: #144973;"></i> Hardware (Rollo Tótem)</h2>
            <div class="hw-status">
                <div class="hw-counter"><?php echo $tickets_impresos; ?> <span class="hw-total">/ <?php echo $capacidad_rollo; ?></span></div>
                <div style="font-size: 0.9rem; font-weight: 800; color: #64748b; text-transform: uppercase;">TICKETS IMPRESOS</div>
                <div style="font-size: 0.8rem; font-weight: 700; color: #2563eb; margin-top: 5px;"><i class="fa-solid fa-history"></i> HISTORIAL: <?php echo $historial_totem; ?> ROLLOS UTILIZADOS</div>
                
                <?php $color_bar = ($porcentaje_papel < 20) ? '#ef4444' : '#10b981'; ?>
                <div class="hw-bar-bg">
                    <div class="hw-bar-fill" style="background: <?php echo $color_bar; ?>; width: <?php echo $porcentaje_papel; ?>%;"></div>
                </div>
                <div style="color: <?php echo $color_bar; ?>; font-weight: 900; font-size: 1.1rem; margin-bottom: 20px;">RESTANTE: <?php echo $porcentaje_papel; ?>%</div>
                
                <form method="POST" action="">
                    <button type="submit" name="resetear_papel" class="at-btn at-btn-success" onclick="return confirm('¿Colocaste un rollo nuevo en la máquina? Esto pondrá el contador en 0.')"><i class="fa-solid fa-rotate-left"></i> Registrar Rollo Nuevo (Reset a 0)</button>
                </form>
            </div>
            
            <div class="at-input-row">
                <form method="POST" action="" id="form-tkts" class="at-group">
                    <input type="hidden" name="guardar_tickets_manual" value="1">
                    <label class="at-label">Ajustar Impresos Manualmente</label>
                    <div class="at-input-with-button" style="display:flex; gap:10px;">
                        <input type="number" id="inp_tkts" name="cantidad_tickets_manual" value="<?php echo $tickets_impresos; ?>" class="at-input" style="text-align:center;">
                        <button type="button" class="at-btn-icon at-btn-primary" onclick="confirmarFuerza('form-tkts')"><i class="fa-solid fa-pen"></i></button>
                    </div>
                </form>
                <form method="POST" action="" id="form-cap" class="at-group">
                    <input type="hidden" name="guardar_capacidad_manual" value="1">
                    <label class="at-label">Ajustar Capacidad Total</label>
                    <div class="at-input-with-button" style="display:flex; gap:10px;">
                        <input type="number" id="inp_cap" name="capacidad_rollo_manual" value="<?php echo $capacidad_rollo; ?>" class="at-input" style="text-align:center;">
                        <button type="button" class="at-btn-icon at-btn-clear" onclick="confirmarFuerza('form-cap')"><i class="fa-solid fa-gear"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="at-panel" style="grid-column: 1 / -1;">
            <h2 class="at-header"><i class="fa-solid fa-print" style="color: #144973;"></i> Hardware (Rollo Ventanilla)</h2>
            <div class="hw-status">
                <div class="hw-counter"><?php echo $tickets_impresos_ven; ?> <span class="hw-total">/ <?php echo $capacidad_rollo_ven; ?></span></div>
                <div style="font-size: 0.9rem; font-weight: 800; color: #64748b; text-transform: uppercase;">TICKETS IMPRESOS (VENTANILLA)</div>
                <div style="font-size: 0.8rem; font-weight: 700; color: #2563eb; margin-top: 5px;"><i class="fa-solid fa-history"></i> HISTORIAL: <?php echo $historial_ven; ?> ROLLOS UTILIZADOS</div>
                
                <?php $color_bar_ven = ($porcentaje_papel_ven < 20) ? '#ef4444' : '#10b981'; ?>
                <div class="hw-bar-bg">
                    <div class="hw-bar-fill" style="background: <?php echo $color_bar_ven; ?>; width: <?php echo $porcentaje_papel_ven; ?>%;"></div>
                </div>
                <div style="color: <?php echo $color_bar_ven; ?>; font-weight: 900; font-size: 1.1rem; margin-bottom: 20px;">RESTANTE: <?php echo $porcentaje_papel_ven; ?>%</div>
                
                <form method="POST" action="">
                    <button type="submit" name="resetear_papel_ventanilla" class="at-btn at-btn-success" onclick="return confirm('¿Colocaste un rollo nuevo en Ventanilla? Esto pondrá el contador en 0.')"><i class="fa-solid fa-rotate-left"></i> Registrar Rollo Nuevo (Reset a 0)</button>
                </form>
            </div>
            
            <div class="at-input-row">
                <form method="POST" action="" id="form-tkts-ven" class="at-group">
                    <input type="hidden" name="guardar_tickets_manual_ventanilla" value="1">
                    <label class="at-label">Ajustar Impresos Manualmente</label>
                    <div class="at-input-with-button" style="display:flex; gap:10px;">
                        <input type="number" name="cantidad_tickets_manual_ventanilla" value="<?php echo $tickets_impresos_ven; ?>" class="at-input" style="text-align:center;">
                        <button type="button" class="at-btn-icon at-btn-primary" onclick="confirmarFuerza('form-tkts-ven')"><i class="fa-solid fa-pen"></i></button>
                    </div>
                </form>
                <form method="POST" action="" id="form-cap-ven" class="at-group">
                    <input type="hidden" name="guardar_capacidad_manual_ventanilla" value="1">
                    <label class="at-label">Ajustar Capacidad Total</label>
                    <div class="at-input-with-button" style="display:flex; gap:10px;">
                        <input type="number" name="capacidad_rollo_manual_ventanilla" value="<?php echo $capacidad_rollo_ven; ?>" class="at-input" style="text-align:center;">
                        <button type="button" class="at-btn-icon at-btn-clear" onclick="confirmarFuerza('form-cap-ven')"><i class="fa-solid fa-gear"></i></button>
                    </div>
                </form>
            </div>
        </div>
<?php endif; ?>

<?php if ($tiene_diseno): ?>
        <div class="at-panel" style="grid-column: 1 / -1;">
            <div style="display: grid; grid-template-columns: 1fr; gap: 30px;">
                <div style="padding-bottom: 25px; border-bottom: 2px dashed #e2e8f0;">
                    <h2 class="at-header"><i class="fa-solid fa-window-maximize" style="color: #144973;"></i> Interfaz de Actis en IOSFA</h2>
                    <div class="at-flex-between" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <div style="flex: 1; min-width: 250px;">
                            <div style="font-weight: 900; color: #0f172a; font-size: 1.1rem;">Visibilidad de Header y Footer</div>
                            <div style="font-size: 0.9rem; color: #64748b; font-weight: 500;">Muestra u oculta la barra superior y el pie de página de Actis al abrir el validador en la ventanilla.</div>
                        </div>
                        <form method="POST" action="" style="margin: 0; min-width: 100%;" class="at-group">
                            <input type="hidden" name="toggle_header_footer" value="1">
                            <input type="hidden" name="toggle_header_footer_val" value="<?php echo ($mostrar_hf_ven === 1) ? '0' : '1'; ?>">
                            <?php if($mostrar_hf_ven === 1): ?>
                                <button type="submit" class="at-btn at-btn-danger"><i class="fa-solid fa-eye-slash"></i> Apagar Interfaz</button>
                            <?php else: ?>
                                <button type="submit" class="at-btn at-btn-success"><i class="fa-solid fa-eye"></i> Encender Interfaz</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div>
                    <h2 class="at-header" style="border:none; padding:0; margin-bottom:10px;"><i class="fa-solid fa-ticket" style="color: #144973;"></i> Simulador de Tickets (Vista Previa)</h2>
                    <p style="font-size: 0.9rem; color: #64748b; font-weight: 500; margin-bottom: 15px;">Visualiza los tickets en pantalla sin afectar los contadores de papel ni registrar turnos falsos en la base de datos.</p>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <a href="imprimir_asistencia.php?servicio=ASISTENCIA&preview=1" target="_blank" class="at-btn at-btn-primary" style="width: 100%;"><i class="fa-solid fa-ticket"></i> Ver Ticket Asistencia</a>
                        <a href="imprimir_ticket_iofa.php?modo=VALIDACION&servicio=GENERAL&codigo=TOKENPRUEBA&dni=12345678&nombre=PACIENTE%20DE%20PRUEBA&preview=1" target="_blank" class="at-btn at-btn-clear" style="width: 100%; background:#0f172a;"><i class="fa-solid fa-qrcode"></i> Ver Ticket Validación</a>
                        <a href="totem_imprimir_primavera_dev.php?preview=1" target="_blank" class="at-btn" style="width: 100%; background:#ec4899; color:white; border-color:#db2777;"><i class="fa-solid fa-heart"></i> Ver Ticket Primavera 🌸</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="at-panel" style="grid-column: 1 / -1;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 class="at-header" style="margin: 0; border: none; padding: 0;"><i class="fa-solid fa-shapes" style="color: #144973;"></i> Gestión de Botones y Accesos Directos</h2>
                <form method="POST" action="" style="margin: 0;">
                    <input type="hidden" name="agregar_boton_nuevo" value="1">
                    <button type="submit" class="at-btn at-btn-primary" style="font-size: 0.9rem; padding: 8px 15px;"><i class="fa-solid fa-plus"></i> Agregar Nuevo Widget</button>
                </form>
            </div>
            <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                <p style="font-size: 0.9rem; color: #64748b; font-weight: 500;">
                    Desde aquí podés editar los textos, colores, íconos y el orden de los botones principales (Directorio, Mapa, etc.) y de los widgets secundarios (Autismo, Wifi).
                </p>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                <th style="padding: 15px; text-align: left; color: #64748b; font-weight: 800; font-size: 0.85rem; text-transform: uppercase;">Activo</th>
                                <th style="padding: 15px; text-align: left; color: #64748b; font-weight: 800; font-size: 0.85rem; text-transform: uppercase;">Grupo</th>
                                <th style="padding: 15px; text-align: left; color: #64748b; font-weight: 800; font-size: 0.85rem; text-transform: uppercase;">Ícono</th>
                                <th style="padding: 15px; text-align: left; color: #64748b; font-weight: 800; font-size: 0.85rem; text-transform: uppercase;">Título / Bajada</th>
                                <th style="padding: 15px; text-align: left; color: #64748b; font-weight: 800; font-size: 0.85rem; text-transform: uppercase;">Color</th>
                                <th style="padding: 15px; text-align: center; color: #64748b; font-weight: 800; font-size: 0.85rem; text-transform: uppercase;">Orden</th>
                                <th style="padding: 15px; text-align: right; color: #64748b; font-weight: 800; font-size: 0.85rem; text-transform: uppercase;">Guardar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($botones_lista && $botones_lista->num_rows > 0): while($btn = $botones_lista->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <form method="POST" action="">
                                    <input type="hidden" name="guardar_boton" value="1">
                                    <input type="hidden" name="id_boton" value="<?php echo $btn['id']; ?>">
                                    
                                    <td style="padding: 15px;">
                                        <label style="display:flex; align-items:center; cursor:pointer;">
                                            <input type="checkbox" name="activo" value="1" <?php echo $btn['activo'] ? 'checked' : ''; ?> style="width: 20px; height: 20px;">
                                        </label>
                                    </td>
                                    <td style="padding: 15px;">
                                        <span style="font-size: 0.8rem; font-weight: 700; background: #e0e7ff; color: #4338ca; padding: 4px 8px; border-radius: 6px; text-transform:uppercase;">
                                            <?php echo htmlspecialchars($btn['grupo']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 15px;">
                                        <input type="text" name="icono" value="<?php echo htmlspecialchars($btn['icono']); ?>" class="at-input" style="width: 120px;" placeholder="fa-icon">
                                        <i class="fa-solid <?php echo htmlspecialchars($btn['icono']); ?>" style="color: <?php echo htmlspecialchars($btn['color']); ?>; font-size: 1.2rem; margin-left: 10px;"></i>
                                    </td>
                                    <td style="padding: 15px;">
                                        <input type="text" name="titulo" value="<?php echo htmlspecialchars($btn['titulo']); ?>" class="at-input" style="width: 100%; margin-bottom: 5px; font-weight: bold;">
                                        <input type="text" name="bajada" value="<?php echo htmlspecialchars($btn['bajada']); ?>" class="at-input" style="width: 100%; font-size: 0.85rem; color: #64748b;">
                                    </td>
                                    <td style="padding: 15px;">
                                        <input type="color" name="color" value="<?php echo htmlspecialchars($btn['color']); ?>" style="width: 40px; height: 40px; border: none; cursor: pointer; border-radius: 8px; padding:0;">
                                    </td>
                                    <td style="padding: 15px; text-align: center;">
                                        <input type="number" name="orden" value="<?php echo $btn['orden']; ?>" class="at-input" style="width: 70px; text-align: center;">
                                    </td>
                                    <td style="padding: 15px; text-align: right;">
                                        <button type="submit" class="at-btn at-btn-success" style="padding: 8px 15px; font-size: 0.9rem;"><i class="fa-solid fa-save"></i></button>
                                    </td>
                                </form>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="at-panel" style="grid-column: 1 / -1;">
            <h2 class="at-header"><i class="fa-solid fa-images" style="color: #144973;"></i> Gestión de Carrusel (Tótem)</h2>
            <div style="display: grid; grid-template-columns: 1fr; gap: 30px;">
                <form method="POST" action="" enctype="multipart/form-data" style="background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px dashed #cbd5e1;">
                    <input type="hidden" name="agregar_carrusel" value="1">
                    <div class="at-input-row">
                        <div class="at-group">
                            <label class="at-label">Título (Opcional)</label>
                            <input type="text" name="titulo_carrusel" class="at-input" placeholder="Ej: Nuevo Servicio">
                        </div>
                        <div class="at-group">
                            <label class="at-label">Orden (Ej: 1, 2, 3)</label>
                            <input type="number" name="orden_carrusel" value="0" class="at-input">
                        </div>
                    </div>
                    <div class="at-group">
                        <label class="at-label">Descripción (Opcional)</label>
                        <input type="text" name="descripcion_carrusel" class="at-input" placeholder="Breve texto para la diapositiva">
                    </div>
                    <div class="at-group">
                        <label class="at-label">Imagen/s (PNG/JPG)</label>
                        <input type="file" name="imagen_carrusel[]" accept="image/*" multiple required class="at-input" style="background: white;">
                        <small style="color:#64748b;">Podés seleccionar múltiples archivos a la vez manteniendo presionado CTRL o seleccionando varios en la ventana de carga.</small>
                    </div>
                    <button type="submit" class="at-btn at-btn-success" style="margin-top: 10px;"><i class="fa-solid fa-cloud-arrow-up"></i> Subir Diapositiva(s)</button>
                </form>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
                    <?php if($carrusel_slides && $carrusel_slides->num_rows > 0): while($slide = $carrusel_slides->fetch_assoc()): ?>
                        <div style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                            <img src="uploads/carrusel/<?php echo $slide['imagen']; ?>" style="width: 100%; height: 150px; object-fit: cover; display: block;">
                            <div style="padding: 15px;">
                                <div style="font-weight: 800; color: #0f172a; margin-bottom: 5px;"><?php echo htmlspecialchars($slide['titulo'] ?: 'Sin título'); ?></div>
                                <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 15px;"><?php echo htmlspecialchars($slide['descripcion'] ?: 'Sin descripción'); ?></div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-size: 0.8rem; font-weight: 700; background: #e0e7ff; color: #4338ca; padding: 4px 8px; border-radius: 6px;">Orden: <?php echo $slide['orden']; ?></span>
                                    <a href="admin_totem.php?eliminar_carrusel=<?php echo $slide['id']; ?>" class="at-btn-icon at-btn-danger" style="width: 32px; height: 32px; font-size: 0.8rem;" onclick="return confirm('¿Eliminar esta imagen del carrusel?')"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; else: ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 30px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; color: #64748b; font-weight: 600;">
                            No hay imágenes en el carrusel.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PANEL: Tipografía Independiente por Sección -->
        <div class="at-panel" style="grid-column: 1 / -1;">
            <h2 class="at-header"><i class="fa-solid fa-text-height" style="color: #144973;"></i> Tipografía del Tótem — Control por Sección</h2>
            <form method="POST" action="" id="form-tipografia">
                <input type="hidden" name="guardar_tipografia" value="1">
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px;">

                    <?php
                    // Definición de grupos de sliders
                    $grupos_tipografia = [
                        [
                            'titulo' => '🖱️ Botones (panel izquierdo)',
                            'color'  => '#144973',
                            'bg'     => '#eff6ff',
                            'campos' => [
                                ['key' => 'fs_btn_titulo',  'label' => 'Título del botón',      'min' => 8,  'max' => 50, 'default' => 22],
                                ['key' => 'fs_btn_desc',    'label' => 'Descripción del botón', 'min' => 5,  'max' => 40, 'default' => 14],
                                ['key' => 'fs_btn_icono',   'label' => 'Ícono del botón',       'min' => 10, 'max' => 55, 'default' => 30],
                            ],
                        ],
                        [
                            'titulo' => '📦 Widgets (panel superior derecho)',
                            'color'  => '#0284c7',
                            'bg'     => '#f0f9ff',
                            'campos' => [
                                ['key' => 'fs_widget_titulo', 'label' => 'Título del widget',      'min' => 8, 'max' => 50, 'default' => 20],
                                ['key' => 'fs_widget_desc',   'label' => 'Texto / valor del widget','min' => 5, 'max' => 50, 'default' => 14],
                            ],
                        ],
                        [
                            'titulo' => '🕐 Reloj flotante',
                            'color'  => '#0f172a',
                            'bg'     => '#f8fafc',
                            'campos' => [
                                ['key' => 'fs_reloj_hora',  'label' => 'Hora (ej: 14:35)',  'min' => 10, 'max' => 60, 'default' => 32],
                                ['key' => 'fs_reloj_fecha', 'label' => 'Fecha (ej: LUN 24)','min' => 5,  'max' => 40, 'default' => 11],
                            ],
                        ],
                        [
                            'titulo' => '🪟 Títulos de Modales',
                            'color'  => '#8b5cf6',
                            'bg'     => '#faf5ff',
                            'campos' => [
                                ['key' => 'fs_modal_titulo', 'label' => 'Título del modal (barra superior)', 'min' => 10, 'max' => 60, 'default' => 40],
                            ],
                        ],
                        [
                            'titulo' => '🏥 Header / Marca del hospital',
                            'color'  => '#059669',
                            'bg'     => '#f0fdf4',
                            'campos' => [
                                ['key' => 'fs_header_nombre', 'label' => 'Nombre del hospital', 'min' => 10, 'max' => 55, 'default' => 28],
                                ['key' => 'fs_header_dir',    'label' => 'Dirección / subtítulo','min' => 5,  'max' => 40, 'default' => 14],
                            ],
                        ],
                    ];
                    foreach ($grupos_tipografia as $grupo): ?>

                    <div style="background: <?php echo $grupo['bg']; ?>; border: 2px solid <?php echo $grupo['color']; ?>22;
                                border-radius: 16px; padding: 20px; display: flex; flex-direction: column; gap: 16px;">
                        <div style="font-size: 0.9rem; font-weight: 900; color: <?php echo $grupo['color']; ?>;
                                    border-bottom: 2px solid <?php echo $grupo['color']; ?>33; padding-bottom: 10px;">
                            <?php echo $grupo['titulo']; ?>
                        </div>

                        <?php foreach ($grupo['campos'] as $campo): ?>
                        <div style="display: flex; flex-direction: column; gap: 6px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <label style="font-size: 0.78rem; font-weight: 700; color: #475569;">
                                    <?php echo $campo['label']; ?>
                                </label>
                                <span id="display-<?php echo $campo['key']; ?>"
                                      style="font-size: 0.85rem; font-weight: 900; color: <?php echo $grupo['color']; ?>;
                                             background: white; border: 1.5px solid <?php echo $grupo['color']; ?>;
                                             border-radius: 8px; padding: 2px 10px; min-width: 58px; text-align: center;">
                                    <?php echo ($admin_fs[$campo['key']] / 10.0); ?>vh
                                </span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700;"><?php echo ($campo['min']/10.0); ?>vh</span>
                                <input type="range"
                                       name="<?php echo $campo['key']; ?>"
                                       id="slider-<?php echo $campo['key']; ?>"
                                       min="<?php echo $campo['min']; ?>"
                                       max="<?php echo $campo['max']; ?>"
                                       step="1"
                                       value="<?php echo $admin_fs[$campo['key']]; ?>"
                                       style="flex: 1; height: 6px; cursor: pointer; accent-color: <?php echo $grupo['color']; ?>;"
                                       oninput="document.getElementById('display-<?php echo $campo['key']; ?>').textContent = (this.value/10.0).toFixed(1) + 'vh'">
                                <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700;"><?php echo ($campo['max']/10.0); ?>vh</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php endforeach; ?>

                </div>

                <button type="submit" class="at-btn at-btn-primary" style="width: 100%; padding: 14px; margin-top: 24px;">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar Tipografía del Tótem
                </button>
            </form>
        </div>

        <div class="at-panel" style="grid-column: 1 / -1;">
            <h2 class="at-header"><i class="fa-solid fa-file-pdf" style="color: #ef4444;"></i> Gestión de Documentos Descargables (PDF)</h2>
            <div style="display: grid; grid-template-columns: 1fr; gap: 30px;">
                <form method="POST" action="" enctype="multipart/form-data" style="background: #fef2f2; padding: 20px; border-radius: 12px; border: 1px dashed #fca5a5;">
                    <input type="hidden" name="agregar_documento" value="1">
                    <div class="at-input-row">
                        <div class="at-group">
                            <label class="at-label" style="color: #991b1b;">Título del Documento</label>
                            <input type="text" name="titulo_documento" class="at-input" placeholder="Ej: Ficha Odontológica" required style="border-color: #fca5a5;">
                        </div>
                        <div class="at-group" style="max-width: 220px;">
                            <label class="at-label" style="color: #991b1b;">Categoría</label>
                            <select name="categoria_documento" class="at-input" style="border-color: #fca5a5; cursor:pointer;">
                                <option value="recetas">📋 Recetas y Órdenes Médicas</option>
                                <option value="odontologia">🦷 Odontología</option>
                                <option value="otro">📁 Otros</option>
                            </select>
                        </div>
                    </div>
                    <div class="at-group">
                        <label class="at-label" style="color: #991b1b;">Archivo PDF</label>
                        <input type="file" name="archivo_documento" accept=".pdf" required class="at-input" style="background: white; border-color: #fca5a5;">
                    </div>
                    <button type="submit" class="at-btn at-btn-primary" style="margin-top: 10px; background: #ef4444; border-color: #dc2626;"><i class="fa-solid fa-cloud-arrow-up"></i> Subir PDF</button>
                </form>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                <th style="padding: 15px; text-align: left; color: #64748b; font-weight: 800; font-size: 0.85rem; text-transform: uppercase;">Activo</th>
                                <th style="padding: 15px; text-align: left; color: #64748b; font-weight: 800; font-size: 0.85rem; text-transform: uppercase;">Título</th>
                                <th style="padding: 15px; text-align: left; color: #64748b; font-weight: 800; font-size: 0.85rem; text-transform: uppercase;">Archivo</th>
                                <th style="padding: 15px; text-align: right; color: #64748b; font-weight: 800; font-size: 0.85rem; text-transform: uppercase;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($documentos_lista && $documentos_lista->num_rows > 0): while($doc = $documentos_lista->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 15px; width: 80px;">
                                    <form method="POST" action="" style="margin:0;">
                                        <input type="hidden" name="toggle_documento" value="1">
                                        <input type="hidden" name="id_documento" value="<?php echo $doc['id']; ?>">
                                        <label style="display:flex; align-items:center; cursor:pointer;">
                                            <input type="checkbox" name="activo_documento" value="1" <?php echo $doc['activo'] ? 'checked' : ''; ?> onchange="this.form.submit()" style="width: 20px; height: 20px;">
                                        </label>
                                    </form>
                                </td>
                                <td style="padding: 15px; font-weight: bold; color: #0f172a;">
                                    <?php echo htmlspecialchars($doc['titulo']); ?>
                                </td>
                                <td style="padding: 15px; font-size: 0.85rem; color: #64748b;">
                                    <a href="uploads/documentos/<?php echo htmlspecialchars($doc['archivo']); ?>" target="_blank" style="color: #2563eb; text-decoration: none;"><i class="fa-solid fa-external-link-alt"></i> Ver PDF</a>
                                </td>
                                <td style="padding: 15px; text-align: right;">
                                    <a href="admin_totem.php?eliminar_documento=<?php echo $doc['id']; ?>" class="at-btn-icon at-btn-danger" style="width: 32px; height: 32px; font-size: 0.8rem;" onclick="return confirm('¿Estás seguro de eliminar este documento de forma permanente?')"><i class="fa-solid fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 30px; color: #64748b; font-weight: 600;">No hay documentos subidos.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
<?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// ── confirmarFuerza ─────────────────────────────────────────────────────
function confirmarFuerza(idForm) {
    Swal.fire({
        title: '¿Confirmar Ajuste?',
        text: 'Vas a forzar los valores de control del hardware manualmente.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#144973',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sí, forzar valor',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) document.getElementById(idForm).submit();
    });
}
</script>
<?php require_once 'includes/footer.php'; ?>