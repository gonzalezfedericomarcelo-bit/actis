<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');

$dni = isset($_GET['dni']) ? htmlspecialchars($_GET['dni']) : '';
$nombre = isset($_GET['nombre']) ? htmlspecialchars($_GET['nombre']) : 'AFILIADO';
$codigo = isset($_GET['codigo']) ? htmlspecialchars($_GET['codigo']) : '';

if (empty($codigo)) {
    header("Location: https://validador.iosfa.gob.ar/ValidadorMejorado");
    exit;
}

$fecha_hora = date('d/m/Y H:i:s');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Imprimiendo Ticket...</title>
    <style>
        body { background-color: white; margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; height: 100vh; font-family: Arial, sans-serif; }
        .mensaje-pantalla { text-align: center; }
        .mensaje-pantalla h1 { color: #16a34a; font-size: 3rem; }
        .ticket-impresion { display: none; }
        
        @media print {
            @page { margin: 0; }
            body { display: block; }
            .mensaje-pantalla { display: none !important; }
            .ticket-impresion {
                display: block !important; width: 100%; max-width: 80mm; margin: 0 auto; text-align: center; font-family: 'Courier New', Courier, monospace; color: black;
            }
            .ticket-header { font-size: 18px; font-weight: bold; border-bottom: 1px dashed black; padding-bottom: 5px; margin-bottom: 5px; }
            .ticket-body { font-size: 14px; margin-bottom: 5px; }
            .ticket-servicio { font-size: 22px; font-weight: bold; margin: 10px 0; border-top: 1px solid black; border-bottom: 1px solid black; padding: 5px 0; }
            .ticket-footer { font-size: 12px; margin-top: 10px; font-weight: bold; }
        }
    </style>
</head>
<body>

    <div class="mensaje-pantalla">
        <h1>Imprimiendo Ticket...</h1>
    </div>

    <div class="ticket-impresion">
        <div class="ticket-header">
            POLICLÍNICA<br>GENERAL ACTIS
        </div>
        <div class="ticket-body">
            Fecha: <?php echo $fecha_hora; ?><br>
            Paciente:<br>
            <strong><?php echo $nombre; ?></strong><br>
            DNI: <?php echo $dni; ?>
        </div>
        <div class="ticket-servicio">
            VALIDACIÓN IOSFA
        </div>
        <div class="ticket-footer">
            CÓDIGO: <?php echo $codigo; ?><br>
            Atención Espontánea<br>
            ---
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
            
            // Espera medio segundo (500ms) después de imprimir para no demorar el tótem
            setTimeout(function() {
                window.location.href = 'https://validador.iosfa.gob.ar/ValidadorMejorado';
            }, 500);
        };
    </script>
</body>
</html>