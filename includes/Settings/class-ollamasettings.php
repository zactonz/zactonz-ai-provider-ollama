<?php
/**
 * WordPress admin settings for the Ollama provider.
 *
 * @package Zactonz\AiProviderForOllama\Settings
 */

declare( strict_types=1 );

namespace Zactonz\AiProviderForOllama\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;
use Zactonz\AiProviderForOllama\Diagnostics\OllamaDiagnostics;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Class for the Ollama settings in the WordPress admin.
 *
 * Provides a settings page under Settings > Ollama for configuring the Ollama
 * host URL.
 *
 * @since 1.0.0
 */
class OllamaSettings {

	private const OPTION_GROUP                   = 'zctz_ollama_ai_connector_settings';
	private const OPTION_NAME                    = 'zctz_ollama_ai_connector_settings';
	private const LEGACY_OPTION_NAME             = 'zactonz_ollama_ai_connector_settings';
	private const PAGE_SLUG                      = 'zactonz-ai-provider-ollama';
	private const SECTION_ID                     = 'zctz_ollama_ai_connector_main';
	private const CLOUD_API_KEY_OPTION           = 'zctz_ollama_ai_connector_cloud_api_key';
	private const SELF_HOSTED_API_KEY_OPTION     = 'zctz_ollama_ai_connector_self_hosted_api_key';
	private const AJAX_ACTION                    = 'zctz_ollama_ai_connector_list_models';
	private const AJAX_MODEL_CAPABILITIES_ACTION = 'zctz_ollama_ai_connector_model_capabilities';
	private const AJAX_SAVE_CONNECTION_ACTION    = 'zctz_ollama_ai_connector_save_connection';
	private const AJAX_DIAGNOSTICS_ACTION        = 'zctz_ollama_ai_connector_diagnostics';
	private const NONCE_ACTION                   = 'zctz_ollama_ai_connector_nonce';
	private const CONNECTION_CLOUD               = 'cloud';
	private const CONNECTION_SELF_HOSTED         = 'self_hosted';
	private const THINKING_DEFAULT               = 'default';
	private const THINKING_ENABLED               = 'enabled';
	private const THINKING_DISABLED              = 'disabled';
	private const THINKING_LOW                   = 'low';
	private const THINKING_MEDIUM                = 'medium';
	private const THINKING_HIGH                  = 'high';

	/**
	 * Initializes the settings.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'maybe_migrate_legacy_settings' ), 5 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_screen' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_script' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_connector_script' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_list_models' ) );
		add_action( 'wp_ajax_' . self::AJAX_MODEL_CAPABILITIES_ACTION, array( $this, 'ajax_model_capabilities' ) );
		add_action( 'wp_ajax_' . self::AJAX_SAVE_CONNECTION_ACTION, array( $this, 'ajax_save_connection' ) );
		add_action( 'wp_ajax_' . self::AJAX_DIAGNOSTICS_ACTION, array( $this, 'ajax_diagnostics' ) );
		add_filter( 'wpai_has_ai_credentials', array( $this, 'is_connected' ) );
		add_filter( 'wpai_is_ollama_connector_configured', array( $this, 'is_connected' ) );
		add_filter( 'wpai_preferred_text_models', array( $this, 'prepend_default_text_model_preference' ) );
		add_filter( 'wpai_preferred_image_models', array( $this, 'prepend_default_image_model_preference' ) );
		add_filter( 'wpai_preferred_vision_models', array( $this, 'prepend_default_vision_model_preference' ) );
		add_filter( 'wpai_preferred_embedding_models', array( $this, 'prepend_default_embedding_model_preference' ) );
		add_filter( 'wpai_preferred_tool_models', array( $this, 'prepend_default_tools_model_preference' ) );
	}

	/**
	 * Migrates older Zactonz option names and refreshes request authentication.
	 *
	 * @since 1.0.0
	 */
	public function maybe_migrate_legacy_settings(): void {
		$new_settings = get_option( self::OPTION_NAME, false );
		if ( false === $new_settings ) {
			$legacy_settings = get_option( self::LEGACY_OPTION_NAME, false );
			if ( is_array( $legacy_settings ) ) {
				update_option( self::OPTION_NAME, $legacy_settings, false );
			}
		}

		$current_settings = get_option( self::OPTION_NAME, array() );
		if ( is_array( $current_settings ) ) {
			$changed = false;
			if ( ! isset( $current_settings['model_text'] ) && ! empty( $current_settings['model'] ) ) {
				$current_settings['model_text'] = (string) $current_settings['model'];
				$changed                        = true;
			}
			if ( isset( $current_settings['thinking'] ) && self::THINKING_ENABLED === $current_settings['thinking'] ) {
				$current_settings['thinking'] = self::THINKING_MEDIUM;
				$changed                      = true;
			}
			if ( $changed ) {
				update_option( self::OPTION_NAME, $current_settings, false );
			}
		}

		$this->set_active_request_authentication();
	}

	/**
	 * Registers the setting and settings fields.
	 *
	 * @since 1.0.0
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			self::SECTION_ID,
			'',
			'__return_empty_string',
			self::PAGE_SLUG
		);

		add_settings_field(
			self::OPTION_NAME . '_connection_type',
			__( 'Connection', 'zactonz-ai-provider-ollama' ),
			array( $this, 'render_connection_type_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array( 'label_for' => self::OPTION_NAME . '-connection-self-hosted' )
		);

		add_settings_field(
			self::OPTION_NAME . '_host',
			__( 'Host URL or IP', 'zactonz-ai-provider-ollama' ),
			array( $this, 'render_host_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array(
				'label_for' => self::OPTION_NAME . '-host',
				'class'     => 'zactonz-ai-provider-ollama-self-hosted-row',
			)
		);

		add_settings_field(
			self::OPTION_NAME . '_port',
			__( 'Port', 'zactonz-ai-provider-ollama' ),
			array( $this, 'render_port_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array(
				'label_for' => self::OPTION_NAME . '-port',
				'class'     => 'zactonz-ai-provider-ollama-self-hosted-row',
			)
		);

		add_settings_field(
			self::OPTION_NAME . '_cloud_api_key',
			__( 'Cloud API Key', 'zactonz-ai-provider-ollama' ),
			array( $this, 'render_cloud_api_key_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array(
				'label_for' => self::OPTION_NAME . '-cloud-api-key',
				'class'     => 'zactonz-ai-provider-ollama-cloud-row',
			)
		);

		add_settings_field(
			self::OPTION_NAME . '_self_hosted_api_key',
			__( 'Self-hosted API Key', 'zactonz-ai-provider-ollama' ),
			array( $this, 'render_self_hosted_api_key_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array(
				'label_for' => self::OPTION_NAME . '-self-hosted-api-key',
				'class'     => 'zactonz-ai-provider-ollama-self-hosted-row',
			)
		);

		$model_fields = array(
			'text'      => __( 'Default Text Model', 'zactonz-ai-provider-ollama' ),
			'vision'    => __( 'Default Vision Model', 'zactonz-ai-provider-ollama' ),
			'image'     => __( 'Default Image Model', 'zactonz-ai-provider-ollama' ),
			'embedding' => __( 'Default Embedding Model', 'zactonz-ai-provider-ollama' ),
			'tools'     => __( 'Default Tool Model', 'zactonz-ai-provider-ollama' ),
		);
		foreach ( $model_fields as $capability => $label ) {
			add_settings_field(
				self::OPTION_NAME . '_model_' . $capability,
				$label,
				array( $this, 'render_capability_model_field' ),
				self::PAGE_SLUG,
				self::SECTION_ID,
				array(
					'capability' => $capability,
					'label_for'  => self::OPTION_NAME . '-model-' . $capability,
				)
			);
		}

		add_settings_field(
			self::OPTION_NAME . '_thinking',
			__( 'Thinking', 'zactonz-ai-provider-ollama' ),
			array( $this, 'render_thinking_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array( 'label_for' => self::OPTION_NAME . '-thinking' )
		);

		add_settings_field(
			self::OPTION_NAME . '_embedding_request_timeout',
			__( 'Embedding Request Timeout', 'zactonz-ai-provider-ollama' ),
			array( $this, 'render_embedding_request_timeout_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array( 'label_for' => self::OPTION_NAME . '-embedding-request-timeout' )
		);

		add_settings_field(
			self::OPTION_NAME . '_diagnostics',
			__( 'Diagnostics', 'zactonz-ai-provider-ollama' ),
			array( $this, 'render_diagnostics_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID
		);

		add_settings_field(
			self::OPTION_NAME . '_request_timeout',
			__( 'Text Request Timeout', 'zactonz-ai-provider-ollama' ),
			array( $this, 'render_request_timeout_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array(
				'label_for' => self::OPTION_NAME . '-request-timeout',
				'class'     => 'zactonz-ai-provider-ollama-self-hosted-row',
			)
		);

		add_settings_field(
			self::OPTION_NAME . '_model',
			__( 'Available Models', 'zactonz-ai-provider-ollama' ),
			array( $this, 'render_available_models_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array( 'label_for' => self::OPTION_NAME . '-model' )
		);
	}

	/**
	 * Registers the settings screen.
	 *
	 * @since 1.0.0
	 */
	public function register_settings_screen(): void {
		add_options_page(
			__( 'Ollama Settings', 'zactonz-ai-provider-ollama' ),
			__( 'Ollama', 'zactonz-ai-provider-ollama' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_screen' )
		);
	}

	/**
	 * Sanitizes the settings array.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The input value.
	 * @return array<string, mixed> The sanitized settings.
	 */
	public function sanitize_settings( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$existing = self::get_settings();

		$connection_type = isset( $value['connection_type'] )
			? sanitize_key( (string) $value['connection_type'] )
			: self::CONNECTION_SELF_HOSTED;
		if ( self::CONNECTION_CLOUD !== $connection_type ) {
			$connection_type = self::CONNECTION_SELF_HOSTED;
		}

		$host = isset( $value['host'] ) ? trim( (string) $value['host'] ) : '';
		if ( '' !== $host ) {
			if ( ! preg_match( '#^[a-z][a-z0-9+\-.]*://#i', $host ) ) {
				$host = 'http://' . $host;
			}
			$host = rtrim( esc_url_raw( $host ), '/' );
		}

		$port = isset( $value['port'] ) ? trim( (string) $value['port'] ) : '';
		if ( '' !== $port ) {
			$port = (string) absint( $port );
			if ( '' === $port || (int) $port < 1 || (int) $port > 65535 ) {
				add_settings_error(
					self::OPTION_NAME,
					'zctz_ollama_ai_connector_invalid_port',
					__( 'Use an Ollama port between 1 and 65535.', 'zactonz-ai-provider-ollama' )
				);
				$port = '';
			}
		}

		$thinking = isset( $value['thinking'] )
			? sanitize_key( (string) $value['thinking'] )
			: ( isset( $existing['thinking'] ) ? sanitize_key( (string) $existing['thinking'] ) : self::THINKING_DEFAULT );
		if ( self::THINKING_ENABLED === $thinking ) {
			$thinking = self::THINKING_MEDIUM;
		}
		if ( ! in_array( $thinking, array( self::THINKING_DEFAULT, self::THINKING_DISABLED, self::THINKING_LOW, self::THINKING_MEDIUM, self::THINKING_HIGH ), true ) ) {
			$thinking = self::THINKING_DEFAULT;
		}

		$request_timeout = isset( $value['request_timeout'] ) ? trim( (string) $value['request_timeout'] ) : '';
		if ( '' !== $request_timeout ) {
			$request_timeout = (string) absint( $request_timeout );
			if ( '' === $request_timeout || (int) $request_timeout < 15 || (int) $request_timeout > 1800 ) {
				add_settings_error(
					self::OPTION_NAME,
					'zctz_ollama_ai_connector_invalid_request_timeout',
					__( 'Use a text request timeout between 15 and 1800 seconds.', 'zactonz-ai-provider-ollama' )
				);
				$request_timeout = '';
			}
		}

		$embedding_request_timeout = isset( $value['embedding_request_timeout'] )
			? trim( (string) $value['embedding_request_timeout'] )
			: ( isset( $existing['embedding_request_timeout'] ) ? trim( (string) $existing['embedding_request_timeout'] ) : '' );
		if ( '' !== $embedding_request_timeout ) {
			$embedding_request_timeout = (string) absint( $embedding_request_timeout );
			if ( '' === $embedding_request_timeout || (int) $embedding_request_timeout < 5 || (int) $embedding_request_timeout > 1800 ) {
				add_settings_error(
					self::OPTION_NAME,
					'zctz_ollama_ai_connector_invalid_embedding_request_timeout',
					__( 'Use an embedding request timeout between 5 and 1800 seconds.', 'zactonz-ai-provider-ollama' )
				);
				$embedding_request_timeout = '';
			}
		}

		$this->save_api_keys( $value, $connection_type );

		$sanitized = array(
			'connection_type'           => $connection_type,
			'host'                      => $host,
			'port'                      => $port,
			'thinking'                  => $thinking,
			'request_timeout'           => $request_timeout,
			'embedding_request_timeout' => $embedding_request_timeout,
		);

		foreach ( array( 'text', 'vision', 'image', 'embedding', 'tools' ) as $capability ) {
			$key               = 'model_' . $capability;
			$sanitized[ $key ] = isset( $value[ $key ] )
				? sanitize_text_field( (string) $value[ $key ] )
				: ( isset( $existing[ $key ] ) ? sanitize_text_field( (string) $existing[ $key ] ) : '' );
		}
		if ( '' === $sanitized['model_text'] && ! isset( $value['model_text'] ) && isset( $existing['model'] ) ) {
			$sanitized['model_text'] = sanitize_text_field( (string) $existing['model'] );
		}
		// Keep the legacy key synchronized for integrations that still read it.
		$sanitized['model'] = $sanitized['model_text'];

		return $sanitized;
	}

	/**
	 * Renders the settings screen.
	 *
	 * @since 1.0.0
	 */
	public function render_screen(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>

		<div class="wrap zactonz-ai-provider-ollama-settings-screen">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: 1: link to the AI Credentials screen, 2: closing link tag */
					esc_html__( 'Choose Ollama Cloud or a self-hosted Ollama endpoint. Cloud and self-hosted API keys are stored separately and can also be managed on the %1$sSettings > Connectors%2$s screen.', 'zactonz-ai-provider-ollama' ),
					'<a href="' . esc_url( admin_url( 'options-connectors.php' ) ) . '">',
					'</a>'
				);
				?>
			</p>
			<p>
				<?php
				printf(
					/* translators: 1: code tag, 2: closing code tag */
					esc_html__( 'Leave the host and port empty to use the default (%1$shttp://localhost:11434%2$s). You can also set the %1$sOLLAMA_HOST%2$s environment variable to override these settings.', 'zactonz-ai-provider-ollama' ),
					'<code>',
					'</code>'
				);
				?>
			</p>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>

		<?php
	}

	/**
	 * Renders the connection type selector.
	 *
	 * @since 1.0.0
	 */
	public function render_connection_type_field(): void {
		$connection_type = self::get_connection_type();
		?>

		<fieldset class="zactonz-ai-provider-ollama-connection-type">
			<label>
				<input
					type="radio"
					id="<?php echo esc_attr( self::OPTION_NAME . '-connection-self-hosted' ); ?>"
					name="<?php echo esc_attr( self::OPTION_NAME . '[connection_type]' ); ?>"
					value="<?php echo esc_attr( self::CONNECTION_SELF_HOSTED ); ?>"
					<?php checked( self::CONNECTION_SELF_HOSTED, $connection_type ); ?>
				/>
				<?php echo esc_html__( 'Self-hosted', 'zactonz-ai-provider-ollama' ); ?>
			</label>
			<label>
				<input
					type="radio"
					id="<?php echo esc_attr( self::OPTION_NAME . '-connection-cloud' ); ?>"
					name="<?php echo esc_attr( self::OPTION_NAME . '[connection_type]' ); ?>"
					value="<?php echo esc_attr( self::CONNECTION_CLOUD ); ?>"
					<?php checked( self::CONNECTION_CLOUD, $connection_type ); ?>
				/>
				<?php echo esc_html__( 'Ollama Cloud', 'zactonz-ai-provider-ollama' ); ?>
			</label>
		</fieldset>
		<p class="description">
			<?php echo esc_html__( 'Cloud connects to ollama.com and requires a valid API key. Self-hosted uses the URL and port below.', 'zactonz-ai-provider-ollama' ); ?>
		</p>

		<?php
	}

	/**
	 * Renders the host URL field.
	 *
	 * @since 1.0.0
	 */
	public function render_host_field(): void {
		$settings = self::get_settings();
		$value    = isset( $settings['host'] ) ? $settings['host'] : '';
		?>

		<input
			type="text"
			id="<?php echo esc_attr( self::OPTION_NAME . '-host' ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[host]' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="http://localhost"
		/>
		<p class="description">
			<?php
				printf(
					/* translators: 1: code tag, 2: closing code tag */
					esc_html__( 'The base URL or IP address of your self-hosted Ollama instance (without /v1). Example: %1$shttp://localhost%2$s or %1$s127.0.0.1%2$s.', 'zactonz-ai-provider-ollama' ),
					'<code>',
					'</code>'
				);
			?>
		</p>

		<?php
	}

	/**
	 * Renders the optional Ollama port field.
	 *
	 * @since 1.0.0
	 */
	public function render_port_field(): void {
		$settings = self::get_settings();
		$value    = isset( $settings['port'] ) ? $settings['port'] : '11434';
		?>

		<input
			type="number"
			id="<?php echo esc_attr( self::OPTION_NAME . '-port' ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[port]' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="small-text"
			min="1"
			max="65535"
			step="1"
			placeholder="11434"
		/>
		<p class="description">
			<?php echo esc_html__( 'Leave blank when the host URL already includes a port or the endpoint uses its standard URL port.', 'zactonz-ai-provider-ollama' ); ?>
		</p>

		<?php
	}

	/**
	 * Renders the Ollama Cloud API key field.
	 *
	 * @since 1.0.0
	 */
	public function render_cloud_api_key_field(): void {
		$this->render_api_key_field(
			self::CONNECTION_CLOUD,
			self::OPTION_NAME . '-cloud-api-key',
			self::OPTION_NAME . '[cloud_api_key]',
			self::OPTION_NAME . '[clear_cloud_api_key]',
			__( 'Enter Ollama Cloud API key', 'zactonz-ai-provider-ollama' ),
			__( 'Required for Ollama Cloud. Leave blank to keep the saved Cloud API key.', 'zactonz-ai-provider-ollama' ),
			__( 'Remove saved Cloud API key', 'zactonz-ai-provider-ollama' )
		);
	}

	/**
	 * Renders the self-hosted Ollama API key field.
	 *
	 * @since 1.0.0
	 */
	public function render_self_hosted_api_key_field(): void {
		$this->render_api_key_field(
			self::CONNECTION_SELF_HOSTED,
			self::OPTION_NAME . '-self-hosted-api-key',
			self::OPTION_NAME . '[self_hosted_api_key]',
			self::OPTION_NAME . '[clear_self_hosted_api_key]',
			__( 'Enter optional self-hosted API key', 'zactonz-ai-provider-ollama' ),
			__( 'Optional for protected self-hosted Ollama endpoints. Leave blank to keep the saved self-hosted API key.', 'zactonz-ai-provider-ollama' ),
			__( 'Remove saved self-hosted API key', 'zactonz-ai-provider-ollama' )
		);
	}

	/**
	 * Renders one mode-specific API key field.
	 *
	 * @since 1.0.0
	 *
	 * @param string $connection_type Connection type.
	 * @param string $field_id Field ID.
	 * @param string $field_name Field name.
	 * @param string $clear_name Clear checkbox field name.
	 * @param string $empty_placeholder Placeholder when no key is saved.
	 * @param string $description Field description.
	 * @param string $clear_label Clear checkbox label.
	 */
	private function render_api_key_field(
		string $connection_type,
		string $field_id,
		string $field_name,
		string $clear_name,
		string $empty_placeholder,
		string $description,
		string $clear_label
	): void {
		$has_api_key = self::has_saved_api_key_for_connection( $connection_type );
		?>

		<input
			type="password"
			id="<?php echo esc_attr( $field_id ); ?>"
			name="<?php echo esc_attr( $field_name ); ?>"
			value=""
			class="regular-text"
			autocomplete="new-password"
			placeholder="<?php echo esc_attr( $has_api_key ? __( 'Saved API key is hidden', 'zactonz-ai-provider-ollama' ) : $empty_placeholder ); ?>"
		/>
		<?php if ( $has_api_key ) : ?>
			<p class="description">
				<?php echo esc_html__( 'An API key is saved for this connection mode. Enter a new value to replace it.', 'zactonz-ai-provider-ollama' ); ?>
			</p>
		<?php endif; ?>
		<p class="description">
			<?php echo esc_html( $description ); ?>
		</p>
		<?php if ( $has_api_key ) : ?>
			<label>
				<input
					type="checkbox"
					name="<?php echo esc_attr( $clear_name ); ?>"
					value="1"
				/>
				<?php echo esc_html( $clear_label ); ?>
			</label>
		<?php endif; ?>

		<?php
	}

	/**
	 * Renders the preferred model selector.
	 *
	 * @since 1.0.0
	 */
	public function render_preferred_model_field(): void {
		$this->render_capability_model_field( array( 'capability' => 'text' ) );
	}

	/**
	 * Renders a capability-specific default model selector.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, string> $args Field arguments.
	 */
	public function render_capability_model_field( array $args ): void {
		$capability = isset( $args['capability'] ) && in_array( $args['capability'], array( 'text', 'vision', 'image', 'embedding', 'tools' ), true )
			? $args['capability']
			: 'text';
		$settings   = self::get_settings();
		$key        = 'model_' . $capability;
		$value      = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : ( 'text' === $capability && isset( $settings['model'] ) ? (string) $settings['model'] : '' );
		?>

		<select
			id="<?php echo esc_attr( self::OPTION_NAME . '-model-' . $capability ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>"
			class="zactonz-ai-provider-ollama-model-select"
			data-capability="<?php echo esc_attr( $capability ); ?>"
			data-selected="<?php echo esc_attr( $value ); ?>"
		>
			<option value=""><?php echo esc_html__( 'Automatic', 'zactonz-ai-provider-ollama' ); ?></option>
			<?php if ( '' !== $value ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" selected><?php echo esc_html( $value ); ?></option>
			<?php endif; ?>
		</select>
		<p class="description">
			<?php echo esc_html__( 'Only compatible models are offered. Automatic lets the WordPress AI Client choose the first available compatible model.', 'zactonz-ai-provider-ollama' ); ?>
		</p>

		<?php
	}

	/**
	 * Renders the thinking control for the preferred model.
	 *
	 * @since 1.0.0
	 */
	public function render_thinking_field(): void {
		$thinking = self::get_thinking_mode();
		?>

		<select
			id="<?php echo esc_attr( self::OPTION_NAME . '-thinking' ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[thinking]' ); ?>"
		>
			<option value="<?php echo esc_attr( self::THINKING_DEFAULT ); ?>" <?php selected( self::THINKING_DEFAULT, $thinking ); ?>>
				<?php echo esc_html__( 'Model default', 'zactonz-ai-provider-ollama' ); ?>
			</option>
			<option value="<?php echo esc_attr( self::THINKING_DISABLED ); ?>" <?php selected( self::THINKING_DISABLED, $thinking ); ?>>
				<?php echo esc_html__( 'No thinking', 'zactonz-ai-provider-ollama' ); ?>
			</option>
			<option value="<?php echo esc_attr( self::THINKING_LOW ); ?>" <?php selected( self::THINKING_LOW, $thinking ); ?>>
				<?php echo esc_html__( 'Low', 'zactonz-ai-provider-ollama' ); ?>
			</option>
			<option value="<?php echo esc_attr( self::THINKING_MEDIUM ); ?>" <?php selected( self::THINKING_MEDIUM, $thinking ); ?>>
				<?php echo esc_html__( 'Medium', 'zactonz-ai-provider-ollama' ); ?>
			</option>
			<option value="<?php echo esc_attr( self::THINKING_HIGH ); ?>" <?php selected( self::THINKING_HIGH, $thinking ); ?>>
				<?php echo esc_html__( 'High', 'zactonz-ai-provider-ollama' ); ?>
			</option>
		</select>
		<p class="description">
			<?php echo esc_html__( 'Applies to the default text model when it reports thinking support. Some models, including GPT-OSS, do not support disabling thinking.', 'zactonz-ai-provider-ollama' ); ?>
		</p>
		<p class="description" id="<?php echo esc_attr( self::OPTION_NAME . '-thinking-support' ); ?>"></p>

		<?php
	}

	/**
	 * Renders the text request timeout field.
	 *
	 * @since 1.0.0
	 */
	public function render_request_timeout_field(): void {
		$settings = self::get_settings();
		$value    = isset( $settings['request_timeout'] ) && '' !== $settings['request_timeout']
			? $settings['request_timeout']
			: '180';
		?>

		<input
			type="number"
			id="<?php echo esc_attr( self::OPTION_NAME . '-request-timeout' ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[request_timeout]' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="small-text"
			min="15"
			max="1800"
			step="1"
		/>
		<span><?php echo esc_html__( 'seconds', 'zactonz-ai-provider-ollama' ); ?></span>
		<p class="description">
			<?php echo esc_html__( 'Thinking models and slow local hardware may need longer than the old 60-second request window. Per-request ollama.request_timeout custom options still override this value.', 'zactonz-ai-provider-ollama' ); ?>
		</p>

		<?php
	}

	/**
	 * Renders the embedding request timeout field.
	 *
	 * @since 1.1.0
	 */
	public function render_embedding_request_timeout_field(): void {
		$settings = self::get_settings();
		$value    = isset( $settings['embedding_request_timeout'] ) && '' !== $settings['embedding_request_timeout']
			? $settings['embedding_request_timeout']
			: '60';
		?>
		<input
			type="number"
			id="<?php echo esc_attr( self::OPTION_NAME . '-embedding-request-timeout' ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[embedding_request_timeout]' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="small-text"
			min="5"
			max="1800"
			step="1"
		/>
		<span><?php echo esc_html__( 'seconds', 'zactonz-ai-provider-ollama' ); ?></span>
		<p class="description"><?php echo esc_html__( 'Used for single and batch embedding requests.', 'zactonz-ai-provider-ollama' ); ?></p>
		<?php
	}

	/**
	 * Renders the on-demand diagnostics panel.
	 *
	 * @since 1.1.0
	 */
	public function render_diagnostics_field(): void {
		?>
		<button type="button" class="button" id="zctz-ollama-run-diagnostics">
			<?php echo esc_html__( 'Run diagnostics', 'zactonz-ai-provider-ollama' ); ?>
		</button>
		<span class="spinner" id="zctz-ollama-diagnostics-spinner"></span>
		<div id="zctz-ollama-diagnostics-results" aria-live="polite"></div>
		<p class="description"><?php echo esc_html__( 'Checks the endpoint, latency, installed models, selected defaults, embedding compatibility, and streaming support. API keys are never displayed.', 'zactonz-ai-provider-ollama' ); ?></p>
		<?php
	}

	/**
	 * Renders the available models list.
	 *
	 * @since 1.0.0
	 */
	public function render_available_models_field(): void {
		?>

		<div id="ollama-models-container">
			<span id="ollama-model-status"></span>
		</div>
		<p class="description">
			<?php
			echo esc_html__( 'Available models are fetched from your Ollama instance. If a model is not listed that you want, ensure that model is installed within Ollama.', 'zactonz-ai-provider-ollama' );
			?>
		</p>

		<?php
	}

	/**
	 * Enqueues the settings page script.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_settings_script( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$plugin_dir  = ZCTZ_OLLAMA_AI_CONNECTOR_PLUGIN_DIR;
		$script_path = $plugin_dir . 'admin/settings.js';
		$style_path  = $plugin_dir . 'admin/style-settings.css';
		$version     = file_exists( $script_path ) ? (string) filemtime( $script_path ) : false;

		wp_enqueue_script(
			'zactonz-ai-provider-ollama-settings',
			plugins_url( 'admin/settings.js', ZCTZ_OLLAMA_AI_CONNECTOR_PLUGIN_FILE ),
			array( 'wp-api-fetch', 'wp-i18n' ),
			$version,
			true
		);

		wp_enqueue_style(
			'zactonz-ai-provider-ollama-settings',
			plugins_url( 'admin/style-settings.css', ZCTZ_OLLAMA_AI_CONNECTOR_PLUGIN_FILE ),
			array(),
			file_exists( $style_path ) ? (string) filemtime( $style_path ) : false
		);
		wp_style_add_data( 'zactonz-ai-provider-ollama-settings', 'rtl', 'replace' );

		$models_ajax_url       = add_query_arg(
			array(
				'action'   => self::AJAX_ACTION,
				'_wpnonce' => wp_create_nonce( self::NONCE_ACTION ),
			),
			admin_url( 'admin-ajax.php' )
		);
		$capabilities_ajax_url = add_query_arg(
			array(
				'action'   => self::AJAX_MODEL_CAPABILITIES_ACTION,
				'_wpnonce' => wp_create_nonce( self::NONCE_ACTION ),
			),
			admin_url( 'admin-ajax.php' )
		);
		$diagnostics_ajax_url  = add_query_arg(
			array(
				'action'   => self::AJAX_DIAGNOSTICS_ACTION,
				'_wpnonce' => wp_create_nonce( self::NONCE_ACTION ),
			),
			admin_url( 'admin-ajax.php' )
		);
		$localized_settings    = array(
			'ajaxUrl'             => esc_url_raw( $models_ajax_url ),
			'capabilitiesAjaxUrl' => esc_url_raw( $capabilities_ajax_url ),
			'diagnosticsAjaxUrl'  => esc_url_raw( $diagnostics_ajax_url ),
			'thinkingStrings'     => array(
				'checking'    => __( 'Checking thinking support for this model...', 'zactonz-ai-provider-ollama' ),
				'chooseModel' => __( 'Choose a default model before setting its thinking behavior.', 'zactonz-ai-provider-ollama' ),
				'supported'   => __( 'This default model reports thinking support.', 'zactonz-ai-provider-ollama' ),
				'unsupported' => __( 'This default model does not report thinking support.', 'zactonz-ai-provider-ollama' ),
			),
		);

		wp_localize_script(
			'zactonz-ai-provider-ollama-settings',
			'zctzOllamaAiConnectorSettings',
			$localized_settings
		);
		wp_localize_script(
			'zactonz-ai-provider-ollama-settings',
			'zactonzOllamaAiConnectorSettings',
			$localized_settings
		);
	}

	/**
	 * Enqueues the Ollama controls layered into the WordPress Connectors screen.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_connector_script( string $hook_suffix ): void {
		if ( 'options-connectors.php' !== $hook_suffix ) {
			return;
		}

		$plugin_dir  = ZCTZ_OLLAMA_AI_CONNECTOR_PLUGIN_DIR;
		$script_path = $plugin_dir . 'admin/connector-settings.js';
		$style_path  = $plugin_dir . 'admin/style-connector-settings.css';
		$version     = file_exists( $script_path ) ? (string) filemtime( $script_path ) : false;
		$settings    = self::get_settings();

		wp_enqueue_script(
			'zactonz-ai-provider-ollama-connector-settings',
			plugins_url( 'admin/connector-settings.js', ZCTZ_OLLAMA_AI_CONNECTOR_PLUGIN_FILE ),
			array(),
			$version,
			true
		);

		wp_enqueue_style(
			'zactonz-ai-provider-ollama-connector-settings',
			plugins_url( 'admin/style-connector-settings.css', ZCTZ_OLLAMA_AI_CONNECTOR_PLUGIN_FILE ),
			array(),
			file_exists( $style_path ) ? (string) filemtime( $style_path ) : false
		);

		$connector_settings = array(
			'ajaxUrl'             => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'action'              => self::AJAX_SAVE_CONNECTION_ACTION,
			'nonce'               => wp_create_nonce( self::NONCE_ACTION ),
			'connectionType'      => self::get_connection_type(),
			'host'                => isset( $settings['host'] ) ? $settings['host'] : '',
			'port'                => isset( $settings['port'] ) ? $settings['port'] : '11434',
			'requestTimeout'      => isset( $settings['request_timeout'] ) && '' !== $settings['request_timeout'] ? $settings['request_timeout'] : '180',
			'hasCloudApiKey'      => self::has_saved_api_key_for_connection( self::CONNECTION_CLOUD ),
			'hasSelfHostedApiKey' => self::has_saved_api_key_for_connection( self::CONNECTION_SELF_HOSTED ),
			'strings'             => array(
				'cloud'                  => __( 'Ollama Cloud', 'zactonz-ai-provider-ollama' ),
				'cloudApiKey'            => __( 'Cloud API key', 'zactonz-ai-provider-ollama' ),
				'cloudApiKeyHelp'        => __( 'Required for Ollama Cloud. Leave blank to keep the saved Cloud API key.', 'zactonz-ai-provider-ollama' ),
				'cloudApiKeySaved'       => __( 'A Cloud API key is saved. Enter a new key to replace it.', 'zactonz-ai-provider-ollama' ),
				'cloudHelp'              => __( 'Cloud connects to ollama.com and uses only the Cloud API key below.', 'zactonz-ai-provider-ollama' ),
				'connection'             => __( 'Connection', 'zactonz-ai-provider-ollama' ),
				'enterCloudApiKey'       => __( 'Enter Ollama Cloud API key', 'zactonz-ai-provider-ollama' ),
				'enterSelfHostedApiKey'  => __( 'Enter optional self-hosted API key', 'zactonz-ai-provider-ollama' ),
				'host'                   => __( 'Host URL or IP', 'zactonz-ai-provider-ollama' ),
				'hostPlaceholder'        => __( 'http://localhost', 'zactonz-ai-provider-ollama' ),
				'port'                   => __( 'Port', 'zactonz-ai-provider-ollama' ),
				'removeCloudApiKey'      => __( 'Remove saved Cloud API key', 'zactonz-ai-provider-ollama' ),
				'removeSelfHostedApiKey' => __( 'Remove saved self-hosted API key', 'zactonz-ai-provider-ollama' ),
				'requestTimeout'         => __( 'Text request timeout', 'zactonz-ai-provider-ollama' ),
				'requestTimeoutHelp'     => __( 'Seconds to wait for Ollama text responses. Slower local or thinking models may need more time.', 'zactonz-ai-provider-ollama' ),
				'savedApiKey'            => __( 'Saved API key is hidden', 'zactonz-ai-provider-ollama' ),
				'seconds'                => __( 'seconds', 'zactonz-ai-provider-ollama' ),
				'save'                   => __( 'Save and check connection', 'zactonz-ai-provider-ollama' ),
				'saving'                 => __( 'Checking...', 'zactonz-ai-provider-ollama' ),
				'selfHosted'             => __( 'Self-hosted', 'zactonz-ai-provider-ollama' ),
				'selfHostedApiKey'       => __( 'Self-hosted API key', 'zactonz-ai-provider-ollama' ),
				'selfHostedApiKeyHelp'   => __( 'Optional for protected self-hosted Ollama endpoints. Leave blank to keep the saved self-hosted API key.', 'zactonz-ai-provider-ollama' ),
				'selfHostedApiKeySaved'  => __( 'A self-hosted API key is saved. Enter a new key to replace it.', 'zactonz-ai-provider-ollama' ),
				'selfHostedHelp'         => __( 'Self-hosted uses the host, port, optional self-hosted API key, and timeout below.', 'zactonz-ai-provider-ollama' ),
				'updated'                => __( 'Connection settings saved. Refreshing connector status.', 'zactonz-ai-provider-ollama' ),
				'unexpectedResponse'     => __( 'WordPress returned an unexpected connection response.', 'zactonz-ai-provider-ollama' ),
			),
		);

		wp_localize_script(
			'zactonz-ai-provider-ollama-connector-settings',
			'zctzOllamaAiConnectorConnectorSettings',
			$connector_settings
		);
		wp_localize_script(
			'zactonz-ai-provider-ollama-connector-settings',
			'zactonzOllamaAiConnectorConnectorSettings',
			$connector_settings
		);
	}

	/**
	 * Handles the AJAX request to list available Ollama models.
	 *
	 * @since 1.0.0
	 */
	public function ajax_list_models(): void {
		check_ajax_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'zactonz-ai-provider-ollama' ), 403 );
		}

		$models = $this->get_models();

		if ( is_wp_error( $models ) ) {
			wp_send_json_error( $models->get_error_message(), $models->get_error_code() );
		}

		$descriptors = array();
		$registry    = AiClient::defaultRegistry();
		$provider    = $registry->getProviderClassName( 'ollama' );
		$directory   = $provider::modelMetadataDirectory();
		foreach ( $models as $model ) {
			$model_data = method_exists( $model, 'toArray' ) ? $model->toArray() : array();
			if ( ! isset( $model_data['id'] ) || ! is_string( $model_data['id'] ) ) {
				continue;
			}
			if ( method_exists( $directory, 'getModelDescriptor' ) ) {
				$model_data = array_merge( $model_data, $directory->getModelDescriptor( $model_data['id'] ) );
			}
			$descriptors[] = $model_data;
		}

		wp_send_json_success( $descriptors );
	}

	/**
	 * Handles the AJAX request to check a model's Ollama capabilities.
	 *
	 * @since 1.0.0
	 */
	public function ajax_model_capabilities(): void {
		check_ajax_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'zactonz-ai-provider-ollama' ), 403 );
		}

		$model_name = isset( $_GET['model'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['model'] ) ) : '';
		if ( '' === $model_name ) {
			wp_send_json_error( __( 'Choose an Ollama model first.', 'zactonz-ai-provider-ollama' ), 400 );
		}

		if ( ! class_exists( AiClient::class ) ) {
			wp_send_json_error( __( 'The WordPress AI Client is not available.', 'zactonz-ai-provider-ollama' ), 404 );
		}

		$registry = AiClient::defaultRegistry();
		if ( ! $registry->hasProvider( 'ollama' ) ) {
			wp_send_json_error( __( 'AI provider not found.', 'zactonz-ai-provider-ollama' ), 404 );
		}

		try {
			$provider_classname       = $registry->getProviderClassName( 'ollama' );
			$model_metadata_directory = $provider_classname::modelMetadataDirectory();
			$capabilities             = method_exists( $model_metadata_directory, 'getModelCapabilities' )
				? $model_metadata_directory->getModelCapabilities( $model_name )
				: array();
			$descriptor               = method_exists( $model_metadata_directory, 'getModelDescriptor' )
				? $model_metadata_directory->getModelDescriptor( $model_name )
				: array();

			wp_send_json_success(
				array(
					'thinking'     => isset( $descriptor['features']['thinking'] )
						? (bool) $descriptor['features']['thinking']
						: in_array( 'thinking', $capabilities, true ),
					'capabilities' => $capabilities,
					'features'     => isset( $descriptor['features'] ) ? $descriptor['features'] : array(),
				)
			);
		} catch ( \Throwable $e ) {
			wp_send_json_error( __( 'Could not check thinking support for this Ollama model.', 'zactonz-ai-provider-ollama' ), 500 );
		}
	}

	/**
	 * Runs redacted connection diagnostics for an administrator.
	 *
	 * @since 1.1.0
	 */
	public function ajax_diagnostics(): void {
		check_ajax_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'zactonz-ai-provider-ollama' ), 403 );
		}

		wp_send_json_success( ( new OllamaDiagnostics() )->run() );
	}

	/**
	 * Saves connection settings from the Connectors screen and verifies them.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_connection(): void {
		check_ajax_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'zactonz-ai-provider-ollama' ), 403 );
		}

		$settings                              = self::get_settings();
		$settings['connection_type']           = isset( $_POST['connection_type'] )
			? sanitize_key( wp_unslash( $_POST['connection_type'] ) )
			: self::CONNECTION_SELF_HOSTED;
		$settings['host']                      = isset( $_POST['host'] )
			? sanitize_text_field( wp_unslash( $_POST['host'] ) )
			: '';
		$settings['port']                      = isset( $_POST['port'] )
			? sanitize_text_field( wp_unslash( $_POST['port'] ) )
			: '';
		$settings['request_timeout']           = isset( $_POST['request_timeout'] )
			? sanitize_text_field( wp_unslash( $_POST['request_timeout'] ) )
			: '';
		$settings['cloud_api_key']             = isset( $_POST['cloud_api_key'] )
			? sanitize_text_field( wp_unslash( $_POST['cloud_api_key'] ) )
			: '';
		$settings['clear_cloud_api_key']       = isset( $_POST['clear_cloud_api_key'] )
			? sanitize_text_field( wp_unslash( $_POST['clear_cloud_api_key'] ) )
			: '';
		$settings['self_hosted_api_key']       = isset( $_POST['self_hosted_api_key'] )
			? sanitize_text_field( wp_unslash( $_POST['self_hosted_api_key'] ) )
			: '';
		$settings['clear_self_hosted_api_key'] = isset( $_POST['clear_self_hosted_api_key'] )
			? sanitize_text_field( wp_unslash( $_POST['clear_self_hosted_api_key'] ) )
			: '';

		$settings = $this->sanitize_settings( $settings );
		update_option( self::OPTION_NAME, $settings );
		$this->invalidate_model_cache();

		$verification = $this->verify_connection();
		if ( is_wp_error( $verification ) ) {
			wp_send_json_success(
				array(
					'connected' => false,
					'message'   => $verification->get_error_message(),
				)
			);
		}

		wp_send_json_success(
			array(
				'connected' => true,
				'message'   => __( 'Ollama is reachable with these settings.', 'zactonz-ai-provider-ollama' ),
			)
		);
	}

	/**
	 * Checks if the Ollama provider is connected.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if an Ollama endpoint is reachable, false otherwise.
	 */
	public function is_connected(): bool {
		return ! is_wp_error( $this->verify_connection() );
	}

	/**
	 * Verifies the active Ollama connection.
	 *
	 * @since 1.0.0
	 *
	 * @return true|\WP_Error True when connected, otherwise an error.
	 */
	private function verify_connection() {
		$this->set_active_request_authentication();

		return self::verify_active_connection();
	}

	/**
	 * Verifies the active Ollama connection without reading core Connector credentials.
	 *
	 * @since 1.0.0
	 *
	 * @return true|\WP_Error True when connected, otherwise an error.
	 */
	public static function verify_active_connection() {
		if ( self::is_cloud_connection() ) {
			return self::verify_cloud_connection();
		}

		return self::verify_self_hosted_connection();
	}

	/**
	 * Verifies Ollama Cloud with an authenticated OpenAI-compatible models call.
	 *
	 * @since 1.0.0
	 *
	 * @return true|\WP_Error True when Cloud credentials are accepted, otherwise an error.
	 */
	private static function verify_cloud_connection() {
		$api_key = self::get_effective_api_key_for_connection( self::CONNECTION_CLOUD );
		if ( '' === $api_key ) {
			return new WP_Error(
				'ollama_cloud_missing_api_key',
				__(
					'Ollama Cloud requires an API key before it can be marked connected.',
					'zactonz-ai-provider-ollama'
				),
				400
			);
		}

		$response = wp_remote_get(
			self::get_host() . '/v1/models',
			array(
				'headers' => self::get_request_headers_for_connection( self::CONNECTION_CLOUD ),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'ollama_cloud_request_failed',
				sprintf(
					/* translators: %s: Request error message. */
					__(
						'Could not reach Ollama Cloud to validate the API key. Error: %s',
						'zactonz-ai-provider-ollama'
					),
					$response->get_error_message()
				),
				500
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 401 === $status_code || 403 === $status_code ) {
			return new WP_Error(
				'ollama_cloud_invalid_api_key',
				__(
					'Ollama Cloud rejected this API key. Enter a valid Ollama Cloud API key and try again.',
					'zactonz-ai-provider-ollama'
				),
				$status_code
			);
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error(
				'ollama_cloud_unexpected_status',
				sprintf(
					/* translators: %d: HTTP status code. */
					__(
						'Ollama Cloud returned HTTP %d while validating the API key.',
						'zactonz-ai-provider-ollama'
					),
					$status_code
				),
				$status_code
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || ! isset( $body['data'] ) || ! is_array( $body['data'] ) ) {
			return new WP_Error(
				'ollama_cloud_invalid_response',
				__(
					'Ollama Cloud returned an unexpected response while validating the API key.',
					'zactonz-ai-provider-ollama'
				),
				500
			);
		}

		return true;
	}

	/**
	 * Verifies self-hosted Ollama with the native tags endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return true|\WP_Error True when the endpoint is reachable, otherwise an error.
	 */
	private static function verify_self_hosted_connection() {
		$response = wp_remote_get(
			self::get_host() . '/api/tags',
			array(
				'headers' => self::get_request_headers_for_connection( self::CONNECTION_SELF_HOSTED ),
				'timeout' => min( 20.0, self::get_text_request_timeout() ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'ollama_self_hosted_request_failed',
				sprintf(
					/* translators: %s: Request error message. */
					__(
						'Could not reach self-hosted Ollama with these settings. Error: %s',
						'zactonz-ai-provider-ollama'
					),
					$response->get_error_message()
				),
				500
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 401 === $status_code || 403 === $status_code ) {
			return new WP_Error(
				'ollama_self_hosted_invalid_api_key',
				__(
					'Self-hosted Ollama rejected this API key. Enter the correct self-hosted API key or remove it if your endpoint does not require one.',
					'zactonz-ai-provider-ollama'
				),
				$status_code
			);
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error(
				'ollama_self_hosted_unexpected_status',
				sprintf(
					/* translators: %d: HTTP status code. */
					__(
						'Self-hosted Ollama returned HTTP %d while checking the models endpoint.',
						'zactonz-ai-provider-ollama'
					),
					$status_code
				),
				$status_code
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || ! array_key_exists( 'models', $body ) ) {
			return new WP_Error(
				'ollama_self_hosted_invalid_response',
				__(
					'Self-hosted Ollama returned an unexpected response while checking the models endpoint.',
					'zactonz-ai-provider-ollama'
				),
				500
			);
		}

		return true;
	}

	/**
	 * Builds request headers for the active Ollama connection mode.
	 *
	 * @since 1.0.0
	 *
	 * @param string $connection_type Connection type.
	 * @return array<string, string> Request headers.
	 */
	public static function get_request_headers_for_connection( string $connection_type ): array {
		$headers = array(
			'Accept' => 'application/json',
		);
		$api_key = self::get_effective_api_key_for_connection( $connection_type );

		if ( '' !== $api_key ) {
			$headers['Authorization'] = 'Bearer ' . $api_key;
		}

		return $headers;
	}

	/**
	 * Prepends the default Ollama model to a WordPress AI model preference list.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array{string, string}> $models Existing preferred models.
	 * @return array<int, array{string, string}> Preferred models with Ollama first.
	 */
	public function prepend_default_model_preference( array $models ): array {
		return $this->prepend_model_preference( $models, self::get_preferred_model( 'text' ) );
	}

	/**
	 * Prepends the default text model.
	 *
	 * @since 1.1.0
	 * @param array<int, mixed> $models Existing preferences.
	 * @return array<int, mixed> Updated preferences.
	 */
	public function prepend_default_text_model_preference( array $models ): array {
		return $this->prepend_model_preference( $models, self::get_preferred_model( 'text' ) );
	}

	/**
	 * Prepends the default vision model.
	 *
	 * @since 1.1.0
	 * @param array<int, mixed> $models Existing preferences.
	 * @return array<int, mixed> Updated preferences.
	 */
	public function prepend_default_vision_model_preference( array $models ): array {
		return $this->prepend_model_preference( $models, self::get_preferred_model( 'vision' ) );
	}

	/**
	 * Prepends the default image model.
	 *
	 * @since 1.1.0
	 * @param array<int, mixed> $models Existing preferences.
	 * @return array<int, mixed> Updated preferences.
	 */
	public function prepend_default_image_model_preference( array $models ): array {
		return $this->prepend_model_preference( $models, self::get_preferred_model( 'image' ) );
	}

	/**
	 * Prepends the default embedding model.
	 *
	 * @since 1.1.0
	 * @param array<int, mixed> $models Existing preferences.
	 * @return array<int, mixed> Updated preferences.
	 */
	public function prepend_default_embedding_model_preference( array $models ): array {
		return $this->prepend_model_preference( $models, self::get_preferred_model( 'embedding' ) );
	}

	/**
	 * Prepends the default tool-calling model.
	 *
	 * @since 1.1.0
	 * @param array<int, mixed> $models Existing preferences.
	 * @return array<int, mixed> Updated preferences.
	 */
	public function prepend_default_tools_model_preference( array $models ): array {
		return $this->prepend_model_preference( $models, self::get_preferred_model( 'tools' ) );
	}

	/**
	 * Prepends one Ollama model without adding duplicates.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, mixed> $models Existing preferences.
	 * @param string            $model_id Ollama model ID.
	 * @return array<int, mixed> Updated preferences.
	 */
	private function prepend_model_preference( array $models, string $model_id ): array {
		if ( '' === $model_id ) {
			return $models;
		}

		$models = array_values(
			array_filter(
				$models,
				static function ( $model ) use ( $model_id ): bool {
					return ! is_array( $model )
						|| ! isset( $model[0], $model[1] )
						|| 'ollama' !== $model[0]
						|| $model_id !== $model[1];
				}
			)
		);

		array_unshift( $models, array( 'ollama', $model_id ) );

		return $models;
	}

	/**
	 * Gets the models from the Ollama provider.
	 *
	 * @since 1.1.0
	 *
	 * @return \WP_Error|list<\WordPress\AiClient\Providers\Models\DTO\ModelMetadata> The models.
	 */
	public function get_models() {
		if ( ! class_exists( AiClient::class ) ) {
			return new WP_Error( 'ai_client_not_found', __( 'The WordPress AI Client is not available.', 'zactonz-ai-provider-ollama' ), 404 );
		}

		$verification = self::verify_active_connection();
		if ( is_wp_error( $verification ) ) {
			return $verification;
		}

		$provider_id = 'ollama';
		$registry    = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( $provider_id ) ) {
			return new WP_Error( 'ai_provider_not_found', __( 'AI provider not found.', 'zactonz-ai-provider-ollama' ), 404 );
		}

		$provider_classname = $registry->getProviderClassName( $provider_id );
		$this->set_active_request_authentication();

		try {
			// phpcs:ignore Generic.Commenting.DocComment.MissingShort
			$model_metadata_directory = $provider_classname::modelMetadataDirectory();
			return $model_metadata_directory->listModelMetadata();
		} catch ( \Throwable $e ) {
			/* translators: %s: Error message. */
			return new WP_Error( 'could_not_list_models', sprintf( __( 'Could not list models for provider - is Ollama running or are the API credentials invalid? Error: %s', 'zactonz-ai-provider-ollama' ), $e->getMessage() ), 500 );
		}
	}

	/**
	 * Invalidates provider model caches when connection settings change.
	 *
	 * @since 1.0.0
	 */
	private function invalidate_model_cache(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();
		if ( ! $registry->hasProvider( 'ollama' ) ) {
			return;
		}

		$provider_classname       = $registry->getProviderClassName( 'ollama' );
		$model_metadata_directory = $provider_classname::modelMetadataDirectory();
		if ( method_exists( $model_metadata_directory, 'invalidateCaches' ) ) {
			$model_metadata_directory->invalidateCaches();
		}
	}

	/**
	 * Saves mode-specific API keys and refreshes request authentication.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $value Submitted settings values.
	 * @param string               $connection_type Selected connection type.
	 */
	private function save_api_keys( array $value, string $connection_type ): void {
		$this->save_api_key_for_connection(
			self::CONNECTION_CLOUD,
			$value,
			'cloud_api_key',
			'clear_cloud_api_key'
		);
		$this->save_api_key_for_connection(
			self::CONNECTION_SELF_HOSTED,
			$value,
			'self_hosted_api_key',
			'clear_self_hosted_api_key'
		);

		// Backward compatibility for versions that submitted one generic API key.
		if ( isset( $value['api_key'] ) || isset( $value['clear_api_key'] ) ) {
			$this->save_api_key_for_connection(
				$connection_type,
				$value,
				'api_key',
				'clear_api_key'
			);
		}

		$this->set_active_request_authentication();
	}

	/**
	 * Saves or clears the API key for one connection mode.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $connection_type Connection type.
	 * @param array<string, mixed> $value Submitted settings values.
	 * @param string               $api_key_field API key field key.
	 * @param string               $clear_field Clear checkbox field key.
	 */
	private function save_api_key_for_connection(
		string $connection_type,
		array $value,
		string $api_key_field,
		string $clear_field
	): void {
		$option_name = self::get_api_key_option_name_for_connection( $connection_type );

		if ( ! empty( $value[ $clear_field ] ) ) {
			update_option( $option_name, '', false );
			return;
		}

		if ( ! isset( $value[ $api_key_field ] ) ) {
			return;
		}

		$api_key = sanitize_text_field( (string) $value[ $api_key_field ] );
		if ( '' === $api_key ) {
			return;
		}

		update_option( $option_name, $api_key, false );
	}

	/**
	 * Applies the active mode-specific key to Ollama requests.
	 *
	 * @since 1.0.0
	 */
	private function set_active_request_authentication(): void {
		$api_key = self::get_active_saved_api_key();

		$this->set_request_authentication( $api_key );
	}

	/**
	 * Updates the current request's Ollama authentication after a key save.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key Saved API key, or an empty string for no key.
	 */
	private function set_request_authentication( string $api_key ): void {
		if ( ! class_exists( AiClient::class ) || ! class_exists( ApiKeyRequestAuthentication::class ) ) {
			return;
		}

		$api_key_override = self::get_api_key_override();
		if ( '' !== $api_key_override ) {
			$api_key = $api_key_override;
		}

		$registry = AiClient::defaultRegistry();
		if ( ! $registry->hasProvider( 'ollama' ) ) {
			return;
		}

		$registry->setProviderRequestAuthentication(
			'ollama',
			new ApiKeyRequestAuthentication( $api_key )
		);
	}

	/**
	 * Gets the API key override from a PHP constant or environment variable.
	 *
	 * @since 1.0.0
	 *
	 * @return string API key override, or an empty string when none is configured.
	 */
	public static function get_api_key_override(): string {
		if ( defined( 'OLLAMA_API_KEY' ) ) {
			$constant_value = constant( 'OLLAMA_API_KEY' );
			if ( is_string( $constant_value ) && '' !== trim( $constant_value ) ) {
				return sanitize_text_field( $constant_value );
			}
		}

		$environment_value = getenv( 'OLLAMA_API_KEY' );
		if ( is_string( $environment_value ) && '' !== trim( $environment_value ) ) {
			return sanitize_text_field( $environment_value );
		}

		return '';
	}

	/**
	 * Gets the saved API key for the active connection mode.
	 *
	 * @since 1.0.0
	 *
	 * @return string Active saved API key or an empty string.
	 */
	public static function get_active_saved_api_key(): string {
		return self::get_saved_api_key_for_connection( self::get_connection_type() );
	}

	/**
	 * Gets the effective API key for one connection mode.
	 *
	 * @since 1.0.0
	 *
	 * @param string $connection_type Connection type.
	 * @return string API key override, saved key, or an empty string.
	 */
	private static function get_effective_api_key_for_connection( string $connection_type ): string {
		$api_key_override = self::get_api_key_override();
		if ( '' !== $api_key_override ) {
			return $api_key_override;
		}

		return self::get_saved_api_key_for_connection( $connection_type );
	}

	/**
	 * Gets the option name for one mode-specific API key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $connection_type Connection type.
	 * @return string Option name.
	 */
	private static function get_api_key_option_name_for_connection( string $connection_type ): string {
		if ( self::CONNECTION_CLOUD === $connection_type ) {
			return self::CLOUD_API_KEY_OPTION;
		}

		return self::SELF_HOSTED_API_KEY_OPTION;
	}

	/**
	 * Gets the saved API key for one connection mode.
	 *
	 * @since 1.0.0
	 *
	 * @param string $connection_type Connection type.
	 * @return string Saved API key or an empty string.
	 */
	private static function get_saved_api_key_for_connection( string $connection_type ): string {
		$api_key = get_option( self::get_api_key_option_name_for_connection( $connection_type ), '' );

		return is_string( $api_key ) ? $api_key : '';
	}

	/**
	 * Checks whether one connection mode has a saved API key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $connection_type Connection type.
	 * @return bool True when a saved API key exists.
	 */
	private static function has_saved_api_key_for_connection( string $connection_type ): bool {
		return '' !== self::get_saved_api_key_for_connection( $connection_type );
	}

	/**
	 * Gets the settings from the WordPress option.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The settings.
	 */
	public static function get_settings(): array {
		$settings = get_option( self::OPTION_NAME, null );
		if ( is_array( $settings ) ) {
			return $settings;
		}

		$legacy_settings = get_option( self::LEGACY_OPTION_NAME, array() );
		return is_array( $legacy_settings ) ? $legacy_settings : array();
	}

	/**
	 * Gets the selected connection type.
	 *
	 * @since 1.0.0
	 *
	 * @return string Either cloud or self_hosted.
	 */
	public static function get_connection_type(): string {
		$settings = self::get_settings();
		if ( isset( $settings['connection_type'] ) && self::CONNECTION_CLOUD === $settings['connection_type'] ) {
			return self::CONNECTION_CLOUD;
		}

		return self::CONNECTION_SELF_HOSTED;
	}

	/**
	 * Checks whether the selected connection target is Ollama Cloud.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when Ollama Cloud is selected.
	 */
	public static function is_cloud_connection(): bool {
		return self::CONNECTION_CLOUD === self::get_connection_type();
	}

	/**
	 * Gets the preferred model thinking behavior.
	 *
	 * @since 1.0.0
	 *
	 * @return string Thinking behavior for the preferred model.
	 */
	public static function get_thinking_mode(): string {
		$settings = self::get_settings();
		$thinking = isset( $settings['thinking'] ) ? $settings['thinking'] : self::THINKING_DEFAULT;

		if ( self::THINKING_ENABLED === $thinking ) {
			return self::THINKING_MEDIUM;
		}

		if ( in_array( $thinking, array( self::THINKING_DEFAULT, self::THINKING_DISABLED, self::THINKING_LOW, self::THINKING_MEDIUM, self::THINKING_HIGH ), true ) ) {
			return $thinking;
		}

		return self::THINKING_DEFAULT;
	}

	/**
	 * Gets the configured preferred model ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $capability Task capability.
	 * @return string Preferred Ollama model ID.
	 */
	public static function get_preferred_model( string $capability = 'text' ): string {
		$settings = self::get_settings();
		$key      = 'model_' . $capability;
		if ( isset( $settings[ $key ] ) ) {
			return (string) $settings[ $key ];
		}

		return 'text' === $capability && isset( $settings['model'] ) ? (string) $settings['model'] : '';
	}

	/**
	 * Gets all task-specific model defaults.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, string> Defaults keyed by task.
	 */
	public static function get_preferred_models(): array {
		$models = array();
		foreach ( array( 'text', 'vision', 'image', 'embedding', 'tools' ) as $capability ) {
			$models[ $capability ] = self::get_preferred_model( $capability );
		}

		return $models;
	}

	/**
	 * Gets the configured text generation timeout.
	 *
	 * @since 1.0.0
	 *
	 * @return float Timeout in seconds.
	 */
	public static function get_text_request_timeout(): float {
		$settings = self::get_settings();
		if ( isset( $settings['request_timeout'] ) && is_numeric( $settings['request_timeout'] ) ) {
			$timeout = (float) $settings['request_timeout'];
			if ( $timeout >= 15.0 && $timeout <= 1800.0 ) {
				return $timeout;
			}
		}

		return 180.0;
	}

	/**
	 * Gets the configured embedding request timeout.
	 *
	 * @since 1.1.0
	 *
	 * @return float Timeout in seconds.
	 */
	public static function get_embedding_request_timeout(): float {
		$settings = self::get_settings();
		if ( isset( $settings['embedding_request_timeout'] ) && is_numeric( $settings['embedding_request_timeout'] ) ) {
			$timeout = (float) $settings['embedding_request_timeout'];
			if ( $timeout >= 5.0 && $timeout <= 1800.0 ) {
				return $timeout;
			}
		}

		return 60.0;
	}

	/**
	 * Gets the configured thinking behavior for a preferred model request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id Requested Ollama model ID.
	 * @return string Thinking behavior for this request.
	 */
	public static function get_thinking_mode_for_model( string $model_id ): string {
		$preferred_model = self::get_preferred_model( 'text' );
		if ( '' === $preferred_model || $preferred_model !== $model_id ) {
			return self::THINKING_DEFAULT;
		}

		return self::get_thinking_mode();
	}

	/**
	 * Gets the configured Ollama host and applies the separate port field.
	 *
	 * @since 1.0.0
	 *
	 * @return string Ollama host URL.
	 */
	public static function get_host(): string {
		if ( self::is_cloud_connection() ) {
			return 'https://ollama.com';
		}

		$settings          = self::get_settings();
		$uses_default_host = ! isset( $settings['host'] ) || '' === $settings['host'];
		$host              = ! $uses_default_host
			? $settings['host']
			: 'http://localhost';
		$port              = isset( $settings['port'] ) ? $settings['port'] : '';

		if ( '' === $port && $uses_default_host ) {
			$port = '11434';
		}

		if ( '' === $port || wp_parse_url( $host, PHP_URL_PORT ) ) {
			return rtrim( $host, '/' );
		}

		$parts = wp_parse_url( $host );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return rtrim( $host, '/' );
		}

		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : 'http';
		$path   = isset( $parts['path'] ) ? $parts['path'] : '';

		return rtrim( $scheme . '://' . $parts['host'] . ':' . $port . $path, '/' );
	}
}
