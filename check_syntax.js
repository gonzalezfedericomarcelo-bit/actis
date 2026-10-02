const fs = require('fs');
let code = fs.readFileSync('test_tamp_ventanilla.php', 'utf8');

// Encuentra donde empieza la funcion de javascript
const jsStart = code.indexOf('(function() {');
if (jsStart === -1) {
    console.error("No se encontro (function() {");
    process.exit(1);
}
let jsCode = code.substring(jsStart);
// Reemplazar TODAS las etiquetas PHP con un string "1" (para que no rompan sintaxis si están en medio de una expresion)
jsCode = jsCode.replace(/<\?php[\s\S]*?\?>/g, '"1"');

try {
    new Function(jsCode);
    console.log("Syntax OK");
} catch (e) {
    console.error("SYNTAX ERROR:", e.message);
    const lines = jsCode.split('\n');
    for (let i = 0; i < lines.length; i++) {
        try {
            new Function(lines.slice(0, i+1).join('\n'));
        } catch(err) {
            if (err.message === e.message) {
                console.log('Error around line ' + (i+1) + ': ' + lines[i]);
                console.log('Previous lines:');
                console.log(lines[i-2]);
                console.log(lines[i-1]);
                break;
            }
        }
    }
}
