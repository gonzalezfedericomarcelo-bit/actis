<?php
header("Access-Control-Allow-Origin: *");
$json = file_get_contents('php://input');
if($json) {
    file_put_contents('estado_totem.json', $json);
}
?>