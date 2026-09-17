# Zactonz AI Connector: Ollama

[![WordPress plugin](https://img.shields.io/badge/WordPress.org-Zactonz%20AI%20Connector%3A%20Ollama-blue?logo=wordpress)](https://wordpress.org/plugins/zactonz-ai-provider-ollama/)
[![Latest release](https://img.shields.io/github/v/release/zactonz/zactonz-ai-provider-ollama?include_prereleases&label=release)](https://github.com/zactonz/zactonz-ai-provider-ollama/releases)
[![License: GPL v2 or later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php)](composer.json)

Adds an Ollama connector to **Settings > Connectors** for the WordPress AI Client, local Ollama, self-hosted Ollama, and Ollama Cloud.

Disclaimer: Zactonz AI Connector: Ollama is developed by Zactonz Technologies. Ollama is a third-party project. This plugin is not affiliated with, endorsed by, or sponsored by Ollama.

Version: v1.1.0

Developer: [Zactonz Technologies](https://zactonz.com/)

Links: [Documentation](https://developers.zactonz.com/wordpress/plugins/zactonz-ai-provider-ollama/) | [WordPress.org plugin](https://wordpress.org/plugins/zactonz-ai-provider-ollama/) | [GitHub releases](https://github.com/zactonz/zactonz-ai-provider-ollama/releases) | [Support forum](https://wordpress.org/support/plugin/zactonz-ai-provider-ollama/)

## Features

- Registers Ollama with the WordPress AI Client
- Registers Ollama on **Settings > Connectors** with its provider logo and live connection status
- Uses connector-focused WordPress.org metadata so admins can find it from the plugin directory connector search
- Adds an Ollama Cloud or self-hosted connection switch on the Connector screen
- Configures a self-hosted Ollama URL or IP address and port in WordPress admin
- Stores Cloud and self-hosted API keys separately and applies the active key to Ollama requests
- Loads model choices and capabilities from the selected Ollama endpoint
- Lets admins choose a default Ollama model for WordPress AI model preferences
- Lets admins choose model-default, think, or no-think behavior when the default model reports thinking support
- Provides an admin text request timeout for slower local or thinking requests
- Supports optional credentials plus the `OLLAMA_API_KEY` environment variable or PHP constant
- Uses Ollama model discovery plus its OpenAI-compatible API for chat text generation
- Adds single and batch embedding generation through Ollama's native `/api/embed` endpoint on WordPress 7.1+
- Detects model capabilities accurately, including text, vision, tools, thinking, images, and embeddings
- Provides separate default models for text, vision, image generation, embeddings, and tool calling
- Supports default, disabled, low, medium, and high thinking levels when the selected text model supports thinking
- Adds redacted connection diagnostics and a WordPress Site Health test
- Provides stable, cancellable SSE streaming with separate content, thinking, and tool-call events

## Installation

1. Upload the `zactonz-ai-provider-ollama` folder to `/wp-content/plugins/`.
2. Ensure the WordPress AI Client or PHP AI Client integration is available.
3. Activate **Zactonz AI Connector: Ollama**.
4. Go to **Settings > Connectors**.
5. Choose **Ollama Cloud** and add a valid Cloud API key, or choose **Self-hosted** and save the Ollama URL or IP address, port, and optional self-hosted API key.
6. Use **Settings > Ollama** for default-model selection, thinking behavior, timeout, and model discovery details.

The default local connection is `http://127.0.0.1:11434`.

## API Key

Ollama Cloud requires an API key in **Settings > Connectors** or **Settings > Ollama**. Self-hosted Ollama API access does not require a key by default, but a separate optional self-hosted key is available for protected endpoints. Keys can also be provided before WordPress loads:

```php
define('OLLAMA_API_KEY', 'your-api-key');
```

An `OLLAMA_API_KEY` environment variable or PHP constant takes priority over the saved admin value.

## Usage

After the connector is active and reachable, WordPress AI Client code can use Ollama by provider ID:

```php
$text = wp_ai_client_prompt( 'Write a short WordPress release note.' )
	->using_provider( 'ollama' )
	->generate_text();
```

The default model picker moves the selected model to the front of Ollama model discovery and prepends it to WordPress AI model preference lists. Callers can still request a specific Ollama model by model ID when needed.

For the default model, the thinking control uses Ollama's OpenAI-compatible reasoning field. **No thinking** asks supported models to skip reasoning and can shorten local responses. Models that do not report thinking support keep their normal behavior.

### Embeddings

WordPress 7.1 and PHP AI Client 1.4 add the embedding-generation contract used by this connector. Single and batch inputs are supported:

```php
use WordPress\AiClient\AiClient;

$embeddings = AiClient::input( array( 'First document', 'Second document' ) )
	->usingProvider( 'ollama' )
	->generateEmbeddings();
```

On WordPress 7.0 the plugin remains fully load-safe and continues to provide its text and image capabilities; embedding-only models become available automatically after upgrading to WordPress 7.1.

### Streaming

Until the PHP AI Client publishes a provider-neutral streaming interface, the Ollama text model exposes a stable provider extension named `generateOllamaStreamResult()`. Each callback event has a `type` of `content_delta`, `thinking_delta`, `tool_call_delta`, or `done`. Return `false` to cancel and receive the partial result.

```php
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\UserMessage;
use Zactonz\AiProviderForOllama\Provider\OllamaProvider;

$model  = OllamaProvider::model( 'qwen3' );
$result = $model->generateOllamaStreamResult(
	array( new UserMessage( array( new MessagePart( 'Write a release note.' ) ) ) ),
	static function ( array $event ) {
		if ( 'content_delta' === $event['type'] ) {
			echo esc_html( $event['delta'] );
			flush();
		}
	}
);
```

Streaming requires the PHP cURL extension. The diagnostics panel reports whether the server is ready.

## Development

Install development dependencies with Composer:

```bash
composer install
```

Run the full local quality suite:

```bash
composer lint
```

Individual checks are also available:

```bash
composer test
composer phpcs
composer phpstan
```

The WordPress.org distribution excludes development tooling through `.distignore`.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.

## Security

Please report security issues privately. See [SECURITY.md](SECURITY.md).

## License

Zactonz AI Connector: Ollama is licensed under GPL-2.0-or-later. See [LICENSE](LICENSE).
