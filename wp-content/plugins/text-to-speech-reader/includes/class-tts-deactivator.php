<?php
/**
 * Xử lý logic khi plugin bị gỡ kích hoạt (deactivate).
 * Lưu ý: KHÔNG xoá dữ liệu ở đây — việc xoá dữ liệu (options) được
 * xử lý trong uninstall.php để đảm bảo dữ liệu chỉ mất khi người dùng
 * thật sự gỡ cài đặt (delete) plugin.
 *
 * @package TTS_Reader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTS_Deactivator {

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
