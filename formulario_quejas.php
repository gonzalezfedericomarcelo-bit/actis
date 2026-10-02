<?php
require_once 'includes/conexion.php';

$dni = htmlspecialchars($_GET['dni'] ?? '');
$nombre = htmlspecialchars($_GET['nombre'] ?? '');
$codigo = htmlspecialchars($_GET['codigo'] ?? '');
$estado = htmlspecialchars($_GET['estado'] ?? '');

$telefono_bd = '';
$email_bd = '';

// Buscar datos adicionales en la base de datos de Actis
if (!empty($dni)) {
    $q_paciente = $conexion->query("SELECT telefono, email FROM pacientes WHERE dni = '$dni' LIMIT 1");
    if ($q_paciente && $q_paciente->num_rows > 0) {
        $row = $q_paciente->fetch_assoc();
        $telefono_bd = htmlspecialchars($row['telefono'] ?? '');
        $email_bd = htmlspecialchars($row['email'] ?? '');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sugerencias y Quejas - ACTIS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #0369a1; /* Azul corporativo iosfa */
            --primary-light: #0ea5e9;
            --bg-color: #f0fdf4; /* Un toque muy leve de verde mar para higiene/salud o azulado #f0f9ff */
            --bg-color: #f1f5f9;
            --text-main: #0f172a;
            --text-muted: #475569;
            --card-bg: #ffffff;
        }
        body {
            margin: 0; padding: 0; font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #e0f2fe 0%, #f1f5f9 100%);
            color: var(--text-main);
            min-height: 100vh;
        }
        
        /* HEADER PREMIUM */
        .header {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: white; 
            padding: 30px 20px 40px 20px; 
            text-align: center;
            box-shadow: 0 10px 25px rgba(2, 132, 199, 0.2); 
            border-bottom-left-radius: 40px; 
            border-bottom-right-radius: 40px;
            position: relative;
            overflow: hidden;
        }
        .header::before {
            content: '';
            position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            pointer-events: none;
        }
        .header-logo {
            width: 140px;
            margin-bottom: 15px;
            filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.2));
            background: white;
            border-radius: 12px;
            padding: 5px 15px;
        }
        .header h1 { margin: 0; font-size: 26px; font-weight: 800; letter-spacing: -0.5px; }
        .header p { margin: 8px 0 0 0; font-size: 15px; opacity: 0.9; font-weight: 500; }
        
        .container { 
            padding: 20px; max-width: 650px; margin: -25px auto 30px auto; 
            position: relative; z-index: 10;
        }
        
        /* CARD PREMIUM */
        .card {
            background: var(--card-bg); 
            border-radius: 20px; 
            padding: 25px; 
            margin-bottom: 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08); 
            border: 1px solid rgba(255,255,255,0.8);
            position: relative;
        }
        
        .card-title {
            font-size: 18px; font-weight: 800; color: var(--primary); 
            margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px; 
            border-bottom: 2px solid #e2e8f0; 
            padding-bottom: 12px;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        
        .form-group { margin-bottom: 18px; }
        .form-group label { 
            display: block; font-size: 13px; font-weight: 700; 
            color: var(--text-muted); margin-bottom: 8px; 
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        
        .form-control {
            width: 100%; padding: 14px 16px; 
            border: 2px solid #e2e8f0; border-radius: 12px;
            font-size: 15px; font-family: 'Inter', sans-serif; 
            box-sizing: border-box; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: #f8fafc; color: var(--text-main); font-weight: 500;
        }
        .form-control:focus { 
            border-color: var(--primary-light); 
            background: #ffffff;
            outline: none; 
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.15); 
        }
        .form-control[readonly] { 
            background: #f1f5f9; color: #64748b; 
            cursor: not-allowed; border-color: #cbd5e1; 
            opacity: 0.8;
        }
        
        textarea.form-control { min-height: 140px; resize: vertical; line-height: 1.5; }
        
        .row-inputs { display: flex; gap: 15px; }
        .row-inputs .form-group { flex: 1; }
        
        /* BOTON SUBMIT PREMIUM */
        .btn-submit {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); 
            color: white; border: none; width: 100%; padding: 18px;
            border-radius: 16px; font-size: 18px; font-weight: 800; 
            font-family: 'Inter', sans-serif; text-transform: uppercase; letter-spacing: 1px;
            cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 12px;
            box-shadow: 0 8px 20px rgba(2, 132, 199, 0.3); 
            transition: all 0.2s ease;
        }
        .btn-submit:active { transform: translateY(2px); box-shadow: 0 4px 10px rgba(2, 132, 199, 0.3); }
        .btn-submit:hover { background: linear-gradient(135deg, #0369a1 0%, #075985 100%); }
        
        .badge {
            display: inline-block; padding: 6px 12px; border-radius: 20px; 
            font-size: 13px; font-weight: 800; letter-spacing: 0.5px;
        }
        .badge.activo { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .badge.inactivo { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        @media (max-width: 480px) {
            .row-inputs { flex-direction: column; gap: 0; }
        }
    </style>
</head>
<body>

    <div class="header">
        <img src="img/osfa.png" alt="OSFA" class="header-logo" onerror="this.style.display='none'">
        <h1><i class="fa-solid fa-comment-dots"></i> Buzón de Sugerencias</h1>
        <p>Policlínica General Actis</p>
    </div>

    <div class="container">
        <!-- BOTÓN VOLVER AL INICIO (Específico para Totem) -->
        <button type="button" onclick="window.location.href='dashboard_totem.php'" style="background:#ef4444; color:white; border:none; padding:12px 20px; border-radius:12px; font-size:16px; font-weight:800; cursor:pointer; box-shadow:0 4px 15px rgba(239,68,68,0.3); text-transform:uppercase; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; transition: all 0.2s ease;">
            <i class="fa-solid fa-arrow-left"></i> VOLVER AL INICIO
        </button>

        <form id="formQuejas" onsubmit="enviarFormulario(event)">
            
            <!-- DATOS DEL AFILIADO (Solo Lectura) -->
            <div class="card">
                <div class="card-title"><i class="fa-solid fa-id-card"></i> Datos del Afiliado</div>
                
                <div class="form-group">
                    <label>Nombre Completo</label>
                    <input type="text" class="form-control" name="nombre" value="<?php echo $nombre; ?>" readonly>
                </div>
                
                <div class="row-inputs">
                    <div class="form-group">
                        <label>DNI</label>
                        <input type="text" class="form-control" name="dni" value="<?php echo $dni; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Nº Afiliado</label>
                        <input type="text" class="form-control" name="codigo" value="<?php echo $codigo; ?>" readonly>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom:0;">
                    <label>Estado en IOSFA</label>
                    <div>
                        <?php if($estado === 'AFILIADO ACTIVO'): ?>
                            <span class="badge activo"><i class="fa-solid fa-check"></i> ACTIVO</span>
                        <?php else: ?>
                            <span class="badge inactivo"><i class="fa-solid fa-xmark"></i> <?php echo $estado ?: 'NO REGISTRADO'; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- DATOS DE CONTACTO (Editables) -->
            <div class="card">
                <div class="card-title"><i class="fa-solid fa-address-book"></i> Datos de Contacto</div>
                
                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <input type="email" class="form-control" name="email" value="<?php echo $email_bd; ?>" placeholder="ejemplo@correo.com">
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label>Teléfono / Celular</label>
                    <input type="tel" class="form-control" name="telefono" value="<?php echo $telefono_bd; ?>" placeholder="Ej: 1123456789">
                </div>
            </div>

            <!-- MENSAJE -->
            <div class="card">
                <div class="card-title"><i class="fa-solid fa-pen-to-square"></i> Su Mensaje</div>
                
                <div class="form-group">
                    <label>Tipo de Mensaje <span style="color:#ef4444">*</span></label>
                    <select class="form-control" name="tipo" required>
                        <option value="" disabled selected>Seleccione una opción...</option>
                        <option value="Sugerencia">Sugerencia</option>
                        <option value="Queja">Queja / Reclamo</option>
                        <option value="Felicitación">Felicitación</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom:0;">
                    <label>Detalle de su mensaje <span style="color:#ef4444">*</span></label>
                    <textarea class="form-control" name="mensaje" placeholder="Escriba aquí sus comentarios..." required></textarea>
                </div>
            </div>
            
            <button type="submit" class="btn-submit"><i class="fa-solid fa-paper-plane"></i> Enviar Mensaje</button>
            
        </form>
    </div>

    <script>
        function enviarFormulario(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Enviando...',
                text: 'Procesando su mensaje',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            
            // Simular envío (Aquí se conectaría con la inserción final)
            setTimeout(() => {
                Swal.fire({
                    icon: 'success',
                    title: '¡Mensaje Enviado!',
                    text: 'Gracias por ayudarnos a mejorar. Su comentario ha sido registrado con éxito.',
                    confirmButtonColor: '#0369a1',
                    confirmButtonText: 'Finalizar',
                    customClass: {
                        popup: 'border-radius-20'
                    }
                }).then(() => {
                    window.location.href = 'dashboard_totem.php';
                });
            }, 1500);
        }
    </script>
</body>
</html>
