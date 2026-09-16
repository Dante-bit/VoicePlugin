<?php
/**
 * Trang thiết lập (Settings) trong khu vực quản trị.
 * Quản lý cấu hình đọc bài viết, highlight từng từ thời gian thực, tải MP3 và hiển thị.
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
	 * Thêm trang "Cài đặt" làm submenu bên trong menu riêng "TTS Reader".
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

		/* ----------------- Section 1: Hiển thị giao diện ----------------- */
		add_settings_section(
			'tts_reader_main_section',
			__( '1. Tuỳ chọn hiển thị & Vị trí nút', 'tts-reader' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'button_text',
			__( 'Chữ trên nút phát', 'tts-reader' ),
			array( $this, 'field_button_text' ),
			self::PAGE_SLUG,
			'tts_reader_main_section'
		);

		add_settings_field(
			'button_position',
			__( 'Vị trí nút đọc', 'tts-reader' ),
			array( $this, 'field_button_position' ),
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

		/* ----------------- Section 2: Bộ máy & Tốc độ ----------------- */
		add_settings_section(
			'tts_reader_voice_section',
			__( '2. Cấu hình Bộ máy (Engine) & Tốc độ đọc', 'tts-reader' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'engine',
			__( 'Bộ máy giọng đọc (Engine)', 'tts-reader' ),
			array( $this, 'field_engine' ),
			self::PAGE_SLUG,
			'tts_reader_voice_section'
		);

		add_settings_field(
			'default_rate',
			__( 'Tốc độ đọc mặc định (0.5 - 2.0)', 'tts-reader' ),
			array( $this, 'field_default_rate' ),
			self::PAGE_SLUG,
			'tts_reader_voice_section'
		);

		add_settings_field(
			'default_lang',
			__( 'Mã ngôn ngữ tiêu chuẩn', 'tts-reader' ),
			array( $this, 'field_default_lang' ),
			self::PAGE_SLUG,
			'tts_reader_voice_section'
		);

		/* ----------------- Section 3: Tính năng nâng cao v3.0 ----------------- */
		add_settings_section(
			'tts_reader_features_section',
			__( '3. Tính năng nâng cao: Highlight & Tải MP3', 'tts-reader' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'enable_highlight',
			__( 'Tô sáng (Highlight) chữ đang đọc', 'tts-reader' ),
			array( $this, 'field_enable_highlight' ),
			self::PAGE_SLUG,
			'tts_reader_features_section'
		);

		add_settings_field(
			'highlight_color',
			__( 'Tông màu Highlight', 'tts-reader' ),
			array( $this, 'field_highlight_color' ),
			self::PAGE_SLUG,
			'tts_reader_features_section'
		);

		add_settings_field(
			'auto_scroll',
			__( 'Tự động cuộn trang theo chữ đang đọc', 'tts-reader' ),
			array( $this, 'field_auto_scroll' ),
			self::PAGE_SLUG,
			'tts_reader_features_section'
		);

		add_settings_field(
			'enable_download',
			__( 'Tải xuống file âm thanh (Download MP3)', 'tts-reader' ),
			array( $this, 'field_enable_download' ),
			self::PAGE_SLUG,
			'tts_reader_features_section'
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
			'enable_highlight'   => 1,
			'highlight_color'    => 'yellow',
			'auto_scroll'        => 1,
			'enable_download'    => 1,
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
		$s       = self::get_settings();
		$options = array(
			'before' => __( 'Trước nội dung (Khuyên dùng)', 'tts-reader' ),
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
			'google'    => __( '🌐 Google TTS Online (Khuyên dùng) — Giọng chuẩn tiếng Việt mượt mà, hoạt động 100% trên mọi thiết bị', 'tts-reader' ),
			'auto'      => __( '🔄 Tự động — Ưu tiên giọng Web Speech nếu máy có, ngược lại dùng Google TTS', 'tts-reader' ),
			'webspeech' => __( '💻 Web Speech API — Giọng tích hợp của trình duyệt/hệ điều hành', 'tts-reader' ),
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
		echo '<p class="description">' . esc_html__( 'Google TTS Online cho chất lượng đọc tự nhiên và ổn định nhất, không phụ thuộc vào thiết bị người dùng.', 'tts-reader' ) . '</p>';
	}

	public function field_default_rate() {
		$s = self::get_settings();
		printf(
			'<input type="number" step="0.1" min="0.5" max="2" name="%s[default_rate]" value="%s" style="width:100px;" />
			<p class="description">%s</p>',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $s['default_rate'] ),
			esc_html__( 'Tốc độ 1.0x là tốc độ tự nhiên chuẩn.', 'tts-reader' )
		);
	}

	public function field_default_lang() {
		$s = self::get_settings();
		printf(
			'<input type="text" name="%s[default_lang]" value="%s" class="regular-text" placeholder="vi-VN" />
			<p class="description">%s</p>',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $s['default_lang'] ),
			esc_html__( 'Mã ngôn ngữ chuẩn BCP-47 (ví dụ: vi-VN cho Tiếng Việt).', 'tts-reader' )
		);
	}

	public function field_post_types() {
		$s          = self::get_settings();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		foreach ( $post_types as $pt ) {
			$checked = in_array( $pt->name, (array) $s['enabled_post_types'], true );
			printf(
				'<label style="display:inline-block;margin-right:16px;margin-bottom:6px;"><input type="checkbox" name="%s[enabled_post_types][]" value="%s" %s /> %s</label>',
				esc_attr( self::OPTION_KEY ),
				esc_attr( $pt->name ),
				checked( $checked, true, false ),
				esc_html( $pt->labels->singular_name )
			);
		}
	}

	public function field_enable_highlight() {
		$s = self::get_settings();
		printf(
			'<label><input type="checkbox" name="%s[enable_highlight]" value="1" %s /> %s</label>
			<p class="description">%s</p>',
			esc_attr( self::OPTION_KEY ),
			checked( ! empty( $s['enable_highlight'] ), true, false ),
			esc_html__( 'Tô sáng DUY NHẤT 1 chữ đang được đọc theo thời gian thực', 'tts-reader' ),
			esc_html__( 'Từng chữ phát ra đến đâu sẽ sáng rực lên đến đó, giúp người đọc dễ theo dõi.', 'tts-reader' )
		);
	}

	public function field_highlight_color() {
		$s       = self::get_settings();
		$options = array(
			'yellow'  => __( '🟡 Vàng rực rỡ (Rõ ràng, dễ theo dõi nhất)', 'tts-reader' ),
			'emerald' => __( '🟢 Xanh ngọc lục bảo (Thanh thoát, hiện đại)', 'tts-reader' ),
			'blue'    => __( '🔵 Xanh dương nhạt (Sáng rõ, công nghệ)', 'tts-reader' ),
			'orange'  => __( '🟠 Cam đào pastel (Ấm áp, nổi bật)', 'tts-reader' ),
		);
		echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[highlight_color]">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $s['highlight_color'], $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	public function field_auto_scroll() {
		$s = self::get_settings();
		printf(
			'<label><input type="checkbox" name="%s[auto_scroll]" value="1" %s /> %s</label>
			<p class="description">%s</p>',
			esc_attr( self::OPTION_KEY ),
			checked( ! empty( $s['auto_scroll'] ), true, false ),
			esc_html__( 'Tự động cuộn màn hình theo chữ đang đọc nếu vượt quá tầm nhìn', 'tts-reader' ),
			esc_html__( 'Tránh việc người nghe phải cuộn trang thủ công khi nghe bài viết dài.', 'tts-reader' )
		);
	}

	public function field_enable_download() {
		$s = self::get_settings();
		printf(
			'<label><input type="checkbox" name="%s[enable_download]" value="1" %s /> %s</label>
			<p class="description">%s</p>',
			esc_attr( self::OPTION_KEY ),
			checked( ! empty( $s['enable_download'] ), true, false ),
			esc_html__( 'Hiển thị nút "Tải xuống MP3" để người dùng lưu về nghe offline', 'tts-reader' ),
			esc_html__( 'Hệ thống sẽ tự động ghép các đoạn audio thành file MP3 hoàn chỉnh.', 'tts-reader' )
		);
	}

	/**
	 * Làm sạch dữ liệu trước khi lưu vào database.
	 */
	public function sanitize( $input ) {
		$output = array();

		$output['button_text']     = isset( $input['button_text'] ) ? sanitize_text_field( $input['button_text'] ) : '';
		$output['button_position'] = isset( $input['button_position'] ) && in_array( $input['button_position'], array( 'before', 'after', 'both' ), true )
			? $input['button_position']
			: 'before';

		$output['engine']          = isset( $input['engine'] ) && in_array( $input['engine'], array( 'google', 'auto', 'webspeech' ), true )
			? $input['engine']
			: 'google';

		$output['default_rate']        = isset( $input['default_rate'] ) ? max( 0.5, min( 2, floatval( $input['default_rate'] ) ) ) : 1;
		$output['default_lang']        = isset( $input['default_lang'] ) ? sanitize_text_field( $input['default_lang'] ) : 'vi-VN';

		$output['enabled_post_types']  = isset( $input['enabled_post_types'] ) && is_array( $input['enabled_post_types'] )
			? array_map( 'sanitize_key', $input['enabled_post_types'] )
			: array();

		$output['enable_highlight']    = ! empty( $input['enable_highlight'] ) ? 1 : 0;
		$output['highlight_color']     = isset( $input['highlight_color'] ) && in_array( $input['highlight_color'], array( 'yellow', 'emerald', 'blue', 'orange' ), true )
			? $input['highlight_color']
			: 'yellow';

		$output['auto_scroll']         = ! empty( $input['auto_scroll'] ) ? 1 : 0;
		$output['enable_download']     = ! empty( $input['enable_download'] ) ? 1 : 0;

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
		<div class="wrap tts-settings-wrap">
			<h1>
				<span class="dashicons dashicons-admin-generic" style="font-size:28px;width:28px;height:28px;vertical-align:middle;color:#4f46e5;"></span>
				<?php esc_html_e( 'Cài đặt Text to Speech Reader', 'tts-reader' ); ?>
			</h1>
			<p class="description"><?php esc_html_e( 'Tuỳ chỉnh hiệu ứng highlight 1 chữ theo thời gian thực, tính năng tải MP3 và bố cục hiển thị.', 'tts-reader' ); ?></p>
			
			<div class="tts-settings-container">
				<form method="post" action="options.php">
					<?php
					settings_fields( self::PAGE_SLUG );
					do_settings_sections( self::PAGE_SLUG );
					submit_button( __( 'Lưu thay đổi', 'tts-reader' ), 'primary large' );
					?>
				</form>
			</div>
		</div>
		<?php
	}
}
