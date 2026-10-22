<?php
/**
 * Ollama text-generation model implementation.
 *
 * @package Zactonz\AiProviderForOllama\Models
 */

declare( strict_types=1 );

namespace Zactonz\AiProviderForOllama\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zactonz\AiProviderForOllama\Models\Traits\OllamaRequestOptionsTrait;
use Zactonz\AiProviderForOllama\Provider\OllamaProvider;
use Zactonz\AiProviderForOllama\Settings\OllamaSettings;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
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
	 * @phpstan-param array<string, string|list<string>> $headers
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
	 * Streams a text response and returns the fully aggregated AI Client result.
	 *
	 * This provider extension is intentionally Ollama-specific until the PHP AI
	 * Client publishes a stable streaming contract. The callback receives arrays
	 * with a `type` of content_delta, thinking_delta, tool_call_delta, or done.
	 * Returning false stops delivering events and returns the partial result with
	 * `cancelled` in its additional provider data. The HTTP request itself still
	 * runs to completion: nothing in the HTTP API can abort a response mid-body,
	 * and PHP defers an exception thrown from a transport callback until the
	 * transfer has finished anyway.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, Message> $prompt Prompt messages accepted by generateTextResult().
	 * @phpstan-param list<Message> $prompt
	 * @param callable            $on_event Receives each normalized stream event.
	 * @return GenerativeAiResult Aggregated result.
	 * @throws RuntimeException When streaming is unavailable, cancelled by an exception, or the transfer fails.
	 * @throws ResponseException When Ollama returns an unsuccessful response.
	 */
	public function generateOllamaStreamResult( array $prompt, callable $on_event ): GenerativeAiResult {
		$params = $this->prepareGenerateTextParams( $prompt );
		unset( $params['ollama.request_timeout'], $params['ollama.connect_timeout'] );
		$params           = $this->applyPreferredModelThinkingMode( $params );
		$params['stream'] = true;

		/*
		 * An OpenAI-compatible stream omits the usage block unless it is asked for,
		 * so without this the aggregated result reports no tokens at all.
		 */
		if ( ! isset( $params['stream_options'] ) ) {
			$params['stream_options'] = array( 'include_usage' => true );
		}

		$url     = OllamaProvider::url( '/v1/chat/completions' );
		$headers = array(
			'Content-Type' => 'application/json',
			'Accept'       => 'text/event-stream',
		);
		foreach ( OllamaSettings::get_request_headers_for_connection( OllamaSettings::get_connection_type() ) as $name => $value ) {
			$headers[ $name ] = $value;
		}

		$content          = '';
		$thinking         = '';
		$finish_reason    = null;
		$id               = '';
		$usage            = array();
		$tool_calls       = array();
		$buffer           = '';
		$raw_body         = '';
		$streamed         = false;
		$halted           = false;
		$cancelled        = false;
		$callback_error   = null;
		$process_sse_data = function ( string $data ) use ( &$content, &$thinking, &$finish_reason, &$id, &$usage, &$tool_calls, &$cancelled, &$callback_error, $on_event ): bool {
			if ( '[DONE]' === trim( $data ) ) {
				return true;
			}

			$chunk = json_decode( $data, true );
			if ( ! is_array( $chunk ) ) {
				return true;
			}
			if ( isset( $chunk['id'] ) && is_string( $chunk['id'] ) ) {
				$id = $chunk['id'];
			}
			if ( isset( $chunk['usage'] ) && is_array( $chunk['usage'] ) ) {
				$usage = $chunk['usage'];
			}

			$choice = isset( $chunk['choices'][0] ) && is_array( $chunk['choices'][0] ) ? $chunk['choices'][0] : array();
			$delta  = isset( $choice['delta'] ) && is_array( $choice['delta'] ) ? $choice['delta'] : array();
			$events = array();
			if ( isset( $delta['content'] ) && is_string( $delta['content'] ) && '' !== $delta['content'] ) {
				$content .= $delta['content'];
				$events[] = array(
					'type'  => 'content_delta',
					'delta' => $delta['content'],
					'raw'   => $chunk,
				);
			}
			$thinking_delta = isset( $delta['reasoning_content'] ) && is_string( $delta['reasoning_content'] )
				? $delta['reasoning_content']
				: ( isset( $delta['reasoning'] ) && is_string( $delta['reasoning'] ) ? $delta['reasoning'] : '' );
			if ( '' !== $thinking_delta ) {
				$thinking .= $thinking_delta;
				$events[]  = array(
					'type'  => 'thinking_delta',
					'delta' => $thinking_delta,
					'raw'   => $chunk,
				);
			}
			if ( isset( $delta['tool_calls'] ) && is_array( $delta['tool_calls'] ) ) {
				foreach ( $delta['tool_calls'] as $tool_delta ) {
					if ( ! is_array( $tool_delta ) ) {
						continue;
					}
					$index = isset( $tool_delta['index'] ) ? (int) $tool_delta['index'] : count( $tool_calls );
					if ( ! isset( $tool_calls[ $index ] ) ) {
						$tool_calls[ $index ] = array(
							'id'       => '',
							'type'     => 'function',
							'function' => array(
								'name'      => '',
								'arguments' => '',
							),
						);
					}
					if ( isset( $tool_delta['id'] ) && is_string( $tool_delta['id'] ) ) {
						$tool_calls[ $index ]['id'] = $tool_delta['id'];
					}
					if ( isset( $tool_delta['function']['name'] ) && is_string( $tool_delta['function']['name'] ) ) {
						$tool_calls[ $index ]['function']['name'] .= $tool_delta['function']['name'];
					}
					if ( isset( $tool_delta['function']['arguments'] ) && is_string( $tool_delta['function']['arguments'] ) ) {
						$tool_calls[ $index ]['function']['arguments'] .= $tool_delta['function']['arguments'];
					}
					$events[] = array(
						'type'  => 'tool_call_delta',
						'index' => $index,
						'delta' => $tool_delta,
						'raw'   => $chunk,
					);
				}
			}
			if ( isset( $choice['finish_reason'] ) && is_string( $choice['finish_reason'] ) ) {
				$finish_reason = $choice['finish_reason'];
			}

			foreach ( $events as $event ) {
				try {
					if ( false === $on_event( $event ) ) {
						$cancelled = true;
						return false;
					}
				} catch ( \Throwable $e ) {
					$callback_error = $e;
					return false;
				}
			}
			return true;
		};

		$consume = function ( string $chunk ) use ( &$buffer, &$raw_body, &$cancelled, &$callback_error, $process_sse_data ): bool {
			$raw_body .= $chunk;
			$buffer   .= str_replace( "\r\n", "\n", $chunk );

			while ( true ) {
				$separator = strpos( $buffer, "\n\n" );
				if ( false === $separator ) {
					break;
				}

				$block  = substr( $buffer, 0, $separator );
				$buffer = substr( $buffer, $separator + 2 );
				$data   = array();

				foreach ( explode( "\n", $block ) as $line ) {
					if ( 0 === strpos( $line, 'data:' ) ) {
						$data[] = ltrim( substr( $line, 5 ) );
					}
				}

				if ( ! empty( $data ) && ! $process_sse_data( implode( "\n", $data ) ) ) {
					return false;
				}
			}

			return ! $cancelled && null === $callback_error;
		};

		/*
		 * WordPress forwards every Requests hook to an action named requests-<hook>,
		 * and both bundled transports dispatch request.progress with each block of
		 * the body as it arrives. So the request stays an ordinary wp_remote_post()
		 * and the chunks come back through a WordPress action, with no transport
		 * code of our own.
		 */
		$progress = function ( $chunk ) use ( $consume, &$streamed, &$halted ): void {
			$streamed = true;

			if ( ! $consume( (string) $chunk ) ) {
				$halted = true;

				throw new OllamaStreamCancellation();
			}
		};

		/*
		 * wp_json_encode() returns false on data it cannot encode, malformed UTF-8 in
		 * post content being the realistic case. Passing that to wp_remote_post sends
		 * an empty body and Ollama answers with a puzzling error, so say what actually
		 * went wrong instead.
		 */
		$body = wp_json_encode( $params );

		if ( false === $body ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is not rendered directly.
			throw new RuntimeException( 'The Ollama streaming request could not be encoded: ' . json_last_error_msg() );
		}

		add_action( 'requests-request.progress', $progress, 10, 1 );

		try {
			$response = wp_remote_post(
				$url,
				array(
					'headers'     => $headers,
					'body'        => $body,
					'timeout'     => (int) ceil( OllamaSettings::get_text_request_timeout() ),
					'redirection' => 0,
					'httpversion' => '1.1',
				)
			);
		} catch ( OllamaStreamCancellation $cancellation ) {
			$response = null;
		} finally {
			remove_action( 'requests-request.progress', $progress, 10 );
		}

		if ( null === $response ) {
			$status_code = 200;
		} elseif ( is_wp_error( $response ) ) {
			if ( ! $cancelled ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is not rendered directly.
				throw new RuntimeException( 'Ollama streaming request failed: ' . $response->get_error_message() );
			}

			$status_code = 200;
		} else {
			$status_code = (int) wp_remote_retrieve_response_code( $response );

			if ( ! $streamed ) {
				/*
				 * No incremental delivery, so the response arrived in one piece. Replay
				 * it through the same parser and the caller still sees every event.
				 */
				$halted = ! $consume( (string) wp_remote_retrieve_body( $response ) );
			}
		}

		if ( ! $cancelled && ( $status_code < 200 || $status_code >= 300 ) ) {
			$message = json_decode( $raw_body, true );
			$message = is_array( $message ) && isset( $message['error'] ) ? (string) $message['error'] : trim( $raw_body );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Provider exception data is not output directly.
			throw ResponseException::fromInvalidData( $this->providerMetadata()->getName(), 'stream', '' !== $message ? $message : 'Unexpected HTTP status ' . $status_code . '.' );
		}

		/*
		 * A stream can end without the blank line that closes its last block, leaving
		 * one event in the buffer. Parse it, unless the caller has already cancelled or
		 * its callback has already thrown, so nothing reaches the caller after it asked
		 * to stop and the partial result stays exactly what it was told about.
		 */
		if ( '' !== trim( $buffer ) && ! $halted ) {
			$data = preg_replace( '/^data:\s*/m', '', trim( $buffer ) );
			if ( is_string( $data ) ) {
				$process_sse_data( $data );
			}
		}

		/*
		 * Checked once, here, because the caller's callback runs on every path above:
		 * during the stream, during a replayed body, and on the trailing block.
		 */
		if ( $callback_error instanceof \Throwable ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is not rendered directly.
			throw new RuntimeException( 'The Ollama stream callback failed: ' . $callback_error->getMessage(), 0, $callback_error );
		}

		ksort( $tool_calls );
		$message = array(
			'role'    => 'assistant',
			'content' => $content,
		);
		if ( '' !== $thinking ) {
			$message['reasoning_content'] = $thinking;
		}
		if ( ! empty( $tool_calls ) ) {
			$message['tool_calls'] = array_values( $tool_calls );
		}
		$finish_reason = null !== $finish_reason ? $finish_reason : ( ! empty( $tool_calls ) ? 'tool_calls' : 'stop' );
		$response_data = array(
			'id'        => $id,
			'choices'   => array(
				array(
					'message'       => $message,
					'finish_reason' => $finish_reason,
				),
			),
			'usage'     => $usage,
			'cancelled' => $cancelled,
		);

		$result = $this->parseResponseToGenerativeAiResult(
			new Response( 200, array( 'Content-Type' => 'application/json' ), (string) wp_json_encode( $response_data ) )
		);
		try {
			$on_event(
				array(
					'type'         => 'done',
					'cancelled'    => $cancelled,
					'finishReason' => $finish_reason,
					'usage'        => $usage,
				)
			);
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is not rendered directly.
			throw new RuntimeException( 'The Ollama stream callback failed: ' . $e->getMessage(), 0, $e );
		}

		return $result;
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
		} elseif ( in_array( $thinking_mode, array( 'low', 'medium', 'high' ), true ) ) {
			$data['reasoning_effort'] = $thinking_mode;
		}

		return $data;
	}
}
