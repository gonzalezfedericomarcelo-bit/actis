const fs = require('fs');

const scriptPath = 'C:\\Users\\HACKRO\\Documents\\GitHub\\actis\\tamp_login_iosfa.user.js';
let code = fs.readFileSync(scriptPath, 'utf8');

// Replace the Script Injection logic for Captcha
code = code.replace(/let scriptCaptcha = document\.createElement\('script'\);[\s\S]*?scriptCaptcha\.remove\(\);/, `
                let globalWin = (typeof unsafeWindow !== 'undefined') ? unsafeWindow : window;
                let cid = cidInput;
                if (globalWin[cid] && typeof globalWin[cid].SetText === 'function') {
                    globalWin[cid].SetText(currentCaptchaText);
                } else {
                    let inp = document.getElementById(cid + "_I") || document.getElementById(cid);
                    if (inp) {
                        inp.value = currentCaptchaText;
                        inp.dispatchEvent(new Event('input', { bubbles: true }));
                        inp.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
`);

// Replace the Script Injection logic for Login Button
code = code.replace(/let scriptLogin = document\.createElement\('script'\);[\s\S]*?scriptLogin\.remove\(\);/, `
                    let globalWin = (typeof unsafeWindow !== 'undefined') ? unsafeWindow : window;
                    if (typeof globalWin.onLoguearUsuario === 'function') {
                        globalWin.onLoguearUsuario();
                    } else {
                        let btn = document.querySelector('[id*="btnLogin"]');
                        if (btn) {
                            let cidBtn = btn.id;
                            if (globalWin[cidBtn] && typeof globalWin[cidBtn].DoClick === 'function') {
                                globalWin[cidBtn].DoClick();
                            } else {
                                btn.click();
                            }
                        } else {
                            let form = document.querySelector('form');
                            if(form) form.submit();
                        }
                    }
`);

// Also fix the password injection
code = code.replace(/let scriptPass = document\.createElement\('script'\);[\s\S]*?scriptPass\.remove\(\);/, `
                let globalWin = (typeof unsafeWindow !== 'undefined') ? unsafeWindow : window;
                if (globalWin[cid] && typeof globalWin[cid].SetText === 'function') {
                    globalWin[cid].SetText(passText);
                } else {
                    let inp = document.getElementById(cid + "_I") || document.getElementById(cid);
                    if (inp) {
                        inp.value = passText;
                        inp.dispatchEvent(new Event('input', { bubbles: true }));
                        inp.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
`);

fs.writeFileSync(scriptPath, code);
console.log("Fixed script injections.");
