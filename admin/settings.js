( function ( window, document ) {
	'use strict';

	var textDomain = 'zactonz-ai-provider-ollama';
	var wp = window.wp || {};
	var i18n = wp.i18n || {};
	var __ = 'function' === typeof i18n.__ ? i18n.__ : function ( text ) {
		return text;
	};
	var _n = 'function' === typeof i18n._n ? i18n._n : function ( single, plural, count ) {
		return 1 === count ? single : plural;
	};
	var sprintf = 'function' === typeof i18n.sprintf ? i18n.sprintf : function ( template, value ) {
		return template.replace( '%d', value );
	};

	function getConfig() {
		return window.zctzOllamaAiConnectorSettings || window.zactonzOllamaAiConnectorSettings || null;
	}

	function capabilityLabel( capability ) {
		var labels = {
			text_generation: __( 'Text generation', textDomain ),
			image_generation: __( 'Image generation', textDomain ),
			text_to_speech_conversion: __( 'Text-to-speech', textDomain ),
			speech_generation: __( 'Speech generation', textDomain ),
			music_generation: __( 'Music generation', textDomain ),
			video_generation: __( 'Video generation', textDomain ),
			embedding_generation: __( 'Embedding generation', textDomain ),
			chat_history: __( 'Chat history', textDomain )
		};

		if ( labels[ capability ] ) {
			return labels[ capability ];
		}

		return String( capability )
			.split( '_' )
			.map( function ( word ) {
				return word.charAt( 0 ).toUpperCase() + word.slice( 1 );
			} )
			.join( ' ' );
	}

	function modelHasVision( model ) {
		var options = Array.isArray( model.supportedOptions ) ? model.supportedOptions : [];

		return options.some( function ( option ) {
			if ( ! option || ( 'inputModalities' !== option.name && 'input_modalities' !== option.name ) ) {
				return false;
			}

			if ( ! Array.isArray( option.supportedValues ) ) {
				return false;
			}

			return option.supportedValues.some( function ( values ) {
				return Array.isArray( values ) && values.indexOf( 'image' ) !== -1;
			} );
		} );
	}

	function getCapabilities( model ) {
		var capabilities = {};

		if ( Array.isArray( model.supportedCapabilities ) ) {
			model.supportedCapabilities.forEach( function ( capability ) {
				capabilities[ capability ] = {
					key: capability,
					label: capabilityLabel( capability )
				};
			} );
		}

		if ( modelHasVision( model ) ) {
			capabilities.vision = {
				key: 'vision',
				label: __( 'Vision', textDomain )
			};
		}

		if ( model.features && 'object' === typeof model.features ) {
			Object.keys( model.features ).forEach( function ( feature ) {
				if ( model.features[ feature ] && ! capabilities[ feature ] && 'text' !== feature ) {
					capabilities[ feature ] = {
						key: feature,
						label: capabilityLabel( 'structured_output' === feature ? 'Structured output' : feature )
					};
				}
			} );
		}

		return Object.keys( capabilities ).map( function ( key ) {
			return capabilities[ key ];
		} );
	}

	function createCapabilityPill( capability ) {
		var pill = document.createElement( 'span' );

		pill.className = 'zactonz-ai-provider-ollama-capability-pill';
		pill.textContent = capability.label;

		if ( 'vision' === capability.key ) {
			pill.classList.add( 'zactonz-ai-provider-ollama-capability-pill--vision' );
		}

		if ( 'image_generation' === capability.key ) {
			pill.classList.add( 'zactonz-ai-provider-ollama-capability-pill--image-generation' );
		}

		return pill;
	}

	function renderModels( models, container ) {
		var count;
		var intro;
		var list;

		container.innerHTML = '';

		if ( ! models.length ) {
			intro = document.createElement( 'p' );
			intro.textContent = __( 'No models found. Pull a model with ollama pull <model> and reload this page.', textDomain );
			container.appendChild( intro );
			return;
		}

		count = models.length;
		intro = document.createElement( 'p' );
		intro.textContent = sprintf(
			_n( '%d model available:', '%d models available:', count, textDomain ),
			count
		);
		container.appendChild( intro );

		list = document.createElement( 'ul' );
		list.className = 'zactonz-ai-provider-ollama-models-list';

		models.forEach( function ( model ) {
			var item = document.createElement( 'li' );
			var code = document.createElement( 'code' );
			var modelCapabilities = getCapabilities( model );

			item.className = 'zactonz-ai-provider-ollama-model-item';
			code.textContent = model.id;
			item.appendChild( code );

			if ( modelCapabilities.length ) {
				var capabilityWrapper = document.createElement( 'span' );

				capabilityWrapper.className = 'zactonz-ai-provider-ollama-capabilities';
				modelCapabilities.forEach( function ( capability ) {
					capabilityWrapper.appendChild( createCapabilityPill( capability ) );
				} );
				item.appendChild( capabilityWrapper );
			}

			list.appendChild( item );
		} );

		container.appendChild( list );
	}

	function populateModelSelects( models ) {
		var selects = document.querySelectorAll( '.zactonz-ai-provider-ollama-model-select' );

		Array.prototype.forEach.call( selects, function ( select ) {
			var seen = {};
			var capability = select.dataset.capability || 'text';
			var selected = select.dataset.selected || '';

			while ( select.options.length > 1 ) {
				select.remove( 1 );
			}

			Array.prototype.forEach.call( select.options, function ( option ) {
				seen[ option.value ] = true;
			} );

			models.forEach( function ( model ) {
				var option;

				if ( ! model || ! model.id || seen[ model.id ] || ! modelSupportsTask( model, capability ) ) {
					return;
				}

				option = document.createElement( 'option' );
				option.value = model.id;
				option.textContent = model.id;

				if ( model.id === select.dataset.selected ) {
					option.selected = true;
				}

				select.appendChild( option );
				seen[ model.id ] = true;
			} );

			if ( selected && ! seen[ selected ] ) {
				var unavailable = document.createElement( 'option' );
				unavailable.value = selected;
				unavailable.textContent = selected + ' ' + __( '(unavailable or incompatible)', textDomain );
				unavailable.selected = true;
				select.appendChild( unavailable );
			}
		} );
	}

	function modelSupportsTask( model, task ) {
		var features = model.features || {};
		var capabilities = Array.isArray( model.supportedCapabilities ) ? model.supportedCapabilities : [];

		if ( 'text' === task ) {
			return !! features.text || capabilities.indexOf( 'text_generation' ) !== -1;
		}
		if ( 'vision' === task ) {
			return !! features.vision || modelHasVision( model );
		}
		if ( 'image' === task ) {
			return !! features.image || capabilities.indexOf( 'image_generation' ) !== -1;
		}
		if ( 'embedding' === task ) {
			return !! features.embedding || capabilities.indexOf( 'embedding_generation' ) !== -1;
		}
		if ( 'tools' === task ) {
			return !! features.tools;
		}

		return false;
	}

	function setStatus( status, message, isError ) {
		status.textContent = message;
		status.style.color = isError ? '#d63638' : '';
	}

	function loadModels() {
		var config = getConfig();
		var container = document.getElementById( 'ollama-models-container' );
		var status = document.getElementById( 'ollama-model-status' );

		if ( ! config || ! wp.apiFetch ) {
			return;
		}

		if ( status ) {
			setStatus( status, __( 'Loading models...', textDomain ), false );
		}

		wp.apiFetch( { url: config.ajaxUrl } )
			.then( function ( response ) {
				if ( ! response || ! response.success || ! Array.isArray( response.data ) ) {
					if ( status ) {
						setStatus( status, __( 'Failed to load models.', textDomain ), true );
					}
					return;
				}

				if ( status ) {
					setStatus( status, '', false );
				}

				if ( container ) {
					renderModels( response.data, container );
				}

				populateModelSelects( response.data );
			} )
			.catch( function ( error ) {
				var fallback = __( 'Could not connect to load models.', textDomain );
				var message = error && 'string' === typeof error.message ? error.message : fallback;

				if ( status ) {
					setStatus( status, message, true );
				}
			} );
	}

	function initializeConnectionRows() {
		var radios = document.querySelectorAll( 'input[name="zctz_ollama_ai_connector_settings[connection_type]"]' );
		var selfHostedRows = document.querySelectorAll( '.zactonz-ai-provider-ollama-self-hosted-row' );
		var cloudRows = document.querySelectorAll( '.zactonz-ai-provider-ollama-cloud-row' );

		function updateRows() {
			var cloudInput = document.getElementById( 'zctz_ollama_ai_connector_settings-connection-cloud' );
			var isCloud = !! ( cloudInput && cloudInput.checked );

			Array.prototype.forEach.call( selfHostedRows, function ( row ) {
				row.hidden = isCloud;
			} );

			Array.prototype.forEach.call( cloudRows, function ( row ) {
				row.hidden = ! isCloud;
			} );
		}

		Array.prototype.forEach.call( radios, function ( input ) {
			input.addEventListener( 'change', updateRows );
		} );

		updateRows();
	}

	function initializeThinkingSupport() {
		var config = getConfig();
		var modelSelect = document.getElementById( 'zctz_ollama_ai_connector_settings-model-text' );
		var thinkingSelect = document.getElementById( 'zctz_ollama_ai_connector_settings-thinking' );
		var supportMessage = document.getElementById( 'zctz_ollama_ai_connector_settings-thinking-support' );
		var strings = config && config.thinkingStrings ? config.thinkingStrings : {};

		function setThinkingState( message ) {
			thinkingSelect.disabled = true;
			supportMessage.textContent = message;
		}

		function updateThinkingSupport() {
			if ( ! modelSelect.value ) {
				setThinkingState( strings.chooseModel || '' );
				return;
			}

			setThinkingState( strings.checking || '' );

			wp.apiFetch( {
				url: config.capabilitiesAjaxUrl + '&model=' + encodeURIComponent( modelSelect.value )
			} )
				.then( function ( response ) {
					var hasThinkingSupport = !! (
						response &&
						response.success &&
						response.data &&
						response.data.thinking
					);

					thinkingSelect.disabled = ! hasThinkingSupport;
					supportMessage.textContent = hasThinkingSupport ? ( strings.supported || '' ) : ( strings.unsupported || '' );
				} )
				.catch( function () {
					setThinkingState( strings.unsupported || '' );
				} );
		}

		if ( ! config || ! config.capabilitiesAjaxUrl || ! wp.apiFetch || ! modelSelect || ! thinkingSelect || ! supportMessage ) {
			return;
		}

		modelSelect.addEventListener( 'change', updateThinkingSupport );
		updateThinkingSupport();
	}

	function initializeDiagnostics() {
		var config = getConfig();
		var button = document.getElementById( 'zctz-ollama-run-diagnostics' );
		var results = document.getElementById( 'zctz-ollama-diagnostics-results' );
		var spinner = document.getElementById( 'zctz-ollama-diagnostics-spinner' );

		if ( ! config || ! config.diagnosticsAjaxUrl || ! wp.apiFetch || ! button || ! results ) {
			return;
		}

		button.addEventListener( 'click', function () {
			button.disabled = true;
			spinner.classList.add( 'is-active' );
			results.textContent = __( 'Running diagnostics…', textDomain );

			wp.apiFetch( { url: config.diagnosticsAjaxUrl } )
				.then( function ( response ) {
					if ( ! response || ! response.success || ! response.data ) {
						throw new Error( __( 'Diagnostics returned an unexpected response.', textDomain ) );
					}
					renderDiagnostics( response.data, results );
				} )
				.catch( function ( error ) {
					results.textContent = error && error.message ? error.message : __( 'Diagnostics failed.', textDomain );
					results.className = 'notice notice-error inline';
				} )
				.finally( function () {
					button.disabled = false;
					spinner.classList.remove( 'is-active' );
				} );
		} );
	}

	function renderDiagnostics( report, container ) {
		var rows = [
			[ __( 'Connection', textDomain ), report.connected ? __( 'Connected', textDomain ) : __( 'Not connected', textDomain ) ],
			[ __( 'Endpoint', textDomain ), report.endpoint || '—' ],
			[ __( 'HTTP status', textDomain ), report.httpStatus || '—' ],
			[ __( 'Latency', textDomain ), String( report.latencyMs ) + ' ms' ],
			[ __( 'Ollama version', textDomain ), report.ollamaVersion || __( 'Cloud or unavailable', textDomain ) ],
			[ __( 'Available models', textDomain ), String( report.modelCount ) ],
			[ __( 'AI Client version', textDomain ), report.aiClientVersion || __( 'Unavailable', textDomain ) ],
			[ __( 'Embeddings', textDomain ), report.embeddingsReady ? __( 'Ready', textDomain ) : __( 'Requires WordPress 7.1 / PHP AI Client 1.4', textDomain ) ],
			[ __( 'Streaming', textDomain ), report.streamingReady ? __( 'Ready', textDomain ) : __( 'PHP cURL is unavailable', textDomain ) ]
		];
		var table = document.createElement( 'table' );
		var tbody = document.createElement( 'tbody' );

		container.innerHTML = '';
		container.className = report.connected ? 'notice notice-success inline' : 'notice notice-error inline';
		rows.forEach( function ( row ) {
			var tr = document.createElement( 'tr' );
			var th = document.createElement( 'th' );
			var td = document.createElement( 'td' );
			th.scope = 'row';
			th.textContent = row[ 0 ];
			td.textContent = row[ 1 ];
			tr.appendChild( th );
			tr.appendChild( td );
			tbody.appendChild( tr );
		} );
		table.appendChild( tbody );
		container.appendChild( table );

		if ( report.error ) {
			var error = document.createElement( 'p' );
			error.textContent = report.error;
			container.appendChild( error );
		}
		if ( report.missingDefaults && Object.keys( report.missingDefaults ).length ) {
			var warning = document.createElement( 'p' );
			warning.textContent = __( 'One or more selected default models are unavailable. Review the model selectors above.', textDomain );
			container.appendChild( warning );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initializeConnectionRows();
		initializeThinkingSupport();
		initializeDiagnostics();
		loadModels();
	} );
}( window, document ) );
