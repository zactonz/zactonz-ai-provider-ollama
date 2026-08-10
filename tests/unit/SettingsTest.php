<?php

declare( strict_types=1 );

namespace Zactonz\AiProviderForOllama\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zactonz\AiProviderForOllama\Settings\OllamaSettings;

class SettingsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['zctz_test_options'] = array();
	}

	public function test_sanitizes_task_defaults_thinking_and_timeouts(): void {
		$settings = new OllamaSettings();
		$value    = $settings->sanitize_settings(
			array(
				'connection_type'          => 'self_hosted',
				'host'                     => '127.0.0.1',
				'port'                     => '11434',
				'model_text'               => 'qwen3',
				'model_vision'             => 'gemma3',
				'model_image'              => 'x/z-image-turbo',
				'model_embedding'          => 'embeddinggemma',
				'model_tools'              => 'qwen3',
				'thinking'                 => 'high',
				'request_timeout'           => '240',
				'embedding_request_timeout' => '45',
			)
		);

		$this->assertSame( 'http://127.0.0.1', $value['host'] );
		$this->assertSame( 'qwen3', $value['model'] );
		$this->assertSame( 'embeddinggemma', $value['model_embedding'] );
		$this->assertSame( 'high', $value['thinking'] );
		$this->assertSame( '45', $value['embedding_request_timeout'] );
	}

	public function test_connection_only_save_preserves_model_defaults(): void {
		$GLOBALS['zctz_test_options']['zctz_ollama_ai_connector_settings'] = array(
			'connection_type'          => 'self_hosted',
			'model_text'               => 'qwen3',
			'model_embedding'          => 'embeddinggemma',
			'thinking'                 => 'low',
			'embedding_request_timeout' => '50',
		);

		$value = ( new OllamaSettings() )->sanitize_settings(
			array(
				'connection_type' => 'self_hosted',
				'host'            => 'localhost',
				'port'            => '11434',
				'request_timeout' => '180',
			)
		);

		$this->assertSame( 'qwen3', $value['model_text'] );
		$this->assertSame( 'embeddinggemma', $value['model_embedding'] );
		$this->assertSame( 'low', $value['thinking'] );
		$this->assertSame( '50', $value['embedding_request_timeout'] );
	}

	public function test_each_default_prepends_only_its_task_preference(): void {
		$GLOBALS['zctz_test_options']['zctz_ollama_ai_connector_settings'] = array(
			'model_text'      => 'qwen3',
			'model_embedding' => 'embeddinggemma',
		);
		$settings = new OllamaSettings();

		$this->assertSame( array( array( 'ollama', 'qwen3' ) ), $settings->prepend_default_text_model_preference( array() ) );
		$this->assertSame( array( array( 'ollama', 'embeddinggemma' ) ), $settings->prepend_default_embedding_model_preference( array() ) );
	}
}
