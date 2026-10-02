<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once 'includes/conexion.php';

$dni = isset($_GET['dni']) ? htmlspecialchars($_GET['dni']) : '';
$nombre = isset($_GET['nombre']) ? htmlspecialchars($_GET['nombre']) : 'PACIENTE';
$afiliado = isset($_GET['afiliado']) ? htmlspecialchars($_GET['afiliado']) : '';
$servicio = isset($_GET['servicio']) ? htmlspecialchars($_GET['servicio']) : 'VALIDACIÓN';
$codigo = isset($_GET['token']) ? htmlspecialchars($_GET['token']) : '';
$tiempo = isset($_GET['tiempo']) ? (float)$_GET['tiempo'] : 0;
$fuerza = isset($_GET['fuerza']) ? htmlspecialchars($_GET['fuerza']) : '';
$estado = isset($_GET['estado']) ? htmlspecialchars($_GET['estado']) : '';

// FORZAR "NO ACTIVO" SI ES DE SEGURIDAD
if (strpos(strtoupper($fuerza), 'SEGURIDAD') !== false) {
    $estado = 'NO ACTIVO';
}

$fecha_hora = date('d/m/Y H:i:s');
$fecha_hora_db = date('Y-m-d H:i:s');
$es_preview = isset($_GET['preview']) && $_GET['preview'] == '1';

if (!$es_preview) {
    $res_papel = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'tickets_impresos_ventanilla'");
    $impresos = ($res_papel && $fila_papel = $res_papel->fetch_assoc()) ? (int)$fila_papel['estado'] : 0;
    
    $res_cap = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'capacidad_rollo_ventanilla'");
    $capacidad = ($res_cap && $fila_cap = $res_cap->fetch_assoc()) ? (int)$fila_cap['estado'] : 120;

    $res_bloqueo = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'bloqueo_min_papel_ventanilla'");
    $bloqueo_ven = ($res_bloqueo && $fila_bloqueo = $res_bloqueo->fetch_assoc()) ? (int)$fila_bloqueo['estado'] : 5;

    $papel_restante = $capacidad - $impresos;

    if ($papel_restante <= $bloqueo_ven) {
        die("<div style='font-family:sans-serif; text-align:center; padding: 50px;'><h1 style='color:red;'>ERROR: IMPRESORA DE VENTANILLA SIN PAPEL</h1><p style='font-size: 24px;'>Por favor, coloque un rollo nuevo y resetee el contador en el administrador antes de continuar imprimiendo.</p><button onclick='window.close()' style='padding: 10px 20px; font-size: 18px; cursor: pointer;'>Cerrar</button></div>");
    }

    $conexion->query("INSERT INTO estadisticas_totem (fecha_hora, dni, nombre, servicio, modo, token_iofa, origen, tiempo_operacion) VALUES ('$fecha_hora_db', '$dni', '$nombre', '$servicio', 'VALIDACION', '$codigo', 'VENTANILLA', $tiempo)");

    $conexion->query("UPDATE totem_config SET estado = CAST(estado AS UNSIGNED) + 1 WHERE tipo = 'tickets_impresos_ventanilla'");
    $impresos++; // Actualizamos para el cálculo del porcentaje

    $res_min = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'alerta_min_papel_ventanilla'");
    $min_alerta = ($res_min && $fila_min = $res_min->fetch_assoc()) ? (int)$fila_min['estado'] : 20;

    $res_silencio_ven = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'alertas_silenciadas_ventanilla'");
    $silenciado_ven = ($res_silencio_ven && $fila_sil_ven = $res_silencio_ven->fetch_assoc()) ? (int)$fila_sil_ven['estado'] : 0;

    $porcentaje_restante = ($capacidad > 0) ? round((($capacidad - $impresos) / $capacidad) * 100) : 0;

    if($porcentaje_restante <= $min_alerta && $silenciado_ven === 0) { 
        $url = "https://federicogonzalez.net/actis/enviar_alerta_soporte.php?alerta=papel_ventanilla";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_exec($ch);
        curl_close($ch);
    }
}
$hora = (int)date('H');
if ($hora < 12) { $saludo = "¡Buen día!"; }
elseif ($hora < 20) { $saludo = "¡Buenas tardes!"; }
else { $saludo = "¡Buenas noches!"; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Imprimir Ticket Validación</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { background: #e2e8f0; margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .ticket-impresion {
            background: white; padding: 15px; margin: 20px auto; box-shadow: 0 10px 25px rgba(0,0,0,0.2); width: 280px;
            text-align: center; font-family: 'Courier New', Courier, monospace; color: black; box-sizing: border-box;
        }
        .ticket-logo { font-size: 22px; font-weight: 900; margin-bottom: 5px; padding-bottom: 5px; border-bottom: 2px dashed black; }
        .ticket-datos { font-size: 16px !important; text-align: left; margin-bottom: 15px; font-weight: 900 !important; color: #000000 !important; }
        .ticket-datos strong { font-size: 18px !important; font-weight: 900 !important; }
        .ticket-codigo-caja { border: 2px solid black; padding: 10px 0; margin: 15px 0; border-radius: 8px; }
        .ticket-codigo-titulo { font-size: 14px; font-weight: 900; }
        .ticket-codigo-valor { font-size: 32px; font-weight: 900; letter-spacing: 2px; margin-top: 5px; }
        .ticket-qr { margin: 10px 0; }
        .ticket-qr img { width: 120px; height: 120px; }
        .ticket-pie { font-size: 12px; font-weight: bold; border-top: 2px dashed black; padding-top: 10px; margin-top: 10px; padding-bottom: -15mm; }
        @media print {
            @page { margin: 0; }
            body { display: block !important; background: white !important; height: auto !important; overflow: visible !important; }
            .ticket-impresion {
                display: block !important; position: static !important; width: 80%; max-width: 220px; margin: 0 auto; box-shadow: none; padding: -10px;
            }
        }
    </style>
</head>
<body>
    <div class="ticket-impresion">
        <div class="ticket-logo">
            POLICLÍNICA<br>GENERAL ACTIS
        </div>
        
        <div class="ticket-datos">
            Fecha: <?php echo $fecha_hora; ?><br><br>
            Paciente:<br>
            <strong><?php echo strtoupper($nombre); ?></strong><br>
            DNI: <strong><?php echo $dni; ?></strong><br>
            <?php if (!empty($afiliado)): ?>
            Afiliado: <strong><?php echo $afiliado; ?></strong><br>
            <?php endif; ?>
            Estado: <strong><?php echo $estado; ?></strong><br>
            <?php if (strpos(strtoupper($fuerza), 'SEGURIDAD') !== false): ?>
                <div style="background-color: #000000 !important; color: #ffffff !important; padding: 8px; text-align: center; margin-top: 10px; font-weight: 900; border-radius: 5px; font-size: 18px; -webkit-print-color-adjust: exact; print-color-adjust: exact;">FUERZAS DE SEGURIDAD</div>
            <?php else: ?>
                Fuerza: <strong><?php echo $fuerza; ?></strong>
            <?php endif; ?>
        </div>

        <div class="ticket-codigo-caja">
            <div class="ticket-codigo-titulo" style="font-size: 16px;">DESTINO: <?php echo strtoupper($servicio); ?></div>
            
            <div class="ticket-codigo-titulo" style="margin-top: 8px; border-top: 1px dashed black; padding-top: 8px;">TOKEN OSFA</div>
            <div class="ticket-codigo-valor"><?php echo $codigo; ?></div>
        </div>

        <div class="ticket-qr" id="contenedor-qr" style="display: flex; justify-content: center; margin-top: 10px;"></div>

        <div class="ticket-pie" style="font-size: 12px; font-weight: bold; border-top: 2px dashed black; padding-top: 8px; margin-top: 5px !important; padding-bottom: 0px !important; margin-bottom: 0px !important; text-align: center;">
           Conserve este ticket.
        </div>
        <?php include 'includes/footer_ticket_impreso.php'; ?>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var textoQr = "https://federicogonzalez.net/actis/ticket_publico_validacion.php?token=<?php echo urlencode($codigo); ?>&dni=<?php echo urlencode($dni); ?>&nombre=<?php echo urlencode($nombre); ?>&servicio=<?php echo urlencode($servicio); ?>&fecha=<?php echo urlencode(date('d/m/Y')); ?>&hora=<?php echo urlencode(date('H:i')); ?>";

            new QRCode(document.getElementById("contenedor-qr"), {
                text: textoQr, 
                width: 120, 
                height: 120,
                colorDark : "#000000", 
                colorLight : "#ffffff", 
                correctLevel : QRCode.CorrectLevel.L
            });

            // Forzamos impresión inmediata (sin setTimeout para evitar bloqueos del navegador)
            window.print();
            
            // Cerramos la ventana una vez finalizada la impresión (en Electron el fallback es el principal porque onafterprint es nulo)
            window.onafterprint = function() {
                window.close();
            };
            
            // Cierre súper rápido (como en el Tótem) para que vuelva a Ventanilla al instante
            setTimeout(function(){ window.close(); }, 500);
        });
    </script>
</body>
</html>