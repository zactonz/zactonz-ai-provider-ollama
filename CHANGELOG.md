# Changelog

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
