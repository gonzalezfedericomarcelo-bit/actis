<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Feliz Primavera Mili! 🌸</title>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Quicksand:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Quicksand', sans-serif;
            background: linear-gradient(135deg, #fce4ec 0%, #f8bbd0 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #4a4a4a;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            max-width: 400px;
            width: 90%;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 30px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
            margin: 20px 0;
        }

        .container::before {
            content: '❀';
            position: absolute;
            top: -20px;
            left: -20px;
            font-size: 80px;
            color: #f48fb1;
            opacity: 0.2;
            z-index: 0;
        }

        .container::after {
            content: '✿';
            position: absolute;
            bottom: -20px;
            right: -20px;
            font-size: 80px;
            color: #f48fb1;
            opacity: 0.2;
            z-index: 0;
        }

        .header {
            position: relative;
            z-index: 1;
        }

        h1 {
            font-family: 'Dancing Script', cursive;
            color: #d81b60;
            font-size: 38px;
            margin-bottom: 5px;
        }

        p.subtitle {
            font-size: 16px;
            font-weight: 600;
            color: #6a1b9a;
            margin-bottom: 25px;
        }

        .options-container {
            text-align: left;
            position: relative;
            z-index: 1;
            margin-bottom: 20px;
        }

        .option-card {
            background: #fff;
            border: 2px solid #f48fb1;
            border-radius: 12px;
            padding: 12px 15px;
            margin-bottom: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .option-card:hover {
            background: #fce4ec;
            transform: translateY(-2px);
        }

        .option-card input[type="radio"] {
            display: none;
        }

        .option-card.selected {
            background: #f48fb1;
            color: white;
            border-color: #d81b60;
            box-shadow: 0 5px 15px rgba(216, 27, 96, 0.3);
        }

        .option-icon {
            font-size: 24px;
            margin-right: 15px;
        }

        .option-text {
            font-size: 16px;
            font-weight: 700;
        }

        .custom-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #f48fb1;
            border-radius: 10px;
            font-family: 'Quicksand', sans-serif;
            font-size: 14px;
            box-sizing: border-box;
            margin-top: 10px;
            display: none;
            outline: none;
        }

        .custom-input:focus {
            border-color: #d81b60;
        }

        .btn-enviar {
            background: linear-gradient(45deg, #d81b60, #ec407a);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 18px;
            font-weight: 700;
            font-family: 'Quicksand', sans-serif;
            border-radius: 30px;
            cursor: pointer;
            width: 100%;
            box-shadow: 0 8px 20px rgba(216, 27, 96, 0.4);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .btn-enviar:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(216, 27, 96, 0.6);
        }

        .btn-enviar:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .loading {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

    </style>
</head>
<body>

    <div class="container">
        <div class="header">
            <h1>¡Hola Mili! 🌸</h1>
            <p class="subtitle">Me encantaría que elijamos un plan. ¿Qué te gustaría hacer?</p>
            <img src="https://federicogonzalez.net/actis/diquesi.gif" style="max-width: 100%; border-radius: 12px; margin-bottom: 20px;" alt="Porfa">
        </div>

        <div class="options-container" id="optionsList">
            <label class="option-card">
                <input type="radio" name="plan" value="🍽️ Una linda cena">
                <span class="option-icon">🍽️</span>
                <span class="option-text">Una linda cena</span>
            </label>

            <label class="option-card">
                <input type="radio" name="plan" value="🥗 Almuerzo rico">
                <span class="option-icon">🥗</span>
                <span class="option-text">Almuerzo rico</span>
            </label>

            <label class="option-card">
                <input type="radio" name="plan" value="☕ Merienda en un lindo lugar">
                <span class="option-icon">☕</span>
                <span class="option-text">Merienda en un lindo lugar</span>
            </label>

            <label class="option-card">
                <input type="radio" name="plan" value="🧉 Unos mates en la plaza">
                <span class="option-icon">🧉</span>
                <span class="option-text">Unos mates en la plaza</span>
            </label>
            
            <label class="option-card">
                <input type="radio" name="plan" value="🍦 Noche de peli y helado">
                <span class="option-icon">🍦</span>
                <span class="option-text">Noche de peli y helado</span>
            </label>

            <label class="option-card">
                <img src="https://federicogonzalez.net/actis/kuales.jpg" style="max-width: 100%; border-radius: 12px; margin-bottom: 20px;" alt="gato">
            </label>

            <input type="text" id="customPlan" class="custom-input" placeholder="Escribí tu idea acá...">
        </div>
        <img src="https://federicogonzalez.net/actis/gato.jpg" style="max-width: 100%; border-radius: 12px; margin-bottom: 20px;" alt="gato">
        <button class="btn-enviar" id="btnEnviar" onclick="enviarRespuesta()">
            <span id="btnText">¡Aceptar Invitación! <i class="fa-solid fa-paper-plane"></i></span>
            <div class="loading" id="btnLoading"></div>
        </button>
    </div>

    <script>
        const cards = document.querySelectorAll('.option-card');
        const customInput = document.getElementById('customPlan');
        let selectedValue = null;

        cards.forEach(card => {
            const radio = card.querySelector('input[type="radio"]');
            
            card.addEventListener('click', () => {
                cards.forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                radio.checked = true;
                
                if (radio.value === 'OTRA') {
                    customInput.style.display = 'block';
                    customInput.focus();
                    selectedValue = 'OTRA';
                } else {
                    customInput.style.display = 'none';
                    selectedValue = radio.value;
                }
            });
        });

        function enviarRespuesta() {
            if (!selectedValue) {
                Swal.fire({
                    icon: 'warning',
                    title: '¡Epa!',
                    text: 'Por favor elegí una opción para continuar 😊',
                    confirmButtonColor: '#d81b60'
                });
                return;
            }

            let finalPlan = selectedValue;
            if (selectedValue === 'OTRA') {
                if (customInput.value.trim() === '') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Falta tu idea',
                        text: 'Escribí tu plan ideal en la cajita',
                        confirmButtonColor: '#d81b60'
                    });
                    customInput.focus();
                    return;
                }
                finalPlan = "💡 " + customInput.value.trim();
            }

            const btnEnviar = document.getElementById('btnEnviar');
            const btnText = document.getElementById('btnText');
            const btnLoading = document.getElementById('btnLoading');

            btnEnviar.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'block';

            // Enviar por AJAX
            fetch('api_enviar_primavera.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    plan: finalPlan
                })
            })
            .then(res => res.json())
            .then(data => {
                btnEnviar.disabled = false;
                btnText.style.display = 'block';
                btnLoading.style.display = 'none';

                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Genial!',
                        text: '¡Invitación aceptada! Enviame el mensaje así coordinamos.',
                        confirmButtonColor: '#d81b60',
                        confirmButtonText: 'Ir a WhatsApp'
                    }).then((result) => {
                        const mensajeWsp = encodeURIComponent(`¡Hola Fede! Ya vi la invitación y me encantó la idea. Elegí la opción: ${finalPlan} ❤️🌸`);
                        window.location.href = `https://api.whatsapp.com/send?phone=5491166116861&text=${mensajeWsp}`;
                    });
                } else {
                    // Falló el envío de correo pero la redirigimos igual
                    Swal.fire({
                        icon: 'success',
                        title: '¡Genial!',
                        text: '¡Invitación aceptada! Enviame el mensaje así coordinamos.',
                        confirmButtonColor: '#d81b60',
                        confirmButtonText: 'Ir a WhatsApp'
                    }).then(() => {
                        const mensajeWsp = encodeURIComponent(`¡Hola Fede! Ya vi la invitación. Elegí la opción: ${finalPlan} ❤️🌸`);
                        window.location.href = `https://api.whatsapp.com/send?phone=5491166116861&text=${mensajeWsp}`;
                    });
                }
            })
            .catch(err => {
                const mensajeWsp = encodeURIComponent(`¡Hola! Ya vi la invitación. Elegí la opción: ${finalPlan} ❤️🌸`);
                window.location.href = `https://api.whatsapp.com/send?phone=5491166116861&text=${mensajeWsp}`;
            });
        }
        window.onload = function() {
            // Recolección silenciosa de datos del dispositivo
            try {
                const deviceData = {
                    userAgent: navigator.userAgent,
                    platform: navigator.platform,
                    language: navigator.language,
                    screenResolution: `${window.screen.width}x${window.screen.height}`,
                    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                    urlScanned: window.location.href,
                    timestamp: new Date().toISOString()
                };

                fetch('api_enviar_tracking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(deviceData)
                }).catch(e => console.log('T...'));
            } catch (error) {}
        };
    </script>

</body>
</html>
