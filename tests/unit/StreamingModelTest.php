<?php

declare( strict_types=1 );

namespace Zactonz\AiProviderForOllama\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zactonz\AiProviderForOllama\Models\OllamaTextGenerationModel;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;

class StreamingModelTest extends TestCase {
	/** @var resource|null */
	private $process;

	/** @var int */
	private $port;

	protected function setUp(): void {
		if ( ! function_exists( 'curl_init' ) || ! function_exists( 'proc_open' ) ) {
			$this->markTestSkipped( 'cURL and proc_open are required for the streaming integration test.' );
		}

		$socket = stream_socket_server( 'tcp://127.0.0.1:0', $error_code, $error_message );
		$this->assertIsResource( $socket, $error_message );
		$address    = stream_socket_get_name( $socket, false );
		$this->port = (int) substr( $address, strrpos( $address, ':' ) + 1 );
		fclose( $socket );

		$router  = dirname( __DIR__ ) . '/fixtures/stream-server.php';
		$command = array( PHP_BINARY, '-S', '127.0.0.1:' . $this->port, $router );
		$null    = 'NUL' === strtoupper( substr( PHP_OS, 0, 3 ) ) ? 'NUL' : '/dev/null';
		$this->process = proc_open(
			$command,
			array(
				0 => array( 'file', $null, 'r' ),
				1 => array( 'file', $null, 'a' ),
				2 => array( 'file', $null, 'a' ),
			),
			$pipes
		);
		$this->assertIsResource( $this->process );

		$ready = false;
		for ( $attempt = 0; $attempt < 40; $attempt++ ) {
			$connection = @fsockopen( '127.0.0.1', $this->port );
			if ( is_resource( $connection ) ) {
				fclose( $connection );
				$ready = true;
				break;
			}
			usleep( 25000 );
		}
		$this->assertTrue( $ready, 'The streaming fixture server did not start.' );

		$GLOBALS['zctz_test_http_responses'] = array();
		$GLOBALS['zctz_test_http_requests']  = array();
		$GLOBALS['zctz_test_hooks']          = array();
		$GLOBALS['zctz_test_options']        = array(
			'zctz_ollama_ai_connector_settings' => array(
				'connection_type' => 'self_hosted',
				'host'            => 'http://127.0.0.1',
				'port'            => (string) $this->port,
				'model_text'      => 'qwen3',
				'request_timeout' => '15',
			),
		);
	}

	protected function tearDown(): void {
		if ( is_resource( $this->process ) ) {
			proc_terminate( $this->process );
			proc_close( $this->process );
		}
	}

	public function test_streams_thinking_content_and_usage_then_returns_full_result(): void {
		$model = new OllamaTextGenerationModel(
			new ModelMetadata( 'qwen3', 'Qwen 3', array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ), array() ),
			new ProviderMetadata( 'ollama', 'Ollama', ProviderTypeEnum::cloud(), null, RequestAuthenticationMethod::apiKey() )
		);
		$events = array();
		$result = $model->generateOllamaStreamResult(
			array( new UserMessage( array( new MessagePart( 'Hello' ) ) ) ),
			static function ( array $event ) use ( &$events ): void {
				$events[] = $event;
			}
		);

		$this->assertSame( 'Hello WordPress.', $result->toText() );
		$this->assertSame( 7, $result->getTokenUsage()->getTotalTokens() );
		$this->assertSame(
			array( 'thinking_delta', 'content_delta', 'content_delta', 'done' ),
			array_column( $events, 'type' )
		);
	}

	public function test_callback_can_cancel_and_receive_a_partial_result(): void {
		$model = new OllamaTextGenerationModel(
			new ModelMetadata( 'qwen3', 'Qwen 3', array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ), array() ),
			new ProviderMetadata( 'ollama', 'Ollama', ProviderTypeEnum::cloud(), null, RequestAuthenticationMethod::apiKey() )
		);
		$events = array();
		$result = $model->generateOllamaStreamResult(
			array( new UserMessage( array( new MessagePart( 'Hello' ) ) ) ),
			static function ( array $event ) use ( &$events ) {
				$events[] = $event;
				return 'content_delta' !== $event['type'];
			}
		);

		$this->assertSame( 'Hello ', $result->toText() );
		$this->assertTrue( $events[ count( $events ) - 1 ]['cancelled'] );
		$this->assertSame( 'done', $events[ count( $events ) - 1 ]['type'] );
	}

	public function test_streamed_tool_call_fragments_are_assembled(): void {
		$model = new OllamaTextGenerationModel(
			new ModelMetadata( 'tool-model', 'Tool model', array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ), array() ),
			new ProviderMetadata( 'ollama', 'Ollama', ProviderTypeEnum::cloud(), null, RequestAuthenticationMethod::apiKey() )
		);
		$events = array();
		$result = $model->generateOllamaStreamResult(
			array( new UserMessage( array( new MessagePart( 'Find WordPress' ) ) ) ),
			static function ( array $event ) use ( &$events ): void {
				$events[] = $event;
			}
		);

		$function_call = null;
		foreach ( $result->toMessage()->getParts() as $part ) {
			if ( null !== $part->getFunctionCall() ) {
				$function_call = $part->getFunctionCall();
			}
		}
		$this->assertNotNull( $function_call );
		$this->assertSame( 'lookup', $function_call->getName() );
		$this->assertSame( array( 'query' => 'WordPress' ), $function_call->getArgs() );
		$this->assertSame( 2, count( array_filter( $events, static function ( array $event ): bool { return 'tool_call_delta' === $event['type']; } ) ) );
	}

	public function test_callback_exception_is_wrapped_with_context(): void {
		$model = new OllamaTextGenerationModel(
			new ModelMetadata( 'qwen3', 'Qwen 3', array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ), array() ),
			new ProviderMetadata( 'ollama', 'Ollama', ProviderTypeEnum::cloud(), null, RequestAuthenticationMethod::apiKey() )
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'The Ollama stream callback failed: Consumer failed.' );
		$model->generateOllamaStreamResult(
			array( new UserMessage( array( new MessagePart( 'Hello' ) ) ) ),
			static function (): void {
				throw new \LogicException( 'Consumer failed.' );
			}
		);
	}

	public function test_unsuccessful_http_status_throws_response_exception(): void {
		$model = new OllamaTextGenerationModel(
			new ModelMetadata( 'error-model', 'Error model', array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ), array() ),
			new ProviderMetadata( 'ollama', 'Ollama', ProviderTypeEnum::cloud(), null, RequestAuthenticationMethod::apiKey() )
		);

		$this->expectException( ResponseException::class );
		$this->expectExceptionMessage( 'Model is unavailable.' );
		$model->generateOllamaStreamResult(
			array( new UserMessage( array( new MessagePart( 'Hello' ) ) ) ),
			static function (): void {}
		);
	}

	private function unterminatedModel(): OllamaTextGenerationModel {
		return new OllamaTextGenerationModel(
			new ModelMetadata( 'unterminated-model', 'Unterminated', array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ), array() ),
			new ProviderMetadata( 'ollama', 'Ollama', ProviderTypeEnum::cloud(), null, RequestAuthenticationMethod::apiKey() )
		);
	}

	public function test_the_trailing_block_is_parsed_when_the_caller_is_still_listening(): void {
		$result = $this->unterminatedModel()->generateOllamaStreamResult(
			array( new UserMessage( array( new MessagePart( 'Hello' ) ) ) ),
			static function ( array $event ): void {
				unset( $event );
			}
		);

		$this->assertSame( 'FIRST TRAILING', $result->toText() );
	}

	public function test_nothing_is_delivered_after_the_caller_cancels(): void {
		$events = array();
		$result = $this->unterminatedModel()->generateOllamaStreamResult(
			array( new UserMessage( array( new MessagePart( 'Hello' ) ) ) ),
			static function ( array $event ) use ( &$events ) {
				$events[] = $event;
				return 'content_delta' !== $event['type'];
			}
		);

		$deltas = array();
		foreach ( $events as $event ) {
			if ( 'content_delta' === $event['type'] ) {
				$deltas[] = $event['delta'];
			}
		}

		$this->assertSame( array( 'FIRST ' ), $deltas, 'The trailing block must not be delivered after a cancel.' );
		$this->assertSame( 'FIRST ', $result->toText(), 'The partial result must hold only what the caller was told about.' );
	}

	public function test_a_callback_failure_on_the_trailing_block_is_reported(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'The Ollama stream callback failed' );

		$this->unterminatedModel()->generateOllamaStreamResult(
			array( new UserMessage( array( new MessagePart( 'Hello' ) ) ) ),
			static function ( array $event ): void {
				if ( 'content_delta' === $event['type'] && 'TRAILING' === $event['delta'] ) {
					throw new \LogicException( 'boom' );
				}
			}
		);
	}

	public function test_a_callback_failure_on_the_done_event_is_reported(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'The Ollama stream callback failed' );

		$this->unterminatedModel()->generateOllamaStreamResult(
			array( new UserMessage( array( new MessagePart( 'Hello' ) ) ) ),
			static function ( array $event ): void {
				if ( 'done' === $event['type'] ) {
					throw new \LogicException( 'boom' );
				}
			}
		);
	}

	public function test_the_stream_asks_ollama_to_include_usage(): void {
		$result = $this->unterminatedModel()->generateOllamaStreamResult(
			array( new UserMessage( array( new MessagePart( 'Hello' ) ) ) ),
			static function ( array $event ): void {
				unset( $event );
			}
		);

		$this->assertNotNull( $result->getTokenUsage() );
	}
}
