const fs = require('fs');

const content = fs.readFileSync('C:\\Users\\HACKRO\\Documents\\GitHub\\actis\\found_script.txt', 'utf8');

const lines = content.split('\n');
let scriptLines = [];
let capturing = false;

for (let i = 0; i < lines.length; i++) {
    if (lines[i].includes('Showing lines 1 to 416')) {
        capturing = true;
        continue;
    }
    
    if (capturing) {
        if (lines[i].includes('The above content shows the entire')) {
            break;
        }
        
        let match = lines[i].match(/^\d+:\s*(.*)$/);
        if (match) {
            scriptLines.push(match[1]);
        }
    }
}

if (scriptLines.length > 0) {
    for (let i=0; i<scriptLines.length; i++) {
        let doubleMatch = scriptLines[i].match(/^\d+:\s*(.*)$/);
        if (doubleMatch) {
            scriptLines[i] = doubleMatch[1];
        }
    }
    
    fs.writeFileSync('C:\\Users\\HACKRO\\Documents\\GitHub\\actis\\tamp_login_iosfa.user.js', scriptLines.join('\n').trim());
    console.log("Extracted successfully!");
} else {
    console.log("Could not find the block.");
}
