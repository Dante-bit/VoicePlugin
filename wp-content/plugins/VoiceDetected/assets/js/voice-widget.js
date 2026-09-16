/**
 * Voice Detected – Big Tech Voice Assistant Controller v3
 * Features:
 *   - Google Assistant 4-dots audio reactivity & waveform visualizer
 *   - Zero-dependency Web Audio API sound synthesizer (Start & Success chimes)
 *   - Vietnamese Text-to-Speech (TTS) voice feedback
 *   - Click-to-execute smart suggestion chips
 *   - Rich search cards with thumbnail & excerpt
 *   - Keyboard shortcuts: Alt+V (toggle voice), Esc (close panel)
 */

window.VoiceWidget = (function () {
    'use strict';

    const cfg = window.VD_Config || {};

    /* ─── State ───────────────────────────────────────────── */
    let state       = 'idle'; // idle | listening | processing | result | error
    let panelOpen   = false;
    let isMuted     = localStorage.getItem('vd_sound_muted') === '1';

    /* ─── DOM refs ─────────────────────────────────────────── */
    let $root, $micBtn, $pulse, $bars, $panelBars, $googleDots,
        $transcript, $status, $resultsPanel, $overlay,
        $commandPanel, $textCmd, $textSubmit, $recordBtn, $clearInput,
        $cmdLog, $clearLog, $helpToggle, $helpPanel, $panelClose,
        $soundToggle, $engineBadge, $chips;

    /* ─── Web Audio Synthesizer (Zero asset download) ──────── */
    let audioCtx = null;
    function getAudioContext() {
        if (!audioCtx) {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (AudioCtx) audioCtx = new AudioCtx();
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    /**
     * Play high-tech two-tone listening beep (C5 -> G5)
     */
    function playSoundListening() {
        if (isMuted) return;
        try {
            const ctx = getAudioContext();
            if (!ctx) return;
            const now = ctx.currentTime;

            // Tone 1: C5 (523Hz)
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(523.25, now);
            gain1.gain.setValueAtTime(0.08, now);
            gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.12);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(now);
            osc1.stop(now + 0.12);

            // Tone 2: G5 (784Hz)
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(783.99, now + 0.08);
            gain2.gain.setValueAtTime(0.09, now + 0.08);
            gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.22);
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.start(now + 0.08);
            osc2.stop(now + 0.22);
        } catch (e) {
            console.warn('[VoiceDetected] Audio play error:', e);
        }
    }

    /**
     * Play pleasant confirmation chime (G5 -> C6)
     */
    function playSoundSuccess() {
        if (isMuted) return;
        try {
            const ctx = getAudioContext();
            if (!ctx) return;
            const now = ctx.currentTime;

            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(659.25, now); // E5
            osc.frequency.exponentialRampToValueAtTime(1046.5, now + 0.16); // C6
            gain.gain.setValueAtTime(0.09, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.32);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(now);
            osc.stop(now + 0.32);
        } catch (e) {}
    }

    /**
     * Play subtle error chime
     */
    function playSoundError() {
        if (isMuted) return;
        try {
            const ctx = getAudioContext();
            if (!ctx) return;
            const now = ctx.currentTime;

            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(320, now);
            osc.frequency.exponentialRampToValueAtTime(180, now + 0.2);
            gain.gain.setValueAtTime(0.06, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.22);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(now);
            osc.stop(now + 0.22);
        } catch (e) {}
    }

    /* ─── Vietnamese Text-To-Speech (TTS) ─────────────────── */
    function speak(text) {
        if (isMuted || cfg.tts_voice === false || !('speechSynthesis' in window) || !text) return;
        try {
            window.speechSynthesis.cancel(); // Dừng câu trước nếu có
            const clean = text.replace(/[*_#🔍🏠🔥⬇️🔝📞✨✅❓⚠️❌]/g, '').trim();
            if (!clean) return;

            const utter = new SpeechSynthesisUtterance(clean);
            utter.lang = cfg.language || 'vi-VN';
            utter.rate = parseFloat(cfg.tts_speed) || 1.05;
            utter.pitch = 1.0;

            // Tìm giọng đọc tiếng Việt nếu có
            const voices = window.speechSynthesis.getVoices();
            const viVoice = voices.find(v => v.lang.includes('vi') || v.lang.includes('VN'));
            if (viVoice) utter.voice = viVoice;

            window.speechSynthesis.speak(utter);
        } catch (e) {
            console.warn('[VoiceDetected] TTS error:', e);
        }
    }

    /* ─── Init ─────────────────────────────────────────────── */
    function init() {
        $root         = document.getElementById('vd-widget-root');
        $micBtn       = document.getElementById('vd-mic-btn');
        $pulse        = document.getElementById('vd-pulse');
        $bars         = document.querySelectorAll('#vd-waveform .vd-bar');
        $panelBars    = document.querySelectorAll('#vd-btn-wave .vd-bar');
        $googleDots   = document.querySelectorAll('.vd-gdot');
        $transcript   = document.getElementById('vd-transcript-text');
        $status       = document.getElementById('vd-transcript-label');
        $resultsPanel = document.getElementById('vd-results-panel');
        $commandPanel = document.getElementById('vd-command-panel');
        $textCmd      = document.getElementById('vd-text-cmd');
        $textSubmit   = document.getElementById('vd-text-submit');
        $recordBtn    = document.getElementById('vd-record-btn');
        $clearInput   = document.getElementById('vd-clear-input');
        $cmdLog       = document.getElementById('vd-cmd-log');
        $clearLog     = document.getElementById('vd-clear-log');
        $helpToggle   = document.getElementById('vd-help-toggle');
        $helpPanel    = document.getElementById('vd-help-panel');
        $panelClose   = document.getElementById('vd-panel-close');
        $overlay      = document.getElementById('vd-overlay');
        $soundToggle  = document.getElementById('vd-sound-toggle');
        $engineBadge  = document.getElementById('vd-engine-badge');
        $chips        = document.querySelectorAll('.vd-chip');

        if (!$micBtn) return;

        // Apply position + theme
        $root.setAttribute('data-position', cfg.position || 'bottom-right');
        $root.setAttribute('data-theme',    cfg.theme    || 'dark');

        if ($engineBadge) {
            if (cfg.api_engine === 'groq') {
                $engineBadge.textContent = 'Groq Whisper';
            } else if (cfg.api_engine === 'gemini') {
                $engineBadge.textContent = 'Gemini AI';
            } else if (cfg.api_engine === 'google') {
                $engineBadge.textContent = 'Google Cloud';
            } else {
                $engineBadge.textContent = 'Web Speech';
            }
        }

        // Apply initial sound mute state
        updateSoundButton();

        // Mic button → toggle panel open/close
        $micBtn.addEventListener('click', () => {
            getAudioContext();
            togglePanel();
        });

        // Close button inside panel
        $panelClose?.addEventListener('click', closePanel);

        // Sound toggle
        $soundToggle?.addEventListener('click', () => {
            isMuted = !isMuted;
            localStorage.setItem('vd_sound_muted', isMuted ? '1' : '0');
            updateSoundButton();
            if (!isMuted) {
                playSoundSuccess();
                speak('Âm thanh trợ lý đã bật');
            }
        });

        // Record button inside panel
        $recordBtn?.addEventListener('click', () => {
            getAudioContext();
            VoiceEngine.toggle();
        });

        // Clear input button
        $clearInput?.addEventListener('click', () => {
            if ($textCmd) {
                $textCmd.value = '';
                $clearInput.style.display = 'none';
                $textCmd.focus();
            }
        });

        // Text input typing listener
        $textCmd?.addEventListener('input', () => {
            if ($clearInput) {
                $clearInput.style.display = ($textCmd.value.trim().length > 0) ? 'flex' : 'none';
            }
        });

        // Text command submit
        $textSubmit?.addEventListener('click', () => submitTextCommand());
        $textCmd?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') submitTextCommand();
        });

        // Suggestion chips click-to-execute
        $chips.forEach(chip => {
            chip.addEventListener('click', () => {
                const cmd = chip.getAttribute('data-cmd');
                if (cmd) {
                    if ($textCmd) $textCmd.value = cmd;
                    submitTextCommand(cmd);
                }
            });
        });

        // Dynamic smart chips on single posts / articles
        if (cfg.is_single || document.querySelector('article, .entry-content, .post-content')) {
            const chipsContainer = document.getElementById('vd-suggestion-chips');
            if (chipsContainer) {
                const summarizeBtn = document.createElement('button');
                summarizeBtn.type = 'button';
                summarizeBtn.className = 'vd-chip vd-chip-special';
                summarizeBtn.setAttribute('data-cmd', 'tóm tắt bài này');
                summarizeBtn.innerHTML = '⚡ Tóm tắt bài này';
                summarizeBtn.addEventListener('click', () => {
                    submitTextCommand('tóm tắt bài này');
                });
                chipsContainer.insertBefore(summarizeBtn, chipsContainer.firstChild);

                if (document.querySelector('textarea#comment, #commentform, .comment-form, textarea[name="comment"]')) {
                    const commentBtn = document.createElement('button');
                    commentBtn.type = 'button';
                    commentBtn.className = 'vd-chip';
                    commentBtn.setAttribute('data-cmd', 'viết bình luận Bài viết rất hay');
                    commentBtn.innerHTML = '✍️ Viết bình luận';
                    commentBtn.addEventListener('click', () => {
                        submitTextCommand('viết bình luận Bài viết rất hay');
                    });
                    chipsContainer.insertBefore(commentBtn, summarizeBtn.nextSibling);
                }
            }
        }

        // Clear command history
        $clearLog?.addEventListener('click', () => {
            if ($cmdLog) {
                $cmdLog.innerHTML = '<div class="vd-log-empty">✨ Đã xóa lịch sử câu lệnh.</div>';
            }
        });

        // Help toggle
        $helpToggle?.addEventListener('click', () => {
            $helpPanel?.classList.toggle('vd-visible');
        });

        // Overlay click closes panel
        $overlay?.addEventListener('click', closePanel);

        // Global Keyboard shortcuts: configurable (default Alt+V), Esc (close panel)
        function matchesShortcut(e, shortcutStr) {
            if (!shortcutStr) return false;
            const parts = shortcutStr.toLowerCase().split('+').map(s => s.trim());
            const targetKey = parts[parts.length - 1];
            const needAlt   = parts.includes('alt');
            const needCtrl  = parts.includes('ctrl') || parts.includes('control');
            const needShift = parts.includes('shift');
            const hasCtrl   = Boolean(e.ctrlKey || e.metaKey);

            return (
                e.altKey === needAlt &&
                hasCtrl  === needCtrl &&
                e.shiftKey === needShift &&
                e.key.toLowerCase() === targetKey
            );
        }

        document.addEventListener('keydown', (e) => {
            if (matchesShortcut(e, cfg.keyboard_shortcut || 'Alt+V')) {
                e.preventDefault();
                getAudioContext();
                if (!panelOpen) openPanel();
                VoiceEngine.toggle();
            } else if (e.key === 'Escape' && panelOpen) {
                closePanel();
            }
        });

        // Inline shortcode buttons
        document.querySelectorAll('.vd-inline-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                getAudioContext();
                openPanel();
                setTimeout(() => VoiceEngine.toggle(), 100);
            });
        });

        // Wire engine callbacks
        VoiceEngine.on.start        = onStart;
        VoiceEngine.on.stop         = onStop;
        VoiceEngine.on.interim      = onInterim;
        VoiceEngine.on.result       = onResult;
        VoiceEngine.on.error        = onError;
        VoiceEngine.on.volumeChange = onVolume;

        setState('idle');
    }

    function updateSoundButton() {
        if (!$soundToggle) return;
        const iconOn  = $soundToggle.querySelector('.vd-icon-sound-on');
        const iconOff = $soundToggle.querySelector('.vd-icon-sound-off');
        if (isMuted) {
            $soundToggle.classList.add('vd-muted');
            if (iconOn)  iconOn.style.display  = 'none';
            if (iconOff) iconOff.style.display = 'block';
        } else {
            $soundToggle.classList.remove('vd-muted');
            if (iconOn)  iconOn.style.display  = 'block';
            if (iconOff) iconOff.style.display = 'none';
        }
    }

    /* ─── Panel open/close ─────────────────────────────────── */
    function openPanel() {
        panelOpen = true;
        $root?.classList.add('vd-panel-open');
        $overlay?.classList.add('vd-visible');
        setTimeout(() => $textCmd?.focus(), 150);
    }

    function closePanel() {
        panelOpen = false;
        $root?.classList.remove('vd-panel-open');
        $overlay?.classList.remove('vd-visible');
        if (state === 'listening') VoiceEngine.toggle();
    }

    function togglePanel() {
        panelOpen ? closePanel() : openPanel();
    }

    /* ─── Text command submission ──────────────────────────── */
    function submitTextCommand(explicitText) {
        const text = (explicitText !== undefined ? explicitText : ($textCmd?.value || '')).trim();
        if (!text) return;
        if ($textCmd) $textCmd.value = '';
        if ($clearInput) $clearInput.style.display = 'none';

        // Show in transcript
        if ($transcript) $transcript.textContent = text;
        if ($status) $status.textContent = 'LỆNH VĂN BẢN';

        // Execute via command engine
        const { command, executed } = VoiceCommands.execute(text, 1.0);

        if (executed) {
            playSoundSuccess();
            addLogEntry(text, command, '✅', 'vd-log-ok');
        } else {
            // Conversational AI: respond naturally instead of throwing error!
            handleConversationalAI(text);
        }
    }

    /* ─── State machine ────────────────────────────────────── */
    function setState(newState, text = '') {
        state = newState;
        $root?.setAttribute('data-state', newState);

        if ($recordBtn) {
            if (newState === 'listening') {
                $recordBtn.classList.add('vd-recording');
            } else {
                $recordBtn.classList.remove('vd-recording');
            }
        }
    }

    /* ─── Engine callbacks ─────────────────────────────────── */
    function onStart() {
        setState('listening');
        playSoundListening();
        if ($status) $status.textContent = 'ĐANG NGHE BẠN NÓI…';
        if ($transcript) $transcript.textContent = 'Hãy nói lệnh hoặc nội dung tìm kiếm…';
        clearResults();
    }

    function onStop() {
        setState('processing');
        if ($status) $status.textContent = 'ĐANG XỬ LÝ…';
        if ($transcript && $transcript.textContent === 'Hãy nói lệnh hoặc nội dung tìm kiếm…') {
            $transcript.textContent = 'Trợ lý đang phân tích giọng nói…';
        }
    }

    function onInterim(interimText) {
        if ($transcript) {
            $transcript.innerHTML = escHtml(interimText) + '<span class="vd-cursor-blink">|</span>';
        }
        if ($status) $status.textContent = 'ĐANG NGHE BẠN NÓI…';
    }

    function onResult(transcript, confidence) {
        if ($transcript) $transcript.textContent = transcript;
        setState('result');

        // Min confidence gate (only for Google engine)
        const minConf = parseFloat(cfg.confidence) || 0.6;
        if (confidence < minConf && cfg.api_engine === 'google') {
            playSoundError();
            showMessage(`⚠️ Độ tin cậy thấp (${Math.round(confidence * 100)}%). Bạn hãy nói lại rõ hơn nhé!`);
            speak('Độ tin cậy thấp, bạn hãy thử nói lại rõ hơn nhé');
            addLogEntry(transcript, 'low_confidence', '⚠️', 'vd-log-fail');
            setState('idle');
            return;
        }

        const { command, executed } = VoiceCommands.execute(transcript, confidence);

        if (executed) {
            playSoundSuccess();
            if ($status) $status.textContent = 'ĐÃ HOÀN TẤT';
            const logType = command.startsWith('search') ? 'vd-log-search' : 'vd-log-ok';
            addLogEntry(transcript, command, command.startsWith('search') ? '🔍' : '✅', logType);
            setTimeout(() => {
                setState('idle');
                if ($status) $status.textContent = 'TRỢ LÝ SẴN SÀNG';
            }, 3000);
        } else {
            // Conversational AI: respond naturally instead of throwing error!
            handleConversationalAI(transcript);
        }
    }

    /* ─── Conversational AI Assistant ──────────────────────── */
    async function handleConversationalAI(text) {
        setState('processing');
        if ($status) $status.textContent = 'TRỢ LÝ ĐANG SUY NGHĨ…';

        // Show thinking bubble in results panel
        if ($resultsPanel) {
            $resultsPanel.innerHTML = `
                <div class="vd-ai-card vd-ai-thinking">
                    <div class="vd-ai-header">
                        <span class="vd-ai-orb"></span>
                        <strong>Trợ lý AI</strong>
                    </div>
                    <div class="vd-ai-body">
                        <span>Trợ lý đang suy nghĩ</span>
                        <span class="vd-thinking-dots"><span>.</span><span>.</span><span>.</span></span>
                    </div>
                </div>`;
            if (!panelOpen) openPanel();
        }

        const fd = new FormData();
        fd.append('action',  'vd_ai_chat');
        fd.append('nonce',   cfg.nonce);
        fd.append('message', text);

        try {
            const resp = await fetch(cfg.ajax_url, { method: 'POST', body: fd });
            const data = await resp.json();
            const reply = (data.success && data.data?.reply)
                ? data.data.reply
                : 'Nội dung này hiện không có trên website.';

            // Render AI response card
            if ($resultsPanel) {
                $resultsPanel.innerHTML = `
                    <div class="vd-ai-card">
                        <div class="vd-ai-header">
                            <span class="vd-ai-orb"></span>
                            <strong>Trợ lý AI</strong>
                            <span class="vd-ai-badge">Phản hồi</span>
                        </div>
                        <div class="vd-ai-body">${escHtml(reply)}</div>
                    </div>`;
            }

            if ($status) $status.textContent = 'ĐÃ TRẢ LỜI';
            playSoundSuccess();
            speak(reply);
            addLogEntry(text, 'Trợ lý AI trả lời', '🤖', 'vd-log-ai');
        } catch (e) {
            const fallbackMsg = 'Nội dung này hiện không có trên website.';
            if ($resultsPanel) {
                $resultsPanel.innerHTML = `
                    <div class="vd-ai-card">
                        <div class="vd-ai-header"><span class="vd-ai-orb"></span> <strong>Trợ lý AI</strong></div>
                        <div class="vd-ai-body">${escHtml(fallbackMsg)}</div>
                    </div>`;
            }
            if ($status) $status.textContent = 'HOÀN TẤT';
            speak(fallbackMsg);
            addLogEntry(text, 'Không có trong website', 'ℹ️', 'vd-log-ai');
        }

        setTimeout(() => {
            setState('idle');
            if ($status) $status.textContent = 'TRỢ LÝ SẴN SÀNG';
        }, 4000);
    }

    function onError(msg) {
        setState('error');
        playSoundError();
        showMessage('❌ ' + msg);
        if ($status) $status.textContent = 'LỖI MICROPHONE';
        if ($transcript) $transcript.textContent = '❌ ' + msg;
        speak('Đã xảy ra lỗi micro, bạn vui lòng thử lại');
        setTimeout(() => {
            setState('idle');
            if ($status) $status.textContent = 'TRỢ LÝ SẴN SÀNG';
        }, 3500);
    }

    function onVolume(vol) {
        // Animate Google dots with volume
        if ($googleDots && state === 'listening') {
            const scale = 1 + (vol / 100) * 0.45;
            $googleDots.forEach((dot, idx) => {
                const shift = Math.sin(Date.now() / 150 + idx) * (vol / 100) * 12;
                dot.style.transform = `translateY(${shift.toFixed(1)}px) scale(${scale.toFixed(2)})`;
            });
        }
        // Animate waveform bars in panel
        $bars?.forEach((bar) => {
            const h = Math.max(5, Math.round((vol / 100) * 34 * (0.6 + Math.random() * 0.8)));
            bar.style.height = h + 'px';
        });
        // Animate bars in floating mic button
        $panelBars?.forEach((bar) => {
            const h = Math.max(4, Math.round((vol / 100) * 22 * (0.5 + Math.random() * 0.9)));
            bar.style.height = h + 'px';
        });
    }

    /* ─── Command log ──────────────────────────────────────── */
    function addLogEntry(text, command, icon, cssClass) {
        if (!$cmdLog) return;

        const empty = $cmdLog.querySelector('.vd-log-empty');
        if (empty) empty.remove();

        const time  = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const entry = document.createElement('div');
        entry.className = `vd-log-entry ${cssClass}`;
        entry.innerHTML = `
            <span class="vd-log-icon">${icon}</span>
            <span class="vd-log-body">
                <span class="vd-log-text">${escHtml(text)}</span>
                <span class="vd-log-cmd">${escHtml(command)}</span>
            </span>
            <span class="vd-log-time">${escHtml(time)}</span>`;

        $cmdLog.insertBefore(entry, $cmdLog.firstChild);

        const entries = $cmdLog.querySelectorAll('.vd-log-entry');
        if (entries.length > 25) entries[entries.length - 1].remove();
    }

    /* ─── Results display (Rich Cards) ─────────────────────── */
    function showMessage(text) {
        if (!$resultsPanel) return;
        $resultsPanel.innerHTML = `<div class="vd-message-card">${escHtml(text)}</div>`;
    }

    function clearResults() {
        if ($resultsPanel) $resultsPanel.innerHTML = '';
    }

    function showSearchResults(data, fallbackUrl = '') {
        if (!$resultsPanel) return;
        const { posts, keyword, total } = data;

        // TTS voice feedback
        speak(`Tìm thấy ${total} bài viết về ${keyword}`);

        let html = `
            <div class="vd-results-header">
                <span>🔍 Kết quả cho: <strong>"${escHtml(keyword)}"</strong></span>
                <span class="vd-results-count">${total} bài viết</span>
            </div>
            <ul class="vd-results-list">`;

        posts.forEach(post => {
            const thumb = post.thumbnail
                ? `<img src="${escHtml(post.thumbnail)}" alt="" class="vd-result-thumb" loading="lazy">`
                : `<span class="vd-result-icon">📄</span>`;
            html += `
                <li class="vd-result-item">
                    <a href="${escHtml(post.url)}" class="vd-result-link">
                        <span class="vd-result-media">${thumb}</span>
                        <span class="vd-result-info">
                            <span class="vd-result-title">${escHtml(post.title)}</span>
                            ${post.excerpt ? `<span class="vd-result-excerpt">${escHtml(post.excerpt)}</span>` : ''}
                            <span class="vd-result-meta">
                                <span class="vd-result-badge">${post.type === 'page' ? 'Trang' : 'Bài viết'}</span>
                            </span>
                        </span>
                        <span class="vd-result-arrow">→</span>
                    </a>
                </li>`;
        });

        html += `</ul>`;
        if (fallbackUrl) {
            html += `<a href="${escHtml(fallbackUrl)}" class="vd-all-results-btn">Xem tất cả kết quả trên web →</a>`;
        }

        $resultsPanel.innerHTML = html;

        if (!panelOpen) openPanel();
    }

    /* ─── AI Article Summarizer (Feature 2) ─────────────────── */
    function extractArticleContent() {
        const selectors = ['.entry-content', 'article .content', '.post-content', 'article', 'main', '.site-main', '#content'];
        for (const s of selectors) {
            const el = document.querySelector(s);
            if (el && el.innerText && el.innerText.trim().length > 80) {
                return el.innerText.trim().slice(0, 5000);
            }
        }
        return (document.body?.innerText || '').trim().slice(0, 3000);
    }

    async function summarizeCurrentPost() {
        setState('processing');
        if (!panelOpen) openPanel();
        if ($status) $status.textContent = 'AI ĐANG TÓM TẮT…';
        if ($transcript) $transcript.textContent = '⚡ Đang đọc và tóm tắt bài viết này…';

        if ($resultsPanel) {
            $resultsPanel.innerHTML = `
                <div class="vd-ai-card vd-ai-thinking">
                    <div class="vd-ai-header">
                        <span class="vd-ai-orb"></span>
                        <strong>⚡ AI Tóm tắt bài viết</strong>
                    </div>
                    <div class="vd-ai-body">
                        <span>Đang phân tích và chắt lọc 3 ý cốt lõi của bài viết</span>
                        <span class="vd-thinking-dots"><span>.</span><span>.</span><span>.</span></span>
                    </div>
                </div>`;
        }

        const fd = new FormData();
        fd.append('action', 'vd_summarize_post');
        fd.append('nonce', cfg.nonce);
        if (cfg.current_post_id) {
            fd.append('post_id', cfg.current_post_id);
        }
        fd.append('post_title', cfg.current_title || document.title || 'Bài viết');
        fd.append('post_content', extractArticleContent());

        try {
            const resp = await fetch(cfg.ajax_url, { method: 'POST', body: fd });
            const res = await resp.json();
            if (res.success && res.data?.summary) {
                const s = res.data.summary;
                const bullets = Array.isArray(s.bullets) ? s.bullets : [];
                const icons = ['🎯', '💡', '📌', '⭐', '✨'];
                const bulletsHtml = bullets.map((b, i) => `
                    <li class="vd-summary-bullet">
                        <span class="vd-bullet-icon">${icons[i % icons.length]}</span>
                        <span class="vd-bullet-text">${escHtml(b)}</span>
                    </li>
                `).join('');

                const fullSpeech = `Tóm tắt bài viết ${s.title || ''}. ` +
                    bullets.map((b, i) => `Ý thứ ${i + 1}: ${b}`).join('. ') +
                    (s.conclusion ? `. Kết luận: ${s.conclusion}` : '');

                if ($resultsPanel) {
                    $resultsPanel.innerHTML = `
                        <div class="vd-summary-card">
                            <div class="vd-summary-header">
                                <div class="vd-summary-badges">
                                    <span class="vd-summary-tag">⚡ AI Tóm tắt</span>
                                    <span class="vd-summary-time">⏱️ Đọc ~${escHtml(s.read_time || '1 phút')}</span>
                                </div>
                                <button type="button" class="vd-summary-audio-btn" id="vd-replay-summary-btn" title="Nghe đọc tóm tắt">
                                    🔊 Nghe lại
                                </button>
                            </div>
                            <h4 class="vd-summary-title">${escHtml(s.title || cfg.current_title || document.title)}</h4>
                            <ul class="vd-summary-bullets">
                                ${bulletsHtml}
                            </ul>
                            ${s.conclusion ? `
                                <div class="vd-summary-conclusion">
                                    <div class="vd-conclusion-label">💡 Điểm nhấn / Kết luận:</div>
                                    <div class="vd-conclusion-text">${escHtml(s.conclusion)}</div>
                                </div>
                            ` : ''}
                        </div>`;

                    document.getElementById('vd-replay-summary-btn')?.addEventListener('click', () => {
                        speak(fullSpeech);
                    });
                }

                if ($status) $status.textContent = 'ĐÃ TÓM TẮT XONG';
                playSoundSuccess();
                speak(fullSpeech);
                addLogEntry('Tóm tắt bài viết này', 'AI Tóm tắt bài viết', '⚡', 'vd-log-ai');
            } else {
                throw new Error(res.data?.message || 'Không thể tạo tóm tắt.');
            }
        } catch (e) {
            playSoundError();
            showMessage('⚠️ ' + (e.message || 'Không thể tóm tắt bài viết lúc này.'));
            speak('Không thể tóm tắt bài viết lúc này');
        }

        setTimeout(() => {
            setState('idle');
            if ($status) $status.textContent = 'TRỢ LÝ SẴN SÀNG';
        }, 4000);
    }

    /* ─── Smart Form & Comment Dictation (Feature 4) ─────────── */
    function writeComment(text) {
        if (!text) return false;
        const textarea = document.querySelector('textarea#comment, textarea[name="comment"], #commentform textarea, form.comment-form textarea, textarea');
        if (textarea) {
            textarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
            textarea.focus();
            const current = textarea.value.trim();
            textarea.value = current ? current + ' ' + text : text;
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            textarea.dispatchEvent(new Event('change', { bubbles: true }));

            textarea.classList.add('vd-field-highlight');
            setTimeout(() => textarea.classList.remove('vd-field-highlight'), 2500);

            playSoundSuccess();
            showMessage(`✍️ Đã điền vào bình luận: "<em>${escHtml(text)}</em>"`);
            speak('Đã viết nội dung vào khung bình luận');
            addLogEntry(text, 'Viết bình luận', '✍️', 'vd-log-ok');
            if (!panelOpen) openPanel();
            return true;
        } else {
            playSoundError();
            showMessage('⚠️ Không tìm thấy khung bình luận trên trang này.');
            speak('Không tìm thấy khung bình luận trên trang này');
            return false;
        }
    }

    function fillField(type, val) {
        if (!val) return false;
        let field = null;
        if (type === 'name') {
            field = document.querySelector('input#author, input[name="author"], input[name*="name"], input#name, input[autocomplete="name"]');
        } else if (type === 'email') {
            field = document.querySelector('input#email, input[type="email"], input[name*="email"]');
        } else if (type === 'phone') {
            field = document.querySelector('input[type="tel"], input[name*="phone"], input[name*="tel"], input#phone');
        }

        if (!field) {
            const inputs = [...document.querySelectorAll('input:not([type="hidden"]), textarea')];
            field = inputs.find(el => (el.name || '').toLowerCase().includes(type) || (el.id || '').toLowerCase().includes(type));
        }

        const labelMap = { name: 'họ tên', email: 'email', phone: 'số điện thoại' };
        const label = labelMap[type] || type;

        if (field) {
            field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            field.focus();
            field.value = val;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));

            field.classList.add('vd-field-highlight');
            setTimeout(() => field.classList.remove('vd-field-highlight'), 2500);

            playSoundSuccess();
            showMessage(`✍️ Đã điền ${label}: "<strong>${escHtml(val)}</strong>"`);
            speak(`Đã điền ${label}`);
            addLogEntry(val, `Điền ${label}`, '✍️', 'vd-log-ok');
            if (!panelOpen) openPanel();
            return true;
        } else {
            playSoundError();
            showMessage(`⚠️ Không tìm thấy ô nhập liệu ${label} trên trang.`);
            speak(`Không tìm thấy ô nhập liệu`);
            return false;
        }
    }

    /* ─── Helpers ──────────────────────────────────────────── */
    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    function setMute(val) {
        isMuted = Boolean(val);
        localStorage.setItem('vd_sound_muted', isMuted ? '1' : '0');
        updateSoundButton();
        if (!isMuted) {
            playSoundSuccess();
            speak('Âm thanh trợ lý đã bật');
        } else {
            if ('speechSynthesis' in window) window.speechSynthesis.cancel();
        }
    }

    /* ─── Boot ─────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', init);

    return {
        showMessage,
        showSearchResults,
        clearResults,
        openPanel,
        closePanel,
        speak,
        playSoundSuccess,
        setMute,
        summarizeCurrentPost,
        writeComment,
        fillField,
    };
})();

