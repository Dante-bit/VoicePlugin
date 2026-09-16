<?php
/**
 * Lớp điều phối trung tâm: đăng ký toàn bộ action/filter của plugin.
 *
 * Giúp tách biệt logic "khai báo hook" khỏi logic "xử lý hook",
 * dễ bảo trì và mở rộng khi plugin lớn dần.
 *
 * @package TTS_Reader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TTS_Loader {

	/**
	 * Danh sách action sẽ được đăng ký với WordPress.
	 *
	 * @var array
	 */
	protected $actions = array();

	/**
	 * Danh sách filter sẽ được đăng ký với WordPress.
	 *
	 * @var array
	 */
	protected $filters = array();

	public function __construct() {
		$this->define_i18n_hooks();
		$this->define_public_hooks();
		$this->define_admin_hooks();
	}

	/**
	 * Đăng ký load textdomain (đa ngôn ngữ).
	 */
	private function define_i18n_hooks() {
		$i18n = new TTS_i18n();
		$this->add_action( 'plugins_loaded', $i18n, 'load_textdomain' );
	}

	/**
	 * Đăng ký hook cho phần hiển thị ngoài trang (front-end).
	 */
	private function define_public_hooks() {
		$public = new TTS_Public();

		$this->add_action( 'wp_enqueue_scripts', $public, 'enqueue_assets' );
		$this->add_filter( 'the_content', $public, 'inject_reader_button' );

		$shortcode = new TTS_Shortcode();
		$this->add_action( 'init', $shortcode, 'register' );

		// Đăng ký AJAX proxy cho Google TTS Audio.
		$proxy = new TTS_Proxy();
		$this->add_action( 'init', $proxy, 'register' );
	}

	/**
	 * Đăng ký hook cho khu vực quản trị: menu riêng "TTS Reader"
	 * (Tổng quan + Cài đặt) và Settings API.
	 */
	private function define_admin_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		// Menu cha "TTS Reader" phải được tạo TRƯỚC (priority 9)
		// để add_submenu_page() trong TTS_Settings tìm thấy parent slug.
		$admin = new TTS_Admin();
		$this->add_action( 'admin_menu', $admin, 'register_menu', 9 );
		$this->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_admin_assets' );

		$settings = new TTS_Settings();
		$this->add_action( 'admin_menu', $settings, 'add_settings_page', 10 );
		$this->add_action( 'admin_init', $settings, 'register_settings' );
	}

	protected function add_action( $hook, $component, $callback, $priority = 10, $args = 1 ) {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'args' );
	}

	protected function add_filter( $hook, $component, $callback, $priority = 10, $args = 1 ) {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'args' );
	}

	/**
	 * Thực thi: gắn toàn bộ action/filter đã khai báo vào WordPress.
	 */
	public function run() {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['args'] );
		}
	}
}
