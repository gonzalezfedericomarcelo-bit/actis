<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Monitor Tótem En Vivo</title>
    <style>

        body { background: #0f172a; color: white; font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .pantalla { width: 98vw; max-width: none; height: 96vh; border: 6px solid #38bdf8; border-radius: 15px; position: relative; background: linear-gradient(-45deg, #eff6ff, #dbeafe, #bfdbfe); color: #0f172a; overflow: hidden; padding: 20px; box-sizing: border-box; box-shadow: 0 10px 30px rgba(0,0,0,0.8); display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .estado-badge { position: absolute; top: 10px; right: 10px; background: #22c55e; color: white; padding: 5px 15px; border-radius: 20px; font-weight: bold; font-size: 14px; animation: titilar 1.5s infinite; }
        @keyframes titilar { 0% {opacity:1;} 50% {opacity:0.5;} 100% {opacity:1;} }
        .texto-principal { font-size: 4vh; font-weight: 900; color: #0284c7; text-align: center; text-transform: uppercase; margin-bottom: 2vh; }
        .dato-destacado { font-size: 5vh; font-weight: 900; background: white; padding: 2vh 4vw; border-radius: 15px; border: 4px solid #0284c7; color: #0f172a; text-align: center; min-width: 50%; box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        
        @media (max-width: 768px) {
            .pantalla { width: 95vw; height: 95vh; border-width: 4px; padding: 10px; }
            .texto-principal { font-size: 3vh; margin-bottom: 1.5vh; padding: 0 10px; }
            .dato-destacado { font-size: 3.5vh; padding: 1.5vh 3vw; min-width: 85%; max-width: 95%; word-break: break-word; box-sizing: border-box; }
            .estado-badge { font-size: 12px; padding: 4px 10px; top: 10px; right: 10px; }
        }
    </style>
</head>
<body>
    <div class="pantalla" id="visor">
        <div class="estado-badge">🔴 EN VIVO</div>
        <div id="contenido" style="width: 100%; display: flex; flex-direction: column; align-items: center; flex-grow: 1; justify-content: center;">
            <div class="texto-principal">ESPERANDO CONEXIÓN DEL TÓTEM...</div>
        </div>
        <div style="width: 100%; padding: 1.5vh; background: white; border-radius: 12px; border: 3px dashed #94a3b8; text-align: center; font-size: 2.8vh; font-weight: 800; color: #64748b; margin-top: 2vh; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);">
            ⚡ ÚLTIMA ACCIÓN: <span id="log-accion" style="color: #0ea5e9; font-weight: 900;">ESPERANDO...</span>
        </div>
    </div>
    <script>
        setInterval(() => {
            fetch('estado_totem.json?t=' + Date.now())
            .then(r => r.json())
            .then(data => {
                let html = '';
                if (data.salvapantallas) {
                    html = '<div class="texto-principal">TÓTEM LIBRE</div><div class="dato-destacado" style="border-color:#94a3b8; color:#64748b;">Mostrando Salvapantallas</div>';
                } else if (data.ayuda_activa) {
                    html = '<div class="texto-principal" style="color:#dc2626;">¡SOLICITANDO AYUDA!</div><div class="dato-destacado" style="border-color:#dc2626; color:#dc2626;">El usuario abrió el panel de alerta</div>';
                } else if (data.modal_activo) {
                    html = '<div class="texto-principal">MOSTRANDO RESULTADO / ERROR</div><div style="background:white; padding:20px; border-radius:15px; width:80%; max-height:60vh; overflow:auto; border:4px solid #22c55e;">' + data.html_modal + '</div>';
                } else if (data.modo === '') {
                    html = '<div class="texto-principal">PANTALLA DE INICIO</div><div class="dato-destacado">Eligiendo operación</div>';
                } else if (data.modo !== '' && data.servicio_seleccionado === '' && data.dni_input !== '') {
                    let dniLimpio = data.dni_input.includes('Ingrese') ? '' : data.dni_input;
                    html = '<div class="texto-principal">INGRESANDO DNI (' + data.modo + ')</div><div class="dato-destacado">' + (dniLimpio || 'Escribiendo...') + '</div>';
                } else if (data.modo !== '' && data.servicio_seleccionado === '') {
                     html = '<div class="texto-principal">PANTALLA DE SERVICIOS</div><div class="dato-destacado">Buscando Especialidad...</div>';
                } else if (data.servicio_seleccionado !== '') {
                     html = '<div class="texto-principal">TRÁMITE SELECCIONADO</div><div class="dato-destacado" style="border-color:#22c55e;">' + data.servicio_seleccionado + '</div>';
                }
                document.getElementById('contenido').innerHTML = html;
                if(data.ultima_accion) {
                    document.getElementById('log-accion').innerText = data.ultima_accion;
                }
            }).catch(e => {
                document.getElementById('contenido').innerHTML = '<div class="texto-principal" style="color:#dc2626;">SIN CONEXIÓN</div>';
            });
        }, 1000);
    </script>
</body>
</html>