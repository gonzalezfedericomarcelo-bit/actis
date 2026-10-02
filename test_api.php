<?php
$data = ['dni' => '35911753', 'tipo_accion' => 'cancelado'];
$ch = curl_init('https://federicogonzalez.net/actis/api_enviar_email.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
$result = curl_exec($ch);
echo "Result:\n" . $result . "\n";
?>
