<?php
/**
 * Chạy khi người dùng gỡ cài đặt (Delete) plugin từ trang Plugins.
 * Dọn sạch toàn bộ dữ liệu (options) mà plugin đã tạo ra.
 *
 * @package TTS_Reader
 */

// WordPress chỉ include file này khi người dùng thật sự bấm "Delete",
// hằng số dưới đây dùng để chặn truy cập trực tiếp file.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'tts_reader_settings' );

// Nếu plugin từng dùng cho multisite và lưu theo từng site, dọn luôn ở đây (tuỳ chọn):
if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		delete_option( 'tts_reader_settings' );
		restore_current_blog();
	}
}
