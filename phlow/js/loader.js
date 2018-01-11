jQuery(function($) {

	var widgets = $('div[id*=phlow_widget]'),
		loadedWidgets = [],
		seenImages = [];

	widgets.each(function() {
		var widget = $(this),
			data = widget.data() || {};

		if (data.type == undefined ||
			data.source == undefined ||
			data.context == undefined)
		{
			return;
		}

		loadImages.call(widget, data);
	});

	function loadImages(data) {
		var widget = this,
			container = $('ul', widget),
			loader = getLoader();

		container.prepend(loader);

		data.action = 'phlow_images_get';

		var req = $.ajax({
			method: 'GET',
			url: phlowAjax.url,
			dataType: 'json',
			data: data
		});

		req.done(function(res) {
			if (!res.success) {
				return console.error(res.errors);
			}

			var images = [];

			res.data.forEach(function(image) {
				var img = $('<img />').prop({ src: image.src, alt: image.id }).addClass('images-view'),
					link = $('<a />').prop({ target: '_blank', href: image.url }).append(img),
					item = $('<li />').append(link);

				container.prepend(item);
				loadedWidgets.push(widget);
			});
		});

		req.fail(function(err) {
			console.error(err);
		});
		
		req.always(function() {
			loader.remove();
		});
	}

	function getLoader() {
		return $('<div />')
			.addClass('phlow-loader')
			.append('<svg width="32px" height="32px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid"><rect x="0" y="0" width="100" height="100" fill="none"></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(0 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(30 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.08333333333333333s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(60 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.16666666666666666s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(90 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.25s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(120 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.3333333333333333s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(150 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.4166666666666667s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(180 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.5s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(210 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.5833333333333334s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(240 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.6666666666666666s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(270 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.75s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(300 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.8333333333333334s" repeatCount="indefinite"/></rect><rect  x="46.5" y="40" width="7" height="20" rx="5" ry="5" fill="#f44336" transform="rotate(330 50 50) translate(0 -30)">  <animate attributeName="opacity" from="1" to="0" dur="1s" begin="0.9166666666666666s" repeatCount="indefinite"/></rect></svg>');
	}

	function sendSeenAction(photoId, payload) {
		if (!photoId || seenImages.indexOf(photoId) !== -1) {
			return;
		}
		if (!payload.source || !payload.context) {
			return;
		}

		seenImages.push(photoId);

		var req = $.ajax({
			method: 'POST',
			url: phlowAjax.url,
			dataType: 'json',
			data: {
				action: 'phlow_photo_seen',
				source: payload.source,
				context: payload.context,
				photoId: photoId
			}
		});

		req.done(function(res) {
			if (!res.success) {
				seenImages.splice(seenImages.indexOf(photoId), 1);
				console.error(res.errors);
			}
		});

		req.fail(function(err) {
			seenImages.splice(seenImages.indexOf(photoId), 1);
			console.error(err);
		});
	}

	function scrollingHandler() {
		loadedWidgets.forEach(function(widget) {
			if (widget.visible()) {
				var payload = widget.data();

				widget.find('img').each(function() {
					var photoId = $(this).prop('alt');
					sendSeenAction(photoId, payload);
				});
			}
		});
	}

	$(window).scroll(scrollingHandler);
});
