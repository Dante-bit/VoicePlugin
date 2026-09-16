<?php
/**
 * Shortcode [tts_reader] — cho phép chèn thủ công khối đọc kèm nút đọc
 * ở bất kỳ đâu trong bài viết/trang (Elementor, Gutenberg, Shortcode block).
 *
 * Cách dùng:
 * [tts_reader voice="male_warm" rate="1.0" label="Nghe audio"]Đoạn văn bản cần đọc...[/tts_reader]
 *
 * @package TTS_Reader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTS_Shortcode {

	public function register() {
		add_shortcode( 'tts_reader', array( $this, 'render' ) );
	}

	/**
	 * @param array       $atts    Thuộc tính shortcode.
	 * @param string|null $content Nội dung bên trong shortcode.
	 */
	public function render( $atts, $content = null ) {
		$settings = TTS_Settings::get_settings();

		$atts = shortcode_atts(
			array(
				'label' => ! empty( $settings['button_text'] ) ? $settings['button_text'] : __( 'Nghe nội dung', 'tts-reader' ),
				'rate'  => $settings['default_rate'],
			),
			$atts,
			'tts_reader'
		);

		if ( empty( $content ) ) {
			return '';
		}

		static $instance = 0;
		$instance++;
		$source_id = 'tts-reader-source-shortcode-' . $instance;

		ob_start();
		?>
		<div class="tts-reader-wrapper tts-reader-shortcode"
			 data-tts-target="#<?php echo esc_attr( $source_id ); ?>"
			 data-tts-color="<?php echo esc_attr( $settings['highlight_color'] ); ?>">

			<button type="button" class="tts-reader-btn tts-reader-play" aria-label="<?php echo esc_attr( $atts['label'] ); ?>">
				<span class="tts-icon tts-icon-play" data-state="idle" aria-hidden="true">
					<svg class="tts-svg-play" viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M8 5v14l11-7z"/></svg>
					<svg class="tts-svg-pause" viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
				</span>
				<span class="tts-label"><?php echo esc_html( $atts['label'] ); ?></span>
			</button>

			<div class="tts-wave" aria-hidden="true" style="display:none;">
				<span></span><span></span><span></span><span></span><span></span>
			</div>

			<button type="button" class="tts-reader-btn tts-reader-stop" style="display:none;" aria-label="<?php esc_attr_e( 'Dừng đọc', 'tts-reader' ); ?>">
				<span class="tts-icon tts-icon-stop" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M6 6h12v12H6z"/></svg>
				</span>
			</button>

			<?php if ( ! empty( $settings['enable_download'] ) ) : ?>
			<button type="button" class="tts-reader-btn tts-reader-download" title="<?php esc_attr_e( 'Tải MP3', 'tts-reader' ); ?>" aria-label="<?php esc_attr_e( 'Tải MP3', 'tts-reader' ); ?>">
				<span class="tts-download-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM17 13l-5 5-5-5h3V9h4v4h3z"/></svg>
				</span>
				<span class="tts-download-spinner" style="display:none;" aria-hidden="true"></span>
				<span class="tts-download-label"><?php esc_html_e( 'Tải MP3', 'tts-reader' ); ?></span>
			</button>
			<?php endif; ?>
		</div>

		<div id="<?php echo esc_attr( $source_id ); ?>" class="tts-content-container tts-shortcode-content">
			<?php echo wp_kses_post( $content ); ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
