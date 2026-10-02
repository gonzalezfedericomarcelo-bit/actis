<?php
// Generación de Hash de Seguridad Criptográfica para auditoría visual
$hash_impresion = strtoupper(substr(hash('sha256', date('Y-m-d H:i:s') . 'ACTIS_SECURE_PRINT'), 0, 14));
?>
<div style="font-family: 'Courier New', Courier, monospace; text-align: center; margin-top: 0px; border-top: 2px dashed #000; padding-top: 2px; color: #000; line-height: 1.1;">
    <div style="font-size: 11px; font-weight: 900; letter-spacing: 1px; margin-bottom: 0px;">*** COMPLIANCE Y AUDITORÍA ***</div>
    <div style="font-size: 10px; font-weight: bold; margin-bottom: 2px; text-align: center; padding: 0 5px;">
        Ticket oficial emitido bajo estrictas normas de auditoría médica interna. La alteración de este documento será reportada automáticamente.
    </div>

    <div style="border-top: 1px dotted #000; border-bottom: 1px dotted #000; padding: 1px 0; font-size: 9px; font-weight: bold; margin-bottom: 2px;">
        AUDIT: <?php echo $hash_impresion; ?> | LOC: AR-BUE <br/> TERM: TOTEM_ACTIS
    </div>

    <div style="font-size: 0.65rem; font-weight: 700; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 0px; letter-spacing: 0.2px;">
        &copy; <?php echo date('Y'); ?> SG Mec Info Federico GONZÁLEZ <br/> Encargado de Informática <br/> Policlínica General ACTIS
    </div>
    
</div>
<div style="border-top: 1px dotted #000; padding: 1px 0; font-size: 9px; font-weight: bold; margin-bottom: 2px;">
       
    </div>