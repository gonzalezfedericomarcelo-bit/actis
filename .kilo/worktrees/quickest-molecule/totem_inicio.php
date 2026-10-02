<?php 
require_once 'includes/conexion.php'; 
$q_evac = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'estado_manual'");
$is_evacuacion = false;
if ($q_evac && $q_evac->num_rows > 0) {
    if ($q_evac->fetch_assoc()['estado'] == 'EVACUACION') {
        $is_evacuacion = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Autogestión ACTIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --color-primario: #0284c7;
            --color-exito: #16a34a;
            --bg-color: #f0f9ff;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            display: flex;
            flex-direction: column;
            height: 100vh;
            user-select: none;
            overflow: hidden;
            padding-bottom: 120px;
        }
        .header-totem {
            background: white;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .header-totem h1 {
            margin: 0;
            color: var(--color-primario);
            font-size: 3rem;
            font-weight: 800;
            letter-spacing: 2px;
        }
        .contenedor-principal {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .pantalla-dni {
            background: white;
            width: 100%;
            max-width: 550px;
            height: 90px;
            border-radius: 20px;
            border: 4px solid var(--color-primario);
            font-size: 3.5rem;
            font-weight: 900;
            text-align: center;
            line-height: 85px;
            margin-bottom: 30px;
            color: #1e293b;
            box-shadow: inset 0 4px 6px rgba(0,0,0,0.1);
            letter-spacing: 8px;
        }
        .teclado-numerico {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            width: 100%;
            max-width: 550px;
        }
        .btn-tecla {
            background: white;
            border: none;
            border-radius: 20px;
            font-size: 3rem;
            font-weight: 800;
            color: #334155;
            padding: 25px 0;
            cursor: pointer;
            box-shadow: 0 6px 0 #cbd5e1;
            transition: all 0.1s;
        }
        .btn-tecla:active {
            transform: translateY(6px);
            box-shadow: 0 0 0 #cbd5e1;
            background-color: #f1f5f9;
        }
        .btn-accion {
            background: #e2e8f0;
            color: #475569;
        }
        .btn-borrar {
            background: #fee2e2;
            color: #ef4444;
        }
        .btn-validar {
            background: var(--color-exito);
            color: white;
            font-size: 2.2rem;
            grid-column: span 3;
            padding: 25px 0;
            margin-top: 15px;
            box-shadow: 0 6px 0 #15803d;
        }
        .btn-validar:active {
            box-shadow: 0 0 0 #15803d;
            background-color: #15803d;
        }
        .btn-validar:disabled {
            background: #94a3b8;
            box-shadow: 0 6px 0 #64748b;
            opacity: 0.7;
            transform: none;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

<div class="header-totem">
    <h1>POLICLÍNICA GENERAL ACTIS</h1>
    <p style="font-size: 1.5rem; color: #64748b; margin: 10px 0 0 0; font-weight: 600;">Terminal de Autogestión</p>
</div>

<div class="contenedor-principal">
    <?php if ($is_evacuacion): ?>
        <div style="background:#ef4444; color:white; padding: 40px; border-radius: 20px; text-align:center; box-shadow: 0 10px 25px rgba(239,68,68,0.4); border: 4px solid #b91c1c; width: 100%; max-width: 600px;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size: 5rem; margin-bottom:20px;"></i>
            <h2 style="font-size: 3rem; margin:0 0 10px 0; font-weight:900;">CÓDIGO ROJO</h2>
            <p style="font-size: 1.5rem; margin:0; font-weight:600;">Por favor, diríjase a la salida más cercana de manera ordenada.</p>
        </div>
    <?php else: ?>
        <h2 style="font-size: 2.5rem; margin-bottom: 25px; color: #334155;">Ingrese su número de DNI</h2>
        
        <form id="formTotem" action="totem_procesar.php" method="POST" style="width: 100%; display: flex; flex-direction: column; align-items: center;">
            <input type="hidden" name="dni_paciente" id="dni_input" value="">
            <input type="hidden" name="token_generado" id="token_input" value="">
            <div class="pantalla-dni" id="dni_display"></div>

            <div class="teclado-numerico">
                <button type="button" class="btn-tecla" onclick="agregarNumero('1')">1</button>
                <button type="button" class="btn-tecla" onclick="agregarNumero('2')">2</button>
                <button type="button" class="btn-tecla" onclick="agregarNumero('3')">3</button>
                <button type="button" class="btn-tecla" onclick="agregarNumero('4')">4</button>
                <button type="button" class="btn-tecla" onclick="agregarNumero('5')">5</button>
                <button type="button" class="btn-tecla" onclick="agregarNumero('6')">6</button>
                <button type="button" class="btn-tecla" onclick="agregarNumero('7')">7</button>
                <button type="button" class="btn-tecla" onclick="agregarNumero('8')">8</button>
                <button type="button" class="btn-tecla" onclick="agregarNumero('9')">9</button>
                <button type="button" class="btn-tecla btn-borrar" onclick="borrarUltimo()"><i class="fa-solid fa-delete-left"></i></button>
                <button type="button" class="btn-tecla" onclick="agregarNumero('0')">0</button>
                <button type="button" class="btn-tecla btn-accion" onclick="limpiarTodo()">C</button>
                
                <button type="button" class="btn-tecla btn-validar" id="btn_submit" onclick="iniciarValidacionTotem()" disabled>
                    <i class="fa-solid fa-print"></i> OBTENER TICKET
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
    const dniDisplay = document.getElementById('dni_display');
    const dniInput = document.getElementById('dni_input');
    const btnSubmit = document.getElementById('btn_submit');

    function actualizarPantalla() {
        dniDisplay.innerText = dniInput.value;
        if(dniInput.value.length >= 7) {
            btnSubmit.disabled = false;
        } else {
            btnSubmit.disabled = true;
        }
    }

    function agregarNumero(num) {
        if (dniInput.value.length < 8) {
            dniInput.value += num;
            actualizarPantalla();
        }
    }

    function borrarUltimo() {
        dniInput.value = dniInput.value.slice(0, -1);
        actualizarPantalla();
    }

    function limpiarTodo() {
        dniInput.value = '';
        actualizarPantalla();
    }

    function iniciarValidacionTotem() {
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> VALIDANDO IOSFA...';
        
        localStorage.setItem('totem_solicitar_busqueda', dniInput.value);
        
        let ventanaIosfa = window.open('https://validador.iosfa.gob.ar/ValidadorMejorado', 'ValidadorIOSFA', 'width=800,height=600,top=10000,left=10000');
        
        let intentos = 0;
        let vigia = setInterval(() => {
            intentos++;
            let codigo_iofa = localStorage.getItem('totem_codigo_iofa_final');
            
            if (codigo_iofa) {
                clearInterval(vigia);
                localStorage.removeItem('totem_codigo_iofa_final');
                
                if(codigo_iofa === 'ERROR') {
                    btnSubmit.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ERROR IOSFA';
                    if(ventanaIosfa) ventanaIosfa.close();
                    setTimeout(() => { 
                        btnSubmit.innerHTML = '<i class="fa-solid fa-print"></i> OBTENER TICKET';
                        btnSubmit.disabled = false;
                    }, 3000);
                } else {
                    document.getElementById('token_input').value = codigo_iofa;
                    document.getElementById('formTotem').submit();
                }
            } else if (intentos > 40) { 
                clearInterval(vigia);
                btnSubmit.innerHTML = '<i class="fa-solid fa-clock"></i> TIEMPO AGOTADO';
                if(ventanaIosfa) ventanaIosfa.close();
                setTimeout(() => { 
                    btnSubmit.innerHTML = '<i class="fa-solid fa-print"></i> OBTENER TICKET';
                    btnSubmit.disabled = false;
                }, 3000);
            }
        }, 500);
    }  

    actualizarPantalla();
</script>
</body>
</html>