
(function () {
    // instant-capture.js - add quick record/take-photo buttons for the upload form
    const ready = () => {
        const input = document.getElementById('inlineUploadFiles');
        const form = document.getElementById('inlineUploadForm') || input?.closest('form');
        if (!input || !form) return;

        let heroActions = form.querySelector('.hero-actions');
        if (!heroActions) {
            heroActions = document.createElement('div');
            heroActions.className = 'hero-actions';
            form.insertBefore(heroActions, form.firstChild);
        }

        if (document.getElementById('instantRecordBtn') || document.getElementById('instantPhotoBtn') || document.getElementById('instantVideoBtn')) {
            return;
        }

        // create buttons
        const recordBtn = document.createElement('button');
        recordBtn.type = 'button';
        recordBtn.className = 'ui-btn';
        recordBtn.id = 'instantRecordBtn';
        recordBtn.textContent = 'Record audio';

        const photoBtn = document.createElement('button');
        photoBtn.type = 'button';
        photoBtn.className = 'ui-btn';
        photoBtn.id = 'instantPhotoBtn';
        photoBtn.textContent = 'Take photo';

        const videoBtn = document.createElement('button');
        videoBtn.type = 'button';
        videoBtn.className = 'ui-btn';
        videoBtn.id = 'instantVideoBtn';
        videoBtn.textContent = 'Record video';

        heroActions.insertBefore(photoBtn, heroActions.firstChild);
        heroActions.insertBefore(videoBtn, heroActions.firstChild);
        heroActions.insertBefore(recordBtn, heroActions.firstChild);

        const allowedExt = new Set(
            String(document.body?.dataset?.mediaExtensions || '')
                .split(',')
                .map((value) => value.trim().toLowerCase())
                .filter(Boolean)
        );
        const maxUploadBytes = Number(document.body?.dataset?.maxUploadFileSizeBytes || '104857600');

        const extFromName = (name) => (String(name || '').split('.').pop() || '').toLowerCase();
        const isAllowedFile = (file) => {
            const ext = extFromName(file.name);
            if (!ext || (allowedExt.size > 0 && !allowedExt.has(ext))) return false;
            if (Number.isFinite(maxUploadBytes) && maxUploadBytes > 0 && file.size > maxUploadBytes) return false;
            return true;
        };

        // helper to add File to input.files via DataTransfer
        const addFileToInput = (file) => {
            if (!isAllowedFile(file)) {
                alert('Captured file is not allowed or too large.');
                return false;
            }
            const dt = new DataTransfer();
            // preserve existing files
            const existing = Array.from(input.files || []);
            for (const f of existing) dt.items.add(f);
            dt.items.add(file);
            input.files = dt.files;
            // trigger change so preview logic picks it up
            input.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        };

        const ensureLocationInputs = () => {
            let coordsInput = form.querySelector('input[name="instant_location_coords"]');
            let labelInput = form.querySelector('input[name="instant_location_label"]');
            if (!coordsInput) {
                coordsInput = document.createElement('input');
                coordsInput.type = 'hidden';
                coordsInput.name = 'instant_location_coords';
                form.appendChild(coordsInput);
            }
            if (!labelInput) {
                labelInput = document.createElement('input');
                labelInput.type = 'hidden';
                labelInput.name = 'instant_location_label';
                form.appendChild(labelInput);
            }
            return { coordsInput, labelInput };
        };

        const clearLocationSelection = () => {
            const { coordsInput, labelInput } = ensureLocationInputs();
            coordsInput.value = '';
            labelInput.value = '';
        };

        const saveLocationSelection = (place) => {
            const { coordsInput, labelInput } = ensureLocationInputs();
            if (!place) {
                coordsInput.value = '';
                labelInput.value = '';
                return;
            }
            coordsInput.value = `${Number(place.lat).toFixed(6)}, ${Number(place.lon).toFixed(6)}`;
            labelInput.value = String(place.label || '').trim();
        };

        // Audio recording
        let mediaRecorder = null;
        let audioChunks = [];
        let audioStream = null;
        let audioContext = null;
        let analyser = null;
        let visualizerFrame = 0;
        let visualizerWrap = null;

        const stopVisualizer = () => {
            if (visualizerFrame) {
                cancelAnimationFrame(visualizerFrame);
                visualizerFrame = 0;
            }
            if (audioContext) {
                audioContext.close().catch(() => { });
                audioContext = null;
            }
            analyser = null;
            if (visualizerWrap) {
                visualizerWrap.remove();
                visualizerWrap = null;
            }
        };

        const startVisualizer = (stream) => {
            stopVisualizer();

            visualizerWrap = document.createElement('div');
            visualizerWrap.className = 'recording-visualizer';

            const label = document.createElement('div');
            label.className = 'recording-visualizer-label';
            label.textContent = 'Recording…';

            const canvas = document.createElement('canvas');
            canvas.className = 'recording-visualizer-canvas';
            canvas.width = 480;
            canvas.height = 64;

            visualizerWrap.appendChild(label);
            visualizerWrap.appendChild(canvas);
            form.appendChild(visualizerWrap);

            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;

            audioContext = new AudioCtx();
            const source = audioContext.createMediaStreamSource(stream);
            analyser = audioContext.createAnalyser();
            analyser.fftSize = 256;
            source.connect(analyser);

            const ctx = canvas.getContext('2d');
            if (!ctx) return;

            const bufferLength = analyser.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);

            const draw = () => {
                if (!analyser) return;

                analyser.getByteFrequencyData(dataArray);
                ctx.clearRect(0, 0, canvas.width, canvas.height);

                const barCount = 32;
                const step = Math.max(1, Math.floor(bufferLength / barCount));
                const barWidth = canvas.width / barCount;

                for (let i = 0; i < barCount; i++) {
                    const value = dataArray[i * step] || 0;
                    const height = Math.max(2, (value / 255) * (canvas.height - 8));
                    const x = i * barWidth;
                    const y = canvas.height - height;
                    ctx.fillStyle = 'currentColor';
                    ctx.fillRect(x + 1, y, Math.max(1, barWidth - 2), height);
                }

                visualizerFrame = requestAnimationFrame(draw);
            };

            draw();
        };

        recordBtn.addEventListener('click', async () => {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Audio recording not supported in this browser.');
                return;
            }

            if (mediaRecorder && mediaRecorder.state === 'recording') {
                // stop
                mediaRecorder.stop();
                return;
            }

            try {
                audioStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (e) {
                alert('Permission denied or no microphone available.');
                return;
            }

            if (typeof MediaRecorder === 'undefined') {
                for (const t of audioStream.getTracks()) t.stop();
                audioStream = null;
                alert('Audio recording not supported in this browser.');
                return;
            }

            const mp4Candidates = ['audio/mp4;codecs=mp4a.40.2', 'audio/mp4'];
            const fallbackCandidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/ogg'];

            const recorderMime = mp4Candidates.find((type) => {
                return typeof MediaRecorder.isTypeSupported === 'function'
                    ? MediaRecorder.isTypeSupported(type)
                    : false;
            }) || fallbackCandidates.find((type) => {
                return typeof MediaRecorder.isTypeSupported === 'function'
                    ? MediaRecorder.isTypeSupported(type)
                    : false;
            }) || '';

            if (!recorderMime) {
                for (const t of audioStream.getTracks()) t.stop();
                audioStream = null;
                alert('Audio recording is not supported in this browser.');
                return;
            }

            mediaRecorder = new MediaRecorder(audioStream, { mimeType: recorderMime });
            audioChunks = [];
            startVisualizer(audioStream);
            recordBtn.classList.add('ui-btn-record-live', 'active');

            const stopBtn = document.createElement('button');
            stopBtn.type = 'button';
            stopBtn.className = 'ui-btn ui-btn-record-stop';
            stopBtn.textContent = 'Stop recording';
            stopBtn.id = 'stopRecordBtn';
            heroActions.appendChild(stopBtn);

            stopBtn.addEventListener('click', () => {
                if (mediaRecorder && mediaRecorder.state === 'recording') mediaRecorder.stop();
            });

            mediaRecorder.addEventListener('dataavailable', (e) => {
                if (e.data && e.data.size) audioChunks.push(e.data);
            });

            mediaRecorder.addEventListener('stop', () => {
                const blobType = recorderMime.includes('mp4') ? 'audio/mp4' : recorderMime;
                const blob = new Blob(audioChunks, { type: blobType });
                const filename = 'recording_' + Date.now() + (
                    recorderMime.includes('ogg') ? '.ogg'
                        : recorderMime.includes('webm') ? '.webm'
                            : '.m4a'
                );
                const file = new File([blob], filename, { type: blobType });
                addFileToInput(file);

                // cleanup
                stopVisualizer();
                if (audioStream) {
                    for (const t of audioStream.getTracks()) t.stop();
                    audioStream = null;
                }
                mediaRecorder = null;
                recordBtn.classList.remove('ui-btn-record-live', 'active');
                stopBtn.remove();
            });

            mediaRecorder.start();
        });

        const openCaptureModal = async (mode) => {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Camera capture is not supported in this browser.');
                return;
            }

            let camStream = null;
            let recorder = null;
            let recordTimer = 0;
            let elapsedTimer = 0;
            let elapsedSec = 0;
            let previewFile = null;
            let previewUrl = '';
            let locationQueryTimer = 0;
            let locationAbortController = null;
            let selectedPlace = null;
            let currentFacingMode = 'environment';
            let hasMultipleCameras = false;
            let preferredDeviceId = '';
            let keydownHandler = null;

            const overlay = document.createElement('div');
            overlay.className = 'instant-capture-overlay';

            const wrapper = document.createElement('div');
            wrapper.className = 'instant-capture-modal';
            wrapper.setAttribute('role', 'dialog');
            wrapper.setAttribute('aria-modal', 'true');

            const frame = document.createElement('div');
            frame.className = 'instant-capture-frame';

            const liveVideo = document.createElement('video');
            liveVideo.className = 'instant-capture-video';
            liveVideo.autoplay = true;
            liveVideo.playsInline = true;

            const imagePreview = document.createElement('img');
            imagePreview.className = 'instant-capture-image';
            imagePreview.alt = 'Captured photo preview';
            imagePreview.hidden = true;

            const videoPreview = document.createElement('video');
            videoPreview.className = 'instant-capture-video-preview';
            videoPreview.controls = true;
            videoPreview.playsInline = true;
            videoPreview.hidden = true;

            frame.appendChild(liveVideo);
            frame.appendChild(imagePreview);
            frame.appendChild(videoPreview);

            const recordingInfo = document.createElement('div');
            recordingInfo.className = 'instant-capture-recording-note';
            recordingInfo.hidden = true;
            recordingInfo.textContent = 'Recording 0s / 10s';

            const locationWrap = document.createElement('div');
            locationWrap.className = 'instant-capture-location';
            locationWrap.hidden = true;

            const locationLabel = document.createElement('label');
            locationLabel.className = 'instant-capture-location-label';
            locationLabel.textContent = 'Add location (optional)';

            const locationInput = document.createElement('input');
            locationInput.type = 'text';
            locationInput.className = 'upload-auth-input instant-capture-location-input';
            locationInput.placeholder = 'Search address or place';
            locationInput.autocomplete = 'off';
            locationInput.spellcheck = false;

            const suggestions = document.createElement('div');
            suggestions.className = 'instant-capture-suggestions';
            suggestions.hidden = true;

            locationWrap.appendChild(locationLabel);
            locationWrap.appendChild(locationInput);
            locationWrap.appendChild(suggestions);

            const controls = document.createElement('div');
            controls.className = 'instant-capture-controls';

            const primaryBtn = document.createElement('button');
            primaryBtn.type = 'button';
            primaryBtn.className = 'ui-btn';
            primaryBtn.textContent = mode === 'video' ? 'Start video' : 'Take photo';

            const stopBtn = mode === 'video' ? document.createElement('button') : null;
            if (stopBtn) {
                stopBtn.type = 'button';
                stopBtn.className = 'ui-btn';
                stopBtn.textContent = 'Stop';
                stopBtn.hidden = true;
            }

            const keepBtn = document.createElement('button');
            keepBtn.type = 'button';
            keepBtn.className = 'ui-btn ui-btn-strong';
            keepBtn.textContent = 'Keep';
            keepBtn.hidden = true;

            const retakeBtn = document.createElement('button');
            retakeBtn.type = 'button';
            retakeBtn.className = 'ui-btn';
            retakeBtn.textContent = 'Retake';
            retakeBtn.hidden = true;

            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'ui-btn';
            closeBtn.textContent = 'Close';

            const flipBtn = document.createElement('button');
            flipBtn.type = 'button';
            flipBtn.className = 'ui-btn';
            flipBtn.textContent = 'Flip camera';
            flipBtn.hidden = true;

            const cameraSelect = document.createElement('select');
            cameraSelect.className = 'upload-auth-input instant-capture-camera-select';
            cameraSelect.hidden = true;
            cameraSelect.setAttribute('aria-label', 'Choose camera');

            const setHidden = (el, hidden) => {
                if (!el) return;
                el.hidden = hidden;
                el.style.display = hidden ? 'none' : '';
            };

            controls.appendChild(primaryBtn);
            if (stopBtn) controls.appendChild(stopBtn);
            controls.appendChild(keepBtn);
            controls.appendChild(retakeBtn);
            controls.appendChild(cameraSelect);
            controls.appendChild(flipBtn);
            controls.appendChild(closeBtn);

            wrapper.appendChild(frame);
            wrapper.appendChild(recordingInfo);
            wrapper.appendChild(locationWrap);
            wrapper.appendChild(controls);
            overlay.appendChild(wrapper);
            document.body.appendChild(overlay);

            const stopStream = () => {
                if (!camStream) return;
                for (const t of camStream.getTracks()) t.stop();
                camStream = null;
            };

            const stopRecordTimers = () => {
                if (recordTimer) {
                    clearTimeout(recordTimer);
                    recordTimer = 0;
                }
                if (elapsedTimer) {
                    clearInterval(elapsedTimer);
                    elapsedTimer = 0;
                }
                elapsedSec = 0;
                recordingInfo.hidden = true;
            };

            const clearSuggestions = () => {
                suggestions.innerHTML = '';
                suggestions.hidden = true;
            };

            const renderSuggestions = (items) => {
                suggestions.innerHTML = '';
                if (!items.length) {
                    suggestions.hidden = true;
                    return;
                }
                items.forEach((item) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'instant-capture-suggestion';
                    button.textContent = item.label;
                    button.addEventListener('click', () => {
                        selectedPlace = item;
                        locationInput.value = item.label;
                        clearSuggestions();
                    });
                    suggestions.appendChild(button);
                });
                suggestions.hidden = false;
            };

            const fetchSuggestions = async (query) => {
                if (locationAbortController) {
                    locationAbortController.abort();
                }
                locationAbortController = new AbortController();
                try {
                    const url = `https://photon.komoot.io/api/?limit=6&q=${encodeURIComponent(query)}`;
                    const response = await fetch(url, { signal: locationAbortController.signal });
                    if (!response.ok) return;
                    const data = await response.json();
                    const features = Array.isArray(data?.features) ? data.features : [];
                    const mapped = features
                        .map((feature) => {
                            const coords = feature?.geometry?.coordinates;
                            const props = feature?.properties || {};
                            if (!Array.isArray(coords) || coords.length < 2) return null;
                            const lon = Number(coords[0]);
                            const lat = Number(coords[1]);
                            if (!Number.isFinite(lat) || !Number.isFinite(lon)) return null;
                            const labelParts = [props.name, props.street, props.city, props.state, props.country].filter(Boolean);
                            const label = labelParts.length ? labelParts.join(', ') : `${lat.toFixed(5)}, ${lon.toFixed(5)}`;
                            return { lat, lon, label };
                        })
                        .filter(Boolean)
                        .slice(0, 6);
                    renderSuggestions(mapped);
                } catch (e) {
                    if (e?.name !== 'AbortError') {
                        clearSuggestions();
                    }
                }
            };

            const cleanup = () => {
                stopRecordTimers();
                if (recorder && recorder.state !== 'inactive') {
                    recorder.stop();
                }
                recorder = null;
                stopStream();
                if (locationQueryTimer) {
                    clearTimeout(locationQueryTimer);
                    locationQueryTimer = 0;
                }
                if (locationAbortController) {
                    locationAbortController.abort();
                    locationAbortController = null;
                }
                if (previewUrl) {
                    URL.revokeObjectURL(previewUrl);
                    previewUrl = '';
                }
                if (keydownHandler) {
                    document.removeEventListener('keydown', keydownHandler);
                    keydownHandler = null;
                }
                overlay.remove();
            };

            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) cleanup();
            });

            keydownHandler = (event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    cleanup();
                    return;
                }

                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }

                const target = event.target;
                const tagName = target && typeof target.tagName === 'string' ? target.tagName.toLowerCase() : '';
                if (tagName === 'input' || tagName === 'select' || tagName === 'textarea' || (target && target.isContentEditable)) {
                    return;
                }

                const actionBtn = !keepBtn.hidden
                    ? keepBtn
                    : stopBtn && !stopBtn.hidden
                        ? stopBtn
                        : !primaryBtn.hidden
                            ? primaryBtn
                            : null;
                if (!actionBtn) return;
                event.preventDefault();
                actionBtn.click();
            };
            document.addEventListener('keydown', keydownHandler);

            const refreshCameraControls = async () => {
                if (!navigator.mediaDevices || typeof navigator.mediaDevices.enumerateDevices !== 'function') {
                    hasMultipleCameras = false;
                    setHidden(flipBtn, true);
                    setHidden(cameraSelect, true);
                    return;
                }

                try {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    const videoInputs = devices.filter((d) => d.kind === 'videoinput');
                    hasMultipleCameras = videoInputs.length > 1;
                    const isCoarsePointer = typeof window.matchMedia === 'function'
                        ? window.matchMedia('(pointer: coarse)').matches
                        : false;
                    const frontPattern = /(front|user|facetime|selfie)/i;
                    const rearPattern = /(back|rear|environment|world)/i;
                    const hasFrontCamera = videoInputs.some((d) => frontPattern.test(String(d.label || '')));
                    const hasRearCamera = videoInputs.some((d) => rearPattern.test(String(d.label || '')));
                    const canFlipFacingMode = hasMultipleCameras && isCoarsePointer && hasFrontCamera && hasRearCamera;

                    cameraSelect.innerHTML = '';
                    if (!hasMultipleCameras) {
                        setHidden(cameraSelect, true);
                        setHidden(flipBtn, true);
                        return;
                    }

                    const activeTrack = camStream && camStream.getVideoTracks ? camStream.getVideoTracks()[0] : null;
                    const activeDeviceId = activeTrack && typeof activeTrack.getSettings === 'function'
                        ? String(activeTrack.getSettings().deviceId || '')
                        : '';

                    videoInputs.forEach((device, index) => {
                        const option = document.createElement('option');
                        option.value = device.deviceId || '';
                        option.textContent = device.label || `Camera ${index + 1}`;
                        cameraSelect.appendChild(option);
                    });

                    const wantedDeviceId = preferredDeviceId || activeDeviceId || (videoInputs[0] && videoInputs[0].deviceId) || '';
                    if (wantedDeviceId) {
                        cameraSelect.value = wantedDeviceId;
                        preferredDeviceId = wantedDeviceId;
                    }

                    // Keep camera switching controls only in live camera mode.
                    if (!liveVideo.hidden) {
                        setHidden(cameraSelect, false);
                        setHidden(flipBtn, !canFlipFacingMode);
                    }
                } catch (e) {
                    hasMultipleCameras = false;
                    setHidden(cameraSelect, true);
                    setHidden(flipBtn, true);
                }
            };

            const openCamera = async () => {
                const wantsAudio = mode === 'video';
                const tryGetStream = async (includeAudio) => {
                    const candidates = [];

                    if (preferredDeviceId) {
                        candidates.push({
                            video: { deviceId: { exact: preferredDeviceId } },
                            audio: includeAudio,
                        });
                    }

                    candidates.push({
                        video: { facingMode: { ideal: currentFacingMode } },
                        audio: includeAudio,
                    });

                    candidates.push({ video: true, audio: includeAudio });

                    for (const mediaConstraints of candidates) {
                        try {
                            return await navigator.mediaDevices.getUserMedia(mediaConstraints);
                        } catch (e) {
                            // Try the next constraint profile.
                        }
                    }
                    return null;
                };

                camStream = await tryGetStream(wantsAudio);
                if (!camStream && wantsAudio) {
                    camStream = await tryGetStream(false);
                }

                if (!camStream) {
                    alert('Permission denied or no camera available.');
                    overlay.remove();
                    return false;
                }

                liveVideo.srcObject = camStream;
                liveVideo.hidden = false;
                imagePreview.hidden = true;
                videoPreview.hidden = true;
                locationWrap.hidden = true;
                setHidden(primaryBtn, false);
                setHidden(stopBtn, true);
                setHidden(keepBtn, true);
                setHidden(retakeBtn, true);
                selectedPlace = null;
                locationInput.value = '';
                clearSuggestions();
                previewFile = null;
                stopRecordTimers();

                await refreshCameraControls();
                return true;
            };

            locationInput.addEventListener('input', () => {
                selectedPlace = null;
                const query = locationInput.value.trim();
                if (locationQueryTimer) {
                    clearTimeout(locationQueryTimer);
                }
                if (query.length < 3) {
                    clearSuggestions();
                    return;
                }
                locationQueryTimer = window.setTimeout(() => {
                    fetchSuggestions(query);
                }, 220);
            });

            closeBtn.addEventListener('click', cleanup);

            const capturePhoto = () => {
                const srcW = liveVideo.videoWidth || 1280;
                const srcH = liveVideo.videoHeight || 720;
                const maxEdge = 1600;
                const scale = Math.min(1, maxEdge / Math.max(srcW, srcH));
                const outW = Math.max(2, Math.round(srcW * scale));
                const outH = Math.max(2, Math.round(srcH * scale));

                const canvas = document.createElement('canvas');
                canvas.width = outW;
                canvas.height = outH;
                const ctx = canvas.getContext('2d');
                if (!ctx) return;

                ctx.drawImage(liveVideo, 0, 0, srcW, srcH, 0, 0, outW, outH);
                canvas.toBlob((blob) => {
                    if (!blob) return;
                    previewFile = new File([blob], 'photo_' + Date.now() + '.jpg', { type: 'image/jpeg' });

                    if (previewUrl) {
                        URL.revokeObjectURL(previewUrl);
                    }
                    previewUrl = URL.createObjectURL(blob);
                    imagePreview.src = previewUrl;
                    imagePreview.hidden = false;
                    liveVideo.hidden = true;
                    setHidden(cameraSelect, true);
                    setHidden(flipBtn, true);
                    stopStream();
                    locationWrap.hidden = false;
                    setHidden(primaryBtn, true);
                    setHidden(stopBtn, true);
                    setHidden(keepBtn, false);
                    setHidden(retakeBtn, false);
                }, 'image/jpeg', 0.9);
            };

            const startVideoRecording = () => {
                if (!camStream || typeof MediaRecorder === 'undefined') {
                    alert('Video recording is not supported in this browser.');
                    return;
                }

                const chunks = [];
                const candidates = [
                    'video/mp4;codecs=avc1.42E01E,mp4a.40.2',
                    'video/mp4',
                    'video/webm;codecs=vp9,opus',
                    'video/webm;codecs=vp8,opus',
                    'video/webm',
                    'video/ogg;codecs=theora,opus',
                ];
                const mimeType = candidates.find((type) => {
                    return typeof MediaRecorder.isTypeSupported === 'function'
                        ? MediaRecorder.isTypeSupported(type)
                        : false;
                }) || '';

                if (!mimeType) {
                    alert('Video recording is not supported on this device/browser.');
                    return;
                }

                try {
                    recorder = mimeType ? new MediaRecorder(camStream, { mimeType }) : new MediaRecorder(camStream);
                } catch (e) {
                    alert('Video recording is not supported in this browser.');
                    return;
                }

                recorder.addEventListener('dataavailable', (event) => {
                    if (event.data && event.data.size) chunks.push(event.data);
                });

                recorder.addEventListener('stop', () => {
                    stopRecordTimers();
                    const type = (recorder && recorder.mimeType) ? recorder.mimeType : 'video/mp4';
                    const ext = type.includes('webm') ? 'webm' : type.includes('ogg') ? 'ogv' : 'mp4';
                    const blob = new Blob(chunks, { type });
                    previewFile = new File([blob], 'video_' + Date.now() + '.' + ext, { type });

                    if (previewUrl) {
                        URL.revokeObjectURL(previewUrl);
                    }
                    previewUrl = URL.createObjectURL(blob);
                    videoPreview.src = previewUrl;
                    videoPreview.preload = 'metadata';
                    videoPreview.load();
                    videoPreview.hidden = false;
                    liveVideo.hidden = true;
                    setHidden(cameraSelect, true);
                    setHidden(flipBtn, true);
                    stopStream();

                    locationWrap.hidden = false;
                    setHidden(primaryBtn, true);
                    setHidden(stopBtn, true);
                    setHidden(keepBtn, false);
                    setHidden(retakeBtn, false);
                    recorder = null;
                });

                recorder.start();
                elapsedSec = 0;
                recordingInfo.textContent = 'Recording 0s / 10s';
                recordingInfo.hidden = false;
                setHidden(primaryBtn, true);
                setHidden(keepBtn, true);
                setHidden(retakeBtn, true);
                setHidden(cameraSelect, true);
                setHidden(flipBtn, true);
                setHidden(stopBtn, false);

                elapsedTimer = window.setInterval(() => {
                    elapsedSec += 1;
                    recordingInfo.textContent = `Recording ${elapsedSec}s / 10s`;
                }, 1000);

                recordTimer = window.setTimeout(() => {
                    if (recorder && recorder.state === 'recording') {
                        recorder.stop();
                    }
                }, 10000);
            };

            primaryBtn.addEventListener('click', () => {
                if (mode === 'video') {
                    startVideoRecording();
                    return;
                }
                capturePhoto();
            });

            if (stopBtn) {
                stopBtn.addEventListener('click', () => {
                    if (recorder && recorder.state === 'recording') {
                        recorder.stop();
                    }
                });
            }

            flipBtn.addEventListener('click', async () => {
                if (!hasMultipleCameras) return;
                if (recorder && recorder.state === 'recording') {
                    return;
                }
                currentFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
                preferredDeviceId = '';
                stopStream();
                await openCamera();
            });

            cameraSelect.addEventListener('change', async () => {
                const nextDeviceId = String(cameraSelect.value || '').trim();
                if (!nextDeviceId || nextDeviceId === preferredDeviceId) return;
                if (recorder && recorder.state === 'recording') {
                    cameraSelect.value = preferredDeviceId;
                    return;
                }
                preferredDeviceId = nextDeviceId;
                stopStream();
                await openCamera();
            });

            retakeBtn.addEventListener('click', async () => {
                previewFile = null;
                if (previewUrl) {
                    URL.revokeObjectURL(previewUrl);
                    previewUrl = '';
                }
                await openCamera();
            });

            keepBtn.addEventListener('click', () => {
                if (!previewFile) return;
                const added = addFileToInput(previewFile);
                if (!added) return;

                if (selectedPlace) {
                    saveLocationSelection(selectedPlace);
                } else {
                    clearLocationSelection();
                }
                cleanup();
            });

            const opened = await openCamera();
            if (!opened) {
                clearLocationSelection();
                return;
            }

            closeBtn.focus();
        };

        photoBtn.addEventListener('click', () => {
            openCaptureModal('photo');
        });

        videoBtn.addEventListener('click', () => {
            openCaptureModal('video');
        });
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready);
    else ready();

})();
