=== Text to Speech Reader ===
Contributors: yourname
Tags: text to speech, accessibility, audio, read aloud, tts
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Thêm nút "Nghe bài viết" giúp người dùng nghe nội dung bài viết/trang bằng giọng đọc của trình duyệt.

== Description ==

Text to Speech Reader tự động (hoặc qua shortcode) chèn một nút cho phép người đọc nghe nội dung
bài viết bằng giọng nói, sử dụng Web Speech API sẵn có trong trình duyệt hiện đại — không cần
API bên thứ ba, không tốn phí, không gửi nội dung ra ngoài server.

Tính năng chính:

* Tự động chèn nút đọc vào đầu/cuối nội dung bài viết, trang, hoặc custom post type.
* Shortcode `[tts_reader]...[/tts_reader]` để chèn thủ công một đoạn văn bản bất kỳ.
* Điều khiển: Phát / Tạm dừng / Tiếp tục / Dừng.
* Chọn tốc độ đọc (0.75x - 2x).
* Cấu hình ngôn ngữ giọng đọc mặc định (vi-VN, en-US, ...).
* Trang cài đặt riêng trong khu vực quản trị.

== Installation ==

1. Tải thư mục `text-to-speech-reader` lên `/wp-content/plugins/`.
2. Kích hoạt plugin trong menu "Plugins" của WordPress.
3. Vào **Settings → TTS Reader** để cấu hình.

== Frequently Asked Questions ==

= Plugin có cần kết nối internet để đọc không? =
Không, giọng đọc lấy trực tiếp từ trình duyệt (Web Speech API) của người dùng.

= Vì sao không nghe được giọng đọc? =
Một số trình duyệt hoặc hệ điều hành cần cài thêm gói giọng đọc, hoặc chưa hỗ trợ ngôn ngữ đã chọn.

== Changelog ==

= 1.0.0 =
* Phát hành phiên bản đầu tiên.
