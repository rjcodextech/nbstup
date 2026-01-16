<?php
/**
 * Plugin Name: Auto WebP Converter & Logger
 * Description: Automatically converts uploaded images (JPG/PNG) to WebP format, manages storage, protects server memory, and logs conversion activity.
 * Version: 2.0
 * Requires PHP: 7.0
 * Author: BabaPinnak
 * Author URI: https://babapinnak.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: auto-webp-converter-logger
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Main plugin class for Auto WebP Converter & Logger.
 */
class Autoweco_ConvertImagesToWebP {

    /**
     * Maximum file size allowed for conversion (15MB).
     */
    const MAX_FILE_SIZE = 15728640;

    private $autoweco_options;
    private $autoweco_log_file;
    private $upload_dir;
    private $is_fresh_upload_request = false;

    /**
     * Static locks and caches.
     */
    private static $metadata_processing_lock = array();
    private static $request_errors = array();
    private static $image_info_cache = array();
    private static $editor_support_cache = array();

    public function __construct() {
        $this->autoweco_options = get_option('autoweco_convert_images_to_webp_options');
        
        if (!is_array($this->autoweco_options)) {
            $this->autoweco_options = array(
                'quality'         => 85,
                'delete_original' => true,
                'log_conversions' => false, 
                'log_retention'   => 'never',
            );
            update_option('autoweco_convert_images_to_webp_options', $this->autoweco_options);
        }

        $this->upload_dir = wp_upload_dir();

        // Setup secure logging directory with Multisite support
        $blog_id = get_current_blog_id();
        $log_dir_name = is_multisite() ? "auto-webp-converter-logger-{$blog_id}/" : 'auto-webp-converter-logger/';
        $log_dir = trailingslashit($this->upload_dir['basedir']) . $log_dir_name;
        
        if (!file_exists($log_dir)) { 
            wp_mkdir_p($log_dir); 
        }
        
        // Security: Prevent direct browser access to logs
        if (!file_exists($log_dir . '.htaccess')) { 
            $htaccess_content = "# Apache 2.2\n<IfModule !mod_authz_core.c>\nOrder Deny,Allow\nDeny from all\n</IfModule>\n\n# Apache 2.4\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>";
            file_put_contents($log_dir . '.htaccess', $htaccess_content); 
        }
        if (!file_exists($log_dir . 'index.php')) { 
            file_put_contents($log_dir . 'index.php', '<?php // Silence is golden'); 
        }
        
        $this->autoweco_log_file = $log_dir . 'webp-conversion-log.txt';
        
        // --- Hooks ---
        add_action('init', array($this, 'autoweco_load_textdomain'));
        add_action('admin_menu', array($this, 'autoweco_add_settings_page'));
        add_action('admin_init', array($this, 'autoweco_register_settings'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'autoweco_add_settings_link'));
        
        // Conversion Pipeline
        add_filter('wp_handle_upload', array($this, 'autoweco_convert_images_to_webp_on_upload'));
        add_action('add_attachment', array($this, 'autoweco_mark_attachment_pending'), 10, 1);
        add_action('add_attachment', array($this, 'autoweco_force_mime_sync'), 20, 1);
        add_filter('wp_generate_attachment_metadata', array($this, 'autoweco_process_metadata_and_thumbnails'), 10, 2);
        add_action('delete_attachment', array($this, 'autoweco_cleanup_attachment_meta'));

        // Utilities
        add_action('admin_post_autoweco_view_full_log', array($this, 'autoweco_handle_view_full_log'));
        add_action('admin_post_autoweco_reset_settings', array($this, 'autoweco_handle_reset_settings'));
        
        // Cron
        add_filter('cron_schedules', array($this, 'autoweco_add_custom_cron_interval'));
        add_action('autoweco_periodic_log_cleanup_event', array($this, 'autoweco_perform_log_cleanup'));
        add_action('update_option_autoweco_convert_images_to_webp_options', array($this, 'autoweco_manage_cron_schedule'), 10, 2);
        
        // Notices
        add_action('admin_notices', array($this, 'autoweco_display_admin_notices'));
        
        if ($this->autoweco_detect_optimizer_conflict()) {
            add_action('admin_notices', array($this, 'autoweco_conflict_notice'));
        }
        
        if ($this->autoweco_is_gd_missing()) {
            add_action('admin_notices', array($this, 'autoweco_gd_library_missing_notice'));
        }
    }

    private function autoweco_get_max_file_size() {
        return apply_filters('autoweco_max_file_size', self::MAX_FILE_SIZE);
    }

    public function autoweco_load_textdomain() {
        load_plugin_textdomain('auto-webp-converter-logger', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Cached getimagesize wrapper.
     */
    private function autoweco_get_image_info_cached($path) {
        $key = realpath($path) ?: $path;
        if (!isset(self::$image_info_cache[$key])) {
            self::$image_info_cache[$key] = @getimagesize($path);
        }
        return self::$image_info_cache[$key];
    }

    /**
     * Replaces extension with .webp safely (UTF-8 support).
     */
    private function autoweco_replace_extension_webp($filename) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png']) && function_exists('mb_substr') && function_exists('mb_strrpos')) {
            $pos = mb_strrpos($filename, '.');
            if ($pos !== false) {
                return mb_substr($filename, 0, $pos) . '.webp';
            }
        }
        return preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $filename);
    }

    // --- Activation/Deactivation ---
    public static function activate() { self::autoweco_static_manage_cron_schedule(); }
    public static function deactivate() { wp_clear_scheduled_hook('autoweco_periodic_log_cleanup_event'); }

    // =========================================================================
    // CRON & SCHEDULING
    // =========================================================================

    public function autoweco_manage_cron_schedule($old_value = null, $new_value = null) {
        if (!is_array($old_value) || !is_array($new_value) || !array_key_exists('log_retention', $old_value) || !array_key_exists('log_retention', $new_value)) {
            self::autoweco_run_schedule_logic($new_value);
            return;
        }
        if ($old_value['log_retention'] !== $new_value['log_retention']) {
            self::autoweco_run_schedule_logic($new_value);
        }
    }

    public static function autoweco_static_manage_cron_schedule() {
        $options = get_option('autoweco_convert_images_to_webp_options');
        self::autoweco_run_schedule_logic($options);
    }

    private static function autoweco_run_schedule_logic($options) {
        if (!is_array($options)) {
            $options = array('log_conversions' => false, 'log_retention' => 'never');
        }
        $retention_active = isset($options['log_retention']) && $options['log_retention'] !== 'never';
        
        wp_clear_scheduled_hook('autoweco_periodic_log_cleanup_event');
        if ($retention_active) {
            wp_schedule_event(time(), 'two_days', 'autoweco_periodic_log_cleanup_event');
        }
    }

    public function autoweco_add_custom_cron_interval($schedules) {
        $schedules['two_days'] = array(
            'interval' => 172800,
            'display'  => esc_html__('Every 2 Days', 'auto-webp-converter-logger'),
        );
        return $schedules;
    }

    // =========================================================================
    // HELPER FUNCTIONS & NOTICES
    // =========================================================================

    private function autoweco_is_gd_missing() { return !function_exists('imagewebp'); }

    private function autoweco_detect_optimizer_conflict() {
        return (defined('SHORTPIXEL_API_KEY') || class_exists('\ShortPixel') || class_exists('ShortPixel\ShortPixel') || class_exists('\Smush\Core') || class_exists('WpSmush\Core') || class_exists('EWWW_Image_Optimizer') || defined('WP_SMUSH_VERSION') || class_exists('Imagify'));
    }

    public function autoweco_conflict_notice() {
        if (current_user_can('manage_options')) {
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('⚠️ Auto WebP Converter: Another image optimization plugin is active (Smush, ShortPixel, etc). WebP conversion is temporarily disabled to prevent conflicts.', 'auto-webp-converter-logger') . '</p></div>';
        }
    }

    public function autoweco_gd_library_missing_notice() {
        if (current_user_can('manage_options')) {
            echo '<div class="notice notice-error"><p><strong>⚠️ Auto WebP Converter: GD library with WebP support is missing.</strong></p></div>';
        }
    }

    public function autoweco_trigger_error_notice($message) {
        if (!in_array($message, self::$request_errors, true)) {
            self::$request_errors[] = $message;
        }
        $errors = get_transient('autoweco_conversion_errors');
        if (!is_array($errors)) $errors = [];
        
        if (count($errors) >= 5) {
            if (!in_array('...and more errors (check logs)', $errors)) {
                $errors[] = __('...and more errors (check logs)', 'auto-webp-converter-logger');
            }
            set_transient('autoweco_conversion_errors', $errors, 45);
            return;
        }

        if (!in_array($message, $errors)) {
            $errors[] = $message;
            set_transient('autoweco_conversion_errors', $errors, 45); 
        }
    }

    public function autoweco_display_admin_notices() {
        if (get_transient('autoweco_settings_reset')) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('✓ Settings have been reset to defaults.', 'auto-webp-converter-logger') . '</p></div>';
            delete_transient('autoweco_settings_reset');
        }

        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
             echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'auto-webp-converter-logger') . '</p></div>';
        }

        if (!empty(self::$request_errors)) {
            foreach (self::$request_errors as $error) {
                echo '<div class="notice notice-error is-dismissible"><p><strong>Auto WebP Error:</strong> ' . esc_html($error) . '</p></div>';
            }
            delete_transient('autoweco_conversion_errors'); 
            return; 
        }

        $errors = get_transient('autoweco_conversion_errors');
        if (!empty($errors) && is_array($errors)) {
            foreach ($errors as $error) {
                if (in_array($error, self::$request_errors)) continue;
                echo '<div class="notice notice-error is-dismissible"><p><strong>Auto WebP Error:</strong> ' . esc_html($error) . '</p></div>';
            }
            delete_transient('autoweco_conversion_errors');
        }
    }

    // =========================================================================
    // SETTINGS PAGE UI
    // =========================================================================

    public function autoweco_add_settings_link($links) {
        $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=autoweco_convert-images-to-webp')) . '">' . esc_html__('Settings', 'auto-webp-converter-logger') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function autoweco_add_settings_page() {
        add_options_page(
            esc_html__('Auto WebP Converter', 'auto-webp-converter-logger'),
            esc_html__('Images to WebP', 'auto-webp-converter-logger'),
            'manage_options',
            'autoweco_convert-images-to-webp',
            array($this, 'autoweco_create_settings_page')
        );
    }

    public function autoweco_create_settings_page() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have sufficient permissions.', 'auto-webp-converter-logger')); }
        
        $logging_enabled = !empty($this->autoweco_options['log_conversions']);
        $retention_never = isset($this->autoweco_options['log_retention']) && $this->autoweco_options['log_retention'] === 'never';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Auto WebP Converter Settings', 'auto-webp-converter-logger'); ?></h1>
            
            <div class="notice notice-warning is-dismissible">
                <p><strong><?php esc_html_e('⚠️ IMPORTANT: Please disable/remove any other image optimizer plugins (e.g., Smush, Imagify, EWWW) to avoid conflicts.', 'auto-webp-converter-logger'); ?></strong></p>
            </div>

            <?php if ($logging_enabled && $retention_never): ?>
                <div class="notice notice-info is-dismissible">
                    <p><?php esc_html_e('ℹ️ Logging is enabled but auto-cleanup is disabled. Logs may grow indefinitely.', 'auto-webp-converter-logger'); ?></p>
                </div>
            <?php endif; ?>

            <p><?php esc_html_e('Note: Only images smaller than 15MB will be automatically converted to WebP.', 'auto-webp-converter-logger'); ?></p>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('autoweco_convert_images_to_webp_options_group');
                do_settings_sections('autoweco_convert-images-to-webp');
                submit_button();
                ?>
            </form>
            <?php $this->autoweco_reset_settings_callback(); ?>
            <hr>
            <h2><?php esc_html_e('Recent Conversion Logs', 'auto-webp-converter-logger'); ?></h2>
            <div style="background: #f0f0f1; padding: 15px; border: 1px solid #ccc; max-height: 300px; overflow-y: auto; font-family: monospace; white-space: pre-wrap;">
                <?php 
                if (!$logging_enabled) {
                    echo '<span style="color: #666;">' . esc_html__('ℹ️ Logging is currently disabled.', 'auto-webp-converter-logger') . '</span>';
                } elseif (file_exists($this->autoweco_log_file)) {
                    $file_size = filesize($this->autoweco_log_file);
                    if ($file_size > 5 * 1024 * 1024) {
                         echo '<span style="color: #666;">' . esc_html__('Log file is too large to display inline. Please use the "View Full Logs" button below.', 'auto-webp-converter-logger') . '</span>';
                    } else {
                        $lines = file($this->autoweco_log_file);
                        if ($lines) {
                            $recent_lines = array_slice($lines, -15);
                            $recent_lines = array_reverse($recent_lines);
                            foreach ($recent_lines as $line) echo esc_html($line);
                        } else {
                            echo '<span style="color: #0073aa;">ℹ️ ' . esc_html__('No logs found yet.', 'auto-webp-converter-logger') . '</span>';
                        }
                    }
                } else {
                    echo '<span style="color: #0073aa;">ℹ️ ' . esc_html__('No logs found yet.', 'auto-webp-converter-logger') . '</span>';
                }
                ?>
            </div>
            <?php if ($logging_enabled): ?>
            <p>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=autoweco_view_full_log'), 'autoweco_view_log_action', 'autoweco_nonce')); ?>" target="_blank" class="button button-secondary"><?php esc_html_e('View Full Logs', 'auto-webp-converter-logger'); ?></a>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function autoweco_reset_settings_callback() {
        ?>
        <p>
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=autoweco_reset_settings'), 'autoweco_reset_action', 'autoweco_nonce')); ?>" class="button button-secondary" style="color: #b32d2e; border-color: #b32d2e;" onclick="return confirm('<?php esc_attr_e('Are you sure? This will restore default settings.', 'auto-webp-converter-logger'); ?>')"><?php esc_html_e('Reset to Defaults', 'auto-webp-converter-logger'); ?></a>
        </p>
        <?php
    }

    public function autoweco_handle_reset_settings() {
        if (!current_user_can('manage_options')) { wp_die('Unauthorized access'); }
        check_admin_referer('autoweco_reset_action', 'autoweco_nonce');
        delete_option('autoweco_convert_images_to_webp_options');
        set_transient('autoweco_settings_reset', true, 30);
        wp_redirect(admin_url('options-general.php?page=autoweco_convert-images-to-webp'));
        exit;
    }

    public function autoweco_register_settings() {
        register_setting(
            'autoweco_convert_images_to_webp_options_group', 
            'autoweco_convert_images_to_webp_options', 
            array('sanitize_callback' => 'autoweco_sanitize_options')
        );

        add_settings_section('autoweco_settings_section', esc_html__('Configuration', 'auto-webp-converter-logger'), null, 'autoweco_convert-images-to-webp');

        add_settings_field('quality', esc_html__('WebP Quality (0-100)', 'auto-webp-converter-logger'), array($this, 'autoweco_quality_callback'), 'autoweco_convert-images-to-webp', 'autoweco_settings_section');
        add_settings_field('delete_original', esc_html__('Delete Original Image', 'auto-webp-converter-logger'), array($this, 'autoweco_delete_original_callback'), 'autoweco_convert-images-to-webp', 'autoweco_settings_section');
        add_settings_field('log_conversions', esc_html__('Enable Logging', 'auto-webp-converter-logger'), array($this, 'autoweco_log_conversions_callback'), 'autoweco_convert-images-to-webp', 'autoweco_settings_section');
        add_settings_field('log_retention', esc_html__('Auto-Delete Logs', 'auto-webp-converter-logger'), array($this, 'autoweco_log_retention_callback'), 'autoweco_convert-images-to-webp', 'autoweco_settings_section');
    }

    public function autoweco_quality_callback() {
        $options = get_option('autoweco_convert_images_to_webp_options');
        $quality = isset($options['quality']) ? (int) $options['quality'] : 85;
        printf('<input type="number" id="quality" name="autoweco_convert_images_to_webp_options[quality]" value="%s" min="0" max="100" class="small-text" /> <p class="description">Default: 85</p>', esc_attr($quality));
        echo '<p class="description">' . esc_html__('Note: Thumbnails generated by WordPress will also be converted to WebP if applicable.', 'auto-webp-converter-logger') . '</p>';
    }

    public function autoweco_delete_original_callback() {
        $options = get_option('autoweco_convert_images_to_webp_options');
        $checked = isset($options['delete_original']) ? $options['delete_original'] : true; 
        printf('<input type="checkbox" id="delete_original" name="autoweco_convert_images_to_webp_options[delete_original]" %s /> <label for="delete_original">Delete original JPG/PNG after successful conversion (Default: Yes)</label>', checked($checked, true, false));
    }

    public function autoweco_log_conversions_callback() {
        $options = get_option('autoweco_convert_images_to_webp_options');
        $checked = isset($options['log_conversions']) ? $options['log_conversions'] : false;
        printf(
            '<input type="checkbox" id="log_conversions" name="autoweco_convert_images_to_webp_options[log_conversions]" %s /> <label for="log_conversions">%s</label>',
            checked($checked, true, false),
            esc_html__('Enable detailed conversion logging (Default: No)', 'auto-webp-converter-logger')
        );
        echo '<p class="description">' . esc_html__('Enable if you want to monitor conversion results. Recommended only for debugging purposes.', 'auto-webp-converter-logger') . '</p>';
    }

    public function autoweco_log_retention_callback() {
        $options = get_option('autoweco_convert_images_to_webp_options');
        $retention = isset($options['log_retention']) ? $options['log_retention'] : 'never';
        ?>
        <select name="autoweco_convert_images_to_webp_options[log_retention]">
            <option value="never" <?php selected($retention, 'never'); ?>>Never (Default)</option>
            <option value="2" <?php selected($retention, '2'); ?>>After 2 Days</option>
            <option value="7" <?php selected($retention, '7'); ?>>After 7 Days</option>
            <option value="30" <?php selected($retention, '30'); ?>>After 30 Days</option>
            <option value="90" <?php selected($retention, '90'); ?>>After 90 Days</option>
        </select>
        <p class="description">Automatically remove log entries older than this time.</p>
        <?php
    }

    private function autoweco_log_conversion($message) {
        if (empty($this->autoweco_options['log_conversions'])) return;
        $timestamp = current_time('mysql');
        $log_entry = sprintf("[%s] %s\n", $timestamp, $message);
        if (!file_exists(dirname($this->autoweco_log_file))) return;
        if (wp_is_writable(dirname($this->autoweco_log_file))) {
            file_put_contents($this->autoweco_log_file, $log_entry, FILE_APPEND | LOCK_EX);
        }
    }

    public function autoweco_handle_view_full_log() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }
        
        check_admin_referer('autoweco_view_log_action', 'autoweco_nonce');

        $log_file = $this->autoweco_log_file;
        if (file_exists($log_file)) {
            // Memory Safety: Stream file to avoid RAM issues
            $file_size = filesize($log_file);
            if ($file_size > 5 * 1024 * 1024) { 
                header('Content-Type: text/plain');
                header('Content-Disposition: inline; filename="webp-conversion-log-partial.txt"');
                
                echo "--- LOG FILE TOO LARGE. SHOWING LAST 5000 LINES ---\n\n";
                $handle = fopen($log_file, 'r');
                $buffer = [];
                if ($handle) {
                    while (($line = fgets($handle)) !== false) {
                        $buffer[] = $line;
                        if (count($buffer) > 5000) array_shift($buffer);
                    }
                    fclose($handle);
                }
                echo implode("", $buffer);
            } else {
                header('Content-Type: text/plain');
                header('Content-Disposition: inline; filename="webp-conversion-log.txt"');
                readfile($log_file);
            }
            exit;
        } else {
            wp_die('Log file does not exist yet.');
        }
    }
    
    public function autoweco_perform_log_cleanup() {
        $options = get_option('autoweco_convert_images_to_webp_options');
        if (!is_array($options) || !isset($options['log_retention']) || $options['log_retention'] === 'never') return;
        if (!file_exists($this->autoweco_log_file)) return;
        
        // Memory Safe Cleanup via Streaming
        $file_size = filesize($this->autoweco_log_file);
        if ($file_size > 5 * 1024 * 1024) { 
            $handle = fopen($this->autoweco_log_file, 'r');
            $buffer = [];
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $buffer[] = $line;
                    if (count($buffer) > 5000) array_shift($buffer);
                }
                fclose($handle);
                file_put_contents($this->autoweco_log_file, implode('', $buffer), LOCK_EX);
            }
            return;
        }

        $retention_days = (int)$options['log_retention'];
        if ($retention_days <= 0) return;
        
        $handle = fopen($this->autoweco_log_file, 'r');
        $new_content = '';
        $cutoff_time = strtotime("-$retention_days days");
        $modified = false;

        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                if (preg_match('/^\[(.*?)\]/', $line, $matches)) {
                    $log_time = strtotime($matches[1]);
                    if ($log_time >= $cutoff_time) {
                        $new_content .= $line;
                    } else {
                        $modified = true;
                    }
                } else {
                     $new_content .= $line; 
                }
            }
            fclose($handle);
            
            if ($modified) {
                file_put_contents($this->autoweco_log_file, $new_content, LOCK_EX);
            }
        }
    }
    
    // =========================================================================
    // MEMORY PROTECTION & CONVERSION LOGIC
    // =========================================================================
    
    private function autoweco_check_memory_usage($image_path) {
        $size = $this->autoweco_get_image_info_cached($image_path);
        if (!$size) return true;

        $width = $size[0];
        $height = $size[1];
        $channels = isset($size['channels']) ? $size['channels'] : 3;
        $overhead = 1.7; 
        
        $mime = isset($size['mime']) ? $size['mime'] : '';
        if ($mime === 'image/png') {
            $channels = isset($size['channels']) ? $size['channels'] : 4;
            $overhead = 2.1; 
        }

        $bits = isset($size['bits']) ? $size['bits'] : 8;
        $needed_bytes = ($width * $height * $channels * ($bits / 8) * $overhead);
        
        $current_usage = memory_get_usage();
        $limit = ini_get('memory_limit');
        
        if ($limit == '-1') return true;

        if (function_exists('wp_convert_hr_to_bytes')) {
            $limit_bytes = wp_convert_hr_to_bytes($limit);
        } else {
            $limit_bytes = $this->autoweco_convert_hr_to_bytes($limit);
        }

        return (($current_usage + $needed_bytes) <= $limit_bytes);
    }

    private function autoweco_convert_hr_to_bytes($value) {
        $value = strtolower(trim($value));
        $bytes = (int) $value;
        if (false !== strpos($value, 'g')) $bytes *= 1073741824; 
        elseif (false !== strpos($value, 'm')) $bytes *= 1048576; 
        elseif (false !== strpos($value, 'k')) $bytes *= 1024;
        return min($bytes, PHP_INT_MAX);
    }

    public function autoweco_convert_image_to_webp($image_path, $new_image_path, $image_ext, $force_delete = false) {
        // Security: Directory Traversal Prevention
        $image_real = realpath($image_path);
        $base_real  = realpath($this->upload_dir['basedir']);

        if ($image_real === false || $base_real === false || strpos($image_real, $base_real) !== 0) {
            $this->autoweco_log_conversion('Security: Blocked attempted conversion of file outside uploads directory.');
            return false;
        }

        if ($this->autoweco_is_gd_missing()) {
            $msg = __('GD library missing or WebP not supported.', 'auto-webp-converter-logger');
            $this->autoweco_log_conversion($msg);
            $this->autoweco_trigger_error_notice($msg);
            return false;
        }

        if (!is_readable($image_path)) {
            $msg = sprintf(__('Image not readable: %s.', 'auto-webp-converter-logger'), $image_path);
            $this->autoweco_log_conversion($msg);
            return false;
        }

        if (!$this->autoweco_check_memory_usage($image_path)) {
            $msg = sprintf(__('Insufficient memory to convert uploaded file: %s. Please increase PHP Memory Limit.', 'auto-webp-converter-logger'), basename($image_path));
            $this->autoweco_log_conversion($msg);
            $this->autoweco_trigger_error_notice($msg); 
            return false;
        }

        $image = false;

        switch (strtolower($image_ext)) {
            case 'jpeg':
            case 'jpg':
                $image = @imagecreatefromjpeg($image_path);
                break;
            case 'png':
                $image = @imagecreatefrompng($image_path);
                if ($image) {
                    if (!function_exists('imagepalettetotruecolor')) {
                        @imagedestroy($image);
                        $this->autoweco_log_conversion('PNG conversion not supported on this server configuration.');
                        return false;
                    }
                    if (!imageistruecolor($image)) imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                }
                break;
            default:
                return false;
        }

        if (!$image) return false;

        $result = imagewebp($image, $new_image_path, $this->autoweco_options['quality']);
        @imagedestroy($image);

        if ($result && file_exists($new_image_path) && filesize($new_image_path) > 0) {
            $this->autoweco_log_conversion(sprintf(__('Successfully converted: %s.', 'auto-webp-converter-logger'), basename($image_path)));
            
            // Integrity Checks before Deletion
            if ($force_delete || !empty($this->autoweco_options['delete_original'])) {
                $orig_info = $this->autoweco_get_image_info_cached($image_path);
                $new_info = $this->autoweco_get_image_info_cached($new_image_path);

                if ($orig_info && $new_info) {
                    $filetype = wp_check_filetype_and_ext($new_image_path, basename($new_image_path));
                    if (empty($filetype['type']) || $filetype['type'] !== 'image/webp') {
                        $this->autoweco_log_conversion('Invalid WebP MIME detected — preserving original.');
                        return true;
                    }

                    if ($orig_info[0] !== $new_info[0] || $orig_info[1] !== $new_info[1]) {
                        $this->autoweco_log_conversion('Dimension mismatch - preserving original.');
                        return true;
                    }
                    if (filesize($new_image_path) > filesize($image_path) * 1.5) {
                        $this->autoweco_log_conversion('WebP unusually large - preserving original.');
                        return true;
                    }
                } else {
                    $this->autoweco_log_conversion('Conversion incomplete (metadata missing) — preserving original.');
                    return true;
                }

                wp_delete_file($image_path);
            }
            return true;
        } else {
            if (file_exists($new_image_path) && filesize($new_image_path) === 0) {
                @unlink($new_image_path);
            }
            $msg = sprintf(__('Conversion failed for: %s.', 'auto-webp-converter-logger'), basename($image_path));
            $this->autoweco_log_conversion($msg);
            $this->autoweco_trigger_error_notice($msg);
            return false;
        }
    }

    public function autoweco_convert_images_to_webp_on_upload($file) {
        if (apply_filters('autoweco_skip_conversion', false, $file)) return $file;
        if ($this->autoweco_detect_optimizer_conflict()) {
            $this->autoweco_log_conversion('Skipped conversion: Another optimizer plugin detected.');
            return $file;
        }
        if (defined('WP_CLI') && WP_CLI) return $file;

        $this->is_fresh_upload_request = true;

        if (!isset($file['file']) || !file_exists($file['file'])) return $file;

        $max_file_size = $this->autoweco_get_max_file_size();
        if (filesize($file['file']) > $max_file_size) {
            $msg = sprintf(__('File too large to convert: %s', 'auto-webp-converter-logger'), basename($file['file']));
            $this->autoweco_log_conversion($msg);
            $this->autoweco_trigger_error_notice($msg);
            return $file;
        }

        $allowed_mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
        $filetype = wp_check_filetype_and_ext($file['file'], basename($file['file']), $allowed_mimes);

        if (empty($filetype['ext']) || empty($filetype['type'])) return $file;

        $webp_filename = $this->autoweco_replace_extension_webp(basename($file['file']));
        $unique_webp_filename = wp_unique_filename($this->upload_dir['path'], $webp_filename);
        $webp_path = trailingslashit($this->upload_dir['path']) . $unique_webp_filename;

        if ($this->autoweco_convert_image_to_webp($file['file'], $webp_path, $filetype['ext'], false)) {
            $file['file'] = $webp_path;
            $file['url'] = trailingslashit($this->upload_dir['url']) . $unique_webp_filename;
            $file['type'] = 'image/webp';
        }
        return $file;
    }

    public function autoweco_mark_attachment_pending($post_id) {
        if ($this->is_fresh_upload_request) {
            update_post_meta($post_id, '_autoweco_pending_conversion', '1');
        }
    }

    public function autoweco_force_mime_sync($post_id) {
        $file_path = get_attached_file($post_id);
        if (!$file_path) return;

        if (preg_match('/\.webp$/i', $file_path)) {
            $post = get_post($post_id);
            if ($post && $post->post_mime_type !== 'image/webp') {
                wp_update_post(array('ID' => $post_id, 'post_mime_type' => 'image/webp'));
                if (!wp_attachment_is_image($post_id)) return;
                
                if (isset(self::$editor_support_cache[$file_path])) {
                    $is_supported = self::$editor_support_cache[$file_path];
                } else {
                    $editor = wp_get_image_editor($file_path);
                    $is_supported = !is_wp_error($editor);
                    self::$editor_support_cache[$file_path] = $is_supported;
                }

                if (!$is_supported) {
                    $this->autoweco_log_conversion("Metadata regen skipped: Editor cannot handle WebP file.");
                    return;
                }
                if (!function_exists('wp_generate_attachment_metadata')) require_once(ABSPATH . 'wp-admin/includes/image.php');
                $metadata = wp_generate_attachment_metadata($post_id, $file_path);
                if ($metadata && !is_wp_error($metadata)) wp_update_attachment_metadata($post_id, $metadata);
            }
            return;
        }

        if (!file_exists($file_path)) {
            $webp_path = $this->autoweco_replace_extension_webp($file_path);
            if (file_exists($webp_path)) {
                $filetype = wp_check_filetype_and_ext($webp_path, basename($webp_path));
                if (empty($filetype['type']) || $filetype['type'] !== 'image/webp') {
                    $this->autoweco_log_conversion('Orphan fix skipped: Invalid WebP MIME type detected.');
                    return;
                }
                
                if (filesize($webp_path) > $this->autoweco_get_max_file_size()) {
                    $this->autoweco_log_conversion("Orphan fix skipped: WebP file too large ($webp_path)");
                    return; 
                }
                
                $relative_path = str_replace(trailingslashit($this->upload_dir['basedir']), '', $webp_path);
                update_post_meta($post_id, '_wp_attached_file', $relative_path);
                wp_update_post(array('ID' => $post_id, 'post_mime_type' => 'image/webp'));
                
                if (!wp_attachment_is_image($post_id)) return;
                
                if (isset(self::$editor_support_cache[$webp_path])) {
                    $is_supported = self::$editor_support_cache[$webp_path];
                } else {
                    $editor = wp_get_image_editor($webp_path);
                    $is_supported = !is_wp_error($editor);
                    self::$editor_support_cache[$webp_path] = $is_supported;
                }

                if (!$is_supported) return;

                if (!function_exists('wp_generate_attachment_metadata')) require_once(ABSPATH . 'wp-admin/includes/image.php');
                $metadata = wp_generate_attachment_metadata($post_id, $webp_path);
                if ($metadata && !is_wp_error($metadata)) wp_update_attachment_metadata($post_id, $metadata);
            }
        }
    }

    public function autoweco_process_metadata_and_thumbnails($metadata, $attachment_id) {
        if (isset(self::$metadata_processing_lock[$attachment_id])) return $metadata;
        self::$metadata_processing_lock[$attachment_id] = true;

        try {
            $is_pending = get_post_meta($attachment_id, '_autoweco_pending_conversion', true);
            $inconsistent_state = false;
            if (!empty($metadata['file']) && !empty($metadata['sizes'])) {
                $main_is_webp = (strtolower(pathinfo($metadata['file'], PATHINFO_EXTENSION)) === 'webp');
                if ($main_is_webp) {
                    foreach ($metadata['sizes'] as $size_data) {
                        if (strtolower(pathinfo($size_data['file'], PATHINFO_EXTENSION)) !== 'webp') {
                            $inconsistent_state = true;
                            break;
                        }
                    }
                }
            }

            if (! $is_pending && ! $this->is_fresh_upload_request && ! $inconsistent_state) return $metadata;
            delete_post_meta($attachment_id, '_autoweco_pending_conversion');

            $mime_type = get_post_mime_type($attachment_id);
            if ($mime_type && strpos($mime_type, 'image/') !== 0) return $metadata;
            if (empty($metadata['sizes']) && empty($metadata['file'])) return $metadata;

            $dirname = str_replace('\\', '/', dirname($metadata['file']));
            $subdir = ($dirname === '.' || $dirname === '/' || $dirname === '') ? '' : $dirname;
            $base_dir = $subdir ? trailingslashit($this->upload_dir['basedir']) . $subdir : trailingslashit($this->upload_dir['basedir']);

            $main_file_name = basename($metadata['file']);
            $main_file_path = trailingslashit($base_dir) . $main_file_name;
            $main_ext = pathinfo($main_file_name, PATHINFO_EXTENSION);
            
            if (strtolower($main_ext) !== 'webp') {
                $expected_webp_name = $this->autoweco_replace_extension_webp($main_file_name);
                $expected_webp_path = trailingslashit($base_dir) . $expected_webp_name;

                if (!file_exists($main_file_path) && file_exists($expected_webp_path)) {
                    $new_meta_path = ($subdir ? trailingslashit($subdir) : '') . $expected_webp_name;
                    $metadata['file'] = $new_meta_path;
                    update_post_meta($attachment_id, '_wp_attached_file', $metadata['file']);
                    wp_update_post(array('ID' => $attachment_id, 'post_mime_type' => 'image/webp'));
                }
            }

            if (!empty($metadata['sizes'])) {
                $should_delete = !empty($this->autoweco_options['delete_original']);
                $unconverted_thumbs = [];

                foreach ($metadata['sizes'] as $size_name => $size_data) {
                    if (!isset($size_data['file'])) continue;
                    $file_name = $size_data['file'];
                    $file_path = trailingslashit($base_dir) . $file_name;
                    $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);

                    if (strtolower($file_ext) !== 'webp' && file_exists($file_path)) {
                        if (!in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png'])) continue;

                        if (filesize($file_path) > $this->autoweco_get_max_file_size()) {
                            $this->autoweco_log_conversion(sprintf(__('Thumbnail too large: %s (skipped)', 'auto-webp-converter-logger'), $file_name));
                            $unconverted_thumbs[] = $size_name;
                            continue; 
                        }

                        $webp_filename = $this->autoweco_replace_extension_webp($file_name);
                        $webp_path = trailingslashit($base_dir) . $webp_filename;
                        $converted = $this->autoweco_convert_image_to_webp($file_path, $webp_path, $file_ext, $should_delete);

                        if ($converted) {
                            $metadata['sizes'][$size_name]['file'] = $webp_filename;
                            $metadata['sizes'][$size_name]['mime-type'] = 'image/webp';
                        } else {
                            $unconverted_thumbs[] = $size_name;
                        }
                    }
                }
                if (!empty($unconverted_thumbs)) {
                    $this->autoweco_log_conversion(sprintf('Notice: Some thumbnails not converted for attachment ID %d: %s', $attachment_id, implode(', ', $unconverted_thumbs)));
                }
            }
            return $metadata;
        } finally {
            unset(self::$metadata_processing_lock[$attachment_id]);
        }
    }
    
    public function autoweco_cleanup_attachment_meta($post_id) {
        delete_post_meta($post_id, '_autoweco_pending_conversion');
        $file_path = get_attached_file($post_id);
        if ($file_path) {
            $key = realpath($file_path) ?: $file_path;
            if ($key && isset(self::$image_info_cache[$key])) unset(self::$image_info_cache[$key]);
        }
    }
}

new Autoweco_ConvertImagesToWebP();
register_activation_hook(__FILE__, array('Autoweco_ConvertImagesToWebP', 'activate'));
register_deactivation_hook(__FILE__, array('Autoweco_ConvertImagesToWebP', 'deactivate'));

function autoweco_sanitize_options($input) {
    $new_input = array();
    $new_input['quality'] = (isset($input['quality']) && is_numeric($input['quality'])) ? min(max((int) $input['quality'], 0), 100) : 85;
    $new_input['delete_original'] = !empty($input['delete_original']);
    $new_input['log_conversions'] = !empty($input['log_conversions']);
    $valid_retention = ['never', '2', '7', '30', '90'];
    $log_retention = isset($input['log_retention']) ? $input['log_retention'] : 'never';
    $new_input['log_retention'] = in_array($log_retention, $valid_retention, true) ? $log_retention : 'never';
    return $new_input;
}
// Made with ❤️ by Ujjawal Gupta - guptaujjawal12@gmail.com 
?>
