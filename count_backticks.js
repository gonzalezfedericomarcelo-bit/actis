const fs = require('fs');
let code = fs.readFileSync('test_tamp_ventanilla.php', 'utf8');
let count = 0;
for(let i=0; i<code.length; i++) {
    if (code[i] === '`') count++;
}
console.log('Backticks count:', count);
