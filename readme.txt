=== Zactonz AI Connector: Ollama ===
Contributors:      zactonz
Tags:              connector, ollama, ai, ai-client, local-ai
Requires at least: 7.0
Tested up to:      7.0
Stable tag:        1.1.0
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Adds an Ollama connector to Settings > Connectors for the WordPress AI Client, local Ollama, self-hosted Ollama, and Ollama Cloud.

== Description ==

This plugin by [Zactonz Technologies](https://zactonz.com/) provides an [Ollama](https://ollama.com/) connector for the WordPress AI Client. It is built for the WordPress **Settings > Connectors** screen and for the plugin directory connector search, so site owners can add Ollama when they need a connector for local AI, self-hosted AI, or Ollama Cloud.

Use Zactonz AI Connector: Ollama when WordPress asks you to search the plugin directory for a connector. After activation, Ollama appears as an AI connector and can power text generation, image generation, embeddings, tool calling, structured output, and streaming where supported by the selected model.

It lets WordPress sites use large language models running locally, on a remote self-hosted Ollama instance, or on Ollama Cloud for text and image generation and other AI capabilities.

**Disclaimer:** Zactonz AI Connector: Ollama is developed by Zactonz Technologies. Ollama is a third-party project. This plugin is not affiliated with, endorsed by, or sponsored by Ollama.

Source code, issues and release history are on [GitHub](https://github.com/zactonz/zactonz-ai-provider-ollama). Setup and usage docs are on the [Zactonz developer portal](https://developers.zactonz.com/wordpress/plugins/zactonz-ai-provider-ollama/).

Ollama exposes an [OpenAI-compatible API](https://ollama.com/blog/openai-compatibility), and this connector uses that API to communicate with any model you have pulled into Ollama (Llama, Mistral, Gemma, Phi, and many more).

**Features:**

* Text generation with any Ollama model
* Image generation with supported models
* Automatic model discovery from your Ollama instance
* Function calling support
* Structured output (JSON mode) support
* Connector integration for Settings > Connectors with an Ollama Cloud or self-hosted connection switch
* Discoverable WordPress AI connector metadata for plugin-directory connector searches
* Separate saved API keys for Ollama Cloud and protected self-hosted endpoints
* Self-hosted host URL and port controls, default model selection, thinking mode, and model discovery from the selected endpoint
* Admin text request timeout for slow local or thinking responses
* Works without an API key for local instances
* Single and batch embedding generation on WordPress 7.1 and newer
* Accurate per-model discovery for text, vision, tools, thinking, images, structured output, and embeddings
* Separate default models for text, vision, images, embeddings, and tool calling
* Default, disabled, low, medium, and high thinking controls
* Redacted diagnostics plus WordPress Site Health integration
* Stable cancellable SSE streaming for content, thinking, and tool-call chunks

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

= Why should this plugin appear in the connector search? =

WordPress links to the plugin directory from **Settings > Connectors** when a needed connector is not installed. This plugin is tagged as `connector` and registers Ollama as a WordPress AI Client connector after activation.

= Do I need an API key? =

Ollama Cloud requires a valid API key in **Settings > Connectors** or **Settings > Ollama**.

Self-hosted Ollama does not need an API key by default. If your self-hosted endpoint is protected, enter the optional self-hosted API key. Cloud and self-hosted keys are stored separately in Zactonz-owned options and are applied to Ollama provider requests for the active connection mode.

An `OLLAMA_API_KEY` environment variable or PHP constant can also be used and takes priority over saved admin keys.

= How do I change the Ollama host URL? =

By default, the connector uses `http://localhost:11434`. You can change this in two ways:

1. Set the `OLLAMA_HOST` environment variable (takes precedence).
2. Choose **Self-hosted** on **Settings > Connectors** or **Settings > Ollama**, then enter your host URL or IP and port.

== Changelog ==

= 1.1.0 - 2026-08-03 =

* Added native Ollama single and batch embedding generation for the WordPress 7.1 / PHP AI Client 1.4 embedding API.
* Reworked discovery to advertise only capabilities reported by Ollama, with conservative fallback behavior when model details are unavailable.
* Added separate default selectors for text, vision, image, embedding, and tool-calling models.
* Added model-default, disabled, low, medium, and high thinking preferences with automatic migration from the previous enabled setting.
* Added a redacted diagnostics panel covering endpoint status, latency, model availability, version, embeddings, and streaming readiness.
* Added a WordPress Site Health connectivity test and redacted debug-information section.
* Added stable provider-level SSE streaming with content, thinking, usage, tool-call aggregation, callback-error handling, and cancellation with partial results.
* Improved WordPress.org connector-search metadata for admins coming from Settings > Connectors.
* Fixed Connector-screen saves so connection changes no longer discard model and thinking preferences.
* Fixed Ollama Cloud capability reporting so structured output is not advertised where it is unsupported.
* Added automated coverage for discovery, settings migration and preservation, embeddings, diagnostics, Site Health, streaming, cancellation, and streamed tool calls.

= 1.0.0 - 2026-05-22 =

* Zactonz release with Settings > Connectors recognition, Ollama Cloud and self-hosted modes, separate Cloud and self-hosted API credentials, provider logo, and live connected-status integration.
* Host URL or IP and port controls with model discovery from the configured Ollama instance.
* Default-model thinking mode and a longer configurable text request timeout for local Ollama responses.
* Text generation, structured output, function calling, vision-aware model metadata, and image generation for compatible models.

== Upgrade Notice ==

= 1.1.0 =

Adds embeddings, task-specific model defaults, accurate capability discovery, diagnostics, Site Health, and stable Ollama streaming. Existing connection and default-model settings are migrated automatically.

= 1.0.0 =

Initial release.
