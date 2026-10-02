<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';
$dias = ['Domingo','Lunes','Martes','Miercoles','Jueves','Viernes','Sabado'];
$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$out['fecha_actual'] = $dias[date('w')] . ', ' . date('j') . ' de ' . $meses[date('n')] . ' de ' . date('Y');
$out['total']=0; $out['card1_val']=0; $out['card2_val']=0;
$out['pct_izq']=0; $out['pct_der']=0;
$out['val_izq_a']=0; $out['val_izq_b']=0; $out['val_der_a']=0; $out['val_der_b']=0;
$out['chip_a']=0; $out['chip_b']=0; $out['hora_pico']='N/A';
$out['mini1_a']=0; $out['mini1_b']=0; $out['mini2_a']=0; $out['mini2_b']=0;
$out['mini3_a']=0; $out['mini3_b']=0; $out['eficacia']=0;
$out['tiempo_prom']='0m'; $out['tiempo_max']='0m'; $out['combinado']=0;
$out['top1']=[['nombre'=>'Sin datos','cantidad'=>0]]; $out['top1_key']='nombre';
$out['top2']=[['nombre'=>'Sin datos','cantidad'=>0]]; $out['top2_key']='nombre';
$out['area_card1_val']=0; $out['area_card2_val']=0;
$out['pie_a']=0; $out['pie_b']=0; $out['pie_c']=0;
echo json_encode($out);
