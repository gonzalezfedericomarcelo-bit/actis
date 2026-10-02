const ftp = require('basic-ftp');
const fs = require('fs');
const path = require('path');

async function deploy() {
    const args = process.argv.slice(2);
    if (args.length === 0) {
        console.error('❌ Especifica al menos un archivo para subir.');
        process.exit(1);
    }

    const client = new ftp.Client();
    client.ftp.verbose = false;

    try {
        console.log('🔄 Conectando al servidor FTP...');
        await client.access({
            host: '147.93.38.97',
            user: 'u415354546',
            password: 'Fmg35911@',
            secure: false
        });

        // Intentamos ir a la carpeta de actis
        try {
            await client.cd('/domains/federicogonzalez.net/public_html/actis');
        } catch (e) {
            try {
                await client.cd('public_html/actis');
            } catch (e2) {
                console.log('No se pudo navegar al directorio actis. Subiendo en directorio actual.');
            }
        }

        for (const file of args) {
            const localPath = path.resolve(__dirname, file);
            if (!fs.existsSync(localPath)) {
                console.error(`❌ El archivo ${file} no existe localmente.`);
                continue;
            }
            console.log(`📤 Subiendo ${file} a producción...`);
            const remotePath = file.replace(/\\/g, '/');
            
            try {
                const dir = path.dirname(remotePath);
                if (dir !== '.') {
                    await client.ensureDir(dir);
                    await client.cd('..'); // Vuelve atrás para mantener consistencia
                }
            } catch(e) {}
            
            await client.uploadFrom(localPath, remotePath);
            console.log(`✅ ${file} subido con éxito.`);
        }
    }
    catch(err) {
        console.error('❌ Error de FTP:', err);
    }
    client.close();
}

deploy();
