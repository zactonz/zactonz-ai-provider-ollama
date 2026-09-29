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

	public function test_each_configured_default_leads_the_listing(): void {
		$GLOBALS['zctz_test_options']['zctz_ollama_ai_connector_settings'] = array(
			'connection_type' => 'self_hosted',
			'model_text'      => 'qwen3',
			'model_embedding' => 'embeddinggemma',
		);

		$directory = new OllamaModelMetadataDirectory();
		$directory->setHttpTransporter(
			new FakeHttpTransporter(
				array(
					$this->response( array( 'models' => array( array( 'name' => 'zeta' ), array( 'name' => 'embeddinggemma' ), array( 'name' => 'qwen3' ) ) ) ),
					$this->response( array( 'capabilities' => array( 'completion' ) ) ),
					$this->response( array( 'capabilities' => array( 'embedding' ) ) ),
					$this->response( array( 'capabilities' => array( 'completion' ) ) ),
				)
			)
		);
		$directory->setRequestAuthentication( new PassthroughAuthentication() );

		$ids = array();
		foreach ( $directory->listModelMetadata() as $model ) {
			$ids[] = $model->getId();
		}

		// The text default leads a plain request; the embedding default still sits
		// ahead of everything else that could answer an embedding request.
		$this->assertSame( array( 'qwen3', 'embeddinggemma', 'zeta' ), $ids );
	}

	private function response( array $data ): Response {
		return new Response( 200, array( 'Content-Type' => 'application/json' ), json_encode( $data ) );
	}

	public function test_a_listing_entry_without_a_name_is_skipped_rather_than_fatal(): void {
		$directory = new OllamaModelMetadataDirectory();
		$transport = new FakeHttpTransporter(
			array(
				$this->response(
					array(
						'models' => array(
							array( 'name' => 'qwen3' ),
							array( 'model' => 'named-by-model-key' ),
							array( 'size' => 123 ),
							'not-an-array',
							array( 'name' => 'gemma3' ),
						),
					)
				),
				$this->response( array( 'capabilities' => array( 'completion' ) ) ),
				$this->response( array( 'capabilities' => array( 'completion' ) ) ),
				$this->response( array( 'capabilities' => array( 'completion' ) ) ),
			)
		);
		$directory->setHttpTransporter( $transport );
		$directory->setRequestAuthentication( new PassthroughAuthentication() );

		$ids = array();
		foreach ( $directory->listModelMetadata() as $model ) {
			$ids[] = $model->getId();
		}

		$this->assertSame( array( 'gemma3', 'named-by-model-key', 'qwen3' ), $ids );
	}

	public function test_a_cloud_listing_entry_that_is_not_an_array_is_skipped(): void {
		$GLOBALS['zctz_test_options']['zctz_ollama_ai_connector_settings']['connection_type'] = 'cloud';

		$directory = new OllamaModelMetadataDirectory();
		$transport = new FakeHttpTransporter(
			array(
				$this->response( array( 'data' => array( 'not-an-array', array( 'id' => 'gpt-oss' ) ) ) ),
				$this->response( array( 'capabilities' => array( 'completion' ) ) ),
			)
		);
		$directory->setHttpTransporter( $transport );
		$directory->setRequestAuthentication( new PassthroughAuthentication() );

		$ids = array();
		foreach ( $directory->listModelMetadata() as $model ) {
			$ids[] = $model->getId();
		}

		$this->assertSame( array( 'gpt-oss' ), $ids );
	}
}
