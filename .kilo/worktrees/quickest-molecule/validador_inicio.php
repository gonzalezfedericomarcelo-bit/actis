<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
require_once 'includes/header.php';
?>

<style>
    .dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .dash-title { font-size: 1.8rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 10px; }
    .dash-title i { color: #2563eb; }

    .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; align-items: start; max-width: 1200px; margin: 0 auto; }
    .grid-col { display: flex; flex-direction: column; gap: 20px; }
    
    .chart-card { background: #fff; border-radius: 16px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; display: flex; flex-direction: column; transition: all 0.3s ease; }
    .chart-card h3 { font-size: 1.2rem; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
    
    .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 15px; }
    .form-group label { font-size: 0.85rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    
    .input-mobile { width: 100%; padding: 14px 18px; font-size: 1.8rem; border: 2px solid #cbd5e1; border-radius: 12px; background: #f8fafc; outline: none; font-weight: 900; color: #0f172a; transition: all 0.2s; box-sizing: border-box; text-align: center; letter-spacing: 4px; }
    .input-mobile:focus { border-color: #2563eb; background: white; box-shadow: 0 0 0 4px rgba(37,99,235,0.1); }
    
    .btn-mobile { width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 16px; border-radius: 12px; font-weight: 800; font-size: 1.1rem; border: none; cursor: pointer; color: white; text-transform: uppercase; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 0 #1e40af; }
    .btn-mobile:active { transform: scale(0.98); }
    .bg-blue { background: #2563eb; } .bg-blue:hover { background: #1d4ed8; }
    
    .status-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; font-weight: 700; color: #475569; font-size: 0.95rem; }
    .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; color: white; background: #10b981; font-weight: 800; text-transform: uppercase; }
</style>

<div class="dash-header">
    <h1 class="dash-title"><i class="fa-solid fa-desktop"></i> Validación de Pacientes (Ventanilla)</h1>
</div>

<div class="dashboard-grid">
    
    <div class="grid-col" style="grid-column: span 2;">
        <div class="chart-card">
            <h3><i class="fa-solid fa-id-card" style="color: #2563eb;"></i> Consulta de Padrón Directa</h3>
            <div class="form-group">
                <label>Ingrese el documento del afiliado o escanee el código de barras:</label>
                <input type="text" id="dni_operario" class="input-mobile" placeholder="DNI DEL AFILIADO" autocomplete="off" autofocus>
            </div>
            <button type="button" id="btn_buscar_operario" onclick="ejecutarConsultaBackground()" class="btn-mobile bg-blue">
                <i class="fa-solid fa-magnifying-glass"></i> Consultar IOSFA
            </button>
            
            <div id="panel_error_operario" style="margin-top: 20px; padding: 15px; border-radius: 12px; font-weight: 800; font-size: 1rem; text-align: center; display: none;"></div>
        </div>
    </div>

    <div class="grid-col">
        <div class="chart-card">
            <h3><i class="fa-solid fa-print" style="color: #10b981;"></i> Configuración</h3>
            
            <div class="form-group">
                <label>Tamaño de Comprobante:</label>
                <select id="papel_ventanilla" class="input-mobile" style="font-size: 1rem; padding: 12px; letter-spacing: 0; text-align: left; font-weight: 700; cursor:pointer;">
                    <option value="80">Ticketeadora Térmica (80mm)</option>
                    <option value="58">Ticketeadora Térmica (58mm)</option>
                </select>
            </div>

            <div class="status-item" style="margin-top: 10px;">
                <span>Canal Backend:</span>
                <span class="status-badge">GM-HTTP HABILITADO</span>
            </div>
        </div>
    </div>

</div>

<form id="form_transf_actis" method="GET" action="imprimir_ventanilla_validacion.php" style="display:none;">
    <input type="hidden" name="dni" id="transf_dni">
    <input type="hidden" name="token" id="transf_token">
    <input type="hidden" name="nombre" id="transf_nombre">
    <input type="hidden" name="mm" id="transf_papel">
</form>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        let papel = localStorage.getItem('actis_v_papel') || '80';
        document.getElementById('papel_ventanilla').value = papel;
        document.getElementById('papel_ventanilla').addEventListener('change', (e) => {
            localStorage.setItem('actis_v_papel', e.target.value);
        });
        
        // Bloquear letras, solo números en el campo
        document.getElementById('dni_operario').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Evento Enter físico del teclado o de la lectora de barras
        document.getElementById('dni_operario').addEventListener('keypress', function(e) {
            if(e.key === 'Enter') { e.preventDefault(); ejecutarConsultaBackground(); }
        });
    });

    function ejecutarConsultaBackground() {
        let dniInput = document.getElementById('dni_operario');
        let btn = document.getElementById('btn_buscar_operario');
        let errorPanel = document.getElementById('panel_error_operario');
        let dni = dniInput.value.trim();

        if (dni.length < 7) {
            errorPanel.innerHTML = '❌ Ingrese un número de documento válido.';
            errorPanel.style.background = '#fee2e2';
            errorPanel.style.color = '#dc2626';
            errorPanel.style.border = '1px solid #fecaca';
            errorPanel.style.display = 'block';
            return;
        }

        errorPanel.style.display = 'none';
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> INTERROGANDO PADRÓN CENTRAL DE IOSFA...';
        dniInput.disabled = true;


        // 2. DESPUÉS emitimos el evento nativo para que Tampermonkey lo intercepte a tiempo
        window.dispatchEvent(new CustomEvent('ActisConsultarIOSFA', { detail: dni }));
    }

    
    // Escuchar cuando Tampermonkey devuelve un ERROR
    window.addEventListener('ActisErrorIOSFA', function(e) {
        clearTimeout(window.actisTimeoutDetect);
        let msg = e.detail;
        let dniInput = document.getElementById('dni_operario');
        let btn = document.getElementById('btn_buscar_operario');
        let errorPanel = document.getElementById('panel_error_operario');

        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Consultar IOSFA';
        dniInput.disabled = false;
        dniInput.focus();

        errorPanel.innerHTML = '❌ ' + msg;
        errorPanel.style.background = '#fee2e2';
        errorPanel.style.color = '#dc2626';
        errorPanel.style.border = '1px solid #fecaca';
        errorPanel.style.display = 'block';
    });

    // Escuchar cuando Tampermonkey devuelve el EXITO con los datos
window.addEventListener('ActisExitoIOSFA', function(e) {
    clearTimeout(window.actisTimeoutDetect);
    let data = e.detail;
    
    let dniInput = document.getElementById('dni_operario');
    let btn = document.getElementById('btn_buscar_operario');
    let panel = document.getElementById('panel_error_operario'); // Reutilizamos el panel para mostrar el éxito

    // Restaurar la interfaz para el próximo paciente
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Consultar IOSFA';
    dniInput.disabled = false;
    dniInput.value = ''; 
    dniInput.focus();

    // Mostrar todos los datos extraídos en pantalla
    panel.innerHTML = `
        <div style="text-align: left; color: #5274AD;">
            <h3 style="margin-top: 0; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;"><i class="fa-solid fa-check-circle"></i> AFILIADO VALIDADO</h3>
            <p style="margin: 6px 0;"><strong>Paciente:</strong> ${data.nombre}</p>
            <p style="margin: 6px 0;"><strong>Nro. Afiliado:</strong> ${data.nro_afiliado}</p>
            <p style="margin: 6px 0;"><strong>DNI:</strong> ${data.dni}</p>
            <p style="margin: 10px 0 0 0; font-size: 1.2rem;"><strong>Token:</strong> <span style="background: #0f172a; color: #ffffff; padding: 3px 10px; border-radius: 6px;">${data.token}</span></p>
        </div>
    `;
    panel.style.background = '#f8fafc';
    panel.style.border = '2px solid #5274AD';
    panel.style.display = 'block';

    // Cargar los datos exactos en el formulario para la ticketeadora
    document.getElementById('transf_dni').value = data.dni;
    document.getElementById('transf_token').value = data.token;
    document.getElementById('transf_nombre').value = data.nombre;
    document.getElementById('transf_papel').value = document.getElementById('papel_ventanilla').value;
    
    // Esperar 2.5 segundos para que se pueda leer, y luego mandar a imprimir
    setTimeout(() => {
        document.getElementById('form_transf_actis').submit();
        
        // Opcional: Ocultar el panel después de imprimir
        setTimeout(() => { panel.style.display = 'none'; }, 1000);
    }, 2500);
});
</script>

<?php require_once 'includes/footer.php'; ?>