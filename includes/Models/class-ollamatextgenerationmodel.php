<?php
/**
 * Ollama text-generation model implementation.
 *
 * @package Zactonz\AiProviderForOllama\Models
 */

declare( strict_types=1 );

namespace Zactonz\AiProviderForOllama\Models;

use Zactonz\AiProviderForOllama\Models\Traits\OllamaRequestOptionsTrait;
use Zactonz\AiProviderForOllama\Provider\OllamaProvider;
use Zactonz\AiProviderForOllama\Settings\OllamaSettings;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * Class for an Ollama text generation model using the OpenAI-compatible chat completions API.
 *
 * TODO: Could look to use the native API instead of the OpenAI-compatible API.
 *
 * @since 1.0.0
 */
class OllamaTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {
	use OllamaRequestOptionsTrait;

	/**
	 * Prepares the response format parameter for Ollama's OpenAI-compatible API.
	 *
	 * Ollama's OpenAI-compatible API uses the same response_format key as OpenAI,
	 * but schema mode expects the schema to be nested at json_schema.schema.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed>|null $output_schema The output schema.
	 * @return array<string, mixed> The prepared response format parameter.
	 */
	protected function prepareResponseFormatParam( ?array $output_schema ): array {
		if ( is_array( $output_schema ) ) {
			return array(
				'type'        => 'json_schema',
				'json_schema' => array(
					'name'   => 'response_schema',
					'schema' => $output_schema,
				),
			);
		}

		return array(
			'type' => 'json_object',
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param HttpMethodEnum                          $method The HTTP method.
	 * @param string                                  $path The request path.
	 * @param array<string, string|array<int,string>> $headers Request headers.
	 * @param mixed                                   $data Request body data.
	 * @return Request The prepared request.
	 */
	protected function createRequest(
		HttpMethodEnum $method,
		string $path,
		array $headers = array(),
		$data = null
	): Request {
		$request_options = $this->prepareRequestOptionsForTextGeneration();

		// Keep transport-only timeout options out of the OpenAI-compatible payload.
		if ( is_array( $data ) ) {
			unset( $data['ollama.request_timeout'], $data['ollama.connect_timeout'] );
			$data = $this->applyPreferredModelThinkingMode( $data );
		}

		// Ollama supports OpenAI-compatible endpoints at /v1/.
		$path = ltrim( (string) preg_replace( '#^v1/?#', '', ltrim( $path, '/' ) ), '/' );
		$path = '/v1/' . $path;

		return new Request(
			$method,
			OllamaProvider::url( $path ),
			$headers,
			$data,
			$request_options
		);
	}

	/**
	 * Prepares request options for text generation with a longer default timeout.
	 *
	 * Supported custom options:
	 *  - ollama.request_timeout (seconds)
	 *  - ollama.connect_timeout (seconds)
	 *
	 * @since 1.1.0
	 *
	 * @return \WordPress\AiClient\Providers\Http\DTO\RequestOptions Prepared request options.
	 */
	private function prepareRequestOptionsForTextGeneration(): RequestOptions {
		return $this->prepareRequestOptions( OllamaSettings::get_text_request_timeout(), 10.0 );
	}

	/**
	 * Applies the preferred model thinking mode to Ollama's OpenAI-compatible API.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data OpenAI-compatible request payload.
	 * @return array<string, mixed> Request payload with the admin thinking choice.
	 */
	private function applyPreferredModelThinkingMode( array $data ): array {
		if ( isset( $data['reasoning_effort'] ) || isset( $data['reasoning'] ) ) {
			return $data;
		}

		$model_id      = isset( $data['model'] ) && is_string( $data['model'] ) ? $data['model'] : '';
		$thinking_mode = OllamaSettings::get_thinking_mode_for_model( $model_id );

		if ( 'disabled' === $thinking_mode ) {
			$data['reasoning_effort'] = 'none';
		} elseif ( 'enabled' === $thinking_mode ) {
			$data['reasoning_effort'] = 'medium';
		}

		return $data;
	}
}
