<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal IOSFA - ACTIS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; padding: 0; background: #f8fafc; font-family: 'Poppins', sans-serif; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        .header-elegante { background: #2563eb; color: white; padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 10px rgba(0,0,0,0.1); z-index: 10; }
        .header-elegante h1 { margin: 0; font-size: 1.2rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .header-elegante .instruccion { font-size: 0.85rem; background: rgba(255,255,255,0.2); padding: 5px 15px; border-radius: 20px; font-weight: 600; }
        .iframe-container { flex: 1; width: 100%; position: relative; background: white; }
        iframe { width: 100%; height: 100%; border: none; }
    </style>
</head>
<body>
    <div class="header-elegante">
        <h1><i class="fa-solid fa-shield-halved"></i> Conexión Segura IOSFA</h1>
        <div class="instruccion"><i class="fa-solid fa-mobile-screen"></i> Modo Optimizado</div>
    </div>
    <div class="iframe-container">
        <iframe src="https://validador.iosfa.gob.ar/ValidadorMejorado" allow="clipboard-read; clipboard-write"></iframe>
    </div>
</body>
</html>