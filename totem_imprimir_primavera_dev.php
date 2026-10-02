<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
$fecha_hora = date('d/m/Y H:i:s');
$ticket_id = "VIP-" . mt_rand(1000, 9999);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>💌 Invitación Especial...</title>
    <!-- Mantenemos las fuentes que vos habías puesto originalmente -->
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@700&family=Dancing+Script:wght@700&family=Quicksand:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* ================= RESET Y PANTALLA ================= */
        body { 
            background-color: #fdf2f8; 
            margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; 
            min-height: 100vh; font-family: 'Quicksand', sans-serif; 
        }
        
        .mensaje-pantalla { text-align: center; }
        .mensaje-pantalla h1 { color: #db2777; font-size: 3rem; font-family: 'Dancing Script', cursive; }
        
        /* ================= TICKET TÉRMICO ESTRICTO (80MM) ================= */
        .ticket-impresion {
            display: block !important; 
            width: 72mm; /* Ancho estricto del área imprimible de ticketeadoras de 80mm */
            margin: 0 auto; 
            text-align: center; 
            font-family: 'Quicksand', sans-serif; 
            color: black;
            background: white;
            border: 2px solid black; 
            padding: 5px 10px; 
            box-sizing: border-box;
            position: relative;
            overflow: hidden; 
        }
        
        /* Marca de agua de ROSAS GIGANTES en las esquinas */
        .ticket-impresion::before {
            content: '';
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('https://federicogonzalez.net/actis/rosas.png'), url('https://federicogonzalez.net/actis/rosas.png');
            background-repeat: no-repeat, no-repeat;
            background-position: top right, bottom left;
            background-size: 160px, 160px; /* Tamaño gigante para que ocupe gran parte */
            opacity: 0.20; /* Mucho más transparente */
            filter: grayscale(100%); /* Escala de grises para la ticketera */
            z-index: 0; pointer-events: none;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* TAMAÑOS COMPACTOS PARA QUE ENTRE EN UN SOLO TICKET */
        .banner-primavera {
            background-color: black;
            color: white;
            border: 2px solid black;
            font-weight: 900;
            padding: 8px 0;
            letter-spacing: 1px;
            border-radius: 5px;
            font-size: 15px;
            margin-bottom: 10px;
            text-transform: uppercase;
            position: relative; z-index: 1;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .vip-badge {
            display: inline-block;
            border: 1px solid black;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 9px; /* Reducido */
            font-weight: bold;
            margin-bottom: 10px;
            position: relative; z-index: 1;
        }

        .ticket-header { 
            font-family: 'Dancing Script', cursive; font-size: 32px; 
            font-weight: 900; margin-bottom: 5px; line-height: 1.1; position: relative; z-index: 1; 
        }
        
        .sub-header { 
            font-size: 12px; letter-spacing: 1px; border-bottom: 2px dotted black; 
            padding-bottom: 5px; margin-bottom: 10px; text-transform: uppercase; position: relative; z-index: 1; 
            font-weight: 800;
        }
        
        .atrevimiento-text {
            font-family: 'Dancing Script', cursive;
            font-size: 15px;
            margin: 15px auto 5px auto;
            padding: 8px;
            line-height: 1.1;
            position: relative; 
            z-index: 1;
            border: 1px dashed black;
            border-radius: 8px;
            width: 90%;
            background-color: white;
        }
        
        .divisor-floral { font-size: 14px; margin: 5px 0; position: relative; z-index: 1; }

        .ticket-body { font-size: 14px; margin-bottom: 10px; font-weight: 800; line-height: 1.3; position: relative; z-index: 1; }
        
        .ticket-servicio { 
            font-size: 17px; 
            font-weight: 900; margin: 5px 0; padding: 10px 0; 
            line-height: 1.2; border-top: 2px dashed black; border-bottom: 2px dashed black; position: relative; z-index: 1;
        }
        
        .ticket-footer { font-size: 12px; margin-top: 10px; font-weight: 800; position: relative; z-index: 1; }
        
        .qr-container { 
            margin: 10px auto 25px auto; padding: 5px; border: 2px solid black; 
            display: block; width: 40mm; border-radius: 5px; position: relative;
        }
        .qr-container::after {
            content: 'Escanéame o Escribime';
            position: absolute; bottom: -12px; left: 50%; transform: translateX(-50%);
            background: white; padding: 0 8px; font-size: 11px; font-weight: 900; width: max-content;
        }
        .qr-container img { width: 100%; height: auto; display: block; } /* Tamaño exacto para ticketeadora */
        
        .telefono-contacto {
            font-size: 18px; font-weight: 900; margin: -10px auto 10px auto;
            position: relative; z-index: 1; border: 2px solid black; display: block;
            width: max-content;
            padding: 5px 15px; border-radius: 5px; background: white;
        }
        
        .rsvp-text { font-weight: 900; font-size: 16px; margin-top: 10px; letter-spacing: 1px; text-transform: uppercase; display: none; }
        .atrevimiento-text { font-family: 'Caveat', cursive; font-size: 24px; font-weight: 700; line-height: 1.2; margin: 15px 0; border-top: 2px dashed black; padding-top: 10px; position: relative; z-index: 1; }
        
        .fecha-chica { font-size: 10px; font-weight: 700; color: #333; margin-top: 10px; border-top: 2px solid #ccc; padding-top: 5px; position: relative; z-index: 1; }

        /* ================= REGLAS ESTRICTAS PARA IMPRESIÓN ================= */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            @page { margin: 0; }
            body { display: block; background: white !important; margin: 0; padding: 0; }
            .mensaje-pantalla { display: none !important; }
            
            .ticket-impresion {
                display: block !important; 
                width: 72mm !important; max-width: 72mm !important; 
                margin: 0 !important; padding: 5px 10px !important; 
                border: none !important; 
                box-shadow: none !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }
            .banner-primavera { 
                background-color: black !important; 
                color: white !important; 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact; 
            }
        }
    </style>
</head>
<body>

    <div class="mensaje-pantalla">
        <h1>🌸 Generando Pase VIP... 🌸</h1>
    </div>

    <div class="ticket-impresion">
        <!-- Detalle 1: Banner de Primavera -->
        <div class="banner-primavera">¡Feliz Primavera! 🌸</div>
        
        <!-- Detalle 2: Etiqueta VIP -->
        <div class="vip-badge">TICKET CLASIFICADO - ACCESO ÚNICO</div>

        <div class="ticket-header">
            Invitación<br>Especial
        </div>
        
        <div class="sub-header" style="border: none;">
            <span style="font-size: 14px; text-transform: uppercase; letter-spacing: 2px;">Para</span><br>
            <span style="font-family: 'Dancing Script', cursive; font-size: 45px; text-transform: none; font-weight: 900; line-height: 1; display: inline-block; margin-top: 5px; border-bottom: 3px solid black; padding-bottom: 5px; width: 100%;">Mili</span>
        </div>
        
        <div class="ticket-body">
            Pasaba a dejarte este mensajito...<br>
            <span style="display: block; font-family: 'Caveat', cursive; font-size: 24px; font-weight: 700; margin-top: 10px; line-height: 1.1;">
                Verte en el puesto los días que nos cruzamos me cambia la mañana, tu sonrisa me puede. 😊
            </span>
        </div>

        <!-- Detalle 3: Divisor decorativo -->
        <div class="divisor-floral">✧ ❀ ✧ ❀ ✧ ❀ ✧</div>

        <div class="ticket-servicio">
            ME ENCANTARÍA INVITARTE<br>A HACER ALGO JUNTOS 😎
            <div style="font-family: 'Caveat', cursive; font-size: 22px; font-weight: 700; text-transform: none; margin-top: 15px; line-height: 1.1; border-top: 2px dashed black; padding-top: 15px;">
                Sé que no nos conocemos bien, pero muero de ganas por conocerte. Te prometo que te vas a divertir, solo que a veces me hago el serio en el trabajo pero después soy un hombre simple y espontáneo. ✨
            </div>
        </div>

        <div class="divisor-floral">✧ ❀ ✧ ❀ ✧ ❀ ✧</div>

        <div class="ticket-footer">
            <div class="qr-container" style="margin-top: 15px; text-align: center;">
                <p style="font-weight: 800; font-size: 14px; margin-bottom: -5px; color: black; letter-spacing: 1px;">Escanéame para elegir el plan ideal ❤️</p>
                <div style="padding: 10px; background: white; display: inline-block; border-radius: 10px; border: 2px dashed black; margin-top: 10px;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=https://federicogonzalez.net/actis/primavera.php" alt="QR Code" style="width: 100%; max-width: 250px; margin: 0 auto; display: block;">
                </div>
                <p style="font-weight: 900; font-size: 18px; margin-top: 10px; color: black; letter-spacing: 2px;">11 6611-6861</p>
            </div>
            
            <div class="atrevimiento-text">
                "Me parecés súper interesante y no quería quedarme con las ganas de invitarte a salir. ¡Fijate en el QR qué onda!" ✨
            </div>
            
            <!-- IMAGEN DEL PERRITO AL FINAL DEL TICKET -->
            <div style="margin-top: 15px; text-align: center;">
                <img src="https://federicogonzalez.net/actis/perro.png" style="width: 100%; max-width: 150px; margin: 0 auto; display: block; filter: grayscale(100%) contrast(1.2);">
                <p style="font-family: 'Dancing Script', cursive; font-size: 22px; margin-top: 5px; font-weight: 700;">decime que si porfa jaja</p>
            </div>
            
            <div class="fecha-chica">Generado el: <?php echo $fecha_hora; ?></div>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
            
            if (window.opener || window.location.href.includes('preview=')) {
                return;
            }

            setTimeout(function() {
                window.location.href = 'https://validador.iosfa.gob.ar/ValidadorDni';
            }, 500);
        };
    </script>
</body>
</html>