jQuery(function($) {

	// embed handler
	$('#phlow_embed').on('change', function() {
		var element = $(this);

		element.prop({ disabled: true });

		var req = $.ajax({
			method: 'GET',
			url: phlowAjax.url,
			dataType: 'json',
			data: {
				action: 'phlow_embed_get',
				embed: element.val(),
				type: $('#phlow_type').val()
			}
		});

		req.done(function(res) {
			$('#phlow_embed_box').html(res.html);
		});

		req.fail(function(err) {
			console.error(err);
		});

		req.always(function() {
			element.prop({ disabled: false });
		})
	});
});
