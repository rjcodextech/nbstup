<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package AutoWebPConverterLogger
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * 1. Helper Function: Single Site Cleanup
 * Cleans DB options, transients, and cron for the current blog context.
 */
function autoweco_cleanup_current_site_db() {
    delete_option( 'autoweco_convert_images_to_webp_options' );
    delete_transient( 'autoweco_conversion_errors' );
    delete_transient( 'autoweco_settings_reset' );

    // Robust Cron Cleanup (Loop to remove duplicates if any)
    while ( wp_next_scheduled( 'autoweco_periodic_log_cleanup_event' ) ) {
        wp_clear_scheduled_hook( 'autoweco_periodic_log_cleanup_event' );
    }

    // Clean up post meta flags
    delete_post_meta_by_key( '_autoweco_pending_conversion' );
}

/**
 * 2. Execute Database Cleanup
 * Handles both Single Site and Multisite environments.
 */
if ( is_multisite() ) {
    $sites = get_sites();
    if ( $sites ) {
        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );
            autoweco_cleanup_current_site_db();
            restore_current_blog();
        }
    }
} else {
    autoweco_cleanup_current_site_db();
}

/**
 * 3. Helper Function: Recursive Directory Delete
 * Uses direct PHP for reliability (no FTP credentials needed).
 */
if ( ! function_exists( 'autoweco_recursive_remove_dir' ) ) {
    function autoweco_recursive_remove_dir( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        // Suppress warnings if directory is unreadable
        $files = @scandir( $dir );
        if ( false === $files ) {
            return;
        }

        $files = array_diff( $files, array( '.', '..' ) );

        foreach ( $files as $file ) {
            $path = $dir . '/' . $file;
            
            if ( is_dir( $path ) ) {
                autoweco_recursive_remove_dir( $path );
            } else {
                @unlink( $path );
            }
        }

        @rmdir( $dir );
    }
}

/**
 * 4. Execute File System Cleanup
 * Removes log directories created in wp-content/uploads.
 */
$upload_dir = wp_upload_dir();
$basedir    = $upload_dir['basedir'];

// Find all candidate folders
$folders = glob( trailingslashit( $basedir ) . 'auto-webp-converter-logger*' );

// Fix: Ensure $folders is an array even if glob returns false
$folders = $folders ?: array();

if ( ! empty( $folders ) ) {
    foreach ( $folders as $folder ) {
        if ( is_dir( $folder ) ) {
            $dirname = basename( $folder );

            // SAFETY CHECK: Only delete folders matching our exact pattern
            // Matches: "auto-webp-converter-logger" OR "auto-webp-converter-logger-2"
            if ( preg_match( '/^auto-webp-converter-logger(-\d+)?$/', $dirname ) ) {
                
                // Extra Safety: Path traversal check
                if ( strpos( realpath( $folder ), realpath( $basedir ) ) === 0 ) {
                    autoweco_recursive_remove_dir( $folder );
                }
            }
        }
    }
}

