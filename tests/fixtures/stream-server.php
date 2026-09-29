<?php
/**
 * Deterministic SSE endpoint for the streaming integration test.
 */

header( 'Content-Type: text/event-stream' );
header( 'Cache-Control: no-cache' );

$request = json_decode( file_get_contents( 'php://input' ), true );
if ( isset( $request['model'] ) && 'error-model' === $request['model'] ) {
	http_response_code( 503 );
	header( 'Content-Type: application/json' );
	echo json_encode( array( 'error' => 'Model is unavailable.' ) );
	return;
}

if ( isset( $request['model'] ) && 'unterminated-model' === $request['model'] ) {
	// A stream whose final block is not closed by a blank line, so it lands in the
	// trailing buffer the model parses after the transfer.
	echo 'data: ', json_encode(
		array( 'id' => 'u', 'choices' => array( array( 'delta' => array( 'content' => 'FIRST ' ) ) ) )
	), "\n\n";
	echo 'data: ', json_encode(
		array( 'id' => 'u', 'choices' => array( array( 'delta' => array( 'content' => 'TRAILING' ), 'finish_reason' => 'stop' ) ) )
	);
	return;
}

if ( isset( $request['model'] ) && 'tool-model' === $request['model'] ) {
	$chunks = array(
		array(
			'id'      => 'tool-stream-test',
			'choices' => array(
				array(
					'delta'         => array(
						'tool_calls' => array(
							array(
								'index'    => 0,
								'id'       => 'call-1',
								'function' => array( 'name' => 'lookup', 'arguments' => '{"query":' ),
							),
						),
					),
					'finish_reason' => null,
				),
			),
		),
		array(
			'id'      => 'tool-stream-test',
			'choices' => array(
				array(
					'delta'         => array(
						'tool_calls' => array(
							array( 'index' => 0, 'function' => array( 'arguments' => '"WordPress"}' ) ),
						),
					),
					'finish_reason' => null,
				),
			),
		),
		array(
			'id'      => 'tool-stream-test',
			'choices' => array( array( 'delta' => array(), 'finish_reason' => 'tool_calls' ) ),
		),
	);
} else {
	$chunks = array(
	array(
		'id'      => 'stream-test',
		'choices' => array( array( 'delta' => array( 'role' => 'assistant', 'reasoning_content' => 'Checking. ' ), 'finish_reason' => null ) ),
	),
	array(
		'id'      => 'stream-test',
		'choices' => array( array( 'delta' => array( 'content' => 'Hello ' ), 'finish_reason' => null ) ),
	),
	array(
		'id'      => 'stream-test',
		'choices' => array( array( 'delta' => array( 'content' => 'WordPress.' ), 'finish_reason' => null ) ),
	),
	array(
		'id'      => 'stream-test',
		'choices' => array( array( 'delta' => array(), 'finish_reason' => 'stop' ) ),
		'usage'   => array( 'prompt_tokens' => 3, 'completion_tokens' => 4, 'total_tokens' => 7 ),
	),
);
}

foreach ( $chunks as $chunk ) {
	echo 'data: ' . json_encode( $chunk ) . "\n\n";
}
echo "data: [DONE]\n\n";
