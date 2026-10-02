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
            .kb-action-enter { background: #198754; width: 100%; color: white; border: none; font-size: 28px; padding: 20px; margin-top: 10px; border-radius: 10px; letter-spacing: 2px; cursor: pointer; font-weight: bold; }
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
            .hint-text { color: #0d6efd; font-size: 16px; margin-top: 15px; font-weight: bold; }
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
                    <p style="margin: 0 0 10px 0; font-size: 18px; color: #aaa;">Por favor, ingrese los 3 caracteres de la imagen usando su <b>teclado físico</b>:</p>
                    <div style="display:flex; justify-content:center; align-items:center; gap: 15px; margin-bottom: 15px;">
                        <div id="captcha-img-wrapper"></div>
                        <button id="kb-reload" style="background:#ffc107; color:black; border:none; border-radius:8px; padding:20px 15px; font-weight:bold; cursor:pointer; font-size: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.3); transition: background 0.1s;">🔄 RECARGAR</button>
                    </div>
                    <div id="fake-captcha-input" class="fake-input">___</div>
                    <button class="kb-action-enter" id="kb-enter" disabled>INGRESAR AL SISTEMA (ENTER)</button>
                </div>
            </div>
        `;
        document.documentElement.appendChild(totemUI);

        const fakeCaptchaInput = document.getElementById('fake-captcha-input');
        const kbEnter = document.getElementById('kb-enter');
        const errorModal = document.getElementById('totem-error-modal');
        const errorMessage = document.getElementById('totem-error-message');
        
        document.getElementById('btn-close-modal').addEventListener('click', () => { errorModal.style.display = 'none'; });
        document.getElementById('kb-reload').addEventListener('click', () => { window.location.reload(); });

        let currentCaptchaText = "";

        // EVENTOS DEL TECLADO FÍSICO
        document.addEventListener('keydown', (e) => {
            // Ignoramos si el modal de error está visible o si aún no llegamos a la etapa del captcha
            if (errorModal.style.display === 'flex' || document.getElementById('captcha-section').style.display === 'none') {
                return;
            }

            if (e.key === 'Backspace') {
                if (currentCaptchaText.length > 0) {
                    currentCaptchaText = currentCaptchaText.slice(0, -1);
                    updateCaptchaUI();
                }
            } else if (e.key === 'Enter') {
                if (currentCaptchaText.length === 3 && !kbEnter.disabled) {
                    kbEnter.click();
                }
            } else if (/^[a-zA-Z0-9]$/.test(e.key)) {
                if (currentCaptchaText.length < 3) {
                    currentCaptchaText += e.key;
                    updateCaptchaUI();
                }
            }
        });

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
                                botonE.innerText = "INGRESAR AL SISTEMA (ENTER)";
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
                await new Promise(r => setTimeout(r, 800)); // Misma latencia requerida que en el Tótem
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
        }, 1000);
    }
})();
