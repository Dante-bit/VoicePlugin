<?php
/**
 * Admin settings page for Voice Detected.
 */

defined( 'ABSPATH' ) || exit;

class VD_Admin {

    public static function init(): void {
        add_action( 'admin_menu',            [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init',            [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
    }

    public static function add_menu(): void {
        add_menu_page(
            __( 'Voice Detected', 'voice-detected' ),
            __( 'Voice Detected', 'voice-detected' ),
            'manage_options',
            'voice-detected',
            [ __CLASS__, 'render_settings_page' ],
            'dashicons-microphone',
            81
        );

        add_submenu_page(
            'voice-detected',
            __( 'Cài đặt', 'voice-detected' ),
            __( 'Cài đặt', 'voice-detected' ),
            'manage_options',
            'voice-detected',
            [ __CLASS__, 'render_settings_page' ]
        );

        add_submenu_page(
            'voice-detected',
            __( 'Lịch sử lệnh', 'voice-detected' ),
            __( 'Lịch sử lệnh', 'voice-detected' ),
            'manage_options',
            'voice-detected-history',
            [ __CLASS__, 'render_history_page' ]
        );
    }

    public static function register_settings(): void {
        register_setting( 'voice_detected_group', VD_OPTION_KEY, [
            'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
        ] );
    }

    public static function sanitize_settings( array $input ): array {
        $clean = [];
        $clean['api_key']           = sanitize_text_field( $input['api_key']         ?? '' );
        $clean['api_engine']        = in_array( $input['api_engine'] ?? '', [ 'google', 'gemini', 'groq', 'webspeech' ], true )
                                        ? $input['api_engine'] : 'webspeech';
        $clean['language']          = sanitize_text_field( $input['language']         ?? 'vi-VN' );
        $clean['trigger_word']      = sanitize_text_field( $input['trigger_word']     ?? '' );
        $clean['show_on']           = in_array( $input['show_on'] ?? '', [ 'all', 'frontend', 'backend', 'custom' ], true )
                                        ? $input['show_on'] : 'all';
        $clean['custom_pages']      = array_map( 'intval', (array) ( $input['custom_pages'] ?? [] ) );
        $clean['show_history']      = ! empty( $input['show_history'] );
        $clean['max_history']       = max( 10, min( 500, intval( $input['max_history'] ?? 50 ) ) );
        $clean['widget_position']   = in_array( $input['widget_position'] ?? '', [ 'bottom-right', 'bottom-left', 'top-right', 'top-left' ], true )
                                        ? $input['widget_position'] : 'bottom-right';
        $clean['widget_theme']      = in_array( $input['widget_theme'] ?? '', [ 'dark', 'light', 'glass' ], true )
                                        ? $input['widget_theme'] : 'glass';
        $clean['confidence_min']    = max( 0.0, min( 1.0, floatval( $input['confidence_min'] ?? 0.6 ) ) );
        $clean['enabled']           = ! empty( $input['enabled'] );

        // ─── New UI & Audio Customization Options ─────────────────
        $accent = sanitize_hex_color( $input['accent_color'] ?? '' );
        $clean['accent_color']      = $accent ? $accent : '#6366F1';
        $clean['widget_size']       = in_array( $input['widget_size'] ?? '', [ 'small', 'medium', 'large' ], true )
                                        ? $input['widget_size'] : 'medium';
        $clean['widget_title']      = sanitize_text_field( $input['widget_title'] ?? 'Trợ lý ảo AI' );
        $clean['audio_filter']      = ! empty( $input['audio_filter'] );
        $clean['mic_sensitivity']   = in_array( $input['mic_sensitivity'] ?? '', [ 'fast', 'normal', 'relaxed' ], true )
                                        ? $input['mic_sensitivity'] : 'normal';
        $clean['sound_effects']     = ! empty( $input['sound_effects'] );
        $clean['tts_voice']         = ! empty( $input['tts_voice'] );
        $clean['tts_speed']         = max( 0.7, min( 1.5, floatval( $input['tts_speed'] ?? 1.0 ) ) );
        $clean['suggestion_chips']  = sanitize_text_field( $input['suggestion_chips'] ?? '🏠 Trang chủ, 🔥 Mới nhất hôm nay, ⬇️ Cuộn xuống, 🔝 Đầu trang, 📞 Liên hệ' );
        $clean['keyboard_shortcut'] = sanitize_text_field( $input['keyboard_shortcut'] ?? 'Alt+V' );
        $clean['scroll_step']       = max( 150, min( 1500, intval( $input['scroll_step'] ?? 450 ) ) );

        return $clean;
    }

    public static function enqueue_admin_assets( string $hook ): void {
        if ( ! in_array( $hook, [ 'toplevel_page_voice-detected', 'voice-detected_page_voice-detected-history' ], true ) ) {
            return;
        }

        wp_enqueue_style(
            'vd-admin',
            VD_PLUGIN_URL . 'assets/css/admin.css',
            [],
            VD_VERSION
        );

        wp_enqueue_script(
            'vd-admin',
            VD_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            VD_VERSION,
            true
        );

        wp_localize_script( 'vd-admin', 'VD_Admin', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'vd_nonce' ),
        ] );
    }

    public static function render_settings_page(): void {
        $template = VD_PLUGIN_DIR . 'templates/admin-settings.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }

    public static function render_history_page(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'vd_history';
        $rows  = $wpdb->get_results(
            "SELECT h.*, u.display_name FROM {$table} h
             LEFT JOIN {$wpdb->users} u ON u.ID = h.user_id
             ORDER BY h.created_at DESC LIMIT 200"
        );
        ?>
        <div class="wrap vd-history-wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-microphone"></span>
                <?php esc_html_e( 'Lịch sử lệnh giọng nói', 'voice-detected' ); ?>
            </h1>
            <table class="widefat striped vd-history-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php esc_html_e( 'Người dùng', 'voice-detected' ); ?></th>
                        <th><?php esc_html_e( 'Transcript', 'voice-detected' ); ?></th>
                        <th><?php esc_html_e( 'Lệnh', 'voice-detected' ); ?></th>
                        <th><?php esc_html_e( 'Độ tin cậy', 'voice-detected' ); ?></th>
                        <th><?php esc_html_e( 'Thực thi', 'voice-detected' ); ?></th>
                        <th><?php esc_html_e( 'Thời gian', 'voice-detected' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <tr><td colspan="7" style="text-align:center;"><?php esc_html_e( 'Chưa có lịch sử.', 'voice-detected' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $rows as $row ) : ?>
                            <tr>
                                <td><?php echo esc_html( $row->id ); ?></td>
                                <td><?php echo esc_html( $row->display_name ?: 'Guest' ); ?></td>
                                <td><?php echo esc_html( $row->transcript ); ?></td>
                                <td><code><?php echo esc_html( $row->command ); ?></code></td>
                                <td><?php echo esc_html( number_format( $row->confidence * 100, 1 ) ); ?>%</td>
                                <td><?php echo $row->executed ? '<span class="vd-badge vd-badge-ok">✓</span>' : '<span class="vd-badge vd-badge-fail">✗</span>'; ?></td>
                                <td><?php echo esc_html( $row->created_at ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
