const https = require('https');
https.get('https://validador.iosfa.gob.ar/Login', (res) => {
    let data = '';
    res.on('data', (chunk) => data += chunk);
    res.on('end', () => {
        const fs = require('fs');
        fs.writeFileSync('C:\\Users\\HACKRO\\Documents\\GitHub\\actis\\iosfa_login.html', data);
        console.log("Downloaded IOSFA Login");
    });
});
