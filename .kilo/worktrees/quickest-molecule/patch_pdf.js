const fs = require('fs');

let content = fs.readFileSync('reporte_turnos_pdf.php', 'utf8');

const oldVars = `$filtro_hora_creacion = isset($_GET['hora_creacion']) ? $conexion->real_escape_string($_GET['hora_creacion']) : '';
$filtro_creado_hoy = (isset($_GET['creado_hoy']) && $_GET['creado_hoy'] == '1') ? 1 : 0;`;

const newVars = `$filtro_hora_creacion = isset($_GET['hora_creacion']) ? $conexion->real_escape_string($_GET['hora_creacion']) : '';
$filtro_creado_hoy = (isset($_GET['creado_hoy']) && $_GET['creado_hoy'] == '1') ? 1 : 0;
$filtro_nro_turno = isset($_GET['nro_turno']) ? $conexion->real_escape_string($_GET['nro_turno']) : '';
$filtro_afiliado = isset($_GET['afiliado']) ? $conexion->real_escape_string($_GET['afiliado']) : '';
$filtro_contacto = isset($_GET['contacto']) ? $conexion->real_escape_string($_GET['contacto']) : '';
$filtro_detalles = isset($_GET['detalles']) ? $conexion->real_escape_string($_GET['detalles']) : '';`;

content = content.replace(oldVars, newVars);

const oldWhere = `if($filtro_creado_hoy) { $where_clauses[] = "DATE(t.creado_el) = '".date('Y-m-d')."'"; $texto_filtros[] = "Sacados Hoy"; }`;

const newWhere = `if($filtro_creado_hoy) { $where_clauses[] = "DATE(t.creado_el) = '".date('Y-m-d')."'"; $texto_filtros[] = "Sacados Hoy"; }
if($filtro_nro_turno != '') { $where_clauses[] = "t.numero_turno LIKE '%$filtro_nro_turno%'"; $texto_filtros[] = "Turno: $filtro_nro_turno"; }
if($filtro_afiliado != '') { $where_clauses[] = "p.hc LIKE '%$filtro_afiliado%'"; $texto_filtros[] = "Afiliado: $filtro_afiliado"; }
if($filtro_contacto != '') { $where_clauses[] = "(p.telefono LIKE '%$filtro_contacto%' OR p.email LIKE '%$filtro_contacto%')"; $texto_filtros[] = "Contacto: $filtro_contacto"; }
if($filtro_detalles != '') { $where_clauses[] = "(t.motivo_visita LIKE '%$filtro_detalles%' OR t.observaciones LIKE '%$filtro_detalles%' OR t.diagnostico LIKE '%$filtro_detalles%' OR t.comentario_paciente LIKE '%$filtro_detalles%')"; $texto_filtros[] = "Detalles: $filtro_detalles"; }`;

content = content.replace(oldWhere, newWhere);

fs.writeFileSync('reporte_turnos_pdf.php', content, 'utf8');
