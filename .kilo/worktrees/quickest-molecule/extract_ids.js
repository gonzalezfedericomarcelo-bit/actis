const fs = require('fs');
const content = fs.readFileSync('pagina1', 'utf-8');
const regex = /id="([^"]*_0001)"/g;
let match;
while ((match = regex.exec(content)) !== null) {
    console.log(match[1]);
}
