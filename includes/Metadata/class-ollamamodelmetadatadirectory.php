<?php
/**
 * Ollama model metadata discovery.
 *
 * @package Zactonz\AiProviderForOllama\Metadata
 */

declare( strict_types=1 );

namespace Zactonz\AiProviderForOllama\Metadata;

use Zactonz\AiProviderForOllama\Provider\OllamaProvider;
use Zactonz\AiProviderForOllama\Settings\OllamaSettings;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModelMetadataDirectory;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * Class for the Ollama model metadata directory.
 *
 * @since 1.0.0
 *
 * @phpstan-type TagsResponseData array{
 *     models: list<array{name: string, details?: array{families?: list<string>}}>
 * }
 * @phpstan-type ModelsResponseData array{
 *     data: list<array{id?: string, name?: string}>
 * }
 * @phpstan-type ShowResponseData array{
 *     capabilities?: list<string>,
 *     details?: array{families?: list<string>}
 * }
 */
class OllamaModelMetadataDirectory extends AbstractApiBasedModelMetadataDirectory {

	/**
	 * Returns Ollama-native capabilities for a model.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_name The model name.
	 * @return list<string> Ollama capabilities returned by /api/show.
	 */
	public function getModelCapabilities( string $model_name ): array {
		$details      = $this->fetchModelDetails( $model_name );
		$capabilities = null !== $details && isset( $details['capabilities'] ) && is_array( $details['capabilities'] )
			? $details['capabilities']
			: array();

		return array_values(
			array_filter(
				$capabilities,
				static function ( $capability ): bool {
					return is_string( $capability ) && '' !== $capability;
				}
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, ModelMetadata> The discovered model metadata.
	 * @throws ResponseException When the Ollama response is missing required data.
	 */
	protected function sendListModelsRequest(): array {
		if ( OllamaSettings::is_cloud_connection() ) {
			return $this->sendCloudListModelsRequest();
		}

		$request  = $this->createRequest( HttpMethodEnum::GET(), 'api/tags' );
		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );

		ResponseUtil::throwIfNotSuccessful( $response );

		// phpcs:ignore Generic.Commenting.DocComment.MissingShort
		/** @var TagsResponseData $tags_data */
		$tags_data = $response->getData();
		if ( ! isset( $tags_data['models'] ) ) {
			throw ResponseException::fromMissingData( 'Ollama', 'models' );
		}

		$models_map = array();
		foreach ( $tags_data['models'] as $model_entry ) {
			$model_name = $model_entry['name'];
			$metadata   = $this->buildModelMetadata( $model_name, $this->fetchModelDetails( $model_name ) );
			if ( null === $metadata ) {
				continue;
			}

			$models_map[ $model_name ] = $metadata;
		}

		return $this->sortModelsMap( $models_map );
	}

	/**
	 * Lists Ollama Cloud models from the OpenAI-compatible models endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, ModelMetadata> The discovered model metadata.
	 * @throws ResponseException When the Ollama Cloud response is missing required data.
	 */
	private function sendCloudListModelsRequest(): array {
		$request  = $this->createRequest( HttpMethodEnum::GET(), 'v1/models' );
		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );

		ResponseUtil::throwIfNotSuccessful( $response );

		// phpcs:ignore Generic.Commenting.DocComment.MissingShort
		/** @var ModelsResponseData $models_data */
		$models_data = $response->getData();
		if ( ! isset( $models_data['data'] ) ) {
			throw ResponseException::fromMissingData( 'Ollama Cloud', 'data' );
		}

		$models_map = array();
		foreach ( $models_data['data'] as $model_entry ) {
			$model_name = isset( $model_entry['id'] ) ? $model_entry['id'] : ( $model_entry['name'] ?? '' );
			if ( ! is_string( $model_name ) || '' === $model_name ) {
				continue;
			}

			$metadata = $this->buildModelMetadata( $model_name, null );
			if ( null === $metadata ) {
				continue;
			}

			$models_map[ $model_name ] = $metadata;
		}

		return $this->sortModelsMap( $models_map );
	}

	/**
	 * Sorts model metadata and places the preferred model first.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, ModelMetadata> $models_map Models keyed by model ID.
	 * @return array<string, ModelMetadata> Sorted models.
	 */
	private function sortModelsMap( array $models_map ): array {
		ksort( $models_map );
		$settings        = OllamaSettings::get_settings();
		$preferred_model = isset( $settings['model'] ) ? $settings['model'] : '';
		if ( '' !== $preferred_model && isset( $models_map[ $preferred_model ] ) ) {
			$model      = $models_map[ $preferred_model ];
			$models_map = array( $preferred_model => $model ) + array_diff_key(
				$models_map,
				array( $preferred_model => true )
			);
		}

		return $models_map;
	}

	/**
	 * Builds a ModelMetadata object for a single model, or returns null if the model should be skipped.
	 *
	 * Skips embedding-only models (those whose capabilities array is non-empty and lacks 'completion').
	 * Falls back to text-only generation when details are unavailable.
	 *
	 * @since 1.0.0
	 *
	 * @param string                $model_name The model name.
	 * @param ShowResponseData|null $details The response data from /api/show, or null on failure.
	 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata|null The model metadata, or null if the model should be excluded.
	 */
	private function buildModelMetadata( string $model_name, ?array $details ): ?ModelMetadata {
		// Fallback when /api/show fails: assume text-only generation.
		$has_vision                = false;
		$is_image_generation_model = $this->isImageGenerationModel( $model_name, $details );

		if ( null !== $details ) {
			$model_capabilities = isset( $details['capabilities'] ) ? $details['capabilities'] : array();

			// Skip embedding-only models, but keep image-generation models which may not report "completion".
			if ( ! empty( $model_capabilities ) && ! in_array( 'completion', $model_capabilities, true ) && ! $is_image_generation_model ) {
				return null;
			}

			// Check for vision support via capabilities array or details.families.
			$has_vision = in_array( 'vision', $model_capabilities, true );
			if ( ! $has_vision && isset( $details['details']['families'] ) ) {
				$has_vision = in_array( 'clip', $details['details']['families'], true );
			}
		}

		if ( $has_vision ) {
			$input_modalities_option = new SupportedOption(
				OptionEnum::inputModalities(),
				array(
					array( ModalityEnum::text() ),
					array( ModalityEnum::text(), ModalityEnum::image() ),
				)
			);
		} else {
			$input_modalities_option = new SupportedOption(
				OptionEnum::inputModalities(),
				array( array( ModalityEnum::text() ) )
			);
		}

		if ( $is_image_generation_model ) {
			return new ModelMetadata(
				$model_name,
				$model_name,
				array(
					CapabilityEnum::imageGeneration(),
				),
				array(
					new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
					new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::image() ) ) ),
					new SupportedOption( OptionEnum::candidateCount() ),
					new SupportedOption( OptionEnum::outputMimeType(), array( 'image/png' ) ),
					new SupportedOption( OptionEnum::outputFileType(), array( FileTypeEnum::inline() ) ),
					new SupportedOption( OptionEnum::customOptions() ),
				)
			);
		}

		$options = array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::candidateCount() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::topK() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::frequencyPenalty() ),
			new SupportedOption( OptionEnum::presencePenalty() ),
			new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) ),
			new SupportedOption( OptionEnum::outputSchema() ),
			new SupportedOption( OptionEnum::functionDeclarations() ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
			$input_modalities_option,
		);

		return new ModelMetadata(
			$model_name,
			$model_name,
			array(
				CapabilityEnum::textGeneration(),
				CapabilityEnum::chatHistory(),
			),
			$options
		);
	}

	/**
	 * Determines whether a model is likely an image-generation model.
	 *
	 * @since 1.1.0
	 *
	 * @param string                $model_name The model name.
	 * @param ShowResponseData|null $details The optional model details.
	 * @return bool True if the model appears to support image generation.
	 */
	private function isImageGenerationModel( string $model_name, ?array $details ): bool {

		if ( null === $details || '' === $model_name ) {
			return false;
		}

		$model_capabilities = isset( $details['capabilities'] ) && is_array( $details['capabilities'] )
			? $details['capabilities']
			: array();
		return in_array( 'image', $model_capabilities, true );
	}

	/**
	 * Fetches model details from the Ollama /api/show endpoint.
	 *
	 * Returns null if the request fails, in which case the caller falls back
	 * to default text-generation capabilities for the model.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_name The model name.
	 * @return ShowResponseData|null The response data, or null on failure.
	 */
	private function fetchModelDetails( string $model_name ): ?array {
		try {
			$request  = $this->createRequest(
				HttpMethodEnum::POST(),
				'api/show',
				array( 'Content-Type' => 'application/json' ),
				array( 'name' => $model_name )
			);
			$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
			$response = $this->getHttpTransporter()->send( $request );

			ResponseUtil::throwIfNotSuccessful( $response );

			// phpcs:ignore Generic.Commenting.DocComment.MissingShort
			/** @var ShowResponseData $data */
			$data = $response->getData();
			return $data;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Creates a request object for the Ollama API.
	 *
	 * @since 1.0.0
	 *
	 * @param \WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum $method  The HTTP method.
	 * @param string                                                  $path    The API endpoint path, relative to the base URI.
	 * @param array<string, string|list<string>>                      $headers The request headers.
	 * @param string|array<string, mixed>|null                        $data    The request data.
	 * @return \WordPress\AiClient\Providers\Http\DTO\Request The request object.
	 */
	private function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		return new Request(
			$method,
			OllamaProvider::url( $path ),
			$headers,
			$data
		);
	}
}
