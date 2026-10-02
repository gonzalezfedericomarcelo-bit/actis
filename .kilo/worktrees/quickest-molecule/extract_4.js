const fs = require('fs');

const content = fs.readFileSync('C:\\Users\\HACKRO\\Documents\\GitHub\\actis\\found_script.txt', 'utf8');

const lines = content.split('\n');
let scriptLines = [];
let capturing = false;

for (let i = 0; i < lines.length; i++) {
    let line = lines[i];
    
    if (line.includes('274: 1: // ==UserScript==')) {
        capturing = true;
    }
    
    if (capturing) {
        if (line.includes('The above content shows the entire') || line.includes('The above content does NOT show the entire file')) {
            break;
        }
        
        let match = line.match(/^\d+:\s*(.*)$/);
        if (match) {
            let innerLine = match[1];
            let innerMatch = innerLine.match(/^\d+:\s(.*)$/);
            if (innerMatch) {
                scriptLines.push(innerMatch[1]);
            } else {
                scriptLines.push(innerLine);
            }
        }
    }
}

fs.writeFileSync('C:\\Users\\HACKRO\\Documents\\GitHub\\actis\\tamp_login_iosfa.user.js', scriptLines.join('\n'));
console.log("Written test script with", scriptLines.length, "lines.");
