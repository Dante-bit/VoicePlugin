<?php
/**
 * Xử lý phần hiển thị ngoài trang (front-end):
 * - Nạp CSS/JS v3.0
 * - Tự động chèn thanh điều khiển "Nghe bài viết" vào nội dung
 * - Hỗ trợ bộ chọn giọng AI, highlight theo câu và tải MP3
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

		// Truyền cấu hình PHP -> JavaScript đầy đủ các tính năng v3.0
		wp_localize_script(
			'tts-reader-script',
			'ttsReaderConfig',
			array(
				'lang'            => $settings['default_lang'],
				'rate'            => (float) $settings['default_rate'],
				'engine'          => $settings['engine'],
				'enableHighlight' => ! empty( $settings['enable_highlight'] ),
				'highlightColor'  => $settings['highlight_color'],
				'autoScroll'      => ! empty( $settings['auto_scroll'] ),
				'enableDownload'  => ! empty( $settings['enable_download'] ),
				'proxyUrl'        => admin_url( 'admin-ajax.php' ),
				'i18n'              => array(
					'play'              => __( 'Nghe bài viết', 'tts-reader' ),
					'pause'             => __( 'Tạm dừng', 'tts-reader' ),
					'resume'            => __( 'Tiếp tục', 'tts-reader' ),
					'stop'              => __( 'Dừng', 'tts-reader' ),
					'loading'           => __( 'Đang tải...', 'tts-reader' ),
					'downloading'       => __( 'Đang chuẩn bị MP3...', 'tts-reader' ),
					'downloadReady'     => __( 'Đã tải xong!', 'tts-reader' ),
					'downloadError'     => __( 'Lỗi tải MP3. Thử lại sau.', 'tts-reader' ),
					'unsupported'       => __( 'Trình duyệt của bạn không hỗ trợ đọc văn bản.', 'tts-reader' ),
					'noVietnameseVoice' => __( 'Không tìm thấy giọng đọc tiếng Việt trên thiết bị này.', 'tts-reader' ),
					'notGoogleVoice'    => __( 'Đang dùng giọng tiếng Việt của hệ thống.', 'tts-reader' ),
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

		// Tránh chèn trùng lặp nếu the_content chạy nhiều lần.
		if ( false !== strpos( $content, 'tts-reader-wrapper' ) ) {
			return $content;
		}

		$settings = TTS_Settings::get_settings();
		$button   = $this->get_button_html( $settings );

		// Bọc nội dung trong 1 thẻ có ID khớp với data-tts-target của nút.
		$source_id       = 'tts-reader-source-' . get_the_ID();
		$wrapped_content = sprintf( '<div id="%s" class="tts-content-container">%s</div>', esc_attr( $source_id ), $content );

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
	 * Sinh HTML cho khối nút + thanh điều khiển đọc v3.0.
	 *
	 * @param array $settings Mảng cài đặt của plugin.
	 * @return string HTML player.
	 */
	public function get_button_html( $settings = array() ) {
		if ( empty( $settings ) ) {
			$settings = TTS_Settings::get_settings();
		}

		$label      = ! empty( $settings['button_text'] ) ? $settings['button_text'] : __( 'Nghe bài viết', 'tts-reader' );
		$post_id    = get_the_ID();
		$post_title = get_the_title( $post_id );

		ob_start();
		?>
		<div class="tts-reader-wrapper"
			 data-tts-target="#tts-reader-source-<?php echo esc_attr( $post_id ); ?>"
			 data-tts-title="<?php echo esc_attr( $post_title ? $post_title : 'bai-viet' ); ?>"
			 data-tts-color="<?php echo esc_attr( $settings['highlight_color'] ); ?>">

			<!-- Nút phát chính -->
			<button type="button" class="tts-reader-btn tts-reader-play" aria-label="<?php echo esc_attr( $label ); ?>">
				<span class="tts-icon tts-icon-play" data-state="idle" aria-hidden="true">
					<svg class="tts-svg-play" viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M8 5v14l11-7z"/></svg>
					<svg class="tts-svg-pause" viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
				</span>
				<span class="tts-label"><?php echo esc_html( $label ); ?></span>
			</button>

			<!-- Hiệu ứng sóng âm mini khi đang phát -->
			<div class="tts-wave" aria-hidden="true" style="display:none;">
				<span></span><span></span><span></span><span></span><span></span>
			</div>

			<!-- Nút dừng phát -->
			<button type="button" class="tts-reader-btn tts-reader-stop" style="display:none;" aria-label="<?php esc_attr_e( 'Dừng đọc', 'tts-reader' ); ?>" title="<?php esc_attr_e( 'Dừng đọc', 'tts-reader' ); ?>">
				<span class="tts-icon tts-icon-stop" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M6 6h12v12H6z"/></svg>
				</span>
			</button>



			<!-- Bộ chọn tốc độ đọc -->
			<div class="tts-control-item tts-speed-wrapper" title="<?php esc_attr_e( 'Tốc độ đọc', 'tts-reader' ); ?>">
				<span class="tts-control-icon" aria-hidden="true">⚡</span>
				<select class="tts-reader-speed" aria-label="<?php esc_attr_e( 'Tốc độ đọc', 'tts-reader' ); ?>">
					<option value="0.75" <?php selected( $settings['default_rate'], '0.75' ); ?>>0.75x</option>
					<option value="1" <?php selected( $settings['default_rate'], '1' ); ?>>1.0x</option>
					<option value="1.25" <?php selected( $settings['default_rate'], '1.25' ); ?>>1.25x</option>
					<option value="1.5" <?php selected( $settings['default_rate'], '1.5' ); ?>>1.5x</option>
				</select>
			</div>

			<!-- Nút Tải xuống file MP3 -->
			<?php if ( ! empty( $settings['enable_download'] ) ) : ?>
			<button type="button" class="tts-reader-btn tts-reader-download" title="<?php esc_attr_e( 'Tải bản ghi âm MP3 nghe offline', 'tts-reader' ); ?>" aria-label="<?php esc_attr_e( 'Tải MP3', 'tts-reader' ); ?>">
				<span class="tts-download-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM17 13l-5 5-5-5h3V9h4v4h3z"/></svg>
				</span>
				<span class="tts-download-spinner" style="display:none;" aria-hidden="true"></span>
				<span class="tts-download-label"><?php esc_html_e( 'Tải MP3', 'tts-reader' ); ?></span>
			</button>
			<?php endif; ?>

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
