const fs = require('fs');
const cp = require('child_process');

let latestContent = '';
try {
    let files = fs.readdirSync('C:\\Users\\HACKRO\\.gemini\\antigravity-ide\\brain\\604d106d-0ad7-4a7b-9c9e-0f95a356fd51\\artifacts');
} catch(e) {}

const testContent = fs.readFileSync('C:\\Users\\HACKRO\\Documents\\GitHub\\actis\\test_tamp_login_iosfa.user.js', 'utf8');

if (testContent.length > 100) {
    fs.writeFileSync('C:\\Users\\HACKRO\\Documents\\GitHub\\actis\\tamp_login_iosfa.user.js', testContent);
    console.log("Restored from test_tamp!");
} else {
    console.log("Test tamp is also empty!");
}
