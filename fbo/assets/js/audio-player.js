(function () {
    // audio-player.js - isolated file so it can be removed later
    const EMPTY_COVER_DATA_URI = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
    const AUDIO_EXT = new Set(['mp3', 'wav', 'flac', 'ogg', 'm4a', 'webm']);

    // minimal ID3v2 parser to extract TIT2 and APIC frames (best-effort)
    function readSynchsafeInt(view, offset) {
        return (view.getUint8(offset) & 0x7f) << 21 |
            (view.getUint8(offset + 1) & 0x7f) << 14 |
            (view.getUint8(offset + 2) & 0x7f) << 7 |
            (view.getUint8(offset + 3) & 0x7f);
    }

    function readUint32(view, offset) {
        return view.getUint32(offset, false);
    }

    function decodeString(bytes, encoding) {
        try {
            if (encoding === 0) return new TextDecoder('iso-8859-1').decode(bytes);
            if (encoding === 1) return new TextDecoder('utf-16').decode(bytes);
            if (encoding === 2) return new TextDecoder('utf-16be').decode(bytes);
            return new TextDecoder('utf-8').decode(bytes);
        } catch (e) {
            return '';
        }
    }

    function parseID3v2(buffer) {
        const view = new DataView(buffer);
        if (view.byteLength < 10) return {};
        if (String.fromCharCode(view.getUint8(0), view.getUint8(1), view.getUint8(2)) !== 'ID3') return {};
        const ver = view.getUint8(3);
        const flags = view.getUint8(5);
        const tagSize = readSynchsafeInt(view, 6);
        let offset = 10;
        const end = 10 + tagSize;
        const result = {};

        while (offset + 10 <= Math.min(end, view.byteLength)) {
            const id = String.fromCharCode(
                view.getUint8(offset), view.getUint8(offset + 1), view.getUint8(offset + 2), view.getUint8(offset + 3)
            );
            let frameSize = 0;
            if (ver >= 4) frameSize = readSynchsafeInt(view, offset + 4);
            else frameSize = readUint32(view, offset + 4);
            const frameFlags = view.getUint16(offset + 8, false);
            offset += 10;
            if (frameSize <= 0 || offset + frameSize > view.byteLength) break;

            try {
                if (id === 'TIT2') {
                    const encoding = view.getUint8(offset);
                    const bytes = new Uint8Array(buffer, offset + 1, frameSize - 1);
                    result.title = decodeString(bytes, encoding);
                }

                if (id === 'APIC') {
                    const enc = view.getUint8(offset);
                    let p = offset + 1;
                    let mime = '';
                    while (p < offset + frameSize && view.getUint8(p) !== 0) {
                        mime += String.fromCharCode(view.getUint8(p));
                        p++;
                    }
                    p++; // skip 0
                    const picType = view.getUint8(p); p++;
                    while (p < offset + frameSize && view.getUint8(p) !== 0) p++;
                    p++;
                    const imgStart = p;
                    const imgLen = offset + frameSize - imgStart;
                    if (imgLen > 0) {
                        const imgBytes = new Uint8Array(buffer, imgStart, imgLen);
                        result.image = { mime: mime || 'image/jpeg', data: imgBytes };
                    }
                }
            } catch (e) {
                // ignore individual frame errors
            }

            offset += frameSize;
        }

        return result;
    }

    // Utility: check for an existing image with same base name
    async function findCoverFor(src) {
        try {
            const base = src.replace(/\.[^.?#]+(\?.*)?$/, '');
            const exts = ['jpg', 'png', 'webp', 'jpeg'];
            for (const e of exts) {
                const url = base + '.' + e;
                const resp = await fetch(url, { method: 'HEAD' });
                if (resp.ok) return url;
            }
        } catch (err) {
            // ignore
        }
        return null;
    }

    // Build a shared audio element to keep playback in background and show native controls
    const globalAudio = document.createElement('audio');
    globalAudio.id = 'globalAudio';
    globalAudio.preload = 'metadata';
    globalAudio.crossOrigin = 'anonymous';
    globalAudio.controls = false;
    globalAudio.style.position = 'absolute';
    globalAudio.style.left = '-9999px';
    globalAudio.style.width = '1px';
    globalAudio.style.height = '1px';
    globalAudio.style.opacity = '0';
    globalAudio.style.pointerEvents = 'none';
    try { globalAudio.setAttribute('controlsList', 'nodownload'); } catch (e) { }

    const isChromeDesktop = (typeof navigator !== 'undefined') && /Chrome\//.test(navigator.userAgent) && !/Mobile|Android/.test(navigator.userAgent) && !/Edg\//.test(navigator.userAgent) && !/OPR\//.test(navigator.userAgent);
    if (isChromeDesktop) {
        globalAudio.addEventListener('contextmenu', (ev) => {
            ev.preventDefault();
        }, { passive: false });
    }

    let playlist = [];
    let order = [];
    let currentIndex = 0;
    let audioCtx = null;
    let analyser = null;
    let mediaSource = null;
    let analyserData = null;
    let reactiveFrame = 0;

    function formatTime(seconds) {
        if (!Number.isFinite(seconds) || seconds < 0) return '0:00';
        const s = Math.floor(seconds);
        const m = Math.floor(s / 60);
        const r = s % 60;
        return `${m}:${String(r).padStart(2, '0')}`;
    }

    function syncCustomControls() {
        const activeIdx = order.length ? order[currentIndex] : -1;
        const hasDuration = Number.isFinite(globalAudio.duration) && globalAudio.duration > 0;

        playlist.forEach((track, idx) => {
            if (!track.controls) return;

            const isActive = idx === activeIdx;
            const { button, range, current, total } = track.controls;

            button.textContent = isActive && !globalAudio.paused ? 'Pause' : 'Play';

            if (!isActive) {
                if (!track.controls.isSeeking) {
                    range.value = '0';
                    current.textContent = '0:00';
                }
                total.textContent = track.durationLabel || '--:--';
                return;
            }

            const duration = hasDuration ? globalAudio.duration : 0;
            const progress = hasDuration ? Math.max(0, Math.min(1000, Math.round((globalAudio.currentTime / duration) * 1000))) : 0;

            if (!track.controls.isSeeking) {
                range.value = String(progress);
                current.textContent = formatTime(globalAudio.currentTime);
            }

            total.textContent = hasDuration ? formatTime(duration) : '--:--';
            track.durationLabel = total.textContent;
        });
    }

    function clearReactiveBars() {
        if (reactiveFrame) {
            cancelAnimationFrame(reactiveFrame);
            reactiveFrame = 0;
        }
        document.querySelectorAll('.audio-playing-bars span').forEach((bar) => {
            bar.style.transform = '';
            bar.style.opacity = '';
        });
        document.querySelectorAll('.audio-player.reactive-enabled').forEach((el) => {
            el.classList.remove('reactive-enabled');
        });
    }

    function getActivePlayer() {
        const active = playlist[order[currentIndex]];
        return active && active.el ? active.el : null;
    }

    function ensureAnalyser() {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return false;

        try {
            if (!audioCtx) {
                audioCtx = new Ctx();
            }
            if (!mediaSource) {
                mediaSource = audioCtx.createMediaElementSource(globalAudio);
            }
            if (!analyser) {
                analyser = audioCtx.createAnalyser();
                analyser.fftSize = 128;
                analyser.smoothingTimeConstant = 0.82;
                mediaSource.connect(analyser);
                analyser.connect(audioCtx.destination);
                analyserData = new Uint8Array(analyser.frequencyBinCount);
            }
            return true;
        } catch (e) {
            return false;
        }
    }

    function renderReactiveBars() {
        if (!analyser || !analyserData) return;
        const activePlayer = getActivePlayer();
        if (!activePlayer) return;

        const bars = activePlayer.querySelectorAll('.audio-playing-bars span');
        if (!bars.length) return;

        analyser.getByteFrequencyData(analyserData);
        const step = Math.max(1, Math.floor(analyserData.length / bars.length));

        bars.forEach((bar, idx) => {
            let sum = 0;
            let count = 0;
            const start = idx * step;
            const end = Math.min(analyserData.length, start + step);
            for (let i = start; i < end; i += 1) {
                sum += analyserData[i];
                count += 1;
            }
            const energy = count ? (sum / count) / 255 : 0;
            const scale = 0.28 + (energy * 2.9);
            bar.style.transform = `scaleY(${scale.toFixed(3)})`;
            bar.style.opacity = (0.45 + energy * 0.55).toFixed(3);
        });

        if (!globalAudio.paused && !globalAudio.ended) {
            reactiveFrame = requestAnimationFrame(renderReactiveBars);
        }
    }

    function startReactiveBars() {
        clearReactiveBars();
        if (!ensureAnalyser()) return;

        const activePlayer = getActivePlayer();
        if (activePlayer) {
            activePlayer.classList.add('reactive-enabled');
        }

        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume().catch(() => { });
        }

        reactiveFrame = requestAnimationFrame(renderReactiveBars);
    }

    function shuffle(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
    }

    function setTrack(idx) {
        if (!playlist.length) return;
        currentIndex = ((idx % order.length) + order.length) % order.length;
        const track = playlist[order[currentIndex]];
        if (!track) return;
        globalAudio.src = track.src;
        globalAudio.play().catch(() => { });
        document.querySelectorAll('.audio-player.playing').forEach(el => el.classList.remove('playing'));
        if (track.el) track.el.classList.add('playing');
        syncCustomControls();
    }

    globalAudio.addEventListener('ended', () => {
        if (!order.length) return;
        currentIndex = (currentIndex + 1) % order.length;
        setTrack(currentIndex);
    });

    globalAudio.addEventListener('play', () => {
        document.querySelectorAll('.audio-player.playing').forEach(el => el.classList.remove('playing'));
        const active = playlist[order[currentIndex]];
        if (active && active.el) active.el.classList.add('playing');
        startReactiveBars();
        syncCustomControls();
    });
    globalAudio.addEventListener('pause', () => {
        document.querySelectorAll('.audio-player.playing').forEach(el => el.classList.remove('playing'));
        clearReactiveBars();
        syncCustomControls();
    });

    globalAudio.addEventListener('ended', () => {
        clearReactiveBars();
        syncCustomControls();
    });

    globalAudio.addEventListener('timeupdate', syncCustomControls);
    globalAudio.addEventListener('durationchange', syncCustomControls);
    globalAudio.addEventListener('loadedmetadata', syncCustomControls);
    globalAudio.addEventListener('seeked', syncCustomControls);

    // try to fetch ID3 metadata (best-effort). Returns {title, imageUrl}
    async function loadMetadata(src) {
        try {
            const resp = await fetch(src);
            if (!resp.ok) throw new Error('fetch failed');
            const buffer = await resp.arrayBuffer();
            const meta = parseID3v2(buffer);
            if (meta.image && meta.image.data) {
                const blob = new Blob([meta.image.data], { type: meta.image.mime || 'image/jpeg' });
                const url = URL.createObjectURL(blob);
                return { title: meta.title || null, imageUrl: url };
            }
            return { title: meta.title || null, imageUrl: null };
        } catch (e) {
            return { title: null, imageUrl: null };
        }
    }

    // find audio items on page and enhance them
    async function enhanceAudioItems() {
        const items = Array.from(document.querySelectorAll('.item'));
        for (const item of items) {
            const mediaPath = item.getAttribute('data-media-path') || '';
            const ext = (mediaPath.split('.').pop() || '').toLowerCase();
            const itemType = item.getAttribute('data-post-type') || '';
            if (!AUDIO_EXT.has(ext) && itemType !== 'audio') continue;
            const mediaWrap = item.querySelector('.media-wrap');
            if (!mediaWrap) continue;

            mediaWrap.classList.add('audio');

            const player = document.createElement('div');
            player.className = 'audio-player';

            const cover = document.createElement('img');
            cover.className = 'audio-cover';
            cover.alt = '';
            cover.setAttribute('aria-hidden', 'true');
            cover.src = EMPTY_COVER_DATA_URI;

            const inGrid = !!item.closest('.archive.grid');
            const noCoverLabel = document.createElement('div');
            noCoverLabel.className = 'audio-cover-label';
            noCoverLabel.textContent = inGrid ? 'Audio cover' : 'Click to play';

            const playingBars = document.createElement('div');
            playingBars.className = 'audio-playing-bars';
            playingBars.setAttribute('aria-hidden', 'true');
            for (let i = 0; i < 24; i += 1) {
                const bar = document.createElement('span');
                playingBars.appendChild(bar);
            }

            // placeholder for title (used in list view, but declared here so metadata callback can reference it)
            let titleEl;

            if (inGrid) {
                player.classList.add('is-grid');
                // grid preview: cover + indicator overlay only
                const indicator = document.createElement('div');
                indicator.className = 'audio-indicator';
                indicator.innerHTML = '<svg width="36px" height="36px" viewBox="0 0 24 24" stroke-width="1" fill="none" xmlns="http://www.w3.org/2000/svg" color="#ffffff"><path d="M4 13.4998L3.51493 13.6211C2.62459 13.8437 2 14.6437 2 15.5614V17.4383C2 18.356 2.62459 19.156 3.51493 19.3786L5.25448 19.8135C5.63317 19.9081 6 19.6217 6 19.2314V13.7683C6 13.378 5.63317 13.0916 5.25448 13.1862L4 13.4998ZM4 13.4998V13C4 8.02944 7.58172 4 12 4C16.4183 4 20 8.02944 20 13V13.5M20 13.5L20.4851 13.6211C21.3754 13.8437 22 14.6437 22 15.5614V17.4383C22 18.356 21.3754 19.156 20.4851 19.3786L18.7455 19.8135C18.3668 19.9081 18 19.6217 18 19.2314V13.7683C18 13.378 18.3668 13.0916 18.7455 13.1862L20 13.5Z" stroke="#ffffff" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"></path></svg>';

                player.appendChild(cover);
                player.appendChild(noCoverLabel);
                player.appendChild(indicator);
                player.appendChild(playingBars);
                mediaWrap.innerHTML = '';
                mediaWrap.appendChild(player);
            } else {
                player.classList.add('is-list');
                // single/list view: cover, title and custom controls
                titleEl = document.createElement('div');
                titleEl.className = 'audio-title';
                titleEl.textContent = '';

                const controls = document.createElement('div');
                controls.className = 'audio-controls';

                const playToggle = document.createElement('button');
                playToggle.type = 'button';
                playToggle.className = 'audio-control-btn';
                playToggle.textContent = 'Play';

                const progress = document.createElement('input');
                progress.type = 'range';
                progress.className = 'audio-progress-range';
                progress.min = '0';
                progress.max = '1000';
                progress.value = '0';
                progress.step = '1';
                progress.setAttribute('aria-label', 'Audio progress');

                const currentTime = document.createElement('span');
                currentTime.className = 'audio-time-current';
                currentTime.textContent = '0:00';

                const totalTime = document.createElement('span');
                totalTime.className = 'audio-time-total';
                totalTime.textContent = '--:--';

                controls.appendChild(playToggle);
                controls.appendChild(progress);
                controls.appendChild(currentTime);
                controls.appendChild(totalTime);

                player.controls = {
                    button: playToggle,
                    range: progress,
                    current: currentTime,
                    total: totalTime,
                    isSeeking: false,
                };

                player.appendChild(cover);
                player.appendChild(noCoverLabel);
                player.appendChild(titleEl);
                player.appendChild(controls);
                player.appendChild(playingBars);

                mediaWrap.innerHTML = '';
                mediaWrap.appendChild(player);
            }

            // register in playlist
            const absSrc = mediaPath;
            playlist.push({ src: absSrc, el: player, cover: null, title: null, controls: player.controls || null, durationLabel: '--:--' });

            const idx = playlist.length - 1;

            // attempt ID3 metadata extraction
            (async () => {
                const meta = await loadMetadata(absSrc);
                let coverUrl = meta.imageUrl;
                let title = meta.title;

                if (!coverUrl) {
                    const fallback = await findCoverFor(absSrc);
                    coverUrl = fallback;
                }

                if (coverUrl) {
                    player.classList.remove('no-cover');
                    cover.classList.remove('no-cover');
                    cover.src = coverUrl;
                } else {
                    player.classList.add('no-cover');
                    cover.classList.add('no-cover');
                    cover.src = EMPTY_COVER_DATA_URI;
                }

                if (titleEl) {
                    if (title) titleEl.textContent = title;
                    else {
                        const name = absSrc.split('/').pop() || absSrc;
                        titleEl.textContent = name.replace(/^[0-9_\-]+/, '').replace(/\.[^.]+$/, '');
                    }
                }

                playlist[idx].cover = coverUrl;
                if (titleEl) playlist[idx].title = titleEl.textContent;
            })();

            // clicking the cover/title starts playback for non-grid items
            const startPlayback = () => {
                const targetSrc = playlist[idx] && playlist[idx].src ? playlist[idx].src : '';
                let isSame = false;
                try {
                    if (globalAudio.src) {
                        const g = new URL(globalAudio.src, location.href).pathname;
                        const t = new URL(targetSrc, location.href).pathname;
                        isSame = g.endsWith(t) || g === t;
                    }
                } catch (e) {
                    isSame = globalAudio.src === targetSrc;
                }

                if (isSame) {
                    if (globalAudio.paused) {
                        globalAudio.play().catch(() => { });
                    } else {
                        globalAudio.pause();
                    }
                    return;
                }

                order = playlist.map((_, i) => i);
                shuffle(order);
                const pos = order.indexOf(idx);
                if (pos > 0) { order.splice(pos, 1); order.unshift(idx); }
                setTrack(0);
            };

            if (!inGrid) {
                cover.addEventListener('click', startPlayback);
                noCoverLabel.addEventListener('click', startPlayback);
                if (titleEl) titleEl.addEventListener('click', startPlayback);

                const trackRef = playlist[idx];
                if (trackRef.controls) {
                    trackRef.controls.button.addEventListener('click', startPlayback);

                    const startSeeking = () => { trackRef.controls.isSeeking = true; };
                    const stopSeeking = () => {
                        trackRef.controls.isSeeking = false;
                        syncCustomControls();
                    };

                    trackRef.controls.range.addEventListener('pointerdown', startSeeking);
                    trackRef.controls.range.addEventListener('pointerup', stopSeeking);
                    trackRef.controls.range.addEventListener('touchstart', startSeeking, { passive: true });
                    trackRef.controls.range.addEventListener('touchend', stopSeeking, { passive: true });
                    trackRef.controls.range.addEventListener('blur', stopSeeking);

                    trackRef.controls.range.addEventListener('input', () => {
                        const isActive = order.length && order[currentIndex] === idx;
                        if (!isActive) return;
                        const duration = globalAudio.duration;
                        if (!Number.isFinite(duration) || duration <= 0) return;

                        const next = (Number(trackRef.controls.range.value) / 1000) * duration;
                        globalAudio.currentTime = next;
                        trackRef.controls.current.textContent = formatTime(next);
                    });
                }
            }
        }

        syncCustomControls();
    }

    // ── Video overlay click-to-play/pause (single / list view) ─────
    const VIDEO_PLAY_SVG = '<svg class="list-play-icon" width="36px" height="36px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.90588 4.53682C6.50592 4.2998 6 4.58808 6 5.05299V18.947C6 19.4119 6.50592 19.7002 6.90588 19.4632L18.629 12.5162C19.0211 12.2838 19.0211 11.7162 18.629 11.4838L6.90588 4.53682Z" stroke="#ffffff" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
    const VIDEO_PAUSE_SVG = '<svg class="list-play-icon" width="36px" height="36px" stroke-width="1" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" color="#ffffff"><path d="M6 18.4V5.6C6 5.26863 6.26863 5 6.6 5H9.4C9.73137 5 10 5.26863 10 5.6V18.4C10 18.7314 9.73137 19 9.4 19H6.6C6.26863 19 6 18.7314 6 18.4Z" stroke="#ffffff" stroke-width="1"></path><path d="M14 18.4V5.6C14 5.26863 14.2686 5 14.6 5H17.4C17.7314 5 18 5.26863 18 5.6V18.4C18 18.7314 17.7314 19 17.4 19H14.6C14.2686 19 14 18.7314 14 18.4Z" stroke="#ffffff" stroke-width="1"></path></svg>';

    function initVideoOverlays() {
        document.querySelectorAll('.list-video-overlay').forEach((overlay) => {
            const mediaWrap = overlay.closest('.media-wrap');
            const video = mediaWrap && mediaWrap.querySelector('video');
            if (!video) return;

            let videoControlsState = null;

            const syncOverlayIcon = () => {
                overlay.innerHTML = video.paused ? VIDEO_PLAY_SVG : VIDEO_PAUSE_SVG;
            };

            const maybeEnableLongVideoControls = () => {
                if (videoControlsState) return;
                if (!Number.isFinite(video.duration) || video.duration <= 30) return;

                const controls = document.createElement('div');
                controls.className = 'video-controls';

                const playToggle = document.createElement('button');
                playToggle.type = 'button';
                playToggle.className = 'video-control-btn';
                playToggle.textContent = 'Play';

                const progress = document.createElement('input');
                progress.type = 'range';
                progress.className = 'video-progress-range';
                progress.min = '0';
                progress.max = '1000';
                progress.value = '0';
                progress.step = '1';
                progress.setAttribute('aria-label', 'Video progress');

                const currentTime = document.createElement('span');
                currentTime.className = 'video-time-current';
                currentTime.textContent = '0:00';

                const totalTime = document.createElement('span');
                totalTime.className = 'video-time-total';
                totalTime.textContent = formatTime(video.duration);

                controls.appendChild(playToggle);
                controls.appendChild(progress);
                controls.appendChild(currentTime);
                controls.appendChild(totalTime);
                mediaWrap.appendChild(controls);
                mediaWrap.classList.add('has-video-controls');

                videoControlsState = {
                    controls,
                    button: playToggle,
                    range: progress,
                    current: currentTime,
                    total: totalTime,
                    isSeeking: false,
                };

                const syncVideoControls = () => {
                    if (!videoControlsState) return;
                    const duration = Number.isFinite(video.duration) && video.duration > 0 ? video.duration : 0;

                    videoControlsState.button.textContent = video.paused ? 'Play' : 'Pause';

                    if (!videoControlsState.isSeeking) {
                        const value = duration > 0 ? Math.max(0, Math.min(1000, Math.round((video.currentTime / duration) * 1000))) : 0;
                        videoControlsState.range.value = String(value);
                        videoControlsState.current.textContent = formatTime(video.currentTime);
                    }

                    videoControlsState.total.textContent = duration > 0 ? formatTime(duration) : '--:--';
                };

                playToggle.addEventListener('click', () => {
                    if (video.paused) {
                        video.play().catch(() => { });
                    } else {
                        video.pause();
                    }
                });

                const startSeeking = () => { videoControlsState.isSeeking = true; };
                const stopSeeking = () => {
                    videoControlsState.isSeeking = false;
                    syncVideoControls();
                };

                progress.addEventListener('pointerdown', startSeeking);
                progress.addEventListener('pointerup', stopSeeking);
                progress.addEventListener('touchstart', startSeeking, { passive: true });
                progress.addEventListener('touchend', stopSeeking, { passive: true });
                progress.addEventListener('blur', stopSeeking);

                progress.addEventListener('input', () => {
                    const duration = video.duration;
                    if (!Number.isFinite(duration) || duration <= 0) return;
                    const next = (Number(progress.value) / 1000) * duration;
                    video.currentTime = next;
                    videoControlsState.current.textContent = formatTime(next);
                });

                video.addEventListener('timeupdate', syncVideoControls);
                video.addEventListener('durationchange', syncVideoControls);
                video.addEventListener('loadedmetadata', syncVideoControls);
                video.addEventListener('seeked', syncVideoControls);
                video.addEventListener('play', syncVideoControls);
                video.addEventListener('pause', syncVideoControls);
                video.addEventListener('ended', syncVideoControls);

                syncVideoControls();
            };

            overlay.addEventListener('click', () => {
                if (video.paused) {
                    video.play().catch(() => { });
                } else {
                    video.pause();
                }
            });

            video.addEventListener('play', () => {
                mediaWrap.classList.add('is-playing');
                syncOverlayIcon();
            });

            video.addEventListener('pause', () => {
                mediaWrap.classList.remove('is-playing');
                syncOverlayIcon();
            });

            video.addEventListener('ended', () => {
                mediaWrap.classList.remove('is-playing');
                syncOverlayIcon();
            });

            video.addEventListener('loadedmetadata', maybeEnableLongVideoControls);
            video.addEventListener('durationchange', maybeEnableLongVideoControls);
            maybeEnableLongVideoControls();
            syncOverlayIcon();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            document.body.appendChild(globalAudio);
            enhanceAudioItems();
            initVideoOverlays();
        });
    } else {
        document.body.appendChild(globalAudio);
        enhanceAudioItems();
        initVideoOverlays();
    }

})();
