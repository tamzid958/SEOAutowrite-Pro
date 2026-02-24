<?php
/**
 * Plugin Name:       SEOAutowrite Pro
 * Plugin URI:        https://github.com/your-username/seoautowrite-pro
 * Description:       AI-powered WordPress article writer with SEO optimisation, scheduled publishing, backlink briefs, FAQ schema, and featured image generation via Ollama.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Tested up to:      6.7
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://yourwebsite.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       seoautowrite-pro
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASAW_VERSION', '1.0.0' );
define( 'ASAW_PLUGIN_FILE', __FILE__ );
define( 'ASAW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASAW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ASAW_TEXT_DOMAIN', 'seoautowrite-pro' );

// Load all classes.
require_once ASAW_PLUGIN_DIR . 'includes/class-asaw-logger.php';
require_once ASAW_PLUGIN_DIR . 'includes/class-asaw-utils.php';
require_once ASAW_PLUGIN_DIR . 'includes/class-asaw-provider-interface.php';
require_once ASAW_PLUGIN_DIR . 'includes/providers/class-asaw-image-provider-interface.php';
require_once ASAW_PLUGIN_DIR . 'includes/providers/class-asaw-none-image-provider.php';
require_once ASAW_PLUGIN_DIR . 'includes/providers/class-asaw-openai-image-provider.php';
require_once ASAW_PLUGIN_DIR . 'includes/providers/class-asaw-ollama-provider.php';
require_once ASAW_PLUGIN_DIR . 'includes/class-asaw-image.php';
require_once ASAW_PLUGIN_DIR . 'includes/class-asaw-generator.php';
require_once ASAW_PLUGIN_DIR . 'includes/class-asaw-cron.php';
require_once ASAW_PLUGIN_DIR . 'includes/class-asaw-settings.php';
require_once ASAW_PLUGIN_DIR . 'includes/class-asaw-plugin.php';

// Activation / deactivation hooks.
register_activation_hook( __FILE__, array( 'ASAW_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ASAW_Plugin', 'deactivate' ) );

// Boot the plugin on plugins_loaded so all WP functions are available.
add_action( 'plugins_loaded', array( 'ASAW_Plugin', 'get_instance' ) );
