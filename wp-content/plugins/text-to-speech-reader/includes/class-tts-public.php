<?php
/**
 * Xử lý phần hiển thị ngoài trang (front-end):
 * - Nạp CSS/JS
 * - Tự động chèn nút "Nghe bài viết" vào nội dung (the_content)
 *
 * @package TTS_Reader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTS_Public {

	/**
	 * Nạp CSS/JS ở front-end, chỉ khi đang ở trang single của loại nội dung được bật.
	 */
	public function enqueue_assets() {
		if ( ! $this->should_display() ) {
			return;
		}

		wp_enqueue_style(
			'tts-reader-style',
			TTS_READER_URL . 'assets/css/tts-reader.css',
			array(),
			TTS_READER_VERSION
		);

		wp_enqueue_script(
			'tts-reader-script',
			TTS_READER_URL . 'assets/js/tts-reader.js',
			array(),
			TTS_READER_VERSION,
			true
		);

		$settings = TTS_Settings::get_settings();

		// Truyền cấu hình PHP -> JavaScript một cách an toàn.
		wp_localize_script(
			'tts-reader-script',
			'ttsReaderConfig',
			array(
				'lang'     => $settings['default_lang'],
				'rate'     => (float) $settings['default_rate'],
				'engine'   => $settings['engine'],
				'proxyUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'     => array(
					'play'              => __( 'Nghe bài viết', 'tts-reader' ),
					'pause'             => __( 'Tạm dừng', 'tts-reader' ),
					'resume'            => __( 'Tiếp tục', 'tts-reader' ),
					'stop'              => __( 'Dừng', 'tts-reader' ),
					'loading'           => __( 'Đang tải...', 'tts-reader' ),
					'unsupported'       => __( 'Trình duyệt của bạn không hỗ trợ đọc văn bản.', 'tts-reader' ),
					'noVietnameseVoice' => __( 'Không tìm thấy giọng đọc tiếng Việt trên thiết bị này. Hãy thử mở bằng Chrome/Edge, đảm bảo có mạng, rồi bấm nghe lại.', 'tts-reader' ),
					'notGoogleVoice'    => __( 'Đang dùng giọng tiếng Việt của hệ thống (không phải giọng Google). Mở bằng Chrome/Edge và có kết nối mạng để dùng giọng Google Tiếng Việt.', 'tts-reader' ),
					'error'             => __( 'Không thể tải giọng đọc. Vui lòng kiểm tra kết nối mạng.', 'tts-reader' ),
				),
			)
		);
	}

	/**
	 * Tự động chèn nút đọc vào nội dung bài viết thông qua filter the_content.
	 *
	 * @param string $content Nội dung gốc.
	 * @return string Nội dung đã (có thể) được chèn thêm nút.
	 */
	public function inject_reader_button( $content ) {
		if ( ! $this->should_display() ) {
			return $content;
		}

		// Tránh chèn trùng lặp nếu the_content chạy nhiều lần (ví dụ trong widget).
		if ( false !== strpos( $content, 'tts-reader-wrapper' ) ) {
			return $content;
		}

		$settings = TTS_Settings::get_settings();
		$button   = $this->get_button_html( $settings['button_text'] );

		// Bọc nội dung trong 1 thẻ có ID khớp với data-tts-target của nút,
		// để JavaScript biết chính xác cần đọc đoạn văn bản nào.
		$source_id      = 'tts-reader-source-' . get_the_ID();
		$wrapped_content = sprintf( '<div id="%s">%s</div>', esc_attr( $source_id ), $content );

		switch ( $settings['button_position'] ) {
			case 'after':
				return $wrapped_content . $button;
			case 'both':
				return $button . $wrapped_content . $button;
			case 'before':
			default:
				return $button . $wrapped_content;
		}
	}

	/**
	 * Sinh HTML cho khối nút + thanh điều khiển đọc.
	 * data-tts-content trỏ tới id của nội dung sẽ được đọc (lấy qua JS từ .entry-content/the_content).
	 */
	private function get_button_html( $label ) {
		ob_start();
		?>
		<div class="tts-reader-wrapper" data-tts-target="#tts-reader-source-<?php echo esc_attr( get_the_ID() ); ?>">
			<button type="button" class="tts-reader-btn tts-reader-play" aria-label="<?php echo esc_attr( $label ); ?>">
				<span class="tts-icon tts-icon-play" data-state="idle" aria-hidden="true"></span>
				<span class="tts-label"><?php echo esc_html( $label ); ?></span>
			</button>
			<div class="tts-wave" aria-hidden="true" style="display:none;">
				<span></span><span></span><span></span><span></span><span></span>
			</div>
			<button type="button" class="tts-reader-btn tts-reader-stop" style="display:none;" aria-label="<?php esc_attr_e( 'Dừng đọc', 'tts-reader' ); ?>">
				<span class="tts-icon tts-icon-stop" aria-hidden="true"></span>
			</button>
			<label class="tts-reader-speed-label">
				<?php esc_html_e( 'Tốc độ', 'tts-reader' ); ?>
				<select class="tts-reader-speed">
					<option value="0.75">0.75x</option>
					<option value="1" selected>1x</option>
					<option value="1.25">1.25x</option>
					<option value="1.5">1.5x</option>
					<option value="2">2x</option>
				</select>
			</label>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Kiểm tra điều kiện có nên hiển thị tính năng đọc hay không.
	 */
	private function should_display() {
		if ( ! is_singular() ) {
			return false;
		}

		$settings = TTS_Settings::get_settings();
		return in_array( get_post_type(), (array) $settings['enabled_post_types'], true );
	}
}
