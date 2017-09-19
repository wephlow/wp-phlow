(function($) {

	"use strict";

    // Editor variables
    var activeEditor = top.tinymce.activeEditor,
        windowManager = activeEditor.windowManager,
        wmParams = windowManager.getParams();

    // Dialog variables
    var sourceSelect = $('#phlow_source'),
    	typeSelect = $('#phlow_type'),
    	nudityCheckbox = $('#phlow_nudity'),
    	violenceCheckbox = $('#phlow_violence'),
        ownedCheckbox =  $('#phlow_owned'),
    	sourceBox = $('#phlow_source_box'),
    	typeBox = $('#phlow_type_box'),
    	submitButton = $('#phlow_submit'),
    	loader = $('#phlow_loader');

    // Data
    var storage = {
    	shortcode: {}
    };

    function setShortcodeData(key, value) {
    	storage.shortcode[key] = value;
    }

    function fetchMagazines() {
    	var d = $.Deferred();

    	loader.show();

    	var req = $.ajax({
    		mathod: 'GET',
    		url: wmParams.ajax_url,
    		dataType: 'json',
    		data: {
				action: 'phlow_magazines_get'
			}
    	});

    	req.done(function(res) {
    		storage.magazines = res.data;
    		d.resolve();
    	});

    	req.fail(function(err) {
    		console.error(err);
    		d.reject(err);
    	});

    	req.always(function() {
    		loader.hide();
    	});

    	return d.promise();
    }

    function addTemplateHTML(tpl, toType) {
    	var content = tmpl(tpl.id, tpl.data);

    	if (toType) {
    		typeBox.html(content);
    	}
    	else {
    		sourceBox.html(content);
    	}
    	
    	tpl.handler && tpl.handler();
    }

    // Sources handler
    function sourceHandler() {
    	var sourceIndex = Number(this.value),
    		tpl = null;

    	switch (sourceIndex) {
    		case 1: {
    			tpl = {
    				id: 'phlow_mymagazines_tmpl',
    				data: {
    					magazines: storage.magazines
    				},
    				handler: myMagazinesHandler.bind(null)
    			};
    		} break;

    		case 2: {
    			tpl = {
    				id: 'phlow_magazines_tmpl',
    				data: {},
    				handler: magazinesHandler.bind(null)
    			};
    		} break;

    		case 3: {
    			tpl = {
    				id: 'phlow_moments_tmpl',
    				data: {},
    				handler: momentsHandler.bind(null)
    			};
    		} break;

    		default: {
    			tpl = {
    				id: 'phlow_tags_tmpl',
    				data: {},
    				handler: streamsHandler.bind(null)
    			};
    		} break;
    	}

    	setShortcodeData('context', ''); // clear context data
    	setShortcodeData('source', sourceIndex);

    	addTemplateHTML(tpl);
    }

    // Types handler
    function typeHandler() {
    	var typeIndex = Number(this.value),
    		tpl = null;

    	switch (typeIndex) {
    		case 2: {
    			tpl = {
    				id: 'phlow_size_tmpl',
    				data: {},
    				handler: sizeHandler.bind(null)
    			};
    		} break;

    		case 1: {
    			tpl = {
    				id: 'phlow_empty_tmpl',
    				data: {}
    			};
    		} break;

    		default: {
    			tpl = {
    				id: 'phlow_empty_tmpl',
    				data: {}
    			};
    		} break;
    	}

    	setShortcodeData('width', 0); // clear width data
    	setShortcodeData('height', 0); // clear height data
    	setShortcodeData('type', typeIndex);

    	addTemplateHTML(tpl, true);
    }

    // Streams handler
    function streamsHandler() {
    	var input = $('#phlow_context');

    	input.on('input', function() {
    		setShortcodeData('context', this.value);
    	});
    }

    // My magazines handler
    function myMagazinesHandler() {
    	var select = $('#phlow_context');
    	setShortcodeData('context', select.val());

    	select.on('change', function() {
    		setShortcodeData('context', this.value);
    	});
    }

	// Magazines search handler
	function magazinesHandler() {
		var input = $('#phlow_context');

		input.easyAutocomplete({
			url: function(string) {
				return wmParams.ajax_url + '?action=phlow_magazines_search&string=' + string;
			},
			getValue: 'title',
			requestDelay: 200,
			list: {
				onSelectItemEvent: function() {
					var magazine = input.getSelectedItemData();
					setShortcodeData('context', magazine.magazineId);
				}
			}
		});
	}

	// Moments search handler
	function momentsHandler() {
		var input = $('#phlow_context');

		input.easyAutocomplete({
			url: function(string) {
				return wmParams.ajax_url + '?action=phlow_moments_search&string=' + string;
			},
			getValue: 'name',
			requestDelay: 200,
			list: {
				onSelectItemEvent: function() {
					var moment = input.getSelectedItemData();
					setShortcodeData('context', moment.eventId);
				}
			}
		});
	}

	// Size handler
	function sizeHandler() {
		var widthInput = $('#phlow_width'),
			heightInput = $('#phlow_height');

		var width = widthInput.val();
		setShortcodeData('width', Number(width));

		var height = heightInput.val();
		setShortcodeData('height', Number(height));

		widthInput.on('input', function() {
			setShortcodeData('width', Number(this.value));
		});

		heightInput.on('input', function() {
			setShortcodeData('height', Number(this.value));
		});
	}

	// Validate shortcode data
	function validateShortcodeData() {
		var data = storage.shortcode,
			errors = [];

		// context
		if (!data.context) {
			errors.push('context');
		}

		// phlow_stream type
		if (data.type == 2) {
			!data.width && errors.push('width');
			!data.height && errors.push('height');
		}

		return errors;
	}

	// Generate shortcode
	function generateShortcode() {
		// prepare data
		var data = storage.shortcode,
			nudity = data.nudity,
			violence = data.violence,
            owned = data.owned,
			context = data.context.trim(),
			source, type, width, height;

		alert(JSON.stringify(data));

		if (data.source == 1 || data.source == 2) {
			source = 'magazine';
		}
		else if (data.source == 3) {
			source = 'moment';
		}
		else {
			source = 'streams';
			if (data.type == 2){
                context = context.replace(',', '-');
			} else {
                context = context.replace('-', ',');
			}
			context = context.replace(' ', '');
			context = context.replace('#', '');
		}

		if (data.type == 1) {
			type = 'phlow_line';
		}
		else if (data.type == 2) {
			type = 'phlow_stream';
			width = data.width;
			height = data.height;
		}
		else {
			type = 'phlow_group';
		}

		// generate shortcode
		var parts = [];

		parts.push(type);
		parts.push('source="' + source + '"');
		parts.push('context="' + context + '"');
		parts.push('nudity="' + nudity + '"');
		parts.push('violence="' + violence + '"');
		parts.push('owned="' + owned + '"');

		if (width) {
			parts.push('width="' + width + '"');
		}

		if (height) {
			parts.push('height="' + height + '"');
		}

		return '[' + parts.join(' ') + ']';
	}

	// Submit handler
	function submitHandler(e) {
		e.preventDefault();
		
		// delete previous errors
		$('.form-error').removeClass('form-error');

		var errors = validateShortcodeData();

		if (errors.length) {
			// set error classes
			errors.forEach(function(field) {
				$('#phlow_' + field).addClass('form-error');
			});

			return;
		}

		var shortcode = generateShortcode();

		activeEditor.execCommand('mceInsertContent', false, shortcode);
		windowManager.close();
	}

    // Initialization
    function dialogInit() {
    	// fetch data
    	fetchMagazines().then(function() {
    		// set defaults
	    	var nudity = Number(wmParams.nudity);
	    	nudityCheckbox.prop('checked', nudity);
	    	setShortcodeData('nudity', nudity);

	    	var violence = Number(wmParams.violence);
	    	violenceCheckbox.prop('checked', violence);
	    	setShortcodeData('violence', violence);

            var owned = Number(wmParams.owned);
            ownedCheckbox.prop('checked', owned);
            setShortcodeData('owned', owned);

	    	var type = typeSelect.val();
	    	typeHandler.call({ value: type });

	    	var source = sourceSelect.val();
	    	sourceHandler.call({ value: source });

	    	// select handlers
	    	sourceSelect.on('change', sourceHandler);
	    	typeSelect.on('change', typeHandler);

	    	ownedCheckbox.on('change', function(){
                setShortcodeData('owned', (this.checked) ? 1 : 0);
			});

            violenceCheckbox.on('change', function(){
                setShortcodeData('violence', (this.checked) ? 1 : 0);
            });

            nudityCheckbox.on('change', function(){
                setShortcodeData('nudity', (this.checked) ? 1 : 0);
            });

	    	// button handler
	    	submitButton.on('click', submitHandler);
    	});
    }

    dialogInit();

})(jQuery);
