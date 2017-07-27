jQuery(function($) {

	var props = {
		sourceSelect: $('#phlow_source'),
		typeSelect: $('#phlow_type'),
		sourceBox: $('#phlow_source_box')
	};

	/**
	 * Receiving HTML block of a specific source
	 */
	var getSourceBlock = function() {
		var element = $(this),
			source = Number(props.sourceSelect.val()),
			type = props.typeSelect.val();

		element.prop({ disabled: true });

		var req = $.ajax({
			method: 'GET',
			url: phlowAjax.url,
			dataType: 'json',
			data: {
				action: 'phlow_source_get',
				source: source,
				type: type
			}
		});

		req.done(function(res) {
			props.sourceBox.html(res.html);
			sourcesInit(source);
		});

		req.fail(function(err) {
			console.error(err);
		});

		req.always(function() {
			element.prop({ disabled: false });
		});
	};

	// source handler
	props.sourceSelect.on('change', getSourceBlock.bind(this));

	// type handler
	props.typeSelect.on('change', getSourceBlock.bind(this));

	/**
	 * Running source handlers
	 */
	var sourcesInit = function(src) {
		switch(src) {
			case 2:
				magazineHandler();
				break;
			case 3:
				momentHandler();
				break;
		}
	}

	/**
	 * Magazines search handler
	 */
	var magazineHandler = function() {
		var inputName = $('#phlow_magazine_name'),
			inputId = $('#phlow_magazine_id');

		inputName.easyAutocomplete({
			url: function(string) {
				return phlowAjax.url + '?action=phlow_magazines_search&string=' + string;
			},
			getValue: 'title',
			requestDelay: 200,
			list: {
				onSelectItemEvent: function() {
					var magazine = inputName.getSelectedItemData();
					inputId.val(magazine.magazineId);
				}
			}
		});
	}

	/**
	 * Moments search handler
	 */
	var momentHandler = function() {
		var inputName = $('#phlow_moment_name'),
			inputId = $('#phlow_moment_id');

		inputName.easyAutocomplete({
			url: function(string) {
				return phlowAjax.url + '?action=phlow_moments_search&string=' + string;
			},
			getValue: 'name',
			requestDelay: 200,
			list: {
				onSelectItemEvent: function() {
					var moment = inputName.getSelectedItemData();
					inputId.val(moment.eventId);
				}
			}
		});
	}

	/**
	 * Copying to the clipboard handler
	 */
	var clipboardInit = function() {
		var textarea = $('#phlow_shortcode'),
			block = $('#phlow_shortcode_box');
		
		if (!textarea.length) {
			return;
		}

		var button = $('<button>')
			.addClass('button')
			.attr({
				'data-clipboard-target': '#' + textarea.prop('id'),
				'id': 'phlow_clipboard'
			})
			.text('Copy to clipboard');

		block.after(button);

		new Clipboard('#phlow_clipboard');
	}

	// initialization
	var source = Number(props.sourceSelect.val());
	sourcesInit(source);
	clipboardInit();
});
