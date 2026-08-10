<?php
/**
 * Test HTTP doubles.
 */

declare( strict_types=1 );

namespace Zactonz\AiProviderForOllama\Tests\Support;

use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;

class FakeHttpTransporter implements HttpTransporterInterface {
	/** @var list<Response> */
	private $responses;

	/** @var list<Request> */
	public $requests = array();

	/** @param list<Response> $responses */
	public function __construct( array $responses ) {
		$this->responses = array_values( $responses );
	}

	public function send( Request $request, ?RequestOptions $options = null ): Response {
		unset( $options );
		$this->requests[] = $request;
		if ( empty( $this->responses ) ) {
			throw new \RuntimeException( 'No fake HTTP response remains.' );
		}
		return array_shift( $this->responses );
	}
}

class PassthroughAuthentication implements RequestAuthenticationInterface {
	public function authenticateRequest( Request $request ): Request {
		return $request;
	}

	public static function getJsonSchema(): array {
		return array( 'type' => 'object' );
	}
}
