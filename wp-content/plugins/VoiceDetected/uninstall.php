<?php
/**
 * Voice Detected – Uninstall
 * Runs when the plugin is deleted from WordPress.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove plugin options
delete_option( 'voice_detected_settings' );

// Drop history table
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}vd_history" );
