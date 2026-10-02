<?php
session_start();

// --- CONFIGURACIÓN DE SEGURIDAD ---
// Al estar en PHP, esto es invisible para el usuario que hace Ctrl+U en el navegador
$usuario_valido = 'admin';
$password_valida = '3sp3j0!';

$error_login = '';

// Lógica de Inicio de Sesión
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_installer'])) {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    
    if ($user === $usuario_valido && $pass === $password_valida) {
        $_SESSION['instalador_auth'] = true;
        header("Location: index.php");
        exit;
    } else {
        $error_login = 'Credenciales incorrectas. Acceso denegado.';
    }
}

// Lógica de Cierre de Sesión
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Verificación de estado de sesión
$isAuth = isset($_SESSION['instalador_auth']) && $_SESSION['instalador_auth'] === true;

// Lógica de navegador
$ua = $_SERVER['HTTP_USER_AGENT'];
$isFirefox = (strpos($ua, 'Firefox') !== false);
$linkTM = $isFirefox ? "https://addons.mozilla.org/es/firefox/addon/tampermonkey/" : "https://chromewebstore.google.com/detail/tampermonkey/dhdgffkkebhmkfjojejmpbldmpobfkfo";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador ACTIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        /* Tema ACTIS */
        .actis-header { background: linear-gradient(135deg, #144973 0%, #0d3252 100%); color: white; padding: 40px 0; border-bottom: 5px solid #0a2640; margin-bottom: 40px; position: relative; }
        .logo-actis { max-width: 100px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3)); margin-bottom: 15px; }
        
        /* Botones */
        .btn-actis-primary { background-color: #144973; color: white; border: none; font-weight: 700; transition: all 0.2s; }
        .btn-actis-primary:hover { background-color: #0d3252; color: white; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(20,73,115,0.3); }
        .btn-actis-success { background-color: #10b981; color: white; border: none; font-weight: 700; transition: all 0.2s; }
        .btn-actis-success:hover { background-color: #059669; color: white; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16,185,129,0.3); }
        
        /* Tarjetas */
        .card-module { border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.02); transition: transform 0.3s ease, box-shadow 0.3s ease; height: 100%; }
        .card-module:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.08); border-color: #cbd5e1; }
        .icon-circle { width: 60px; height: 60px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px auto; color: #144973; font-size: 24px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); }
        
        /* Pantalla Login */
        .login-wrapper { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #e2e8f0; }
        .login-box { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; border-top: 5px solid #144973; }
        .form-control:focus { border-color: #144973; box-shadow: 0 0 0 0.25rem rgba(20,73,115,0.15); }
    </style>
</head>
<body>

<?php if (!$isAuth): ?>
    <div class="login-wrapper">
        <div class="login-box">
            <img src="https://federicogonzalez.net/actis/img/osfa.png" alt="ACTIS Logo" class="logo-actis" style="max-width: 80px;">
            <h3 class="mb-4" style="color: #0f172a; font-weight: 800; letter-spacing: -0.5px;">Acceso Restringido</h3>
            
            <?php if ($error_login): ?>
                <div class="alert alert-danger py-2 fw-bold small"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error_login; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3 text-start">
                    <label class="form-label fw-bold text-secondary small text-uppercase">Usuario</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-user text-muted"></i></span>
                        <input type="text" name="username" class="form-control" autocomplete="off" required>
                    </div>
                </div>
                <div class="mb-4 text-start">
                    <label class="form-label fw-bold text-secondary small text-uppercase">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>
                <button type="submit" name="login_installer" class="btn btn-actis-primary w-100 py-2 fs-6">Ingresar al Sistema <i class="fa-solid fa-arrow-right ms-2"></i></button>
            </form>
        </div>
    </div>

<?php else: ?>
    <div class="actis-header text-center">
        <div class="container">
            <a href="?logout=1" class="btn btn-sm btn-outline-light position-absolute top-0 end-0 mt-3 me-3 fw-bold" style="border-radius: 20px;">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
            </a>
            <img src="https://federicogonzalez.net/actis/img/osfa.png" alt="ACTIS" class="logo-actis">
            <h1 class="fw-bold text-uppercase" style="letter-spacing: 1px;">Sistema ACTIS</h1>
            <p class="text-info mb-0 fw-bold" style="letter-spacing: 2px; font-size:14px;">Centro de Despliegue de Módulos</p>
        </div>
    </div>

    <div class="container mb-5 pb-5">
        <div class="alert alert-warning shadow-sm border-0 border-start border-5 border-warning mb-5 d-flex align-items-center" role="alert">
            <i class="fa-solid fa-triangle-exclamation fs-3 me-3 text-warning"></i> 
            <div>
                <strong class="d-block text-dark">Paso 1 Obligatorio</strong>
                <span class="text-muted">Asegúrese de tener instalada la extensión <a href="<?php echo $linkTM; ?>" target="_blank" class="fw-bold text-dark text-decoration-none border-bottom border-dark">Tampermonkey</a> en este navegador antes de inyectar los scripts.</span>
            </div>
        </div>

        <div class="row g-4">
            <?php 
            $modulos = [
                'Turnos' => [
                    'archivo' => 'loader_turnos.user.js', 
                    'icon' => 'fa-calendar-check', 
                    'desc' => 'Gestión, asignación y extracción automática de datos para turnos simples y sesionados.'
                ],
                'Ventanilla' => [
                    'archivo' => 'loader_ventanilla.user.js', 
                    'icon' => 'fa-desktop', 
                    'desc' => 'Llamador de pacientes por pantallas y atención asistida en ventanillas centrales.'
                ],
                'Totem' => [
                    'archivo' => 'loader_totem.user.js', 
                    'icon' => 'fa-tablet-screen-button', 
                    'desc' => 'Módulo de kiosco interactivo para validación espontánea de pacientes en sala.'
                ],
                'AutoLogin IOSFA' => [
                    'archivo' => 'loader_autologin_iosfa.user.js', 
                    'icon' => 'fa-user-shield', 
                    'desc' => 'Inyección global de autologin para IOSFA, compatible con tótems.'
                ]
            ];
            
            foreach($modulos as $titulo => $datos): ?>
            <div class="col-md-4">
                <div class="card card-module text-center p-4">
                    <div class="card-body d-flex flex-column p-0">
                        <div class="icon-circle">
                            <i class="fa-solid <?php echo $datos['icon']; ?>"></i>
                        </div>
                        <h4 class="card-title fw-bold" style="color:#0f172a; font-size:1.1rem; text-transform:uppercase; letter-spacing:0.5px;">Módulo <?php echo $titulo; ?></h4>
                        <p class="text-muted small mb-4 mt-2"><?php echo $datos['desc']; ?></p>
                        
                        <?php if($titulo == 'Totem' && !$isFirefox): ?>
                            <div class="alert alert-danger p-2 small fw-bold mb-3 mt-auto border-0 bg-danger text-white rounded-3">
                                <i class="fa-brands fa-firefox-browser"></i> REQUERIDO: USAR MOZILLA FIREFOX
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-auto">
                            <a href="<?php echo $datos['archivo']; ?>?v=<?php echo time(); ?>" class="btn btn-actis-success w-100 py-2 rounded-pill">
                                <i class="fa-solid fa-download me-2"></i> Inyectar Loader
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if($isFirefox): ?>
        <div class="row mt-5">
            <div class="col-12">
                <div class="card card-module p-3 border-start border-5 border-info bg-white">
                    <div class="card-body d-flex justify-content-between align-items-center flex-wrap p-0">
                        <div class="d-flex align-items-center gap-3">
                            <div class="fs-1 text-info"><i class="fa-brands fa-firefox-browser"></i></div>
                            <div>
                                <h5 class="fw-bold mb-1 text-dark" style="font-size:1rem;">Configuración de Kiosco Firefox</h5>
                                <p class="text-muted mb-0 small">Script .BAT para habilitar impresión silenciosa (sin diálogo) y permitir sonido auto-play.</p>
                            </div>
                        </div>
                        <a href="configurar_firefox.bat" class="btn btn-dark mt-3 mt-md-0 rounded-pill px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-gears me-2"></i> Descargar .BAT
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>