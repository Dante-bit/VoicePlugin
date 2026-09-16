/**
 * Voice Detected – Admin Panel JS (Pro Suite)
 * Handles: API key validation, tabs, interactive color picker, live preview.
 */

(function ($) {
    'use strict';

    const cfg = window.VD_Admin || {};

    /* ─── Tab switching ────────────────────────────────────── */
    $('.vd-tab-btn').on('click', function () {
        const target = $(this).data('tab');
        $('.vd-tab-btn').removeClass('active');
        $('.vd-tab-pane').removeClass('active');
        $(this).addClass('active');
        $('#vd-tab-' + target).addClass('active');

        if (target === 'analytics') {
            loadAnalytics();
        }
    });

    /* ─── Validate API Key ─────────────────────────────────── */
    $('#vd-validate-key-btn').on('click', function () {
        const $btn    = $(this);
        const key     = $('#vd-api-key-input').val().trim();
        const $msg    = $('#vd-key-validation-msg');
        const engine  = $('input[name="voice_detected_settings[api_engine]"]:checked').val() || 'groq';

        if (!key) {
            $msg.text('Vui lòng nhập API key.').attr('class', 'vd-validation-msg vd-error');
            return;
        }

        $btn.prop('disabled', true).text('Đang kiểm tra…');
        $msg.text('').attr('class', 'vd-validation-msg');

        $.post(cfg.ajax_url, {
            action  : 'vd_validate_key',
            nonce   : cfg.nonce,
            api_key : key,
            engine  : engine,
        }).done(function (res) {
            if (res.success) {
                $msg.text('✅ ' + res.data.message).attr('class', 'vd-validation-msg vd-success');
            } else {
                $msg.text('❌ ' + res.data.message).attr('class', 'vd-validation-msg vd-error');
            }
        }).fail(function () {
            $msg.text('❌ Không thể kết nối máy chủ.').attr('class', 'vd-validation-msg vd-error');
        }).always(function () {
            $btn.prop('disabled', false).text('Kiểm tra Key');
        });
    });

    /* ─── Toggle engine-specific fields ─────────────────────── */
    function toggleEngineFields() {
        const engine = $('input[name="voice_detected_settings[api_engine]"]:checked').val();

        if (engine === 'webspeech') {
            $('#vd-api-card').slideUp(180);
        } else {
            $('#vd-api-card').slideDown(180);
        }

        $('#vd-info-groq').toggle(engine === 'groq');
        $('#vd-info-gemini').toggle(engine === 'gemini');
        $('#vd-info-google').toggle(engine === 'google');

        if (engine === 'groq') {
            $('#vd-api-key-input').attr('placeholder', 'gsk_…');
        } else if (engine === 'gemini') {
            $('#vd-api-key-input').attr('placeholder', 'AIzaSy…');
        } else {
            $('#vd-api-key-input').attr('placeholder', 'AIza…');
        }
    }
    $('input[name="voice_detected_settings[api_engine]"]').on('change', toggleEngineFields);
    toggleEngineFields();

    /* ─── Confidence slider value ───────────────────────────── */
    $('#vd-confidence-range').on('input', function () {
        $('#vd-confidence-value').text(Math.round($(this).val() * 100) + '%');
    });

    /* ─── TTS Speed slider value ────────────────────────────── */
    $('#vd-tts-speed').on('input', function () {
        $('#vd-tts-speed-val').text(parseFloat($(this).val()).toFixed(2) + 'x');
    });

    /* ─── Interactive Color Picker & Live Preview ───────────── */
    function updateAccent(color) {
        if (!color) return;
        $('#vd-accent-picker').val(color);
        $('#vd-accent-text').val(color);

        // Update live preview mock button
        $('#vd-mock-btn').css({
            'background': color,
            'box-shadow': '0 6px 20px ' + color + '88'
        });
        $('.vd-mock-badge').css({
            'color': color,
            'background': color + '22'
        });
    }

    $('#vd-accent-picker').on('input change', function () {
        updateAccent($(this).val());
    });

    $('#vd-accent-text').on('input change', function () {
        const val = $(this).val().trim();
        if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
            updateAccent(val);
        }
    });

    $('.vd-color-chip').on('click', function () {
        const color = $(this).data('color');
        if (color) updateAccent(color);
    });

    // Update preview title
    $('#vd-widget-title').on('input', function () {
        $('#vd-mock-title').text($(this).val().trim() || 'Trợ lý ảo AI');
    });

    // Update preview size
    $('input[name="voice_detected_settings[widget_size]"]').on('change', function () {
        const size = $(this).val();
        let px = 56;
        if (size === 'small') px = 46;
        if (size === 'large') px = 66;
        $('#vd-mock-btn').css({ width: px + 'px', height: px + 'px' });
    });

    // Initialize preview state
    const initialColor = $('#vd-accent-text').val() || '#6366F1';
    updateAccent(initialColor);

    /* ─── Copy shortcode ─────────────────────────────────────── */
    $('#vd-copy-shortcode').on('click', function () {
        const code = $('#vd-shortcode-sample').text().trim();
        navigator.clipboard.writeText(code).then(() => {
            $(this).text('✅ Đã copy!');
            setTimeout(() => $(this).text('📋 Copy Shortcode'), 2000);
        });
    });

    /* ─── Analytics & SEO Tab Data (Feature 5) ───────────────── */
    let analyticsLoaded = false;
    function loadAnalytics(force = false) {
        if (analyticsLoaded && !force) return;
        const $btn = $('#vd-refresh-analytics-btn');
        $btn.prop('disabled', true).text('Đang tải…');

        $.post(cfg.ajax_url, {
            action: 'vd_get_analytics',
            nonce:  cfg.nonce
        }).done(function (res) {
            if (res.success && res.data) {
                analyticsLoaded = true;
                const d   = res.data;
                const kpi = d.kpi || {};
                $('#vd-kpi-total').text(kpi.total_queries ?? 0);
                $('#vd-kpi-success').text((kpi.success_rate ?? 0) + '%');
                $('#vd-kpi-ai').text((kpi.ai_rate ?? 0) + '%');
                $('#vd-kpi-search').text((kpi.search_rate ?? 0) + '%');

                // Render Top Voice Searches
                const $searches = $('#vd-top-queries-list');
                const topSearches = d.top_searches || [];
                if (topSearches.length === 0) {
                    $searches.html('<div class="vd-empty-box">Chưa có lượt tìm kiếm bằng giọng nói nào được ghi nhận.</div>');
                } else {
                    let sHtml = '';
                    topSearches.forEach((item, i) => {
                        sHtml += `
                            <div class="vd-query-item">
                                <div class="vd-query-meta">
                                    <span class="vd-query-rank">#${i + 1}</span>
                                    <span class="vd-query-text">${escapeHtml(item.query)}</span>
                                    <span class="vd-query-count"><strong>${item.count}</strong> lượt (${item.pct}%)</span>
                                </div>
                                <div class="vd-bar-track">
                                    <div class="vd-bar-fill" style="width:${Math.max(6, item.pct)}%;"></div>
                                </div>
                            </div>`;
                    });
                    $searches.html(sHtml);
                }

                // Render Content Gaps
                const $gaps = $('#vd-content-gaps-list');
                const contentGaps = d.content_gaps || [];
                if (contentGaps.length === 0) {
                    $gaps.html('<div class="vd-empty-box">🎉 Tuyệt vời! Chưa phát hiện câu hỏi nào bị thiếu nội dung trên website.</div>');
                } else {
                    let gHtml = '';
                    contentGaps.forEach(item => {
                        gHtml += `
                            <div class="vd-gap-item">
                                <div class="vd-gap-header">
                                    <span class="vd-gap-query">❓ "${escapeHtml(item.query)}"</span>
                                    <span class="vd-gap-badge">${item.count} lần hỏi</span>
                                </div>
                                <div class="vd-gap-tip">
                                    💡 <em>Gợi ý SEO:</em> Người dùng muốn biết điều này nhưng web chưa có bài viết. Bạn nên xuất bản bài mới để tăng thứ hạng tìm kiếm!
                                </div>
                            </div>`;
                    });
                    $gaps.html(gHtml);
                }
            }
        }).fail(function () {
            $('#vd-top-queries-list').html('<div class="vd-error-box">Không thể tải dữ liệu thống kê.</div>');
            $('#vd-content-gaps-list').html('<div class="vd-error-box">Không thể tải dữ liệu thống kê.</div>');
        }).always(function () {
            $btn.prop('disabled', false).text('🔄 Làm mới dữ liệu');
        });
    }

    $('#vd-refresh-analytics-btn').on('click', function () {
        loadAnalytics(true);
    });

    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

})(jQuery);
