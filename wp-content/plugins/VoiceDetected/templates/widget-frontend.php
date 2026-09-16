<?php
defined( 'ABSPATH' ) || exit;
$settings   = (array) get_option( VD_OPTION_KEY, [] );
$title      = ! empty( $settings['widget_title'] ) ? $settings['widget_title'] : __( 'Trợ lý giọng nói AI', 'voice-detected' );
$shortcut   = ! empty( $settings['keyboard_shortcut'] ) ? $settings['keyboard_shortcut'] : 'Alt+V';
$chips_raw  = $settings['suggestion_chips'] ?? '🏠 Trang chủ, 🔥 Mới nhất hôm nay, ⬇️ Cuộn xuống, 🔝 Đầu trang, 📞 Liên hệ';
$chips_list = array_filter( array_map( 'trim', explode( ',', $chips_raw ) ) );
?>

<!-- Voice Detected Widget - Big Tech Assistant UI -->
<div id="vd-widget-root" role="complementary" aria-label="<?php esc_attr_e( 'Voice control widget', 'voice-detected' ); ?>">

    <!-- ── Floating Mic Button ── -->
    <button
        id="vd-mic-btn"
        aria-label="<?php esc_attr_e( 'Mở trợ lý giọng nói', 'voice-detected' ); ?>"
        title="<?php echo esc_attr( sprintf( __( '%s (%s)', 'voice-detected' ), $title, $shortcut ) ); ?>"
    >
        <!-- Mic SVG -->
        <svg id="vd-mic-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 1C10.34 1 9 2.34 9 4V12C9 13.66 10.34 15 12 15C13.66 15 15 13.66 15 12V4C15 2.34 13.66 1 12 1Z" fill="currentColor"/>
            <path d="M19 12C19 15.87 15.87 19 12 19C8.13 19 5 15.87 5 12H3C3 16.97 6.93 21.07 12 21.51V23H14V21.51C19.07 21.07 23 16.97 23 12H19Z" fill="currentColor"/>
        </svg>
        <!-- Waveform bars (shown inside button while recording) -->
        <span id="vd-btn-wave" aria-hidden="true">
            <?php for ( $i = 0; $i < 5; $i++ ) : ?>
                <span class="vd-bar"></span>
            <?php endfor; ?>
        </span>
    </button>

    <!-- Pulse rings (double glowing aura) -->
    <div id="vd-pulse" aria-hidden="true"></div>
    <div id="vd-pulse-outer" aria-hidden="true"></div>

    <!-- Status label below button -->
    <div id="vd-status-label" aria-live="polite"></div>

    <!-- ── Command Panel (Google / Siri Assistant Style) ── -->
    <div id="vd-command-panel" role="dialog" aria-label="<?php esc_attr_e( 'Bảng trợ lý giọng nói', 'voice-detected' ); ?>">

        <!-- Panel header -->
        <div class="vd-panel-header">
            <div class="vd-panel-title">
                <!-- Glowing animated assistant orb -->
                <span class="vd-assistant-orb" aria-hidden="true"></span>
                <span id="vd-title-text"><?php echo esc_html( $title ); ?></span>
                <span class="vd-status-badge" id="vd-engine-badge">Ready</span>
            </div>
            <div class="vd-panel-actions">
                <!-- Sound TTS mute/unmute toggle -->
                <button id="vd-sound-toggle" title="<?php esc_attr_e( 'Bật/Tắt âm thanh trợ lý', 'voice-detected' ); ?>" aria-label="Toggle Sound">
                    <svg class="vd-icon-sound-on" viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
                    <svg class="vd-icon-sound-off" viewBox="0 0 24 24" width="14" height="14" fill="currentColor" style="display:none;"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
                </button>
                <button id="vd-help-toggle" title="<?php esc_attr_e( 'Hướng dẫn câu lệnh', 'voice-detected' ); ?>">?</button>
                <button id="vd-panel-close" aria-label="<?php esc_attr_e( 'Đóng (Esc)', 'voice-detected' ); ?>" title="Đóng (Esc)">✕</button>
            </div>
        </div>

        <!-- Google 4-Dots Animated Visualizer -->
        <div id="vd-google-visualizer" aria-hidden="true">
            <div class="vd-google-dots">
                <span class="vd-gdot vd-gdot-blue"></span>
                <span class="vd-gdot vd-gdot-red"></span>
                <span class="vd-gdot vd-gdot-yellow"></span>
                <span class="vd-gdot vd-gdot-green"></span>
            </div>
            <div id="vd-waveform" class="vd-waveform-bars">
                <?php for ( $i = 0; $i < 9; $i++ ) : ?>
                    <div class="vd-bar"></div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Live Transcript / Hero Status Area -->
        <div id="vd-transcript-pill" aria-live="polite">
            <div class="vd-transcript-header">
                <span id="vd-transcript-label"><?php esc_html_e( 'TRỢ LÝ SẴN SÀNG', 'voice-detected' ); ?></span>
                <span id="vd-live-indicator"></span>
            </div>
            <div id="vd-transcript-text"><?php esc_html_e( 'Nhấn mic hoặc gõ lệnh để bắt đầu…', 'voice-detected' ); ?></div>
        </div>

        <!-- Suggestion Chips Bar (Click-to-execute) -->
        <div id="vd-suggestion-chips" aria-label="<?php esc_attr_e( 'Gợi ý lệnh nhanh', 'voice-detected' ); ?>">
            <?php foreach ( $chips_list as $chip_text ) :
                // Strip emoji for data-cmd fallback
                $clean_cmd = preg_replace( '/^[^a-zA-Z0-9\x{00C0}-\x{024F}\x{1E00}-\x{1EFF}]+/u', '', $chip_text );
                $cmd_val   = trim( $clean_cmd ?: $chip_text );
            ?>
                <button type="button" class="vd-chip" data-cmd="<?php echo esc_attr( $cmd_val ); ?>">
                    <?php echo esc_html( $chip_text ); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Results area (search results / messages / rich cards) -->
        <div id="vd-results-panel"></div>

        <!-- Text command input row with integrated Big-Tech mic pill -->
        <div class="vd-text-input-row">
            <div class="vd-input-wrap">
                <input
                    type="text"
                    id="vd-text-cmd"
                    placeholder="<?php esc_attr_e( 'Nói hoặc gõ lệnh, ví dụ: tìm bài viết mới nhất…', 'voice-detected' ); ?>"
                    autocomplete="off"
                    spellcheck="false"
                    aria-label="<?php esc_attr_e( 'Nhập lệnh văn bản', 'voice-detected' ); ?>"
                >
                <button id="vd-clear-input" type="button" title="<?php esc_attr_e( 'Xóa text', 'voice-detected' ); ?>" style="display:none;">✕</button>
            </div>
            <button id="vd-text-submit" title="<?php esc_attr_e( 'Gửi lệnh (Enter)', 'voice-detected' ); ?>">
                <svg viewBox="0 0 24 24" fill="none"><path d="M2 12L22 2L12 22L10 13L2 12Z" fill="currentColor"/></svg>
            </button>
            <button id="vd-record-btn" class="vd-record-pulse-btn" title="<?php esc_attr_e( 'Nhấn để nói (Alt+V)', 'voice-detected' ); ?>">
                <svg id="vd-record-icon" viewBox="0 0 24 24" fill="none">
                    <path d="M12 1C10.34 1 9 2.34 9 4V12C9 13.66 10.34 15 12 15C13.66 15 15 13.66 15 12V4C15 2.34 13.66 1 12 1Z" fill="currentColor"/>
                    <path d="M19 12C19 15.87 15.87 19 12 19C8.13 19 5 15.87 5 12H3C3 16.97 6.93 21.07 12 21.51V23H14V21.51C19.07 21.07 23 16.97 23 12H19Z" fill="currentColor"/>
                </svg>
            </button>
        </div>

        <!-- Command history log with header -->
        <div class="vd-log-header">
            <span><?php esc_html_e( 'Lịch sử tương tác', 'voice-detected' ); ?></span>
            <button id="vd-clear-log" title="<?php esc_attr_e( 'Xóa lịch sử', 'voice-detected' ); ?>"><?php esc_html_e( 'Xóa', 'voice-detected' ); ?></button>
        </div>
        <div id="vd-cmd-log" aria-live="polite" aria-label="<?php esc_attr_e( 'Lịch sử lệnh', 'voice-detected' ); ?>">
            <div class="vd-log-empty"><?php esc_html_e( '✨ Chưa có lệnh nào. Thử bấm gợi ý ở trên hoặc nhấn mic để nói!', 'voice-detected' ); ?></div>
        </div>

        <!-- Quick commands help (collapsible) -->
        <div id="vd-help-panel" class="vd-collapsible">
            <div class="vd-help-grid">
                <div class="vd-help-cmd"><code>tìm [từ khóa]</code><span><?php esc_html_e( 'Tìm bài viết ngay', 'voice-detected' ); ?></span></div>
                <div class="vd-help-cmd"><code>mở bài [tên]</code><span><?php esc_html_e( 'Mở trực tiếp bài viết', 'voice-detected' ); ?></span></div>
                <div class="vd-help-cmd"><code>về trang chủ</code><span><?php esc_html_e( 'Về trang chủ', 'voice-detected' ); ?></span></div>
                <div class="vd-help-cmd"><code>cuộn xuống / lên</code><span><?php esc_html_e( 'Cuộn trang web', 'voice-detected' ); ?></span></div>
                <div class="vd-help-cmd"><code>lên đầu / cuối</code><span><?php esc_html_e( 'Đầu hoặc cuối trang', 'voice-detected' ); ?></span></div>
                <div class="vd-help-cmd"><code>quay lại</code><span><?php esc_html_e( 'Trang trước đó', 'voice-detected' ); ?></span></div>
                <div class="vd-help-cmd"><code>Alt + V</code><span><?php esc_html_e( 'Phím tắt bật mic', 'voice-detected' ); ?></span></div>
                <div class="vd-help-cmd"><code>Esc</code><span><?php esc_html_e( 'Đóng bảng trợ lý', 'voice-detected' ); ?></span></div>
            </div>
        </div>
    </div><!-- /#vd-command-panel -->
</div><!-- /#vd-widget-root -->

<!-- Frosted Backdrop Blur Overlay (Google / YouTube style) -->
<div id="vd-overlay" aria-hidden="true"></div>

