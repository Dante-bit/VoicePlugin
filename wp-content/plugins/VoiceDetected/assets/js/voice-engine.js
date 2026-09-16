/**
 * Voice Detected – Voice Engine
 * Handles audio capture (MediaRecorder) and sends to Google STT via AJAX.
 * Falls back to Web Speech API if configured.
 */

window.VoiceEngine = (function () {
    'use strict';

    const cfg = window.VD_Config || {};

    let mediaRecorder  = null;
    let audioChunks    = [];
    let stream         = null;
    let isRecording    = false;
    let analyser       = null;
    let audioCtx       = null;
    let animFrameId    = null;

    /* ─── Public callbacks ─────────────────────────────────── */
    const on = {
        start       : () => {},
        stop        : () => {},
        interim     : (_text) => {}, // Live stream text in real-time while speaking!
        result      : (_transcript, _confidence) => {},
        error       : (_msg) => {},
        volumeChange: (_vol) => {},  // 0–100
    };

    /* ─── Studio Audio Preprocessing Graph ────────────────── */
    function buildAudioProcessingGraph(streamSource) {
        if (!audioCtx) return streamSource;

        let lastNode = streamSource;

        if (cfg.audio_filter !== false) {
            try {
                // 1. High-pass filter: cut sub-bass rumblings, fan noise, desk thumps (< 85Hz)
                const highpass = audioCtx.createBiquadFilter();
                highpass.type = 'highpass';
                highpass.frequency.setValueAtTime(85, audioCtx.currentTime);
                highpass.Q.setValueAtTime(0.7, audioCtx.currentTime);
                lastNode.connect(highpass);
                lastNode = highpass;

                // 2. Dynamics Compressor: auto-level speech, boost whisper, clamp loud peaks
                const compressor = audioCtx.createDynamicsCompressor();
                compressor.threshold.setValueAtTime(-24, audioCtx.currentTime);
                compressor.knee.setValueAtTime(28, audioCtx.currentTime);
                compressor.ratio.setValueAtTime(10, audioCtx.currentTime);
                compressor.attack.setValueAtTime(0.003, audioCtx.currentTime);
                compressor.release.setValueAtTime(0.25, audioCtx.currentTime);
                lastNode.connect(compressor);
                lastNode = compressor;
            } catch (e) {
                console.warn('[VoiceEngine] Audio filter setup error, bypassing:', e);
            }
        }

        return lastNode;
    }

    function getSilenceTimeout() {
        if (cfg.mic_sensitivity === 'fast') return 800;
        if (cfg.mic_sensitivity === 'relaxed') return 1800;
        return 1200; // default normal
    }

    /* ─── MediaRecorder (Server STT path: Groq / Gemini / Google) ── */
    let silenceTimer   = null;
    let maxRecordTimer = null;
    let speechDetected = false;

    async function startGoogle() {
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl:  true,
                    channelCount:     1,
                    sampleRate:       48000,
                }
            });
        } catch (e) {
            on.error(cfg.i18n?.mic_blocked || 'Microphone access denied.');
            return;
        }

        // Setup Web Audio Graph with High-pass & Compressor
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        analyser = audioCtx.createAnalyser();
        analyser.fftSize = 256;

        const src = audioCtx.createMediaStreamSource(stream);
        const filteredNode = buildAudioProcessingGraph(src);
        filteredNode.connect(analyser);

        // Record from filtered audio stream for best noise suppression
        let recordStream = stream;
        try {
            const dest = audioCtx.createMediaStreamDestination();
            filteredNode.connect(dest);
            recordStream = dest.stream;
        } catch (e) {}

        speechDetected = false;
        clearTimeout(silenceTimer);
        clearTimeout(maxRecordTimer);
        silenceTimer   = null;
        maxRecordTimer = null;

        // Auto stop after 8s max
        maxRecordTimer = setTimeout(() => {
            if (isRecording) stopGoogle();
        }, 8000);

        tickVisualiser();

        audioChunks = [];
        const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
            ? 'audio/webm;codecs=opus'
            : 'audio/webm';

        mediaRecorder = new MediaRecorder(recordStream, { mimeType });
        mediaRecorder.ondataavailable = (e) => {
            if (e.data.size > 0) audioChunks.push(e.data);
        };
        mediaRecorder.onstop = handleRecordingStop;
        mediaRecorder.start();
        isRecording = true;
        on.start();
    }

    async function stopGoogle() {
        clearTimeout(silenceTimer);
        clearTimeout(maxRecordTimer);
        silenceTimer   = null;
        maxRecordTimer = null;
        speechDetected = false;

        if (!mediaRecorder || mediaRecorder.state === 'inactive') return;
        mediaRecorder.stop();
        stream?.getTracks().forEach(t => t.stop());
        cancelAnimationFrame(animFrameId);
        isRecording = false;
        on.stop();
    }

    async function handleRecordingStop() {
        const blob   = new Blob(audioChunks, { type: 'audio/webm' });
        const b64    = await blobToBase64(blob);
        const audioB64 = b64.split(',')[1]; // strip data URL prefix

        const fd = new FormData();
        fd.append('action',   'vd_transcribe');
        fd.append('nonce',    cfg.nonce);
        fd.append('audio',    audioB64);
        fd.append('language', cfg.language || 'vi-VN');

        try {
            const resp = await fetch(cfg.ajax_url, { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                const { transcript, confidence } = data.data;
                on.result(transcript, confidence);
            } else {
                // Fallback to Web Speech API result if stored
                on.error(data.data?.message || 'Transcription failed.');
            }
        } catch (e) {
            on.error('Network error: ' + e.message);
        }
    }

    /* ─── Web Speech API path (Google Real-Time Streaming) ──── */
    let recognition  = null;
    let speechStream = null;

    async function startWebSpeech() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            on.error('Trình duyệt không hỗ trợ Web Speech API. Vui lòng dùng Chrome hoặc Edge.');
            return;
        }

        // Connect mic stream to analyser for animated visualizer with noise filtering
        try {
            speechStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl:  true,
                    channelCount:     1,
                    sampleRate:       48000,
                }
            });
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            analyser = audioCtx.createAnalyser();
            analyser.fftSize = 256;
            const src = audioCtx.createMediaStreamSource(speechStream);
            const filteredNode = buildAudioProcessingGraph(src);
            filteredNode.connect(analyser);
            tickVisualiser();
        } catch (e) {
            // Visualiser optional, speech recognition can still work
        }

        recognition = new SpeechRecognition();
        recognition.lang           = cfg.language || 'vi-VN';
        recognition.interimResults = true;  // Real-time live streaming text!
        recognition.continuous     = false; // Auto stops when user finishes speaking
        recognition.maxAlternatives = 1;

        recognition.onstart  = () => {
            isRecording = true;
            on.start();
        };

        recognition.onend = () => {
            stopVisualiser();
            isRecording = false;
            on.stop();
        };

        recognition.onerror = (e) => {
            stopVisualiser();
            if (e.error !== 'no-speech') {
                on.error(e.error);
            }
        };

        recognition.onresult = (e) => {
            let interimText = '';
            let finalTranscript = '';
            let confidence = 0.95;

            for (let i = e.resultIndex; i < e.results.length; i++) {
                const res = e.results[i];
                if (res.isFinal) {
                    finalTranscript += res[0].transcript;
                    confidence = res[0].confidence || 0.95;
                } else {
                    interimText += res[0].transcript;
                }
            }

            // Stream words in real-time as user speaks!
            if (interimText) {
                on.interim(interimText.trim());
            }
            if (finalTranscript) {
                on.result(finalTranscript.toLowerCase().trim(), confidence);
            }
        };

        try {
            recognition.start();
        } catch (e) {
            on.error(e.message);
        }
    }

    function stopVisualiser() {
        if (speechStream) {
            speechStream.getTracks().forEach(t => t.stop());
            speechStream = null;
        }
        if (animFrameId) {
            cancelAnimationFrame(animFrameId);
            animFrameId = null;
        }
        on.volumeChange(0);
    }

    function stopWebSpeech() {
        stopVisualiser();
        if (recognition) {
            try { recognition.stop(); } catch (e) {}
            recognition = null;
        }
        isRecording = false;
    }

    /* ─── Visualiser & Silence VAD ─────────────────────────── */
    function tickVisualiser() {
        if (!analyser) return;
        const data = new Uint8Array(analyser.frequencyBinCount);
        analyser.getByteFrequencyData(data);
        const avg = data.reduce((a, b) => a + b, 0) / data.length;
        const vol = Math.min(100, Math.round((avg / 128) * 100));
        on.volumeChange(vol);

        // Silence Voice Activity Detection (VAD)
        if (vol > 15) {
            speechDetected = true;
            if (silenceTimer) {
                clearTimeout(silenceTimer);
                silenceTimer = null;
            }
        } else if (speechDetected && vol < 10) {
            // Once user has spoken and then pauses, auto stop based on sensitivity setting
            if (!silenceTimer) {
                silenceTimer = setTimeout(() => {
                    if (isRecording) {
                        stopGoogle();
                    }
                }, getSilenceTimeout());
            }
        }

        animFrameId = requestAnimationFrame(tickVisualiser);
    }

    /* ─── Utilities ─────────────────────────────────────────── */
    function blobToBase64(blob) {
        return new Promise((res, rej) => {
            const reader = new FileReader();
            reader.onloadend = () => res(reader.result);
            reader.onerror   = rej;
            reader.readAsDataURL(blob);
        });
    }

    /* ─── Public API ────────────────────────────────────────── */
    return {
        get isRecording() { return isRecording; },
        on,

        start() {
            if (isRecording) return;
            if (cfg.api_engine === 'webspeech') {
                startWebSpeech();
            } else {
                startGoogle(); // Handles server-side engines: groq, gemini, google
            }
        },

        stop() {
            if (!isRecording) return;
            if (cfg.api_engine === 'webspeech') {
                stopWebSpeech();
            } else {
                stopGoogle();
            }
        },

        toggle() {
            isRecording ? this.stop() : this.start();
        },
    };
})();
