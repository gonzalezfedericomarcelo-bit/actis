const fs = require('fs');
let code = fs.readFileSync('test_tamp_ventanilla.php', 'utf8');

const jsStart = code.indexOf('(function() {');
let jsCode = code.substring(jsStart);
jsCode = jsCode.replace(/<\?php[\s\S]*?\?>/g, '"1"');
jsCode = jsCode.replace(/`[\s\S]*?`/g, '""');

let count = 0;
let lines = jsCode.split('\n');
for(let i=0; i<lines.length; i++) {
    let line = lines[i];
    for(let j=0; j<line.length; j++) {
        if (line[j] === '{') count++;
        else if (line[j] === '}') count--;
    }
    if (count < 0) {
        console.log('Negative count at line ' + (i+1));
        console.log('Line:', lines[i]);
        console.log('Prev:', lines[i-1]);
        break;
    }
}
console.log('Final count:', count);
