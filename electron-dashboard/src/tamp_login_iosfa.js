// ==UserScript==
// @name         AutoLogin IOSFA - Kiosco UI
// @namespace    http://tampermonkey.net/
// @version      2.1
// @description  Interfaz de Tótem para IOSFA, autocompleta CUIT/PASS y muestra teclado en pantalla para Captcha.
// @author       Antigravity
// @match        *://validador.iosfa.gob.ar/*
// @grant        GM_addStyle
// ==/UserScript==

(function() {
    'use strict';

    const CUIT = "23165102819";
    const PASS = "2wsx3edc";

    let inputCuit = null;
    let intentosLogin = 0;
    let checkLogin = setInterval(() => {
        let inputPass = document.querySelector('input[type="password"]');
        let imgCaptchaReal = document.querySelector('img[id*="Captcha_IMG"]');
        
        if (inputPass && imgCaptchaReal) {
            inputCuit = document.querySelector('input[id*="txtUserName"], input[id*="txtCUIT"]');
            if (!inputCuit) {
                let textInputs = Array.from(document.querySelectorAll('input[type="text"]'));
                inputCuit = textInputs.pop();
            }
            clearInterval(checkLogin);
            iniciarAutologin();
        } else if (intentosLogin > 20) {
            clearInterval(checkLogin);
        }
        intentosLogin++;
    }, 500);

    function iniciarAutologin() {
        let style = document.createElement('style');
        style.textContent = `
            body { background: #1a1b1c !important; overflow: hidden; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
            #totem-ui { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: #121417; color: white; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 9999999; }
            .login-box { background: #1e2227; padding: 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.8); text-align: center; width: 600px; }
            .login-box h1 { margin-top: 0; font-size: 28px; color: #0d6efd; margin-bottom: 20px; font-weight: bold; }
            .status-text { color: #198754; font-size: 18px; margin-bottom: 15px; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 10px; }
            .captcha-container { background: #2a2f35; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
            .captcha-container img { border-radius: 5px; border: 2px solid #0d6efd; margin: 0; }
            #kb-reload:active { background: #e0a800 !important; transform: translateY(2px); }
            .fake-input { width: 100%; height: 60px; line-height: 56px; font-size: 32px; text-align: center; background: #121417; color: #fff; border: 2px solid #0d6efd; box-sizing: border-box; border-radius: 8px; letter-spacing: 5px; font-weight: bold; user-select: none; }
            .keyboard { display: flex; flex-direction: column; gap: 10px; margin-top: 30px; }
            .kb-row { display: flex; justify-content: center; gap: 10px; }
            .kb-key { background: #2a2f35; color: white; border: none; border-radius: 8px; font-size: 24px; font-weight: bold; padding: 15px 0; width: 60px; cursor: pointer; transition: background 0.1s; box-shadow: 0 4px 6px rgba(0,0,0,0.3); text-transform: none; }
            .kb-key:active { background: #0d6efd; transform: translateY(2px); }
            .kb-action-del { background: #dc3545; width: 120px; font-size: 20px; }
            .kb-action-del:active { background: #bb2d3b; }
            .kb-action-shift { background: #6c757d; width: 100px; font-size: 20px; }
            .kb-action-shift:active { background: #5c636a; }
            .kb-action-shift.active { background: #0d6efd; }
            .kb-action-enter { background: #198754; width: 100%; font-size: 28px; padding: 20px; margin-top: 10px; border-radius: 10px; letter-spacing: 2px; }
            .kb-action-enter:active { background: #157347; }
            .kb-action-enter:disabled { background: #444; color: #888; cursor: not-allowed; transform: none; box-shadow: none; }
            .modal-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.8); display: none; align-items: center; justify-content: center; z-index: 10000000; }
            .modal-content { background: #2a2f35; padding: 30px; border-radius: 10px; border: 2px solid #dc3545; text-align: center; max-width: 500px; color: white; box-shadow: 0 10px 30px rgba(0,0,0,0.8); }
            .modal-title { color: #dc3545; font-size: 24px; font-weight: bold; margin-bottom: 15px; }
            .modal-message { font-size: 18px; margin-bottom: 25px; }
            .modal-btn { background: #0d6efd; color: white; border: none; padding: 10px 30px; font-size: 18px; border-radius: 5px; cursor: pointer; font-weight: bold; }
            .modal-btn:active { background: #0b5ed7; transform: translateY(2px); }
            .progress-container { width: 100%; height: 15px; background: #121417; border-radius: 10px; margin-top: 10px; overflow: hidden; display: none; border: 1px solid #333; }
            .progress-bar { width: 0%; height: 100%; background: #198754; transition: width 0.1s linear; }
        `;
        document.head.appendChild(style);

        const totemUI = document.createElement('div');
        totemUI.id = 'totem-ui';
        totemUI.innerHTML = `
            <div id="totem-error-modal" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-title">⚠️ ATENCIÓN</div>
                    <div id="totem-error-message" class="modal-message"></div>
                    <button class="modal-btn" id="btn-close-modal">ENTENDIDO</button>
                </div>
            </div>
            <div class="login-box">
                <h1>INGRESO AL SISTEMA OSFA</h1>
                <div id="status-cuit" class="status-text">
                    ⏳ Autocompletando Credenciales...
                    <div id="progress-container" class="progress-container"><div id="progress-bar" class="progress-bar"></div></div>
                </div>
                <div id="status-pass" class="status-text" style="display:none;">✔️ Autocompletado Exitoso</div>
                <div id="captcha-section" class="captcha-container" style="display:none;">
                    <p style="margin: 0 0 10px 0; font-size: 18px; color: #aaa;">Por favor, ingrese los 3 caracteres de la imagen:</p>
                    <div style="display:flex; justify-content:center; align-items:center; gap: 15px; margin-bottom: 15px;">
                        <div id="captcha-img-wrapper"></div>
                        <button id="kb-reload" style="background:#ffc107; color:black; border:none; border-radius:8px; padding:20px 15px; font-weight:bold; cursor:pointer; font-size: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.3); transition: background 0.1s;">🔄 RECARGAR</button>
                    </div>
                    <div id="fake-captcha-input" class="fake-input">___</div>
                </div>
                <div id="keyboard" class="keyboard" style="display:none; pointer-events: none; opacity: 0.5;">
                    <div class="kb-row"><button class="kb-key">1</button><button class="kb-key">2</button><button class="kb-key">3</button><button class="kb-key">4</button><button class="kb-key">5</button><button class="kb-key">6</button><button class="kb-key">7</button><button class="kb-key">8</button><button class="kb-key">9</button><button class="kb-key">0</button></div>
                    <div class="kb-row"><button class="kb-key letter-key">Q</button><button class="kb-key letter-key">W</button><button class="kb-key letter-key">E</button><button class="kb-key letter-key">R</button><button class="kb-key letter-key">T</button><button class="kb-key letter-key">Y</button><button class="kb-key letter-key">U</button><button class="kb-key letter-key">I</button><button class="kb-key letter-key">O</button><button class="kb-key letter-key">P</button></div>
                    <div class="kb-row"><button class="kb-key letter-key">A</button><button class="kb-key letter-key">S</button><button class="kb-key letter-key">D</button><button class="kb-key letter-key">F</button><button class="kb-key letter-key">G</button><button class="kb-key letter-key">H</button><button class="kb-key letter-key">J</button><button class="kb-key letter-key">K</button><button class="kb-key letter-key">L</button></div>
                    <div class="kb-row"><button class="kb-key kb-action-shift active" id="kb-shift">MAYÚS</button><button class="kb-key letter-key">Z</button><button class="kb-key letter-key">X</button><button class="kb-key letter-key">C</button><button class="kb-key letter-key">V</button><button class="kb-key letter-key">B</button><button class="kb-key letter-key">N</button><button class="kb-key letter-key">M</button><button class="kb-key kb-action-del" id="kb-del">BORRAR</button></div>
                    <button class="kb-key kb-action-enter" id="kb-enter" disabled>INGRESAR AL SISTEMA</button>
                </div>
            </div>
        `;
        document.documentElement.appendChild(totemUI);

        const fakeCaptchaInput = document.getElementById('fake-captcha-input');
        const kbEnter = document.getElementById('kb-enter');
        const kbDel = document.getElementById('kb-del');
        const kbShift = document.getElementById('kb-shift');
        const keyboard = document.getElementById('keyboard');
        const errorModal = document.getElementById('totem-error-modal');
        const errorMessage = document.getElementById('totem-error-message');
        
        document.getElementById('btn-close-modal').addEventListener('click', () => { errorModal.style.display = 'none'; });

        let currentCaptchaText = "";
        let isUppercase = true;

        document.querySelectorAll('.kb-key:not(#kb-del):not(#kb-enter):not(#kb-shift)').forEach(btn => {
            btn.addEventListener('click', () => {
                if (currentCaptchaText.length < 3) {
                    currentCaptchaText += btn.innerText;
                    updateCaptchaUI();
                }
            });
        });

        kbShift.addEventListener('click', () => {
            isUppercase = !isUppercase;
            if (isUppercase) {
                kbShift.classList.add('active');
                document.querySelectorAll('.letter-key').forEach(btn => { btn.innerText = btn.innerText.toUpperCase(); });
            } else {
                kbShift.classList.remove('active');
                document.querySelectorAll('.letter-key').forEach(btn => { btn.innerText = btn.innerText.toLowerCase(); });
            }
        });

        kbDel.addEventListener('click', () => {
            if (currentCaptchaText.length > 0) {
                currentCaptchaText = currentCaptchaText.slice(0, -1);
                updateCaptchaUI();
            }
        });

        document.getElementById('kb-reload').addEventListener('click', () => { window.location.reload(); });

        function updateCaptchaUI() {
            fakeCaptchaInput.innerText = currentCaptchaText.padEnd(3, '_');
            kbEnter.disabled = currentCaptchaText.length !== 3;
        }

        kbEnter.addEventListener('click', () => {
            let inputCaptchaReal = document.querySelector('input[id*="Captcha_TB"]');
            
            kbEnter.innerText = "PROCESANDO...";
            kbEnter.disabled = true;

            if (inputCaptchaReal) {
                let cidInput = inputCaptchaReal.id.replace('_I', '');
                
                
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

                
                setTimeout(() => {
                    
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

                    
                    let segundosEsperando = 0;
                    let checkError = setInterval(() => {
                        segundosEsperando++;
                        let botonE = document.getElementById('kb-enter');
                        if(botonE) botonE.innerText = "PROCESANDO... ESPERANDO RESPUESTA (" + (segundosEsperando / 2).toFixed(1) + "s)";
                        let errorCells = document.querySelectorAll('.dxeErrorCellSys');
                        let errorVisible = null;
                        for (let i = 0; i < errorCells.length; i++) {
                            if (errorCells[i].style.display !== 'none' && errorCells[i].innerText.trim() !== '') {
                                errorVisible = errorCells[i].innerText;
                                break;
                            }
                        }

                        if (errorVisible) {
                            clearInterval(checkError);
                            errorMessage.innerText = errorVisible;
                            errorModal.style.display = 'flex';
                            currentCaptchaText = "";
                            fakeCaptchaInput.innerText = "___";
                            if(botonE) {
                                botonE.innerText = "INGRESAR AL SISTEMA";
                                botonE.disabled = true;
                            }
                            
                            setTimeout(() => {
                                let imgCaptchaReal = document.querySelector('img[id*="Captcha_IMG"]');
                                let wrapper = document.getElementById('captcha-img-wrapper');
                                if (imgCaptchaReal && wrapper) {
                                    imgCaptchaReal.style.backgroundColor = '#1279C0';
                                    imgCaptchaReal.style.display = 'block';
                                    imgCaptchaReal.style.margin = '0 auto';
                                    wrapper.innerHTML = '';
                                    wrapper.appendChild(imgCaptchaReal);
                                }
                            }, 500);
                        }
                    }, 500);
                }, 300);
            }
        });

        async function autoTypeCUIT(cuitText) {
            inputCuit.focus();
            await new Promise(r => setTimeout(r, 500));
            inputCuit.setSelectionRange(0, 0);
            await new Promise(r => setTimeout(r, 200));
            for (let i = 0; i < cuitText.length; i++) {
                document.execCommand("insertText", false, cuitText[i]);
                await new Promise(r => setTimeout(r, 800)); 
            }
            await new Promise(r => setTimeout(r, 1500));
        }

        function autoFillPass(passText) {
            let inputPass = document.querySelector('input[id*="txtPassword"]');
            if (inputPass) {
                let cid = inputPass.id.replace('_I', '');
                
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

            }
        }

        setTimeout(async () => {
            const progressContainer = document.getElementById('progress-container');
            const progressBar = document.getElementById('progress-bar');
            if (progressContainer) progressContainer.style.display = 'block';
            let progress = 0;
            let progressInterval = setInterval(() => {
                progress += (100 / 110);
                if (progress >= 100) { progress = 100; clearInterval(progressInterval); }
                if (progressBar) progressBar.style.width = progress + '%';
            }, 100);

            await autoTypeCUIT(CUIT);
            document.getElementById('status-cuit').style.display = "none";
            document.getElementById('status-pass').style.display = "block";
            await new Promise(r => setTimeout(r, 500));
            autoFillPass(PASS);
            
            await new Promise(r => setTimeout(r, 500));
            let imgCaptchaReal = document.querySelector('img[id*="Captcha_IMG"]');
            let wrapper = document.getElementById('captcha-img-wrapper');
            if (imgCaptchaReal) {
                imgCaptchaReal.style.backgroundColor = '#1279C0';
                imgCaptchaReal.style.display = 'block';
                imgCaptchaReal.style.margin = '0 auto';
                if (wrapper) {
                    wrapper.innerHTML = '';
                    wrapper.appendChild(imgCaptchaReal);
                }
            } else {
                if (wrapper) wrapper.innerHTML = '<p style="color:#ff4444; font-weight:bold;">Error: No se pudo cargar la imagen del servidor IOSFA.</p>';
            }

            document.getElementById('status-cuit').style.display = "none";
            document.getElementById('status-pass').style.display = "none";
            document.getElementById('captcha-section').style.display = "block";
            keyboard.style.pointerEvents = "auto";
            keyboard.style.opacity = "1";
            keyboard.style.display = "flex";
        }, 1000);
    }
})();
