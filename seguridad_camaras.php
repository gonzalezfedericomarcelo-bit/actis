<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';

if (!isset($mis_permisos) || !in_array('modulo_seguridad', $mis_permisos)) { 
    echo "<script>window.location='dashboard.php';</script>"; 
    exit; 
}
?>
<style>
    .camaras-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 15px; }
    .camara-box { background: #000; border-radius: 12px; overflow: hidden; position: relative; aspect-ratio: 16/9; border: 2px solid #1e293b; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2); }
    .camara-label { position: absolute; top: 10px; left: 10px; background: rgba(0,0,0,0.7); color: #fff; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 800; z-index: 10; display: flex; align-items: center; gap: 6px; }
    .camara-label i { color: #ef4444; animation: blink 2s infinite; }
    @keyframes blink { 0% { opacity: 1; } 50% { opacity: 0.3; } 100% { opacity: 1; } }
    .placeholder-vid { width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; color: #475569; }
    @media (max-width: 768px) { .camaras-grid { grid-template-columns: 1fr; } }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <h2 style="margin:0; color:#0f172a;"><i class="fa-solid fa-video"></i> Centro de Monitoreo</h2>
    <button style="width:auto; padding:8px 15px; background:transparent; border:1px solid #cbd5e1; border-radius:8px; color:#475569; font-weight:700;"><i class="fa-solid fa-gear"></i> Configurar DVR</button>
</div>

<div class="camaras-grid">
    <div class="camara-box">
        <div class="camara-label"><i class="fa-solid fa-circle"></i> CH 01 - Acceso Principal</div>
        <div class="placeholder-vid">
            <i class="fa-solid fa-video-slash fa-3x" style="margin-bottom:10px;"></i>
            <span>Sin Señal RTSP Configurada</span>
        </div>
    </div>
    
    <div class="camara-box">
        <div class="camara-label"><i class="fa-solid fa-circle"></i> CH 02 - Pasillo Odontología</div>
        <div class="placeholder-vid">
            <i class="fa-solid fa-video-slash fa-3x" style="margin-bottom:10px;"></i>
            <span>Sin Señal RTSP Configurada</span>
        </div>
    </div>

    <div class="camara-box">
        <div class="camara-label"><i class="fa-solid fa-circle"></i> CH 03 - Portón Vehicular (Nocturno)</div>
        <div class="placeholder-vid">
            <i class="fa-solid fa-video-slash fa-3x" style="margin-bottom:10px;"></i>
            <span>Sin Señal RTSP Configurada</span>
        </div>
    </div>

    <div class="camara-box">
        <div class="camara-label"><i class="fa-solid fa-circle"></i> CH 04 - Logística / El Chino</div>
        <div class="placeholder-vid">
            <i class="fa-solid fa-video-slash fa-3x" style="margin-bottom:10px;"></i>
            <span>Sin Señal RTSP Configurada</span>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
