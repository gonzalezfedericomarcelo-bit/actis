const fs = require('fs');
const path = 'C:\\Users\\HACKRO\\Documents\\GitHub\\actis\\test_tamp_sugerencias.php';
let content = fs.readFileSync(path, 'utf8');

// Remove PHP headers
content = content.replace(/<\?php[\s\S]*?\?>/i, '').trim();

fs.writeFileSync('C:\\Users\\HACKRO\\Documents\\GitHub\\actis\\electron-dashboard\\src\\test_tamp_sugerencias.js', content);
console.log("Extracted test_tamp_sugerencias.js");
