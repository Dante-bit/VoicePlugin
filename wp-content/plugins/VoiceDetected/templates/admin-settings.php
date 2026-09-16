<?php
/**
 * Admin settings page template - Voice Detected (Pro Suite).
 */
defined( 'ABSPATH' ) || exit;

$settings    = (array) get_option( VD_OPTION_KEY, [] );
$engine      = $settings['api_engine']        ?? 'webspeech';
$language    = $settings['language']          ?? 'vi-VN';
$position    = $settings['widget_position']   ?? 'bottom-right';
$theme       = $settings['widget_theme']      ?? 'glass';
$show_on     = $settings['show_on']           ?? 'all';
$enabled     = ! empty( $settings['enabled'] );
$accent      = ! empty( $settings['accent_color'] ) ? $settings['accent_color'] : '#6366F1';
$size        = $settings['widget_size']       ?? 'medium';
$title       = $settings['widget_title']      ?? 'Trợ lý ảo AI';
$audio_filt  = ! empty( $settings['audio_filter'] );
$sensitivity = $settings['mic_sensitivity']   ?? 'normal';
$sound_fx    = ! empty( $settings['sound_effects'] );
$tts_voice   = ! empty( $settings['tts_voice'] );
$tts_speed   = floatval( $settings['tts_speed'] ?? 1.0 );
$chips       = $settings['suggestion_chips']  ?? '🏠 Trang chủ, 🔥 Mới nhất hôm nay, ⬇️ Cuộn xuống, 🔝 Đầu trang, 📞 Liên hệ';
$shortcut    = $settings['keyboard_shortcut'] ?? 'Alt+V';
$scroll_step = intval( $settings['scroll_step'] ?? 450 );
?>
<div class="wrap vd-admin-wrap">

    <!-- Header -->
    <div class="vd-admin-header">
        <div class="vd-admin-logo">🎙️</div>
        <div>
            <h1 class="vd-admin-title">Voice Detected Pro</h1>
            <p class="vd-admin-subtitle">
                <?php esc_html_e( 'Trợ lý giọng nói thông minh & bộ lọc âm chống ồn thế hệ mới cho WordPress', 'voice-detected' ); ?>
                &nbsp;
                <?php if ( $enabled ) : ?>
                    <span class="vd-status-badge active">● <?php esc_html_e( 'Đang hoạt động', 'voice-detected' ); ?></span>
                <?php else : ?>
                    <span class="vd-status-badge inactive">● <?php esc_html_e( 'Đã tắt', 'voice-detected' ); ?></span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php settings_errors( 'voice_detected_group' ); ?>

    <!-- Tabs -->
    <div class="vd-tabs">
        <button type="button" class="vd-tab-btn active" data-tab="general">⚙️ <?php esc_html_e( 'Cài đặt chung', 'voice-detected' ); ?></button>
        <button type="button" class="vd-tab-btn" data-tab="engine">⚡ <?php esc_html_e( 'Engine & Lọc âm', 'voice-detected' ); ?></button>
        <button type="button" class="vd-tab-btn" data-tab="appearance">🎨 <?php esc_html_e( 'Giao diện & Màu sắc', 'voice-detected' ); ?></button>
        <button type="button" class="vd-tab-btn" data-tab="audio">🔊 <?php esc_html_e( 'Âm thanh & Giọng nói', 'voice-detected' ); ?></button>
        <button type="button" class="vd-tab-btn" data-tab="commands">🗣️ <?php esc_html_e( 'Danh mục khẩu lệnh', 'voice-detected' ); ?></button>
        <button type="button" class="vd-tab-btn" data-tab="shortcode">📋 <?php esc_html_e( 'Shortcode', 'voice-detected' ); ?></button>
        <button type="button" class="vd-tab-btn" data-tab="analytics">📊 <?php esc_html_e( 'Thống kê & SEO Giọng nói', 'voice-detected' ); ?></button>
    </div>

    <form method="post" action="options.php" id="vd-settings-form">
        <?php settings_fields( 'voice_detected_group' ); ?>

        <!-- ═══ TAB 1: General ════════════════════════════════════ -->
        <div class="vd-tab-pane active" id="vd-tab-general">
            <div class="vd-card">
                <h2 class="vd-card-title">⚙️ <?php esc_html_e( 'Cấu hình hệ thống', 'voice-detected' ); ?></h2>

                <!-- Enable plugin -->
                <div class="vd-form-row">
                    <label><?php esc_html_e( 'Kích hoạt', 'voice-detected' ); ?></label>
                    <div>
                        <label class="vd-toggle-label">
                            <input type="checkbox" name="<?php echo VD_OPTION_KEY; ?>[enabled]" value="1" <?php checked( $enabled ); ?>>
                            <strong><?php esc_html_e( 'Bật trợ lý nhận dạng giọng nói trên website', 'voice-detected' ); ?></strong>
                        </label>
                    </div>
                </div>

                <!-- Show on -->
                <div class="vd-form-row">
                    <label><?php esc_html_e( 'Phạm vi hiển thị', 'voice-detected' ); ?></label>
                    <div class="vd-radio-group">
                        <?php $show_options = [
                            'all'      => __( 'Tất cả trang (Frontend & Admin)', 'voice-detected' ),
                            'frontend' => __( 'Chỉ người xem ngoài Frontend', 'voice-detected' ),
                            'backend'  => __( 'Chỉ khu vực Quản trị Admin', 'voice-detected' ),
                        ]; ?>
                        <?php foreach ( $show_options as $val => $label ) : ?>
                            <label>
                                <input type="radio" name="<?php echo VD_OPTION_KEY; ?>[show_on]" value="<?php echo esc_attr( $val ); ?>" <?php checked( $show_on, $val ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Keyboard Shortcut -->
                <div class="vd-form-row">
                    <label for="vd-shortcut"><?php esc_html_e( 'Phím tắt kích hoạt', 'voice-detected' ); ?></label>
                    <div>
                        <input type="text" id="vd-shortcut" class="vd-input" style="max-width:200px"
                               name="<?php echo VD_OPTION_KEY; ?>[keyboard_shortcut]"
                               value="<?php echo esc_attr( $shortcut ); ?>"
                               placeholder="Alt+V">
                        <p class="vd-hint"><?php esc_html_e( 'Nhấn tổ hợp phím này trên bất kỳ trang nào để mở nhanh trợ lý (Ví dụ: Alt+V, Alt+M, Ctrl+K).', 'voice-detected' ); ?></p>
                    </div>
                </div>

                <!-- Trigger word -->
                <div class="vd-form-row">
                    <label for="vd-trigger"><?php esc_html_e( 'Từ kích hoạt (Hotword)', 'voice-detected' ); ?></label>
                    <div>
                        <input type="text" id="vd-trigger" class="vd-input"
                               name="<?php echo VD_OPTION_KEY; ?>[trigger_word]"
                               value="<?php echo esc_attr( $settings['trigger_word'] ?? '' ); ?>"
                               placeholder="<?php esc_attr_e( 'Để trống để nhận lệnh trực tiếp (hoặc gõ ví dụ: hey assistant)', 'voice-detected' ); ?>">
                        <p class="vd-hint"><?php esc_html_e( 'Nếu điền, người dùng phải nói từ này trước khi ra lệnh. Khuyên dùng: Để trống để nói câu lệnh là thực thi ngay.', 'voice-detected' ); ?></p>
                    </div>
                </div>

                <!-- Language -->
                <div class="vd-form-row">
                    <label for="vd-language"><?php esc_html_e( 'Ngôn ngữ chính', 'voice-detected' ); ?></label>
                    <select id="vd-language" class="vd-select" style="max-width:280px" name="<?php echo VD_OPTION_KEY; ?>[language]">
                        <option value="vi-VN" <?php selected( $language, 'vi-VN' ); ?>>🇻🇳 Tiếng Việt (vi-VN)</option>
                        <option value="en-US" <?php selected( $language, 'en-US' ); ?>>🇺🇸 English (en-US)</option>
                        <option value="en-GB" <?php selected( $language, 'en-GB' ); ?>>🇬🇧 English UK (en-GB)</option>
                        <option value="ja-JP" <?php selected( $language, 'ja-JP' ); ?>>🇯🇵 日本語 (ja-JP)</option>
                        <option value="ko-KR" <?php selected( $language, 'ko-KR' ); ?>>🇰🇷 한국어 (ko-KR)</option>
                        <option value="zh-CN" <?php selected( $language, 'zh-CN' ); ?>>🇨🇳 中文 (zh-CN)</option>
                    </select>
                </div>

                <!-- Save history -->
                <div class="vd-form-row">
                    <label><?php esc_html_e( 'Lưu lịch sử lệnh', 'voice-detected' ); ?></label>
                    <div>
                        <label class="vd-toggle-label">
                            <input type="checkbox" name="<?php echo VD_OPTION_KEY; ?>[show_history]" value="1" <?php checked( ! empty( $settings['show_history'] ) ); ?>>
                            <?php esc_html_e( 'Lưu lại câu nói và lệnh đã thực hiện vào Database', 'voice-detected' ); ?>
                        </label>
                    </div>
                </div>

                <!-- Max history -->
                <div class="vd-form-row">
                    <label for="vd-max-history"><?php esc_html_e( 'Giới hạn số dòng lưu', 'voice-detected' ); ?></label>
                    <input type="number" id="vd-max-history" class="vd-input" style="max-width:120px"
                           name="<?php echo VD_OPTION_KEY; ?>[max_history]"
                           value="<?php echo esc_attr( $settings['max_history'] ?? 50 ); ?>"
                           min="10" max="500">
                </div>
            </div>

            <?php submit_button( __( '💾 Lưu cài đặt', 'voice-detected' ), 'vd-btn vd-btn-primary', 'submit', false ); ?>
        </div>

        <!-- ═══ TAB 2: Engine & Audio ════════════════════════════ -->
        <div class="vd-tab-pane" id="vd-tab-engine">
            <div class="vd-card">
                <h2 class="vd-card-title">⚡ <?php esc_html_e( 'Engine nhận dạng giọng nói', 'voice-detected' ); ?></h2>

                <div class="vd-form-row">
                    <label><?php esc_html_e( 'Chọn Engine', 'voice-detected' ); ?></label>
                    <div class="vd-radio-group">
                        <label class="vd-engine-option">
                            <input type="radio" name="<?php echo VD_OPTION_KEY; ?>[api_engine]" value="webspeech" <?php checked( $engine, 'webspeech' ); ?>>
                            <div>
                                <strong>🌐 Web Speech API (Google Real-Time Streaming)</strong>
                                <span class="vd-badge-rec">Khuyên Dùng Cho Tốc Độ</span>
                                <p class="vd-hint">Nói tới đâu chữ tuôn ra tức thì tới đó, không cần API Key, không độ trễ upload, hỗ trợ tuyệt hảo trên Chrome/Edge.</p>
                            </div>
                        </label>
                        <label class="vd-engine-option">
                            <input type="radio" name="<?php echo VD_OPTION_KEY; ?>[api_engine]" value="groq" <?php checked( $engine, 'groq' ); ?>>
                            <div>
                                <strong>⚡ Groq Whisper (whisper-large-v3-turbo)</strong>
                                <span class="vd-badge-free">Miễn Phí 100%</span>
                                <p class="vd-hint">Xử lý trên chip LPU siêu tốc (0.3s), lọc ồn thông minh, nghe chuẩn xác giọng địa phương cả ba miền Bắc - Trung - Nam.</p>
                            </div>
                        </label>
                        <label class="vd-engine-option">
                            <input type="radio" name="<?php echo VD_OPTION_KEY; ?>[api_engine]" value="gemini" <?php checked( $engine, 'gemini' ); ?>>
                            <div>
                                <strong>✨ Gemini AI (Google AI Studio)</strong>
                                <p class="vd-hint">Sử dụng mô hình Gemini 1.5 Flash đa phương tiện phân tích âm thanh.</p>
                            </div>
                        </label>
                        <label class="vd-engine-option">
                            <input type="radio" name="<?php echo VD_OPTION_KEY; ?>[api_engine]" value="google" <?php checked( $engine, 'google' ); ?>>
                            <div>
                                <strong>☁️ Google Cloud Speech-to-Text</strong>
                                <p class="vd-hint">Dùng Google Cloud API Key với Speech API riêng biệt.</p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- API Key Card (for groq, gemini, google) -->
            <div class="vd-card" id="vd-api-card" <?php echo $engine === 'webspeech' ? 'style="display:none"' : ''; ?>>
                <h2 class="vd-card-title">🔑 <?php esc_html_e( 'API Key', 'voice-detected' ); ?></h2>

                <div class="vd-info-box" id="vd-info-groq" <?php echo $engine !== 'groq' ? 'style="display:none"' : ''; ?>>
                    ⚡ <strong>Groq API Key:</strong> Miễn phí 100% không giới hạn.
                    <a href="https://console.groq.com/keys" target="_blank" rel="noopener">Lấy API Key Groq miễn phí (gsk_...) →</a>
                </div>

                <div class="vd-info-box" id="vd-info-gemini" <?php echo $engine !== 'gemini' ? 'style="display:none"' : ''; ?>>
                    ✨ <strong>Gemini API Key:</strong> Miễn phí từ Google AI Studio.
                    <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Lấy Gemini API Key →</a>
                </div>

                <div class="vd-info-box" id="vd-info-google" <?php echo $engine !== 'google' ? 'style="display:none"' : ''; ?>>
                    ☁️ <strong>Google Cloud API Key:</strong> Cần bật Cloud Speech-to-Text API.
                    <a href="https://console.cloud.google.com/apis/library/speech.googleapis.com" target="_blank" rel="noopener">Mở Google Cloud Console →</a>
                </div>

                <div class="vd-form-row">
                    <label for="vd-api-key-input"><?php esc_html_e( 'Khóa API Key', 'voice-detected' ); ?></label>
                    <div>
                        <div class="vd-key-row">
                            <input type="password" id="vd-api-key-input" class="vd-input"
                                   name="<?php echo VD_OPTION_KEY; ?>[api_key]"
                                   value="<?php echo esc_attr( $settings['api_key'] ?? '' ); ?>"
                                   autocomplete="new-password" placeholder="Nhập API Key...">
                            <button type="button" id="vd-validate-key-btn" class="vd-btn vd-btn-secondary vd-btn-sm">
                                <?php esc_html_e( 'Kiểm tra Key', 'voice-detected' ); ?>
                            </button>
                        </div>
                        <div id="vd-key-validation-msg" class="vd-validation-msg"></div>
                    </div>
                </div>

                <!-- Confidence Gate -->
                <div class="vd-form-row">
                    <label><?php esc_html_e( 'Ngưỡng tin cậy', 'voice-detected' ); ?></label>
                    <div>
                        <div class="vd-slider-row">
                            <input type="range" id="vd-confidence-range" name="<?php echo VD_OPTION_KEY; ?>[confidence_min]"
                                   min="0" max="1" step="0.05"
                                   value="<?php echo esc_attr( $settings['confidence_min'] ?? 0.6 ); ?>">
                            <span class="vd-slider-val" id="vd-confidence-value">
                                <?php echo esc_html( round( ( $settings['confidence_min'] ?? 0.6 ) * 100 ) ); ?>%
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Studio Audio Processing Card -->
            <div class="vd-card">
                <h2 class="vd-card-title">🎙️ <?php esc_html_e( 'Bộ lọc âm & Tốc độ ngắt câu (VAD)', 'voice-detected' ); ?></h2>

                <!-- Noise Filter Toggle -->
                <div class="vd-form-row">
                    <label><?php esc_html_e( 'Lọc âm chống ồn', 'voice-detected' ); ?></label>
                    <div>
                        <label class="vd-toggle-label">
                            <input type="checkbox" name="<?php echo VD_OPTION_KEY; ?>[audio_filter]" value="1" <?php checked( $audio_filt ); ?>>
                            <strong><?php esc_html_e( 'Bật bộ xử lý lọc tạp âm Web Audio (High-pass Filter + Dynamic Compressor)', 'voice-detected' ); ?></strong>
                        </label>
                        <p class="vd-hint"><?php esc_html_e( 'Triệt tiêu tiếng quạt gió, tiếng ù điều hòa, hơi thở phì phò vào mic và tự động cân bằng âm lượng giọng nói.', 'voice-detected' ); ?></p>
                    </div>
                </div>

                <!-- VAD Sensitivity -->
                <div class="vd-form-row">
                    <label><?php esc_html_e( 'Tốc độ ngắt câu', 'voice-detected' ); ?></label>
                    <div class="vd-radio-group">
                        <label>
                            <input type="radio" name="<?php echo VD_OPTION_KEY; ?>[mic_sensitivity]" value="fast" <?php checked( $sensitivity, 'fast' ); ?>>
                            🚀 <?php esc_html_e( 'Nhanh (800ms) – Thực thi siêu tốc ngay khi vừa dứt lời', 'voice-detected' ); ?>
                        </label>
                        <label>
                            <input type="radio" name="<?php echo VD_OPTION_KEY; ?>[mic_sensitivity]" value="normal" <?php checked( $sensitivity, 'normal' ); ?>>
                            ⏱️ <?php esc_html_e( 'Tiêu chuẩn (1200ms) – Cân bằng hoàn hảo, tránh ngắt sớm', 'voice-detected' ); ?>
                        </label>
                        <label>
                            <input type="radio" name="<?php echo VD_OPTION_KEY; ?>[mic_sensitivity]" value="relaxed" <?php checked( $sensitivity, 'relaxed' ); ?>>
                            🧘 <?php esc_html_e( 'Thoải mái (1800ms) – Phù hợp người nói chậm, hay ngẫm nghĩ giữa câu', 'voice-detected' ); ?>
                        </label>
                    </div>
                </div>
            </div>

            <?php submit_button( __( '💾 Lưu cài đặt', 'voice-detected' ), 'vd-btn vd-btn-primary', 'submit', false ); ?>
        </div>

        <!-- ═══ TAB 3: Appearance & UI ═══════════════════════════ -->
        <div class="vd-tab-pane" id="vd-tab-appearance">
            <div class="vd-card">
                <h2 class="vd-card-title">🎨 <?php esc_html_e( 'Tùy chỉnh giao diện Widget', 'voice-detected' ); ?></h2>

                <!-- Theme -->
                <div class="vd-form-row">
                    <label><?php esc_html_e( 'Chủ đề thẩm mỹ', 'voice-detected' ); ?></label>
                    <div class="vd-radio-group" style="flex-direction:row;gap:20px;">
                        <label>
                            <input type="radio" name="<?php echo VD_OPTION_KEY; ?>[widget_theme]" value="glass" <?php checked( $theme, 'glass' ); ?>>
                            🔮 <strong>Glass</strong> (Kính mờ Aurora)
                        </label>
                        <label>
                            <input type="radio" name="<?php echo VD_OPTION_KEY; ?>[widget_theme]" value="dark" <?php checked( $theme, 'dark' ); ?>>
                            🌙 <strong>Dark</strong> (Tối Cyber)
                        </label>
                        <label>
                            <input type="radio" name="<?php echo VD_OPTION_KEY; ?>[widget_theme]" value="light" <?php checked( $theme, 'light' ); ?>>
                            ☀️ <strong>Light</strong> (Sáng tinh tế)
                        </label>
                    </div>
                </div>

                <!-- Accent Color Picker & Presets -->
                <div class="vd-form-row">
                    <label for="vd-accent-picker"><?php esc_html_e( 'Màu chủ đạo (Accent)', 'voice-detected' ); ?></label>
                    <div>
                        <div class="vd-color-picker-wrap">
                            <input type="color" id="vd-accent-picker" class="vd-color-input" value="<?php echo esc_attr( $accent ); ?>">
                            <input type="text" id="vd-accent-text" class="vd-input" style="max-width:120px;"
                                   name="<?php echo VD_OPTION_KEY; ?>[accent_color]"
                                   value="<?php echo esc_attr( $accent ); ?>" placeholder="#6366F1">
                        </div>

                        <!-- Quick Color Preset Chips -->
                        <div class="vd-preset-colors" aria-label="Preset Colors">
                            <span class="vd-color-chip" style="background:#6366F1;" data-color="#6366F1" title="Indigo Blue"></span>
                            <span class="vd-color-chip" style="background:#8B5CF6;" data-color="#8B5CF6" title="Neon Violet"></span>
                            <span class="vd-color-chip" style="background:#10B981;" data-color="#10B981" title="Emerald Green"></span>
                            <span class="vd-color-chip" style="background:#F43F5E;" data-color="#F43F5E" title="Rose Pink"></span>
                            <span class="vd-color-chip" style="background:#F59E0B;" data-color="#F59E0B" title="Amber Gold"></span>
                            <span class="vd-color-chip" style="background:#06B6D4;" data-color="#06B6D4" title="Cyan Sky"></span>
                            <span class="vd-color-chip" style="background:#EC4899;" data-color="#EC4899" title="Fuchsia Pink"></span>
                        </div>
                        <p class="vd-hint"><?php esc_html_e( 'Chọn màu preset hoặc click hộp màu để chọn bất kỳ màu nào hợp với nhận diện thương hiệu của bạn.', 'voice-detected' ); ?></p>
                    </div>
                </div>

                <!-- Widget Size -->
                <div class="vd-form-row">
                    <label><?php esc_html_e( 'Kích thước nút Mic', 'voice-detected' ); ?></label>
                    <div class="vd-radio-group" style="flex-direction:row;gap:20px;">
                        <label>
                            <input type="radio" name="<?php echo VD_OPTION_KEY; ?>[widget_size]" value="small" <?php checked( $size, 'small' ); ?>>
                            🔘 Nhỏ (46px)
                        </label>
                        <label>
                            <input type="radio" name="<?php echo VD_OPTION_KEY; ?>[widget_size]" value="medium" <?php checked( $size, 'medium' ); ?>>
                            🔘 Vừa (56px - Tiêu chuẩn)
                        </label>
                        <label>
                            <input type="radio" name="<?php echo VD_OPTION_KEY; ?>[widget_size]" value="large" <?php checked( $size, 'large' ); ?>>
                            🔘 Lớn (66px)
                        </label>
                    </div>
                </div>

                <!-- Position -->
                <div class="vd-form-row">
                    <label for="vd-widget-position"><?php esc_html_e( 'Vị trí trên màn hình', 'voice-detected' ); ?></label>
                    <select id="vd-widget-position" class="vd-select" style="max-width:240px" name="<?php echo VD_OPTION_KEY; ?>[widget_position]">
                        <option value="bottom-right" <?php selected( $position, 'bottom-right' ); ?>>↘ Góc phải dưới</option>
                        <option value="bottom-left"  <?php selected( $position, 'bottom-left' ); ?>>↙ Góc trái dưới</option>
                        <option value="top-right"    <?php selected( $position, 'top-right' ); ?>>↗ Góc phải trên</option>
                        <option value="top-left"     <?php selected( $position, 'top-left' ); ?>>↖ Góc trái trên</option>
                    </select>
                </div>

                <!-- Widget Title -->
                <div class="vd-form-row">
                    <label for="vd-widget-title"><?php esc_html_e( 'Tiêu đề bảng trợ lý', 'voice-detected' ); ?></label>
                    <div>
                        <input type="text" id="vd-widget-title" class="vd-input" style="max-width:320px;"
                               name="<?php echo VD_OPTION_KEY; ?>[widget_title]"
                               value="<?php echo esc_attr( $title ); ?>"
                               placeholder="Trợ lý ảo AI">
                    </div>
                </div>

                <!-- Suggestion Chips Editor -->
                <div class="vd-form-row">
                    <label for="vd-suggestion-chips-input"><?php esc_html_e( 'Dãy Chip gợi ý 1-chạm', 'voice-detected' ); ?></label>
                    <div>
                        <textarea id="vd-suggestion-chips-input" class="vd-input" rows="2"
                                  name="<?php echo VD_OPTION_KEY; ?>[suggestion_chips]"><?php echo esc_textarea( $chips ); ?></textarea>
                        <p class="vd-hint"><?php esc_html_e( 'Các nút gợi ý bấm nhanh ngăn cách bởi dấu phẩy (,). Có thể chèn emoji như 🏠, 🔥, ⬇️, 🔝.', 'voice-detected' ); ?></p>
                    </div>
                </div>

                <!-- Scroll Step -->
                <div class="vd-form-row">
                    <label for="vd-scroll-step"><?php esc_html_e( 'Khoảng cách cuộn trang (px)', 'voice-detected' ); ?></label>
                    <div>
                        <input type="number" id="vd-scroll-step" class="vd-input" style="max-width:140px;"
                               name="<?php echo VD_OPTION_KEY; ?>[scroll_step]"
                               value="<?php echo esc_attr( $scroll_step ); ?>"
                               min="150" max="1500" step="50">
                        <p class="vd-hint"><?php esc_html_e( 'Số pixel màn hình cuộn xuống/lên mỗi khi nói lệnh "cuộn xuống" hoặc "cuộn lên".', 'voice-detected' ); ?></p>
                    </div>
                </div>

                <!-- Live Preview Widget Mockup -->
                <div class="vd-preview-section">
                    <h3 class="vd-preview-title">👁️ <?php esc_html_e( 'Xem trước giao diện Widget (Live Preview)', 'voice-detected' ); ?></h3>
                    <div class="vd-preview-canvas">
                        <div class="vd-mock-panel" id="vd-mock-panel">
                            <div class="vd-mock-header">
                                <span class="vd-mock-orb"></span>
                                <strong id="vd-mock-title"><?php echo esc_html( $title ); ?></strong>
                                <span class="vd-mock-badge">READY</span>
                            </div>
                            <div class="vd-mock-dots">
                                <span class="vd-mdot" style="background:#4285F4;"></span>
                                <span class="vd-mdot" style="background:#EA4335;"></span>
                                <span class="vd-mdot" style="background:#FBBC05;"></span>
                                <span class="vd-mdot" style="background:#34A853;"></span>
                            </div>
                            <div class="vd-mock-text">"Đang nghe bạn nói…"</div>
                            <div class="vd-mock-chips" id="vd-mock-chips">
                                <span class="vd-mchip">🏠 Trang chủ</span>
                                <span class="vd-mchip">🔥 Mới nhất</span>
                                <span class="vd-mchip">⬇️ Cuộn xuống</span>
                            </div>
                        </div>
                        <div class="vd-mock-btn" id="vd-mock-btn">
                            🎙️
                        </div>
                    </div>
                </div>
            </div>

            <?php submit_button( __( '💾 Lưu cài đặt', 'voice-detected' ), 'vd-btn vd-btn-primary', 'submit', false ); ?>
        </div>

        <!-- ═══ TAB 4: Audio & Speech ════════════════════════════ -->
        <div class="vd-tab-pane" id="vd-tab-audio">
            <div class="vd-card">
                <h2 class="vd-card-title">🔊 <?php esc_html_e( 'Âm thanh & Phản hồi giọng nói (TTS)', 'voice-detected' ); ?></h2>

                <!-- Sound Effects (Chimes) -->
                <div class="vd-form-row">
                    <label><?php esc_html_e( 'Âm thanh hiệu ứng', 'voice-detected' ); ?></label>
                    <div>
                        <label class="vd-toggle-label">
                            <input type="checkbox" name="<?php echo VD_OPTION_KEY; ?>[sound_effects]" value="1" <?php checked( $sound_fx ); ?>>
                            <strong><?php esc_html_e( 'Bật chuông báo công nghệ cao (Web Audio Chimes Ding/Success)', 'voice-detected' ); ?></strong>
                        </label>
                        <p class="vd-hint"><?php esc_html_e( 'Phát âm đôi trong trẻo C5-G5 khi bật mic và E5-C6 khi hoàn thành câu lệnh, giúp người dùng cảm nhận tương tác sống động.', 'voice-detected' ); ?></p>
                    </div>
                </div>

                <!-- TTS Voice Toggle -->
                <div class="vd-form-row">
                    <label><?php esc_html_e( 'Đọc phản hồi tiếng Việt', 'voice-detected' ); ?></label>
                    <div>
                        <label class="vd-toggle-label">
                            <input type="checkbox" name="<?php echo VD_OPTION_KEY; ?>[tts_voice]" value="1" <?php checked( $tts_voice ); ?>>
                            <strong><?php esc_html_e( 'Trợ lý đọc phản hồi bằng giọng nói tiếng Việt (Text-to-Speech)', 'voice-detected' ); ?></strong>
                        </label>
                        <p class="vd-hint"><?php esc_html_e( 'Tự động đọc thông báo sống động như: "Đang về trang chủ", "Tìm thấy 3 bài viết", "Đang cuộn xuống"...', 'voice-detected' ); ?></p>
                    </div>
                </div>

                <!-- TTS Speed -->
                <div class="vd-form-row">
                    <label for="vd-tts-speed"><?php esc_html_e( 'Tốc độ giọng đọc', 'voice-detected' ); ?></label>
                    <div>
                        <div class="vd-slider-row">
                            <input type="range" id="vd-tts-speed" name="<?php echo VD_OPTION_KEY; ?>[tts_speed]"
                                   min="0.8" max="1.3" step="0.05"
                                   value="<?php echo esc_attr( $tts_speed ); ?>">
                            <span class="vd-slider-val" id="vd-tts-speed-val"><?php echo esc_html( $tts_speed ); ?>x</span>
                        </div>
                        <p class="vd-hint"><?php esc_html_e( '1.0x là tốc độ bình thường. Kéo lên 1.15x nếu muốn trợ lý nói nhanh nhẹn hơn.', 'voice-detected' ); ?></p>
                    </div>
                </div>
            </div>

            <?php submit_button( __( '💾 Lưu cài đặt', 'voice-detected' ), 'vd-btn vd-btn-primary', 'submit', false ); ?>
        </div>

        <!-- ═══ TAB 5: Commands Reference ═════════════════════════ -->
        <div class="vd-tab-pane" id="vd-tab-commands">
            <div class="vd-card">
                <h2 class="vd-card-title">🗣️ <?php esc_html_e( 'Bảng tra cứu khẩu lệnh tiếng Việt tự nhiên', 'voice-detected' ); ?></h2>
                <p class="vd-hint" style="margin-bottom:14px;">
                    <?php esc_html_e( 'Hệ thống tự động lọc bỏ các từ đệm tự nhiên người Việt hay nói (ví dụ: "ơi", "à", "hộ tôi", "giúp tôi", "cho tôi xem", "nhé", "với"). Bạn có thể nói rất tự nhiên:', 'voice-detected' ); ?>
                </p>

                <table class="widefat striped vd-cmd-table">
                    <thead>
                        <tr>
                            <th style="width:28%;"><?php esc_html_e( 'Nhóm chức năng', 'voice-detected' ); ?></th>
                            <th style="width:40%;"><?php esc_html_e( 'Cách nói tự nhiên được hỗ trợ', 'voice-detected' ); ?></th>
                            <th style="width:32%;"><?php esc_html_e( 'Hành vi thực thi', 'voice-detected' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>🏠 Điều hướng trang chủ</strong></td>
                            <td><code>về trang chủ</code>, <code>về nhà</code>, <code>mở trang chủ</code>, <code>quay về trang chủ</code></td>
                            <td>Chuyển hướng về trang chủ website tức thì</td>
                        </tr>
                        <tr>
                            <td><strong>🔍 Tìm kiếm bài viết</strong></td>
                            <td><code>tìm kiếm [tên bài]</code>, <code>tìm bài [từ khóa]</code>, <code>tìm giúp tôi bài viết mới nhất</code></td>
                            <td>Tìm và hiển thị danh sách Rich Card popup</td>
                        </tr>
                        <tr>
                            <td><strong>📄 Mở bài viết trực tiếp</strong></td>
                            <td><code>mở bài [tên bài]</code>, <code>xem bài [tên]</code>, <code>cho tôi xem bài viết [tên]</code></td>
                            <td>Tự tìm bài phù hợp nhất và mở thẳng vào trang bài viết</td>
                        </tr>
                        <tr>
                            <td><strong>📜 Cuộn trang mượt mà</strong></td>
                            <td><code>cuộn xuống</code>, <code>lướt xuống</code>, <code>cuộn lên</code>, <code>lướt lên</code></td>
                            <td>Cuộn màn hình theo bước nhảy pixel đã cấu hình</td>
                        </tr>
                        <tr>
                            <td><strong>🔝 Đỉnh & Đáy trang</strong></td>
                            <td><code>lên đầu trang</code>, <code>về đầu trang</code>, <code>xuống cuối trang</code>, <code>xuống đáy</code></td>
                            <td>Cuộn mượt về đầu hoặc cuối trang web</td>
                        </tr>
                        <tr>
                            <td><strong>📞 Trang liên hệ & Giới thiệu</strong></td>
                            <td><code>trang liên hệ</code>, <code>liên hệ</code>, <code>trang giới thiệu</code>, <code>tin tức</code></td>
                            <td>Mở các trang chức năng tương ứng</td>
                        </tr>
                        <tr>
                            <td><strong>🔄 Điều khiển trình duyệt</strong></td>
                            <td><code>tải lại trang</code>, <code>f5</code>, <code>quay lại</code>, <code>tiến tới</code>, <code>in trang</code></td>
                            <td>Reload, quay lại trang trước, mở hộp thoại in</td>
                        </tr>
                        <tr>
                            <td><strong>🔇 Bật / Tắt âm thanh</strong></td>
                            <td><code>tắt âm thanh</code>, <code>tắt tiếng</code>, <code>im lặng</code>, <code>bật âm thanh</code></td>
                            <td>Tắt/mở giọng đọc và chuông phản hồi trợ lý</td>
                        </tr>
                        <tr>
                            <td><strong>✕ Đóng bảng trợ lý</strong></td>
                            <td><code>đóng trợ lý</code>, <code>tắt trợ lý</code>, <code>ẩn trợ lý</code>, <code>đóng bảng</code></td>
                            <td>Thu gọn bảng điều khiển về nút nổi</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══ TAB 6: Shortcode ══════════════════════════════════ -->
        <div class="vd-tab-pane" id="vd-tab-shortcode">
            <div class="vd-card">
                <h2 class="vd-card-title">📋 <?php esc_html_e( 'Nhúng nút trợ lý bằng Shortcode', 'voice-detected' ); ?></h2>
                <p><?php esc_html_e( 'Bạn có thể chèn nút microphone vào bất kỳ bài viết, trang nội dung hoặc widget:', 'voice-detected' ); ?></p>

                <div class="vd-shortcode-box">
                    <code id="vd-shortcode-sample">[voice_detected theme="glass" label="Nhấn để nói"]</code>
                    <button type="button" id="vd-copy-shortcode" class="vd-btn vd-btn-secondary vd-btn-sm">📋 Copy Shortcode</button>
                </div>
            </div>
        </div>

        <!-- ═══ TAB 7: Analytics & Voice SEO (Feature 5) ════════════ -->
        <div class="vd-tab-pane" id="vd-tab-analytics">
            <div class="vd-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:10px;">
                    <div>
                        <h2 class="vd-card-title" style="margin:0;">📊 <?php esc_html_e( 'Bảng phân tích Voice Search SEO & Cảnh báo nội dung còn thiếu', 'voice-detected' ); ?></h2>
                        <p class="vd-hint" style="margin:4px 0 0 0;"><?php esc_html_e( 'Theo dõi hành vi người dùng bằng giọng nói và phát hiện câu hỏi người dùng tìm mà web chưa có.', 'voice-detected' ); ?></p>
                    </div>
                    <button type="button" id="vd-refresh-analytics-btn" class="vd-btn vd-btn-secondary vd-btn-sm">🔄 <?php esc_html_e( 'Làm mới dữ liệu', 'voice-detected' ); ?></button>
                </div>

                <!-- KPI Grid -->
                <div class="vd-kpi-grid">
                    <div class="vd-kpi-card">
                        <div class="vd-kpi-icon">🎙️</div>
                        <div class="vd-kpi-info">
                            <span class="vd-kpi-label"><?php esc_html_e( 'Tổng lượt giọng nói', 'voice-detected' ); ?></span>
                            <span class="vd-kpi-val" id="vd-kpi-total">--</span>
                        </div>
                    </div>
                    <div class="vd-kpi-card">
                        <div class="vd-kpi-icon">✅</div>
                        <div class="vd-kpi-info">
                            <span class="vd-kpi-label"><?php esc_html_e( 'Tỷ lệ thành công', 'voice-detected' ); ?></span>
                            <span class="vd-kpi-val vd-val-success" id="vd-kpi-success">--%</span>
                        </div>
                    </div>
                    <div class="vd-kpi-card">
                        <div class="vd-kpi-icon">🤖</div>
                        <div class="vd-kpi-info">
                            <span class="vd-kpi-label"><?php esc_html_e( 'Hỏi đáp AI', 'voice-detected' ); ?></span>
                            <span class="vd-kpi-val vd-val-ai" id="vd-kpi-ai">--%</span>
                        </div>
                    </div>
                    <div class="vd-kpi-card">
                        <div class="vd-kpi-icon">🔍</div>
                        <div class="vd-kpi-info">
                            <span class="vd-kpi-label"><?php esc_html_e( 'Tìm kiếm bài viết', 'voice-detected' ); ?></span>
                            <span class="vd-kpi-val vd-val-search" id="vd-kpi-search">--%</span>
                        </div>
                    </div>
                </div>

                <!-- Analytics 2-column Grid -->
                <div class="vd-analytics-grid" style="margin-top:24px;">
                    <!-- Left: Top Voice Searches -->
                    <div class="vd-analytics-col">
                        <h3 class="vd-col-title">🔥 <?php esc_html_e( 'Top từ khóa tìm kiếm giọng nói', 'voice-detected' ); ?></h3>
                        <p class="vd-col-sub"><?php esc_html_e( 'Các chủ đề người dùng truy vấn nhiều nhất trên trang web', 'voice-detected' ); ?></p>
                        <div id="vd-top-queries-list" class="vd-analytics-list">
                            <div class="vd-loading-state"><?php esc_html_e( 'Đang tải dữ liệu…', 'voice-detected' ); ?></div>
                        </div>
                    </div>

                    <!-- Right: Content Gap Alerts -->
                    <div class="vd-analytics-col">
                        <h3 class="vd-col-title">⚠️ <?php esc_html_e( 'Cảnh báo khoảng trống nội dung (Content Gap)', 'voice-detected' ); ?></h3>
                        <p class="vd-col-sub"><?php esc_html_e( 'Các câu hỏi người dùng nói nhưng chưa có bài viết tương ứng trên web! Bạn nên viết bài mới về các chủ đề này để đón đầu xu hướng SEO.', 'voice-detected' ); ?></p>
                        <div id="vd-content-gaps-list" class="vd-analytics-list">
                            <div class="vd-loading-state"><?php esc_html_e( 'Đang tải dữ liệu…', 'voice-detected' ); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </form><!-- /form -->
</div><!-- /.vd-admin-wrap -->
