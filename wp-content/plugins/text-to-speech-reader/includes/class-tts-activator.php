<?php
/**
 * Xử lý logic khi plugin được kích hoạt.
 *
 * @package TTS_Reader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTS_Activator {

	/**
	 * Thiết lập giá trị mặc định cho các tuỳ chọn (options) khi kích hoạt lần đầu.
	 */
	public static function activate() {
		$defaults = array(
			'enabled_post_types' => array( 'post' ),
			'button_position'    => 'before', // before | after | both
			'button_text'        => __( 'Nghe bài viết', 'tts-reader' ),
			'default_lang'       => 'vi-VN',
			'default_rate'       => '1',
		);

		// add_option sẽ không ghi đè nếu option đã tồn tại (an toàn khi update plugin).
		add_option( 'tts_reader_settings', $defaults );

		flush_rewrite_rules();
	}
}
