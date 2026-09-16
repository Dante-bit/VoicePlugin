<?php
/**
 * Plugin Name:       Text to Speech Reader
 * Plugin URI:         https://example.com/plugins/text-to-speech-reader
 * Description:        Thêm nút "Đọc bài viết" vào bài viết/trang, sử dụng giọng nói của trình duyệt (Web Speech API) để đọc to nội dung. Hỗ trợ chọn giọng đọc, tốc độ, tạm dừng/tiếp tục.
 * Version:             2.0.0
 * Requires at least:   5.8
 * Requires PHP:        7.4
 * Author:              Your Name
 * Author URI:          https://example.com
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         tts-reader
 * Domain Path:         /languages
 */

// Không cho phép truy cập trực tiếp file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ------------------------------------------------------------------
 * HẰNG SỐ CỦA PLUGIN
 * ------------------------------------------------------------------
 */
define( 'TTS_READER_VERSION', '2.0.0' );
define( 'TTS_READER_FILE', __FILE__ );
define( 'TTS_READER_PATH', plugin_dir_path( __FILE__ ) );
define( 'TTS_READER_URL', plugin_dir_url( __FILE__ ) );
define( 'TTS_READER_BASENAME', plugin_basename( __FILE__ ) );
define( 'TTS_READER_TEXT_DOMAIN', 'tts-reader' );

/**
 * ------------------------------------------------------------------
 * NẠP CÁC FILE CLASS (mỗi thành phần một file riêng)
 * ------------------------------------------------------------------
 */
require_once TTS_READER_PATH . 'includes/class-tts-loader.php';
require_once TTS_READER_PATH . 'includes/class-tts-i18n.php';
require_once TTS_READER_PATH . 'includes/class-tts-settings.php';
require_once TTS_READER_PATH . 'includes/class-tts-admin.php';
require_once TTS_READER_PATH . 'includes/class-tts-public.php';
require_once TTS_READER_PATH . 'includes/class-tts-shortcode.php';
require_once TTS_READER_PATH . 'includes/class-tts-proxy.php';
require_once TTS_READER_PATH . 'includes/class-tts-activator.php';
require_once TTS_READER_PATH . 'includes/class-tts-deactivator.php';

/**
 * ------------------------------------------------------------------
 * HOOK KÍCH HOẠT / GỠ KÍCH HOẠT
 * ------------------------------------------------------------------
 */
register_activation_hook( __FILE__, array( 'TTS_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TTS_Deactivator', 'deactivate' ) );

/**
 * ------------------------------------------------------------------
 * KHỞI CHẠY PLUGIN
 * ------------------------------------------------------------------
 */
function tts_reader_run() {
	$plugin = new TTS_Loader();
	$plugin->run();
}
tts_reader_run();
