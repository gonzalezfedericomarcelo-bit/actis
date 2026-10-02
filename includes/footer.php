<?php 
// Aseguramos que la variable de permisos exista por si el footer es llamado aislado
$mis_permisos = isset($_SESSION['permisos']) ? $_SESSION['permisos'] : [];
?>
</main>

<footer class="actis-footer-complejo">
    <div class="afc-container">
        <div class="afc-columna afc-branding">
            <h3><img src="img/osfa.svg" alt="Logo ACTIS" style="width: 40px; height: 40px; border-radius: 10px;"> ACTIS Core</h3>
            <p>Plataforma de Gestión Integral. Optimizando la atención médica de nuestros afiliados con tecnología de vanguardia, procesos automatizados y control estadístico en tiempo real.</p>
            <div class="afc-social">
                <a href="#"><i class="fa-solid fa-globe"></i></a>
                <a href="#"><i class="fa-brands fa-whatsapp"></i></a>
                <a href="#"><i class="fa-solid fa-envelope"></i></a>
            </div>
        </div>

        <div class="afc-columna afc-links">
            <h4>Área Operativa</h4>
            <ul>
                <li><a href="dashboard.php"><i class="fa-solid fa-angle-right"></i> Dashboard Principal</a></li>
                
                <?php if(in_array('modulo_turnos', $mis_permisos)): ?>
                <li><a href="turnos_listar.php"><i class="fa-solid fa-angle-right"></i> Listado de Turnos</a></li>
                <?php endif; ?>

                <?php if(in_array('modulo_recepcion', $mis_permisos)): ?>
                <li><a href="recepcion_inicio.php"><i class="fa-solid fa-angle-right"></i> Recepción de Pacientes</a></li>
                <li><a href="recepcion_salidas_lista.php"><i class="fa-solid fa-angle-right"></i> Checkout y Firmas</a></li>
                <?php endif; ?>

                <?php if(in_array('modulo_validador', $mis_permisos)): ?>
                <li><a href="https://validador.iosfa.gob.ar/ValidadorDni" target="_blank"><i class="fa-solid fa-angle-right"></i> Escáner de Validación</a></li>
                <li><a href="validador_listar.php"><i class="fa-solid fa-angle-right"></i> Historial IOSFA</a></li>
                <?php endif; ?>

                <?php if(in_array('modulo_pacientes', $mis_permisos)): ?>
                <li><a href="pacientes_listar.php"><i class="fa-solid fa-angle-right"></i> Directorio de Pacientes</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="afc-columna afc-links">
            <h4>Infraestructura</h4>
            <ul>
                <?php if(in_array('modulo_totem_admin', $mis_permisos) || in_array('modulo_reportes', $mis_permisos) || in_array('modulo_usuarios', $mis_permisos) || in_array('modulo_roles', $mis_permisos)): ?>
                    
                    <?php if(in_array('modulo_totem_admin', $mis_permisos)): ?>
                    <li><a href="admin_servicios.php"><i class="fa-solid fa-angle-right"></i> Servicios del Tótem</a></li>
                    <li><a href="admin_totem.php"><i class="fa-solid fa-angle-right"></i> Parámetros de Hardware</a></li>
                    <?php endif; ?>
                    
                    <?php if(in_array('modulo_reportes', $mis_permisos)): ?>
                    <li><a href="reporte_general.php"><i class="fa-solid fa-angle-right"></i> Analíticas del Sistema</a></li>
                    <?php endif; ?>
                    
                    <?php if(in_array('modulo_usuarios', $mis_permisos)): ?>
                    <li><a href="usuarios_gestionar.php"><i class="fa-solid fa-angle-right"></i> Gestión de Cuentas</a></li>
                    <?php endif; ?>
                    
                    <?php if(in_array('modulo_roles', $mis_permisos)): ?>
                    <li><a href="roles_gestionar.php"><i class="fa-solid fa-angle-right"></i> Roles y Permisos</a></li>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <li><span style="color: #64748b; font-size: 0.85rem;">Acceso restringido a nivel administrativo.</span></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="afc-columna afc-contacto">
            <h4>Soporte Técnico</h4>
            <div class="afc-info-item">
                <i class="fa-solid fa-user-shield"></i>
                <span>SG MEC INFO Federico Gonzalez</span>
            </div>
            <div class="afc-info-item">
                <i class="fa-solid fa-phone"></i>
                <span>+54 11 6611-6861</span>
            </div>
            <div class="afc-info-item">
                <i class="fa-solid fa-location-dot"></i>
                <span>Policlínica General Actis</span>
            </div>
            <div class="afc-estado">
                <div class="afc-dot"></div> Sistemas en línea y operando
            </div>
        </div>
    </div>
    
    <div class="afc-bottom">
        <p>&copy; <?php echo date('Y'); ?> Policlínica General ACTIS. Todos los derechos reservados. | Software exclusivo IOSFA.</p>
    </div>
</footer>

<style>
    /* ESTILOS DEL FOOTER COMPLEJO */
    .actis-footer-complejo { background: #0f172a; color: #cbd5e1; font-family: 'Poppins', sans-serif; margin-top: auto; border-top: 4px solid #144973; position: relative; z-index: 10; }
    .afc-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 40px; max-width: 1400px; margin: 0 auto; padding: 60px 30px; }
    
    .afc-columna h3 { color: #ffffff; font-size: 1.8rem; margin: 0 0 20px 0; display: flex; align-items: center; gap: 12px; font-weight: 900; letter-spacing: -0.5px; }
    .afc-branding p { font-size: 0.9rem; line-height: 1.7; color: #94a3b8; margin-bottom: 25px; }
    
    .afc-social { display: flex; gap: 12px; }
    .afc-social a { width: 38px; height: 38px; background: rgba(255,255,255,0.05); color: #cbd5e1; border-radius: 8px; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.3s ease; font-size: 1.1rem; border: 1px solid rgba(255,255,255,0.1); }
    .afc-social a:hover { background: #144973; color: white; transform: translateY(-3px); border-color: #144973; box-shadow: 0 5px 15px rgba(20,73,115,0.4); }

    .afc-columna h4 { color: #ffffff; font-size: 1.1rem; margin: 0 0 25px 0; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; position: relative; padding-bottom: 12px; }
    .afc-columna h4::after { content: ''; position: absolute; left: 0; bottom: 0; width: 40px; height: 3px; background: #144973; border-radius: 2px; }

    .afc-links ul { list-style: none; padding: 0; margin: 0; }
    .afc-links ul li { margin-bottom: 15px; }
    .afc-links ul li a { color: #94a3b8; text-decoration: none; font-size: 0.95rem; transition: all 0.2s; display: flex; align-items: center; gap: 10px; font-weight: 500; }
    .afc-links ul li a i { font-size: 0.75rem; color: #144973; transition: transform 0.2s; }
    .afc-links ul li a:hover { color: #ffffff; padding-left: 6px; }
    .afc-links ul li a:hover i { transform: translateX(4px); }

    .afc-contacto .afc-info-item { display: flex; align-items: flex-start; gap: 15px; margin-bottom: 20px; color: #94a3b8; font-size: 0.95rem; line-height: 1.5; }
    .afc-contacto .afc-info-item i { color: #144973; font-size: 1.2rem; margin-top: 2px; }
    .afc-contacto .afc-info-item span { font-weight: 500; }
    
    .afc-estado { margin-top: 30px; background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.2); padding: 12px 15px; border-radius: 8px; display: inline-flex; align-items: center; gap: 10px; color: #4ade80; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .afc-dot { width: 10px; height: 10px; background: #22c55e; border-radius: 50%; box-shadow: 0 0 10px #22c55e; animation: pulse-dot 2s infinite; }
    @keyframes pulse-dot { 0% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(1.3); } 100% { opacity: 1; transform: scale(1); } }

    .afc-bottom { background: #020617; padding: 25px 20px; text-align: center; border-top: 1px solid rgba(255,255,255,0.05); }
    .afc-bottom p { margin: 0; font-size: 0.85rem; color: #64748b; font-weight: 500; }
    
    @media (max-width: 992px) {
        .actis-footer-complejo { margin-bottom: 75px; /* Evita que el bottom-bar móvil tape el footer */ }
        .afc-container { padding: 40px 20px; gap: 30px; }
    }
</style>

<script>
    // Inyector automático de Loaders al tocar cualquier formulario para feedback instantáneo
    if (document.querySelector('form')) {
        document.querySelectorAll('form').forEach(f => {
            f.addEventListener('submit', (e) => { 
                if(!f.classList.contains('no-loader') && f.getAttribute('target') !== '_blank') {
                    if (typeof mostrarLoader === "function") {
                        mostrarLoader(); 
                    }
                }
            });
        });
    }
    
    // Mata el Loader si el usuario navega hacia atrás
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) { 
            if (typeof ocultarLoader === "function") {
                ocultarLoader(); 
            }
        }
    });
</script>
</body>
</html>