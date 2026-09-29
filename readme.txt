=== Zactonz AI Connector for Ollama ===
Contributors:      zactonz
Tags:              connector, ollama, ai, ai-client, local-ai
Requires at least: 7.0
Tested up to:      7.1
Stable tag:        1.2.0
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Adds an Ollama connector to Settings > Connectors for the WordPress AI Client, local Ollama, self-hosted Ollama, and Ollama Cloud.

== Description ==

This plugin by [Zactonz Technologies](https://zactonz.com/) provides an [Ollama](https://ollama.com/) connector for the WordPress AI Client. It is built for the WordPress **Settings > Connectors** screen and for the plugin directory connector search, so site owners can add Ollama when they need a connector for local AI, self-hosted AI, or Ollama Cloud.

Use Zactonz AI Connector for Ollama when WordPress asks you to search the plugin directory for a connector. After activation, Ollama appears as an AI connector and can power text generation, image generation, embeddings, tool calling, structured output, and streaming where supported by the selected model.

It lets WordPress sites use large language models running locally, on a remote self-hosted Ollama instance, or on Ollama Cloud for text and image generation and other AI capabilities.

**Disclaimer:** Zactonz AI Connector for Ollama is developed by Zactonz Technologies. Ollama is a third-party project. This plugin is not affiliated with, endorsed by, or sponsored by Ollama.

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

== External services ==

This plugin connects to Ollama, which may be software you run yourself or the third-party Ollama Cloud service, depending on which connection you choose.

**Self-hosted or local Ollama.** Requests go to the host and port you configure, `http://localhost:11434` by default. Nothing leaves your own machine or network, and no third party is involved.

**Ollama Cloud.** Choosing the Cloud connection sends requests to the Ollama service at `https://ollama.com`. What is sent: the API key you configure, and the prompts, images, tool definitions and generation settings that a WordPress AI feature passes to the AI Client, each time such a feature runs a request through this connector. The connector also requests the model catalogue when its settings screen loads, when a WordPress AI feature asks which models are available, and when you run diagnostics or the Site Health test. No other data is sent.

**Service provider:** Ollama ([terms of service](https://ollama.com/terms), [privacy policy](https://ollama.com/privacy)).

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

= 1.2.0 - 2026-09-29 =

* Changed streaming to use the WordPress HTTP API. The plugin no longer calls cURL anywhere in its own code: a stream is an ordinary wp_remote_post() and the body arrives through the requests-request.progress action that WordPress bridges from the Requests library.
* Changed a transport without incremental delivery to replay the whole response through the same parser, so streaming returns every event instead of failing when the cURL extension is absent.
* Changed cancellation to stop delivering events without aborting the transfer, which no HTTP API hook can do. Nothing is delivered after the callback returns false, and the partial result holds only what the caller was told about.
* Fixed the Ollama mark on Settings > Connectors, which was supplied at its own 646x854 proportions and did not fit the square the screen draws it in.
* Changed the API key to also live in the option WordPress registers for the connector, so a key entered on Settings > Connectors and one entered on the plugin's own screen are the same credential. The per-connection keys are still kept, so switching between Cloud and self-hosted does not discard the other one, and an existing key is copied across on upgrade.
* Added an uninstall handler that removes the settings and both stored keys when the plugin is deleted, on single sites and across a network.
* Fixed streamed responses reporting no token usage. An OpenAI-compatible stream omits the usage block unless it is asked for.
* Changed model preference to travel through the order models are listed in rather than five global filters. Those filters are shared with every other connector on the site, so whichever registered last won; listing order is per provider. Every capability's default now leads, not only the text one.
* Renamed the plugin to Zactonz AI Connector for Ollama, matching the rest of the connector family. The directory, slug and text domain are unchanged.
* Fixed the Cloud and self-hosted API keys being interchangeable. A key entered on Settings > Connectors is now filed under the connection mode in use at the time, so switching mode can no longer send an Ollama Cloud key to a self-hosted address, or a private self-hosted token to ollama.com.
* Fixed a fatal error when a model listing contains an entry without a usable name. Entries from both the self-hosted and Cloud endpoints are validated before use.
* Fixed events being delivered after a stream was cancelled. The trailing block of an unterminated stream is no longer parsed once the caller has returned false, so the partial result holds only what the caller was told about.
* Fixed a callback failure on a replayed body, on the trailing block, or on the done event being discarded instead of reported.
* Changed the reachability check to be cached for five minutes per connection. The AI Client asks on every model lookup, and each answer previously cost a request, or the full request timeout when Ollama was not running.
* Changed the Site Health connectivity test to run asynchronously, and to report a recommendation rather than a critical failure when Ollama is simply not running. A direct test held the Site Health screen open for the length of the request.
* Added the admin script translations registration, without which none of the settings screen's JavaScript strings could be translated.
* Added the missing direct-access guard to every shipped class file.
* Changed the Ollama port to widen the HTTP API's safe-port list only while the connector's own request is in flight, rather than for every request the site makes.
* Fixed the guard meant to stop the plugin loading twice, which was never set.
* Added an External services section to the readme covering what Ollama Cloud receives, and an upgrade notice for this release.
* Fixed administrator AJAX errors returning HTTP 200 because a WP_Error code was passed where a status code was expected.
* Changed uninstall.php to be covered by the coding-standards and static-analysis runs, which had only looked at igniter.php and includes.

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

= 1.2.0 =

Streaming now runs on the WordPress HTTP API and works without the cURL extension. Fixes the Cloud and self-hosted keys being usable for the wrong connection, a fatal error on an unexpected model listing, and events arriving after a stream was cancelled.

= 1.1.0 =

Adds embeddings, task-specific model defaults, accurate capability discovery, diagnostics, Site Health, and stable Ollama streaming. Existing connection and default-model settings are migrated automatically.

= 1.0.0 =

Initial release.
