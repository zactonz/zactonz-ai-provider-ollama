=== Zactonz AI Connector: Ollama ===
Contributors:      zactonz
Tags:              connector, ai-connector, ollama, ai, local-ai
Requires at least: 7.0
Tested up to:      7.0
Stable tag:        1.0.0
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Ollama AI connector for the WordPress AI Client by Zactonz Technologies.

== Description ==

This plugin by [Zactonz Technologies](https://zactonz.com/) provides [Ollama](https://ollama.com/) integration for the WordPress AI Client. It lets WordPress sites use large language models running locally, on a remote self-hosted Ollama instance, or on Ollama Cloud for text and image generation and other AI capabilities.

**Disclaimer:** Zactonz AI Connector: Ollama is developed by Zactonz Technologies. Ollama is a third-party project. This plugin is not affiliated with, endorsed by, or sponsored by Ollama.

Ollama exposes an [OpenAI-compatible API](https://ollama.com/blog/openai-compatibility), and this connector uses that API to communicate with any model you have pulled into Ollama (Llama, Mistral, Gemma, Phi, and many more).

**Features:**

* Text generation with any Ollama model
* Image generation with supported models
* Automatic model discovery from your Ollama instance
* Function calling support
* Structured output (JSON mode) support
* Connector integration for Settings > Connectors with an Ollama Cloud or self-hosted connection switch
* Separate saved API keys for Ollama Cloud and protected self-hosted endpoints
* Self-hosted host URL and port controls, default model selection, thinking mode, and model discovery from the selected endpoint
* Admin text request timeout for slow local or thinking responses
* Works without an API key for local instances

**Requirements:**

* PHP 7.4 or higher
* WordPress 7.0 or higher
* Ollama running locally or on a remote server (like Ollama Cloud)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/zactonz-ai-provider-ollama/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Settings > Connectors** to choose Ollama Cloud or a self-hosted Ollama connection.
4. Add the required Ollama Cloud API key, or save the self-hosted host URL, port, and optional self-hosted API key.
5. Go to **Settings > Ollama** to review available models and choose a default model, thinking mode, and text request timeout.

== Frequently Asked Questions ==

= How do I install Ollama? =

Visit [ollama.com](https://ollama.com/) to download and install Ollama for your platform. Once installed, pull a model (example `ollama pull llama3.2`) and the connector will automatically discover it.

= Do I need an API key? =

Ollama Cloud requires a valid API key in **Settings > Connectors** or **Settings > Ollama**.

Self-hosted Ollama does not need an API key by default. If your self-hosted endpoint is protected, enter the optional self-hosted API key. Cloud and self-hosted keys are stored separately in Zactonz-owned options and are applied to Ollama provider requests for the active connection mode.

An `OLLAMA_API_KEY` environment variable or PHP constant can also be used and takes priority over saved admin keys.

= How do I change the Ollama host URL? =

By default, the connector uses `http://localhost:11434`. You can change this in two ways:

1. Set the `OLLAMA_HOST` environment variable (takes precedence).
2. Choose **Self-hosted** on **Settings > Connectors** or **Settings > Ollama**, then enter your host URL or IP and port.

== Changelog ==

= 1.0.0 - 2026-05-22 =

* Zactonz release with Settings > Connectors recognition, Ollama Cloud and self-hosted modes, separate Cloud and self-hosted API credentials, provider logo, and live connected-status integration.
* Host URL or IP and port controls with model discovery from the configured Ollama instance.
* Default-model thinking mode and a longer configurable text request timeout for local Ollama responses.
* Text generation, structured output, function calling, vision-aware model metadata, and image generation for compatible models.

== Upgrade Notice ==

= 1.0.0 =

Initial release.
