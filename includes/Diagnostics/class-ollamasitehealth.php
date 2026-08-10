<?php
/**
 * WordPress Site Health integration.
 *
 * @package Zactonz\AiProviderForOllama\Diagnostics
 */

declare( strict_types=1 );

namespace Zactonz\AiProviderForOllama\Diagnostics;

use Zactonz\AiProviderForOllama\Settings\OllamaSettings;

/**
 * Registers Ollama status tests and redacted debug information.
 *
 * @since 1.1.0
 */
class OllamaSiteHealth {

	/**
	 * Registers Site Health hooks.
	 *
	 * @since 1.1.0
	 */
	public function init(): void {
		add_filter( 'site_status_tests', array( $this, 'register_tests' ) );
		add_filter( 'debug_information', array( $this, 'add_debug_information' ) );
	}

	/**
	 * Registers a direct connectivity test.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $tests Existing tests.
	 * @return array<string, mixed> Tests including Ollama.
	 */
	public function register_tests( array $tests ): array {
		$tests['direct']['zctz_ollama_connection'] = array(
			'label' => __( 'Ollama connection', 'zactonz-ai-provider-ollama' ),
			'test'  => array( $this, 'test_connection' ),
		);

		return $tests;
	}

	/**
	 * Runs the Site Health connection test.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed> Site Health test result.
	 */
	public function test_connection(): array {
		$report    = ( new OllamaDiagnostics() )->run();
		$connected = ! empty( $report['connected'] );

		return array(
			'label'       => $connected
				? __( 'Ollama is connected', 'zactonz-ai-provider-ollama' )
				: __( 'Ollama is not reachable', 'zactonz-ai-provider-ollama' ),
			'status'      => $connected ? 'good' : 'critical',
			'badge'       => array(
				'label' => __( 'AI', 'zactonz-ai-provider-ollama' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html(
				$connected
					? sprintf(
						/* translators: 1: Number of models. 2: Request latency in milliseconds. */
						__( 'The configured endpoint responded with %1$d models in %2$d ms.', 'zactonz-ai-provider-ollama' ),
						(int) $report['modelCount'],
						(int) $report['latencyMs']
					)
					: (string) $report['error']
			) . '</p>',
			'actions'     => sprintf(
				'<p><a href="%1$s">%2$s</a></p>',
				esc_url( admin_url( 'options-general.php?page=zactonz-ai-provider-ollama' ) ),
				esc_html__( 'Review Ollama settings', 'zactonz-ai-provider-ollama' )
			),
			'test'        => 'zctz_ollama_connection',
		);
	}

	/**
	 * Adds non-secret Ollama details to Site Health information.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $info Existing debug information.
	 * @return array<string, mixed> Debug information including Ollama.
	 */
	public function add_debug_information( array $info ): array {
		$defaults = OllamaSettings::get_preferred_models();
		$fields   = array(
			'connection_type' => array(
				'label' => __( 'Connection type', 'zactonz-ai-provider-ollama' ),
				'value' => OllamaSettings::get_connection_type(),
			),
			'endpoint'        => array(
				'label' => __( 'Endpoint', 'zactonz-ai-provider-ollama' ),
				'value' => OllamaSettings::get_host(),
			),
			'text_timeout'    => array(
				'label' => __( 'Text timeout', 'zactonz-ai-provider-ollama' ),
				'value' => OllamaSettings::get_text_request_timeout() . 's',
			),
		);
		foreach ( $defaults as $capability => $model_id ) {
			$fields[ 'default_' . $capability ] = array(
				'label' => sprintf(
					/* translators: %s: Capability name. */
					__( 'Default %s model', 'zactonz-ai-provider-ollama' ),
					$capability
				),
				'value' => '' !== $model_id ? $model_id : __( 'Automatic', 'zactonz-ai-provider-ollama' ),
			);
		}

		$info['zctz_ollama'] = array(
			'label'  => __( 'Zactonz AI Connector: Ollama', 'zactonz-ai-provider-ollama' ),
			'fields' => $fields,
		);

		return $info;
	}
}
