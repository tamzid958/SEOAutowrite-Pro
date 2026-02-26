<?php
/**
 * Plugin Name:       SEOAutowrite Pro
 * Plugin URI:        https://seoautowrite.pro
 * Description:       AI-powered WordPress article writer with SEO optimisation, scheduled publishing, backlink briefs, FAQ schema, and featured image generation via Ollama.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Tested up to:      6.9
 * Requires PHP:      7.4
 * Author:            Tamzid Ahmed
 * Author URI:        https://github.com/tamzid958
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       seoautowrite-pro
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEOAPW_VERSION', '1.1.0' );
define( 'SEOAPW_PLUGIN_FILE', __FILE__ );
define( 'SEOAPW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEOAPW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SEOAPW_TEXT_DOMAIN', 'seoautowrite-pro' );

/**
 * Pro API server URL. Override in wp-config.php via:
 *   define( 'SEOAPW_CUSTOM_API_URL', 'https://your-server.com' );
 */
define( 'SEOAPW_API_URL', defined( 'SEOAPW_CUSTOM_API_URL' ) ? SEOAPW_CUSTOM_API_URL : 'https://seoautowrite.pro' );

// Load all classes.
require_once SEOAPW_PLUGIN_DIR . 'includes/class-seoapw-logger.php';
require_once SEOAPW_PLUGIN_DIR . 'includes/class-seoapw-utils.php';
require_once SEOAPW_PLUGIN_DIR . 'includes/class-seoapw-provider-interface.php';
require_once SEOAPW_PLUGIN_DIR . 'includes/providers/class-seoapw-image-provider-interface.php';
require_once SEOAPW_PLUGIN_DIR . 'includes/providers/class-seoapw-none-image-provider.php';
require_once SEOAPW_PLUGIN_DIR . 'includes/providers/class-seoapw-openai-image-provider.php';
require_once SEOAPW_PLUGIN_DIR . 'includes/providers/class-seoapw-ollama-provider.php';
require_once SEOAPW_PLUGIN_DIR . 'includes/class-seoapw-image.php';
require_once SEOAPW_PLUGIN_DIR . 'includes/class-seoapw-generator.php';
require_once SEOAPW_PLUGIN_DIR . 'includes/class-seoapw-cron.php';
require_once SEOAPW_PLUGIN_DIR . 'includes/class-seoapw-settings.php';
require_once SEOAPW_PLUGIN_DIR . 'includes/class-seoapw-plugin.php';

// Pro features — loaded after free-tier classes so they can extend/hook safely.
require_once SEOAPW_PLUGIN_DIR . 'includes/class-seoapw-license.php';
require_once SEOAPW_PLUGIN_DIR . 'includes/class-seoapw-remote.php';
require_once SEOAPW_PLUGIN_DIR . 'includes/class-seoapw-pro-ui.php';
require_once SEOAPW_PLUGIN_DIR . 'includes/class-seoapw-upsell.php';

// Activation / deactivation hooks.
register_activation_hook( __FILE__, array( 'SEOAPW_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SEOAPW_Plugin', 'deactivate' ) );

// Boot the plugin on plugins_loaded so all WP functions are available.
add_action( 'plugins_loaded', array( 'SEOAPW_Plugin', 'get_instance' ) );
