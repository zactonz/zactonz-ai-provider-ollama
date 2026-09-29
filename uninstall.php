<?php
/**
 * Removes the connector's stored options when the plugin is deleted.
 *
 * Deleting a plugin should take its credentials with it rather than leave a
 * usable key in the database. Ollama keeps one key per connection type, so both
 * are removed along with the settings and the option name the plugin used before
 * 1.1.0.
 *
 * @package Zactonz\AiProviderForOllama
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$zctz_ollama_options = array(
	'zctz_ollama_ai_connector_settings',
	'zactonz_ollama_ai_connector_settings',
	'zctz_ollama_ai_connector_cloud_api_key',
	'zctz_ollama_ai_connector_self_hosted_api_key',
	'connectors_ai_ollama_api_key',
);

foreach ( $zctz_ollama_options as $zctz_ollama_option ) {
	delete_option( $zctz_ollama_option );

	if ( is_multisite() ) {
		delete_site_option( $zctz_ollama_option );
	}
}
