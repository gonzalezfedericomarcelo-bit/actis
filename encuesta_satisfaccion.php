<?php
require_once 'includes/conexion.php';
$turno_id = isset($_GET['id']) ? $conexion->real_escape_string($_GET['id']) : 0;
$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['turno_id'])) {
    $tid = $conexion->real_escape_string($_POST['turno_id']);
    $estrellas = intval($_POST['estrellas']);
    $comentario = $conexion->real_escape_string($_POST['comentario']);
    
    $conexion->query("UPDATE turnos SET calificacion = $estrellas, comentario_paciente = '$comentario' WHERE id = $tid");
    $mensaje = 'gracias';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Encuesta de Satisfacción - ACTIS</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { 
            display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; 
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%); /* Fondo suave y limpio para el celular */
        }
        body::before { display: none; } /* Quitamos el background global de la app administrativa */
        
        .tarjeta-encuesta { 
            width: 100%; max-width: 500px; text-align: center; background: #ffffff; 
            border-radius: 24px; padding: 40px 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.08); 
            border: none;
        }
        
        .logo-medico { width: 60px; height: 60px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 20px; }

        .estrellas { display: flex; flex-direction: row-reverse; justify-content: center; gap: 8px; margin: 30px 0; }
        .estrellas input { display: none; }
        .estrellas label { font-size: 3.5rem; color: #e2e8f0; cursor: pointer; transition: transform 0.2s, color 0.2s; }
        .estrellas input:checked ~ label, .estrellas label:hover, .estrellas label:hover ~ label { color: #f59e0b; }
        .estrellas label:active { transform: scale(0.85); }
        
        .textarea-comentario {
            width: 100%; min-height: 140px; padding: 20px; border-radius: 16px; 
            border: 2px solid #e2e8f0; background: #f8fafc; font-family: inherit; 
            font-size: 1.05rem; font-weight: 500; color: #1e293b; margin-bottom: 25px;
            transition: all 0.3s; resize: none;
        }
        .textarea-comentario:focus { outline: none; border-color: #2563eb; background: #ffffff; box-shadow: 0 0 0 4px rgba(37,99,235,0.1); }
        .textarea-comentario::placeholder { color: #94a3b8; font-weight: 400; }
    </style>
</head>
<body>
    <div class="tarjeta-encuesta">
        <?php if($mensaje == 'gracias'): ?>
            <div style="animation: bounce 0.5s ease-out;">
                <i class="fa-solid fa-face-smile-beam" style="font-size: 7rem; color: #10b981; margin-bottom: 20px; drop-shadow: 0 10px 15px rgba(16,185,129,0.3);"></i>
            </div>
            <h2 style="font-size: 2.2rem; font-weight: 900; color: #0f172a; letter-spacing: -0.5px;">¡Gracias!</h2>
            <p style="font-size: 1.15rem; color: #475569; margin-top: 15px; font-weight: 500; line-height: 1.6;">Tu opinión ha sido enviada exitosamente. Nos ayuda enormemente a mejorar.</p>
            <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #f1f5f9; color: #94a3b8; font-weight: 700; font-size: 0.9rem;">
                Policlínica General ACTIS
            </div>
        <?php else: ?>
            <div class="logo-medico"><i class="fa-solid fa-heart-pulse"></i></div>
            <h2 style="font-size: 2rem; font-weight: 900; margin-bottom: 10px; color: #0f172a; letter-spacing: -0.5px;">¿Cómo te fue?</h2>
            <p style="color: #64748b; margin-bottom: 10px; font-size: 1.1rem; font-weight: 500; padding: 0 10px;">Califica tu experiencia de atención en la clínica el día de hoy.</p>
            
            <form method="POST">
                <input type="hidden" name="turno_id" value="<?php echo $turno_id; ?>">
                
                <div class="estrellas">
                    <input type="radio" name="estrellas" value="5" id="s5" required><label for="s5"><i class="fa-solid fa-star"></i></label>
                    <input type="radio" name="estrellas" value="4" id="s4"><label for="s4"><i class="fa-solid fa-star"></i></label>
                    <input type="radio" name="estrellas" value="3" id="s3"><label for="s3"><i class="fa-solid fa-star"></i></label>
                    <input type="radio" name="estrellas" value="2" id="s2"><label for="s2"><i class="fa-solid fa-star"></i></label>
                    <input type="radio" name="estrellas" value="1" id="s1"><label for="s1"><i class="fa-solid fa-star"></i></label>
                </div>
                
                <textarea name="comentario" class="textarea-comentario" placeholder="¿Algo más que nos quieras contar? (Comentario opcional)..."></textarea>
                
                <button type="submit" class="btn btn-primario" style="width: 100%; font-size: 1.3rem; padding: 20px; border-radius: 16px; font-weight: 800; box-shadow: 0 10px 25px rgba(37,99,235,0.3);">
                    <i class="fa-solid fa-paper-plane" style="margin-right: 8px;"></i> Enviar Respuesta
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>