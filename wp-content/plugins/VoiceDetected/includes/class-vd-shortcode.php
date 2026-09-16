<?php
/**
 * [voice_detected] shortcode.
 * Usage: [voice_detected theme="dark" position="bottom-right"]
 */

defined( 'ABSPATH' ) || exit;

class VD_Shortcode {

    public static function init(): void {
        add_shortcode( 'voice_detected', [ __CLASS__, 'render' ] );
    }

    public static function render( array $atts ): string {
        $atts = shortcode_atts( [
            'theme'    => 'dark',
            'position' => 'inline',
            'size'     => 'normal',
            'label'    => '',
        ], $atts, 'voice_detected' );

        ob_start();
        ?>
        <div class="vd-shortcode-wrapper vd-theme-<?php echo esc_attr( $atts['theme'] ); ?> vd-size-<?php echo esc_attr( $atts['size'] ); ?>"
             data-position="<?php echo esc_attr( $atts['position'] ); ?>">
            <button class="vd-mic-btn vd-inline-btn" id="vd-inline-<?php echo uniqid(); ?>" aria-label="<?php esc_attr_e( 'Bắt đầu nhận dạng giọng nói', 'voice-detected' ); ?>">
                <span class="vd-mic-icon">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 1C10.34 1 9 2.34 9 4V12C9 13.66 10.34 15 12 15C13.66 15 15 13.66 15 12V4C15 2.34 13.66 1 12 1Z" fill="currentColor"/>
                        <path d="M19 12C19 15.87 15.87 19 12 19C8.13 19 5 15.87 5 12H3C3 16.97 6.93 21.07 12 21.51V23H14V21.51C19.07 21.07 23 16.97 23 12H19Z" fill="currentColor"/>
                    </svg>
                </span>
                <?php if ( ! empty( $atts['label'] ) ) : ?>
                    <span class="vd-btn-label"><?php echo esc_html( $atts['label'] ); ?></span>
                <?php endif; ?>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }
}
