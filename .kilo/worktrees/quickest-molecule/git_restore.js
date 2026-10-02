const fs = require('fs');
const cp = require('child_process');

let originalContent = '';
try {
    originalContent = cp.execSync('git show HEAD:"tamp_login_iosfa.user.js"').toString('utf8');
} catch(e) {
    console.error("Git failed");
    process.exit(1);
}

fs.writeFileSync('C:\\Users\\HACKRO\\Documents\\GitHub\\actis\\tamp_login_iosfa.user.js', originalContent);
console.log("Restored from GIT!");
