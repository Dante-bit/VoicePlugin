<?php
/**
 * Đăng ký menu quản lý riêng cho plugin trên sidebar khu vực quản trị
 * (thay vì ẩn trong menu "Settings" mặc định của WordPress).
 *
 * Cấu trúc menu:
 *   TTS Reader (top-level, icon loa)
 *     ├── Tổng quan   -> trang dashboard riêng của plugin
 *     └── Cài đặt     -> TTS_Settings::render_settings_page()
 *
 * @package TTS_Reader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTS_Admin {

	const MENU_SLUG = 'tts-reader';

	/**
	 * Tạo mục menu cấp cao nhất + submenu "Tổng quan".
	 * Chạy trước TTS_Settings::add_settings_page() (priority thấp hơn)
	 * để đảm bảo menu cha đã tồn tại khi thêm submenu "Cài đặt".
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Text to Speech Reader', 'tts-reader' ),
			__( 'TTS Reader', 'tts-reader' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-controls-volumeon',
			80
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Tổng quan', 'tts-reader' ),
			__( 'Tổng quan', 'tts-reader' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard' )
		);
	}

	/**
	 * Nạp CSS/JS riêng cho trang dashboard của plugin (chỉ trên đúng trang này).
	 *
	 * @param string $hook Hook suffix của trang admin hiện tại.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, self::MENU_SLUG ) === false ) {
			return;
		}

		wp_enqueue_style(
			'tts-reader-admin-style',
			TTS_READER_URL . 'assets/css/tts-admin.css',
			array(),
			TTS_READER_VERSION
		);

		wp_enqueue_script(
			'tts-reader-admin-script',
			TTS_READER_URL . 'assets/js/tts-admin.js',
			array(),
			TTS_READER_VERSION,
			true
		);

		wp_localize_script(
			'tts-reader-admin-script',
			'ttsAdminConfig',
			array(
				'testText' => __( 'Xin chào, đây là bản kiểm tra giọng đọc tiếng Việt của plugin Text to Speech Reader. Giọng nữ tự nhiên của Google đang đọc nội dung này.', 'tts-reader' ),
				'proxyUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'     => array(
					'checking'   => __( 'Đang kiểm tra danh sách giọng đọc...', 'tts-reader' ),
					'found'      => __( 'Đã tìm thấy giọng đọc tiếng Việt:', 'tts-reader' ),
					'notFound'   => __( 'Không tìm thấy giọng đọc tiếng Việt nào trên trình duyệt/thiết bị này.', 'tts-reader' ),
					'unsupported'=> __( 'Trình duyệt này không hỗ trợ Web Speech API.', 'tts-reader' ),
					'error'      => __( 'Không thể tải giọng đọc. Kiểm tra kết nối mạng.', 'tts-reader' ),
				),
			)
		);
	}

	/**
	 * Xuất HTML trang "Tổng quan".
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings        = TTS_Settings::get_settings();
		$settings_url    = admin_url( 'admin.php?page=' . TTS_Settings::PAGE_SLUG );
		$enabled_types   = (array) $settings['enabled_post_types'];
		$post_type_names = array();

		foreach ( $enabled_types as $pt_slug ) {
			$pt_obj = get_post_type_object( $pt_slug );
			if ( $pt_obj ) {
				$post_type_names[] = $pt_obj->labels->name;
			}
		}
		?>
		<div class="wrap tts-admin-wrap">
			<h1>
				<span class="dashicons dashicons-controls-volumeon" style="font-size:26px;width:26px;height:26px;"></span>
				<?php esc_html_e( 'Text to Speech Reader', 'tts-reader' ); ?>
			</h1>
			<p class="description"><?php esc_html_e( 'Trang quản lý tổng quan cho tính năng đọc bài viết bằng giọng nói.', 'tts-reader' ); ?></p>

			<div class="tts-admin-grid">

				<div class="tts-admin-card">
					<h2><?php esc_html_e( 'Trạng thái hiện tại', 'tts-reader' ); ?></h2>
					<table class="widefat striped">
						<tbody>
							<tr>
								<td><?php esc_html_e( 'Nút đọc bài viết', 'tts-reader' ); ?></td>
								<td><span class="tts-badge tts-badge-on"><?php esc_html_e( 'Đang bật', 'tts-reader' ); ?></span></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Áp dụng cho', 'tts-reader' ); ?></td>
								<td><?php echo $post_type_names ? esc_html( implode( ', ', $post_type_names ) ) : esc_html__( 'Chưa chọn loại nội dung nào', 'tts-reader' ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Ngôn ngữ giọng đọc', 'tts-reader' ); ?></td>
								<td><code><?php echo esc_html( $settings['default_lang'] ); ?></code></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Tốc độ đọc mặc định', 'tts-reader' ); ?></td>
								<td><?php echo esc_html( $settings['default_rate'] ); ?>x</td>
							</tr>
						</tbody>
					</table>
					<p>
						<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary">
							<?php esc_html_e( 'Đi tới Cài đặt', 'tts-reader' ); ?>
						</a>
					</p>
				</div>

				<div class="tts-admin-card">
					<h2><?php esc_html_e( 'Kiểm tra giọng đọc tiếng Việt', 'tts-reader' ); ?></h2>
					<p><?php esc_html_e( 'Danh sách giọng đọc phụ thuộc vào trình duyệt và hệ điều hành của từng máy, không phải của server. Nhấn nút bên dưới để kiểm tra trên trình duyệt bạn đang dùng.', 'tts-reader' ); ?></p>
					<button type="button" id="tts-admin-check-voice" class="button">
						<?php esc_html_e( 'Kiểm tra giọng hệ thống', 'tts-reader' ); ?>
					</button>
					<button type="button" id="tts-admin-test-play" class="button">
						<?php esc_html_e( 'Nghe thử (Web Speech)', 'tts-reader' ); ?>
					</button>
					<button type="button" id="tts-admin-test-google" class="button button-primary">
						<?php esc_html_e( '🔊 Nghe thử Google TTS', 'tts-reader' ); ?>
					</button>
					<div id="tts-admin-voice-result" class="tts-admin-voice-result"></div>
				</div>

				<div class="tts-admin-card">
					<h2><?php esc_html_e( 'Hướng dẫn nhanh', 'tts-reader' ); ?></h2>
					<ol>
						<li><?php esc_html_e( 'Vào tab Cài đặt để chọn loại nội dung áp dụng và vị trí nút.', 'tts-reader' ); ?></li>
						<li><?php esc_html_e( 'Nút "Nghe bài viết" sẽ tự động xuất hiện trong bài viết/trang tương ứng.', 'tts-reader' ); ?></li>
						<li>
							<?php
							printf(
								/* translators: %s: shortcode example */
								esc_html__( 'Muốn chèn thủ công một đoạn văn bản riêng, dùng shortcode: %s', 'tts-reader' ),
								'<code>[tts_reader]' . esc_html__( 'Nội dung cần đọc', 'tts-reader' ) . '[/tts_reader]</code>'
							);
							?>
						</li>
					</ol>
				</div>

			</div>
		</div>
		<?php
	}
}
