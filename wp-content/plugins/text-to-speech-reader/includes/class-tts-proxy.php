<?php
/**
 * Proxy AJAX: tải audio từ Google Translate TTS API về server
 * rồi trả về cho trình duyệt, tránh vấn đề CORS và mixed-content.
 *
 * Endpoint: wp-admin/admin-ajax.php?action=tts_reader_proxy
 *
 * @package TTS_Reader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTS_Proxy {

	/**
	 * Đăng ký AJAX action (cả logged-in lẫn khách/guest).
	 */
	public function register() {
		add_action( 'wp_ajax_tts_reader_proxy',        array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_tts_reader_proxy', array( $this, 'handle' ) );
	}

	/**
	 * Xử lý yêu cầu proxy âm thanh từ Google Translate TTS.
	 * Trả về audio/mpeg stream cho trình duyệt.
	 */
	public function handle() {
		$text = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
		$lang = isset( $_GET['l'] ) ? sanitize_text_field( wp_unslash( $_GET['l'] ) ) : 'vi';

		// Giới hạn độ dài văn bản mỗi chunk (Google TTS tối ưu dưới 200 ký tự).
		$text = mb_substr( $text, 0, 200 );

		if ( empty( $text ) ) {
			wp_die( '', '', array( 'response' => 400 ) );
		}

		// Chỉ cho phép mã ngôn ngữ hợp lệ.
		$lang = preg_replace( '/[^a-z\-]/i', '', $lang );
		$lang = substr( explode( '-', $lang )[0], 0, 5 );
		if ( empty( $lang ) ) {
			$lang = 'vi';
		}

		$google_url = add_query_arg(
			array(
				'ie'     => 'UTF-8',
				'tl'     => rawurlencode( $lang ),
				'client' => 'tw-ob',
				'q'      => rawurlencode( $text ),
			),
			'https://translate.google.com/translate_tts'
		);

		$response = wp_remote_get(
			$google_url,
			array(
				'timeout'    => 15,
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
				'headers'    => array(
					'Referer' => 'https://translate.google.com/',
				),
				'sslverify'  => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_die( '', '', array( 'response' => 502 ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			wp_die( '', '', array( 'response' => 502 ) );
		}

		$body = wp_remote_retrieve_body( $response );

		// Gửi header cache và content type phù hợp.
		header( 'Content-Type: audio/mpeg' );
		header( 'Content-Length: ' . strlen( $body ) );
		header( 'Cache-Control: public, max-age=86400' );
		header( 'X-Robots-Tag: noindex' );

		// Tắt mọi output buffer của WordPress trước khi xuất audio.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $body;
		exit;
	}
}
