/**
 * Voice Detected – Command Parser & Executor
 * Parses transcript text and maps to executable actions.
 * Supports: Vietnamese & English
 */

window.VoiceCommands = (function () {
    'use strict';

    const cfg = window.VD_Config || {};

    /**
     * Vietnamese-safe word boundary wrapper.
     * \b in JS only works with ASCII; Vietnamese Unicode chars (ủ, ề, ớ…)
     * are treated as non-word → \b fails. We use lookahead/lookbehind instead.
     *
     * vd(pattern) wraps the pattern so it matches:
     *   - at the start of string or after a space/punctuation
     *   - at the end of string or before a space/punctuation
     */
    function vd(pat) {
        // pat is a string (the inner regex source)
        return new RegExp(
            '(?:^|(?<=[\\s,;.!?\"\'()]))'  + pat + '(?=$|[\\s,;.!?\"\'()])',
            'iu'
        );
    }

    /* ─── Command definitions ────────────────────────────────
     * Each rule: { pattern: RegExp, command: string, handler: fn(matches), noAccent?: RegExp }
     * ────────────────────────────────────────────────────── */
    const RULES = [

        // ── NAVIGATION ───────────────────────────────────────
        {
            pattern : /(?<!(?:tìm|mở|search|find|kiếm)\s{0,20})(về trang chủ|trở về trang chủ|quay về trang chủ|về nhà|về trang đầu|trang chủ đâu|mở trang chủ|trang chủ|màn hình chính|go home|home page)/iu,
            noAccent: /(ve trang chu|tro ve trang chu|quay ve trang chu|ve nha|ve trang dau|trang chu dau|mo trang chu|trang chu|man hinh chinh|go home|home page)/iu,
            command : 'navigate:home',
            handler : () => {
                VoiceWidget.speak('Đang về trang chủ');
                _navigate(cfg.home_url);
            },
        },
        {
            pattern : /(trang liên hệ|liên hệ|thông tin liên hệ|liên lạc|gặp admin|contact us|contact page|contact)/iu,
            noAccent: /(trang lien he|lien he|thong tin lien he|lien lac|gap admin|contact us|contact page|contact)/iu,
            command : 'navigate:contact',
            handler : () => {
                VoiceWidget.speak('Đang mở trang liên hệ');
                _navigate(cfg.home_url + 'contact');
            },
        },
        {
            pattern : /(trang giới thiệu|giới thiệu|về chúng tôi|thông tin web|about us|about)/iu,
            noAccent: /(trang gioi thieu|gioi thieu|ve chung toi|thong tin web|about us|about)/iu,
            command : 'navigate:about',
            handler : () => {
                VoiceWidget.speak('Đang mở trang giới thiệu');
                _navigate(cfg.home_url + 'about');
            },
        },
        {
            pattern : /(trang blog|blog|tin tức|trang tin tức|danh sách bài viết)/iu,
            noAccent: /(trang blog|blog|tin tuc|trang tin tuc|danh sach bai viet)/iu,
            command : 'navigate:blog',
            handler : () => {
                VoiceWidget.speak('Đang mở trang tin tức');
                _navigate(cfg.home_url + 'blog');
            },
        },
        {
            pattern : /(quay lại|lùi lại|trang trước|go back|back)/iu,
            noAccent: /(quay lai|lui lai|trang truoc|go back|back)/iu,
            command : 'navigate:back',
            handler : () => {
                VoiceWidget.speak('Quay lại trang trước');
                window.history.back();
            },
        },
        {
            pattern : /(tiến tới|tiến lên|trang sau|go forward|forward)/iu,
            noAccent: /(tien toi|tien len|trang sau|go forward|forward)/iu,
            command : 'navigate:forward',
            handler : () => {
                VoiceWidget.speak('Tiến tới trang sau');
                window.history.forward();
            },
        },

        // ── OPEN POST / PAGE ──────────────────────────────────
        {
            pattern : /(?:tóm tắt bài viết này|tóm tắt bài này|tóm tắt bài|tóm tắt trang này|tóm tắt nội dung|bài này nói về cái gì|nội dung chính bài này|ý chính bài này|tóm tắt cho tôi|tóm tắt|summarize post|summarize article|summarize this|summarize)/iu,
            noAccent: /(tom tat bai viet nay|tom tat bai nay|tom tat bai|tom tat trang nay|tom tat noi dung|bai nay noi ve cai gi|noi dung chinh bai nay|y chinh bai nay|tom tat cho toi|tom tat|summarize post|summarize article|summarize this|summarize)/iu,
            command : 'post:summarize',
            handler : () => VoiceWidget.summarizeCurrentPost(),
        },
        {
            pattern : /(?:mở bài viết|mở bài|xem bài viết|xem bài|đọc bài viết|đọc bài|cho tôi xem bài|cho xem bài|mở tin|xem tin|open post|open article)\s+(.+)/iu,
            command : 'post:open',
            handler : (m) => _searchAndOpen( _extractKeyword(m[1].trim()) ),
        },
        {
            pattern : /(?:mở trang|open page|go to page|xem trang)\s+(.+)/iu,
            command : 'page:open',
            handler : (m) => _searchAndOpen( _extractKeyword(m[1].trim()), 'page' ),
        },

        // ── GLOBAL SEARCH ─────────────────────────────────────
        // "tìm bài viết có tiêu đề X" / "tìm X" / "kiếm X" / "search X"
        {
            pattern : /(?:tìm|kiếm|tra cứu|search)\s+(?:bài viết|bài|trang|page|post)?\s*(?:có tiêu đề|có tên|với tiêu đề|tên là|tiêu đề)?\s*["']?(.+?)["']?$/iu,
            command : 'search:by_title',
            handler : (m) => _globalSearch( _extractKeyword(m[1].trim()) ),
        },
        {
            pattern : /(?:tìm kiếm|tìm giúp tôi|tìm giúp|kiếm giúp|tìm bài|kiếm bài|tra cứu|cho tôi tìm|search for|find)\s+(.+)/iu,
            command : 'search:global',
            handler : (m) => _globalSearch( _extractKeyword(m[1].trim()) ),
        },
        {
            // Direct query like "bài viết mới nhất" or "bài mới nhất"
            pattern : /(?:bài viết|bài)\s+(.+)/iu,
            command : 'search:post_direct',
            handler : (m) => _searchAndOpen( _extractKeyword(m[1].trim()) ),
        },

        // ── SCROLL ────────────────────────────────────────────
        {
            pattern : /(cuộn xuống|lướt xuống|kéo xuống|đi xuống|xuống dưới|xuống tiếp|scroll down)/iu,
            noAccent: /(cuon xuong|luot xuong|keo xuong|di xuong|xuong duoi|xuong tiep|scroll down)/iu,
            command : 'scroll:down',
            handler : () => {
                const step = parseInt(cfg.scroll_step, 10) || 450;
                VoiceWidget.speak('Đang cuộn xuống');
                window.scrollBy({ top: step, behavior: 'smooth' });
            },
        },
        {
            pattern : /(cuộn lên|lướt lên|kéo lên|đi lên|lên trên|lên tiếp|scroll up)/iu,
            noAccent: /(cuon len|luot len|keo len|di len|len tren|len tiep|scroll up)/iu,
            command : 'scroll:up',
            handler : () => {
                const step = parseInt(cfg.scroll_step, 10) || 450;
                VoiceWidget.speak('Đang cuộn lên');
                window.scrollBy({ top: -step, behavior: 'smooth' });
            },
        },
        {
            pattern : /(lên đầu trang|về đầu trang|lên đỉnh|đầu trang|lên trên cùng|top of page|scroll to top)/iu,
            noAccent: /(len dau trang|ve dau trang|len dinh|dau trang|len tren cung|top of page|scroll to top)/iu,
            command : 'scroll:top',
            handler : () => {
                VoiceWidget.speak('Về đầu trang');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
        },
        {
            pattern : /(xuống cuối trang|về cuối trang|xuống đáy|cuối trang|xuống chân trang|bottom of page|scroll to bottom)/iu,
            noAccent: /(xuong cuoi trang|ve cuoi trang|xuong day|cuoi trang|xuong chan trang|bottom of page|scroll to bottom)/iu,
            command : 'scroll:bottom',
            handler : () => {
                VoiceWidget.speak('Về cuối trang');
                window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
            },
        },
        {
            pattern : /(?:cuộn xuống|scroll down)\s+(\d+)\s*(?:pixel|px)?/iu,
            command : 'scroll:down_px',
            handler : (m) => window.scrollBy({ top: parseInt(m[1], 10), behavior: 'smooth' }),
        },

        // ── FORM CONTROLS ─────────────────────────────────────
        {
            pattern : /(?:viết bình luận|bình luận|gõ bình luận|thêm bình luận|gửi bình luận|đăng bình luận|comment)\s+(.+)/iu,
            command : 'comment:write',
            handler : (m) => VoiceWidget.writeComment(m[1].trim()),
        },
        {
            pattern : /(?:điền họ tên|nhập họ tên|điền tên|nhập tên|họ tên là|tên tôi là|tôi tên là|tên là)\s+(.+)/iu,
            command : 'form:fill_name',
            handler : (m) => VoiceWidget.fillField('name', m[1].trim()),
        },
        {
            pattern : /(?:điền email|nhập email|email của tôi là|email là|thư điện tử là)\s+(.+)/iu,
            command : 'form:fill_email',
            handler : (m) => {
                const email = m[1].trim()
                    .replace(/\s*a còng\s*|\s*at\s*/gi, '@')
                    .replace(/\s*chấm\s*|\s*dot\s*/gi, '.')
                    .replace(/\s+/g, '');
                VoiceWidget.fillField('email', email);
            },
        },
        {
            pattern : /(?:điền số điện thoại|nhập số điện thoại|điền sđt|nhập sđt|số điện thoại là|điện thoại là|sđt là)\s+(.+)/iu,
            command : 'form:fill_phone',
            handler : (m) => {
                const phone = m[1].trim().replace(/\s+/g, '').replace(/[^0-9+]/g, '');
                VoiceWidget.fillField('phone', phone);
            },
        },
        {
            pattern : /(submit form|gửi form|nộp form|submit)/iu,
            noAccent: /(submit form|gui form|nop form|submit)/iu,
            command : 'form:submit',
            handler : () => _submitForm(),
        },
        {
            pattern : /(xóa form|reset form|clear form)/iu,
            noAccent: /(xoa form|reset form|clear form)/iu,
            command : 'form:reset',
            handler : () => _resetForm(),
        },
        {
            pattern : /(?:điền|nhập|fill in|type)\s+(.+?)\s+(?:vào|into|in)\s+(.+)/iu,
            command : 'form:fill',
            handler : (m) => _fillField(m[2].trim(), m[1].trim()),
        },
        {
            pattern : /(?:click|nhấn|bấm)\s+(.+)/iu,
            command : 'ui:click',
            handler : (m) => _clickElement(m[1].trim()),
        },

        // ── BROWSER ACTIONS ───────────────────────────────────
        {
            pattern : /(tải lại trang|tải lại|refresh|reload|f5|load lại)/iu,
            noAccent: /(tai lai trang|tai lai|refresh|reload|f5|load lai)/iu,
            command : 'browser:reload',
            handler : () => window.location.reload(),
        },
        {
            pattern : /(in trang|print page|print)/iu,
            noAccent: /(in trang|print page|print)/iu,
            command : 'browser:print',
            handler : () => window.print(),
        },
        {
            pattern : /(?:mở liên kết|open link)\s+(.+)/iu,
            command : 'browser:open_link',
            handler : (m) => _openLink(m[1].trim()),
        },

        // ── WIDGET & SOUND CONTROLS ───────────────────────────
        {
            pattern : /(tắt âm thanh|tắt tiếng|tắt loa|im lặng|mute)/iu,
            noAccent: /(tat am thanh|tat tieng|tat loa|im lang|mute)/iu,
            command : 'voice:mute',
            handler : () => {
                if (window.VoiceWidget?.setMute) VoiceWidget.setMute(true);
            },
        },
        {
            pattern : /(bật âm thanh|bật tiếng|mở tiếng|mở loa|unmute)/iu,
            noAccent: /(bat am thanh|bat tieng|mo tieng|mo loa|unmute)/iu,
            command : 'voice:unmute',
            handler : () => {
                if (window.VoiceWidget?.setMute) VoiceWidget.setMute(false);
            },
        },
        {
            pattern : /(đóng trợ lý|tắt trợ lý|ẩn trợ lý|đóng bảng|ẩn bảng|close)/iu,
            noAccent: /(dong tro ly|tat tro ly|an tro ly|dong bang|an bang|close)/iu,
            command : 'ui:close',
            handler : () => {
                if (window.VoiceWidget?.closePanel) VoiceWidget.closePanel();
            },
        },

        // ── ZOOM ──────────────────────────────────────────────
        {
            pattern : /(phóng to|zoom in)/iu,
            noAccent: /(phong to|zoom in)/iu,
            command : 'zoom:in',
            handler : () => _zoom(1.1),
        },
        {
            pattern : /(thu nhỏ|zoom out)/iu,
            noAccent: /(thu nho|zoom out)/iu,
            command : 'zoom:out',
            handler : () => _zoom(0.9),
        },
        {
            pattern : /(đặt lại zoom|reset zoom)/iu,
            noAccent: /(dat lai zoom|reset zoom)/iu,
            command : 'zoom:reset',
            handler : () => { document.body.style.zoom = '1'; },
        },
    ];


    /* ─── Vietnamese Normalization & Conversational Filter ── */
    function removeVietnameseTones(str) {
        if (!str) return '';
        return str
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/g, 'd').replace(/Đ/g, 'D')
            .toLowerCase()
            .trim();
    }

    function stripConversationalNoise(raw) {
        if (!raw) return '';
        let text = raw.trim();

        // 1. Strip leading conversational padding (từ đệm đầu câu)
        const leadingNoise = [
            /^(?:ơi|à|ừm|này|ê|alo|em ơi|bạn ơi|trợ lý ơi|admin ơi)\s+/iu,
            /^(?:hãy|làm ơn|vui lòng|nhờ bạn|giúp tôi|giúp mình|hộ tôi|hộ mình|giùm tôi|giùm tao)\s+/iu,
            /^(?:cho tôi xem|cho mình xem|cho xem|cho coi|thử|coi xem|hãy mở|hãy tìm)\s+/iu,
        ];

        // 2. Strip trailing conversational padding (từ đệm đuôi câu)
        const trailingNoise = [
            /\s+(?:ơi|nhé|nha|nhỉ|với|nhá|hộ cái|giùm cái|hộ với|giùm với|nào|đi|xem nào|coi nào|được không|giùm|hộ)$/iu,
            /[?!.,;:…\s]+$/u,
        ];

        let prev;
        do {
            prev = text;
            for (const p of leadingNoise) {
                text = text.replace(p, '');
            }
            for (const p of trailingNoise) {
                text = text.replace(p, '');
            }
        } while (text !== prev && text.length > 0);

        return text.trim();
    }

    /* ─── Keyword extractor ──────────────────────────────────
     * Lọc bỏ các từ thừa trong tiếng Việt để lấy keyword thực sự.
     * Ví dụ: "bài viết có tiêu đề mới nhất hôm nay" → "mới nhất hôm nay"
     */
    function _extractKeyword(raw) {
        // Các cụm từ thừa cần bỏ đi (prefix noise)
        const noisePatterns = [
            /^(?:cho tôi xem|cho xem|hãy tìm|tìm giúp tôi|tìm giúp|kiếm giúp|vui lòng tìm|hãy mở)\s+/i,
            /^(?:bài viết|bài|trang|page|post)\s+/i,
            /^(?:có tiêu đề|có tên|với tiêu đề|tên là|tiêu đề là|tiêu đề)\s+/i,
            /^(?:về|nói về|chủ đề|topic|about)\s+/i,
        ];
        let keyword = stripConversationalNoise(raw);
        // Lặp để bỏ nhiều lớp noise liên tiếp
        let prev;
        do {
            prev = keyword;
            for (const p of noisePatterns) {
                keyword = keyword.replace(p, '');
            }
        } while (keyword !== prev);

        keyword = keyword.replace(/[?!.,;:…\s]+$/, '').trim();

        return keyword || raw.trim(); // fallback về nguyên bản nếu lọc hết
    }


    /* ─── Action implementations ─────────────────────────── */
    function _navigate(url) {
        if (!url) return;
        // Bảo vệ: không bao giờ điều hướng đến wp-admin từ lệnh frontend
        if (url.includes('/wp-admin')) {
            console.warn('[VoiceDetected] Blocked navigation to admin URL:', url);
            return;
        }
        window.location.href = url;
    }

    async function _searchAndOpen(keyword, postType = 'post') {
        VoiceWidget.showMessage(`🔍 Đang tìm "${keyword}"…`);
        const fd = new FormData();
        fd.append('action',    'vd_search_posts');
        fd.append('nonce',     cfg.nonce);
        fd.append('keyword',   keyword);
        fd.append('per_page',  '5');

        try {
            const resp = await fetch(cfg.ajax_url, { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success && data.data.posts.length > 0) {
                const posts = data.data.posts.filter(p => postType === 'any' || p.type === postType || postType === 'post');
                if (posts.length === 1) {
                    window.location.href = posts[0].url;
                } else if (posts.length > 1) {
                    VoiceWidget.showSearchResults(data.data);
                } else {
                    _globalSearch(keyword);
                }
            } else {
                _globalSearch(keyword);
            }
        } catch (e) {
            _globalSearch(keyword);
        }
    }

    function _globalSearch(keyword) {
        // Dùng search_url từ config, fallback an toàn nếu undefined
        const searchUrl = (cfg.search_url && cfg.search_url !== 'undefined')
            ? cfg.search_url + encodeURIComponent(keyword)
            : (cfg.home_url || window.location.origin + '/') + '?s=' + encodeURIComponent(keyword);
        // Hiển thị popup kết quả trước, redirect là fallback
        _fetchAndShowResults(keyword, searchUrl);
    }

    async function _fetchAndShowResults(keyword, fallbackUrl) {
        VoiceWidget.showMessage(`🔍 Đang tìm: "${keyword}"…`);
        const fd = new FormData();
        fd.append('action',  'vd_search_posts');
        fd.append('nonce',   cfg.nonce);
        fd.append('keyword', keyword);
        fd.append('per_page', '5');

        try {
            const resp = await fetch(cfg.ajax_url, { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success && data.data.posts.length > 0) {
                VoiceWidget.showSearchResults(data.data, fallbackUrl);
            } else {
                VoiceWidget.showMessage(`🔍 Không tìm thấy bài viết nào với từ khóa: "<strong>${keyword}</strong>".<br><a href="${fallbackUrl}" style="color:var(--vd-accent-light);display:inline-block;margin-top:6px;font-size:12px;">Tìm kiếm trên toàn bộ trang web →</a>`);
                VoiceWidget.speak(`Không tìm thấy bài viết phù hợp với ${keyword}`);
            }
        } catch (e) {
            VoiceWidget.showMessage(`⚠️ Không thể tìm kiếm lúc này: ` + e.message);
        }
    }

    function _submitForm() {
        const form = document.querySelector('form:not(#search-form)') || document.querySelector('form');
        if (form) form.submit();
    }

    function _resetForm() {
        const form = document.querySelector('form');
        if (form) form.reset();
    }

    function _fillField(fieldLabel, value) {
        const lower = fieldLabel.toLowerCase();
        // Try to find by label, placeholder, name, id
        const inputs = [...document.querySelectorAll('input, textarea')];
        const target = inputs.find(el =>
            (el.placeholder || '').toLowerCase().includes(lower) ||
            (el.name        || '').toLowerCase().includes(lower) ||
            (el.id          || '').toLowerCase().includes(lower) ||
            (document.querySelector(`label[for="${el.id}"]`)?.textContent || '').toLowerCase().includes(lower)
        );
        if (target) {
            target.focus();
            target.value = value;
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function _clickElement(label) {
        const lower = label.toLowerCase();
        const selectors = ['button', 'a', 'input[type="button"]', 'input[type="submit"]', '[role="button"]'];
        for (const sel of selectors) {
            const el = [...document.querySelectorAll(sel)].find(e =>
                e.textContent.toLowerCase().includes(lower) ||
                (e.getAttribute('aria-label') || '').toLowerCase().includes(lower)
            );
            if (el) { el.click(); return; }
        }
    }

    function _openLink(label) {
        const lower = label.toLowerCase();
        const link = [...document.querySelectorAll('a')].find(a =>
            a.textContent.toLowerCase().includes(lower) ||
            (a.getAttribute('href') || '').toLowerCase().includes(lower)
        );
        if (link) link.click();
    }

    let _zoomLevel = 1;
    function _zoom(factor) {
        _zoomLevel = Math.min(3, Math.max(0.5, _zoomLevel * factor));
        document.body.style.zoom = _zoomLevel.toFixed(2);
    }

    /* ─── Log to server ───────────────────────────────────── */
    function _log(transcript, command, confidence, executed) {
        const fd = new FormData();
        fd.append('action',     'vd_log_command');
        fd.append('nonce',      cfg.nonce);
        fd.append('transcript', transcript);
        fd.append('command',    command);
        fd.append('confidence', confidence);
        fd.append('executed',   executed ? '1' : '0');
        fetch(cfg.ajax_url, { method: 'POST', body: fd }).catch(() => {});
    }

    /* ─── Public API ──────────────────────────────────────── */
    return {
        /**
         * Parse transcript and execute the first matching command.
         * @param {string} transcript
         * @param {number} confidence
         * @returns {{ command: string, executed: boolean }}
         */
        execute(transcript, confidence = 1) {
            const text = transcript.toLowerCase().trim();
            if (!text) return { command: '', executed: false };

            // Strip trigger word if user spoke it (e.g. "hey Domi") but don't reject if omitted
            const trigger = (cfg.trigger_word || '').toLowerCase().trim();
            let payload = text;
            if (trigger && payload.startsWith(trigger)) {
                payload = payload.slice(trigger.length).trim();
            }

            // Clean conversational fillers
            const cleanPayload = stripConversationalNoise(payload) || payload;
            const noAccentPayload = removeVietnameseTones(cleanPayload);

            // Pass 1: Standard match with accents
            for (const rule of RULES) {
                const m = cleanPayload.match(rule.pattern) || payload.match(rule.pattern);
                if (m) {
                    try {
                        rule.handler(m);
                        _log(transcript, rule.command, confidence, true);
                        return { command: rule.command, executed: true };
                    } catch (e) {
                        console.warn('[VoiceDetected] Command error:', rule.command, e);
                    }
                }
            }

            // Pass 2: Accent-tolerant fallback match
            for (const rule of RULES) {
                if (rule.noAccent) {
                    const m = noAccentPayload.match(rule.noAccent);
                    if (m) {
                        try {
                            rule.handler(m);
                            _log(transcript, rule.command, confidence, true);
                            return { command: rule.command, executed: true };
                        } catch (e) {
                            console.warn('[VoiceDetected] Accent-tolerant match error:', rule.command, e);
                        }
                    }
                }
            }

            _log(transcript, '', confidence, false);
            return { command: '', executed: false };
        },

        /** Expose rules list (for debugging/help UI) */
        getRules: () => RULES.map(r => ({ command: r.command, pattern: r.pattern.source })),
        removeVietnameseTones,
        stripConversationalNoise,
    };
})();
