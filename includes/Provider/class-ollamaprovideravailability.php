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

/**
 * Checks whether the active Zactonz Ollama connection is reachable.
 *
 * @since 1.0.0
 */
class OllamaProviderAvailability implements ProviderAvailabilityInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function isConfigured(): bool {
		return ! is_wp_error( OllamaSettings::verify_active_connection() );
	}
}
