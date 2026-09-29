<?php
/**
 * Internal signal used to stop a stream.
 *
 * @package Zactonz\AiProviderForOllama
 */

declare( strict_types=1 );

namespace Zactonz\AiProviderForOllama\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use RuntimeException;

/**
 * Unwinds a stream when the caller's callback returns false.
 *
 * WordPress catches only Requests exceptions around its transports, so this
 * travels out of wp_remote_post() and is caught by the model that threw it. It
 * never escapes the plugin.
 *
 * It stops the plugin processing the rest of the body; it does not abort the
 * transfer, which no HTTP API hook can do.
 *
 * @since 1.2.0
 */
class OllamaStreamCancellation extends RuntimeException {
}
