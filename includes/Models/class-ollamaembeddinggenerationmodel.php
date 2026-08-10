<?php
/**
 * Ollama embedding-generation model implementation.
 *
 * @package Zactonz\AiProviderForOllama\Models
 */

declare( strict_types=1 );

namespace Zactonz\AiProviderForOllama\Models;

use Zactonz\AiProviderForOllama\Models\Traits\OllamaRequestOptionsTrait;
use Zactonz\AiProviderForOllama\Provider\OllamaProvider;
use Zactonz\AiProviderForOllama\Settings\OllamaSettings;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\EmbeddingGeneration\Contracts\EmbeddingGenerationModelInterface;
use WordPress\AiClient\Results\DTO\EmbeddingResult;
use WordPress\AiClient\Results\DTO\TokenUsage;

/**
 * Generates single or batched embeddings with Ollama's native API.
 *
 * This class is only autoloaded when the embedding interfaces introduced in
 * PHP AI Client 1.4 are available. That keeps the plugin load-safe on
 * WordPress 7.0 while enabling embeddings on WordPress 7.1 and newer.
 *
 * @since 1.1.0
 */
class OllamaEmbeddingGenerationModel extends AbstractApiBasedModel implements EmbeddingGenerationModelInterface {
	use OllamaRequestOptionsTrait;

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 * @param array<int, MessagePart> $inputs Text inputs to embed.
	 * @throws InvalidArgumentException When inputs or vectors are invalid.
	 * @throws ResponseException When Ollama returns invalid response data.
	 */
	public function generateEmbeddingResult( array $inputs ): EmbeddingResult {
		$input_texts = array_map( array( $this, 'extract_input_text' ), $inputs );
		if ( empty( $input_texts ) ) {
			throw new InvalidArgumentException( 'At least one text input is required for embedding generation.' );
		}

		$data = array(
			'model'    => $this->metadata()->getId(),
			'input'    => $input_texts,
			'truncate' => true,
		);

		$dimensions = $this->getConfig()->getDimensions();
		if ( null !== $dimensions ) {
			$data['dimensions'] = $dimensions;
		}

		$custom_options = $this->getConfig()->getCustomOptions();
		if ( isset( $custom_options['ollama.truncate'] ) ) {
			$data['truncate'] = (bool) $custom_options['ollama.truncate'];
		}
		if ( isset( $custom_options['ollama.options'] ) && is_array( $custom_options['ollama.options'] ) ) {
			$data['options'] = $custom_options['ollama.options'];
		}

		$request  = new Request(
			HttpMethodEnum::POST(),
			OllamaProvider::url( 'api/embed' ),
			array( 'Content-Type' => 'application/json' ),
			$data,
			$this->prepareRequestOptions( OllamaSettings::get_embedding_request_timeout(), 10.0 )
		);
		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );

		$response_data = $response->getData();
		if ( ! is_array( $response_data ) || ! isset( $response_data['embeddings'] ) || ! is_array( $response_data['embeddings'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Provider exception data is not output directly.
			throw ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'embeddings' );
		}

		$embeddings = array_values( $response_data['embeddings'] );
		if ( count( $embeddings ) !== count( $input_texts ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Provider exception data is not output directly.
			throw ResponseException::fromInvalidData(
				$this->providerMetadata()->getName(),
				'embeddings',
				'Expected one embedding vector for each input.'
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$vector_dimensions = 0;
		foreach ( $embeddings as $index => $embedding ) {
			if ( ! is_array( $embedding ) || empty( $embedding ) || ! array_is_list( $embedding ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Provider exception data is not output directly.
				throw ResponseException::fromInvalidData(
					$this->providerMetadata()->getName(),
					'embeddings[' . $index . ']',
					'The value must be a non-empty list of numbers.'
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$embedding            = array_map(
				static function ( $value ): float {
					if ( ! is_int( $value ) && ! is_float( $value ) ) {
						throw new InvalidArgumentException( 'Embedding vector values must be numeric.' );
					}
					return (float) $value;
				},
				$embedding
			);
			$embeddings[ $index ] = $embedding;

			if ( 0 === $vector_dimensions ) {
				$vector_dimensions = count( $embedding );
			} elseif ( count( $embedding ) !== $vector_dimensions ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Provider exception data is not output directly.
				throw ResponseException::fromInvalidData(
					$this->providerMetadata()->getName(),
					'embeddings[' . $index . ']',
					'All embedding vectors must have the same dimensions.'
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}

		$prompt_tokens   = isset( $response_data['prompt_eval_count'] ) && is_numeric( $response_data['prompt_eval_count'] )
			? (int) $response_data['prompt_eval_count']
			: 0;
		$additional_data = $response_data;
		unset( $additional_data['embeddings'] );

		$id = isset( $response_data['created_at'] ) && is_string( $response_data['created_at'] )
			? $response_data['created_at']
			: '';

		return new EmbeddingResult(
			$id,
			$embeddings,
			$vector_dimensions,
			new TokenUsage( $prompt_tokens, 0, $prompt_tokens ),
			$this->providerMetadata(),
			$this->metadata(),
			$additional_data
		);
	}

	/**
	 * Extracts a text embedding input.
	 *
	 * @since 1.1.0
	 *
	 * @param MessagePart $input Input message part.
	 * @return string Non-empty input text.
	 * @throws InvalidArgumentException When the input is not non-empty text.
	 */
	private function extract_input_text( MessagePart $input ): string {
		$text = $input->getText();
		if ( null === $text || '' === trim( $text ) ) {
			throw new InvalidArgumentException( 'Ollama embeddings currently support non-empty text inputs only.' );
		}

		return $text;
	}
}
