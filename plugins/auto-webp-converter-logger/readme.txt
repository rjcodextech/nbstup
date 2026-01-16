=== Auto WebP Converter & Logger ===
Contributors: babapinnak
Tags: webp, images, optimization, performance, compression
Requires at least: 5.2
Tested up to: 6.9
Stable tag: 2.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Boost site speed by automatically converting uploads to WebP. Features smart memory protection, detailed logging, and zero API dependencies.

== Description ==

**Auto WebP Converter & Logger** is the smartest, safest way to serve next-gen images on WordPress.

Most optimization plugins slow down your site with external API calls or crash your server by processing huge files. **Auto WebP Converter is different.** It runs entirely on your server using the native PHP GD library, featuring a unique **Smart Memory Guard** that calculates available RAM before processing to prevent crashes.

### 🚀 Why Auto WebP Converter?

* **Boost Core Web Vitals:** Serving WebP images significantly reduces page load size.
* **Zero API Fees:** No subscriptions, no credits, no data sharing. It's 100% free.
* **Server Safety First:** Detects low memory and conflicting plugins (like Smush/EWWW) to prevent errors.
* **Set & Forget:** Auto-converts JPGs and PNGs immediately upon upload.

### ⚡ Feature Highlights

* **Smart Conversion:** Converts the main image AND all thumbnails (Media Library, WooCommerce, etc.).
* **Self-Cleaning Logs:** Detailed conversion history with auto-delete (Cron) to keep your database clean.
* **MIME Sync:** Automatically updates WordPress metadata so browsers recognize the `.webp` format.
* **Privacy Focused:** Your images never leave your server.

[youtube https://www.youtube.com/watch?v=CyhMgtOXuio]

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/auto-webp-converter-logger`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Settings > Images to WebP**.
4. Save your preferences. (Default: Quality 85, Logging Disabled).

*Note: If upgrading from version 1.4, please check your settings as they have been reset to defaults for the new security engine.*

== Frequently Asked Questions ==

= Does this convert my existing media library? =
No. The plugin processes **new uploads only**. We focus on optimizing your future workflow without overwhelming your server with bulk operations.

= What image formats does this plugin convert? =
It currently supports **JPG, JPEG, and PNG** images.

= Can I delete the original images after conversion? =
Yes. In the settings, you can check "Delete Original Image". If checked, the original JPG/PNG is removed to save disk space. If unchecked, both the original and the WebP version are kept.

= Will this work if I don't have an API key? =
Yes! This plugin does not use APIs. It uses your server's built-in image processor (GD Library).

= What happens if the uploaded file is too large? =
For server stability, images larger than **15MB** are automatically skipped. The event is logged if you have logging enabled.

= Where are the conversion logs saved? =
Logs are stored in a secure folder: `/wp-content/uploads/auto-webp-converter-logger/`. This folder is protected via `.htaccess` to prevent public access.

= Will this work with third-party upload plugins? =
Yes. The plugin hooks into the core WordPress upload process, so it generally works with plugins like Instant Images or frontend uploader forms, ensuring the correct MIME type is saved.

= What happens if I uninstall the plugin? =
Your images stay safe! The plugin generates standard WebP files. If you uninstall it, your existing WebP images remain on your site and continue to work.

= Does this work on WordPress Multisite? =
Yes. It is fully compatible with Multisite networks. Each site in the network gets its own isolated log folder.

= Why are my images not converting? =
1. **Check GD:** Ensure your host has `php-gd` enabled.
2. **Check Logs:** Enable logging in settings. If an image requires more RAM than your server allows, the plugin skips it to prevent a crash.
3. **Check Conflicts:** If you have Smush or EWWW active, the plugin pauses to avoid double-compression issues.

= Can I rename the plugin folder? =
No. The folder must remain `auto-webp-converter-logger` to ensure you receive future updates from the WordPress repository.

== Screenshots ==

1. **Dashboard:** Simple, clean settings for Quality and Log Retention.
2. **Smart Logging:** Real-time visibility into conversion success and memory usage.

== Changelog ==

= 2.0 =
* **Major Update:** Complete engine rewrite for performance and stability.
* **New:** Smart Memory Protection (prevents "White Screen of Death").
* **New:** Cron-based Log Retention (auto-deletes old logs).
* **New:** Conflict Detection for 3rd party plugins.
* **Security:** Hardened log directory with `.htaccess` and traversal checks.
* **Fix:** Resolved thumbnail conversion inconsistencies.

= 1.4 =
* Fixed MIME type handling for external uploaders.
* Improved metadata synchronization.

= 1.3 =
* Updated log file paths for repository compliance.
* Minor code cleanup.

= 1.2 =
* Added GD library admin notices.
* Improved permission checks.

= 1.0 =
* Initial release.

== Upgrade Notice ==

= 2.0 =
Major update (v2.0). Adds critical memory protection and security fixes. Please check your settings after updating as they have been reset.