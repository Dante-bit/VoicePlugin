<?php
/**
 * Quản lý Dashboard và giao diện quản trị cho TTS Reader v3.0.
 * Cung cấp Studio thử giọng trực tiếp, thống kê trực quan và bộ tạo shortcode.
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
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Text to Speech Reader AI', 'tts-reader' ),
			__( 'TTS Reader AI', 'tts-reader' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-controls-volumeon',
			80
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Tổng quan & Studio', 'tts-reader' ),
			__( 'Tổng quan & Studio', 'tts-reader' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard' )
		);
	}

	/**
	 * Nạp CSS/JS riêng cho trang quản trị của plugin.
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

		$settings = TTS_Settings::get_settings();

		wp_localize_script(
			'tts-reader-admin-script',
			'ttsAdminConfig',
			array(
				'proxyUrl'     => admin_url( 'admin-ajax.php' ),
				'defaultRate'  => (float) $settings['default_rate'],
				'defaultLang'  => $settings['default_lang'],
				'testSample'   => __( 'Xin chào quý độc giả! Đây là hệ thống đọc bài viết tự động. Giọng đọc được tối ưu mượt mà, hỗ trợ tự động tô sáng đúng một chữ đang được đọc và cho phép bạn tải về nghe offline mọi lúc mọi nơi.', 'tts-reader' ),
				'i18n'         => array(
					'copied'     => __( 'Đã sao chép shortcode vào bộ nhớ tạm!', 'tts-reader' ),
					'copyFail'   => __( 'Không thể sao chép, vui lòng bôi đen và nhấn Ctrl+C', 'tts-reader' ),
					'playing'    => __( 'Đang phát âm thanh...', 'tts-reader' ),
					'paused'     => __( 'Đã tạm dừng', 'tts-reader' ),
					'stopped'    => __( 'Đã dừng phát', 'tts-reader' ),
					'btnPlay'    => __( '▶ Phát thử nghiệm', 'tts-reader' ),
					'btnPause'   => __( '⏸ Tạm dừng', 'tts-reader' ),
				),
			)
		);
	}

	/**
	 * Xuất HTML trang "Tổng quan & Studio" hiện đại.
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

		$engine_label = __( 'Google TTS Online', 'tts-reader' );
		if ( $settings['engine'] === 'webspeech' ) {
			$engine_label = __( 'Web Speech API', 'tts-reader' );
		} elseif ( $settings['engine'] === 'auto' ) {
			$engine_label = __( 'Tự động (Auto)', 'tts-reader' );
		}
		?>
		<div class="wrap tts-admin-dashboard">

			<!-- Hero Banner -->
			<div class="tts-hero-banner">
				<div class="tts-hero-content">
					<div class="tts-hero-badge">
						<span class="tts-pulse-dot"></span>
						<span><?php esc_html_e( 'Phiên bản 3.0.0 Pro Edition', 'tts-reader' ); ?></span>
					</div>
					<h1 class="tts-hero-title">
						<span class="tts-hero-icon">🎙️</span>
						<?php esc_html_e( 'Text to Speech Reader AI', 'tts-reader' ); ?>
					</h1>
					<p class="tts-hero-desc">
						<?php esc_html_e( 'Giải pháp đọc bài viết tự động chuẩn tiếng Việt: Tự động tô sáng đúng một chữ đang được đọc theo thời gian thực và hỗ trợ tải MP3 nghe offline.', 'tts-reader' ); ?>
					</p>
					<div class="tts-hero-actions">
						<a href="<?php echo esc_url( $settings_url ); ?>" class="tts-btn tts-btn-primary">
							<span class="dashicons dashicons-admin-generic"></span>
							<?php esc_html_e( 'Tuỳ chỉnh Cài đặt', 'tts-reader' ); ?>
						</a>
						<a href="#tts-studio-card" class="tts-btn tts-btn-secondary">
							<span class="dashicons dashicons-controls-volumeon"></span>
							<?php esc_html_e( 'Mở Studio Thử Giọng', 'tts-reader' ); ?>
						</a>
					</div>
				</div>
				<div class="tts-hero-visual" aria-hidden="true">
					<div class="tts-hero-circle tts-circle-1"></div>
					<div class="tts-hero-circle tts-circle-2"></div>
					<div class="tts-hero-waves">
						<span></span><span></span><span></span><span></span><span></span><span></span><span></span>
					</div>
				</div>
			</div>

			<!-- Quick Stats Row -->
			<div class="tts-stats-row">
				<div class="tts-stat-card">
					<div class="tts-stat-icon tts-icon-purple">🌐</div>
					<div class="tts-stat-info">
						<div class="tts-stat-label"><?php esc_html_e( 'Bộ máy (Engine)', 'tts-reader' ); ?></div>
						<div class="tts-stat-value"><?php echo esc_html( $engine_label ); ?></div>
						<div class="tts-stat-sub"><span class="tts-dot-green"></span> <?php esc_html_e( 'Tiếng Việt tự nhiên', 'tts-reader' ); ?></div>
					</div>
				</div>

				<div class="tts-stat-card">
					<div class="tts-stat-icon tts-icon-blue">📄</div>
					<div class="tts-stat-info">
						<div class="tts-stat-label"><?php esc_html_e( 'Phạm vi hiển thị', 'tts-reader' ); ?></div>
						<div class="tts-stat-value"><?php echo count( $enabled_types ); ?> <?php esc_html_e( 'Loại nội dung', 'tts-reader' ); ?></div>
						<div class="tts-stat-sub"><?php echo $post_type_names ? esc_html( implode( ', ', $post_type_names ) ) : esc_html__( 'Chưa chọn', 'tts-reader' ); ?></div>
					</div>
				</div>

				<div class="tts-stat-card">
					<div class="tts-stat-icon tts-icon-amber">✨</div>
					<div class="tts-stat-info">
						<div class="tts-stat-label"><?php esc_html_e( 'Highlight chữ đang đọc', 'tts-reader' ); ?></div>
						<div class="tts-stat-value">
							<?php if ( ! empty( $settings['enable_highlight'] ) ) : ?>
								<span class="tts-badge tts-badge-success"><?php esc_html_e( '1 Chữ duy nhất', 'tts-reader' ); ?></span>
							<?php else : ?>
								<span class="tts-badge tts-badge-off"><?php esc_html_e( 'Đang tắt', 'tts-reader' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="tts-stat-sub">
							<?php
							$color_names = array(
								'yellow'  => 'Vàng rực',
								'emerald' => 'Xanh ngọc',
								'blue'    => 'Xanh dương',
								'orange'  => 'Cam đào',
							);
							$c_label = isset( $color_names[ $settings['highlight_color'] ] ) ? $color_names[ $settings['highlight_color'] ] : $settings['highlight_color'];
							printf( esc_html__( 'Màu: %s %s', 'tts-reader' ), esc_html( $c_label ), ! empty( $settings['auto_scroll'] ) ? '• Cuộn mượt' : '' );
							?>
						</div>
					</div>
				</div>

				<div class="tts-stat-card">
					<div class="tts-stat-icon tts-icon-green">📥</div>
					<div class="tts-stat-info">
						<div class="tts-stat-label"><?php esc_html_e( 'Tải file MP3', 'tts-reader' ); ?></div>
						<div class="tts-stat-value">
							<?php if ( ! empty( $settings['enable_download'] ) ) : ?>
								<span class="tts-badge tts-badge-success"><?php esc_html_e( 'Đang bật', 'tts-reader' ); ?></span>
							<?php else : ?>
								<span class="tts-badge tts-badge-off"><?php esc_html_e( 'Đang tắt', 'tts-reader' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="tts-stat-sub"><?php esc_html_e( 'Hỗ trợ ghép MP3 nhị phân', 'tts-reader' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Main 2-Column Grid -->
			<div class="tts-admin-columns">

				<!-- Left Column: Voice Studio Card -->
				<div class="tts-column-main">
					<div id="tts-studio-card" class="tts-card tts-studio-card">
						<div class="tts-card-header">
							<div class="tts-card-title-group">
								<h2 class="tts-card-title">
									<span class="dashicons dashicons-format-audio"></span>
									<?php esc_html_e( 'Voice Studio & Nghe Thử Trực Tiếp', 'tts-reader' ); ?>
								</h2>
								<p class="tts-card-subtitle"><?php esc_html_e( 'Kiểm tra tốc độ đọc, thử nghiệm hiệu ứng tô sáng 1 chữ duy nhất theo thời gian thực.', 'tts-reader' ); ?></p>
							</div>
							<span class="tts-tag-pill"><?php esc_html_e( 'Live Test', 'tts-reader' ); ?></span>
						</div>

						<div class="tts-card-body">
							<!-- Speed in Studio -->
							<div class="tts-form-group" style="max-width: 200px;">
								<label for="tts-studio-speed-select"><?php esc_html_e( 'Tốc Độ Đọc:', 'tts-reader' ); ?></label>
								<select id="tts-studio-speed-select" class="tts-select">
									<option value="0.75">0.75x (Chậm rãi)</option>
									<option value="1" selected>1.0x (Chuẩn tự nhiên)</option>
									<option value="1.25">1.25x (Nhanh vừa)</option>
									<option value="1.5">1.5x (Nhanh)</option>
								</select>
							</div>

							<!-- Text Preview / Edit -->
							<div class="tts-form-group" style="margin-top:16px;">
								<label for="tts-studio-textarea"><?php esc_html_e( 'Nội dung kiểm tra (Có thể sửa trực tiếp):', 'tts-reader' ); ?></label>
								<textarea id="tts-studio-textarea" class="tts-textarea" rows="4"><?php esc_html_e( 'Xin chào quý độc giả! Đây là tính năng đọc bài viết thông minh của plugin Text to Speech Reader. Từng chữ bạn đang nghe sẽ tự động được tô sáng trên màn hình, giúp việc theo dõi nội dung trở nên trực quan và thú vị hơn bao giờ hết.', 'tts-reader' ); ?></textarea>
							</div>

							<!-- Live Word Highlight Simulation Box -->
							<div class="tts-form-group">
								<label><?php esc_html_e( 'Xem trước hiệu ứng Highlight 1 chữ duy nhất đang đọc:', 'tts-reader' ); ?></label>
								<div id="tts-studio-highlight-preview" class="tts-preview-box">
									<!-- Words injected by JS -->
								</div>
							</div>

							<!-- Audio Visualizer Equalizer Bar -->
							<div class="tts-studio-player-bar">
								<div class="tts-player-actions">
									<button type="button" id="tts-studio-play-btn" class="tts-btn tts-btn-accent">
										<span class="dashicons dashicons-controls-play"></span>
										<span class="tts-btn-text"><?php esc_html_e( 'Phát thử nghiệm', 'tts-reader' ); ?></span>
									</button>
									<button type="button" id="tts-studio-stop-btn" class="tts-btn tts-btn-outline" style="display:none;">
										<span class="dashicons dashicons-controls-square"></span>
										<span><?php esc_html_e( 'Dừng', 'tts-reader' ); ?></span>
									</button>
								</div>

								<!-- 9-bar animated equalizer -->
								<div class="tts-equalizer" id="tts-studio-equalizer">
									<span class="bar bar-1"></span>
									<span class="bar bar-2"></span>
									<span class="bar bar-3"></span>
									<span class="bar bar-4"></span>
									<span class="bar bar-5"></span>
									<span class="bar bar-6"></span>
									<span class="bar bar-7"></span>
									<span class="bar bar-8"></span>
									<span class="bar bar-9"></span>
								</div>

								<div class="tts-player-status" id="tts-studio-status">
									<?php esc_html_e( 'Sẵn sàng phát thử', 'tts-reader' ); ?>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Right Column: Shortcode Generator & Features -->
				<div class="tts-column-side">

					<!-- Shortcode Generator -->
					<div class="tts-card tts-shortcode-card">
						<div class="tts-card-header">
							<h3 class="tts-card-title">
								<span class="dashicons dashicons-shortcode"></span>
								<?php esc_html_e( 'Tạo Mã Shortcode', 'tts-reader' ); ?>
							</h3>
						</div>
						<div class="tts-card-body">
							<p class="tts-muted"><?php esc_html_e( 'Chèn nút đọc cho một đoạn văn bản hoặc khối nội dung bất kỳ trong Elementor, Gutenberg hoặc Classic Editor.', 'tts-reader' ); ?></p>

							<div class="tts-form-group">
								<label for="tts-sc-rate"><?php esc_html_e( 'Tốc độ đọc:', 'tts-reader' ); ?></label>
								<select id="tts-sc-rate" class="tts-select">
									<option value="1">1.0x (Bình thường)</option>
									<option value="0.75">0.75x (Chậm)</option>
									<option value="1.25">1.25x (Nhanh vừa)</option>
									<option value="1.5">1.5x (Nhanh)</option>
								</select>
							</div>

							<div class="tts-form-group">
								<label for="tts-sc-text"><?php esc_html_e( 'Nội dung đọc:', 'tts-reader' ); ?></label>
								<input type="text" id="tts-sc-text" class="tts-input" value="Nội dung cần đọc to..." />
							</div>

							<div class="tts-form-group">
								<label><?php esc_html_e( 'Mã shortcode sinh tự động:', 'tts-reader' ); ?></label>
								<div class="tts-code-box">
									<code id="tts-generated-shortcode">[tts_reader rate="1"]Nội dung cần đọc to...[/tts_reader]</code>
								</div>
							</div>

							<button type="button" id="tts-copy-shortcode-btn" class="tts-btn tts-btn-primary tts-btn-block">
								<span class="dashicons dashicons-admin-page"></span>
								<span class="tts-copy-label"><?php esc_html_e( 'Sao chép Shortcode', 'tts-reader' ); ?></span>
							</button>
						</div>
					</div>

					<!-- Feature Highlights Card -->
					<div class="tts-card tts-info-card" style="margin-top:20px;">
						<div class="tts-card-header">
							<h3 class="tts-card-title">
								<span class="dashicons dashicons-star-filled" style="color:#eab308;"></span>
								<?php esc_html_e( 'Tính Năng Nổi Bật', 'tts-reader' ); ?>
							</h3>
						</div>
						<div class="tts-card-body">
							<ul class="tts-feature-list">
								<li>
									<strong>✨ Highlight 1 Chữ Duy Nhất:</strong>
									<span>Đọc đến đâu chữ đó sáng lên rực rỡ và tự động cuộn trang theo dõi.</span>
								</li>
								<li>
									<strong>📥 Tải File MP3:</strong>
									<span>Cho phép người đọc tải bản ghi âm MP3 chất lượng cao về nghe offline.</span>
								</li>
								<li>
									<strong>⚡ Ổn Định 100%:</strong>
									<span>Giọng đọc Google chuẩn tiếng Việt phát qua proxy server, không nghẽn mạng.</span>
								</li>
							</ul>
						</div>
					</div>

				</div>

			</div>

			<!-- Toast Notification for Copy -->
			<div id="tts-admin-toast" class="tts-toast" aria-live="polite">
				<span class="dashicons dashicons-yes-alt"></span>
				<span class="tts-toast-text"></span>
			</div>

		</div>
		<?php
	}
}
