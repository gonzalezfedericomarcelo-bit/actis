<?php
/**
 * totem_limpieza.php
 * Módulo de Control de Presentismo para Personal de Limpieza.
 * Diseñado para pantalla táctil en el Tótem Dashboard.
 */
session_start();

// Esta pantalla es de uso interno/rápido.
// Opcionalmente podemos validar sesión si se requiere mayor seguridad, 
// pero se accede tras poner el PIN de limpieza.
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Control de Asistencia - Limpieza</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
        min-height: 100vh;
        margin: 0;
        padding: 0;
        color: #f8fafc;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    
    .header {
        position: absolute;
        top: 20px;
        left: 30px;
        right: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .header-title { font-size: 2.5vh; font-weight: 800; color: #38bdf8; text-transform: uppercase; letter-spacing: 2px; }
    .btn-salir {
        background: rgba(239, 68, 68, 0.2);
        color: #fca5a5;
        border: 2px solid #ef4444;
        padding: 10px 20px;
        border-radius: 12px;
        font-size: 2vh;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        transition: 0.3s;
    }
    .btn-salir:hover { background: #ef4444; color: white; }

    /* Contenedor Principal */
    .container {
        width: 90vw;
        max-width: 800px;
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 30px;
        padding: 40px;
        text-align: center;
        box-shadow: 0 25px 50px rgba(0,0,0,0.4);
    }

    h1 { font-size: 4vh; font-weight: 900; margin-bottom: 20px; color: #f8fafc; }
    p.subtitle { font-size: 2vh; color: #cbd5e1; margin-bottom: 40px; }

    /* FASE 1: DNI NUMPAD */
    #fase-dni { display: block; }
    .dni-input {
        width: 100%;
        max-width: 400px;
        background: rgba(0,0,0,0.3);
        border: 2px solid #38bdf8;
        border-radius: 15px;
        color: #38bdf8;
        font-size: 5vh;
        font-weight: 900;
        text-align: center;
        padding: 15px;
        margin-bottom: 30px;
        letter-spacing: 5px;
        outline: none;
    }
    .numpad {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        max-width: 400px;
        margin: 0 auto;
    }
    .num-btn {
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.2);
        color: white;
        font-size: 4vh;
        font-weight: 700;
        padding: 20px 0;
        border-radius: 15px;
        cursor: pointer;
        transition: 0.2s;
    }
    .num-btn:active { background: #38bdf8; transform: scale(0.95); }
    .btn-action { background: #10b981; border: none; }
    .btn-action.red { background: #ef4444; }

    /* FASE 2: FIRMA */
    #fase-firma { display: none; }
    .info-box {
        background: rgba(56, 189, 248, 0.1);
        border-left: 5px solid #38bdf8;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 30px;
        text-align: left;
    }
    .info-box h2 { margin: 0 0 10px 0; font-size: 3vh; color: #38bdf8; }
    .info-box p { margin: 5px 0; font-size: 2vh; color: #e2e8f0; }

    .canvas-container {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        margin-bottom: 20px;
        border: 3px solid #cbd5e1;
    }
    canvas {
        display: block;
        width: 100%;
        height: 300px;
        touch-action: none;
        cursor: crosshair;
    }
    
    .btn-group { display: flex; gap: 20px; justify-content: center; }
    .btn-lg {
        padding: 15px 40px;
        font-size: 2.5vh;
        font-weight: 800;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        color: white;
        transition: 0.3s;
    }
    .btn-limpiar { background: #64748b; }
    .btn-limpiar:active { background: #475569; }
    .btn-guardar { background: #10b981; }
    .btn-guardar:active { background: #059669; transform: scale(0.95); }

</style>
</head>
<body>

<div class="header">
    <div class="header-title"><i class="fa-solid fa-broom"></i> Control Limpieza</div>
    <a href="dashboard_totem.php" class="btn-salir"><i class="fa-solid fa-xmark"></i> CERRAR</a>
</div>

<div class="container">
    
    <!-- FASE 1: INGRESAR DNI -->
    <div id="fase-dni">
        <h1>Ingresá tu DNI</h1>
        <p class="subtitle">Usá el teclado para identificarte y registrar tu horario.</p>
        
        <input type="text" id="dni-input" class="dni-input" readonly placeholder="DNI">
        
        <div class="numpad">
            <button class="num-btn" onclick="addNum('1')">1</button>
            <button class="num-btn" onclick="addNum('2')">2</button>
            <button class="num-btn" onclick="addNum('3')">3</button>
            <button class="num-btn" onclick="addNum('4')">4</button>
            <button class="num-btn" onclick="addNum('5')">5</button>
            <button class="num-btn" onclick="addNum('6')">6</button>
            <button class="num-btn" onclick="addNum('7')">7</button>
            <button class="num-btn" onclick="addNum('8')">8</button>
            <button class="num-btn" onclick="addNum('9')">9</button>
            <button class="num-btn btn-action red" onclick="clearDni()"><i class="fa-solid fa-eraser"></i></button>
            <button class="num-btn" onclick="addNum('0')">0</button>
            <button class="num-btn btn-action" onclick="buscarDni()"><i class="fa-solid fa-check"></i></button>
        </div>
    </div>

    <!-- FASE 2: FIRMA DIGITAL -->
    <div id="fase-firma">
        <h1>Registro de <span id="lbl-tipo" style="color: #38bdf8;">Entrada</span></h1>
        
        <div class="info-box">
            <h2 id="lbl-nombre">Nombre Empleado</h2>
            <p><i class="fa-regular fa-clock"></i> Fecha y Hora actual: <strong id="lbl-fecha"></strong></p>
        </div>

        <p class="subtitle" style="margin-bottom: 15px;">Por favor, firmá en el recuadro blanco para confirmar:</p>

        <div class="canvas-container">
            <canvas id="signature-pad"></canvas>
        </div>

        <div class="btn-group">
            <button class="btn-lg btn-limpiar" onclick="clearCanvas()"><i class="fa-solid fa-trash-can"></i> Limpiar Firma</button>
            <button class="btn-lg btn-guardar" onclick="guardarAsistencia()"><i class="fa-solid fa-floppy-disk"></i> Confirmar y Guardar</button>
        </div>
    </div>

</div>

<script>
    // Variables Globales
    let currentDni = '';
    let personalId = 0;
    let tipoRegistro = ''; // 'entrada' o 'salida'

    // --- FASE 1: NUMPAD ---
    function addNum(num) {
        if (currentDni.length < 8) {
            currentDni += num;
            document.getElementById('dni-input').value = currentDni;
        }
    }
    function clearDni() {
        currentDni = '';
        document.getElementById('dni-input').value = '';
    }
    function resetFlow() {
        clearDni();
        personalId = 0;
        tipoRegistro = '';
        clearCanvas();
        document.getElementById('fase-firma').style.display = 'none';
        document.getElementById('fase-dni').style.display = 'block';
    }

    function buscarDni() {
        if (currentDni.length < 6) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Ingresá un DNI válido.'});
            return;
        }

        Swal.fire({ title: 'Buscando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        const fd = new FormData();
        fd.append('action', 'buscar_dni');
        fd.append('dni', currentDni);

        fetch('api_limpieza.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    if (data.estado_asistencia === 'finalizado') {
                        Swal.fire({ icon: 'info', title: 'Jornada Completa', text: `Hola ${data.nombre}. Ya marcaste tu entrada y salida por hoy.`, confirmButtonColor: '#38bdf8' })
                            .then(() => { resetFlow(); });
                    } else {
                        prepararFirma(data.nombre, data.personal_id, data.estado_asistencia);
                    }
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                    clearDni();
                }
            })
            .catch(err => {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Problema de conexión.' });
            });
    }

    // --- FASE 2: FIRMA ---
    function prepararFirma(nombre, id, estado) {
        personalId = id;
        tipoRegistro = (estado === 'sin_entrada') ? 'entrada' : 'salida';
        
        document.getElementById('lbl-tipo').innerText = tipoRegistro.toUpperCase();
        document.getElementById('lbl-nombre').innerText = nombre;
        document.getElementById('lbl-fecha').innerText = new Date().toLocaleString('es-AR');
        
        document.getElementById('fase-dni').style.display = 'none';
        document.getElementById('fase-firma').style.display = 'block';
        resizeCanvas();
    }

    // Lógica del Canvas (Firma)
    const canvas = document.getElementById('signature-pad');
    const ctx = canvas.getContext('2d');
    let isDrawing = false;
    let hasSignature = false;

    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        ctx.scale(ratio, ratio);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.lineWidth = 4;
        ctx.strokeStyle = '#0f172a';
        clearCanvas();
    }
    
    // Resize on orientation change
    window.addEventListener('resize', () => { if(document.getElementById('fase-firma').style.display === 'block') resizeCanvas(); });

    function getMousePos(e) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.clientX || (e.touches && e.touches[0].clientX);
        const clientY = e.clientY || (e.touches && e.touches[0].clientY);
        return {
            x: clientX - rect.left,
            y: clientY - rect.top
        };
    }

    function startDrawing(e) {
        e.preventDefault();
        isDrawing = true;
        hasSignature = true;
        const pos = getMousePos(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
    }

    function draw(e) {
        if (!isDrawing) return;
        e.preventDefault();
        const pos = getMousePos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    }

    function stopDrawing() { isDrawing = false; }

    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);
    
    canvas.addEventListener('touchstart', startDrawing, {passive: false});
    canvas.addEventListener('touchmove', draw, {passive: false});
    canvas.addEventListener('touchend', stopDrawing);

    function clearCanvas() {
        ctx.clearRect(0, 0, canvas.width / (window.devicePixelRatio || 1), canvas.height / (window.devicePixelRatio || 1));
        hasSignature = false;
        ctx.beginPath();
    }

    function guardarAsistencia() {
        if (!hasSignature) {
            Swal.fire({ icon: 'warning', title: 'Firma requerida', text: 'Por favor, firmá en el recuadro antes de continuar.' });
            return;
        }

        const dataUrl = canvas.toDataURL('image/png'); // Base64
        
        Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        const fd = new FormData();
        fd.append('action', 'registrar_asistencia');
        fd.append('personal_id', personalId);
        fd.append('tipo', tipoRegistro);
        fd.append('firma', dataUrl);

        fetch('api_limpieza.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({ 
                        icon: 'success', 
                        title: '¡Registrado!', 
                        text: `Tu ${tipoRegistro} se guardó correctamente.`,
                        timer: 3000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'dashboard_totem.php';
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message });
                }
            })
            .catch(err => {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Problema de conexión al guardar.' });
            });
    }

    // Prevenir menú contextual en touch prolongado
    window.oncontextmenu = function(event) {
        event.preventDefault();
        event.stopPropagation();
        return false;
    };
</script>
</body>
</html>
