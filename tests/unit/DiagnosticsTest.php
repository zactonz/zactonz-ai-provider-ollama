<?php

declare( strict_types=1 );

namespace Zactonz\AiProviderForOllama\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zactonz\AiProviderForOllama\Diagnostics\OllamaDiagnostics;
use Zactonz\AiProviderForOllama\Diagnostics\OllamaSiteHealth;

class DiagnosticsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['zctz_test_options'] = array(
			'zctz_ollama_ai_connector_settings' => array(
				'connection_type' => 'self_hosted',
				'model_text'      => 'qwen3',
				'model_embedding' => 'missing-embed',
			),
		);
		$GLOBALS['zctz_test_http_responses'] = array(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode( array( 'models' => array( array( 'name' => 'qwen3' ) ) ) ),
			),
			array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode( array( 'version' => '0.12.0' ) ),
			),
		);
	}

	public function test_report_is_redacted_and_flags_missing_defaults(): void {
		$report = ( new OllamaDiagnostics() )->run();

		$this->assertTrue( $report['connected'] );
		$this->assertSame( 1, $report['modelCount'] );
		$this->assertSame( '0.12.0', $report['ollamaVersion'] );
		$this->assertSame( array( 'embedding' => 'missing-embed' ), $report['missingDefaults'] );
		$this->assertArrayNotHasKey( 'apiKey', $report );
	}

	public function test_site_health_reports_a_good_connection(): void {
		$result = ( new OllamaSiteHealth() )->test_connection();

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( 'zctz_ollama_connection', $result['test'] );
	}
}
