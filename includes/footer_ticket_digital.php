<div style="margin-top: 25px; padding-top: 15px; border-top: 1px dashed #cbd5e1; font-family: 'JetBrains Mono', 'Courier New', monospace; text-align: left; width: 100%; box-sizing: border-box;">
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; font-size: 0.68rem; color: #475569; margin-bottom: 15px;">
        
        <div style="line-height: 1.4; background: #f8fafc; padding: 8px; border-radius: 6px; border: 1px solid #e2e8f0;">
            <div style="font-weight: 800; color: #0f172a; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; margin-bottom: 5px; text-transform: uppercase; font-size: 0.62rem; letter-spacing: 0.5px;">
                <i class="fa-solid fa-shield-halved" style="color: #10b981;"></i> Seguridad & Auditoría
            </div>
            <div><strong>Cifrado:</strong> End-to-End Encryption (E2EE) / TLSv1.3</div>
            <div><strong>Cumplimiento:</strong> ISO/IEC 27001 | Normativa HIPAA</div>
            <div><strong>Protección:</strong> Habilitado Ley Nac. N° 25.326 DD.PP.</div>
            <div><strong>Hash Core:</strong> <?php echo strtoupper(substr(hash('sha256', php_uname() . 'ACTIS_SYS'), 0, 16)); ?></div>
        </div>

        <div style="line-height: 1.4; background: #f8fafc; padding: 8px; border-radius: 6px; border: 1px solid #e2e8f0;">
            <div style="font-weight: 800; color: #0f172a; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; margin-bottom: 5px; text-transform: uppercase; font-size: 0.62rem; letter-spacing: 0.5px;">
                <i class="fa-solid fa-server" style="color: #2563eb;"></i> Entorno & Rendimiento
            </div>
            <div><strong>Plataforma:</strong> ACTIS Core v4.2 PROD (Node-01)</div>
            <div><strong>Base de Datos:</strong> MySQL Enlazado Engine v8.0</div>
            <div><strong>Consumo RAM:</strong> <?php echo number_format(memory_get_usage() / 1024 / 1024, 2); ?> MB</div>
            <div><strong>Render PHP:</strong> <?php echo number_format((microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]) * 1000, 2); ?> ms</div>
        </div>

        <div style="line-height: 1.4; background: #f8fafc; padding: 8px; border-radius: 6px; border: 1px solid #e2e8f0;">
            <div style="font-weight: 800; color: #0f172a; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; margin-bottom: 5px; text-transform: uppercase; font-size: 0.62rem; letter-spacing: 0.5px;">
                <i class="fa-solid fa-clock" style="color: #7c3aed;"></i> Sincronización Temporal
            </div>
            <div><strong>Zona Horaria:</strong> America/Argentina/Buenos_Aires</div>
            <div><strong>Marca de Tiempo:</strong> <?php echo date('d/m/Y H:i:s'); ?> (UTC-3)</div>
            <div><strong>Servidor NTP:</strong> pool.ntp.org Sincronizado</div>
            <div><strong>IP Origen:</strong> <?php echo $_SERVER['REMOTE_ADDR']; ?> (Terminal Blindada)</div>
        </div>

    </div>

    <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 700; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 8px; letter-spacing: 0.2px;">
        &copy; <?php echo date('Y'); ?> SG Mec Info Federico GONZÁLEZ <br> Encargado de Informática <br> Policlínica General ACTIS - OSFA
    </div>

</div>