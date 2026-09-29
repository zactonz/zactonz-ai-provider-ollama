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


	public function test_a_key_typed_on_the_core_connectors_screen_is_used(): void {
		$GLOBALS['zctz_test_options']['zctz_ollama_ai_connector_settings']     = array( 'connection_type' => 'cloud' );
		$GLOBALS['zctz_test_options'][ OllamaSettings::core_api_key_option() ] = 'key-from-core-screen';

		OllamaSettings::adopt_existing_connector_api_key();

		$this->assertSame( 'key-from-core-screen', OllamaSettings::get_active_saved_api_key() );
	}

	public function test_a_shared_key_is_not_adopted_into_a_mode_that_does_not_own_it(): void {
		$GLOBALS['zctz_test_options']['zctz_ollama_ai_connector_settings']     = array(
			'connection_type'  => 'self_hosted',
			'shared_key_mode'  => 'cloud',
		);
		$GLOBALS['zctz_test_options'][ OllamaSettings::core_api_key_option() ] = 'CLOUD-SECRET';

		OllamaSettings::adopt_existing_connector_api_key();

		$this->assertSame( '', OllamaSettings::get_active_saved_api_key() );
		$this->assertArrayNotHasKey(
			'zctz_ollama_ai_connector_self_hosted_api_key',
			$GLOBALS['zctz_test_options'],
			'A Cloud credential must never be filed under the self-hosted connection.'
		);
	}

	public function test_the_plugins_own_key_wins_over_the_shared_option(): void {
		$GLOBALS['zctz_test_options']['zctz_ollama_ai_connector_settings']      = array( 'connection_type' => 'cloud' );
		$GLOBALS['zctz_test_options']['zctz_ollama_ai_connector_cloud_api_key'] = 'key-from-plugin-screen';
		$GLOBALS['zctz_test_options'][ OllamaSettings::core_api_key_option() ]  = 'stale-key';

		$this->assertSame( 'key-from-plugin-screen', OllamaSettings::get_active_saved_api_key() );
	}

	public function test_mirroring_publishes_the_active_key_to_the_shared_option(): void {
		$GLOBALS['zctz_test_options']['zctz_ollama_ai_connector_settings']      = array( 'connection_type' => 'cloud' );
		$GLOBALS['zctz_test_options']['zctz_ollama_ai_connector_cloud_api_key'] = 'cloud-key';

		OllamaSettings::mirror_active_api_key();

		$this->assertSame( 'cloud-key', $GLOBALS['zctz_test_options'][ OllamaSettings::core_api_key_option() ] );
	}

	public function test_switching_connection_type_does_not_discard_the_other_key(): void {
		$GLOBALS['zctz_test_options']['zctz_ollama_ai_connector_cloud_api_key']       = 'cloud-key';
		$GLOBALS['zctz_test_options']['zctz_ollama_ai_connector_self_hosted_api_key'] = 'self-hosted-key';

		$GLOBALS['zctz_test_options']['zctz_ollama_ai_connector_settings'] = array( 'connection_type' => 'cloud' );
		OllamaSettings::mirror_active_api_key();
		$this->assertSame( 'cloud-key', $GLOBALS['zctz_test_options'][ OllamaSettings::core_api_key_option() ] );

		$GLOBALS['zctz_test_options']['zctz_ollama_ai_connector_settings'] = array( 'connection_type' => 'self_hosted' );
		OllamaSettings::mirror_active_api_key();
		$this->assertSame( 'self-hosted-key', $GLOBALS['zctz_test_options'][ OllamaSettings::core_api_key_option() ] );

		// Both are still on disk, so switching back loses nothing.
		$this->assertSame( 'cloud-key', $GLOBALS['zctz_test_options']['zctz_ollama_ai_connector_cloud_api_key'] );
	}

	public function test_the_cloud_key_is_never_used_for_a_self_hosted_connection(): void {
		$GLOBALS['zctz_test_options'] = array(
			'zctz_ollama_ai_connector_settings'       => array( 'connection_type' => 'cloud' ),
			'zctz_ollama_ai_connector_cloud_api_key'  => 'CLOUD-SECRET',
		);

		$this->assertSame( 'CLOUD-SECRET', OllamaSettings::get_active_saved_api_key() );

		$GLOBALS['zctz_test_options']['zctz_ollama_ai_connector_settings']['connection_type'] = 'self_hosted';

		$this->assertSame(
			'',
			OllamaSettings::get_active_saved_api_key(),
			'Switching mode must not carry the Cloud credential to a self-hosted address.'
		);
	}

	public function test_the_self_hosted_key_is_never_sent_to_ollama_cloud(): void {
		$GLOBALS['zctz_test_options'] = array(
			'zctz_ollama_ai_connector_settings'            => array( 'connection_type' => 'self_hosted' ),
			'zctz_ollama_ai_connector_self_hosted_api_key' => 'PRIVATE-LAN-TOKEN',
		);

		$this->assertSame( 'PRIVATE-LAN-TOKEN', OllamaSettings::get_active_saved_api_key() );

		$GLOBALS['zctz_test_options']['zctz_ollama_ai_connector_settings']['connection_type'] = 'cloud';

		$this->assertSame(
			'',
			OllamaSettings::get_active_saved_api_key(),
			'Switching mode must not send a private token to the Cloud service.'
		);

		$headers = OllamaSettings::get_request_headers_for_connection( 'cloud' );
		$this->assertArrayNotHasKey( 'Authorization', $headers );
	}

	public function test_a_key_written_by_the_connectors_screen_lands_in_the_active_mode(): void {
		$GLOBALS['zctz_test_options'] = array(
			'zctz_ollama_ai_connector_settings' => array( 'connection_type' => 'cloud' ),
		);

		OllamaSettings::adopt_connector_api_key( 'KEY-FROM-CONNECTORS-SCREEN' );

		$this->assertSame(
			'KEY-FROM-CONNECTORS-SCREEN',
			$GLOBALS['zctz_test_options']['zctz_ollama_ai_connector_cloud_api_key']
		);
		$this->assertArrayNotHasKey(
			'zctz_ollama_ai_connector_self_hosted_api_key',
			$GLOBALS['zctz_test_options'],
			'The other mode must not receive the key.'
		);
		$this->assertSame( 'KEY-FROM-CONNECTORS-SCREEN', OllamaSettings::get_active_saved_api_key() );
	}

	public function test_an_empty_connector_key_write_is_ignored(): void {
		$GLOBALS['zctz_test_options'] = array(
			'zctz_ollama_ai_connector_settings'       => array( 'connection_type' => 'cloud' ),
			'zctz_ollama_ai_connector_cloud_api_key'  => 'KEEP-ME',
		);

		OllamaSettings::adopt_connector_api_key( '' );

		$this->assertSame( 'KEEP-ME', OllamaSettings::get_active_saved_api_key() );
	}
}
