const fs = require('fs');

const content = fs.readFileSync('C:\\Users\\HACKRO\\Documents\\GitHub\\actis\\found_script.txt', 'utf8');
const lines = content.split('\n');

let capturing = false;
let scriptLines = [];

for (let i = 0; i < lines.length; i++) {
    let line = lines[i];
    
    if (line.includes('> 1: // ==UserScript==')) {
        capturing = true;
        scriptLines.push('// ==UserScript==');
        continue;
    }
    
    if (capturing) {
        if (line.includes('The above content shows the entire') || line.includes('The above content does NOT show the entire file')) {
            break;
        }
        
        let match = line.match(/^\s*\d+:\s*(.*)$/);
        if (match) {
            scriptLines.push(match[1]);
        } else if (line.trim() !== '') {
            scriptLines[scriptLines.length - 1] += ' ' + line.trim();
        }
    }
}

fs.writeFileSync('C:\\Users\\HACKRO\\Documents\\GitHub\\actis\\test_tamp_login_iosfa.user.js', scriptLines.join('\n'));
console.log("Written test script with", scriptLines.length, "lines.");
