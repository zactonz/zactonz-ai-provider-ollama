<?php
/**
 * PHPUnit bootstrap with the small WordPress surface used by the plugin.
 */

declare( strict_types=1 );

require dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

$GLOBALS['zctz_test_options'] = array();
$GLOBALS['zctz_test_hooks']   = array();
$GLOBALS['zctz_test_http_responses'] = array();

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;

		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

function __( $text, $domain = null ) {
	unset( $domain );
	return $text;
}

function esc_html__( $text, $domain = null ) {
	return __( $text, $domain );
}

function esc_attr__( $text, $domain = null ) {
	return __( $text, $domain );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return esc_html( $text );
}

function esc_url( $url ) {
	return (string) $url;
}

function esc_url_raw( $url ) {
	return filter_var( (string) $url, FILTER_SANITIZE_URL );
}

function wp_kses_post( $value ) {
	return $value;
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_unslash( $value ) {
	return $value;
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
	return json_encode( $value, $flags, $depth );
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['zctz_test_options'] ) ? $GLOBALS['zctz_test_options'][ $name ] : $default;
}

function update_option( $name, $value, $autoload = null ) {
	unset( $autoload );
	$GLOBALS['zctz_test_options'][ $name ] = $value;
	return true;
}

function delete_option( $name ) {
	unset( $GLOBALS['zctz_test_options'][ $name ] );
	return true;
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['zctz_test_hooks'][ $hook ][] = array( $callback, $priority, $accepted_args );
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_action( $hook, $callback, $priority, $accepted_args );
}

function remove_action( $hook, $callback, $priority = 10 ) {
	foreach ( $GLOBALS['zctz_test_hooks'][ $hook ] ?? array() as $index => $registered ) {
		if ( $registered[0] === $callback && $registered[1] === $priority ) {
			unset( $GLOBALS['zctz_test_hooks'][ $hook ][ $index ] );
		}
	}

	return true;
}

function remove_filter( $hook, $callback, $priority = 10 ) {
	return remove_action( $hook, $callback, $priority );
}

function plugin_dir_path( $file ) {
	return rtrim( dirname( $file ), '/\\' ) . '/';
}

function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

function is_admin() {
	return true;
}

function is_wp_version_compatible( $version ) {
	unset( $version );
	return true;
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

function current_user_can( $capability ) {
	unset( $capability );
	return true;
}

function add_settings_error() {
	return null;
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function wp_remote_get( $url, $args = array() ) {
	unset( $url, $args );
	if ( empty( $GLOBALS['zctz_test_http_responses'] ) ) {
		return new WP_Error( 'no_response', 'No test HTTP response was queued.' );
	}
	return array_shift( $GLOBALS['zctz_test_http_responses'] );
}

/**
 * Stands in for the HTTP API's transport layer.
 *
 * Requests dispatches request.progress with each block of the body, and WordPress
 * forwards it as the requests-request.progress action. Mirroring that here is what
 * keeps the streaming tests honest: they drive the same code path a real site
 * takes, against a real socket.
 */
function zctz_test_dispatch_progress( $chunk ) {
	foreach ( $GLOBALS['zctz_test_hooks']['requests-request.progress'] ?? array() as $registered ) {
		$registered[0]( $chunk, 0, false );
	}
}

function wp_remote_post( $url, $args = array() ) {
	if ( empty( $GLOBALS['zctz_test_hooks']['requests-request.progress'] ) || ! empty( $GLOBALS['zctz_test_http_responses'] ) ) {
		if ( empty( $GLOBALS['zctz_test_http_responses'] ) ) {
			return new WP_Error( 'no_response', 'No test HTTP response was queued.' );
		}
		return array_shift( $GLOBALS['zctz_test_http_responses'] );
	}

	$handle  = curl_init( $url );
	$status  = 0;
	$body    = '';
	$headers = array();

	foreach ( (array) ( $args['headers'] ?? array() ) as $name => $value ) {
		$headers[] = $name . ': ' . $value;
	}

	curl_setopt_array(
		$handle,
		array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $args['body'] ?? '',
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT        => (int) ( $args['timeout'] ?? 30 ),
			CURLOPT_HEADERFUNCTION => function ( $curl_handle, $header ) use ( &$status ) {
				unset( $curl_handle );
				if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', $header, $matches ) ) {
					$status = (int) $matches[1];
				}
				return strlen( $header );
			},
			CURLOPT_WRITEFUNCTION  => function ( $curl_handle, $chunk ) use ( &$body ) {
				unset( $curl_handle );
				zctz_test_dispatch_progress( $chunk );
				$body .= $chunk;
				return strlen( $chunk );
			},
		)
	);

	$result = curl_exec( $handle );
	$error  = curl_error( $handle );
	unset( $result );

	if ( '' !== $error ) {
		return new WP_Error( 'http_request_failed', $error );
	}

	return array(
		'response' => array( 'code' => $status ),
		'body'     => $body,
	);
}

function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['response']['code'] ) ? $response['response']['code'] : 0;
}

function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? $response['body'] : '';
}

require dirname( __DIR__ ) . '/igniter.php';
\Zactonz\AiProviderForOllama\zctz_load();

require __DIR__ . '/support/FakeHttp.php';
