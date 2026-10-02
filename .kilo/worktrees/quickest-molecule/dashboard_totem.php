�<?php
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once 'includes/conexion.php';

$pb_conn = new mysqli("localhost", "u415354546_papadebauty", "Fmg35911@", "u415354546_papadebauty");
$pb_juegos = [];
$pb_articulos = [];
if (!$pb_conn->connect_error) {
    $pb_conn->set_charset("utf8mb4");
    $res_j = $pb_conn->query("SELECT * FROM juegos WHERE activo = 1 ORDER BY updated_at DESC");
    if ($res_j) while($row = $res_j->fetch_assoc()) $pb_juegos[] = $row;
    
    $res_a = $pb_conn->query("SELECT a.*, c.nombre as categoria_nombre FROM articulos a LEFT JOIN categorias_blog c ON a.id_categoria = c.id ORDER BY a.fecha_publicacion DESC");
    if ($res_a) while($row = $res_a->fetch_assoc()) $pb_articulos[] = $row;
}

// Bloque AJAX para validar el PIN del guardia antes de ir a opciones de staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'validar_pin') {
    header('Content-Type: application/json');
    $pin = $conexion->real_escape_string(trim($_POST['pin'] ?? ''));
    try {
        $res_staff = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_dashboard_staff'");
        $pin_staff = ($res_staff && $res_staff->num_rows > 0) ? $res_staff->fetch_assoc()['estado'] : '12345';
        
        $res_soporte = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_soporte'");
        $pin_soporte = ($res_soporte && $res_soporte->num_rows > 0) ? $res_soporte->fetch_assoc()['estado'] : '35911';
        
        if ($pin === $pin_staff || $pin === $pin_soporte) {
            echo json_encode(['success' => true, 'redirect' => 'dashboard_totem.php?exit_totem=1']); 
        } else {
            echo json_encode(['success' => false, 'message' => 'PIN incorrecto.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error de conexión con la base de datos.']);
    }
    exit;
}
// --- L�GICA DE LOGIN Y MODO SETUP DEL T�TEM ---
if (isset($_GET['exit_totem']) && $_GET['exit_totem'] == '1') {
    setcookie("totem_active", "", time() - 3600, "/"); unset($_SESSION["totem_active"]);
    header("Location: dashboard_totem.php");
    exit();
}

if (isset($_GET['logout_totem']) && $_GET['logout_totem'] == '1') {
    setcookie("totem_active", "", time() - 3600, "/");
    session_destroy();
    header("Location: dashboard_totem.php");
    exit();
}

$totem_active = true; // el logoin de los usuairo de actis esta deshabilitado por ahora pero no borrar quiza se use luego

if (!$totem_active) {
    // FASE 1: LOGIN DEL OPERADOR
    if (!isset($_SESSION['usuario_id'])) {
        $error_login = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['totem_login'])) {
            $usuario = $conexion->real_escape_string(trim($_POST['usuario']));
            $password = $_POST['password']; // ACTIS usa pass en texto plano temporalmente
            
            $sql = "SELECT id, nombre_completo, rol_id FROM usuarios WHERE usuario = '$usuario' AND password = '$password' AND estado = 1";
            $res = $conexion->query($sql);

            if ($res->num_rows > 0) {
                $user = $res->fetch_assoc();
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nombre'] = $user['nombre_completo'];
                $_SESSION['usuario_rol'] = $user['rol_id'];
                header("Location: dashboard_totem.php");
                exit();
            } else {
                $error_login = "Credenciales incorrectas o usuario inactivo.";
            }
        }
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Login Tótem</title>
            <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                body { margin: 0; padding: 0; background: #0f172a; color: white; font-family: 'Outfit', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; overflow: hidden; }
                .login-box { background: rgba(30, 41, 59, 0.8); backdrop-filter: blur(10px); padding: 40px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); width: 100%; max-width: 400px; border: 1px solid rgba(255,255,255,0.1); text-align: center; }
                .login-box h2 { margin-top: 0; font-size: 2rem; color: #38bdf8; margin-bottom: 10px; font-weight: 900; }
                .login-box p { color: #94a3b8; margin-bottom: 30px; }
                .input-group { margin-bottom: 20px; text-align: left; position: relative; }
                .input-group i { position: absolute; top: 15px; left: 15px; color: #94a3b8; }
                .input-group input { width: 100%; padding: 15px 15px 15px 45px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: white; font-family: 'Outfit', sans-serif; font-size: 1rem; box-sizing: border-box; outline: none; transition: border-color 0.3s; }
                .input-group input:focus { border-color: #38bdf8; }
                .btn-login { width: 100%; padding: 15px; background: linear-gradient(135deg, #0284c7 0%, #2563eb 100%); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 10px 20px -5px rgba(37,99,235,0.5); }
                .btn-login:hover { transform: translateY(-3px); box-shadow: 0 15px 25px -5px rgba(37,99,235,0.6); }
                .error-msg { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
            </style>
        </head>
        <body>
            <div class="login-box">
                <i class="fa-solid fa-desktop" style="font-size: 3rem; color: #38bdf8; margin-bottom: 15px;"></i>
                <h2>T�TEM ACTIS</h2>
                <p>Acceso exclusivo para operadores</p>
                <?php if($error_login): ?><div class="error-msg"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error_login); ?></div><?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="totem_login" value="1">
                    <div class="input-group">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" name="usuario" placeholder="Usuario ACTIS" required autocomplete="off" autofocus>
                    </div>
                    <div class="input-group">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="password" placeholder="Contraseña" required>
                    </div>
                    <button type="submit" class="btn-login"><i class="fa-solid fa-right-to-bracket"></i> INGRESAR</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit();
    }

    // FASE 2: SETUP DEL T�TEM (Ya logueado)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['iniciar_totem'])) {
        // Usar COOKIE persistente de 10 años para que el totem no pierda la sesion jamas
        setcookie("totem_active", "1", [
            'expires' => time() + (10 * 365 * 24 * 60 * 60),
            'path' => '/',
            'secure' => true,
            'samesite' => 'None'
        ]);
        $_SESSION["totem_active"] = true;
        header("Location: dashboard_totem.php");
        exit();
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cerrar_sesion'])) {
        unset($_SESSION['usuario_id']);
        unset($_SESSION['usuario_nombre']);
        unset($_SESSION['usuario_rol']);
        header("Location: dashboard_totem.php");
        exit();
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup Tótem</title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { margin: 0; padding: 0; background: #0f172a; color: white; font-family: 'Outfit', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; overflow: hidden; }
            .setup-box { background: rgba(30, 41, 59, 0.8); backdrop-filter: blur(10px); padding: 50px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); width: 100%; max-width: 600px; border: 1px solid rgba(255,255,255,0.1); text-align: center; }
            .setup-box h2 { margin-top: 0; font-size: 2.2rem; color: #10b981; margin-bottom: 5px; font-weight: 900; text-transform: uppercase; }
            .setup-box p.operador { color: #94a3b8; margin-bottom: 40px; font-size: 1.1rem; }
            .btn-step { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 25px; margin-bottom: 20px; border-radius: 16px; font-family: 'Outfit', sans-serif; font-size: 1.3rem; font-weight: bold; cursor: pointer; transition: all 0.3s ease; box-sizing: border-box; text-decoration: none; border: none; }
            .btn-iosfa { background: #334155; color: white; border: 2px solid #475569; }
            .btn-iosfa:hover { background: #475569; border-color: #38bdf8; transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.3); }
            .btn-iosfa i.icon { color: #38bdf8; font-size: 1.8rem; }
            .btn-iniciar { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; box-shadow: 0 10px 25px -5px rgba(16,185,129,0.5); }
            .btn-iniciar:hover { transform: translateY(-3px); box-shadow: 0 15px 30px -5px rgba(16,185,129,0.6); background: linear-gradient(135deg, #059669 0%, #047857 100%); }
            .btn-iniciar i.icon { font-size: 1.8rem; }
            .btn-logout { background: transparent; color: #ef4444; border: 1px solid #ef4444; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; font-family: 'Outfit', sans-serif; transition: all 0.2s; margin-top: 20px; }
            .btn-logout:hover { background: #ef4444; color: white; }
        </style>
    </head>
    <body>
        <div class="setup-box">
            <i class="fa-solid fa-gears" style="font-size: 3.5rem; color: #10b981; margin-bottom: 15px;"></i>
            <h2>Configuración del Tótem</h2>
            <p class="operador"><i class="fa-solid fa-user-shield"></i> Operador logueado: <strong><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></strong></p>
            
            <a href="https://validador.iosfa.gob.ar/ValidadorDni" target="_self" class="btn-step btn-iosfa">
                <div style="display:flex; align-items:center; gap: 15px;"><i class="fa-solid fa-globe icon"></i> <span>Paso 1: Abrir sesión en OSFA</span></div>
                <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 1rem; opacity: 0.5;"></i>
            </a>
            
            <form method="POST">
                <button type="submit" name="iniciar_totem" class="btn-step btn-iniciar">
                    <div style="display:flex; align-items:center; gap: 15px;"><i class="fa-solid fa-power-off icon"></i> <span>Paso 2: Iniciar Tótem</span></div>
                    <i class="fa-solid fa-chevron-right" style="opacity: 0.8;"></i>
                </button>
            </form>
            
        </div>
    </body>
    </html>
    <?php
    exit();
}
// --- FIN L GICA DE LOGIN Y SETUP ---
// Cargar Configuración Dinámica (Logo y Carrusel)
$logo_url = 'img/osfa.png';
$slides = [];
// Leer tipografía independiente por sección
$fs_defaults = [
    'fs_btn_titulo'    => 22,  // 2.2vh — título de botones
    'fs_btn_desc'      => 14,  // 1.4vh — descripción de botones
    'fs_btn_icono'     => 30,  // 3.0vh — ícono de botones
    'fs_widget_titulo' => 20,  // 2.0vh — título de widgets
    'fs_widget_desc'   => 14,  // 1.4vh — descripción de widgets
    'fs_reloj_hora'    => 32,  // 3.2vh — hora del reloj
    'fs_reloj_fecha'   => 11,  // 1.1vh — fecha del reloj
    'fs_modal_titulo'  => 40,  // 4.0vh — título de modales
    'fs_header_nombre' => 28,  // 2.8vh — nombre del hospital
    'fs_header_dir'    => 14,  // 1.4vh — dirección del hospital
];
$fs_vals = [];
foreach ($fs_defaults as $_fk => $_fdef) {
    $_r = $conexion->query("SELECT estado FROM totem_config WHERE tipo = '$_fk' ORDER BY id DESC LIMIT 1");
    $fs_vals[$_fk] = ($_r && $_r->num_rows > 0) ? max(5, min(100, (int)$_r->fetch_assoc()['estado'])) : $_fdef;
}
$res_carrusel = $conexion->query("SELECT * FROM totem_carrusel WHERE activo = 1 ORDER BY orden ASC, id DESC");
if ($res_carrusel && $res_carrusel->num_rows > 0) {
    while($row = $res_carrusel->fetch_assoc()) {
        $slides[] = [
            'imagen' => 'uploads/carrusel/' . $row['imagen'],
            'titulo' => $row['titulo'],
            'descripcion' => $row['descripcion']
        ];
    }
}

$botones_accion = [];
$botones_widgets = [];
$res_botones = $conexion->query("SELECT * FROM totem_botones WHERE activo = 1 ORDER BY orden ASC");
if ($res_botones && $res_botones->num_rows > 0) {
    while($row = $res_botones->fetch_assoc()) {
        $btn_data = [
            "id" => $row['identificador'],
            "titulo" => $row['titulo'],
            "bajada" => $row['bajada'],
            "icono" => $row['icono'],
            "color" => $row['color'],
            "tipo" => $row['accion_tipo'],
            "data" => $row['accion_data']
        ];
        if ($row['grupo'] === 'principal') {
            $botones_accion[] = $btn_data;
        } else {
            $botones_widgets[] = $btn_data;
        }
    }
}
shuffle($slides);

function hexToRgba($hex, $alpha = 0.15) {
    $hex = str_replace("#", "", $hex);
    if(strlen($hex) == 3) {
        $r = hexdec(substr($hex,0,1).substr($hex,0,1));
        $g = hexdec(substr($hex,1,1).substr($hex,1,1));
        $b = hexdec(substr($hex,2,1).substr($hex,2,1));
    } else {
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
    }
    return "rgba($r, $g, $b, $alpha)";
}

if(empty($slides)) {
    // Diapositiva por defecto si no hay ninguna subida
    $slides[] = [
        'imagen' => 'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?q=80&w=2000&auto=format&fit=crop',
        'titulo' => 'Información Institucional',
        'descripcion' => 'Manténgase al tanto de nuestras campañas de salud y prevención.'
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>OSFA - Policlínica General Actis</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        :root {
            --bg-light: #f8fafc;
            --panel-bg: #ffffff;
            --border-color: #e2e8f0;
            --primary: #0284c7; /* IOSFA Blue */
            --primary-light: #e0f2fe;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            
            /* Colores de acento cálidos */
            --accent-green: #10b981;
            --accent-green-bg: #d1fae5;
            --accent-orange: #f59e0b;
            --accent-orange-bg: #fef3c7;
            --accent-purple: #8b5cf6;
            --accent-purple-bg: #ede9fe;
            --accent-red: #ef4444;
            --accent-red-bg: #fee2e2;

            /* ── TIPOGRAFÍA INDEPENDIENTE POR SECCIÓN ─────────────────────
               Cada variable se controla por separado desde admin_totem.php
            ─────────────────────────────────────────────────────────────── */
            --fs-btn-titulo:    <?php echo ($fs_vals['fs_btn_titulo']    / 10.0); ?>vh;
            --fs-btn-desc:      <?php echo ($fs_vals['fs_btn_desc']      / 10.0); ?>vh;
            --fs-btn-icono:     <?php echo ($fs_vals['fs_btn_icono']     / 10.0); ?>vh;
            --fs-widget-titulo: <?php echo ($fs_vals['fs_widget_titulo'] / 10.0); ?>vh;
            --fs-widget-desc:   <?php echo ($fs_vals['fs_widget_desc']   / 10.0); ?>vh;
            --fs-reloj-hora:    <?php echo ($fs_vals['fs_reloj_hora']    / 10.0); ?>vh;
            --fs-reloj-fecha:   <?php echo ($fs_vals['fs_reloj_fecha']   / 10.0); ?>vh;
            --fs-modal-titulo:  <?php echo ($fs_vals['fs_modal_titulo']  / 10.0); ?>vh;
            --fs-header-nombre: <?php echo ($fs_vals['fs_header_nombre'] / 10.0); ?>vh;
            --fs-header-dir:    <?php echo ($fs_vals['fs_header_dir']    / 10.0); ?>vh;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Outfit', sans-serif; }

        body, html {
            height: 100%; width: 100%; overflow: hidden;
            background-color: var(--bg-light); color: var(--text-main);
            user-select: none; -webkit-user-drag: none;
            overscroll-behavior: none; /* Bloquea navegación táctil Swipe */
            overscroll-behavior-x: none;
            touch-action: pan-y; /* Bloquea gestos horizontales nativos */
        }

        .dashboard-container {
            display: flex; height: 100vh; width: 100vw; padding: 3vh; gap: 3vh;
        }

        /* PANEL IZQUIERDO: Acciones (35%) */
        .left-panel {
            width: 35%; display: flex; flex-direction: column; gap: 2vh; position: relative; z-index: 10;
        }

        .brand-header {
            background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 20px;
            padding: 3vh 2vw; display: flex; align-items: center; gap: 1vw; box-shadow: var(--shadow-sm);
        }
        .brand-header i { font-size: 4vh; color: var(--primary); }
        .brand-header h1 { font-size: var(--fs-header-nombre); font-weight: 900; margin: 0; line-height: 1.1; color: var(--primary); }
        .brand-header p  { font-size: var(--fs-header-dir);    color: var(--text-muted); margin: 0; font-weight: 600; }

        .action-list {
            flex: 1; display: flex; flex-direction: column; gap: 0; overflow-y: auto; padding-right: 5px;
            justify-content: space-between;
        }
        .action-list::-webkit-scrollbar { display: none; }
        .action-list .action-btn { flex: 1; margin-bottom: 1.5vh; }
        .action-list .action-btn:last-child { margin-bottom: 0; }

        .action-btn {
            background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 18px;
            padding: 2.5vh 1.5vw; display: flex; align-items: center; gap: 1.5vw;
            cursor: pointer; transition: all 0.2s ease; box-shadow: var(--shadow-sm);
            position: relative; overflow: hidden;
        }
        .action-btn::before {
            content: ''; position: absolute; top: 0; left: 0; width: 6px; height: 100%; background: var(--primary);
            opacity: 0; transition: 0.2s;
        }
        .action-btn:active { transform: scale(0.97); }
        .action-btn:active::before { opacity: 1; }
        
        .action-icon {
            width: 7vh; height: 7vh; border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: var(--fs-btn-icono);
        }
        
        /* Estilos dinámicos para los botones generados desde JSON */
        <?php foreach($botones_accion as $btn): ?>
        .btn-<?php echo $btn['id']; ?> { border-left: 4px solid <?php echo $btn['color']; ?>; }
        .btn-<?php echo $btn['id']; ?> .action-icon { background: <?php echo hexToRgba($btn['color'], 0.15); ?>; color: <?php echo $btn['color']; ?>; }
        .btn-<?php echo $btn['id']; ?>:active { background: <?php echo hexToRgba($btn['color'], 0.25); ?>; }
        .btn-<?php echo $btn['id']; ?>::before { background: <?php echo $btn['color']; ?>; }
        <?php endforeach; ?>

        .action-text { flex: 1; }
        .action-text h3 { font-size: var(--fs-btn-titulo); font-weight: 800; margin: 0 0 0.5vh 0; color: var(--text-main); }
        .action-text p  { font-size: var(--fs-btn-desc);   color: var(--text-muted); margin: 0; line-height: 1.3; font-weight: 500; }

        /* PANEL DERECHO: Información (65%) */
        .right-panel {
            width: 65%; display: flex; flex-direction: column; gap: 2.5vh; position: relative; z-index: 10;
        }

        .info-top-row {
            display: flex; gap: 2.5vh; height: 25%;
        }

        .widget {
            background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 20px;
            padding: 3vh 2vw; display: flex; flex-direction: column; justify-content: center; box-shadow: var(--shadow-sm);
        }
        
        .weather-widget { flex: 1; flex-direction: row; align-items: center; justify-content: space-between; }
        .weather-left h2 { font-size: 5vh; font-weight: 900; margin: 0; color: var(--primary); }
        .weather-left p { font-size: 1.6vh; color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin: 0; }
        .weather-right i { font-size: 6vh; color: #f59e0b; }

        .contact-widget      { flex: 1.8; background: #fff; border-left: 6px solid var(--accent-green); }
        .contact-widget.wifi { flex: 0.65; }
        .contact-widget h4 { font-size: 1.6vh; color: var(--text-muted); text-transform: uppercase; margin: 0 0 1vh 0; }
        .contact-widget .contact-val { font-size: 2.5vh; font-weight: 800; color: var(--text-main); margin: 0; }
        .contact-widget .contact-val i { color: var(--accent-green); margin-right: 10px; }

        .wifi-widget { flex: 1; border-left: 6px solid var(--primary); display: flex; flex-direction: row; align-items: center; justify-content: space-between; }
        .wifi-widget .contact-val i { color: var(--primary); }

        /* Carrusel Dinámico */
        .carousel-widget {
            flex: 1; border-radius: 20px; position: relative; overflow: hidden; border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm); background: #000;
        }
        .carousel-slide {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background-size: cover; background-position: center;
            opacity: 0; transition: opacity 1s ease-in-out;
        }
        .carousel-slide.active { opacity: 1; }
        .carousel-overlay {
            position: absolute; bottom: 0; left: 0; width: 100%;
            background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
            padding: 5vh 3vw; color: white; opacity: 0; transition: opacity 1s ease;
        }
        .carousel-slide.active .carousel-overlay { opacity: 1; }
        
        .carousel-overlay h2 { font-size: 4vh; margin: 0 0 1vh 0; font-weight: 800; text-shadow: 0 2px 4px rgba(0,0,0,0.5); }
        .carousel-overlay p { font-size: 2vh; margin: 0; text-shadow: 0 1px 3px rgba(0,0,0,0.5); color: #e2e8f0; }

        .carousel-btn {
            position: absolute; top: 50%; transform: translateY(-50%);
            background: rgba(0,0,0,0.4); color: white; border: none;
            width: 6vh; height: 6vh; border-radius: 50%; font-size: 3vh;
            cursor: pointer; z-index: 20; transition: 0.2s;
            display: flex; align-items: center; justify-content: center;
        }
        .carousel-btn:hover { background: rgba(0,0,0,0.8); scale: 1.1; }
        .carousel-btn.prev { left: 2vw; }
        .carousel-btn.next { right: 2vw; }

        .btn-staff-red .action-icon { background: var(--accent-red-bg); color: var(--accent-red); }
        .btn-staff-red:active { background: var(--accent-red-bg); }
        .btn-staff-red::before { background: var(--accent-red); }
        /* Efecto peligro/restringido */
        .btn-staff-red { border-left: 4px solid var(--accent-red); }

        /* Modales Interactivos (Directorio, Contacto) */
        .fullscreen-overlay {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(15, 23, 42, 0.9); /* Sin blur para optimizar rendimiento */
            z-index: 100000; display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: 0.2s;
        }
        .fullscreen-overlay.active { opacity: 1; pointer-events: all; }

        .modal-card {
            background: #fff; width: 90vw; height: 90vh; border-radius: 30px;
            box-shadow: var(--shadow-lg); display: flex; flex-direction: column; overflow: hidden;
            transform: scale(0.95); transition: 0.2s ease;
        }
        .fullscreen-overlay.active .modal-card { transform: scale(1); }

        .modal-header {
            background: var(--primary); color: #fff; padding: 4vh; display: flex; justify-content: space-between; align-items: center;
        }
        .modal-header h2 { font-size: var(--fs-modal-titulo); font-weight: 800; margin: 0; }
        .btn-close {
            background: rgba(255,255,255,0.2); border: none; color: white; font-size: 3vh;
            width: 6vh; height: 6vh; border-radius: 50%; cursor: pointer; transition: 0.2s;
        }
        .btn-close:active { transform: scale(0.9); background: var(--accent-red); }

        .modal-body {
            flex: 1; overflow-y: auto; padding: 4vh; background: var(--bg-light);
        }
        
        /* Grid de Directorio Médico */
        .doctor-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 3vh;
        }
        .doctor-card {
            background: #fff; border: 1px solid var(--border-color); border-radius: 15px; padding: 3vh;
            border-left: 5px solid var(--primary); box-shadow: var(--shadow-sm); position: relative;
        }
        .doctor-card h4 { color: var(--primary); font-size: 1.8vh; text-transform: uppercase; margin-bottom: 1vh; }
        .doctor-card h3 { font-size: 2.2vh; color: var(--text-main); font-weight: 800; margin-bottom: 1vh; }
        .doctor-card p { font-size: 1.6vh; color: var(--text-muted); line-height: 1.4; }
        
        .badge-turno { position: absolute; top: 15px; right: 15px; background: var(--accent-green-bg); color: var(--accent-green); padding: 5px 10px; border-radius: 10px; font-size: 1.2vh; font-weight: bold; }

        /* Info Contacto */
        .contact-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4vh; }
        .contact-box { background: #fff; padding: 4vh; border-radius: 20px; text-align: center; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); }
        .contact-box i { font-size: 6vh; margin-bottom: 2vh; }
        .contact-box h3 { font-size: 2.5vh; margin-bottom: 1vh; }
        .contact-box p { font-size: 2vh; color: var(--text-muted); font-weight: 600; }

        /* Teclado Staff */
        .pin-display { font-size: 6vh; letter-spacing: 2vw; color: var(--primary); font-weight: 900; background: transparent; border: none; border-bottom: 4px solid var(--primary); text-align: center; width: 40vw; max-width: 400px; margin-bottom: 4vh; outline: none; }
        .numpad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2vh; width: 100%; max-width: 450px; margin: 0 auto; }
        .num-btn { background: #f1f5f9; border: 1px solid #cbd5e1; color: var(--text-main); font-size: 5vh; font-weight: 900; padding: 4vh 0; border-radius: 15px; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 0 #cbd5e1; transition: 0.1s; }
        .num-btn:active { transform: translateY(4px); box-shadow: 0 0 0 #cbd5e1; background: #e2e8f0; }

        /* Filtros de directorio */
        .filter-bar { display: flex; gap: 1vw; margin-bottom: 3vh; overflow-x: auto; padding-bottom: 1vh; touch-action: pan-x pan-y; }
        .filter-btn { background: #fff; border: 1px solid var(--border-color); color: var(--text-muted); padding: 1.5vh 2vw; border-radius: 20px; font-weight: 700; cursor: pointer; white-space: nowrap; box-shadow: var(--shadow-sm); transition: 0.2s; }
        .filter-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }

        /* Teclado Virtual Email GIGANTE para Tótem */
        .kb-key { background: #fff; border: 1px solid #cbd5e1; color: var(--text-main); font-size: 3.5vh; font-weight: 900; flex: 1; height: 8vh; border-radius: 12px; cursor: pointer; transition: 0.1s; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .kb-key:active { background: var(--primary); color: #fff; transform: scale(0.95); box-shadow: 0 2px 3px rgba(0,0,0,0.1); }

        /* Fix SweetAlert z-index para que se vea sobre el modal */
        .swal2-container { z-index: 200000 !important; }

    </style>
</head>
<body onload="initTotem()">

    <div class="dashboard-container">
        
        <!-- PANEL IZQUIERDO -->
        <div class="left-panel">
            <div class="brand-header">
                <img src="<?php echo htmlspecialchars($logo_url); ?>" style="height: 6vh; max-width: 100%; object-fit: contain;">
                <div style="flex: 1;">
                    <h1>POLICLÍNICA ACTIS</h1>
                    <p>Av. Rivadavia 4283, CABA</p>
                </div>
                <!-- Acceso Restringido Oculto -->
                <div onclick="openStaffModal()" style="color: var(--border-color); cursor: pointer; padding: 10px;">
                    <i class="fa-solid fa-lock" style="font-size: 2vh;"></i>
                </div>
            </div>
            

            <div class="action-list">
                <!-- BOTONES DINÁMICOS JSON -->
                <?php foreach($botones_accion as $btn): ?>
                    <?php 
                        $onclick = "";
                        if ($btn['tipo'] === 'modal') {
                            if ($btn['data'] === 'modal-directorio') {
                                $onclick = "onclick=\"openDirectorio()\"";
                            } elseif ($btn['data'] === 'modal-contacto') {
                                $onclick = "onclick=\"openContacto()\"";
                            } else {
                                $onclick = "onclick=\"document.getElementById('{$btn['data']}').classList.add('active');\"";
                            }
                        } else if ($btn['tipo'] === 'qr') {
                            if ($btn['id'] === 'quejas') {
                                $onclick = "onclick=\"window.location.href='https://validador.iosfa.gob.ar/ValidadorDni?modo=sugerencias'\"";
                            } else {
                                $onclick = "onclick=\"showInfoQRModal('".htmlspecialchars($btn['titulo'], ENT_QUOTES)."', '".htmlspecialchars($btn['bajada'], ENT_QUOTES)."', '".htmlspecialchars($btn['data'], ENT_QUOTES)."', '{$btn['icono']}', '{$btn['color']}')\"";
                            }
                        } else if ($btn['tipo'] === 'js') {
                            $onclick = "onclick=\"{$btn['data']}\"";
                        }
                    ?>
                    <div class="action-btn btn-<?php echo $btn['id']; ?>" <?php echo $onclick; ?>>
                        <div class="action-icon"><i class="fa-solid <?php echo htmlspecialchars($btn['icono']); ?>"></i></div>
                        <div class="action-text">
                            <h3 style="color: #0f172a;"><?php echo htmlspecialchars($btn['titulo']); ?></h3>
                            <p><?php echo htmlspecialchars($btn['bajada']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- PANEL DERECHO -->
        <div class="right-panel">
            <div class="info-top-row">
                <?php foreach($botones_widgets as $widget): ?>
                    <?php
                        $onclick = "";
                        if ($widget['tipo'] === 'js') {
                            $onclick = "onclick=\"{$widget['data']}\" onmousedown=\"this.style.transform='scale(0.95)'\" onmouseup=\"this.style.transform='scale(1)'\" onmouseleave=\"this.style.transform='scale(1)'\"";
                        } elseif ($widget['tipo'] === 'wifi_qr') {
                            $onclick = "onmousedown=\"this.style.transform='scale(0.95)'\" onmouseup=\"this.style.transform='scale(1)'\" onmouseleave=\"this.style.transform='scale(1)'\"";
                        } elseif ($widget['tipo'] === 'qr') {
                            $t = htmlspecialchars($widget['titulo'], ENT_QUOTES);
                            $b = htmlspecialchars($widget['bajada'], ENT_QUOTES);
                            $d = htmlspecialchars($widget['data'], ENT_QUOTES);
                            $ic = htmlspecialchars($widget['icono'], ENT_QUOTES);
                            $col = htmlspecialchars($widget['color'], ENT_QUOTES);
                            $onclick = "onclick=\"showInfoQRModal('{$t}', '{$b}', '{$d}', '{$ic}', '{$col}')\" onmousedown=\"this.style.transform='scale(0.95)'\" onmouseup=\"this.style.transform='scale(1)'\" onmouseleave=\"this.style.transform='scale(1)'\"";
                        }
                        $bg_color = hexToRgba($widget['color'], 0.1);
                        $border_color = hexToRgba($widget['color'], 0.2);
                    ?>
                    <div class="widget contact-widget<?php echo ($widget['tipo'] === 'wifi_qr') ? ' wifi' : ''; ?>" style="cursor: <?php echo $onclick ? 'pointer' : 'default'; ?>; transition: 0.2s; background: <?php echo $bg_color; ?>; border: 2px solid <?php echo $border_color; ?>; border-left: 6px solid <?php echo htmlspecialchars($widget['color']); ?>; display: flex; flex-direction: column; justify-content: flex-start; padding-top: 2.5vh; position: relative; overflow: hidden;" <?php echo $onclick; ?>>
                        <i class="fa-solid <?php echo htmlspecialchars($widget['icono']); ?>" style="position: absolute; right: -2vh; bottom: -4vh; font-size: 14vh; color: <?php echo htmlspecialchars($widget['color']); ?>; opacity: 0.15;"></i>
                        <h4 style="color: <?php echo htmlspecialchars($widget['color']); ?>; font-weight: 900; font-size: var(--fs-widget-titulo); white-space: normal; line-height: 1.2; margin: 0 0 1vh 0;"><i class="fa-solid <?php echo htmlspecialchars($widget['icono']); ?>" style="margin-right: 8px;"></i><?php echo htmlspecialchars($widget['titulo']); ?></h4>

                        <?php if ($widget['tipo'] === 'wifi_qr'):
                            $tiene_bajada = !empty(trim($widget['bajada']));
                        ?>
                            <div style="display: flex; flex-direction: <?php echo $tiene_bajada ? 'row' : 'column'; ?>; justify-content: <?php echo $tiene_bajada ? 'space-between' : 'center'; ?>; align-items: center; flex: 1; z-index: 2; position: relative; gap: 1vh;">
                                <?php if ($tiene_bajada): ?>
                                <p class="contact-val" style="font-size: var(--fs-widget-desc); color: #0f172a; font-weight: 900;"><?php echo htmlspecialchars($widget['bajada']); ?></p>
                                <?php endif; ?>
                                <div id="inline-qr-wifi" style="background:#fff; padding: 4px; border-radius: 8px; border: 1px solid var(--border-color); width: <?php echo $tiene_bajada ? '6vh' : '8vh'; ?>; height: <?php echo $tiene_bajada ? '6vh' : '8vh'; ?>; display: flex; align-items: center; justify-content: center;"></div>
                            </div>
                        <?php else: ?>
                            <p style="font-size: var(--fs-widget-desc); color: #334155; margin: 0; font-weight: 700; position: relative; z-index: 2; line-height: 1.3;"><?php echo htmlspecialchars($widget['bajada']); ?></p>
                        <?php endif; ?>
                    </div>

                <?php endforeach; ?>
            </div>

            <!-- CARRUSEL DINÁMICO JSON + RELOJ FLOTANTE -->
            <div class="carousel-widget" id="main-carousel" style="position: relative;">
                <!-- Reloj flotante semi-transparente -->
                <div id="floating-clock" style="
                    position: absolute; top: 0; right: 0; z-index: 20;
                    background: rgba(15,23,42,0.45);
                    backdrop-filter: blur(8px);
                    -webkit-backdrop-filter: blur(8px);
                    border-radius: 0 20px 0 16px;
                    padding: 1.2vh 1.8vw;
                    text-align: right;
                    pointer-events: none;
                ">
                    <div id="clock" style="font-size: var(--fs-reloj-hora); font-weight: 900; color: rgba(255,255,255,0.90); letter-spacing: 1px; line-height: 1;">00:00</div>
                    <div id="date"  style="font-size: var(--fs-reloj-fecha); color: rgba(255,255,255,0.55); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: 2px;">Cargando...</div>
                </div>
                <?php foreach($slides as $index => $slide): ?>
                    <div class="carousel-slide <?php echo $index === 0 ? 'active' : ''; ?>" style="background-image: url('<?php echo htmlspecialchars($slide['imagen']); ?>');">
                        <?php if(!empty($slide['titulo']) || !empty($slide['descripcion'])): ?>
                        <div class="carousel-overlay">
                            <?php if(!empty($slide['titulo'])): ?><h2><?php echo htmlspecialchars($slide['titulo']); ?></h2><?php endif; ?>
                            <?php if(!empty($slide['descripcion'])): ?><p><?php echo nl2br(htmlspecialchars($slide['descripcion'])); ?></p><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <!-- Botones al final del DOM con z-index alto -->
                <button class="carousel-btn prev" style="z-index: 999;" onclick="changeSlide(-1);"><i class="fa-solid fa-chevron-left"></i></button>
                <button class="carousel-btn next" style="z-index: 999;" onclick="changeSlide(1);"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <!-- MODAL DIRECTORIO M�0DICO -->
    <div id="modal-directorio" class="fullscreen-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h2><i class="fa-solid fa-user-doctor" style="margin-right:15px;"></i> Directorio Médico - Policlínica Actis</h2>
                <button class="btn-close" onclick="closeOverlays()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="filter-bar" id="filter-container">
                    <button class="filter-btn active" onclick="renderDoctors('Todas')">Todas</button>
                    <!-- Filtros generados dinámicamente -->
                </div>
                <div class="doctor-grid" id="doctor-grid">
                    <!-- Cards generadas dinámicamente -->
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL CONSULTAR TURNOS (Diseño Idéntico a Info/Turismo) -->
    <div id="modal-consultar-turnos" class="fullscreen-overlay">
        <div class="modal-card" style="width: 80vw; height: 70vh; display: flex; flex-direction: row; padding: 0; overflow: hidden; border-radius: 30px;">
            
            <!-- Izquierda: Información -->
            <div style="flex: 1.2; background: #f8fafc; padding: 6vh; display: flex; flex-direction: column; justify-content: center; border-right: 2px solid #e2e8f0; position: relative;">
                <div style="width: 12vh; height: 12vh; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 3vh; font-size: 5vh; color: white; background: var(--primary); box-shadow: 0 10px 25px rgba(2, 132, 199, 0.3);">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <h2 style="color: #0f172a; font-size: 5vh; font-weight: 900; line-height: 1.1; margin-bottom: 2vh; text-transform: uppercase;">MIS TURNOS</h2>
                <p style="color: #64748b; font-size: 2.5vh; line-height: 1.5; margin-bottom: 4vh; font-weight: 600;">Consulte sus próximos turnos e imprímalos o envíelos por correo.</p>
                <div style="background: white; border: 1px solid #cbd5e1; padding: 3vh; border-radius: 15px; display: flex; gap: 2vh; align-items: center;">
                    <i class="fa-solid fa-circle-info" style="color: #10b981; font-size: 4vh;"></i>
                    <p style="margin:0; font-size: 2vh; color: #334155; font-weight: 600;">Ingrese su DNI sin puntos para buscar. Luego podrá ver o enviar sus turnos.</p>
                </div>
            </div>

            <!-- Derecha: Teclado y Resultados -->
            <div style="flex: 1; background: white; padding: 4vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; position: relative;">
                
                <!-- SECCI�N DNI -->
                <div id="turnos-dni-section" style="width: 100%; max-width: 350px;">
                    <h3 style="color: var(--primary); font-size: 3vh; margin-bottom: 1vh;"><i class="fa-solid fa-id-card"></i> Ingrese su DNI</h3>
                    <input type="text" id="turnos-dni-input" readonly style="width: 100%; height: 6vh; font-size: 4vh; text-align: center; border-radius: 15px; border: 2px solid #cbd5e1; margin-bottom: 2vh; background: #f1f5f9; letter-spacing: 5px; color: #0f172a; font-weight: 900; box-shadow: inset 0 2px 5px rgba(0,0,0,0.05); outline: none;">
                    
                    <div class="numpad" style="max-width: 350px; gap: 1.5vh;">
                        <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addTurnosDni('1')">1</button>
                        <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addTurnosDni('2')">2</button>
                        <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addTurnosDni('3')">3</button>
                        <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addTurnosDni('4')">4</button>
                        <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addTurnosDni('5')">5</button>
                        <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addTurnosDni('6')">6</button>
                        <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addTurnosDni('7')">7</button>
                        <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addTurnosDni('8')">8</button>
                        <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addTurnosDni('9')">9</button>
                        <button class="num-btn" onclick="clearTurnosDni()" style="padding: 2vh 0; font-size: 3vh; background: #ef4444; color: white; box-shadow: 0 4px 0 #b91c1c;"><i class="fa-solid fa-eraser"></i></button>
                        <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addTurnosDni('0')">0</button>
                        <button class="num-btn" onclick="buscarMisTurnos()" style="padding: 2vh 0; font-size: 3vh; background: #0284c7; color: white; box-shadow: 0 4px 0 #0369a1;"><i class="fa-solid fa-search"></i></button>
                    </div>

                    <div id="turnos-loading" style="display:none; margin-top: 1.5vh; color: var(--primary); font-weight: 800; font-size: 2vh;">
                        <i class="fa-solid fa-spinner fa-spin"></i> Buscando...
                    </div>

                    <button onclick="closeOverlays()" style="width: 100%; padding: 2vh; background: #ef4444; color: white; border: none; border-radius: 15px; font-size: 2.2vh; font-weight: 900; cursor: pointer; box-shadow: 0 5px 0 #b91c1c; transition: all 0.2s; text-transform: uppercase; margin-top: 2vh;">
                        <i class="fa-solid fa-arrow-left" style="margin-right:10px;"></i> Volver al Inicio
                    </button>
                </div>

                <!-- SECCI�N RESULTADOS -->
                <div id="turnos-results-section" style="display: none; width: 100%; text-align: left; height: 100%; flex-direction: column;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1vh;">
                        <h3 style="color: var(--primary); font-size: 2.5vh; margin: 0;"><i class="fa-solid fa-list-check"></i> Turnos Encontrados</h3>
                        <button onclick="closeOverlays()" style="background: #ef4444; color: white; padding: 1vh 2vh; border-radius: 10px; border: none; font-weight: bold; font-size: 1.8vh; cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
                    </div>

                    <div id="turnos-list" style="flex: 1; overflow-y: auto; padding: 1vh; display: grid; gap: 10px;">
                        <!-- Los turnos aparecerán aquí -->
                    </div>
                    
                    <div style="background: #f1f5f9; padding: 1.5vh; border-radius: 15px; margin-top: 1vh; border: 1px solid #cbd5e1;">
                        <label style="font-weight: 700; color: var(--text-main); font-size: 1.8vh; display: block; margin-bottom: 0.5vh;">
                            <i class="fa-solid fa-envelope" style="color: var(--primary);"></i> Correo Registrado:
                        </label>
                        <div style="display: flex; gap: 10px;">
                            <input type="email" id="turnos-email-input" readonly onclick="toggleEmailKeyboard()" style="flex: 1; padding: 10px; border-radius: 10px; border: 2px solid #94a3b8; font-size: 2vh; background: #fff; color: #0f172a; font-weight: 700; cursor: pointer;">
                            <button onclick="toggleEmailKeyboard()" style="background: var(--primary); color: white; border: none; padding: 0 15px; border-radius: 10px; font-weight: bold; font-size: 1.8vh;"><i class="fa-solid fa-keyboard"></i></button>
                        </div>
                        
                        <button onclick="enviarTurnosCorreo()" style="width: 100%; padding: 15px; border-radius: 12px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; font-size: 2vh; font-weight: 800; text-transform: uppercase; cursor: pointer; margin-top: 1.5vh;">
                            <i class="fa-solid fa-paper-plane" style="margin-right: 5px;"></i> Enviar al Correo
                        </button>
                    </div>
                </div>

                <!-- TECLADO EMAIL FLOTANTE (Ocupa toda la derecha si se activa) -->
                <div id="email-keyboard" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: white; padding: 4vh; flex-direction: column; justify-content: center; z-index: 10;">
                    <h3 style="color: var(--primary); font-size: 2.5vh; margin-bottom: 2vh;"><i class="fa-solid fa-keyboard"></i> Editar Correo</h3>
                    <div style="display: flex; gap: 1vh; margin-bottom: 1vh;">
                        <button class="kb-key" onclick="addEmailChar('1')">1</button><button class="kb-key" onclick="addEmailChar('2')">2</button><button class="kb-key" onclick="addEmailChar('3')">3</button><button class="kb-key" onclick="addEmailChar('4')">4</button><button class="kb-key" onclick="addEmailChar('5')">5</button><button class="kb-key" onclick="addEmailChar('6')">6</button><button class="kb-key" onclick="addEmailChar('7')">7</button><button class="kb-key" onclick="addEmailChar('8')">8</button><button class="kb-key" onclick="addEmailChar('9')">9</button><button class="kb-key" onclick="addEmailChar('0')">0</button>
                    </div>
                    <div style="display: flex; gap: 1vh; margin-bottom: 1vh;">
                        <button class="kb-key" onclick="addEmailChar('q')">q</button><button class="kb-key" onclick="addEmailChar('w')">w</button><button class="kb-key" onclick="addEmailChar('e')">e</button><button class="kb-key" onclick="addEmailChar('r')">r</button><button class="kb-key" onclick="addEmailChar('t')">t</button><button class="kb-key" onclick="addEmailChar('y')">y</button><button class="kb-key" onclick="addEmailChar('u')">u</button><button class="kb-key" onclick="addEmailChar('i')">i</button><button class="kb-key" onclick="addEmailChar('o')">o</button><button class="kb-key" onclick="addEmailChar('p')">p</button>
                    </div>
                    <div style="display: flex; gap: 1vh; margin-bottom: 1vh; padding: 0 1vh;">
                        <button class="kb-key" onclick="addEmailChar('a')">a</button><button class="kb-key" onclick="addEmailChar('s')">s</button><button class="kb-key" onclick="addEmailChar('d')">d</button><button class="kb-key" onclick="addEmailChar('f')">f</button><button class="kb-key" onclick="addEmailChar('g')">g</button><button class="kb-key" onclick="addEmailChar('h')">h</button><button class="kb-key" onclick="addEmailChar('j')">j</button><button class="kb-key" onclick="addEmailChar('k')">k</button><button class="kb-key" onclick="addEmailChar('l')">l</button>
                    </div>
                    <div style="display: flex; gap: 1vh; margin-bottom: 2vh; padding: 0 2vh;">
                        <button class="kb-key" onclick="addEmailChar('z')">z</button><button class="kb-key" onclick="addEmailChar('x')">x</button><button class="kb-key" onclick="addEmailChar('c')">c</button><button class="kb-key" onclick="addEmailChar('v')">v</button><button class="kb-key" onclick="addEmailChar('b')">b</button><button class="kb-key" onclick="addEmailChar('n')">n</button><button class="kb-key" onclick="addEmailChar('m')">m</button>
                    </div>
                    <div style="display: flex; gap: 1vh;">
                        <button class="kb-key" onclick="clearEmailAll()" style="background: var(--accent-red); color: white; flex: 1; font-size: 2vh;"><i class="fa-solid fa-trash"></i></button>
                        <button class="kb-key" onclick="addEmailChar('_')" style="flex: 0.5;">_</button>
                        <button class="kb-key" onclick="addEmailChar('-')" style="flex: 0.5;">-</button>
                        <button class="kb-key" onclick="addEmailChar('@')" style="background: var(--primary); color: white; flex: 1;">@</button>
                        <button class="kb-key" onclick="addEmailChar('.')" style="flex: 0.5;">.</button>
                        <button class="kb-key" onclick="deleteEmailChar()" style="background: var(--accent-orange); color: white; flex: 1;"><i class="fa-solid fa-delete-left"></i></button>
                        <button class="kb-key" onclick="toggleEmailKeyboard()" style="background: var(--accent-green); color: white; flex: 1; font-size: 2vh;"><i class="fa-solid fa-check"></i> Listo</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL BUSCAR DNI (Diseño Idéntico a Info/Turismo) -->
    <div id="modal-buscar-dni" class="fullscreen-overlay">
        <div class="modal-card" style="width: 80vw; height: 70vh; display: flex; flex-direction: row; padding: 0; overflow: hidden; border-radius: 30px;">
            
            <!-- Izquierda: Información -->
            <div style="flex: 1.2; background: #f8fafc; padding: 6vh; display: flex; flex-direction: column; justify-content: center; border-right: 2px solid #e2e8f0;">
                <div style="width: 12vh; height: 12vh; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 3vh; font-size: 5vh; color: white; background: #38bdf8; box-shadow: 0 10px 25px rgba(56, 189, 248, 0.3);">
                    <i class="fa-solid fa-id-card"></i>
                </div>
                <h2 style="color: #0f172a; font-size: 5vh; font-weight: 900; line-height: 1.1; margin-bottom: 2vh; text-transform: uppercase;">INGRESE SU DNI</h2>
                <p style="color: #64748b; font-size: 2.5vh; line-height: 1.5; margin-bottom: 4vh; font-weight: 600;">Validaremos sus datos con el Padrón Oficial de OSFA para agilizar su formulario.</p>
                <div style="background: white; border: 1px solid #cbd5e1; padding: 3vh; border-radius: 15px; display: flex; gap: 2vh; align-items: center;">
                    <i class="fa-solid fa-circle-info" style="color: #38bdf8; font-size: 4vh;"></i>
                    <p style="margin:0; font-size: 2vh; color: #334155; font-weight: 600;">Utilice el teclado numérico de la derecha para ingresar su número de documento sin puntos.</p>
                </div>
            </div>

            <!-- Derecha: Teclado y Acción -->
            <div style="flex: 1; background: white; padding: 4vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                
                <h3 style="color: #38bdf8; font-size: 3vh; margin-bottom: 1vh;"><i class="fa-solid fa-id-card"></i> Ingrese su DNI</h3>
                <input type="text" id="dni-input" readonly style="width: 100%; max-width: 350px; height: 6vh; font-size: 4vh; text-align: center; border-radius: 15px; border: 2px solid #cbd5e1; margin-bottom: 2vh; background: #f1f5f9; letter-spacing: 5px; color: #0f172a; font-weight: 900; box-shadow: inset 0 2px 5px rgba(0,0,0,0.05); outline: none;">
                
                <div class="numpad" style="max-width: 350px; gap: 1.5vh;">
                    <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addDni('1')">1</button>
                    <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addDni('2')">2</button>
                    <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addDni('3')">3</button>
                    <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addDni('4')">4</button>
                    <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addDni('5')">5</button>
                    <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addDni('6')">6</button>
                    <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addDni('7')">7</button>
                    <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addDni('8')">8</button>
                    <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addDni('9')">9</button>
                    <button class="num-btn" onclick="clearDni()" style="padding: 2vh 0; font-size: 3vh; background: #ef4444; color: white; box-shadow: 0 4px 0 #b91c1c;"><i class="fa-solid fa-eraser"></i></button>
                    <button class="num-btn" style="padding: 2vh 0; font-size: 3vh;" onclick="addDni('0')">0</button>
                    <button class="num-btn" onclick="buscarDni()" id="btn-buscar-dni-numpad" style="padding: 2vh 0; font-size: 3vh; background: #10b981; color: white; box-shadow: 0 4px 0 #059669;"><i class="fa-solid fa-check"></i></button>
                </div>

                <div id="dni-loading" style="display:none; margin-top: 1.5vh; color: #0284c7; font-weight: 800; font-size: 2vh;">
                    <i class="fa-solid fa-spinner fa-spin"></i> Consultando Padrón...
                </div>
                <div id="dni-error" style="display:none; margin-top: 1.5vh; color: #ef4444; font-weight: 800; font-size: 1.8vh;">
                    <p style="margin-bottom: 0.5vh;">No encontrado o error.</p>
                    <button onclick="continuarSinDni()" style="background: #0f172a; color: white; border: none; padding: 1vh 2vh; border-radius: 8px; font-weight: bold; cursor: pointer;">
                        Escanear QR <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>

                <button onclick="closeOverlays()" style="width: 100%; max-width: 350px; padding: 2vh; background: #ef4444; color: white; border: none; border-radius: 15px; font-size: 2.2vh; font-weight: 900; cursor: pointer; box-shadow: 0 5px 0 #b91c1c; transition: all 0.2s; text-transform: uppercase; margin-top: 2vh;">
                    <i class="fa-solid fa-arrow-left" style="margin-right:10px;"></i> Volver al Inicio
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL CONTACTO -->
    <div id="modal-contacto" class="fullscreen-overlay">
        <div class="modal-card" style="height: auto; max-height: 90vh;">
            <div class="modal-header" style="background: var(--accent-orange);">
                <h2><i class="fa-solid fa-address-book" style="margin-right:15px;"></i> Contacto y Reserva de Turnos</h2>
                <button class="btn-close" onclick="closeOverlays()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body contact-grid">
                <div class="contact-box">
                    <i class="fa-solid fa-phone" style="color: var(--primary);"></i>
                    <h3>Central de Turnos</h3>
                    <p>(11) 4958-0080 / 4958-1597</p>
                    <p style="font-size:1.5vh; margin-top:1vh; color:#64748b;">Operador: (11) 4981-2773</p>
                </div>
                <div class="contact-box">
                    <i class="fa-brands fa-whatsapp" style="color: var(--accent-green);"></i>
                    <h3>WhatsApp</h3>
                    <p>(11) 2302-3297</p>
                    <p style="font-size:1.5vh; margin-top:1vh; color:#64748b;">Solo mensajes</p>
                </div>
                <div class="contact-box">
                    <i class="fa-solid fa-envelope" style="color: var(--accent-orange);"></i>
                    <h3>Correo Electrónico</h3>
                    <p>turnos.actis@iosfa.gob.ar</p>
                </div>
                <div class="contact-box">
                    <i class="fa-solid fa-map-location-dot" style="color: var(--accent-red);"></i>
                    <h3>Ubicación</h3>
                    <p>Av. Rivadavia 4283, CABA</p>
                    <p style="font-size:1.5vh; margin-top:1vh; color:#64748b;">Lunes a viernes 8 a 20 hs</p>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL INFO + QR (Diseño Dividido 2 Columnas) -->
    <div id="modal-info-qr" class="fullscreen-overlay">
        <div class="modal-card" style="width: 80vw; height: 70vh; display: flex; flex-direction: row; padding: 0; overflow: hidden; border-radius: 30px;">
            <!-- Izquierda: Información -->
            <div style="flex: 1.2; background: #f8fafc; padding: 6vh; display: flex; flex-direction: column; justify-content: center; border-right: 2px solid #e2e8f0;">
                <div id="infoqr-icon-container" style="width: 12vh; height: 12vh; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 3vh; font-size: 5vh; color: white;">
                    <i id="infoqr-icon" class="fa-solid fa-qrcode"></i>
                </div>
                <h2 id="infoqr-title" style="color: #0f172a; font-size: 5vh; font-weight: 900; line-height: 1.1; margin-bottom: 2vh; text-transform: uppercase;">Título</h2>
                <p id="infoqr-desc" style="color: #64748b; font-size: 2.5vh; line-height: 1.5; margin-bottom: 4vh; font-weight: 600;">Descripción</p>
                <div style="background: white; border: 1px solid #cbd5e1; padding: 3vh; border-radius: 15px; display: flex; gap: 2vh; align-items: center;">
                    <i class="fa-solid fa-circle-info" style="color: #38bdf8; font-size: 4vh;"></i>
                    <p style="margin:0; font-size: 2vh; color: #334155; font-weight: 600;">Esta sección requiere el uso de su celular personal para escanear el código QR que aparece en pantalla.</p>
                </div>
            </div>
            
            <!-- Derecha: QR y Botón Volver -->
            <div style="flex: 1; background: white; padding: 6vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                <h3 style="color: var(--primary); font-size: 3vh; margin-bottom: 1vh;"><i class="fa-solid fa-mobile-screen" style="margin-right:10px;"></i> Código QR de Acceso</h3>
                <p style="color: #94a3b8; font-size: 2vh; margin-bottom: 3vh; font-weight: bold;">Enfoque la cámara aquí</p>
                
                <div style="background: white; padding: 2vh; border-radius: 20px; border: 3px solid #e2e8f0; display: inline-block; margin-bottom: 5vh; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
                    <div id="infoqr-qrcode-container"></div>
                </div>

                <button onclick="closeOverlays()" style="width: 100%; max-width: 400px; padding: 2vh; background: #ef4444; color: white; border: none; border-radius: 15px; font-size: 2.5vh; font-weight: 900; cursor: pointer; box-shadow: 0 6px 0 #b91c1c; transition: all 0.2s; text-transform: uppercase;">
                    <i class="fa-solid fa-arrow-left" style="margin-right:10px;"></i> Volver +
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL PAPA DE BAUTI (PASO 1) -->
    <div id="modal-papadebauty" class="fullscreen-overlay">
        <div class="modal-card" style="width: 85vw; height: 80vh; display: flex; flex-direction: row; padding: 0; overflow: hidden; border-radius: 30px; background: white;">
            
            <!-- Left panel: Info -->
            <div style="flex: 1.5; background: #fff; padding: 4vh; display: flex; flex-direction: column; overflow-y: auto; position: relative;">
                <button class="btn-close" style="position: absolute; top: 2vh; left: 2vh; background: rgba(0,0,0,0.05); color: #333; width: 6vh; height: 6vh; border-radius: 50%; border: none; font-size: 3vh; cursor: pointer; transition: 0.2s; z-index: 10;" onclick="closeOverlays()" onactive="this.style.transform='scale(0.9)'"><i class="fa-solid fa-arrow-left"></i></button>
                
                <h2 style="font-size: 4vh; font-weight: 900; color: #444; text-align: center; margin-top: 2vh; margin-bottom: 1vh;">Pensado para todos</h2>
                <p style="font-size: 2vh; color: #888; text-align: center; margin-bottom: 4vh;">Neurodiversidad en todas sus formas.</p>
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2vh; padding-right: 2vh;">
                    <div style="border-top: 5px solid #92A8D1; padding: 2vh; background: #f8fafc; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                        <i class="fa-solid fa-puzzle-piece" style="font-size: 4vh; color: #92A8D1; margin-bottom: 1vh; display: block;"></i>
                        <h3 style="font-size: 2.2vh; color: #444; margin-bottom: 1vh;">Autismo (TEA)</h3>
                        <p style="font-size: 1.6vh; color: #666; margin: 0;">Estructura clara, anticipación y apoyos visuales para reducir la ansiedad.</p>
                    </div>
                    <div style="border-top: 5px solid #FFB347; padding: 2vh; background: #f8fafc; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                        <i class="fa-solid fa-bolt" style="font-size: 4vh; color: #FFB347; margin-bottom: 1vh; display: block;"></i>
                        <h3 style="font-size: 2.2vh; color: #444; margin-bottom: 1vh;">TDAH</h3>
                        <p style="font-size: 1.6vh; color: #666; margin: 0;">Actividades dinámicas y cortas para mantener la atención sostenida.</p>
                    </div>
                    <div style="border-top: 5px solid #F7CAC9; padding: 2vh; background: #f8fafc; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                        <i class="fa-solid fa-comments" style="font-size: 4vh; color: #F7CAC9; margin-bottom: 1vh; display: block;"></i>
                        <h3 style="font-size: 2.2vh; color: #444; margin-bottom: 1vh;">Desafíos del Lenguaje</h3>
                        <p style="font-size: 1.6vh; color: #666; margin: 0;">Juegos de fonética, vocabulario y construcción de frases.</p>
                    </div>
                    <div style="border-top: 5px solid #4ECDC4; padding: 2vh; background: #f8fafc; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                        <i class="fa-solid fa-baby" style="font-size: 4vh; color: #4ECDC4; margin-bottom: 1vh; display: block;"></i>
                        <h3 style="font-size: 2.2vh; color: #444; margin-bottom: 1vh;">Estimulación Temprana</h3>
                        <p style="font-size: 1.6vh; color: #666; margin: 0;">Ideal para preescolares que están descubriendo el mundo.</p>
                    </div>
                    <div style="border-top: 5px solid #88B04B; padding: 2vh; background: #f8fafc; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                        <i class="fa-solid fa-users" style="font-size: 4vh; color: #88B04B; margin-bottom: 1vh; display: block;"></i>
                        <h3 style="font-size: 2.2vh; color: #444; margin-bottom: 1vh;">Síndrome de Down</h3>
                        <p style="font-size: 1.6vh; color: #666; margin: 0;">Aprendizaje visual paso a paso para facilitar la comprensión de conceptos.</p>
                    </div>
                    <div style="border-top: 5px solid #FF6B6B; padding: 2vh; background: #f8fafc; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                        <i class="fa-solid fa-font" style="font-size: 4vh; color: #FF6B6B; margin-bottom: 1vh; display: block;"></i>
                        <h3 style="font-size: 2.2vh; color: #444; margin-bottom: 1vh;">Dislexia</h3>
                        <p style="font-size: 1.6vh; color: #666; margin: 0;">Fuentes claras y apoyo de audio para facilitar la lectoescritura.</p>
                    </div>
                </div>
            </div>

            <!-- Right panel: QR & Method -->
            <div style="flex: 1; background: #92A8D1; padding: 6vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; color: white;">
                
                <h3 style="font-size: 3vh; font-weight: 800; margin-bottom: 2vh;"><i class="fa-solid fa-heart" style="margin-right: 10px;"></i> De un Papá para todas las Familias</h3>
                <p style="font-size: 1.8vh; opacity: 0.9; margin-bottom: 4vh; line-height: 1.5;">Soy el papá de Bauti y creé esta plataforma gratuita y segura para ayudar a los niños con autismo a aprender jugando, sin publicidad ni ruidos. Escanea el código para llevarla en tu celular, o explora desde aquí mismo.</p>
                
                <div style="background: white; padding: 2vh; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); margin-bottom: 3vh;">
                    <div id="qr-papadebauty"></div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5vh; width: 100%; max-width: 800px; margin-bottom: 2vh;">
                    <button onclick="document.getElementById('modal-papadebauty').classList.remove('active'); document.getElementById('modal-papadebauty-metodo').classList.add('active');" style="padding: 2vh 1vh; background: white; color: #92A8D1; border: none; border-radius: 15px; font-size: 1.8vh; font-weight: 900; cursor: pointer; box-shadow: 0 6px 0 #7a8fae; transition: all 0.2s; text-transform: uppercase;">
                        <i class="fa-solid fa-wand-magic-sparkles" style="margin-right:8px; font-size: 2vh;"></i> Metodología
                    </button>
                    <button onclick="document.getElementById('modal-papadebauty').classList.remove('active'); openPapadebautyIframe('https://federicogonzalez.net/papadebauty/padres.php?kiosco=1', 'Blog Padres', 'blog');" style="padding: 2vh 1vh; background: #FFB347; color: white; border: none; border-radius: 15px; font-size: 1.8vh; font-weight: 900; cursor: pointer; box-shadow: 0 6px 0 #d9983c; transition: all 0.2s; text-transform: uppercase;">
                        <i class="fa-solid fa-book-open" style="margin-right:8px; font-size: 2vh;"></i> Blog Padres
                    </button>
                    <button onclick="document.getElementById('modal-papadebauty').classList.remove('active'); openPapadebautyIframe('https://federicogonzalez.net/papadebauty/juegos.php?kiosco=1', 'Juegos Interactivos', 'juegos');" style="padding: 2vh 1vh; background: #4ECDC4; color: white; border: none; border-radius: 15px; font-size: 1.8vh; font-weight: 900; cursor: pointer; box-shadow: 0 6px 0 #38a8a0; transition: all 0.2s; text-transform: uppercase;">
                        <i class="fa-solid fa-gamepad" style="margin-right:8px; font-size: 2vh;"></i> Jugar Ahora
                    </button>
                    <button onclick="closeOverlays()" style="padding: 2vh 1vh; background: #ef4444; color: white; border: none; border-radius: 15px; font-size: 1.8vh; font-weight: 900; cursor: pointer; box-shadow: 0 6px 0 #b91c1c; transition: all 0.2s; text-transform: uppercase;">
                        <i class="fa-solid fa-xmark" style="margin-right:8px; font-size: 2vh;"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL PAPA DE BAUTI (PASO 2 - METODOLOGÍA) -->
    <div id="modal-papadebauty-metodo" class="fullscreen-overlay">
        <div class="modal-card" style="width: 85vw; height: 80vh; display: flex; flex-direction: column; padding: 6vh; border-radius: 30px; background: white; position: relative;">
            <button class="btn-close" style="position: absolute; top: 2vh; left: 2vh; background: rgba(0,0,0,0.05); color: #333; width: 6vh; height: 6vh; border-radius: 50%; border: none; font-size: 3vh; cursor: pointer; transition: 0.2s; z-index: 10;" onclick="document.getElementById('modal-papadebauty-metodo').classList.remove('active'); document.getElementById('modal-papadebauty').classList.add('active');"><i class="fa-solid fa-arrow-left"></i></button>

            <h2 style="font-size: 4.5vh; color: #444; margin-top: 2vh; margin-bottom: 6vh; text-align: center; font-weight: 900;">Nuestra Metodología</h2>
            
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 3vh; flex: 1; margin-top: 2vh;">
                <div style="text-align: center; padding: 4vh 2vh; background: #fff; border-radius: 20px; box-shadow: 0 10px 20px rgba(0,0,0,0.05); border: 1px solid #f1f5f9;">
                    <div style="width: 12vh; height: 12vh; margin: 0 auto 3vh; background: #F7CAC9; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 5vh;"><i class="fa-solid fa-eye"></i></div>
                    <h3 style="font-size: 2.5vh; color: #444; margin-bottom: 1.5vh;">Apoyos Visuales</h3>
                    <p style="color: #666; font-size: 1.8vh; line-height: 1.5;">Pictogramas claros y sin distracciones.</p>
                </div>
                <div style="text-align: center; padding: 4vh 2vh; background: #fff; border-radius: 20px; box-shadow: 0 10px 20px rgba(0,0,0,0.05); border: 1px solid #f1f5f9;">
                    <div style="width: 12vh; height: 12vh; margin: 0 auto 3vh; background: #92A8D1; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 5vh;"><i class="fa-solid fa-hands-holding-circle"></i></div>
                    <h3 style="font-size: 2.5vh; color: #444; margin-bottom: 1.5vh;">Sin Errores</h3>
                    <p style="color: #666; font-size: 1.8vh; line-height: 1.5;">Aprendizaje positivo sin sonidos frustrantes.</p>
                </div>
                <div style="text-align: center; padding: 4vh 2vh; background: #fff; border-radius: 20px; box-shadow: 0 10px 20px rgba(0,0,0,0.05); border: 1px solid #f1f5f9;">
                    <div style="width: 12vh; height: 12vh; margin: 0 auto 3vh; background: #FFB347; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 5vh;"><i class="fa-solid fa-music"></i></div>
                    <h3 style="font-size: 2.5vh; color: #444; margin-bottom: 1.5vh;">Calma Sensorial</h3>
                    <p style="color: #666; font-size: 1.8vh; line-height: 1.5;">Colores pasteles y ambiente regulado.</p>
                </div>
                <div style="text-align: center; padding: 4vh 2vh; background: #fff; border-radius: 20px; box-shadow: 0 10px 20px rgba(0,0,0,0.05); border: 1px solid #f1f5f9;">
                    <div style="width: 12vh; height: 12vh; margin: 0 auto 3vh; background: #4ECDC4; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 5vh;"><i class="fa-solid fa-user-check"></i></div>
                    <h3 style="font-size: 2.5vh; color: #444; margin-bottom: 1.5vh;">Autonomía</h3>
                    <p style="color: #666; font-size: 1.8vh; line-height: 1.5;">Diseño intuitivo para que puedan navegar solos.</p>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 4vh;">
                <button onclick="document.getElementById('modal-papadebauty-metodo').classList.remove('active'); document.getElementById('modal-papadebauty').classList.add('active');" style="width: 100%; max-width: 300px; padding: 2vh; background: #ef4444; color: white; border: none; border-radius: 15px; font-size: 2vh; font-weight: 900; cursor: pointer; box-shadow: 0 6px 0 #b91c1c; transition: all 0.2s; text-transform: uppercase;">
                    <i class="fa-solid fa-arrow-left" style="margin-right:10px;"></i> Volver +
                </button>
            </div>
        </div>
    </div>
    <script>
        function openPapadebauty() {
            closeOverlays();
            document.getElementById('modal-papadebauty').classList.add('active');
            if(document.getElementById('qr-papadebauty').innerHTML === "") {
                new QRCode(document.getElementById("qr-papadebauty"), {
                    text: "https://federicogonzalez.net/papadebauty/",
                    width: 250,
                    height: 250,
                    colorDark : "#444444",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
            }
        }
    </script>

    <!-- MODAL PAPA DE BAUTI (PASO 3 - ELIMINADOS, SE USA NATIVO IFRAME) -->

    <!-- MODAL PAPA DE BAUTI (PASO 4 - IFRAME VISOR PARA JUEGOS Y BLOG) -->
    <div id="modal-papadebauty-iframe" class="fullscreen-overlay">
        <div style="width: 100vw; height: 100vh; background: #f8fafc; display: flex; flex-direction: column;">
            <!-- Top Navigation Bar -->
            <div style="height: 10vh; background: #92A8D1; display: flex; align-items: center; padding: 0 4vh; justify-content: space-between; box-shadow: 0 4px 15px rgba(0,0,0,0.1); z-index: 10;">
                <div style="display: flex; align-items: center; gap: 3vh;">
                    <!-- Botón Volver Dinámico -->
                    <button onclick="closePapadebautyIframe()" style="background: rgba(255,255,255,0.2); color: white; border: none; border-radius: 12px; padding: 0 2vh; height: 6vh; font-size: 2vh; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s;" onactive="this.style.transform='scale(0.9)'">
                        <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i> Volver +
                    </button>
                    <div>
                        <h2 id="iframe-title" style="font-size: 3vh; margin: 0; font-weight: 900; color: white;">Papá de Bauti</h2>
                        <p style="font-size: 1.5vh; color: rgba(255,255,255,0.8); margin: 0;">Espacio 100% Seguro</p>
                    </div>
                </div>
            </div>
            
            <!-- Iframe Container (Con Opacity Trick y CSS Inyectado para Layout Nativo Totem) -->
            <div style="flex: 1; position: relative; overflow: hidden; background: white;">
                <iframe id="papadebauty-iframe-content" src="" style="width: 100%; height: 112%; border: none; position: absolute; top: -12vh; left: 0; opacity: 0; transition: opacity 0.5s ease-in-out;" onload="try{ let d=this.contentWindow.document; if(d.querySelector('header')) d.querySelector('header').style.display='none'; if(d.querySelector('footer')) d.querySelector('footer').style.display='none'; if(d.querySelector('.header-top')) d.querySelector('.header-top').style.display='none'; let style = d.createElement('style'); style.innerHTML = ` .hero-articulo { padding-top: 3vh !important; padding-bottom: 3vh !important; } .articulo-wrapper { background: transparent !important; } .articulo-container { margin: 0 auto 3vh auto !important; max-width: 98% !important; padding: 4vh !important; display: flex !important; flex-direction: row !important; gap: 4vh !important; align-items: flex-start !important; } .img-destacada-flotante { width: 45% !important; height: auto !important; margin: 0 !important; position: sticky !important; top: 4vh !important; } .contenido-body { width: 55% !important; font-size: 2.2vh !important; line-height: 1.6 !important; } .contenido-body p, .contenido-body li { font-size: 2.2vh !important; line-height: 1.6 !important; } .contenido-body h2 { font-size: 3.5vh !important; margin-top: 0 !important; } .contenido-body h3 { font-size: 2.8vh !important; } a[href=\'padres.php\'] { display: none !important; } .articulo-container > div:last-child { display: none !important; } .header-actions { display: none !important; } .game-wrapper { padding: 0 !important; } .game-card { max-width: 100% !important; border-radius: 0 !important; } body { margin: 0 !important; } .grid-respuestas { display: flex !important; flex-direction: row !important; flex-wrap: nowrap !important; justify-content: center !important; gap: 1vh !important; max-width: 100% !important; } .btn-opcion { flex: 1 !important; min-width: 0 !important; min-height: 10vh !important; padding: 1vh !important; } .caja-imagen-forzada { height: 35vh !important; margin-bottom: 2vh !important; } .instruccion-juego { font-size: 2.5vh !important; padding: 1vh 2vh !important; margin-bottom: 2vh !important; } .texto-opcion { font-size: 2vh !important; } .img-opcion { width: 7vh !important; height: 7vh !important; } .img-voladora { height: 30vh !important; width: auto !important; max-width: 45% !important; object-fit: contain !important; } #texto-refuerzo { font-size: 3vh !important; padding: 1vh 3vh !important; margin: 1vh auto !important; } @media (max-width: 900px) { .articulo-container { flex-direction: column !important; } .img-destacada-flotante, .contenido-body { width: 100% !important; position: static !important; } } .blog-header { display: none !important; } .blog-layout { display: block !important; max-width: 100% !important; padding: 4vh !important; } aside { display: none !important; } .posts-grid { display: grid !important; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)) !important; gap: 4vh !important; width: 100% !important; } .post-card { border-radius: 20px !important; border: 1px solid #f0f0f0 !important; box-shadow: 0 8px 25px rgba(0,0,0,0.05) !important; max-width: none !important; } .post-img { height: 25vh !important; } .post-body { padding: 3vh !important; } .post-cat { font-size: 1.5vh !important; margin-bottom: 1.5vh !important; } .post-title { font-size: 2.5vh !important; margin-bottom: 2vh !important; line-height: 1.3 !important; } .post-btn { font-size: 2vh !important; padding: 1.5vh 3vh !important; } .games-header, .filter-section, .header-top { display: none !important; } .games-container { padding: 4vh !important; } `; d.head.appendChild(style); this.style.opacity=1; }catch(e){ this.style.opacity=1; console.error('CORS o error en iframe:', e); }"></iframe>
            </div>
        </div>
    </div>
    <script>
        let lastSourceModal = '';
        function openPapadebautyIframe(url, title, source) {
            lastSourceModal = source;
            document.getElementById('iframe-title').innerText = title;
            document.getElementById('papadebauty-iframe-content').style.opacity = '0';
            document.getElementById('papadebauty-iframe-content').src = url;
            document.getElementById('modal-papadebauty-iframe').classList.add('active');
        }

        function closePapadebautyIframe() {
            let iframe = document.getElementById('papadebauty-iframe-content');
            try {
                let href = iframe.contentWindow.location.href;
                if (href.includes('padres.php') || href.includes('juegos.php') || href.endsWith('papadebauty/')) {
                    document.getElementById('modal-papadebauty-iframe').classList.remove('active');
                    iframe.src = '';
                    document.getElementById('modal-papadebauty').classList.add('active');
                } else {
                    if (lastSourceModal === 'blog') {
                        iframe.src = 'https://federicogonzalez.net/papadebauty/padres.php?kiosco=1';
                    } else if (lastSourceModal === 'juegos') {
                        iframe.src = 'https://federicogonzalez.net/papadebauty/juegos.php?kiosco=1';
                    } else {
                        iframe.contentWindow.history.back();
                    }
                }
            } catch(e) {
                document.getElementById('modal-papadebauty-iframe').classList.remove('active');
                iframe.src = '';
                document.getElementById('modal-papadebauty').classList.add('active');
            }
        }
    </script>

    <!-- MODAL MAPA INTERACTIVO -->
    <div id="modal-mapa" class="fullscreen-overlay">
        <div class="modal-card">
            <div class="modal-header" style="background: #8b5cf6;">
                <h2><i class="fa-solid fa-map-location-dot" style="margin-right:15px;"></i> Mapa Interactivo de la Policlínica</h2>
                <button class="btn-close" onclick="closeOverlays()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body" style="display: flex; align-items: center; justify-content: center; background: #fff;">
                <!-- Placeholder de mapa (puede ser una imagen subida al admin después) -->
                <div style="text-align: center; color: var(--text-muted);">
                    <i class="fa-regular fa-map" style="font-size: 15vh; color: #e2e8f0; margin-bottom: 2vh;"></i>
                    <h3 style="font-size: 3vh;">Plano en Construcción</h3>
                    <p style="font-size: 2vh;">Pronto podrás ver el esquema de los pisos aquí.</p>
                </div>
            </div>
        </div>
    </div>


    <!-- MODAL PIN ACTIS (para Staff) -->
    <div id="overlay-login-staff" class="fullscreen-overlay">
        <div class="modal-card" style="width: min(90vw, 450px); height: auto; text-align: center; padding: 5vh; border: 4px solid #144973;">
            <button class="btn-close" onclick="closeOverlays()" style="background:#144973; position:absolute; top:2vh; right:2vw;"><i class="fa-solid fa-xmark"></i></button>
            <h2 style="font-size: 3.5vh; color: #144973; margin-bottom: 1vh; font-weight: 900;"><i class="fa-solid fa-lock" style="margin-right:12px;"></i>ACCESO STAFF</h2>
            <p style="color: var(--text-muted); font-size: 1.4vh; margin-bottom: 4vh; font-weight: bold; text-transform: uppercase;">Ingresá tu PIN de usuario</p>
            
            <input type="password" id="staff-pin-input" readonly class="pin-display" placeholder="••••">
            
            <div class="numpad">
                <button class="num-btn" onclick="addStaffPin('1')">1</button>
                <button class="num-btn" onclick="addStaffPin('2')">2</button>
                <button class="num-btn" onclick="addStaffPin('3')">3</button>
                <button class="num-btn" onclick="addStaffPin('4')">4</button>
                <button class="num-btn" onclick="addStaffPin('5')">5</button>
                <button class="num-btn" onclick="addStaffPin('6')">6</button>
                <button class="num-btn" onclick="addStaffPin('7')">7</button>
                <button class="num-btn" onclick="addStaffPin('8')">8</button>
                <button class="num-btn" onclick="addStaffPin('9')">9</button>
                <button class="num-btn" onclick="clearStaffPin()" style="background: #ef4444; color: white; box-shadow: 0 4px 0 #b91c1c;"><i class="fa-solid fa-eraser"></i></button>
                <button class="num-btn" onclick="addStaffPin('0')">0</button>
                <button class="num-btn" onclick="submitStaffPin()" style="background: #10b981; color: white; box-shadow: 0 4px 0 #047857;"><i class="fa-solid fa-check"></i></button>
            </div>
        </div>
    </div>

    <script>
        // BASE DE DATOS M�0DICOS
        const medicosDB = [
            { esp: "Alergia", nom: "María del Pilar Castro", hor: "Miércoles de 08:00 a 16:00 hs" },
            { esp: "Psicología", nom: "Santiago Martino", hor: "Lun, Mar y Jue 08:00 a 16:00 hs" },
            { esp: "Psicología", nom: "José Balcarce", hor: "Lun/Jue 10 a 17, Mar/Mié 12 a 17 hs" },
            { esp: "Oftalmología", nom: "Rubén Leite", hor: "Lun y Mié de 07:30 a 19:30 hs" },
            { esp: "Oftalmología", nom: "Sebastián Quetglas", hor: "Jue y Vie 08:00 a 12:00 hs" },
            { esp: "Cirugía General", nom: "Daniel Lombardero", hor: "Lun 17-20, Mar 07:30-13:30, Jue 07-13, Vie 07:30-16" },
            { esp: "Pediatría", nom: "Claudia Santillán", hor: "Lunes de 14:00 a 19:00 hs" },
            { esp: "Cardiología", nom: "Marcelo Zaleski", hor: "Lun y Mié 08-14, Jue 08-15, Vie 08-13 hs" },
            { esp: "Cardiología", nom: "Rosario Álvarez (Estudios)", hor: "Lun/Jue 09-17:30, Mar/Mié 09-17 hs" },
            { esp: "Clínica Médica", nom: "Cristina Rosales de Arispe", hor: "Lunes y Miércoles de 12:00 a 18:00 hs" },
            { esp: "Clínica Médica", nom: "María López Bravo", hor: "Lunes 12 a 18, Miércoles 08 a 13 hs" },
            { esp: "Clínica Médica", nom: "Gustavo Adrián Lancestremere", hor: "Miércoles 09 a 17, Viernes 09 a 13 hs" },
            { esp: "Demanda Espontánea", nom: "Raúl Ficarra (Clínica)", hor: "Miércoles de 8:00 a 20:00 hs" },
            { esp: "Demanda Espontánea", nom: "Héctor Longo (Clínica)", hor: "Martes de 8:00 a 20:00 hs" },
            { esp: "Gastroenterología", nom: "Oscar Aira", hor: "Miércoles 14 a 19, Jueves 13 a 19 hs" },
            { esp: "Ginecología", nom: "Karina Negruella", hor: "Lunes, Mié y Jue de 08:00 a 16:00 hs" },
            { esp: "Ginecología", nom: "Marcela Ostojich", hor: "Lun, Mié, Vie 14 a 18, Jue 09 a 13 hs" },
            { esp: "Kinesiología", nom: "Andrea Cionci", hor: "Lunes 14-19, Martes 09-16, Jue/Vie 09-15 hs" },
            { esp: "Nutrición", nom: "María Scaffino", hor: "Lunes y Miércoles de 07:00 a 19:30 hs" },
            { esp: "Odontología", nom: "Rosa Atencio", hor: "Lunes a Jueves de 7:30 a 11:30 hs" },
            { esp: "Odontología", nom: "Carolina Prozzi (Pediátrica)", hor: "Lun 7:30-12, Mar 7:30-11:30, Mié 13:30-17:30" },
            { esp: "Traumatología", nom: "Oscar Emilio Rodríguez", hor: "Lunes, Martes y Miércoles 07:30 a 15:30 hs" },
            { esp: "Urología", nom: "Patricio Simone Arenas", hor: "Lunes 13 a 18, Martes 07:30 a 14:30 hs" },
            { esp: "Ecografía", nom: "Servicio de Imágenes", hor: "Lun a Vie 9 a 20 hs (Turnos: 11-39856474)" },
            { esp: "Laboratorio", nom: "Extracciones", hor: "Lun a Vie 7:30 a 10:00 hs" }
        ];

        // Lógica de Carrusel
        let slideIndex = 0;
        let slideInterval;
        const slides = document.querySelectorAll('.carousel-slide');
        
        function showSlide(index) {
            if(slides.length === 0) return;
            slides[slideIndex].classList.remove('active');
            slideIndex = (index + slides.length) % slides.length;
            slides[slideIndex].classList.add('active');
        }

        function changeSlide(direction) {
            showSlide(slideIndex + direction);
            resetInterval();
        }

        function autoSlide() {
            showSlide(slideIndex + 1);
        }

        function resetInterval() {
            clearInterval(slideInterval);
            slideInterval = setInterval(autoSlide, 8000);
        }

        resetInterval(); // Iniciar rotación

        function initTotem() {
            updateClock();
            setInterval(updateClock, 1000);
            document.addEventListener('contextmenu', e => e.preventDefault());
            
            // Bloqueo agresivo de gestos Swipe-To-Go-Back (historial de navegador)
            document.addEventListener('touchstart', function(e) {
                if (e.touches[0].pageX < 30 || e.touches[0].pageX > window.innerWidth - 30) {
                    e.preventDefault();
                }
            }, { passive: false });

            initDirectorio();

            // Renderizar QR de WiFi inline
            new QRCode(document.getElementById('inline-qr-wifi'), {
                text: "WIFI:S:actiswifi;T:WPA;P:Medicos.2025;;", width: 65, height: 65, colorDark : "#0284c7", colorLight : "#ffffff", correctLevel : QRCode.CorrectLevel.L
            });
        }

        function updateClock() {
            const now = new Date();
            document.getElementById('clock').textContent = now.toLocaleTimeString('es-AR', { hour: '2-digit', minute:'2-digit', second:'2-digit', hour12: false });
            document.getElementById('date').textContent = now.toLocaleDateString('es-AR', { weekday: 'long', day: 'numeric', month: 'long' });
        }

        function closeOverlays() {
            document.querySelectorAll('.fullscreen-overlay').forEach(el => el.classList.remove('active'));

            let oldQr = document.getElementById('qrcode-container');
            if (oldQr) oldQr.innerHTML = '';
            let newQr = document.getElementById('infoqr-qrcode-container');
            if (newQr) newQr.innerHTML = '';
        }

        function openDirectorio() {
            document.getElementById('modal-directorio').classList.add('active');
            renderDoctors('Todas');
        }

        function openContacto() {
            document.getElementById('modal-contacto').classList.add('active');
        }

        function getEspecialidadColor(esp) {
            const colors = {
                'Pediatría': '#f43f5e', 'Alergia': '#8b5cf6', 'Cardiología': '#ef4444', 
                'Clínica Médica': '#0284c7', 'Odontología': '#14b8a6', 'Ginecología': '#d946ef',
                'Demanda Espontánea': '#f59e0b', 'Traumatología': '#84cc16'
            };
            return colors[esp] || 'var(--primary)';
        }

        function initDirectorio() {
            const especialidades = ['Todas', ...new Set(medicosDB.map(m => m.esp))].sort();
            const filterContainer = document.getElementById('filter-container');
            filterContainer.innerHTML = '';
            especialidades.forEach(esp => {
                const btn = document.createElement('button');
                btn.className = `filter-btn ${esp === 'Todas' ? 'active' : ''}`;
                btn.textContent = esp;
                btn.onclick = () => {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    renderDoctors(esp);
                };
                filterContainer.appendChild(btn);
            });
        }

        function renderDoctors(filtroEsp) {
            const grid = document.getElementById('doctor-grid');
            grid.innerHTML = '';
            const filtrados = filtroEsp === 'Todas' ? medicosDB : medicosDB.filter(m => m.esp === filtroEsp);
            filtrados.forEach(m => {
                let color = getEspecialidadColor(m.esp);
                grid.innerHTML += `
                    <div class="doctor-card" style="border-left-color: ${color}">
                        <h4 style="color: ${color}"><i class="fa-solid fa-stethoscope" style="margin-right:8px;"></i>${m.esp}</h4>
                        <h3>${m.nom}</h3>
                        <p><i class="fa-regular fa-clock" style="margin-right:5px; color: ${color};"></i>${m.hor}</p>
                        ${filtroEsp === 'Demanda Espontánea' ? '<div class="badge-turno" style="color:#ef4444; background:#fee2e2;">Sin Turno</div>' : '<div class="badge-turno">Con Turno</div>'}
                    </div>
                `;
            });
        }

        function showInfoQRModal(title, desc, url, iconClass, color) {
            closeOverlays();
            document.getElementById('infoqr-title').innerText = title;
            document.getElementById('infoqr-desc').innerText = desc;
            
            let iconEl = document.getElementById('infoqr-icon');
            iconEl.className = 'fa-solid ' + iconClass;
            document.getElementById('infoqr-icon-container').style.background = color;
            
            let container = document.getElementById('infoqr-qrcode-container');
            container.innerHTML = '';
            new QRCode(container, {
                text: url,
                width: 300,
                height: 300,
                colorDark : "#0f172a",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
            document.getElementById('modal-info-qr').classList.add('active');
            setTimeout(() => { if(document.getElementById('modal-info-qr').classList.contains('active')) closeOverlays(); }, 30000);
        }

        // Lógica de validación DNI con Tampermonkey
        let currentQuejasData = null;
        let fallbackTimer = null;

        function openModalDni(titulo, bajada, url) {
            currentQuejasData = { titulo, bajada, url };
            document.getElementById('dni-input').value = '';
            document.getElementById('dni-loading').style.display = 'none';
            document.getElementById('dni-error').style.display = 'none';
            document.getElementById('btn-buscar-dni-numpad').disabled = false;
            document.getElementById('modal-buscar-dni').classList.add('active');
        }

        function addDni(num) { document.getElementById('dni-input').value += num; }
        function clearDni() { document.getElementById('dni-input').value = ''; }

        function buscarDni() {
            const dni = document.getElementById('dni-input').value;
            if(dni.length < 6) return;
            
            document.getElementById('dni-loading').style.display = 'block';
            document.getElementById('dni-error').style.display = 'none';
            document.getElementById('btn-buscar-dni-numpad').disabled = true;

            // Enviar mensaje a la capa padre (Tampermonkey en validador.iosfa.gob.ar)
            window.parent.postMessage({ action: 'buscarDniIOSFA', dni: dni }, '*');

            // Timeout de emergencia si Tampermonkey no responde
            clearTimeout(fallbackTimer);
            fallbackTimer = setTimeout(() => {
                if(document.getElementById('dni-loading').style.display === 'block') {
                    document.getElementById('dni-loading').style.display = 'none';
                    document.getElementById('dni-error').style.display = 'block';
                }
            }, 8000);
        }

        function continuarSinDni() {
            closeOverlays();
            showQRCode(currentQuejasData.titulo, currentQuejasData.bajada, currentQuejasData.url + "?dni=" + document.getElementById('dni-input').value);
        }

        // Recibir respuesta de Tampermonkey
        window.addEventListener('message', function(e) {
            if(e.data && e.data.action === 'resultadoDniIOSFA') {
                document.getElementById('dni-loading').style.display = 'none';
                clearTimeout(fallbackTimer);
                
                if(e.data.success) {
                    closeOverlays();
                    const fullUrl = currentQuejasData.url + "?dni=" + e.data.dni + "&nombre=" + encodeURIComponent(e.data.nombre);
                    showQRCode(currentQuejasData.titulo, currentQuejasData.bajada, fullUrl);
                } else {
                    document.getElementById('dni-error').style.display = 'block';
                }
            }
        });

        // L�GICA CONSULTAR TURNOS
        let dniTurnosInput = document.getElementById('turnos-dni-input');
        function openConsultarTurnos() {
            closeOverlays();
            clearTurnosDni();
            document.getElementById('turnos-dni-section').style.display = 'block';
            document.getElementById('turnos-results-section').style.display = 'none';
            document.getElementById('turnos-loading').style.display = 'none';
            document.getElementById('modal-consultar-turnos').classList.add('active');
        }
        function addTurnosDni(num) { if(dniTurnosInput.value.length < 15) dniTurnosInput.value += num; }
        function clearTurnosDni() { dniTurnosInput.value = ''; }
        
        let turnosSeleccionados = [];
        
        function buscarMisTurnos() {
            const dni = dniTurnosInput.value.trim();
            if(dni.length < 6) {
                Swal.fire({ title: 'Atención', text: 'Ingrese un DNI válido.', icon: 'warning', confirmButtonColor: '#0284c7' });
                return;
            }
            
            document.getElementById('turnos-loading').style.display = 'block';
            
            let formData = new FormData();
            formData.append('dni', dni);
            
            fetch('api_totem_mis_turnos.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                document.getElementById('turnos-loading').style.display = 'none';
                
                if(data.success) {
                    if(data.turnos.length === 0) {
                        Swal.fire({ title: 'Sin Turnos', text: 'No se encontraron turnos futuros para este DNI.', icon: 'info', confirmButtonColor: '#0284c7' });
                        return;
                    }
                    
                    document.getElementById('turnos-dni-section').style.display = 'none';
                    document.getElementById('turnos-results-section').style.display = 'flex';
                    
                    document.getElementById('turnos-email-input').value = data.email || '';
                    
                    const listContainer = document.getElementById('turnos-list');
                    listContainer.innerHTML = '';
                    turnosSeleccionados = [];
                    
                    data.turnos.forEach(turno => {
                        turnosSeleccionados.push(turno.id);
                        
                        // Escapar valores para evitar errores JS
                        let prof = turno.profesional.replace(/'/g, "\\'");
                        let esp = turno.especialidad.replace(/'/g, "\\'");
                        let fecha = turno.fecha_formateada;
                        let hora = turno.hora_formateada;
                        let est = turno.estado.replace(/'/g, "\\'");
                        
                        listContainer.innerHTML += `
                            <div onclick="Swal.fire({title: 'Detalle del Turno', html: '<b>Profesional:</b> ${prof}<br><b>Especialidad:</b> ${esp}<br><b>Fecha:</b> ${fecha}<br><b>Hora:</b> ${hora}<br><b>Estado:</b> ${est}', icon: 'info', confirmButtonText: 'Cerrar'})" style="background: #fff; padding: 2vh; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); cursor: pointer; transition: 0.2s;" onactive="this.style.transform='scale(0.98)'">
                                <div>
                                    <h4 style="color: var(--primary); font-size: 2vh; margin: 0 0 5px 0;"><i class="fa-solid fa-user-doctor"></i> ${turno.profesional}</h4>
                                    <p style="margin: 0; font-size: 1.6vh; color: var(--text-main); font-weight: 700;">${turno.especialidad}</p>
                                </div>
                                <div style="text-align: right;">
                                    <p style="margin: 0; font-size: 1.8vh; color: var(--text-main); font-weight: 800;"><i class="fa-regular fa-calendar" style="color: var(--accent-orange);"></i> ${turno.fecha_formateada}</p>
                                    <p style="margin: 0; font-size: 1.8vh; color: var(--text-main); font-weight: 800;"><i class="fa-regular fa-clock" style="color: var(--accent-green);"></i> ${turno.hora_formateada} hs</p>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    Swal.fire({ title: 'Error', text: data.message, icon: 'error', confirmButtonColor: '#ef4444' });
                }
            })
            .catch(() => {
                document.getElementById('turnos-loading').style.display = 'none';
                Swal.fire({ title: 'Error', text: 'Sin conexión con el servidor.', icon: 'error' });
            });
        }
        
        function enviarTurnosCorreo() {
            const dni = dniTurnosInput.value.trim();
            const email = document.getElementById('turnos-email-input').value.trim();
            
            if(!email) {
                Swal.fire({ title: 'Atención', text: 'No tiene un correo electrónico registrado o es inválido. Por favor edítelo.', icon: 'warning', confirmButtonColor: '#f59e0b' });
                return;
            }
            
            // Validar formato de email simple
            if(!email.includes('@') || !email.includes('.')) {
                Swal.fire({ title: 'Atención', text: 'El correo electrónico ingresado no parece válido.', icon: 'warning', confirmButtonColor: '#f59e0b' });
                return;
            }
            
            Swal.fire({ title: 'Generando PDF...', html: 'Preparando correo, por favor espere...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
            
            let formData = new FormData();
            formData.append('dni', dni);
            formData.append('email_custom', email);
            formData.append('turnos', JSON.stringify(turnosSeleccionados));
            
            fetch('api_totem_enviar_turnos.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    Swal.fire({ title: '¡Enviado!', text: 'Sus turnos han sido enviados correctamente a su correo.', icon: 'success', confirmButtonColor: '#10b981', timer: 3000 }).then(() => closeOverlays());
                } else {
                    Swal.fire({ title: 'Error al Enviar', text: data.message, icon: 'error', confirmButtonColor: '#ef4444' });
                }
            })
            .catch(() => {
                Swal.fire({ title: 'Error', text: 'Error de conexión al intentar enviar el correo.', icon: 'error' });
            });
        }

        // L�GICA TECLADO VIRTUAL EMAIL
        function toggleEmailKeyboard() {
            const kb = document.getElementById('email-keyboard');
            if (kb.style.display === 'none') {
                kb.style.display = 'flex'; // Usamos flex ahora para el overlay
            } else {
                kb.style.display = 'none';
            }
        }
        function addEmailChar(char) {
            const input = document.getElementById('turnos-email-input');
            input.value += char;
        }
        function deleteEmailChar() {
            const input = document.getElementById('turnos-email-input');
            input.value = input.value.slice(0, -1);
        }
        function clearEmailAll() {
            document.getElementById('turnos-email-input').value = '';
        }


        // LÓGICA STAFF — Flujo: chequear sesión → pedir PIN si falta → admin_totem.php
        function openStaffModal() {
            // Chequear si hay sesión activa
            fetch('api_check_session_staff.php?t=' + Date.now())
            .then(r => r.json())
            .then(data => {
                if (data.active) {
                    window.location.href = data.redirect || 'dashboard.php';
                } else {
                    document.getElementById('staff-pin-input').value = '';
                    document.getElementById('overlay-login-staff').classList.add('active');
                }
            })
            .catch(() => {
                document.getElementById('staff-pin-input').value = '';
                document.getElementById('overlay-login-staff').classList.add('active');
            });
        }

        function addStaffPin(num) {
            const input = document.getElementById('staff-pin-input');
            if (input.value.length < 20) input.value += num;
        }

        function clearStaffPin() {
            document.getElementById('staff-pin-input').value = '';
        }

        function submitStaffPin() {
            const pin = document.getElementById('staff-pin-input').value.trim();
            if (!pin) {
                Swal.fire({ title: 'Atención', text: 'Ingresá tu PIN.', icon: 'warning', confirmButtonColor: '#144973' });
                return;
            }
            Swal.fire({ title: 'Validando...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
            const fd = new FormData();
            fd.append('pin', pin);
            fetch('api_login_pin.php?t=' + Date.now(), { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect || 'dashboard.php';
                } else {
                    Swal.fire({ title: 'Acceso Denegado', text: data.message || 'PIN incorrecto.', icon: 'error', confirmButtonColor: '#ef4444' });
                    clearStaffPin();
                }
            })
            .catch(() => Swal.fire({ title: 'Error', text: 'Sin conexión.', icon: 'error' }));
        }

        // L GICA DE DESLOGUEO (PARA PRUEBAS Y RESETEO)
        function intentarCerrarSesionLogistica() {
            Swal.fire({
                title: 'Cerrar Sesión Logística',
                input: 'password',
                inputLabel: 'Ingrese el PIN de cierre',
                inputPlaceholder: 'PIN',
                showCancelButton: true,
                confirmButtonText: 'Cerrar Sesión',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    if (result.value === '<?php echo $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_dashboard_staff'")->fetch_assoc()['estado'] ?? "12345"; ?>') {
                        window.location.href = 'dashboard_totem.php?logout_logistica=1';
                    } else {
                        Swal.fire('Error', 'PIN incorrecto', 'error');
                    }
                }
            });
        }
    </script>

    </body>
</html>


