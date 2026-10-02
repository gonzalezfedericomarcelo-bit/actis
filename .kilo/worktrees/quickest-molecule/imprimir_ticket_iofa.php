<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once 'includes/conexion.php';

$dni = isset($_GET['dni']) ? htmlspecialchars($_GET['dni']) : '';
$nombre = isset($_GET['nombre']) ? htmlspecialchars($_GET['nombre']) : 'AFILIADO';
$afiliado = isset($_GET['afiliado']) ? htmlspecialchars($_GET['afiliado']) : '';
$estado = isset($_GET['estado']) ? htmlspecialchars($_GET['estado']) : '';
$fuerza = isset($_GET['fuerza']) ? htmlspecialchars($_GET['fuerza']) : '';
$codigo = isset($_GET['codigo']) ? htmlspecialchars($_GET['codigo']) : '';
$servicio = isset($_GET['servicio']) ? htmlspecialchars($_GET['servicio']) : 'GENERAL';
$modo = isset($_GET['modo']) ? htmlspecialchars($_GET['modo']) : 'VALIDACION';
$tiempo = isset($_GET['tiempo']) ? (float)$_GET['tiempo'] : 0;

if (empty($codigo)) {
    header("Location: https://validador.iosfa.gob.ar/ValidadorDni");
    exit;
}

// Crear tabla de estadísticas automáticamente si no existe
$conexion->query("CREATE TABLE IF NOT EXISTS `estadisticas_totem` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `dni` varchar(20) DEFAULT NULL,
  `nombre` varchar(150) DEFAULT NULL,
  `servicio` varchar(150) DEFAULT NULL,
  `modo` varchar(50) DEFAULT NULL,
  `token_iofa` varchar(100) DEFAULT NULL,
  `tiempo_operacion` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conexion->query("ALTER TABLE `estadisticas_totem` MODIFY `tiempo_operacion` DECIMAL(10,2) NOT NULL DEFAULT 0.00;");
$conexion->query("ALTER TABLE `estadisticas_totem` ADD COLUMN IF NOT EXISTS `numero_orden` VARCHAR(10) NULL DEFAULT NULL;");

// Registrar el movimiento para los reportes diarios
$dni_escapado = $conexion->real_escape_string($dni);
$nombre_escapado = $conexion->real_escape_string($nombre);
$servicio_escapado = $conexion->real_escape_string($servicio);
$modo_escapado = $conexion->real_escape_string($modo);
$codigo_escapado = $conexion->real_escape_string($codigo);

$es_preview = isset($_GET['preview']) && $_GET['preview'] == '1';

// Forzamos la hora exacta desde PHP para evitar el desfasaje del servidor MySQL
$fecha_hora_exacta = date('Y-m-d H:i:s');
$id_insertado = 0;

if (!$es_preview) {
    $conexion->query("INSERT INTO estadisticas_totem (fecha_hora, dni, nombre, servicio, modo, token_iofa, tiempo_operacion) VALUES ('$fecha_hora_exacta', '$dni_escapado', '$nombre_escapado', '$servicio_escapado', '$modo_escapado', '$codigo_escapado', $tiempo)");
    $id_insertado = $conexion->insert_id;
}



if (!$es_preview) {
// Actualizar contador de papel térmico
$conexion->query("UPDATE totem_config SET estado = CAST(estado AS UNSIGNED) + 1 WHERE tipo = 'tickets_impresos'");

$res_papel = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'tickets_impresos'");
$impresos = ($res_papel && $fila_papel = $res_papel->fetch_assoc()) ? (int)$fila_papel['estado'] : 0;

$res_cap = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'capacidad_rollo'");
$capacidad = ($res_cap && $fila_cap = $res_cap->fetch_assoc()) ? (int)$fila_cap['estado'] : 120;

$restantes = $capacidad - $impresos;

$res_silencio = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'alertas_silenciadas'");
$silenciado = ($res_silencio && $fila_sil = $res_silencio->fetch_assoc()) ? (int)$fila_sil['estado'] : 0;

// Disparar alerta continua cada vez que se imprima y queden 25 tickets o menos, solo si NO está silenciado:
if($restantes <= 25 && $silenciado === 0) { 
    $url = "https://federicogonzalez.net/actis/enviar_alerta_soporte.php?alerta=papel";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_exec($ch);
    curl_close($ch);
}
} // Fin validacion preview papel

$numero_orden = null;

// Si el usuario eligió ASISTENCIA, sumamos y obtenemos su número de orden
if ($modo === 'ASISTENCIA') {
    if (!$es_preview) {
        $sql_update = "UPDATE servicios SET numero_actual = numero_actual + 1 WHERE nombre = '" . $conexion->real_escape_string($servicio) . "'";
        $conexion->query($sql_update);
    }

    
    $sql_select = "SELECT numero_actual FROM servicios WHERE nombre = '" . $conexion->real_escape_string($servicio) . "'";
    $res = $conexion->query($sql_select);
    if ($res && $fila = $res->fetch_assoc()) {
        $numero_orden = str_pad($fila['numero_actual'], 3, "0", STR_PAD_LEFT);
    } else {
        $numero_orden = "001";
    }
    if (!$es_preview && $id_insertado > 0) {
        $conexion->query("UPDATE estadisticas_totem SET numero_orden = '$numero_orden' WHERE id = $id_insertado");
    }
}

$conexion->close();


$fecha_hora = date('d/m/Y H:i:s');
$hora = (int)date('H');
if ($hora < 12) { $saludo = "¡Buen día!"; $audio = "buendia.mp3"; }
elseif ($hora < 20) { $saludo = "¡Buenas tardes!"; $audio = "buenastardes.mp3"; }
else { $saludo = "¡Buenas noches!"; $audio = "bueasnoches.mp3"; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Imprimiendo Ticket...</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body {
            background: linear-gradient(-45deg, #0f172a, #1e293b, #0284c7, #0f172a); 
            background-size: 400% 400%; animation: gradienteEspera 8s ease infinite;
            margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; 
            height: 100vh; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow: hidden;
        }
        @keyframes gradienteEspera { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .pantalla-carga { 
            text-align: center; background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            padding: 50px 70px; border-radius: 35px; box-shadow: 0 25px 50px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.2);
            color: white; animation: entrarPop 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; transform: scale(0.8); opacity: 0;
        }
        @keyframes entrarPop { to { transform: scale(1); opacity: 1; } }
        .pantalla-carga h1 { color: #ffffff; font-size: 4rem; margin-bottom: 10px; text-shadow: 0 0 20px rgba(2, 132, 199, 0.8); letter-spacing: 2px; }
        .logo-flotante { height: 130px; animation: flotarLogo 3s ease-in-out infinite alternate; filter: drop-shadow(0 0 15px rgba(255,255,255,0.4)); margin-bottom: 10px; }
        @keyframes flotarLogo { 0% { transform: translateY(0px); } 100% { transform: translateY(-15px); } }
        .spinner-moderno { width: 70px; height: 70px; border: 6px solid rgba(255,255,255,0.1); border-top: 6px solid #38bdf8; border-radius: 50%; animation: girarCarga 1s linear infinite; margin: 30px auto; }
        @keyframes girarCarga { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .ticket-impresion { position: absolute; left: -9999px; top: -9999px; visibility: hidden; width: 280px; }
        
        @media print {
            @page { margin: 0; }
            html, body { display: block !important; margin: 0 !important; padding: 0 !important; background: white !important; animation: none !important; height: auto !important; overflow: visible !important; }
            * { animation: none !important; filter: none !important; transition: none !important; }
            .pantalla-carga { display: none !important; }
            
            .ticket-impresion {
                display: block !important; 
                position: static !important;
                visibility: visible !important;
                width: 80% !important; 
                max-width: 220px !important;
                margin: 0 auto 0 22px !important;
                padding: 0 !important;
                box-sizing: border-box;
                text-align: center; 
                font-family: 'Courier New', Courier, monospace; 
                color: black;
            }
            .ticket-logo {
                font-size: 18px;
                font-weight: 900;
                margin-bottom: 5px;
                padding-bottom: 5px;
                border-bottom: 2px dashed black;
            }
            .ticket-datos {
                font-size: 16px !important;
                text-align: center !important;
                margin-bottom: 15px !important;
                font-weight: bold;
                color: black;
            }
            .ticket-datos strong { font-size: 18px !important; font-weight: 900 !important; }
            .ticket-codigo-caja {
                border: 2px solid black;
                padding: 10px 0;
                margin: 15px 0;
                border-radius: 8px;
            }
            .ticket-codigo-titulo { font-size: 14px; font-weight: 900; }
            .ticket-codigo-valor { font-size: 32px; font-weight: 900; letter-spacing: 2px; margin-top: 5px; }
            .ticket-qr { margin: 10px 0; }
            .ticket-qr img { width: 120px; height: 120px; }
            .ticket-pie {
                font-size: 12px;
                font-weight: bold;
                border-top: 2px dashed black;
                padding-top: 10px;
                margin-top: 10px;
                padding-bottom: 0px;
            }
        }
    </style>
    <?php if($es_preview): ?>
    <style>
        body { background: #5274AD !important; overflow: auto !important; height: auto !important; animation: none !important; display: block !important; }
        .pantalla-carga { display: none !important; }
        .ticket-impresion {
            display: block !important; position: relative !important; left: auto !important; top: auto !important; visibility: visible !important;
            background: white !important; padding: 15px !important; margin: 40px auto !important; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.5) !important; width: 280px !important; border-radius: 0px !important;
            font-family: 'Courier New', Courier, monospace !important; color: black !important; text-align: center !important; box-sizing: border-box !important;
        }
        .ticket-logo { font-size: 14px !important; font-weight: 900 !important; margin-bottom: 2px !important; padding-bottom: 2px !important; border-bottom: 1px dashed black !important; line-height: 1 !important; }
        .ticket-datos { font-size: 11px !important; text-align: center !important; margin-bottom: 5px !important; font-weight: 900 !important; color: #000000 !important; line-height: 1.1 !important; }
        .ticket-datos strong { font-size: 12px !important; font-weight: 900 !important; }
        .ticket-codigo-caja { border: 1px solid black !important; padding: 3px 0 !important; margin: 4px 0 !important; border-radius: 4px !important; text-align: center !important; }
        .ticket-codigo-titulo { font-size: 10px !important; font-weight: 900 !important; margin-bottom: 0 !important; }
        .ticket-codigo-valor { font-size: 20px !important; font-weight: 900 !important; letter-spacing: 0px !important; margin-top: 2px !important; }
        .ticket-qr { margin: 4px 0 !important; text-align: center !important; }
        .ticket-qr img { width: 75px !important; height: 75px !important; }
        .ticket-pie { font-size: 9px !important; font-weight: bold !important; border-top: 1px dashed black !important; padding-top: 4px !important; margin-top: 4px !important; text-align: center !important; }
    </style>
    <?php endif; ?>
</head>
<body>

    <div class="pantalla-carga" style="background: transparent; border: none; box-shadow: none; backdrop-filter: none; -webkit-backdrop-filter: none; padding: 0;">
        <div id="contenedor-confeti"></div>
        <style>
            @keyframes estallidoTicket {
                0% { transform: translate(-50%, -50%) scale(0.3); opacity: 1; }
                80% { opacity: 1; }
                100% { transform: translate(calc(-50% + var(--x)), calc(-50% + var(--y))) rotate(var(--r)) scale(1.3); opacity: 0; }
            }
            @keyframes brilloCopado {
                0%, 100% { transform: scale(1); filter: drop-shadow(0 0 10px #f6b40e); }
                50% { transform: scale(1.05); filter: drop-shadow(0 0 25px #f6b40e); }
            }
            @keyframes pulsoMundialista {
                0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(116, 172, 223, 0.8); }
                70% { transform: scale(1.05); box-shadow: 0 0 0 25px rgba(116, 172, 223, 0); }
                100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(116, 172, 223, 0); }
            }
            @keyframes latidoCampeon {
                0% { transform: scale(1); text-shadow: 0 4px 12px rgba(0,0,0,0.6); }
                50% { transform: scale(1.1); text-shadow: 0 10px 25px rgba(116,172,223,0.8); }
                100% { transform: scale(1); text-shadow: 0 4px 12px rgba(0,0,0,0.6); }
            }
        </style>
        <!--
        <script>
            let htmlConfeti = '';
            for (let i = 0; i < 25; i++) {
                let color = ['#74acdf', '#ffffff', '#f6b40e'][i % 3];
                let despX = (Math.random() * 80 - 40) + 'vw';
                let despY = (Math.random() * 80 - 50) + 'vh';
                let rot = (Math.random() * 360) + 'deg';
                let dur = (1.5 + Math.random() * 1.5) + 's';
                htmlConfeti += '<div style="position: absolute; width: 15px; height: 15px; background: ' + color + '; border-radius: ' + (i % 2 === 0 ? '0' : '50%') + '; left: 50%; top: 50%; pointer-events: none; opacity: 0; transform: translate(-50%, -50%); animation: estallidoTicket ' + dur + ' ease-out infinite; --x: ' + despX + '; --y: ' + despY + '; --r: ' + rot + ';"></div>';
            }
            document.getElementById('contenedor-confeti').innerHTML = htmlConfeti;
        </script>
        -->
        
        <img src="https://federicogonzalez.net/actis/img/osfa_blanco.png" style="height: 12vh; margin-bottom: 2vh; filter: drop-shadow(0 0 20px rgba(116,172,223,0.5));">
        <!--
        <h1 style="font-size: 6.5vh; color: #74acdf; font-weight: 900; margin: 0 0 1vh 0; letter-spacing: 1px; text-shadow: 0 4px 12px rgba(0,0,0,0.6); white-space: nowrap; animation: latidoCampeon 1.2s infinite;">⭐⭐⭐ ¡ARGENTINA CAMPEÓN! ⭐⭐⭐</h1>
        <h2 style="font-size: 4.5vh; color: #f6b40e; font-weight: 800; margin: 0 0 3vh 0; animation: brilloCopado 1.5s infinite; text-transform: uppercase; letter-spacing: 1px;">¡VAMOS POR LA CUARTA! ⚽</h2>
        
        <div style="background: linear-gradient(135deg, rgba(116, 172, 223, 0.4), rgba(15, 23, 42, 0.6)); padding: 3vh 5vw; border-radius: 20px; border: 4px solid #74acdf; max-width: 85%; margin: 0 auto; animation: pulsoMundialista 1.5s infinite; box-shadow: 0 10px 30px rgba(0,0,0,0.5); backdrop-filter: blur(10px);">
        -->
        <div style="background: rgba(15, 23, 42, 0.6); padding: 3vh 5vw; border-radius: 20px; border: 4px solid #64748b; max-width: 85%; margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.5); backdrop-filter: blur(10px);">
            <p style="font-size: 3.8vh; font-weight: 900; margin: 0; color: #ffffff; text-shadow: 2px 2px 5px rgba(0,0,0,0.8); letter-spacing: 1px;">🎫 IMPRIMIENDO TICKET... 🎫</p>
            <p style="font-size: 2.5vh; font-weight: 800; margin: 1.5vh 0 0 0; color: #f6b40e; text-transform: uppercase; text-shadow: 1px 1px 3px rgba(0,0,0,0.8);">Por favor, retírelo de la ranura inferior 👇</p>
        </div>
    </div>

    <div class="ticket-impresion">
        <div class="ticket-logo">
            POLICLÍNICA<br>GENERAL ACTIS
        </div>
        
        <div class="ticket-datos">
            <div style="text-align: center; margin-bottom: 5px; font-size: 11px;">Fecha: <?php echo $fecha_hora; ?></div>
            <table style="width: 100%; border-collapse: collapse; text-align: left; margin: 0 auto; border: 2px solid black;">
                <tr>
                    <td style="font-size: 10px; vertical-align: middle; width: 1%; white-space: nowrap; border: 1px solid black; padding: 2px 4px; font-weight: bold;">PACIENTE:</td>
                    <td style="font-size: 12px; font-weight: 900; width: 100%; border: 1px solid black; padding: 2px 4px;"><?php echo strtoupper($nombre); ?></td>
                </tr>
                <tr>
                    <td style="font-size: 10px; vertical-align: middle; white-space: nowrap; border: 1px solid black; padding: 2px 4px; font-weight: bold;">DNI:</td>
                    <td style="font-size: 13px; font-weight: 900; border: 1px solid black; padding: 2px 4px;"><?php echo $dni; ?></td>
                </tr>
                <?php if (!empty($afiliado)): ?>
                <tr>
                    <td style="font-size: 10px; vertical-align: middle; white-space: nowrap; border: 1px solid black; padding: 2px 4px; font-weight: bold;">AFILIADO:</td>
                    <td style="font-size: 13px; font-weight: 900; border: 1px solid black; padding: 2px 4px;"><?php echo $afiliado; ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($estado)): ?>
                <tr>
                    <td style="font-size: 10px; vertical-align: middle; white-space: nowrap; border: 1px solid black; padding: 2px 4px; font-weight: bold;">ESTADO:</td>
                    <td style="font-size: 13px; font-weight: 900; border: 1px solid black; padding: 2px 4px;"><?php echo $estado; ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($fuerza)): ?>
                <tr>
                    <td style="font-size: 10px; vertical-align: middle; white-space: nowrap; border: 1px solid black; padding: 2px 4px; font-weight: bold;">FUERZA:</td>
                    <td style="font-size: 13px; font-weight: 900; border: 1px solid black; padding: 2px 4px;">
                        <?php if (strpos(strtoupper($fuerza), 'SEGURIDAD') !== false): ?>
                            <span style="background-color: #000000 !important; color: #ffffff !important; padding: 0 4px; border-radius: 2px; -webkit-print-color-adjust: exact; print-color-adjust: exact;">FUERZAS DE SEGURIDAD</span>
                        <?php else: ?>
                            <?php echo $fuerza; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="ticket-codigo-caja">
            <div class="ticket-codigo-titulo" style="font-size: 11px;">DESTINO: <?php echo strtoupper($servicio); ?></div>
            
            <?php if ($numero_orden): ?>
                <div class="ticket-codigo-titulo" style="margin-top: 2px; border-top: 1px dashed black; padding-top: 2px;">NÚMERO DE ORDEN</div>
                <div class="ticket-codigo-valor" style="font-size: 26px;"><?php echo $numero_orden; ?></div>
            <?php endif; ?>

            <div class="ticket-codigo-titulo" style="margin-top: 2px; border-top: 1px dashed black; padding-top: 2px;">TOKEN OSFA</div>
            <div class="ticket-codigo-valor"><?php echo $codigo; ?></div>
        </div>

        <div class="ticket-qr" id="contenedor-qr" style="display: flex; justify-content: center;"></div>

        <div class="ticket-pie" style="font-size: 9px; font-weight: bold; border-top: 1px dashed black; padding-top: 2px; margin-top: 2px !important; padding-bottom: 0px !important; margin-bottom: 0px !important; text-align: center;">
           Conserve este ticket.
        </div>
        <?php include 'includes/footer_ticket_impreso.php'; ?>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        <?php if ($modo === 'ASISTENCIA'): ?>
            var textoQr = "https://federicogonzalez.net/actis/ticket_publico_asistencia.php?servicio=<?php echo urlencode($servicio); ?>&orden=<?php echo urlencode($numero_orden); ?>&dni=<?php echo urlencode($dni); ?>&nombre=<?php echo urlencode($nombre); ?>&token=<?php echo urlencode($codigo); ?>&fecha=<?php echo urlencode(date('d/m/Y')); ?>&hora=<?php echo urlencode(date('H:i')); ?>";
        <?php else: ?>
            var textoQr = "https://federicogonzalez.net/actis/ticket_publico_validacion.php?token=<?php echo urlencode($codigo); ?>&dni=<?php echo urlencode($dni); ?>&nombre=<?php echo urlencode($nombre); ?>&servicio=<?php echo urlencode($servicio); ?>&fecha=<?php echo urlencode(date('d/m/Y')); ?>&hora=<?php echo urlencode(date('H:i')); ?>";
        <?php endif; ?>

        try {
            new QRCode(document.getElementById("contenedor-qr"), {
                text: textoQr, 
                width: 75, 
                height: 75,
                colorDark : "#000000", 
                colorLight : "#ffffff", 
                correctLevel : QRCode.CorrectLevel.L
            });
        } catch (e) {
            console.error("Error generando QR:", e);
        }

        setTimeout(function() {
            let audioLocucion = new Audio("img/<?php echo $audio; ?>");
            audioLocucion.play().catch(e => console.log("Audio", e));
            
            let esPreviewJs = new URLSearchParams(window.location.search).get('preview') === '1';
            if (!esPreviewJs) {
                window.onafterprint = function() {
                    window.location.replace('https://validador.iosfa.gob.ar/ValidadorDni');
                };
                
                setTimeout(function() {
                    window.print();
                }, 800);
                
                setTimeout(function() {
                    window.location.replace('https://validador.iosfa.gob.ar/ValidadorDni'); 
                }, 5000);
            }
            
        }, 300);

    });
    </script>
</body>
</html>