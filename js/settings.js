jQuery(function($) {

	var props = {
		embedSelect: $('#phlow_embed'),
		typeSelect: $('#phlow_type'),
		embedBox: $('#phlow_embed_box')
	};

	var embedHandler = function() {
		var element = $(this);

		element.prop({ disabled: true });

		var req = $.ajax({
			method: 'GET',
			url: phlowAjax.url,
			dataType: 'json',
			data: {
				action: 'phlow_embed_get',
				embed: props.embedSelect.val(),
				type: props.typeSelect.val()
			}
		});

		req.done(function(res) {
			props.embedBox.html(res.html);
		});

		req.fail(function(err) {
			console.error(err);
		});

		req.always(function() {
			element.prop({ disabled: false });
		});
	};

	// embed handler
	props.embedSelect.on('change', embedHandler.bind(this));

	// type handler
	props.typeSelect.on('change', embedHandler.bind(this));
});
