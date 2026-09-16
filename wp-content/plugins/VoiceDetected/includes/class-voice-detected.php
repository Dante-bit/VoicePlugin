<?php
/**
 * Main VoiceDetected plugin class.
 * Registers all hooks, enqueues assets, and wires up sub-modules.
 */

defined( 'ABSPATH' ) || exit;

class VoiceDetected {

    /** @var VoiceDetected|null Singleton */
    private static ?VoiceDetected $instance = null;

    /** @var array Plugin settings */
    private array $settings = [];

    public static function get_instance(): VoiceDetected {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = (array) get_option( VD_OPTION_KEY, [] );

        // Boot sub-modules
        VD_Admin::init();
        VD_Ajax::init();
        VD_Shortcode::init();

        // Frontend hooks
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend' ] );
        add_action( 'wp_footer',          [ $this, 'render_widget' ] );

        // Load text domain
        add_action( 'init', [ $this, 'load_textdomain' ] );
    }

    public function load_textdomain(): void {
        load_plugin_textdomain( 'voice-detected', false, dirname( plugin_basename( VD_PLUGIN_FILE ) ) . '/languages' );
    }

    /** Whether the widget should be visible on the current page. */
    private function is_widget_enabled(): bool {
        if ( empty( $this->settings['enabled'] ) ) {
            return false;
        }
        $show_on = $this->settings['show_on'] ?? 'all';
        if ( 'all' === $show_on ) {
            return true;
        }
        if ( 'frontend' === $show_on && ! is_admin() ) {
            return true;
        }
        if ( 'backend' === $show_on && is_admin() ) {
            return true;
        }
        if ( 'custom' === $show_on ) {
            $ids = array_map( 'intval', (array) ( $this->settings['custom_pages'] ?? [] ) );
            return in_array( get_the_ID(), $ids, true );
        }
        return false;
    }

    public function enqueue_frontend(): void {
        if ( ! $this->is_widget_enabled() ) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'vd-widget',
            VD_PLUGIN_URL . 'assets/css/voice-widget.css',
            [],
            VD_VERSION
        );

        // JS – voice engine (MediaRecorder + Google STT)
        wp_enqueue_script(
            'vd-engine',
            VD_PLUGIN_URL . 'assets/js/voice-engine.js',
            [],
            VD_VERSION,
            true
        );

        // JS – command parser
        wp_enqueue_script(
            'vd-commands',
            VD_PLUGIN_URL . 'assets/js/voice-commands.js',
            [ 'vd-engine' ],
            VD_VERSION,
            true
        );

        // JS – widget UI
        wp_enqueue_script(
            'vd-widget',
            VD_PLUGIN_URL . 'assets/js/voice-widget.js',
            [ 'vd-commands' ],
            VD_VERSION,
            true
        );

        // Dynamic inline CSS for custom accent color & size
        $accent_color = ! empty( $this->settings['accent_color'] ) ? $this->settings['accent_color'] : '#6366F1';
        $widget_size  = $this->settings['widget_size'] ?? 'medium';
        $size_px      = '56px';
        if ( $widget_size === 'small' ) {
            $size_px = '46px';
        } elseif ( $widget_size === 'large' ) {
            $size_px = '66px';
        }

        $custom_css = "
            :root {
                --vd-accent: {$accent_color};
                --vd-accent-light: {$accent_color};
                --vd-accent-glow: {$accent_color}66;
                --vd-btn-size: {$size_px};
            }
        ";
        wp_add_inline_style( 'vd-widget', $custom_css );

        // Localized data – gắn vào vd-engine (script đầu tiên) để VD_Config
        // luôn sẵn sàng trước khi voice-commands.js và voice-widget.js chạy.
        wp_localize_script( 'vd-engine', 'VD_Config', [
            'ajax_url'          => admin_url( 'admin-ajax.php' ),
            'nonce'             => wp_create_nonce( 'vd_nonce' ),
            'api_engine'        => $this->settings['api_engine']        ?? 'webspeech',
            'language'          => $this->settings['language']          ?? 'vi-VN',
            'trigger_word'      => $this->settings['trigger_word']      ?? '',
            'confidence'        => floatval( $this->settings['confidence_min'] ?? 0.6 ),
            'position'          => $this->settings['widget_position']   ?? 'bottom-right',
            'theme'             => $this->settings['widget_theme']      ?? 'glass',
            'home_url'          => trailingslashit( get_option( 'home' ) ),   // Frontend URL (không phải wp-admin)
            'search_url'        => trailingslashit( get_option( 'home' ) ) . '?s=',
            'site_name'         => get_bloginfo( 'name' ),
            'is_single'         => is_singular(),
            'current_post_id'   => is_singular() ? get_the_ID() : 0,
            'current_title'     => is_singular() ? get_the_title() : '',
            'accent_color'      => $accent_color,
            'widget_size'       => $widget_size,
            'widget_title'      => $this->settings['widget_title']      ?? 'Trợ lý ảo AI',
            'audio_filter'      => ! empty( $this->settings['audio_filter'] ),
            'mic_sensitivity'   => $this->settings['mic_sensitivity']   ?? 'normal',
            'sound_effects'     => ! empty( $this->settings['sound_effects'] ),
            'tts_voice'         => ! empty( $this->settings['tts_voice'] ),
            'tts_speed'         => floatval( $this->settings['tts_speed'] ?? 1.0 ),
            'suggestion_chips'  => $this->settings['suggestion_chips']  ?? '🏠 Trang chủ, 🔥 Mới nhất hôm nay, ⬇️ Cuộn xuống, 🔝 Đầu trang, 📞 Liên hệ',
            'keyboard_shortcut' => $this->settings['keyboard_shortcut'] ?? 'Alt+V',
            'scroll_step'       => intval( $this->settings['scroll_step'] ?? 450 ),
            'i18n'              => [
                'listening'      => __( 'Đang nghe…',                              'voice-detected' ),
                'processing'     => __( 'Đang xử lý…',                             'voice-detected' ),
                'no_command'     => __( 'Không nhận ra lệnh. Thử lại!',            'voice-detected' ),
                'mic_blocked'    => __( 'Trình duyệt chặn microphone. Vui lòng cấp quyền.', 'voice-detected' ),
                'click_to_start' => __( 'Nhấn để bắt đầu nói',                    'voice-detected' ),
            ],
        ] );
    }

    public function render_widget(): void {
        if ( ! $this->is_widget_enabled() ) {
            return;
        }
        $template = VD_PLUGIN_DIR . 'templates/widget-frontend.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }
}
