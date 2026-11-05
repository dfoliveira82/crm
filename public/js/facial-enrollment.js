(() => {
    'use strict';
    const config = window.SELVAR_FACIAL;
    if (!config) return;

    const $ = (id) => document.getElementById(id);
    const modal = $('facial-enrollment-modal');
    const openButton = $('open-facial-enrollment');
    const searchInput = $('facial-employee-search');
    const searchButton = $('facial-employee-search-button');
    const results = $('facial-employee-results');
    const selectedBox = $('facial-selected-employee');
    const startButton = $('facial-start-button');
    const deleteButton = $('facial-delete-button');
    const statusBox = $('facial-enrollment-status');
    const capturePanel = $('facial-capture-panel');
    const captureRoot = $('facial-capture-root');
    const closeCaptureButton = $('facial-capture-close');

    let selectedEmployee = null;
    let stream = null;
    let modelsReady = false;
    let scanning = false;
    let scanBusy = false;
    let activeCapture = false;
    let selectedCameraId = '';
    let switchingCamera = false;

    const setStatus = (message, type = 'info') => {
        statusBox.textContent = message;
        statusBox.dataset.type = type;
        statusBox.hidden = !message;
    };
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (c) => ({
        '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;'
    }[c]));

    const openModal = () => {
        modal.hidden = false;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('facial-modal-open');
        window.setTimeout(() => searchInput?.focus(), 80);
    };
    const stopCamera = () => {
        scanning = false;
        if (stream) stream.getTracks().forEach((track) => track.stop());
        stream = null;
    };
    const closeCapture = () => {
        scanning = false;
        stopCamera();
        captureRoot.replaceChildren();
        capturePanel.hidden = true;
    };
    const closeModal = () => {
        closeCapture();
        modal.hidden = true;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('facial-modal-open');
    };

    openButton?.addEventListener('click', openModal);
    modal?.querySelectorAll('[data-facial-close]').forEach((el) => el.addEventListener('click', closeModal));
    closeCaptureButton?.addEventListener('click', closeCapture);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) closeModal();
    });

    const api = async (url, options = {}) => {
        const headers = new Headers(options.headers || {});
        headers.set('X-CSRF-TOKEN', config.csrfToken);
        headers.set('Accept', 'application/json');
        return fetch(url, {...options, headers, credentials:'same-origin'});
    };
    const readJson = async (response) => {
        const text = await response.text();
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.toLowerCase().includes('application/json')) {
            // Mantém o corpo completo apenas no console para facilitar o diagnóstico,
            // sem despejar HTML ou detalhes internos na interface.
            console.error('[Cadastro facial] Resposta inesperada da API', {
                status: response.status,
                url: response.url,
                redirected: response.redirected,
                contentType,
                body: text.slice(0, 1200)
            });
            if (response.redirected) {
                throw new Error('A solicitação foi redirecionada. Sua sessão pode ter expirado; atualize a página e entre novamente.');
            }
            throw new Error(`A busca não recebeu JSON do servidor (HTTP ${response.status}). Verifique a rota /api/facial/employees e o log do PHP/IIS.`);
        }
        try {
            return JSON.parse(text);
        } catch (error) {
            console.error('[Cadastro facial] JSON inválido recebido da API', {
                status: response.status,
                url: response.url,
                body: text.slice(0, 1200)
            });
            throw new Error(`A API respondeu com JSON inválido (HTTP ${response.status}). Consulte o log do PHP para identificar o erro.`);
        }
    };

    const updateSelectedEmployee = () => {
        if (!selectedEmployee) {
            selectedBox.hidden = true;
            deleteButton.disabled = true;
            return;
        }
        const status = selectedEmployee.HAS_FACE
            ? '<span class="facial-status-pill is-registered"><i class="fa-solid fa-circle-check"></i> Cadastrado</span>'
            : '<span class="facial-status-pill is-empty"><i class="fa-regular fa-circle"></i> Sem cadastro</span>';
        selectedBox.innerHTML = `<strong>${escapeHtml(selectedEmployee.NOMEFUNCIONARIO)}</strong>
            <span>Chapa ${escapeHtml(selectedEmployee.CHAPA)} · CPF ${escapeHtml(selectedEmployee.CPF_MASKED)}</span>
            ${status}`;
        selectedBox.hidden = false;
        deleteButton.disabled = !selectedEmployee.HAS_FACE;
    };

    deleteButton?.addEventListener('click', async () => {
        if (!selectedEmployee || !selectedEmployee.HAS_FACE || deleteButton.disabled) return;
        const employee = selectedEmployee;
        const confirmed = window.confirm(`Confirma a exclusão do cadastro facial de ${employee.NOMEFUNCIONARIO} (chapa ${employee.CHAPA})?\\n\\nOs descritores biométricos serão removidos. O cadastro funcional no RM não será alterado.`);
        if (!confirmed) return;

        deleteButton.disabled = true;
        startButton.disabled = true;
        setStatus('Excluindo cadastro facial...', 'info');
        try {
            const body = new URLSearchParams({
                _token: config.csrfToken,
                codcoligada: String(employee.CODCOLIGADA),
                chapa: String(employee.CHAPA)
            });
            const response = await api(config.deleteUrl, {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
                body
            });
            const data = await readJson(response);
            if (!response.ok || !data.success) throw new Error(data.message || 'Não foi possível excluir o cadastro facial.');

            selectedEmployee.HAS_FACE = false;
            updateSelectedEmployee();
            startButton.disabled = false;
            results.querySelectorAll('[data-index]').forEach((button) => {
                const item = button.querySelector('.facial-employee-status');
                const row = button._employeeData;
                if (row && Number(row.CODCOLIGADA) === Number(employee.CODCOLIGADA) && String(row.CHAPA) === String(employee.CHAPA)) {
                    if (item) item.textContent = 'SEM CADASTRO';
                }
            });
            setStatus(data.message || 'Cadastro facial excluído com sucesso.', 'success');
        } catch (error) {
            deleteButton.disabled = false;
            startButton.disabled = false;
            setStatus(error.message || 'Não foi possível excluir o cadastro facial.', 'error');
        }
    });

    const renderEmployees = (employees) => {
        if (!employees.length) {
            results.innerHTML = '<div class="facial-empty">Nenhum funcionário administrativo ativo encontrado.</div>';
            results.hidden = false;
            return;
        }
        results.innerHTML = employees.map((employee, index) => `
            <button type="button" class="facial-employee-result" data-index="${index}">
                <span class="facial-employee-avatar"><i class="fa-solid fa-user"></i></span>
                <span class="facial-employee-name">
                    <strong>${escapeHtml(employee.NOMEFUNCIONARIO)}</strong>
                    <small>Chapa ${escapeHtml(employee.CHAPA)} · ${escapeHtml(employee.NOMEFUNCAO || 'Função não informada')}</small>
                </span>
                <span class="facial-employee-status">${employee.HAS_FACE ? 'CADASTRADO' : 'SEM CADASTRO'}</span>
            </button>`).join('');
        results.hidden = false;
        results.querySelectorAll('[data-index]').forEach((button) => {
            button._employeeData = employees[Number(button.dataset.index)];
            button.addEventListener('click', () => {
                selectedEmployee = employees[Number(button.dataset.index)];
                results.querySelectorAll('.facial-employee-result').forEach((item) => item.classList.remove('is-selected'));
                button.classList.add('is-selected');
                updateSelectedEmployee();
                startButton.disabled = false;
                setStatus(selectedEmployee.HAS_FACE
                    ? 'Este funcionário já possui cadastro facial. Você pode excluí-lo ou realizar uma nova captura para atualizar a referência.'
                    : 'Funcionário sem cadastro facial. Inicie a captura para registrar a referência.', selectedEmployee.HAS_FACE ? 'warning' : 'info');
            });
        });
    };

    const searchEmployees = async () => {
        const query = searchInput.value.trim();
        if (query.length < 2) {
            setStatus('Digite pelo menos 2 caracteres para localizar o funcionário.', 'warning');
            return;
        }
        searchButton.disabled = true;
        setStatus('Localizando funcionário no RM...', 'info');
        try {
            const response = await api(`${config.searchUrl}?q=${encodeURIComponent(query)}`);
            const data = await readJson(response);
            if (!response.ok || !data.success) throw new Error(data.message || 'Falha na busca.');
            selectedEmployee = null;
            selectedBox.hidden = true;
            deleteButton.disabled = true;
            startButton.disabled = true;
            renderEmployees(data.employees || []);
            if (!data.employees?.length) setStatus('Nenhum funcionário elegível encontrado.', 'warning');
            else setStatus('', 'info');
        } catch (error) {
            setStatus(error.message || 'Não foi possível realizar a busca.', 'error');
        } finally {
            searchButton.disabled = false;
        }
    };
    searchButton?.addEventListener('click', searchEmployees);
    searchInput?.addEventListener('keydown', (event) => { if (event.key === 'Enter') searchEmployees(); });

    const loadModels = async () => {
        if (modelsReady) return;
        if (!window.faceapi) throw new Error('A biblioteca facial não carregou. Verifique a conexão com a internet.');
        setStatus('Carregando modelos de reconhecimento facial...', 'info');
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(config.modelUrl),
            faceapi.nets.faceLandmark68TinyNet.loadFromUri(config.modelUrl),
            faceapi.nets.faceRecognitionNet.loadFromUri(config.modelUrl)
        ]);
        modelsReady = true;
    };
    const renderCamera = () => {
        captureRoot.innerHTML = `
            <div class="facial-local-capture">
                <div class="facial-camera-selector">
                    <label for="facial-camera-select"><i class="fa-solid fa-camera" aria-hidden="true"></i> Câmera utilizada</label>
                    <select id="facial-camera-select" aria-label="Selecionar câmera"><option value="">Detectando câmeras...</option></select>
                </div>
                <div class="facial-video-wrap">
                    <video id="facial-camera-video" autoplay playsinline muted></video>
                    <div class="facial-guide"></div>
                    <div id="facial-camera-message" class="facial-camera-message">Posicione o rosto</div>
                </div>
                <div class="facial-progress"><div id="facial-progress-bar"></div></div>
                <div id="facial-camera-step" class="facial-step">Preparando câmera...</div>
                <p class="facial-camera-note">Serão feitas três amostras matemáticas do rosto. Nenhuma fotografia será armazenada.</p>
            </div>`;
    };
    const cameraConstraints = (deviceId = '') => ({
        audio: false,
        video: {
            ...(deviceId ? {deviceId: {exact: deviceId}} : {facingMode: {ideal: 'user'}}),
            width: {ideal: 1280},
            height: {ideal: 720}
        }
    });

    const releaseCameraStream = () => {
        if (stream) stream.getTracks().forEach((track) => track.stop());
        stream = null;
    };

    const refreshCameraOptions = async (activeDeviceId = '') => {
        const select = $('facial-camera-select');
        if (!select || !navigator.mediaDevices?.enumerateDevices) return;
        const devices = (await navigator.mediaDevices.enumerateDevices())
            .filter((device) => device.kind === 'videoinput');
        select.replaceChildren();
        if (!devices.length) {
            select.add(new Option('Nenhuma câmera encontrada', ''));
            select.disabled = true;
            return;
        }
        devices.forEach((device, index) => {
            const option = new Option(device.label || `Câmera ${index + 1}`, device.deviceId);
            select.add(option);
        });
        const current = activeDeviceId || devices[0].deviceId;
        select.value = current;
        selectedCameraId = select.value;
        select.disabled = devices.length < 2;
    };

    const startCamera = async (deviceId = '') => {
        if (!navigator.mediaDevices?.getUserMedia) throw new Error('Este navegador não disponibiliza acesso à câmera. Use HTTPS.');
        releaseCameraStream();
        stream = await navigator.mediaDevices.getUserMedia(cameraConstraints(deviceId));
        const video = $('facial-camera-video');
        video.srcObject = stream;
        await video.play();
        const activeTrack = stream.getVideoTracks()[0];
        const actualDeviceId = activeTrack?.getSettings?.().deviceId || deviceId;
        await refreshCameraOptions(actualDeviceId);
        return video;
    };

    const switchCamera = async (deviceId) => {
        if (!deviceId || switchingCamera || deviceId === selectedCameraId) return;
        switchingCamera = true;
        const select = $('facial-camera-select');
        const step = $('facial-camera-step');
        if (select) select.disabled = true;
        if (step) step.textContent = 'Trocando câmera...';
        try {
            await startCamera(deviceId);
            if (step) step.textContent = 'Câmera alterada. Continue posicionando o rosto.';
        } catch (error) {
            const previousDeviceId = selectedCameraId;
            try {
                if (previousDeviceId) await startCamera(previousDeviceId);
            } catch (restoreError) {
                console.error('[Cadastro facial] Não foi possível restaurar a câmera anterior.', restoreError);
            }
            if (select) select.value = selectedCameraId || previousDeviceId;
            if (step) step.textContent = 'Não foi possível trocar a câmera. A câmera anterior foi mantida quando possível.';
            setStatus(error.message || 'Não foi possível acessar a câmera selecionada.', 'error');
        } finally {
            switchingCamera = false;
            if (select) select.disabled = select.options.length < 2;
        }
    };
    const detect = (video) => {
        const options = new faceapi.TinyFaceDetectorOptions({inputSize:320,scoreThreshold:.55});
        return faceapi.detectSingleFace(video,options).withFaceLandmarks(true).withFaceDescriptor();
    };
    const toBase64 = (descriptor) => {
        const bytes = new Uint8Array(descriptor.buffer.slice(descriptor.byteOffset,descriptor.byteOffset+descriptor.byteLength));
        let binary = '';
        for (let i=0;i<bytes.length;i+=0x8000) binary += String.fromCharCode(...bytes.subarray(i,i+0x8000));
        return btoa(binary);
    };
    const wait = (ms) => new Promise((resolve) => window.setTimeout(resolve,ms));

    const captureThree = async (video) => {
        const message = $('facial-camera-message');
        const step = $('facial-camera-step');
        const bar = $('facial-progress-bar');
        const descriptors = [];
        scanning = true;
        let stableAt = null;

        while (scanning && descriptors.length < 3) {
            if (!scanBusy) {
                scanBusy = true;
                try {
                    const result = await detect(video);
                    if (result) {
                        if (stableAt === null) stableAt = performance.now();
                        const elapsed = performance.now()-stableAt;
                        bar.style.width = `${Math.min(100,elapsed/700*100)}%`;
                        message.textContent = 'Mantenha o rosto centralizado...';
                        step.textContent = `Captura ${descriptors.length+1} de 3`;
                        if (elapsed >= 700) {
                            descriptors.push(toBase64(result.descriptor));
                            stableAt = null;
                            bar.style.width = '0%';
                            message.textContent = descriptors.length < 3 ? 'Mude levemente a posição do rosto...' : 'Capturas concluídas';
                            await wait(650);
                        }
                    } else {
                        stableAt = null;
                        bar.style.width = '0%';
                        message.textContent = 'Posicione o rosto dentro da marcação...';
                    }
                } finally { scanBusy = false; }
            }
            await wait(90);
        }
        scanning = false;
        if (descriptors.length !== 3) throw new Error('A captura foi interrompida antes de concluir as três amostras.');
        return descriptors;
    };

    captureRoot?.addEventListener('change', (event) => {
        if (event.target?.id === 'facial-camera-select') switchCamera(event.target.value);
    });

    startButton?.addEventListener('click', async () => {
        if (!selectedEmployee) return;
        startButton.disabled = true;
        capturePanel.hidden = false;
        renderCamera();
        try {
            await loadModels();
            const video = await startCamera(selectedCameraId);
            const descriptors = await captureThree(video);
            setStatus('Capturas concluídas. Salvando os descritores no SQL Server...', 'info');
            const body = new URLSearchParams({
                _token: config.csrfToken,
                codcoligada: String(selectedEmployee.CODCOLIGADA),
                chapa: String(selectedEmployee.CHAPA),
                descriptors: JSON.stringify(descriptors)
            });
            const response = await api(config.registerUrl,{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
                body
            });
            const data = await readJson(response);
            if (!response.ok || !data.success) throw new Error(data.message || 'Não foi possível salvar o cadastro facial.');
            closeCapture();
            setStatus(`Cadastro facial concluído para ${data.employee}.`, 'success');
            selectedEmployee = null;
            selectedBox.hidden = true;
            deleteButton.disabled = true;
            startButton.disabled = true;
            await searchEmployees();
            setStatus(`Cadastro facial concluído para ${data.employee}.`, 'success');
        } catch (error) {
            closeCapture();
            startButton.disabled = !selectedEmployee;
            setStatus(error.message || 'Não foi possível concluir o cadastro facial.', 'error');
        }
    });
})();
