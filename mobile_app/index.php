<?php
// Panel principal para ACTIS Mobile
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ACTIS Mobile</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Outfit', sans-serif;
            background-color: #0f172a;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .container {
            text-align: center;
            padding: 20px;
        }
        .logo {
            font-size: 3rem;
            color: #10b981;
            margin-bottom: 10px;
        }
        h1 {
            font-size: 2rem;
            margin: 0;
            font-weight: 900;
        }
        p {
            color: #94a3b8;
            font-size: 1.2rem;
        }
        .btn {
            display: inline-block;
            margin-top: 30px;
            padding: 15px 30px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: bold;
            font-size: 1.1rem;
            box-shadow: 0 10px 20px rgba(16,185,129,0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <i class="fa-solid fa-mobile-screen-button logo"></i>
        <h1>ACTIS Mobile</h1>
        <p>¡Tu APK Server-Driven está conectada!</p>
        <p style="font-size: 0.9rem;">(Puedes cambiar este diseño en mobile_app/index.php)</p>
        <a href="#" class="btn" onclick="alert('Funciona perfecto')">Probar Acción</a>
    </div>
</body>
</html>
