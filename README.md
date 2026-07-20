# Zactonz AI Connector: Ollama

An Ollama AI connector plugin for the WordPress AI Client and its underlying PHP AI Client SDK.

Disclaimer: Zactonz AI Connector: Ollama is developed by Zactonz Technologies. Ollama is a third-party project. This plugin is not affiliated with, endorsed by, or sponsored by Ollama.

Version: v1.0.0

Developer: [Zactonz Technologies](https://zactonz.com/)

## Features

- Registers Ollama with the WordPress AI Client
- Registers Ollama on **Settings > Connectors** with its provider logo and live connection status
- Adds an Ollama Cloud or self-hosted connection switch on the Connector screen
- Configures a self-hosted Ollama URL or IP address and port in WordPress admin
- Stores Cloud and self-hosted API keys separately and applies the active key to Ollama requests
- Loads model choices and capabilities from the selected Ollama endpoint
- Lets admins choose a default Ollama model for WordPress AI model preferences
- Lets admins choose model-default, think, or no-think behavior when the default model reports thinking support
- Provides an admin text request timeout for slower local or thinking requests
- Supports optional credentials plus the `OLLAMA_API_KEY` environment variable or PHP constant
- Uses Ollama model discovery plus its OpenAI-compatible API for chat text generation

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
