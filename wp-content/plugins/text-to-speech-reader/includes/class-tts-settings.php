<?php
/**
 * Trang thiết lập (Settings) trong khu vực quản trị.
 * Cho phép chọn loại bài đăng áp dụng, vị trí nút, ngôn ngữ mặc định, tốc độ đọc.
 *
 * @package TTS_Reader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTS_Settings {

	const OPTION_KEY = 'tts_reader_settings';
	const PAGE_SLUG  = 'tts-reader-settings';

	/**
	 * Thêm trang "Cài đặt" làm submenu bên trong menu riêng "TTS Reader"
	 * (do TTS_Admin::register_menu() tạo ở cấp cao nhất).
	 */
	public function add_settings_page() {
		add_submenu_page(
			TTS_Admin::MENU_SLUG,
			__( 'Cài đặt - TTS Reader', 'tts-reader' ),
			__( 'Cài đặt', 'tts-reader' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Đăng ký nhóm setting, field và sanitize callback.
	 */
	public function register_settings() {
		register_setting( self::PAGE_SLUG, self::OPTION_KEY, array( $this, 'sanitize' ) );

		add_settings_section(
			'tts_reader_main_section',
			__( 'Tuỳ chọn hiển thị', 'tts-reader' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'button_text',
			__( 'Chữ trên nút', 'tts-reader' ),
			array( $this, 'field_button_text' ),
			self::PAGE_SLUG,
			'tts_reader_main_section'
		);

		add_settings_field(
			'button_position',
			__( 'Vị trí nút', 'tts-reader' ),
			array( $this, 'field_button_position' ),
			self::PAGE_SLUG,
			'tts_reader_main_section'
		);

		add_settings_field(
			'engine',
			__( 'Bộ máy giọng đọc (Engine)', 'tts-reader' ),
			array( $this, 'field_engine' ),
			self::PAGE_SLUG,
			'tts_reader_main_section'
		);

		add_settings_field(
			'default_lang',
			__( 'Ngôn ngữ giọng đọc', 'tts-reader' ),
			array( $this, 'field_default_lang' ),
			self::PAGE_SLUG,
			'tts_reader_main_section'
		);

		add_settings_field(
			'default_rate',
			__( 'Tốc độ đọc mặc định (0.5 - 2)', 'tts-reader' ),
			array( $this, 'field_default_rate' ),
			self::PAGE_SLUG,
			'tts_reader_main_section'
		);

		add_settings_field(
			'enabled_post_types',
			__( 'Áp dụng cho loại nội dung', 'tts-reader' ),
			array( $this, 'field_post_types' ),
			self::PAGE_SLUG,
			'tts_reader_main_section'
		);
	}

	public static function get_settings() {
		$defaults = array(
			'engine'             => 'google',
			'enabled_post_types' => array( 'post' ),
			'button_position'    => 'before',
			'button_text'        => __( 'Nghe bài viết', 'tts-reader' ),
			'default_lang'       => 'vi-VN',
			'default_rate'       => '1',
		);

		return wp_parse_args( get_option( self::OPTION_KEY, array() ), $defaults );
	}

	/* ---------------------- Render field ---------------------- */

	public function field_button_text() {
		$s = self::get_settings();
		printf(
			'<input type="text" name="%s[button_text]" value="%s" class="regular-text" />',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $s['button_text'] )
		);
	}

	public function field_button_position() {
		$s        = self::get_settings();
		$options  = array(
			'before' => __( 'Trước nội dung', 'tts-reader' ),
			'after'  => __( 'Sau nội dung', 'tts-reader' ),
			'both'   => __( 'Cả trước và sau', 'tts-reader' ),
		);
		echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[button_position]">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $s['button_position'], $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	public function field_engine() {
		$s       = self::get_settings();
		$options = array(
			'google'    => __( '🌐 Google TTS Online (Khuyên dùng) — Giọng nữ tiếng Việt tự nhiên, hoạt động trên mọi trình duyệt', 'tts-reader' ),
			'auto'      => __( '🔄 Tự động — Ưu tiên giọng hệ thống tiếng Việt, nếu không có dùng Google TTS', 'tts-reader' ),
			'webspeech' => __( '💻 Web Speech API — Giọng tích hợp trình duyệt/hệ điều hành', 'tts-reader' ),
		);
		echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[engine]">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $s['engine'], $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Với Google TTS Online, giọng đọc tiếng Việt luôn hoạt động trên mọi thiết bị mà không cần cài đặt thêm.', 'tts-reader' ) . '</p>';
	}

	public function field_default_lang() {
		$s = self::get_settings();
		printf(
			'<input type="text" name="%s[default_lang]" value="%s" class="regular-text" placeholder="vi-VN" />
			<p class="description">%s</p>',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $s['default_lang'] ),
			esc_html__( 'Mã ngôn ngữ theo chuẩn BCP-47, ví dụ vi-VN, en-US. Dùng cho Web Speech API và Google TTS Online.', 'tts-reader' )
		);
	}

	public function field_default_rate() {
		$s = self::get_settings();
		printf(
			'<input type="number" step="0.1" min="0.5" max="2" name="%s[default_rate]" value="%s" />',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $s['default_rate'] )
		);
	}

	public function field_post_types() {
		$s          = self::get_settings();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		foreach ( $post_types as $pt ) {
			$checked = in_array( $pt->name, (array) $s['enabled_post_types'], true );
			printf(
				'<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="%s[enabled_post_types][]" value="%s" %s /> %s</label>',
				esc_attr( self::OPTION_KEY ),
				esc_attr( $pt->name ),
				checked( $checked, true, false ),
				esc_html( $pt->labels->singular_name )
			);
		}
	}

	/**
	 * Làm sạch dữ liệu trước khi lưu vào database.
	 */
	public function sanitize( $input ) {
		$output = array();

		$output['engine']          = isset( $input['engine'] ) && in_array( $input['engine'], array( 'google', 'auto', 'webspeech' ), true )
			? $input['engine']
			: 'google';
		$output['button_text']     = isset( $input['button_text'] ) ? sanitize_text_field( $input['button_text'] ) : '';
		$output['button_position'] = isset( $input['button_position'] ) && in_array( $input['button_position'], array( 'before', 'after', 'both' ), true )
			? $input['button_position']
			: 'before';
		$output['default_lang']    = isset( $input['default_lang'] ) ? sanitize_text_field( $input['default_lang'] ) : 'vi-VN';
		$output['default_rate']    = isset( $input['default_rate'] ) ? max( 0.5, min( 2, floatval( $input['default_rate'] ) ) ) : 1;
		$output['enabled_post_types'] = isset( $input['enabled_post_types'] ) && is_array( $input['enabled_post_types'] )
			? array_map( 'sanitize_key', $input['enabled_post_types'] )
			: array();

		return $output;
	}

	/**
	 * Xuất HTML trang cài đặt.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Text to Speech Reader - Cài đặt', 'tts-reader' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::PAGE_SLUG );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
