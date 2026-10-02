const https = require('https');
https.get('https://federicogonzalez.net/actis/test_tamp_sugerencias.php', (res) => {
    let data = '';
    res.on('data', (chunk) => data += chunk);
    res.on('end', () => {
        console.log("Length:", data.length);
        console.log("First 100 chars:", data.substring(0, 100));
    });
}).on('error', (e) => {
    console.error(e);
});
