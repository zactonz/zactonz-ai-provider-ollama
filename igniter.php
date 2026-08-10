<?php
/**
 * Plugin Name:       Zactonz AI Connector: Ollama
 * Plugin URI:        https://developers.zactonz.com/wp/plugins/zactonz-ai-provider-ollama/
 * Description:       Adds an Ollama connector to Settings > Connectors for the WordPress AI Client, local Ollama, self-hosted Ollama, and Ollama Cloud.
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Version:           1.1.0
 * Author:            Zactonz Technologies
 * Author URI:        https://zactonz.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain:       zactonz-ai-provider-ollama
 *
 * @package Zactonz\AiProviderForOllama
 */

declare( strict_types=1 );

namespace Zactonz\AiProviderForOllama;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZCTZ_OLLAMA_AI_CONNECTOR_MIN_PHP_VERSION', '7.4' );
define( 'ZCTZ_OLLAMA_AI_CONNECTOR_MIN_WP_VERSION', '7.0' );
define( 'ZCTZ_OLLAMA_AI_CONNECTOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZCTZ_OLLAMA_AI_CONNECTOR_PLUGIN_FILE', __FILE__ );

/**
 * Displays an admin notice for requirement failures.
 *
 * @since 1.0.0
 *
 * @param string $message The error message to display.
 */
function zctz_requirement_notice( string $message ): void {
	if ( ! is_admin() ) {
		return;
	}
	?>

	<div class="notice notice-error">
		<p><?php echo wp_kses_post( $message ); ?></p>
	</div>

	<?php
}

/**
 * Checks if the PHP version meets the minimum requirement.
 *
 * @since 1.0.0
 *
 * @return bool True if PHP version is sufficient, false otherwise.
 */
function zctz_check_php_version(): bool {
	if ( version_compare( phpversion(), ZCTZ_OLLAMA_AI_CONNECTOR_MIN_PHP_VERSION, '<' ) ) {
		add_action(
			'admin_notices',
			static function () {
				zctz_requirement_notice(
					sprintf(
						/* translators: 1: Required PHP version, 2: Current PHP version */
						__( 'The Zactonz AI Connector: Ollama requires PHP version %1$s or higher. You are running PHP version %2$s.', 'zactonz-ai-provider-ollama' ),
						ZCTZ_OLLAMA_AI_CONNECTOR_MIN_PHP_VERSION,
						PHP_VERSION
					)
				);
			}
		);

		return false;
	}

	return true;
}

/**
 * Checks if the WordPress version meets the minimum requirement.
 *
 * @since 1.0.0
 *
 * @global string $wp_version WordPress version.
 *
 * @return bool True if WordPress version is sufficient, false otherwise.
 */
function zctz_check_wp_version(): bool {
	if ( ! is_wp_version_compatible( ZCTZ_OLLAMA_AI_CONNECTOR_MIN_WP_VERSION ) ) {
		add_action(
			'admin_notices',
			static function () {
				global $wp_version;
				zctz_requirement_notice(
					sprintf(
						/* translators: 1: Required WordPress version, 2: Current WordPress version */
						__( 'The Zactonz AI Connector: Ollama requires WordPress version %1$s or higher. You are running WordPress version %2$s.', 'zactonz-ai-provider-ollama' ),
						ZCTZ_OLLAMA_AI_CONNECTOR_MIN_WP_VERSION,
						$wp_version
					)
				);
			}
		);

		return false;
	}

	return true;
}

/**
 * Loads plugin classes from the includes directory.
 *
 * @since 1.0.0
 *
 * @param string $class_name Fully qualified class name.
 */
function zctz_autoload( string $class_name ): void {
	$prefix = __NAMESPACE__ . '\\';
	if ( 0 !== strpos( $class_name, $prefix ) ) {
		return;
	}

	$relative_class = substr( $class_name, strlen( $prefix ) );
	$class_parts    = explode( '\\', $relative_class );
	$class_basename = array_pop( $class_parts );
	$directory      = implode( '/', $class_parts );
	$directory      = '' === $directory ? '' : $directory . '/';
	$file_basename  = strtolower( $class_basename ) . '.php';
	$base_dir       = ZCTZ_OLLAMA_AI_CONNECTOR_PLUGIN_DIR . 'includes/';
	$file_paths     = array(
		$base_dir . $directory . 'class-' . $file_basename,
		$base_dir . $directory . $file_basename,
		$base_dir . str_replace( '\\', '/', $relative_class ) . '.php',
	);

	foreach ( $file_paths as $file_path ) {
		if ( is_readable( $file_path ) ) {
			require_once $file_path;
			return;
		}
	}
}

/**
 * Loads the Ollama provider plugin.
 *
 * @since 1.0.0
 */
function zctz_load(): void {
	static $loaded = false;

	// Prevent loading twice.
	if ( $loaded ) {
		return;
	}

	// Check version requirements.
	if ( ! zctz_check_php_version() || ! zctz_check_wp_version() ) {
		return;
	}

	spl_autoload_register( __NAMESPACE__ . '\\zctz_autoload' );

	// Initialize the plugin.
	$plugin = new Plugin();
	$plugin->init();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\zctz_load' );
