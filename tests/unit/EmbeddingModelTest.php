<?php

declare( strict_types=1 );

namespace Zactonz\AiProviderForOllama\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zactonz\AiProviderForOllama\Models\OllamaEmbeddingGenerationModel;
use Zactonz\AiProviderForOllama\Tests\Support\FakeHttpTransporter;
use Zactonz\AiProviderForOllama\Tests\Support\PassthroughAuthentication;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;

class EmbeddingModelTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['zctz_test_options'] = array(
			'zctz_ollama_ai_connector_settings' => array( 'connection_type' => 'self_hosted' ),
		);
	}

	public function test_generates_batched_embeddings_with_dimensions_and_usage(): void {
		$model = new OllamaEmbeddingGenerationModel(
			new ModelMetadata( 'embeddinggemma', 'Embedding Gemma', array( CapabilityEnum::embeddingGeneration() ), array() ),
			new ProviderMetadata( 'ollama', 'Ollama', ProviderTypeEnum::cloud(), null, RequestAuthenticationMethod::apiKey() )
		);
		$config = ModelConfig::fromArray( array( 'dimensions' => 3 ) );
		$model->setConfig( $config );
		$transport = new FakeHttpTransporter(
			array(
				new Response(
					200,
					array( 'Content-Type' => 'application/json' ),
					json_encode(
						array(
							'embeddings'       => array( array( 0.1, 0.2, 0.3 ), array( 0.4, 0.5, 0.6 ) ),
							'prompt_eval_count' => 9,
							'total_duration'    => 1000,
						)
					)
				),
			)
		);
		$model->setHttpTransporter( $transport );
		$model->setRequestAuthentication( new PassthroughAuthentication() );

		$result = $model->generateEmbeddingResult( array( new MessagePart( 'One' ), new MessagePart( 'Two' ) ) );

		$this->assertCount( 2, $result->getEmbeddings() );
		$this->assertSame( 3, $result->getDimensions() );
		$this->assertSame( 9, $result->getTokenUsage()->getPromptTokens() );
		$this->assertSame( array( 0.1, 0.2, 0.3 ), $result->getEmbedding()->getValues() );
		$this->assertSame( 3, $transport->requests[0]->getData()['dimensions'] );
	}
}
