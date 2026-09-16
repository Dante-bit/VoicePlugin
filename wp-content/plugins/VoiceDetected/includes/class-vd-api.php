<?php
/**
 * Voice Detected – API handler.
 * Supports: Google Cloud STT, Gemini AI (generativelanguage API)
 */

defined( 'ABSPATH' ) || exit;

class VD_API {

    /**
     * Transcribe a base64-encoded audio blob via Google Cloud STT.
     *
     * @param string $audio_base64 Raw audio as base64.
     * @param string $language     BCP-47 language code, e.g. "vi-VN".
     * @return array{ transcript: string, confidence: float }|WP_Error
     */
    public static function transcribe( string $audio_base64, string $language = 'vi-VN' ) {
        $settings = (array) get_option( VD_OPTION_KEY, [] );
        $engine   = $settings['api_engine'] ?? 'gemini';

        if ( 'groq' === $engine ) {
            return self::transcribe_groq( $audio_base64, $language );
        }
        if ( 'gemini' === $engine ) {
            return self::transcribe_gemini( $audio_base64, $language );
        }
        if ( 'google' === $engine ) {
            return self::transcribe_google( $audio_base64, $language );
        }
        return new WP_Error( 'no_engine', __( 'Chưa chọn engine nhận dạng.', 'voice-detected' ) );
    }

    /**
     * Transcribe via Google Cloud Speech-to-Text.
     */
    private static function transcribe_google( string $audio_base64, string $language ) {
        $settings = (array) get_option( VD_OPTION_KEY, [] );
        $api_key  = trim( $settings['api_key'] ?? '' );

        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'Google API key chưa được cấu hình.', 'voice-detected' ) );
        }

        $endpoint = 'https://speech.googleapis.com/v1/speech:recognize?key=' . urlencode( $api_key );

        $body = [
            'config' => [
                'encoding'                 => 'WEBM_OPUS',
                'sampleRateHertz'          => 48000,
                'languageCode'             => $language,
                'alternativeLanguageCodes' => [ 'en-US', 'vi-VN' ],
                'enableAutomaticPunctuation' => false,
                'model'                    => 'command_and_search',
            ],
            'audio' => [
                'content' => $audio_base64,
            ],
        ];

        $response = wp_remote_post( $endpoint, [
            'timeout' => 15,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code ) {
            $msg = $data['error']['message'] ?? 'Unknown Google STT error';
            return new WP_Error( 'google_stt_error', $msg, [ 'status' => $code ] );
        }

        // Parse first result
        $results     = $data['results'] ?? [];
        $transcript  = '';
        $confidence  = 0.0;

        if ( ! empty( $results[0]['alternatives'][0] ) ) {
            $alt        = $results[0]['alternatives'][0];
            $transcript = $alt['transcript'] ?? '';
            $confidence = floatval( $alt['confidence'] ?? 0.9 );
        }

        return [
            'transcript' => strtolower( trim( $transcript ) ),
            'confidence' => $confidence,
        ];
    }

    /**
     * Transcribe via Gemini AI (generativelanguage.googleapis.com).
     * Gemini 1.5 Flash supports audio input natively.
     */
    private static function transcribe_gemini( string $audio_base64, string $language ) {
        $settings = (array) get_option( VD_OPTION_KEY, [] );
        $api_key  = trim( $settings['api_key'] ?? '' );

        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'Gemini API key chưa được cấu hình.', 'voice-detected' ) );
        }

        $lang_hint = ( strpos( $language, 'vi' ) !== false )
            ? 'Người dùng nói tiếng Việt.'
            : 'The user speaks English.';

        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=' . urlencode( $api_key );

        $body = [
            'contents' => [[
                'parts' => [
                    [
                        'inline_data' => [
                            'mime_type' => 'audio/webm',
                            'data'      => $audio_base64,
                        ],
                    ],
                    [
                        'text' => $lang_hint . ' Transcribe the audio exactly as spoken. Return ONLY the transcribed text, no explanation, no punctuation, lowercase.',
                    ],
                ],
            ]],
            'generationConfig' => [
                'temperature'     => 0,
                'maxOutputTokens' => 256,
            ],
        ];

        $response = wp_remote_post( $endpoint, [
            'timeout' => 20,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $data['error']['message'] ?? 'Gemini API error ' . $code;
            return new WP_Error( 'gemini_error', $msg, [ 'status' => $code ] );
        }

        $transcript = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $transcript = strtolower( trim( $transcript ) );

        return [
            'transcript' => $transcript,
            'confidence' => 0.95, // Gemini doesn't return confidence scores
        ];
    }

    /**
     * Transcribe via Groq Whisper API (whisper-large-v3).
     * Extremely fast (200-400ms), highest accuracy, and free tier.
     *
     * @param string $audio_base64 Raw audio as base64.
     * @param string $language     Language code, e.g. "vi-VN".
     * @return array{ transcript: string, confidence: float }|WP_Error
     */
    private static function transcribe_groq( string $audio_base64, string $language ) {
        $settings = (array) get_option( VD_OPTION_KEY, [] );
        $api_key  = trim( $settings['api_key'] ?? '' );

        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'Groq API key chưa được cấu hình.', 'voice-detected' ) );
        }

        $endpoint = 'https://api.groq.com/openai/v1/audio/transcriptions';
        $boundary = wp_generate_password( 24, false );

        $headers = [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
        ];

        // Language ISO-639-1: 'vi-VN' -> 'vi'
        $lang_code = substr( $language, 0, 2 );
        $audio_data = base64_decode( $audio_base64 );

        $payload  = "--{$boundary}\r\n";
        $payload .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
        $payload .= "whisper-large-v3-turbo\r\n";

        if ( ! empty( $lang_code ) ) {
            $payload .= "--{$boundary}\r\n";
            $payload .= "Content-Disposition: form-data; name=\"language\"\r\n\r\n";
            $payload .= "{$lang_code}\r\n";
        }

        // Context prompt to prevent hallucination loops and guide vocabulary
        $payload .= "--{$boundary}\r\n";
        $payload .= "Content-Disposition: form-data; name=\"prompt\"\r\n\r\n";
        $payload .= "Trợ lý giọng nói website WordPress tiếng Việt. Khẩu lệnh: tìm bài viết, mở bài viết, về trang chủ, mới nhất hôm nay, liên hệ, cuộn trang.\r\n";

        $payload .= "--{$boundary}\r\n";
        $payload .= "Content-Disposition: form-data; name=\"temperature\"\r\n\r\n";
        $payload .= "0\r\n";

        $payload .= "--{$boundary}\r\n";
        $payload .= "Content-Disposition: form-data; name=\"response_format\"\r\n\r\n";
        $payload .= "json\r\n";

        $payload .= "--{$boundary}\r\n";
        $payload .= "Content-Disposition: form-data; name=\"file\"; filename=\"voice_audio.webm\"\r\n";
        $payload .= "Content-Type: audio/webm\r\n\r\n";
        $payload .= $audio_data . "\r\n";
        $payload .= "--{$boundary}--\r\n";

        $response = wp_remote_post( $endpoint, [
            'timeout' => 18,
            'headers' => $headers,
            'body'    => $payload,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code ) {
            $msg = $data['error']['message'] ?? ( 'Groq Whisper error ' . $code );
            return new WP_Error( 'groq_whisper_error', $msg, [ 'status' => $code ] );
        }

        $transcript = $data['text'] ?? '';
        $transcript = strtolower( trim( $transcript ) );

        // Clean repeated hallucinated words (e.g. "bây giờ bây giờ" -> "bây giờ")
        $transcript = preg_replace( '/(\b\S+\b)(?:\s+\1)+/iu', '$1', $transcript );
        // Remove trailing punctuation
        $transcript = trim( preg_replace( '/[?.!,;:…]+$/u', '', $transcript ) );

        return [
            'transcript' => $transcript,
            'confidence' => 0.98,
        ];
    }

    /**
     * Quick validation: hit the API to verify the key.
     *
     * @param string $api_key The API key to test.
     * @param string $engine  Engine to validate against: 'groq', 'gemini', or 'google'.
     *                        If empty, falls back to saved settings.
     * @return array{ valid: bool, message: string, code: int }
     */
    public static function validate_key( string $api_key, string $engine = '' ): array {
        if ( empty( $engine ) ) {
            $settings = (array) get_option( VD_OPTION_KEY, [] );
            $engine   = $settings['api_engine'] ?? 'gemini';
        }

        if ( 'groq' === $engine ) {
            // Validate via Groq Models endpoint
            $endpoint = 'https://api.groq.com/openai/v1/models';
            $response = wp_remote_get( $endpoint, [
                'timeout'   => 10,
                'sslverify' => true,
                'headers'   => [
                    'Authorization' => 'Bearer ' . $api_key,
                ],
            ] );
        } elseif ( 'gemini' === $engine ) {
            // Validate via Gemini: send a dummy text request
            $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=' . urlencode( $api_key );
            $response = wp_remote_post( $endpoint, [
                'timeout'   => 10,
                'sslverify' => true,
                'headers'   => [ 'Content-Type' => 'application/json' ],
                'body'      => wp_json_encode( [
                    'contents' => [[ 'parts' => [[ 'text' => 'hi' ]] ]],
                ] ),
            ] );
        } else {
            // Validate via Google Cloud STT
            $endpoint = 'https://speech.googleapis.com/v1/speech:recognize?key=' . urlencode( $api_key );
            $response = wp_remote_post( $endpoint, [
                'timeout'   => 10,
                'sslverify' => true,
                'headers'   => [ 'Content-Type' => 'application/json' ],
                'body'      => wp_json_encode( [
                    'config' => [ 'encoding' => 'LINEAR16', 'sampleRateHertz' => 16000, 'languageCode' => 'en-US' ],
                    'audio'  => [ 'content' => '' ],
                ] ),
            ] );
        }

        // Network / SSL error
        if ( is_wp_error( $response ) ) {
            return [
                'valid'   => false,
                'message' => 'Lỗi kết nối: ' . $response->get_error_message(),
                'code'    => 0,
            ];
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // 200 = success, 400 = bad request but key accepted (e.g. empty audio)
        if ( in_array( $code, [ 200, 400 ], true ) ) {
            // Gemini 400 with INVALID_ARGUMENT means key OK but request malformed – that's fine
            $status = $body['error']['status'] ?? '';
            if ( 400 === $code && in_array( $status, [ 'UNAUTHENTICATED', 'PERMISSION_DENIED' ], true ) ) {
                $msg = $body['error']['message'] ?? 'API key không hợp lệ.';
                return [ 'valid' => false, 'message' => $msg, 'code' => $code ];
            }
            return [ 'valid' => true, 'message' => 'API key hợp lệ ✓', 'code' => $code ];
        }

        if ( 401 === $code ) {
            $msg = $body['error']['message'] ?? 'API key không chính xác hoặc hết hạn (401 Unauthorized).';
            return [ 'valid' => false, 'message' => $msg, 'code' => 401 ];
        }

        // Any other error code
        $api_msg = $body['error']['message'] ?? ( 'HTTP ' . $code );
        return [ 'valid' => false, 'message' => $api_msg, 'code' => $code ];
    }

    /**
     * AI Chat completion for conversational questions & non-command queries.
     * Supports Groq (qwen/qwen3.8-27b) and Gemini AI.
     *
     * @param string $user_message The user's question or message.
     * @param string $site_context Information about the WordPress site.
     * @return string|WP_Error
     */
    public static function chat_completion( string $user_message, string $site_context = '' ) {
        $settings = (array) get_option( VD_OPTION_KEY, [] );
        $api_key  = trim( $settings['api_key'] ?? '' );
        $engine   = $settings['api_engine'] ?? 'webspeech';

        // Even if engine is webspeech, if api_key is present and starts with 'gsk_', we can use Groq for chatting!
        $is_groq   = ( 'groq' === $engine || 0 === strpos( $api_key, 'gsk_' ) );
        $is_gemini = ( 'gemini' === $engine || 0 === strpos( $api_key, 'AIzaSy' ) );

        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'Chưa cấu hình API key cho AI.', 'voice-detected' ) );
        }

        $system_instruction = "Bạn là trợ lý AI thông minh, thân thiện của website WordPress. "
            . "Ngữ cảnh website: {$site_context}. "
            . "QUY TẮC QUAN TRỌNG: "
            . "1. Trả lời ngắn gọn, súc tích (1-2 câu) bằng tiếng Việt tự nhiên. "
            . "2. Nếu người dùng hỏi nội dung hoặc tìm kiếm bài viết mà KHÔNG có trên website này, hãy trả lời rõ ràng và ngắn gọn rằng website hiện không có nội dung đó. "
            . "3. Không tự bịa đặt bài viết hay thông tin mà website không có.";

        if ( $is_groq ) {
            $endpoint = 'https://api.groq.com/openai/v1/chat/completions';
            $body = [
                'model'       => 'qwen/qwen3.8-27b',
                'messages'    => [
                    [ 'role' => 'system', 'content' => $system_instruction ],
                    [ 'role' => 'user',   'content' => $user_message ],
                ],
                'max_tokens'  => 160,
                'temperature' => 0.6,
            ];

            $resp = wp_remote_post( $endpoint, [
                'timeout' => 12,
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'body'    => wp_json_encode( $body ),
            ] );

            if ( is_wp_error( $resp ) ) {
                return $resp;
            }

            $data = json_decode( wp_remote_retrieve_body( $resp ), true );
            $reply = $data['choices'][0]['message']['content'] ?? '';
            if ( ! empty( $reply ) ) {
                return trim( $reply );
            }
            return new WP_Error( 'groq_chat_failed', 'Không nhận được câu trả lời từ Groq.' );
        }

        if ( $is_gemini ) {
            $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . urlencode( $api_key );
            $body = [
                'systemInstruction' => [
                    'parts' => [ [ 'text' => $system_instruction ] ],
                ],
                'contents' => [
                    [
                        'role'  => 'user',
                        'parts' => [ [ 'text' => $user_message ] ],
                    ],
                ],
                'generationConfig' => [
                    'temperature'     => 0.7,
                    'maxOutputTokens' => 160,
                ],
            ];

            $resp = wp_remote_post( $endpoint, [
                'timeout' => 12,
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( $body ),
            ] );

            if ( is_wp_error( $resp ) ) {
                return $resp;
            }

            $data = json_decode( wp_remote_retrieve_body( $resp ), true );
            $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if ( ! empty( $reply ) ) {
                return trim( $reply );
            }
            return new WP_Error( 'gemini_chat_failed', 'Không nhận được câu trả lời từ Gemini.' );
        }

        return new WP_Error( 'unsupported_engine', 'Engine không hỗ trợ trò chuyện AI.' );
    }

    /**
     * AI Article Summarizer: produce 3 key bullet takeaways + reading time.
     *
     * @param string $title   Article title.
     * @param string $content Article text content.
     * @return array{ title: string, bullets: array<string>, conclusion: string, read_time: string }|WP_Error
     */
    public static function summarize_content( string $title, string $content ) {
        $settings = (array) get_option( VD_OPTION_KEY, [] );
        $api_key  = trim( $settings['api_key'] ?? '' );
        $engine   = $settings['api_engine'] ?? 'webspeech';

        $is_groq   = ( 'groq' === $engine || 0 === strpos( $api_key, 'gsk_' ) );
        $is_gemini = ( 'gemini' === $engine || 0 === strpos( $api_key, 'AIzaSy' ) );

        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'Chưa cấu hình API key cho AI tóm tắt.', 'voice-detected' ) );
        }

        // Limit content to ~3500 chars for fast response
        $clean_content = wp_strip_all_tags( $content );
        if ( mb_strlen( $clean_content ) > 3500 ) {
            $clean_content = mb_substr( $clean_content, 0, 3500 ) . '...';
        }

        $system_prompt = "Bạn là chuyên gia phân tích và tóm tắt bài viết cho website WordPress. "
            . "Nhiệm vụ của bạn: Tóm tắt bài viết được cung cấp thành đúng 3 gạch đầu dòng then chốt và 1 câu kết luận ngắn gọn bằng tiếng Việt. "
            . "Định dạng JSON trả về bắt buộc:\n"
            . "{\n"
            . '  "bullets": ["Ý chính 1", "Ý chính 2", "Ý chính 3"],' . "\n"
            . '  "conclusion": "Câu kết luận ngắn gọn",' . "\n"
            . '  "read_time": "3 phút đọc"' . "\n"
            . "}\n"
            . "Không viết thêm bất kỳ chữ nào ngoài mã JSON hợp lệ.";

        $user_prompt = "Tiêu đề bài viết: {$title}\nNội dung bài viết:\n{$clean_content}";

        $json_str = '';
        if ( $is_groq ) {
            $endpoint = 'https://api.groq.com/openai/v1/chat/completions';
            $body = [
                'model'           => 'qwen/qwen3.8-27b',
                'messages'        => [
                    [ 'role' => 'system', 'content' => $system_prompt ],
                    [ 'role' => 'user',   'content' => $user_prompt ],
                ],
                'max_tokens'      => 350,
                'temperature'     => 0.4,
                'response_format' => [ 'type' => 'json_object' ],
            ];

            $resp = wp_remote_post( $endpoint, [
                'timeout' => 15,
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'body'    => wp_json_encode( $body ),
            ] );

            if ( is_wp_error( $resp ) ) {
                return $resp;
            }

            $data = json_decode( wp_remote_retrieve_body( $resp ), true );
            $json_str = $data['choices'][0]['message']['content'] ?? '';
        } elseif ( $is_gemini ) {
            $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . urlencode( $api_key );
            $body = [
                'systemInstruction' => [
                    'parts' => [ [ 'text' => $system_prompt ] ],
                ],
                'contents' => [
                    [
                        'role'  => 'user',
                        'parts' => [ [ 'text' => $user_prompt ] ],
                    ],
                ],
                'generationConfig' => [
                    'temperature'       => 0.4,
                    'maxOutputTokens'   => 350,
                    'responseMimeType'  => 'application/json',
                ],
            ];

            $resp = wp_remote_post( $endpoint, [
                'timeout' => 15,
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( $body ),
            ] );

            if ( is_wp_error( $resp ) ) {
                return $resp;
            }

            $data = json_decode( wp_remote_retrieve_body( $resp ), true );
            $json_str = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        }

        // Parse JSON output
        $parsed = json_decode( $json_str, true );
        if ( is_array( $parsed ) && ! empty( $parsed['bullets'] ) ) {
            return [
                'title'      => $title,
                'bullets'    => array_slice( (array) $parsed['bullets'], 0, 3 ),
                'conclusion' => $parsed['conclusion'] ?? '',
                'read_time'  => $parsed['read_time'] ?? '2 phút đọc',
            ];
        }

        return new WP_Error( 'summary_parse_failed', 'Không thể tạo bản tóm tắt từ AI.' );
    }
}

