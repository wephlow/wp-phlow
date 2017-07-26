jQuery(function($) {

	var props = {
		sourceSelect: $('#phlow_source'),
		typeSelect: $('#phlow_type'),
		sourceBox: $('#phlow_source_box')
	};

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

	// initialization
	var source = Number(props.sourceSelect.val());
	sourcesInit(source);
});
