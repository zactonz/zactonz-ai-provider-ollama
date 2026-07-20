( function () {
	'use strict';

	var config =
		window.zctzOllamaAiConnectorConnectorSettings ||
		window.zactonzOllamaAiConnectorConnectorSettings;
	if ( ! config ) {
		return;
	}

	var strings = config.strings || {};
	var panelId = 'zactonz-ai-provider-ollama-connector-panel';
	var messageKey = 'zactonz-ai-provider-ollama-connector-message';

	function text( value, fallback ) {
		return value || fallback || '';
	}

	function createElement( tag, className, content ) {
		var element = document.createElement( tag );
		if ( className ) {
			element.className = className;
		}
		if ( content ) {
			element.textContent = content;
		}
		return element;
	}

	function findOllamaCard() {
		var labels = document.querySelectorAll( 'h1, h2, h3, h4, strong' );
		var index;

		for ( index = 0; index < labels.length; index++ ) {
			if ( 'Ollama' !== labels[ index ].textContent.trim() ) {
				continue;
			}

			if ( labels[ index ].closest( '.components-card' ) ) {
				return labels[ index ].closest( '.components-card' );
			}

			var node = labels[ index ].parentElement;
			while ( node && node !== document.body ) {
				if (
					node.textContent.indexOf( 'Ollama' ) !== -1 &&
					node.querySelector( 'button, a' )
				) {
					return node;
				}
				node = node.parentElement;
			}
		}

		return null;
	}

	function radioOption( name, value, label, checked ) {
		var wrapper = createElement(
			'label',
			'zactonz-ai-provider-ollama-connector-panel__radio'
		);
		var input = document.createElement( 'input' );
		input.type = 'radio';
		input.name = name;
		input.value = value;
		input.checked = checked;
		wrapper.appendChild( input );
		wrapper.appendChild( document.createTextNode( label ) );
		return wrapper;
	}

	function field( label, input ) {
		var wrapper = createElement(
			'label',
			'zactonz-ai-provider-ollama-connector-panel__field'
		);
		wrapper.appendChild(
			createElement(
				'span',
				'zactonz-ai-provider-ollama-connector-panel__label',
				label
			)
		);
		wrapper.appendChild( input );
		return wrapper;
	}

	function apiKeySection( options ) {
		var section = createElement(
			'div',
			'zactonz-ai-provider-ollama-connector-panel__api-key'
		);

		var apiKey = document.createElement( 'input' );
		apiKey.type = 'password';
		apiKey.name = options.name;
		apiKey.value = '';
		apiKey.autocomplete = 'new-password';
		apiKey.placeholder = options.hasKey
			? text( strings.savedApiKey, 'Saved API key is hidden' )
			: options.placeholder;
		apiKey.className = 'regular-text';
		section.appendChild( field( options.label, apiKey ) );
		if ( options.hasKey ) {
			section.appendChild(
				createElement(
					'p',
					'zactonz-ai-provider-ollama-connector-panel__help',
					options.savedHelp
				)
			);
		}
		section.appendChild(
			createElement(
				'p',
				'zactonz-ai-provider-ollama-connector-panel__help',
				options.help
			)
		);

		var clearApiKey = document.createElement( 'input' );
		clearApiKey.type = 'checkbox';
		clearApiKey.name = options.clearName;
		clearApiKey.value = '1';

		if ( options.hasKey ) {
			var clearApiKeyLabel = createElement(
				'label',
				'zactonz-ai-provider-ollama-connector-panel__checkbox'
			);
			clearApiKeyLabel.appendChild( clearApiKey );
			clearApiKeyLabel.appendChild( document.createTextNode( options.clearLabel ) );
			section.appendChild( clearApiKeyLabel );
		}

		return {
			clearApiKey: clearApiKey,
			input: apiKey,
			section: section
		};
	}

	function updateStatus( status, kind, message ) {
		status.className = 'zactonz-ai-provider-ollama-connector-panel__status';
		if ( kind ) {
			status.className += ' is-' + kind;
		}
		status.textContent = message || '';
	}

	function renderPanel( card ) {
		if ( document.getElementById( panelId ) ) {
			return;
		}

		var isCloud = 'cloud' === config.connectionType;
		var panel = createElement(
			'form',
			'zactonz-ai-provider-ollama-connector-panel'
		);
		panel.id = panelId;
		panel.noValidate = true;

		var header = createElement(
			'div',
			'zactonz-ai-provider-ollama-connector-panel__header'
		);
		header.appendChild(
			createElement(
				'h2',
				'zactonz-ai-provider-ollama-connector-panel__title',
				text( strings.connection, 'Connection' )
			)
		);
		panel.appendChild( header );

		var choices = createElement(
			'fieldset',
			'zactonz-ai-provider-ollama-connector-panel__choices'
		);
		choices.appendChild(
			radioOption(
				'zactonz-ai-provider-ollama-connection-type',
				'self_hosted',
				text( strings.selfHosted, 'Self-hosted' ),
				! isCloud
			)
		);
		choices.appendChild(
			radioOption(
				'zactonz-ai-provider-ollama-connection-type',
				'cloud',
				text( strings.cloud, 'Ollama Cloud' ),
				isCloud
			)
		);
		panel.appendChild( choices );

		var help = createElement(
			'p',
			'zactonz-ai-provider-ollama-connector-panel__help'
		);
		panel.appendChild( help );

		var cloudApiKey = apiKeySection( {
			clearLabel: text( strings.removeCloudApiKey, 'Remove saved Cloud API key' ),
			clearName: 'clear_cloud_api_key',
			hasKey: !! config.hasCloudApiKey,
			help: text(
				strings.cloudApiKeyHelp,
				'Required for Ollama Cloud. Leave blank to keep the saved Cloud API key.'
			),
			label: text( strings.cloudApiKey, 'Cloud API key' ),
			name: 'cloud_api_key',
			placeholder: text(
				strings.enterCloudApiKey,
				'Enter Ollama Cloud API key'
			),
			savedHelp: text(
				strings.cloudApiKeySaved,
				'A Cloud API key is saved. Enter a new key to replace it.'
			)
		} );
		panel.appendChild( cloudApiKey.section );

		var selfHostedFields = createElement(
			'div',
			'zactonz-ai-provider-ollama-connector-panel__self-hosted'
		);

		var host = document.createElement( 'input' );
		host.type = 'text';
		host.name = 'host';
		host.value = config.host || '';
		host.placeholder = text( strings.hostPlaceholder, 'http://localhost' );
		host.className = 'regular-text';
		selfHostedFields.appendChild(
			field( text( strings.host, 'Host URL or IP' ), host )
		);

		var port = document.createElement( 'input' );
		port.type = 'number';
		port.name = 'port';
		port.min = '1';
		port.max = '65535';
		port.step = '1';
		port.value = 'string' === typeof config.port ? config.port : '11434';
		port.placeholder = '11434';
		port.className = 'small-text';
		selfHostedFields.appendChild( field( text( strings.port, 'Port' ), port ) );

		var selfHostedApiKey = apiKeySection( {
			clearLabel: text(
				strings.removeSelfHostedApiKey,
				'Remove saved self-hosted API key'
			),
			clearName: 'clear_self_hosted_api_key',
			hasKey: !! config.hasSelfHostedApiKey,
			help: text(
				strings.selfHostedApiKeyHelp,
				'Optional for protected self-hosted Ollama endpoints. Leave blank to keep the saved self-hosted API key.'
			),
			label: text( strings.selfHostedApiKey, 'Self-hosted API key' ),
			name: 'self_hosted_api_key',
			placeholder: text(
				strings.enterSelfHostedApiKey,
				'Enter optional self-hosted API key'
			),
			savedHelp: text(
				strings.selfHostedApiKeySaved,
				'A self-hosted API key is saved. Enter a new key to replace it.'
			)
		} );
		selfHostedFields.appendChild( selfHostedApiKey.section );

		var requestTimeout = document.createElement( 'input' );
		requestTimeout.type = 'number';
		requestTimeout.name = 'request_timeout';
		requestTimeout.min = '15';
		requestTimeout.max = '1800';
		requestTimeout.step = '1';
		requestTimeout.value =
			'string' === typeof config.requestTimeout ? config.requestTimeout : '180';
		requestTimeout.className = 'small-text';

		var requestTimeoutField = field(
			text( strings.requestTimeout, 'Text request timeout' ),
			requestTimeout
		);
		requestTimeoutField.className +=
			' zactonz-ai-provider-ollama-connector-panel__timeout';
		var timeoutUnit = createElement(
			'span',
			'zactonz-ai-provider-ollama-connector-panel__unit',
			text( strings.seconds, 'seconds' )
		);
		requestTimeoutField.appendChild( timeoutUnit );
		selfHostedFields.appendChild( requestTimeoutField );
		selfHostedFields.appendChild(
			createElement(
				'p',
				'zactonz-ai-provider-ollama-connector-panel__help',
				text( strings.requestTimeoutHelp, '' )
			)
		);
		panel.appendChild( selfHostedFields );

		var actions = createElement(
			'div',
			'zactonz-ai-provider-ollama-connector-panel__actions'
		);
		var submit = createElement(
			'button',
			'button button-secondary',
			text( strings.save, 'Save and check connection' )
		);
		submit.type = 'submit';
		actions.appendChild( submit );

		var status = createElement(
			'p',
			'zactonz-ai-provider-ollama-connector-panel__status'
		);
		actions.appendChild( status );
		panel.appendChild( actions );

		function selectedConnectionType() {
			var selected = panel.querySelector(
				'input[name="zactonz-ai-provider-ollama-connection-type"]:checked'
			);
			return selected ? selected.value : 'self_hosted';
		}

		function updateMode() {
			isCloud = 'cloud' === selectedConnectionType();
			cloudApiKey.section.hidden = ! isCloud;
			selfHostedFields.hidden = isCloud;
			help.textContent = isCloud
				? text( strings.cloudHelp, '' )
				: text( strings.selfHostedHelp, '' );
		}

		panel.addEventListener( 'change', function ( event ) {
			if ( 'zactonz-ai-provider-ollama-connection-type' === event.target.name ) {
				updateMode();
			}
		} );

		panel.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			submit.disabled = true;
			submit.textContent = text( strings.saving, 'Checking...' );
			updateStatus( status, '', '' );

			var body = new window.FormData();
			body.append( 'action', config.action );
			body.append( '_ajax_nonce', config.nonce );
			body.append( 'connection_type', selectedConnectionType() );
			body.append( 'host', host.value );
			body.append( 'port', port.value );
			body.append( 'cloud_api_key', cloudApiKey.input.value );
			body.append( 'self_hosted_api_key', selfHostedApiKey.input.value );
			if ( cloudApiKey.clearApiKey.checked ) {
				body.append( 'clear_cloud_api_key', cloudApiKey.clearApiKey.value );
			}
			if ( selfHostedApiKey.clearApiKey.checked ) {
				body.append(
					'clear_self_hosted_api_key',
					selfHostedApiKey.clearApiKey.value
				);
			}
			body.append( 'request_timeout', requestTimeout.value );

			window
				.fetch( config.ajaxUrl, {
					body: body,
					credentials: 'same-origin',
					method: 'POST'
				} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( response ) {
					if ( ! response || ! response.success || ! response.data ) {
						throw new Error(
							text(
								strings.unexpectedResponse,
								'WordPress returned an unexpected connection response.'
							)
						);
					}

					var result = response.data;
					var message = result.message || text( strings.updated, '' );
					updateStatus( status, result.connected ? 'success' : 'error', message );
					window.sessionStorage.setItem( messageKey, message );
					window.setTimeout( function () {
						window.location.reload();
					}, 650 );
				} )
				.catch( function ( error ) {
					updateStatus( status, 'error', error.message );
					submit.disabled = false;
					submit.textContent = text( strings.save, 'Save and check connection' );
				} );
		} );

		updateMode();
		var restoredMessage = window.sessionStorage.getItem( messageKey );
		if ( restoredMessage ) {
			updateStatus( status, 'notice', restoredMessage );
			window.sessionStorage.removeItem( messageKey );
		}

		card.insertAdjacentElement( 'afterend', panel );
	}

	function mountPanel() {
		var card = findOllamaCard();
		if ( card ) {
			renderPanel( card );
			return true;
		}

		return false;
	}

	if ( ! mountPanel() ) {
		var observer = new window.MutationObserver( function () {
			if ( mountPanel() ) {
				observer.disconnect();
			}
		} );
		observer.observe( document.body, {
			childList: true,
			subtree: true
		} );
	}
}() );
