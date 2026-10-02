const https = require('https');
https.get('https://validador.iosfa.gob.ar/ValidadorDni?modo=sugerencias', (res) => {
    console.log("Status Code:", res.statusCode);
    console.log("Headers:", res.headers);
});
