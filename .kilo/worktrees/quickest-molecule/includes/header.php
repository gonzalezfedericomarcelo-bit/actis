<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($conexion) && file_exists('includes/conexion.php')) { require_once 'includes/conexion.php'; }

$mis_permisos = [];
$mi_rol_nombre = 'Sin Rol';

if(isset($_SESSION['usuario_id']) && isset($conexion)) {
    $uid = (int)$_SESSION['usuario_id'];
    
    $q_rol = $conexion->query("SELECT r.nombre FROM usuarios u INNER JOIN roles r ON u.rol_id = r.id WHERE u.id = $uid");
    if($q_rol && $q_rol->num_rows > 0) {
        $mi_rol_nombre = $q_rol->fetch_assoc()['nombre'];
    }

    $q_perm = $conexion->query("SELECT p.nombre_permiso FROM permisos p INNER JOIN rol_permiso rp ON p.id = rp.permiso_id INNER JOIN usuarios u ON u.rol_id = rp.rol_id WHERE u.id = $uid AND u.estado = 1");
    if($q_perm && $q_perm->num_rows > 0) {
        while($row = $q_perm->fetch_assoc()) {
            $mis_permisos[] = $row['nombre_permiso'];
        }
        $_SESSION['permisos'] = $mis_permisos; 
    } else {
        $_SESSION['permisos'] = []; 
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    
    <meta property="og:title" content="ACTIS Core | Plataforma de Gestión">
    <meta property="og:description" content="Policlínica General ACTIS - Sistema de Gestión Interno.">
    <link rel="icon" href="https://federicogonzalez.net/actis/img/osfa.svg" type="image/svg+xml">
    <meta property="og:image" itemprop="image" content="https://federicogonzalez.net/actis/img/osfa.svg">
    <meta property="og:url" content="https://federicogonzalez.net/actis/">
    <meta property="og:type" content="website">
    

    <title>ACTIS Core | Plataforma de Gestión</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body { margin: 0; padding: 0; padding-top: 70px; background: #f8fafc; font-family: 'Poppins', sans-serif; -webkit-tap-highlight-color: transparent; }
        .main-content { padding: 15px; max-width: 1400px; margin: 0 auto; padding-bottom: 100px; min-height: calc(100vh - 170px); }
        
        .actis-navbar {
            position: fixed; top: 0; left: 0; width: 100%; height: 70px; background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px); border-bottom: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
            display: flex; justify-content: space-between; align-items: center; padding: 0 20px; z-index: 900; box-sizing: border-box;
        }
        .actis-brand { font-size: 1.2rem; font-weight: 900; color: #0f172a; display: flex; align-items: center; gap: 4px; text-decoration: none; letter-spacing: -0.5px; }
        .actis-brand i { background: #144973; color: white; width: 32px; height: 32px; display: flex; justify-content: center; align-items: center; border-radius: 8px; font-size: 1rem; }
        
        .topbar-tools { display: flex; align-items: center; gap: 12px; }
        .live-clock { background: #f1f5f9; color: #475569; padding: 6px 12px; border-radius: 8px; font-weight: 800; font-size: 0.85rem; display: flex; align-items: center; gap: 6px; border: 1px solid #e2e8f0; white-space: nowrap; }
        
        /* Estilos de Menú Dropdown para Escritorio */
        .dropdown { position: relative; display: inline-block; }
        .dropdown-btn { background: transparent; border: none; padding: 10px 15px; color: #475569; font-weight: 700; font-size: 0.9rem; border-radius: 10px; display: flex; align-items: center; gap: 8px; cursor: pointer; transition: 0.2s; font-family: 'Poppins', sans-serif; }
        .dropdown-btn:hover { background: #f1f5f9; color: #144973; }
        .dropdown-btn i.arrow { font-size: 0.75rem; transition: transform 0.2s; }
        .dropdown:hover .dropdown-btn i.arrow { transform: rotate(180deg); color: #144973; }
        .dropdown-content { display: none; position: absolute; top: 100%; left: 0; background-color: #ffffff; min-width: 220px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-radius: 12px; border: 1px solid #e2e8f0; z-index: 1000; padding: 8px; flex-direction: column; gap: 4px; animation: fadeInDown 0.2s ease-out; }
        .dropdown:hover .dropdown-content { display: flex; }
        .dropdown-content a { color: #334155; padding: 10px 15px; text-decoration: none; display: flex; align-items: center; gap: 10px; font-size: 0.85rem; font-weight: 600; border-radius: 8px; transition: background 0.2s; }
        .dropdown-content a i { width: 20px; text-align: center; color: #64748b; }
        .dropdown-content a:hover { background-color: #f1f5f9; color: #144973; }
        .dropdown-content a:hover i { color: #144973; }
        @keyframes fadeInDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .actis-bottom-bar {
            display: flex; justify-content: space-around; align-items: center;
            position: fixed; bottom: 0; left: 0; width: 100%; height: 75px;
            background: rgba(255,255,255,0.98); backdrop-filter: blur(10px);
            border-top: 1px solid #e2e8f0; box-shadow: 0 -4px 20px rgba(0,0,0,0.06); z-index: 1000;
            padding-bottom: env(safe-area-inset-bottom);
        }
        .bottom-tab {
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;
            color: #94a3b8; text-decoration: none; font-size: 0.7rem; font-weight: 800; flex: 1; height: 100%; border: none; background: transparent; cursor: pointer; transition: all 0.2s;
        }
        .bottom-tab i { font-size: 1.4rem; transition: transform 0.2s; }
        .bottom-tab.active { color: #144973; }
        .bottom-tab.active i { transform: translateY(-3px); color: #144973; }

        .right-drawer-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15,23,42,0.6); backdrop-filter: blur(3px); z-index: 2000; opacity: 0; visibility: hidden; transition: all 0.3s;
        }
        .right-drawer-overlay.active { opacity: 1; visibility: visible; }
        
        .right-drawer {
            position: fixed; top: 0; right: -320px; width: 280px; height: 100vh;
            background: #ffffff; box-shadow: -5px 0 25px rgba(0,0,0,0.15);
            transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 2001;
            display: flex; flex-direction: column;
        }
        .right-drawer.active { right: 0; }
        
        .drawer-header { padding: 25px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 5px; position: relative; }
        .btn-close-drawer { position: absolute; top: 20px; right: 20px; background: #fee2e2; color: #dc2626; border: none; width: 35px; height: 35px; border-radius: 10px; font-size: 1.2rem; cursor: pointer; }
        .drawer-avatar { width: 50px; height: 50px; background: #144973; color: white; border-radius: 15px; display: flex; justify-content: center; align-items: center; font-size: 1.5rem; font-weight: 800; margin-bottom: 10px; }
        
        .drawer-body { flex: 1; overflow-y: auto; padding: 20px; }
        .drawer-group { margin-bottom: 25px; }
        .drawer-title { font-size: 0.75rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 10px; letter-spacing: 0.5px; }
        
        .drawer-link { display: flex; align-items: center; gap: 12px; padding: 12px 14px; text-decoration: none; color: #1e293b; font-weight: 700; font-size: 0.95rem; border-radius: 12px; transition: background 0.2s; margin-bottom: 5px; }
        .drawer-link i { width: 24px; text-align: center; color: #64748b; font-size: 1.1rem; }
        .drawer-link:active { background: #f1f5f9; transform: scale(0.98); }
        .drawer-link.logout { color: #dc2626; background: #fef2f2; margin-top: 10px; }
        .drawer-link.logout i { color: #dc2626; }

        .app-loader { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.85); backdrop-filter: blur(6px); display: none; justify-content: center; align-items: center; flex-direction: column; z-index: 9999; }
        .spinner { width: 50px; height: 50px; border: 5px solid #e2e8f0; border-top-color: #144973; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .swal2-popup { border-radius: 24px !important; font-family: 'Poppins', sans-serif !important; }

        .actis-menu-desktop { display: none; }
        @media (max-width: 991px) {
            .ocultar-celular { display: none !important; }
            .btn-menu-mobile { display: block !important; }
        }
        @media (min-width: 992px) {
            .actis-bottom-bar { display: none; }
            .actis-menu-desktop { display: flex; gap: 8px; align-items: center; }
            .nav-link-desk { padding: 10px 15px; color: #475569; font-weight: 700; font-size: 0.9rem; text-decoration: none; border-radius: 10px; display: flex; align-items: center; gap: 8px; }
            .nav-link-desk:hover, .nav-link-desk.active { background: #f1f5f9; color: #144973; }
            .topbar-tools { display: none; }
        }
    </style>
</head>
<body>

<div class="app-loader" id="loader">
    <div class="spinner"></div>
</div>

<?php if(!isset($_GET['kiosk']) || $_GET['kiosk'] != 1): ?>
<header class="actis-navbar">
    <a href="dashboard.php" class="actis-brand">
        <img src="img/osfa.svg" alt="Logo ACTIS" style="width: 32px; height: 32px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"> ACTIS Core
    </a>
    
    <div class="topbar-tools" style="display: flex; align-items: center; gap: 10px;">
        <div class="live-clock"><i class="fa-regular fa-clock" style="color:#144973;"></i> <span id="clock-display">--:--:--</span></div>
        <button class="btn-menu-mobile" onclick="toggleDrawer()" style="background:transparent; border:none; font-size:1.5rem; color:#144973; cursor:pointer; padding: 0 5px; display: none;"><i class="fa-solid fa-bars"></i></button>
    </div>
    
    <nav class="actis-menu-desktop">
        <a href="dashboard.php" class="nav-link-desk <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="fa-solid fa-house"></i> Inicio</a>
        
        <?php if(in_array('modulo_turnos', $mis_permisos) || in_array('modulo_medico', $mis_permisos) || in_array('modulo_medico_planilla', $mis_permisos) || in_array('modulo_planilla_general', $mis_permisos) || in_array('modulo_pacientes', $mis_permisos)): ?>
        <div class="dropdown">
            <button class="dropdown-btn"><i class="fa-solid fa-stethoscope"></i> Atención <i class="fa-solid fa-chevron-down arrow"></i></button>
            <div class="dropdown-content">
                <?php if(in_array('modulo_turnos', $mis_permisos)): ?><a href="turnos_listar.php"><i class="fa-solid fa-calendar-check"></i> Turnos</a><?php endif; ?>
                <?php if(in_array('modulo_medico', $mis_permisos)): ?><a href="medico_panel.php"><i class="fa-solid fa-user-doctor"></i> Consultorio</a><?php endif; ?>
                <?php if(in_array('modulo_medico_planilla', $mis_permisos)): ?>
                    <a href="medico_planilla.php"><i class="fa-solid fa-file-invoice"></i> Mi Planilla en Pantalla</a>
                    <a href="planilla_medico_pdf.php?medico_id=<?php echo $_SESSION['usuario_id']; ?>" target="_blank"><i class="fa-solid fa-file-pdf"></i> Imprimir Mi Planilla</a>
                <?php endif; ?>
                <?php if(in_array('modulo_planilla_general', $mis_permisos)): ?><a href="planillas_buscador.php"><i class="fa-solid fa-magnifying-glass-chart"></i> Buscador de Planillas</a><?php endif; ?>
                <?php if(in_array('modulo_pacientes', $mis_permisos)): ?><a href="pacientes_listar.php"><i class="fa-solid fa-hospital-user"></i> Pacientes</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if(in_array('modulo_recepcion', $mis_permisos)): ?>
        <div class="dropdown">
            <button class="dropdown-btn"><i class="fa-solid fa-users"></i> Recepción <i class="fa-solid fa-chevron-down arrow"></i></button>
            <div class="dropdown-content">
                <a href="recepcion_inicio.php"><i class="fa-solid fa-user-check"></i> Recepción Entrada</a>
                <a href="recepcion_salidas_lista.php"><i class="fa-solid fa-person-walking-arrow-right"></i> Recepción Salida</a>
                <a href="recepcion_constancias.php"><i class="fa-solid fa-file-contract"></i> Constancias Médicas</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if(in_array('modulo_reportes', $mis_permisos) || in_array('modulo_totem_admin', $mis_permisos) || in_array('modulo_validador', $mis_permisos) || in_array('modulo_totem_ascensores', $mis_permisos)): ?>
        <div class="dropdown">
            <button class="dropdown-btn"><i class="fa-solid fa-chart-line"></i> Gestión <i class="fa-solid fa-chevron-down arrow"></i></button>
            <div class="dropdown-content">
                <?php if(in_array('modulo_validador', $mis_permisos)): ?><a href="https://validador.iosfa.gob.ar/ValidadorDni" target="_blank"><i class="fa-solid fa-file-signature"></i> Validador IOSFA</a><?php endif; ?>
                <?php if(in_array('modulo_reportes', $mis_permisos)): ?><a href="reporte_general.php"><i class="fa-solid fa-chart-pie"></i> Analíticas / Reportes</a><?php endif; ?>
                <?php if(in_array('modulo_totem_admin', $mis_permisos)): ?>
                    <a href="admin_servicios.php"><i class="fa-solid fa-list-ol"></i> Servicios Tótem</a>
                    <a href="admin_totem.php"><i class="fa-solid fa-desktop"></i> Parámetros Tótem</a>
                <?php endif; ?>
                <?php if(in_array('modulo_totem_admin', $mis_permisos) || in_array('modulo_totem_ascensores', $mis_permisos)): ?>
                    <a href="mantenimiento_ascensores.php"><i class="fa-solid fa-tools"></i> Operaciones Ascensores</a>
                    <a href="admin_ascensores.php"><i class="fa-solid fa-elevator"></i> Gestión de Ascensores</a>
                    <a href="admin_empresas.php"><i class="fa-solid fa-building"></i> Empresas Ascensores</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if(in_array('modulo_seguridad', $mis_permisos)): ?>
        <div class="dropdown">
            <button class="dropdown-btn"><i class="fa-solid fa-shield-halved"></i> Seguridad <i class="fa-solid fa-chevron-down arrow"></i></button>
            <div class="dropdown-content">
                <a href="seguridad_dashboard.php"><i class="fa-solid fa-chart-line"></i> Panel de Seguridad</a>
                <a href="seguridad_ingreso.php"><i class="fa-solid fa-id-card"></i> Escáner de Puerta</a>
                <a href="seguridad_llaves.php"><i class="fa-solid fa-key"></i> Gestión de Llaves</a>
                
                <?php if(in_array('modulo_seguridad_admin', $mis_permisos)): ?>
                <a href="seguridad_admin_llaves.php"><i class="fa-solid fa-gear"></i> Configurar Llaves y DNI</a>
                <?php endif; ?>
                
                <a href="seguridad_rondas.php"><i class="fa-solid fa-qrcode"></i> Rondas Nocturnas</a>
                <a href="seguridad_camaras.php"><i class="fa-solid fa-video"></i> Visor Cámaras</a>
                <a href="seguridad_intercomunicador.php"><i class="fa-solid fa-walkie-talkie"></i> Intercomunicador</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if(in_array('modulo_usuarios', $mis_permisos) || in_array('modulo_roles', $mis_permisos) || in_array('modulo_config_modal', $mis_permisos)): ?>
        <div class="dropdown">
            <button class="dropdown-btn"><i class="fa-solid fa-users-gear"></i> Admin <i class="fa-solid fa-chevron-down arrow"></i></button>
            <div class="dropdown-content">
                <?php if(in_array('modulo_usuarios', $mis_permisos)): ?><a href="usuarios_gestionar.php"><i class="fa-solid fa-user-gear"></i> Usuarios</a><?php endif; ?>
                <?php if(in_array('modulo_roles', $mis_permisos)): ?><a href="roles_gestionar.php"><i class="fa-solid fa-key"></i> Matriz de Roles</a><?php endif; ?>
                <?php if(in_array('modulo_config_modal', $mis_permisos)): ?><a href="admin_modal.php"><i class="fa-solid fa-window-restore"></i> Config. Modal ACTIS</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <a href="perfil.php" class="nav-link-desk <?php echo ($current_page == 'perfil.php') ? 'active' : ''; ?>"><i class="fa-solid fa-circle-user"></i> Perfil</a>
        <a href="logout.php" class="nav-link-desk" style="color:#ef4444;"><i class="fa-solid fa-power-off"></i> Salir</a>
    </nav>
</header>

<nav class="actis-bottom-bar">
    <a href="dashboard.php" class="bottom-tab <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-house"></i><span>Inicio</span>
    </a>
    
    <?php if(in_array('modulo_seguridad', $mis_permisos)): ?>
    <a href="seguridad_dashboard.php" class="bottom-tab <?php echo (strpos($current_page, 'seguridad_') !== false) ? 'active' : ''; ?>">
        <i class="fa-solid fa-shield-halved"></i><span>Seguridad</span>
    </a>
    <?php endif; ?>

    <?php if(in_array('modulo_turnos', $mis_permisos)): ?>
    <a href="turnos_listar.php" class="bottom-tab <?php echo ($current_page == 'turnos_listar.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-calendar-check"></i><span>Turnos</span>
    </a>
    <?php endif; ?>

    <?php if(in_array('modulo_reportes', $mis_permisos)): ?>
    <a href="reporte_general.php" class="bottom-tab <?php echo ($current_page == 'reporte_general.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-chart-pie"></i><span>Reportes</span>
    </a>
    <?php endif; ?>

    <?php if(in_array('modulo_totem_admin', $mis_permisos)): ?>
    <a href="admin_totem.php" class="bottom-tab <?php echo ($current_page == 'admin_totem.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-desktop"></i><span>Tótem</span>
    </a>
    <?php endif; ?>
</nav>

<div class="right-drawer-overlay" id="drawerOverlay" onclick="toggleDrawer()"></div>
<div class="right-drawer" id="sideDrawer">
    <div class="drawer-header" style="flex-direction: row; align-items: center; gap: 15px; padding: 20px;">
        <div class="drawer-avatar" style="margin-bottom: 0; width: 45px; height: 45px; flex-shrink: 0;"><?php echo isset($_SESSION['nombre']) ? strtoupper(substr($_SESSION['nombre'], 0, 1)) : 'U'; ?></div>
        <div style="display: flex; flex-direction: column; flex: 1; text-align: left;">
            <div style="font-weight: 900; font-size: 1rem; color: #0f172a; line-height: 1.2;"><?php echo isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre']) : 'Usuario'; ?></div>
            <div style="font-size: 0.75rem; font-weight: 700; color: #144973; text-transform: uppercase;"><?php echo htmlspecialchars($mi_rol_nombre); ?></div>
        </div>
        <button class="btn-close-drawer" onclick="toggleDrawer()" style="position: static; margin: 0; width: 35px; height: 35px; flex-shrink: 0;"><i class="fa-solid fa-xmark"></i></button>
    </div>
    
    <div class="drawer-body">
        
        <?php if(in_array('modulo_validador', $mis_permisos) || in_array('modulo_reportes', $mis_permisos) || in_array('modulo_totem_admin', $mis_permisos) || in_array('modulo_totem_ascensores', $mis_permisos)): ?>
        <div class="drawer-group">
            <div class="drawer-title">Gestión de Sistema</div>
            <?php if(in_array('modulo_validador', $mis_permisos)): ?>
            <a href="https://validador.iosfa.gob.ar/ValidadorDni" target="_blank" class="drawer-link ocultar-celular"><i class="fa-solid fa-file-signature"></i> Validador IOSFA</a>
            <?php endif; ?>
            <?php if(in_array('modulo_totem_admin', $mis_permisos)): ?>
            <a href="admin_servicios.php" class="drawer-link"><i class="fa-solid fa-list-ol"></i> Servicios del Tótem</a>
            <a href="admin_totem.php" class="drawer-link"><i class="fa-solid fa-desktop"></i> Parámetros Tótem</a>
            <?php endif; ?>
            <?php if(in_array('modulo_totem_admin', $mis_permisos) || in_array('modulo_totem_ascensores', $mis_permisos)): ?>
            <a href="mantenimiento_ascensores.php" class="drawer-link"><i class="fa-solid fa-tools"></i> Operaciones Ascensores</a>
            <a href="admin_ascensores.php" class="drawer-link"><i class="fa-solid fa-elevator"></i> Gestión de Ascensores</a>
            <a href="admin_empresas.php" class="drawer-link"><i class="fa-solid fa-building"></i> Empresas Ascensores</a>
            <?php endif; ?>
            <?php if(in_array('modulo_reportes', $mis_permisos)): ?>
            <a href="reporte_general.php" class="drawer-link"><i class="fa-solid fa-chart-pie"></i> Analíticas / Reportes</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if(in_array('modulo_recepcion', $mis_permisos)): ?>
        <div class="drawer-group">
            <div class="drawer-title">Recepción de Turnos</div>
            <a href="recepcion_inicio.php" class="drawer-link"><i class="fa-solid fa-user-check"></i> Recepción Entrada</a>
            <a href="recepcion_salidas_lista.php" class="drawer-link"><i class="fa-solid fa-person-walking-arrow-right"></i> Recepción Salida</a>
            <a href="recepcion_constancias.php" class="drawer-link"><i class="fa-solid fa-file-contract"></i> Constancias Médicas</a>
        </div>
        <?php endif; ?>

        <?php if(in_array('modulo_turnos', $mis_permisos) || in_array('modulo_medico', $mis_permisos) || in_array('modulo_medico_planilla', $mis_permisos) || in_array('modulo_planilla_general', $mis_permisos) || in_array('modulo_pacientes', $mis_permisos)): ?>
        <div class="drawer-group">
            <div class="drawer-title">Atención Médica</div>
            <?php if(in_array('modulo_turnos', $mis_permisos)): ?>
            <a href="turnos_listar.php" class="drawer-link"><i class="fa-solid fa-calendar-check"></i> Gestión de Turnos</a>
            <?php endif; ?>
            <?php if(in_array('modulo_medico', $mis_permisos)): ?>
            <a href="medico_panel.php" class="drawer-link"><i class="fa-solid fa-user-doctor"></i> Consultorio</a>
            <?php endif; ?>
            <?php if(in_array('modulo_medico_planilla', $mis_permisos)): ?>
            <a href="medico_planilla.php" class="drawer-link"><i class="fa-solid fa-file-invoice"></i> Mi Planilla en Pantalla</a>
            <a href="planilla_medico_pdf.php?medico_id=<?php echo $_SESSION['usuario_id']; ?>" target="_blank" class="drawer-link"><i class="fa-solid fa-file-pdf"></i> Imprimir Mi Planilla</a>
            <?php endif; ?>
            <?php if(in_array('modulo_planilla_general', $mis_permisos)): ?>
            <a href="planillas_buscador.php" class="drawer-link"><i class="fa-solid fa-magnifying-glass-chart"></i> Buscador de Planillas</a>
            <?php endif; ?>
            <?php if(in_array('modulo_pacientes', $mis_permisos)): ?>
            <a href="pacientes_listar.php" class="drawer-link"><i class="fa-solid fa-hospital-user"></i> Directorio Pacientes</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if(in_array('modulo_seguridad', $mis_permisos)): ?>
        <div class="drawer-group">
            <div class="drawer-title">Control de Seguridad</div>
            <a href="seguridad_dashboard.php" class="drawer-link"><i class="fa-solid fa-chart-line"></i> Panel de Seguridad</a>
            <a href="seguridad_ingreso.php" class="drawer-link"><i class="fa-solid fa-id-card"></i> Escáner de Puerta</a>
            <a href="seguridad_llaves.php" class="drawer-link"><i class="fa-solid fa-key"></i> Gestión de Llaves</a>
            
            <?php if(in_array('modulo_seguridad_admin', $mis_permisos)): ?>
            <a href="seguridad_admin_llaves.php" class="drawer-link"><i class="fa-solid fa-gear"></i> Configurar Llaves y DNI</a>
            <?php endif; ?>
            
            <a href="seguridad_rondas.php" class="drawer-link"><i class="fa-solid fa-qrcode"></i> Rondas Nocturnas</a>
            <a href="seguridad_camaras.php" class="drawer-link"><i class="fa-solid fa-video"></i> Visor Cámaras</a>
            <a href="seguridad_intercomunicador.php" class="drawer-link"><i class="fa-solid fa-walkie-talkie"></i> Intercomunicador</a>
        </div>
        <?php endif; ?>

        <?php if(in_array('modulo_usuarios', $mis_permisos) || in_array('modulo_roles', $mis_permisos) || in_array('modulo_config_modal', $mis_permisos)): ?>
        <div class="drawer-group">
            <div class="drawer-title">Administración</div>
            <?php if(in_array('modulo_usuarios', $mis_permisos)): ?>
            <a href="usuarios_gestionar.php" class="drawer-link"><i class="fa-solid fa-users-gear"></i> Cuentas de Usuario</a>
            <?php endif; ?>
            <?php if(in_array('modulo_roles', $mis_permisos)): ?>
            <a href="roles_gestionar.php" class="drawer-link"><i class="fa-solid fa-key"></i> Matriz de Permisos</a>
            <?php endif; ?>
            <?php if(in_array('modulo_config_modal', $mis_permisos)): ?>
            <a href="admin_modal.php" class="drawer-link"><i class="fa-solid fa-window-restore"></i> Config. Modal ACTIS</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="drawer-group" style="margin-top: 30px;">
            <a href="perfil.php" class="drawer-link <?php echo ($current_page == 'perfil.php') ? 'active' : ''; ?>"><i class="fa-solid fa-circle-user"></i> Mi Perfil</a>
            <a href="logout.php" class="drawer-link logout"><i class="fa-solid fa-power-off"></i> Cerrar Sesión</a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    function updateClock() {
        const now = new Date();
        let h = now.getHours().toString().padStart(2, '0');
        let m = now.getMinutes().toString().padStart(2, '0');
        let s = now.getSeconds().toString().padStart(2, '0');
        document.getElementById('clock-display').textContent = h + ':' + m + ':' + s;
    }
    setInterval(updateClock, 1000); updateClock();

    function toggleDrawer() {
        document.getElementById('sideDrawer').classList.toggle('active');
        document.getElementById('drawerOverlay').classList.toggle('active');
    }
    
    function mostrarLoader() { document.getElementById('loader').style.display = 'flex'; }
    function ocultarLoader() { document.getElementById('loader').style.display = 'none'; }
    
    const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true, background: '#ffffff', color: '#0f172a' });
    function mostrarExito(msj) { Toast.fire({ icon: 'success', title: msj }); }
    function mostrarError(msj) { Swal.fire({ icon: 'error', title: 'Error', text: msj, confirmButtonColor: '#ef4444', border: '1px solid #fee2e2' }); }
    function mostrarInfo(msj) { Swal.fire({ icon: 'info', title: 'Atención', text: msj, confirmButtonColor: '#144973' }); }
</script>

<main class="main-content">