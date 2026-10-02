<?php
session_start();
error_reporting(0);

$cookie_file = sys_get_temp_dir() . '/iosfa_cookie_' . session_id() . '.txt';
$action = isset($_GET['action']) ? $_GET['action'] : '';

function getViewState($html) {
    $vs = ''; $ev = ''; $vsg = '';
    if (preg_match('/id="__VIEWSTATE"\s+value="(.*?)"/i', $html, $m)) $vs = $m[1];
    if (preg_match('/id="__EVENTVALIDATION"\s+value="(.*?)"/i', $html, $m)) $ev = $m[1];
    if (preg_match('/id="__VIEWSTATEGENERATOR"\s+value="(.*?)"/i', $html, $m)) $vsg = $m[1];
    return ['__VIEWSTATE' => $vs, '__EVENTVALIDATION' => $ev, '__VIEWSTATEGENERATOR' => $vsg];
}

function mostrarErrorImagen($texto) {
    header("Content-Type: image/png");
    $im = imagecreate(800, 60);
    $fondo = imagecolorallocate($im, 255, 200, 200);
    $color_texto = imagecolorallocate($im, 180, 0, 0);
    imagestring($im, 4, 10, 20, $texto, $color_texto);
    imagepng($im);
    imagedestroy($im);
    exit;
}

if ($action == 'captcha') {
    if (file_exists($cookie_file)) { @unlink($cookie_file); }
    
    $ch = curl_init('https://validador.iosfa.gob.ar/Login/Login.aspx');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    
    $html = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if(curl_errno($ch)) {
        mostrarErrorImagen("Error cURL: " . curl_error($ch));
    }
    
    $_SESSION['iosfa_vs'] = getViewState($html);
    
    if (preg_match('/src="([^"]*?Captcha[^"]*?)"/i', $html, $matches) || preg_match('/src="([^"]*?axd[^"]*?)"/i', $html, $matches)) {
        $path = str_replace('&amp;', '&', $matches[1]);
        if(strpos($path, 'http') === false) {
            $captcha_url = 'https://validador.iosfa.gob.ar/Login/' . ltrim($path, '/');
        } else {
            $captcha_url = $path;
        }
        
        curl_setopt($ch, CURLOPT_URL, $captcha_url);
        $img = curl_exec($ch);
        
        if($img && strlen($img) > 100) {
            header("Content-Type: image/jpeg");
            echo $img;
            exit;
        } else {
            mostrarErrorImagen("La imagen descargo vacia.");
        }
    } else {
        $html_recortado = substr(preg_replace('/\s+/', ' ', strip_tags($html)), 0, 80);
        if(empty($html)) { $html_recortado = "Respuesta totalmente en blanco."; }
        mostrarErrorImagen("HTTP $http_code | HTML: $html_recortado");
    }
    curl_close($ch);
    exit;
}

if ($action == 'validar') {
    header('Content-Type: application/json');
    $captcha = $_POST['captcha'] ?? '';
    $dni = $_POST['dni'] ?? '';
    $cuit = '23-16510281-9';
    $pass = '2wsx3edc';
    
    if (empty($captcha) || empty($dni)) {
        echo json_encode(['exito' => false, 'mensaje' => 'Faltan datos']);
        exit;
    }
    
    $vs = $_SESSION['iosfa_vs'] ?? ['__VIEWSTATE'=>'','__EVENTVALIDATION'=>'','__VIEWSTATEGENERATOR'=>''];
    
    $ch = curl_init('https://validador.iosfa.gob.ar/Login/Login.aspx');
    $postData = [
        '__VIEWSTATE' => $vs['__VIEWSTATE'],
        '__VIEWSTATEGENERATOR' => $vs['__VIEWSTATEGENERATOR'],
        '__EVENTVALIDATION' => $vs['__EVENTVALIDATION'],
        'txtCUIT' => $cuit,
        'txtPassword' => $pass,
        'CaptchaControl1' => strtoupper($captcha),
        'btnIngresar' => 'Ingresar'
    ];
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $htmlLogin = curl_exec($ch);
    
    if (strpos($htmlLogin, 'código introducido no es correcto') !== false || strpos($htmlLogin, 'incorrecto') !== false) {
        echo json_encode(['exito' => false, 'mensaje' => 'Captcha incorrecto. Recargue la imagen e intente nuevamente.']);
        exit;
    }
    
    curl_setopt($ch, CURLOPT_URL, 'https://validador.iosfa.gob.ar/LoNuevo');
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_exec($ch);
    
    curl_setopt($ch, CURLOPT_URL, 'https://validador.iosfa.gob.ar/ValidadorMejorado');
    $htmlVal = curl_exec($ch);
    $vs2 = getViewState($htmlVal);
    
    $postDni = [
        '__EVENTTARGET' => 'btnGenerar',
        '__EVENTARGUMENT' => '',
        '__VIEWSTATE' => $vs2['__VIEWSTATE'],
        '__VIEWSTATEGENERATOR' => $vs2['__VIEWSTATEGENERATOR'],
        '__EVENTVALIDATION' => $vs2['__EVENTVALIDATION'],
        'txtAfiliado' => $dni,
        'btnGenerar' => 'Generar Código de Validación'
    ];
    
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postDni));
    $htmlRes = curl_exec($ch);
    curl_close($ch);
    
    if (preg_match('/CODIGO DE VALIDACION.*?>\s*([A-Z0-9]{5,8})/is', $htmlRes, $m) || preg_match('/([A-Z0-9]{6})/i', $htmlRes, $m)) {
        echo json_encode(['exito' => true, 'codigo' => trim($m[1])]);
    } else {
        echo json_encode(['exito' => false, 'mensaje' => 'Validación fallida. Verifique que el paciente esté activo en IOSFA.']);
    }
}
?>