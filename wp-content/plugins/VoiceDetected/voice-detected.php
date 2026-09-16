<?php
/**
 * Plugin Name:       Voice Detected
 * Plugin URI:        https://github.com/your-repo/voice-detected
 * Description:       Nhận dạng giọng nói thông minh – mở bài viết, tìm kiếm, điều hướng, cuộn trang và điều khiển form bằng giọng nói. Hỗ trợ Tiếng Việt & Tiếng Anh với Google Cloud Speech-to-Text API.
 * Version:           1.0.8
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            VoiceDetected Team
 * Author URI:        #
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       voice-detected
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

// ─── Constants ───────────────────────────────────────────────────────────────
define( 'VD_VERSION',     '1.0.8' );
define( 'VD_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'VD_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'VD_PLUGIN_FILE', __FILE__ );
define( 'VD_OPTION_KEY',  'voice_detected_settings' );

// ─── Autoload includes ────────────────────────────────────────────────────────
$includes = [
    'includes/class-vd-api.php',
    'includes/class-vd-ajax.php',
    'includes/class-vd-shortcode.php',
    'includes/class-vd-admin.php',
    'includes/class-voice-detected.php',
];

foreach ( $includes as $file ) {
    $path = VD_PLUGIN_DIR . $file;
    if ( file_exists( $path ) ) {
        require_once $path;
    }
}

// ─── Activation / Deactivation ───────────────────────────────────────────────
register_activation_hook( __FILE__, 'vd_activate' );
register_deactivation_hook( __FILE__, 'vd_deactivate' );

function vd_activate() {
    $defaults = [
        'api_key'          => '',
        'api_engine'       => 'google',   // 'google' | 'webspeech'
        'language'         => 'vi-VN',
        'trigger_word'     => '',
        'show_on'          => 'all',      // 'all' | 'frontend' | 'backend' | 'custom'
        'custom_pages'     => [],
        'show_history'     => true,
        'max_history'      => 50,
        'widget_position'  => 'bottom-right',
        'widget_theme'     => 'dark',
        'confidence_min'   => 0.6,
        'enabled'          => true,
    ];
    if ( ! get_option( VD_OPTION_KEY ) ) {
        add_option( VD_OPTION_KEY, $defaults );
    }

    // Create history table
    global $wpdb;
    $table   = $wpdb->prefix . 'vd_history';
    $charset = $wpdb->get_charset_collate();
    $sql     = "CREATE TABLE IF NOT EXISTS {$table} (
        id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id     BIGINT(20) UNSIGNED DEFAULT 0,
        transcript  TEXT NOT NULL,
        command     VARCHAR(100) DEFAULT '',
        confidence  FLOAT DEFAULT 0,
        executed    TINYINT(1) DEFAULT 0,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) {$charset};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    flush_rewrite_rules();
}

function vd_deactivate() {
    flush_rewrite_rules();
}

// ─── Boot ─────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
    VoiceDetected::get_instance();
} );
