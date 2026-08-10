<?php

declare( strict_types=1 );

namespace Zactonz\AiProviderForOllama\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zactonz\AiProviderForOllama\Metadata\OllamaModelMetadataDirectory;
use Zactonz\AiProviderForOllama\Tests\Support\FakeHttpTransporter;
use Zactonz\AiProviderForOllama\Tests\Support\PassthroughAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Response;

class MetadataDirectoryTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['zctz_test_options'] = array(
			'zctz_ollama_ai_connector_settings' => array( 'connection_type' => 'self_hosted' ),
		);
	}

	public function test_discovers_only_capabilities_reported_by_ollama(): void {
		$directory = new OllamaModelMetadataDirectory();
		$transport = new FakeHttpTransporter(
			array(
				$this->response( array( 'models' => array( array( 'name' => 'qwen3' ), array( 'name' => 'embeddinggemma' ), array( 'name' => 'gemma3' ) ) ) ),
				$this->response( array( 'capabilities' => array( 'completion', 'tools', 'thinking' ), 'model_info' => array( 'qwen.context_length' => 32768 ) ) ),
				$this->response( array( 'capabilities' => array( 'embedding' ) ) ),
				$this->response( array( 'capabilities' => array( 'completion', 'vision' ), 'details' => array( 'families' => array( 'gemma3', 'clip' ) ) ) ),
			)
		);
		$directory->setHttpTransporter( $transport );
		$directory->setRequestAuthentication( new PassthroughAuthentication() );

		$models = $directory->listModelMetadata();
		$this->assertCount( 3, $models );
		$by_id = array();
		foreach ( $models as $model ) {
			$by_id[ $model->getId() ] = $model->toArray();
		}

		$this->assertSame( array( 'embedding_generation' ), $by_id['embeddinggemma']['supportedCapabilities'] );
		$this->assertContains( 'text_generation', $by_id['qwen3']['supportedCapabilities'] );
		$this->assertContains( 'functionDeclarations', array_column( $by_id['qwen3']['supportedOptions'], 'name' ) );
		$this->assertNotContains( 'functionDeclarations', array_column( $by_id['gemma3']['supportedOptions'], 'name' ) );
		$this->assertContains( 'outputSchema', array_column( $by_id['qwen3']['supportedOptions'], 'name' ) );

		$descriptor = $directory->getModelDescriptor( 'qwen3' );
		$this->assertTrue( $descriptor['features']['tools'] );
		$this->assertTrue( $descriptor['features']['thinking'] );
		$this->assertSame( 32768, $descriptor['contextLength'] );
	}

	private function response( array $data ): Response {
		return new Response( 200, array( 'Content-Type' => 'application/json' ), json_encode( $data ) );
	}
}
