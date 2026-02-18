/* global mkFormData, jQuery */
(function ($) {
	'use strict';

	var isEdit  = mkFormData.editId > 0;
	var btnText = mkFormData.submitText || 'Odeslat recenzi';

	// ── Google Maps URL → lat/lng ─────────────────────────────────────
	function parseCoordsFromUrl(url) {
		// Accurate place pin: !3d{lat}!4d{lng} in the data parameter
		var m = url.match(/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/);
		if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]), accurate: true };
		// Fallback: viewport center @lat,lng
		m = url.match(/@(-?\d+\.\d+),(-?\d+\.\d+)/);
		if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]), accurate: false };
		return null;
	}

	function isShortUrl(url) {
		return /maps\.app\.goo\.gl|goo\.gl\/maps/i.test(url);
	}

	function fillCoords(coords) {
		$('#mk_f_lat').val(coords.lat.toFixed(6));
		$('#mk_f_lng').val(coords.lng.toFixed(6));
		var label = 'GPS: ' + coords.lat.toFixed(5) + ', ' + coords.lng.toFixed(5);
		if (!coords.accurate) label += ' (přibližně — doporučujeme použít plnou URL)';
		$('#mk_f_gps_status').text(label).removeClass('mk-gps-err').addClass('mk-gps-ok');
	}

	$('#mk_f_maps').on('change blur', function () {
		var url     = $(this).val().trim();
		var $status = $('#mk_f_gps_status');

		if (!url) {
			$status.text('').removeClass('mk-gps-ok mk-gps-err');
			return;
		}

		// Try parsing directly (works for full Google Maps URLs)
		var coords = parseCoordsFromUrl(url);
		if (coords) { fillCoords(coords); return; }

		// Short URL: resolve server-side then parse
		if (isShortUrl(url)) {
			$status.text('Zjišťuji souřadnice…').removeClass('mk-gps-ok mk-gps-err');
			$.post(mkFormData.ajaxUrl, {
				action: 'mk_resolve_gmaps_url',
				nonce:  mkFormData.resolveNonce,
				url:    url
			}, function (res) {
				if (res.success) {
					fillCoords(res.data);
				} else {
					$status.text('Nepodařilo se zjistit souřadnice.')
					       .removeClass('mk-gps-ok').addClass('mk-gps-err');
				}
			});
			return;
		}

		$status.text('Souřadnice nenalezeny — zkuste zkopírovat URL přímo z adresního řádku Google Maps.')
		       .removeClass('mk-gps-ok').addClass('mk-gps-err');
	});

	// ── Hashtag autocomplete ─────────────────────────────────────────
	var allHashtags = [];
	$.get(mkFormData.hashtagsUrl, function (res) {
		if (res.success) allHashtags = res.data;
	});

	$('#mk_f_hashtags').on('input', function () {
		var val  = $(this).val();
		var last = val.split(',').pop().trim().replace(/^#/, '').toLowerCase();
		var box  = $('#mk_hashtag_suggestions');

		if (!last || !allHashtags.length) { box.hide().empty(); return; }

		var matches = allHashtags.filter(function (t) {
			return t.toLowerCase().startsWith(last);
		}).slice(0, 6);

		if (!matches.length) { box.hide().empty(); return; }

		box.empty();
		matches.forEach(function (tag) {
			$('<div class="mk-autocomplete-item">#' + tag + '</div>').on('click', function () {
				var parts = val.split(',');
				parts[parts.length - 1] = '#' + tag;
				$('#mk_f_hashtags').val(parts.join(', ') + ', ');
				box.hide().empty();
			}).appendTo(box);
		});
		box.show();
	});

	$(document).on('click', function (e) {
		if (!$(e.target).closest('#mk_f_hashtags, #mk_hashtag_suggestions').length) {
			$('#mk_hashtag_suggestions').hide();
		}
	});

	// ── Form submission ──────────────────────────────────────────────
	$('#mk-review-form').on('submit', function (e) {
		e.preventDefault();

		var $form = $(this);
		var $btn  = $('#mk_submit_btn');

		var restaurant = $('#mk_f_restaurant').val().trim();
		var rating     = $('#mk_f_rating').val();
		if (!restaurant) { showMessage('Název restaurace je povinný.', false); return; }
		if (rating === '' || isNaN(parseFloat(rating))) { showMessage('Hodnocení je povinné.', false); return; }

		$btn.prop('disabled', true).text('Odesílám…');
		$('#mk_form_message').hide().removeClass('success error');

		var data = new FormData($form[0]);
		data.append('action', 'mk_submit_review');
		data.append('nonce',  mkFormData.nonce);

		$.ajax({
			url:         mkFormData.ajaxUrl,
			type:        'POST',
			data:        data,
			processData: false,
			contentType: false,
			success: function (res) {
				if (res.success) {
					showMessage(res.data.message + ' <a href="' + res.data.url + '">Zobrazit recenzi →</a>', true);
					if (!isEdit) {
						$form[0].reset();
						$('#mk_f_gps_status').text('').removeClass('mk-gps-ok mk-gps-err');
					}
				} else {
					showMessage(res.data.message || 'Nastala chyba.', false);
				}
			},
			error:    function () { showMessage('Síťová chyba. Zkuste to prosím znovu.', false); },
			complete: function () { $btn.prop('disabled', false).text(btnText); }
		});
	});

	function showMessage(text, success) {
		var $msg = $('#mk_form_message');
		$msg.html(text)
		    .addClass(success ? 'success' : 'error')
		    .removeClass(success ? 'error' : 'success')
		    .show();
		$msg[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}

}(jQuery));
