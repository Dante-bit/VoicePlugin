=== Voice Detected ===
Contributors: voicedetectedteam
Tags: voice recognition, speech to text, accessibility, shortcode, google speech
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Nhận dạng giọng nói thông minh cho WordPress – tìm bài viết, điều hướng, cuộn trang và hơn thế nữa bằng giọng nói.

== Description ==

**Voice Detected** là WordPress plugin cho phép người dùng điều khiển website bằng giọng nói với hỗ trợ đầy đủ Tiếng Việt và Tiếng Anh.

=== Tính năng chính ===

* 🎤 **Floating mic widget** đẹp mắt với glassmorphism design
* 🔍 **Tìm kiếm bài viết** bằng giọng nói với popup kết quả inline
* 📖 **Mở bài viết/trang** trực tiếp bằng giọng nói
* 🧭 **Điều hướng trang** (trang chủ, liên hệ, giới thiệu, blog...)
* 📜 **Cuộn trang** (lên/xuống/đầu/cuối)
* 📋 **Điều khiển form** (submit, reset, điền trường)
* 🖱️ **Click nút/liên kết** bằng giọng nói
* 🔊 **Waveform visualizer** realtime
* ⌨️ **Phím tắt** Alt+V
* 📊 **Lịch sử lệnh** trong admin

=== Engine nhận dạng ===

* Google Cloud Speech-to-Text API (chính xác, hỗ trợ đa ngôn ngữ)
* Web Speech API (miễn phí, tích hợp sẵn trình duyệt)

== Installation ==

1. Upload thư mục `voice-detected` vào `/wp-content/plugins/`
2. Kích hoạt plugin trong **Plugins > Installed Plugins**
3. Vào **Voice Detected > Cài đặt** để cấu hình
4. (Tùy chọn) Nhập Google Cloud API Key nếu muốn dùng Google STT

== Frequently Asked Questions ==

= Tôi cần Google API Key không? =

Không bắt buộc. Plugin có thể dùng Web Speech API miễn phí có sẵn trong Chrome/Edge. Tuy nhiên Google STT chính xác hơn và hỗ trợ Tiếng Việt tốt hơn.

= Plugin hỗ trợ ngôn ngữ nào? =

Tiếng Việt, Tiếng Anh (US/UK), Nhật, Hàn, Trung Quốc và mọi ngôn ngữ Google STT hỗ trợ.

= Làm sao để nhúng nút mic vào bài viết? =

Dùng shortcode: `[voice_detected theme="dark" label="Nhấn để nói"]`

= Phím tắt là gì? =

`Alt + V` để bật/tắt microphone từ bàn phím.

== Changelog ==

= 1.0.0 =
* Ra mắt phiên bản đầu tiên
* Hỗ trợ Google Cloud STT và Web Speech API
* Floating widget với glassmorphism design
* 16+ lệnh giọng nói (Tiếng Việt + Tiếng Anh)
* Admin settings với 4 tab
* Shortcode [voice_detected]
* Lịch sử lệnh

== Upgrade Notice ==

= 1.0.0 =
Phiên bản đầu tiên.
