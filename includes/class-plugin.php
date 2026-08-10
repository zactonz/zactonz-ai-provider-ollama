<?php
/**
 * Plugin bootstrap initializer.
 *
 * @package Zactonz\AiProviderForOllama
 */

declare( strict_types=1 );

namespace Zactonz\AiProviderForOllama;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zactonz\AiProviderForOllama\Diagnostics\OllamaSiteHealth;
use Zactonz\AiProviderForOllama\Provider\OllamaProvider;
use Zactonz\AiProviderForOllama\Settings\OllamaSettings;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Plugin class.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Initializes the plugin.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_provider' ), 5 );
		add_action( 'init', array( $this, 'register_fallback_auth' ), 21 );
		add_action( 'init', array( $this, 'initialize_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ZCTZ_OLLAMA_AI_CONNECTOR_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
		add_filter( 'http_request_host_is_external', array( $this, 'allow_localhost_requests' ), 10, 3 );
		add_filter( 'http_allowed_safe_ports', array( $this, 'allow_ollama_ports' ) );

		( new OllamaSiteHealth() )->init();
	}

	/**
	 * Gets the Ollama host.
	 *
	 * @since 1.0.0
	 *
	 * @return string The Ollama host.
	 */
	private function get_ollama_host(): string {
		// Get the OLLAMA_HOST environment variable if set.
		$host = getenv( 'OLLAMA_HOST' );
		if ( false !== $host && '' !== $host ) {
			return $host;
		}

		return OllamaSettings::get_host();
	}

	/**
	 * Registers the Ollama provider with the AI Client.
	 *
	 * @since 1.0.0
	 */
	public function register_provider(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		if ( $registry->hasProvider( 'ollama' ) ) {
			return;
		}

		$registry->registerProvider( OllamaProvider::class );
	}

	/**
	 * Registers fallback authentication for the Ollama provider.
	 *
	 * Core wires Connector screen credentials at init priority 20. Run after that
	 * so the active Zactonz Cloud/self-hosted mode is the source of truth.
	 *
	 * @since 1.0.0
	 */
	public function register_fallback_auth(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( 'ollama' ) ) {
			return;
		}

		$api_key_override = OllamaSettings::get_api_key_override();
		$api_key          = '' !== $api_key_override ? $api_key_override : OllamaSettings::get_active_saved_api_key();

		$registry->setProviderRequestAuthentication(
			'ollama',
			new ApiKeyRequestAuthentication( $api_key )
		);
	}

	/**
	 * Initializes the Ollama settings.
	 *
	 * @since 1.0.0
	 */
	public function initialize_settings(): void {
		$settings = new OllamaSettings();
		$settings->init();
	}

	/**
	 * Adds action links to the plugin list table.
	 *
	 * This adds "Settings" link to the plugin's action links
	 * on the Plugins page.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string> $links Existing action links.
	 * @return array<string> Modified action links.
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			admin_url( 'options-general.php?page=zactonz-ai-provider-ollama' ),
			esc_html__( 'Settings', 'zactonz-ai-provider-ollama' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Allows localhost requests to the Ollama host.
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $external Whether the request is external.
	 * @param string $host The host of the request.
	 * @param string $url The URL of the request.
	 * @return bool Whether the request is allowed.
	 */
	public function allow_localhost_requests( $external, $host, $url ): bool {
		unset( $url );

		$ollama_host = wp_parse_url( $this->get_ollama_host(), PHP_URL_HOST );
		if ( is_string( $ollama_host ) && 0 === strcasecmp( $host, $ollama_host ) ) {
			return true;
		}

		return $external;
	}

	/**
	 * Allows Ollama ports.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int> $ports The ports.
	 * @return array<int> The allowed ports.
	 */
	public function allow_ollama_ports( $ports ): array {
		$ollama_host = $this->get_ollama_host();
		$ollama_port = wp_parse_url( $ollama_host, PHP_URL_PORT );

		if ( ! $ollama_port ) {
			return $ports;
		}

		return array_merge( $ports, array( $ollama_port ) );
	}
}
