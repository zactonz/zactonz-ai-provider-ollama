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
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
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
 *     details?: array{family?: string, families?: list<string>, parameter_size?: string, quantization_level?: string, format?: string},
 *     model_info?: array<string, mixed>
 * }
 */
class OllamaModelMetadataDirectory extends AbstractApiBasedModelMetadataDirectory {

	/**
	 * Request-local cache of /api/show responses.
	 *
	 * @since 1.1.0
	 * @var array<string, array<string, mixed>|null>
	 */
	private $model_details_cache = array();

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
	 * Returns an administration-friendly model descriptor.
	 *
	 * Unlike ModelMetadata, this includes Ollama-native feature flags and model
	 * details that are useful for diagnostics and settings UI presentation.
	 *
	 * @since 1.1.0
	 *
	 * @param string $model_name Model identifier.
	 * @return array<string, mixed> Model descriptor.
	 */
	public function getModelDescriptor( string $model_name ): array {
		$details      = $this->fetchModelDetails( $model_name );
		$capabilities = $this->normalizeCapabilities( $details );
		$features     = $this->detectFeatures( $model_name, $details );
		$descriptor   = array(
			'id'                 => $model_name,
			'nativeCapabilities' => $capabilities,
			'features'           => $features,
			'contextLength'      => $this->extractContextLength( $details ),
		);

		if ( isset( $details['details'] ) && is_array( $details['details'] ) ) {
			foreach ( array( 'family', 'families', 'parameter_size', 'quantization_level', 'format' ) as $key ) {
				if ( isset( $details['details'][ $key ] ) ) {
					$descriptor[ $key ] = $details['details'][ $key ];
				}
			}
		}

		return $descriptor;
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

			$metadata = $this->buildModelMetadata( $model_name, $this->fetchModelDetails( $model_name ) );
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
		$preferred_model = OllamaSettings::get_preferred_model( 'text' );
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
		$features = $this->detectFeatures( $model_name, $details );

		if ( $features['embedding'] && defined( CapabilityEnum::class . '::EMBEDDING_GENERATION' ) ) {
			$options = array(
				new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
				new SupportedOption( OptionEnum::customOptions() ),
			);
			if ( defined( ModelConfig::class . '::KEY_DIMENSIONS' ) ) {
				$options[] = new SupportedOption( OptionEnum::dimensions() );
			}

			return new ModelMetadata(
				$model_name,
				$model_name,
				array( CapabilityEnum::embeddingGeneration() ),
				$options
			);
		}

		// Embedding-only models cannot be represented by the WordPress 7.0 API.
		if ( $features['embedding'] && ! $features['text'] ) {
			return null;
		}

		$has_vision                = $features['vision'];
		$is_image_generation_model = $features['image'];

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
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::frequencyPenalty() ),
			new SupportedOption( OptionEnum::presencePenalty() ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
			$input_modalities_option,
		);

		if ( $features['structured_output'] ) {
			$options[] = new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) );
			$options[] = new SupportedOption( OptionEnum::outputSchema() );
		} else {
			$options[] = new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain' ) );
		}

		if ( $features['tools'] ) {
			$options[] = new SupportedOption( OptionEnum::functionDeclarations() );
		}

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
	 * Detects normalized model features from Ollama metadata.
	 *
	 * @since 1.1.0
	 *
	 * @param string                $model_name Model identifier.
	 * @param ShowResponseData|null $details Native model details.
	 * @return array<string, bool> Feature flags.
	 */
	private function detectFeatures( string $model_name, ?array $details ): array {
		$capabilities = $this->normalizeCapabilities( $details );
		$families     = array();
		if ( isset( $details['details']['families'] ) && is_array( $details['details']['families'] ) ) {
			$families = array_values( array_filter( $details['details']['families'], 'is_string' ) );
		}

		$is_embedding = in_array( 'embedding', $capabilities, true )
			|| ( null === $details && 1 === preg_match( '/(?:^|[-_:])(embed|embedding|bge|e5)(?:[-_:]|$)|nomic-embed|all-minilm/i', $model_name ) );
		$is_image     = in_array( 'image', $capabilities, true );
		$is_text      = in_array( 'completion', $capabilities, true ) || ( empty( $capabilities ) && ! $is_embedding && ! $is_image );

		return array(
			'embedding'         => $is_embedding,
			'image'             => $is_image,
			'structured_output' => $is_text && ! OllamaSettings::is_cloud_connection(),
			'text'              => $is_text,
			'thinking'          => in_array( 'thinking', $capabilities, true ),
			'tools'             => in_array( 'tools', $capabilities, true ),
			'vision'            => in_array( 'vision', $capabilities, true ) || in_array( 'clip', $families, true ),
		);
	}

	/**
	 * Normalizes the capabilities returned by /api/show.
	 *
	 * @since 1.1.0
	 *
	 * @param ShowResponseData|null $details Native model details.
	 * @return list<string> Unique capability names.
	 */
	private function normalizeCapabilities( ?array $details ): array {
		if ( null === $details || ! isset( $details['capabilities'] ) || ! is_array( $details['capabilities'] ) ) {
			return array();
		}

		return array_values(
			array_unique(
				array_filter(
					$details['capabilities'],
					static function ( $capability ): bool {
						return is_string( $capability ) && '' !== $capability;
					}
				)
			)
		);
	}

	/**
	 * Extracts a context-window size from Ollama model_info metadata.
	 *
	 * @since 1.1.0
	 *
	 * @param ShowResponseData|null $details Native model details.
	 * @return int|null Context size when reported.
	 */
	private function extractContextLength( ?array $details ): ?int {
		if ( null === $details || ! isset( $details['model_info'] ) || ! is_array( $details['model_info'] ) ) {
			return null;
		}

		foreach ( $details['model_info'] as $key => $value ) {
			if ( is_string( $key ) && str_ends_with( $key, '.context_length' ) && is_numeric( $value ) ) {
				return (int) $value;
			}
		}

		return null;
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
		if ( array_key_exists( $model_name, $this->model_details_cache ) ) {
			return $this->model_details_cache[ $model_name ];
		}

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
			$data                                     = $response->getData();
			$this->model_details_cache[ $model_name ] = $data;
			return $data;
		} catch ( \Throwable $e ) {
			$this->model_details_cache[ $model_name ] = null;
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
