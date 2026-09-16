<?php
/**
 * AJAX handlers for Voice Detected plugin.
 */

defined( 'ABSPATH' ) || exit;

class VD_Ajax {

    public static function init(): void {
        $actions = [
            'vd_transcribe',       // Transcribe audio via Google STT
            'vd_search_posts',     // Search posts by keyword
            'vd_get_history',      // Fetch command history
            'vd_clear_history',    // Clear command history
            'vd_validate_key',     // Validate API key
            'vd_log_command',      // Log a command execution
            'vd_ai_chat',          // Conversational AI Assistant
            'vd_summarize_post',   // AI Article Summarizer
            'vd_get_analytics',    // Voice Search Analytics
        ];

        foreach ( $actions as $action ) {
            add_action( 'wp_ajax_' . $action,        [ __CLASS__, $action ] );
            add_action( 'wp_ajax_nopriv_' . $action, [ __CLASS__, $action ] );
        }
    }

    // ─── Security helper ──────────────────────────────────────────────────────
    private static function verify(): void {
        if ( ! check_ajax_referer( 'vd_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce.' ], 403 );
        }
    }

    // ─── Transcribe audio ─────────────────────────────────────────────────────
    public static function vd_transcribe(): void {
        self::verify();

        $audio_b64 = sanitize_text_field( wp_unslash( $_POST['audio'] ?? '' ) );
        $language  = sanitize_text_field( $_POST['language'] ?? 'vi-VN' );

        if ( empty( $audio_b64 ) ) {
            wp_send_json_error( [ 'message' => 'No audio data.' ] );
        }

        $result = VD_API::transcribe( $audio_b64, $language );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( $result );
    }

    // ─── Search posts ─────────────────────────────────────────────────────────
    public static function vd_search_posts(): void {
        self::verify();

        $keyword  = sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) );
        $per_page = intval( $_POST['per_page'] ?? 5 );

        if ( empty( $keyword ) ) {
            wp_send_json_error( [ 'message' => 'Missing keyword.' ] );
        }

        // Strip dấu câu thừa ở cuối (?, !, ., ...) – Gemini hay thêm dấu ? vào câu hỏi
        $keyword = rtrim( $keyword, '?!.,;:… ' );

        $per_page = min( $per_page, 10 );

        // 1️⃣ Tìm theo TITLE trước (chính xác hơn)
        global $wpdb;
        $like      = '%' . $wpdb->esc_like( $keyword ) . '%';
        $title_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('post','page')
               AND post_title LIKE %s
             ORDER BY post_date DESC
             LIMIT %d",
            $like,
            $per_page
        ) );

        // 2️⃣ Nếu không tìm được theo title → fallback full-text WP_Query
        if ( empty( $title_ids ) ) {
            $query     = new WP_Query( [
                's'              => $keyword,
                'post_status'    => 'publish',
                'posts_per_page' => $per_page,
                'post_type'      => [ 'post', 'page' ],
            ] );
            $title_ids = wp_list_pluck( $query->posts, 'ID' );
        }

        $posts = [];
        foreach ( $title_ids as $id ) {
            $post    = get_post( $id );
            $posts[] = [
                'id'        => $post->ID,
                'title'     => get_the_title( $post ),
                'url'       => get_permalink( $post ),
                'type'      => $post->post_type,
                'excerpt'   => wp_trim_words( get_the_excerpt( $post ), 15 ),
                'thumbnail' => get_the_post_thumbnail_url( $post, 'thumbnail' ) ?: '',
            ];
        }

        wp_send_json_success( [
            'posts'   => $posts,
            'total'   => count( $posts ),
            'keyword' => $keyword,
        ] );
    }


    // ─── Get history ─────────────────────────────────────────────────────────
    public static function vd_get_history(): void {
        self::verify();

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Login required.' ] );
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'vd_history';
        $user_id = get_current_user_id();

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 50",
            $user_id
        ) );

        wp_send_json_success( [ 'history' => $rows ] );
    }

    // ─── Clear history ────────────────────────────────────────────────────────
    public static function vd_clear_history(): void {
        self::verify();

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Login required.' ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'vd_history';
        $wpdb->delete( $table, [ 'user_id' => get_current_user_id() ], [ '%d' ] );

        wp_send_json_success( [ 'message' => 'History cleared.' ] );
    }

    // ─── Validate API key ─────────────────────────────────────────────────────
    public static function vd_validate_key(): void {
        self::verify();

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
        }

        $api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
        // Đọc engine từ POST (form chưa lưu) để validate đúng endpoint
        $engine  = sanitize_text_field( wp_unslash( $_POST['engine'] ?? '' ) );
        $allowed = [ 'google', 'gemini', 'groq', 'webspeech' ];
        if ( ! in_array( $engine, $allowed, true ) ) {
            // Fallback về settings đã lưu nếu không có trong POST
            $settings = (array) get_option( VD_OPTION_KEY, [] );
            $engine   = $settings['api_engine'] ?? 'gemini';
        }

        if ( empty( $api_key ) ) {
            wp_send_json_error( [ 'message' => 'API key is empty.' ] );
        }

        $result = VD_API::validate_key( $api_key, $engine );
        if ( $result['valid'] ) {
            wp_send_json_success( [ 'message' => $result['message'] ] );
        } else {
            wp_send_json_error( [ 'message' => $result['message'] ] );
        }
    }

    // ─── Log command ──────────────────────────────────────────────────────────
    public static function vd_log_command(): void {
        self::verify();

        $settings = (array) get_option( VD_OPTION_KEY, [] );
        if ( empty( $settings['show_history'] ) ) {
            wp_send_json_success( [] );
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'vd_history';

        $wpdb->insert( $table, [
            'user_id'    => get_current_user_id(),
            'transcript' => sanitize_text_field( wp_unslash( $_POST['transcript'] ?? '' ) ),
            'command'    => sanitize_text_field( wp_unslash( $_POST['command']    ?? '' ) ),
            'confidence' => floatval( $_POST['confidence'] ?? 0 ),
            'executed'   => intval( $_POST['executed']    ?? 0 ),
        ], [ '%d', '%s', '%s', '%f', '%d' ] );

        // Prune old rows
        $max = intval( $settings['max_history'] ?? 50 );
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE user_id = %d AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d
                ) tmp
            )",
            get_current_user_id(),
            get_current_user_id(),
            $max
        ) );

        wp_send_json_success( [] );
    }

    // ─── AI Conversational Chat ───────────────────────────────────────────────
    public static function vd_ai_chat(): void {
        self::verify();

        $message = sanitize_text_field( wp_unslash( $_POST['message'] ?? '' ) );
        if ( empty( $message ) ) {
            wp_send_json_error( [ 'message' => 'Nội dung tin nhắn trống.' ] );
        }

        // Build brief site context
        $site_name = get_bloginfo( 'name' );
        $recent_posts = get_posts( [
            'numberposts' => 8,
            'post_status' => 'publish',
            'post_type'   => [ 'post', 'page' ],
        ] );

        $post_titles = [];
        foreach ( $recent_posts as $p ) {
            $post_titles[] = get_the_title( $p );
        }
        $titles_str = ! empty( $post_titles ) ? implode( ', ', $post_titles ) : 'Chưa có bài viết';
        $context = "Tên website: '{$site_name}'. Các bài viết hiện có trên trang web: [{$titles_str}].";

        $reply = VD_API::chat_completion( $message, $context );

        if ( ! is_wp_error( $reply ) && ! empty( $reply ) ) {
            wp_send_json_success( [
                'reply'  => $reply,
                'source' => 'ai',
            ] );
        }

        // Fallback friendly message if AI fails or no API key configured
        wp_send_json_success( [
            'reply'  => __( 'Nội dung này hiện không có trên website. Bạn có thể thử tìm bài viết khác hoặc nói "về trang chủ", "cuộn xuống" nhé!', 'voice-detected' ),
            'source' => 'fallback',
        ] );
    }

    // ─── AI Article Summarizer ────────────────────────────────────────────────
    public static function vd_summarize_post(): void {
        self::verify();

        $post_id = intval( $_POST['post_id'] ?? 0 );
        $title   = sanitize_text_field( wp_unslash( $_POST['title'] ?? $_POST['post_title'] ?? '' ) );
        $content = wp_kses_post( wp_unslash( $_POST['content'] ?? $_POST['post_content'] ?? '' ) );

        if ( $post_id > 0 ) {
            $post = get_post( $post_id );
            if ( $post ) {
                $title   = get_the_title( $post );
                $content = $post->post_content;
            }
        }

        if ( empty( $title ) && empty( $content ) ) {
            wp_send_json_error( [ 'message' => 'Không tìm thấy nội dung bài viết để tóm tắt.' ] );
        }

        $summary = VD_API::summarize_content( $title, $content );

        if ( is_wp_error( $summary ) ) {
            wp_send_json_error( [ 'message' => $summary->get_error_message() ] );
        }

        wp_send_json_success( [
            'summary' => $summary,
        ] );
    }

    // ─── Voice Search SEO Analytics ───────────────────────────────────────────
    public static function vd_get_analytics(): void {
        self::verify();

        global $wpdb;
        $table = $wpdb->prefix . 'vd_history';

        // Check table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            wp_send_json_success( [
                'kpi' => [
                    'total_queries' => 0,
                    'success_rate'  => 100,
                    'ai_rate'       => 0,
                    'search_rate'   => 0,
                ],
                'top_searches' => [],
                'content_gaps' => [],
            ] );
        }

        $total_queries = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $executed      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE executed = 1" );
        $ai_chats      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE command LIKE '%ai%' OR command = 'Trợ lý AI trả lời'" );
        $searches_cnt  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE command LIKE 'search%'" );

        $success_rate = $total_queries > 0 ? round( ( $executed / $total_queries ) * 100 ) : 100;
        $ai_chat_rate = $total_queries > 0 ? round( ( $ai_chats / $total_queries ) * 100 ) : 0;
        $search_rate  = $total_queries > 0 ? round( ( $searches_cnt / $total_queries ) * 100 ) : 0;

        // Top searches with percentage calculation
        $top_raw = $wpdb->get_results(
            "SELECT transcript as query, COUNT(*) as count 
             FROM {$table} 
             WHERE (command LIKE 'search%' OR command LIKE 'post%' OR transcript LIKE '%tìm%') AND transcript != ''
             GROUP BY transcript 
             ORDER BY count DESC 
             LIMIT 8",
            ARRAY_A
        );

        $max_search   = ! empty( $top_raw ) ? max( array_column( $top_raw, 'count' ) ) : 1;
        $top_searches = [];
        foreach ( (array) $top_raw as $row ) {
            $top_searches[] = [
                'query' => $row['query'],
                'count' => (int) $row['count'],
                'pct'   => round( ( (int) $row['count'] / max( 1, $max_search ) ) * 100 ),
            ];
        }

        // Content gaps: queries where executed = 0 or command contains 'không có' or low confidence
        $gap_raw = $wpdb->get_results(
            "SELECT transcript as query, COUNT(*) as count 
             FROM {$table} 
             WHERE (executed = 0 OR command = 'no_match' OR command LIKE '%không có%' OR command = 'low_confidence') AND transcript != ''
             GROUP BY transcript 
             ORDER BY count DESC 
             LIMIT 8",
            ARRAY_A
        );

        $content_gaps = [];
        foreach ( (array) $gap_raw as $row ) {
            $content_gaps[] = [
                'query' => $row['query'],
                'count' => (int) $row['count'],
            ];
        }

        wp_send_json_success( [
            'kpi' => [
                'total_queries' => $total_queries,
                'success_rate'  => $success_rate,
                'ai_rate'       => $ai_chat_rate,
                'search_rate'   => $search_rate,
            ],
            'top_searches' => $top_searches,
            'content_gaps' => $content_gaps,
        ] );
    }
}
