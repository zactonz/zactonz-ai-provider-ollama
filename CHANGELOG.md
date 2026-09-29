# Changelog

## 1.2.0 - 2026-09-29

- Changed streaming to use the WordPress HTTP API. The plugin no longer calls cURL anywhere in its own code: a stream is an ordinary `wp_remote_post()` and the body arrives through the `requests-request.progress` action that WordPress bridges from the Requests library.
- Changed a transport without incremental delivery to replay the whole response through the same parser, so streaming returns every event instead of failing when the cURL extension is absent.
- Changed cancellation to stop delivering events without aborting the transfer, which no HTTP API hook can do. The partial result is unchanged.
- Fixed the Ollama mark on Settings > Connectors, which was supplied at its own 646x854 proportions and did not fit the square the screen draws it in.
- Changed the API key to also live in the option WordPress registers for the connector, so a key entered on Settings > Connectors and one entered on the plugin's own screen are the same credential. The per-connection keys are still kept, so switching between Cloud and self-hosted does not discard the other one, and an existing key is copied across on upgrade.
- Added an uninstall handler that removes the settings and both stored keys when the plugin is deleted, on single sites and across a network.
- Fixed streamed responses reporting no token usage. An OpenAI-compatible stream omits the usage block unless it is asked for.
- Changed model preference to travel through the order models are listed in rather than five global filters. Those filters are shared with every other connector on the site, so whichever registered last won; listing order is per provider. Every capability's default now leads, not only the text one.
- Renamed the plugin to Zactonz AI Connector for Ollama, matching the rest of the connector family. The directory, slug and text domain are unchanged.
- Fixed the Cloud and self-hosted API keys being interchangeable. A key entered on Settings > Connectors is now filed under the connection mode in use at the time, so switching mode can no longer send an Ollama Cloud key to a self-hosted address, or a private self-hosted token to ollama.com.
- Fixed a fatal error when a model listing contains an entry without a usable name. Entries from both the self-hosted and Cloud endpoints are validated before use.
- Fixed events being delivered after a stream was cancelled. The trailing block of an unterminated stream is no longer parsed once the caller has returned false, so the partial result holds only what the caller was told about.
- Fixed a callback failure on a replayed body, on the trailing block, or on the done event being discarded instead of reported.
- Changed the reachability check to be cached for five minutes per connection. The AI Client asks on every model lookup, and each answer previously cost a request, or the full request timeout when Ollama was not running.
- Changed the Site Health connectivity test to run asynchronously, and to report a recommendation rather than a critical failure when Ollama is simply not running. A direct test held the Site Health screen open for the length of the request.
- Added the admin script translations registration, without which none of the settings screen's JavaScript strings could be translated.
- Added the missing direct-access guard to every shipped class file.
- Changed the Ollama port to widen the HTTP API's safe-port list only while the connector's own request is in flight, rather than for every request the site makes.
- Fixed the guard meant to stop the plugin loading twice, which was never set.
- Added an External services section to the readme covering what Ollama Cloud receives, and an upgrade notice for this release.
- Fixed administrator AJAX errors returning HTTP 200 because a WP_Error code was passed where a status code was expected.
- Changed uninstall.php to be covered by the coding-standards and static-analysis runs, which had only looked at igniter.php and includes.

## 1.1.0 - 2026-08-03

- Added native Ollama single and batch embedding generation for the WordPress 7.1 / PHP AI Client 1.4 embedding API.
- Reworked discovery to advertise only capabilities reported by Ollama, with conservative fallback behavior when model details are unavailable.
- Added separate default selectors for text, vision, image, embedding, and tool-calling models.
- Added model-default, disabled, low, medium, and high thinking preferences with automatic migration from the previous enabled setting.
- Added a redacted diagnostics panel covering endpoint status, latency, model availability, version, embeddings, and streaming readiness.
- Added a WordPress Site Health connectivity test and redacted debug-information section.
- Added stable provider-level SSE streaming with content, thinking, usage, tool-call aggregation, callback-error handling, and cancellation with partial results.
- Improved WordPress.org connector-search metadata for admins coming from Settings > Connectors.
- Fixed Connector-screen saves so connection changes no longer discard model and thinking preferences.
- Fixed Ollama Cloud capability reporting so structured output is not advertised where it is unsupported.
- Added automated coverage for discovery, settings migration and preservation, embeddings, diagnostics, Site Health, streaming, cancellation, and streamed tool calls.

## 1.0.0 - 2026-05-22

- Initial Zactonz release with Settings > Connectors recognition.
- Added Ollama Cloud and self-hosted modes.
- Added separate Cloud and self-hosted API credentials.
- Added provider logo and live connected-status integration.
- Added host URL or IP and port controls with model discovery from the configured Ollama instance.
- Added default-model thinking mode and a longer configurable text request timeout for local Ollama responses.
- Added text generation, structured output, function calling, vision-aware model metadata, and image generation for compatible models.
