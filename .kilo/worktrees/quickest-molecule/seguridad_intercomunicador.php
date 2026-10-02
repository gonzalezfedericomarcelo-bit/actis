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
    .intercom-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
    .contact-card { background: #fff; border-radius: 16px; padding: 25px; text-align: center; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .contact-avatar { width: 70px; height: 70px; background: #f1f5f9; color: #3b82f6; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 2rem; margin: 0 auto 15px auto; }
    .contact-name { font-size: 1.2rem; font-weight: 800; color: #0f172a; margin: 0 0 5px 0; }
    .contact-role { font-size: 0.85rem; color: #64748b; margin: 0 0 20px 0; }
    .btn-call { width: 100%; padding: 12px; background: #10b981; color: #fff; border: none; border-radius: 12px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px; }
    .btn-call:hover { background: #059669; }
    .btn-emergency { background: #ef4444; }
    .btn-emergency:hover { background: #dc2626; }
</style>

<div style="margin-bottom: 30px;">
    <h2 style="margin:0; color:#0f172a;"><i class="fa-solid fa-walkie-talkie"></i> Intercomunicador IP</h2>
    <p style="color:#64748b; font-size:0.9rem; margin-top:5px;">Llamadas directas por red local usando el micrófono de la PC.</p>
</div>

<div class="intercom-grid">
    <div class="contact-card">
        <div class="contact-avatar"><i class="fa-solid fa-user-tie"></i></div>
        <h3 class="contact-name">Dirección / Admin</h3>
        <p class="contact-role">Oficina Principal</p>
        <button class="btn-call" onclick="iniciarLlamada('Administración')"><i class="fa-solid fa-phone"></i> Llamar</button>
    </div>

    <div class="contact-card">
        <div class="contact-avatar"><i class="fa-solid fa-boxes-stacked"></i></div>
        <h3 class="contact-name">Jefe Logística</h3>
        <p class="contact-role">Depósito y Compras</p>
        <button class="btn-call" onclick="iniciarLlamada('Logística')"><i class="fa-solid fa-phone"></i> Llamar</button>
    </div>

    <div class="contact-card">
        <div class="contact-avatar"><i class="fa-solid fa-server"></i></div>
        <h3 class="contact-name">Soporte IT</h3>
        <p class="contact-role">Sistemas y Tótem</p>
        <button class="btn-call" onclick="iniciarLlamada('Soporte IT')"><i class="fa-solid fa-phone"></i> Llamar</button>
    </div>

    <div class="contact-card" style="border: 2px solid #fee2e2;">
        <div class="contact-avatar" style="background:#fee2e2; color:#ef4444;"><i class="fa-solid fa-truck-medical"></i></div>
        <h3 class="contact-name">Emergencia 911</h3>
        <p class="contact-role">Línea Fija Directa (VoIP)</p>
        <button class="btn-call btn-emergency" onclick="llamar911()"><i class="fa-solid fa-phone-volume"></i> SOS Inmediato</button>
    </div>
</div>

<script>
    function iniciarLlamada(sector) {
        Swal.fire({
            title: 'Llamando...',
            text: 'Conectando con ' + sector,
            iconHtml: '<i class="fa-solid fa-phone-volume" style="color:#3b82f6; animation: pulse 1s infinite;"></i>',
            showCancelButton: true,
            showConfirmButton: false,
            cancelButtonText: 'Colgar',
            cancelButtonColor: '#ef4444'
        });
    }

    function llamar911() {
        Swal.fire({
            title: '¿Disparar Alerta?',
            text: "Esta acción llamará directamente a la Comisaría/Bomberos.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Sí, Llamar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) { mostrarInfo('Iniciando conexión segura de emergencia...'); }
        });
    }
</script>
<?php require_once 'includes/footer.php'; ?>
