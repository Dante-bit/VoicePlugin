<?php
/**
 * Shortcode [tts_reader] — cho phép người biên tập chèn thủ công
 * một khối văn bản kèm nút đọc ở bất kỳ đâu trong bài viết/trang,
 * độc lập với việc tự động chèn qua the_content.
 *
 * Cách dùng:
 * [tts_reader]Đoạn văn bản cần đọc ở đây...[/tts_reader]
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
		$atts = shortcode_atts(
			array(
				'label' => __( 'Nghe đoạn văn này', 'tts-reader' ),
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
		<div class="tts-reader-wrapper tts-reader-shortcode" data-tts-target="#<?php echo esc_attr( $source_id ); ?>">
			<button type="button" class="tts-reader-btn tts-reader-play" aria-label="<?php echo esc_attr( $atts['label'] ); ?>">
				<span class="tts-icon tts-icon-play" aria-hidden="true"></span>
				<span class="tts-label"><?php echo esc_html( $atts['label'] ); ?></span>
			</button>
			<button type="button" class="tts-reader-btn tts-reader-stop" style="display:none;">
				<span class="tts-icon tts-icon-stop" aria-hidden="true"></span>
			</button>
		</div>
		<div id="<?php echo esc_attr( $source_id ); ?>" class="tts-reader-source" hidden>
			<?php echo wp_kses_post( $content ); ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
