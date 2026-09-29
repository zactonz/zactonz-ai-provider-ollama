<?php
/**
 * Ollama provider availability check.
 *
 * @package Zactonz\AiProviderForOllama\Provider
 */

declare( strict_types=1 );

namespace Zactonz\AiProviderForOllama\Provider;

use Zactonz\AiProviderForOllama\Settings\OllamaSettings;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks whether the active Zactonz Ollama connection is reachable.
 *
 * @since 1.0.0
 */
class OllamaProviderAvailability implements ProviderAvailabilityInterface {

	private const CACHE_KEY = 'zctz_ollama_reachable';
	private const CACHE_TTL = 300;

	/**
	 * {@inheritDoc}
	 *
	 * The AI Client asks this on every model lookup, so the answer is cached for a
	 * few minutes and remembered for the current request. Reaching the endpoint each
	 * time cost a round trip per lookup, and up to the full request timeout whenever
	 * Ollama was not running.
	 *
	 * @since 1.2.0 The reachability check is cached.
	 * @since 1.0.0
	 */
	public function isConfigured(): bool {
		static $checked = null;

		if ( null !== $checked ) {
			return $checked;
		}

		$cached = get_transient( self::cache_key() );

		if ( false !== $cached ) {
			$checked = ( 'yes' === $cached );

			return $checked;
		}

		$checked = ! is_wp_error( OllamaSettings::verify_active_connection() );

		set_transient( self::cache_key(), $checked ? 'yes' : 'no', self::CACHE_TTL );

		return $checked;
	}

	/**
	 * Returns the transient name for the current connection settings.
	 *
	 * Changing the mode, host, port or credential asks a different question, so each
	 * combination is remembered separately and a settings change is never answered
	 * from the previous endpoint's result.
	 *
	 * @since 1.2.0
	 *
	 * @return string Transient name.
	 */
	private static function cache_key(): string {
		return self::CACHE_KEY . '_' . md5(
			OllamaSettings::get_connection_type() . '|' . OllamaSettings::get_host() . '|' . OllamaSettings::get_active_saved_api_key()
		);
	}

	/**
	 * Forgets the cached reachability answer.
	 *
	 * @since 1.2.0
	 */
	public static function flush(): void {
		delete_transient( self::cache_key() );
	}
}
