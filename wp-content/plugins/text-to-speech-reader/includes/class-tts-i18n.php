<?php
/**
 * Xử lý đa ngôn ngữ cho plugin.
 *
 * @package TTS_Reader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTS_i18n {

	/**
	 * Nạp file dịch (.mo) trong thư mục /languages.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			TTS_READER_TEXT_DOMAIN,
			false,
			dirname( TTS_READER_BASENAME ) . '/languages/'
		);
	}
}
