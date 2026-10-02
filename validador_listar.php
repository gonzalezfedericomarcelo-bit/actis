<?php
require_once 'includes/conexion.php';
require_once 'includes/header.php';

// Construcción dinámica de filtros
$where = "t.estado = 'Pendiente'";

if(isset($_POST['dni_buscar']) && !empty($_POST['dni_buscar'])) {
    $dni = $conexion->real_escape_string($_POST['dni_buscar']);
    $where .= " AND p.dni = '$dni'";
}

if(isset($_GET['f'])) {
    if($_GET['f'] == 'hoy') $where .= " AND t.fecha_turno = CURDATE()";
    if($_GET['f'] == 'manana') $where .= " AND t.fecha_turno = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
    if($_GET['f'] == 'ayer') $where .= " AND t.fecha_turno = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
}
?>
<div class="tarjeta glass-panel">
    <h2 style="font-size: 2rem; font-weight: 700; margin-bottom: 20px;"><i class="fa-solid fa-filter" style="color:var(--color-primario);"></i> Filtros de Búsqueda</h2>
    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
        <a href="validador_listar.php?f=hoy" class="btn btn-primario"><i class="fa-solid fa-calendar-day"></i> Turnos de Hoy</a>
        <a href="validador_listar.php?f=manana" class="btn" style="background:#64748b; color:white;"><i class="fa-solid fa-calendar-plus"></i> Mañana</a>
        <a href="validador_listar.php?f=ayer" class="btn" style="background:#64748b; color:white;"><i class="fa-solid fa-calendar-minus"></i> Ayer</a>
        <a href="validador_listar.php" class="btn" style="background:#cbd5e1; color:#333;"><i class="fa-solid fa-eraser"></i> Limpiar Filtros</a>
    </div>
</div>

<div class="tarjeta glass-panel">
    <h2 style="font-size: 2rem; font-weight: 700; margin-bottom: 25px;"><i class="fa-solid fa-list-check" style="color:var(--color-primario);"></i> Resultados Pendientes de IOFA</h2>
    
    <?php
    $sql = "SELECT t.*, p.nombre, p.apellido, p.dni FROM turnos t INNER JOIN pacientes p ON t.paciente_id = p.id WHERE $where ORDER BY t.fecha_turno ASC";
    $res = $conexion->query($sql);
    
    if($res->num_rows > 0) {
        echo "<div class='table-responsive'>
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Paciente</th>
                            <th>DNI</th>
                            <th>Servicio</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>";
        while($t = $res->fetch_assoc()) {
            $fecha = date("d/m/Y", strtotime($t['fecha_turno']));
            echo "<tr>";
            echo "<td data-label='Fecha'>$fecha</td>";
            echo "<td data-label='Paciente' style='font-weight:700;'>{$t['nombre']} {$t['apellido']}</td>";
            echo "<td data-label='DNI'>
                    <strong>{$t['dni']}</strong> 
                    <button type='button' onclick='copiarDNI(\"{$t['dni']}\")' class='btn' style='background:transparent; color:var(--color-primario); padding:0 5px; border:none; box-shadow:none; min-height:auto;' title='Copiar DNI'><i class='fa-solid fa-copy'></i></button>
                  </td>";
            echo "<td data-label='Servicio'>{$t['servicio']}</td>";
            echo "<td data-label='Estado'><span style='background:#fef08a;color:#854d0e;padding:5px 15px;border-radius:20px;font-weight:700;'>Pendiente</span></td>";
            echo "<td data-label='Acciones' style='min-width: 300px;'>
                    <div style='display:flex; gap:5px; align-items:center;'>
                        <button type='button' onclick='validarAhora(\"{$t['dni']}\", {$t['id']})' class='btn' style='background:#64748b; color:white; padding:8px 12px; min-height:40px;' title='Abrir Validador'><i class='fa-solid fa-arrow-up-right-from-square'></i></button>
                        <form action='validador_guardar.php' method='POST' style='display:flex; gap:5px; margin:0;'>
                            <input type='hidden' name='turno_id' value='{$t['id']}'>
                            <input type='text' name='token_iofa' id='iofa_{$t['id']}' placeholder='Código' required style='padding:8px; border:1px solid var(--color-borde); border-radius:8px; width:100px; min-height:40px; text-align:center; font-weight:700;'>
                            <button type='button' onclick='pegarPortapapeles({$t['id']})' class='btn btn-primario' style='padding:8px 12px; min-height:40px;' title='Pegar'><i class='fa-solid fa-clipboard'></i></button>
                            <button type='submit' class='btn btn-exito' style='padding:8px 15px; min-height:40px;' title='Guardar e Imprimir'><i class='fa-solid fa-print'></i></button>
                        </form>
                    </div>
                  </td>";
            echo "</tr>";
        }
        echo "</tbody></table></div>";
    } else {
        echo "<div style='text-align:center; padding: 40px;'><i class='fa-solid fa-folder-open' style='font-size: 4rem; color: #cbd5e1; margin-bottom: 15px;'></i><h3 style='color: #64748b;'>No hay resultados para esta búsqueda</h3></div>";
    }
    ?>
    
    <script>
    function copiarDNI(dni) {
        navigator.clipboard.writeText(dni).then(() => {
            mostrarExito('DNI copiado: ' + dni);
        });
    }

    function validarAhora(dni, id) {
        // Guardamos en la memoria qué turno estamos validando para saber dónde pegar el código después
        localStorage.setItem('id_turno_validando', id);
        copiarDNI(dni);
        // Abrir como pestaña normal para que funcione la extensión Tampermonkey
        window.open('https://validador.iosfa.gob.ar/ValidadorMejorado', '_blank');
    }

    // Temporizador que vigila constantemente la memoria para pegar en el input iofa_ que corresponda (ej: iofa_16)
    setInterval(function() {
        let codigoNuevo = localStorage.getItem('ultimo_codigo_iofa');
        if (codigoNuevo) {
            let id = localStorage.getItem('id_turno_validando');
            if (id) {
                let campo = document.getElementById('iofa_' + id);
                if (campo) {
                    campo.value = codigoNuevo;
                    campo.style.backgroundColor = '#dcfce7';
                    mostrarExito('¡Código pegado automáticamente!');
                }
            }
            localStorage.removeItem('ultimo_codigo_iofa');
        }
    }, 500);

    async function pegarPortapapeles(id) {
        try {
            const text = await navigator.clipboard.readText();
            if(text && text.length >= 5) {
                document.getElementById('iofa_' + id).value = text.trim().toUpperCase();
                mostrarExito('Código pegado');
            }
        } catch (err) {
            mostrarError('Error al acceder al portapapeles');
        }
    }
    </script>
<?php require_once 'includes/footer.php'; ?>