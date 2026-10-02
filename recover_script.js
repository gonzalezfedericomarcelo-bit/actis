const fs = require('fs');

const content = fs.readFileSync('C:\\Users\\HACKRO\\Documents\\GitHub\\actis\\found_script.txt', 'utf8');
const lines = content.split('\n');

// Find the start of the third block (which is the original script)
let startIndex = -1;
for (let i = 0; i < lines.length; i++) {
    if (lines[i].includes('Showing lines 1 to 405')) {
        startIndex = i + 2; // skip the lines formatting header
        break;
    }
}

if (startIndex !== -1) {
    let scriptLines = [];
    for (let i = startIndex; i < lines.length; i++) {
        if (lines[i].includes('The above content shows the entire')) {
            break;
        }
        let match = lines[i].match(/^\d+:\s*(.*)$/);
        if (match) {
            let innerLine = match[1];
            let innerMatch = innerLine.match(/^\d+:\s*(.*)$/);
            if (innerMatch) {
                scriptLines.push(innerMatch[1]);
            } else {
                scriptLines.push(innerLine);
            }
        }
    }
    fs.writeFileSync('C:\\Users\\HACKRO\\Documents\\GitHub\\actis\\tamp_login_iosfa.user.js', scriptLines.join('\n'));
    console.log("Recovered script!");
} else {
    console.log("Could not find start index.");
}
