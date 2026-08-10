<?php
/**
 * Ollama connection and compatibility diagnostics.
 *
 * @package Zactonz\AiProviderForOllama\Diagnostics
 */

declare( strict_types=1 );

namespace Zactonz\AiProviderForOllama\Diagnostics;

use Zactonz\AiProviderForOllama\Settings\OllamaSettings;
use WordPress\AiClient\AiClient;

/**
 * Produces redacted, side-effect-free diagnostics for administrators.
 *
 * @since 1.1.0
 */
class OllamaDiagnostics {

	/**
	 * Runs the diagnostics suite.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed> Diagnostic report.
	 */
	public function run(): array {
		$started       = microtime( true );
		$is_cloud      = OllamaSettings::is_cloud_connection();
		$host          = OllamaSettings::get_host();
		$models_path   = $is_cloud ? '/v1/models' : '/api/tags';
		$connection    = wp_remote_get(
			$host . $models_path,
			array(
				'headers'     => OllamaSettings::get_request_headers_for_connection( OllamaSettings::get_connection_type() ),
				'redirection' => 2,
				'timeout'     => min( 20.0, OllamaSettings::get_text_request_timeout() ),
			)
		);
		$latency_ms    = (int) round( ( microtime( true ) - $started ) * 1000 );
		$connected     = ! is_wp_error( $connection ) && wp_remote_retrieve_response_code( $connection ) >= 200 && wp_remote_retrieve_response_code( $connection ) < 300;
		$model_ids     = array();
		$response_code = is_wp_error( $connection ) ? 0 : (int) wp_remote_retrieve_response_code( $connection );

		if ( $connected ) {
			$body = json_decode( wp_remote_retrieve_body( $connection ), true );
			$list = $is_cloud && isset( $body['data'] ) && is_array( $body['data'] )
				? $body['data']
				: ( isset( $body['models'] ) && is_array( $body['models'] ) ? $body['models'] : array() );
			foreach ( $list as $model ) {
				$id = is_array( $model ) ? ( $model['id'] ?? $model['model'] ?? $model['name'] ?? '' ) : '';
				if ( is_string( $id ) && '' !== $id ) {
					$model_ids[] = $id;
				}
			}
		}

		$version = '';
		if ( $connected && ! $is_cloud ) {
			$version_response = wp_remote_get(
				$host . '/api/version',
				array(
					'headers' => OllamaSettings::get_request_headers_for_connection( OllamaSettings::get_connection_type() ),
					'timeout' => 10,
				)
			);
			if ( ! is_wp_error( $version_response ) && 200 === (int) wp_remote_retrieve_response_code( $version_response ) ) {
				$version_data = json_decode( wp_remote_retrieve_body( $version_response ), true );
				$version      = isset( $version_data['version'] ) && is_string( $version_data['version'] ) ? $version_data['version'] : '';
			}
		}

		$defaults         = OllamaSettings::get_preferred_models();
		$missing_defaults = array();
		foreach ( $defaults as $capability => $model_id ) {
			if ( '' !== $model_id && ! in_array( $model_id, $model_ids, true ) ) {
				$missing_defaults[ $capability ] = $model_id;
			}
		}

		$error_message = '';
		if ( is_wp_error( $connection ) ) {
			$error_message = $connection->get_error_message();
		} elseif ( ! $connected ) {
			$error_message = sprintf(
				/* translators: %d: HTTP response status. */
				__( 'Ollama returned HTTP %d.', 'zactonz-ai-provider-ollama' ),
				$response_code
			);
		}

		return array(
			'connected'       => $connected,
			'connectionType'  => $is_cloud ? 'cloud' : 'self_hosted',
			'endpoint'        => $host,
			'error'           => $error_message,
			'httpStatus'      => $response_code,
			'latencyMs'       => $latency_ms,
			'modelCount'      => count( $model_ids ),
			'missingDefaults' => $missing_defaults,
			'ollamaVersion'   => $version,
			'aiClientVersion' => class_exists( AiClient::class ) ? AiClient::VERSION : '',
			'embeddingsReady' => defined( 'WordPress\\AiClient\\Providers\\Models\\Enums\\CapabilityEnum::EMBEDDING_GENERATION' )
				&& interface_exists( 'WordPress\\AiClient\\Providers\\Models\\EmbeddingGeneration\\Contracts\\EmbeddingGenerationModelInterface' ),
			'streamingReady'  => function_exists( 'curl_init' ),
			'timestamp'       => gmdate( 'c' ),
		);
	}
}
