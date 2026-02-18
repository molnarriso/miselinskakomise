/* global L, mkMapData */
document.addEventListener('DOMContentLoaded', function () {
	var maps = document.querySelectorAll('.mk-map');
	if (!maps.length || typeof L === 'undefined') return;

	maps.forEach(function (el) {
		// Skip single-review map (initialised inline)
		if (el.id === 'mk-single-map') return;

		var map = L.map(el).setView([49.8, 15.5], 7);

		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
		}).addTo(map);

		var cluster = L.markerClusterGroup();
		var bounds  = [];

		function ratingColor(rating) {
			if (rating >= 8) return '#16a34a';
			if (rating >= 5) return '#d97706';
			return '#dc2626';
		}

		function makeIcon(rating) {
			var color = ratingColor(rating);
			var label = rating.toFixed(1);
			var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="38" height="48" viewBox="0 0 38 48">' +
				'<path fill="' + color + '" stroke="#fff" stroke-width="2" d="M19 1C10.2 1 3 8.2 3 17c0 11.3 16 30 16 30S35 28.3 35 17C35 8.2 27.8 1 19 1z"/>' +
				'<text x="19" y="21" text-anchor="middle" dominant-baseline="middle" fill="#fff" font-size="11" font-weight="700" font-family="sans-serif">' + label + '</text>' +
				'</svg>';
			return L.divIcon({
				html: svg,
				className: '',
				iconSize: [38, 48],
				iconAnchor: [19, 48],
				popupAnchor: [0, -48]
			});
		}

		function fetchPage(page) {
			var url = mkMapData.restUrl +
				'?per_page=' + mkMapData.perPage +
				'&page=' + page +
				'&_embed=1';

			fetch(url)
				.then(function (r) {
					var total = parseInt(r.headers.get('X-WP-TotalPages') || '1', 10);
					return r.json().then(function (data) {
						return { data: data, total: total };
					});
				})
				.then(function (res) {
					res.data.forEach(function (review) {
						// Fields injected via rest_prepare_recenze filter
						var lat   = parseFloat(review.mk_latitude);
						var lng   = parseFloat(review.mk_longitude);
						if (!lat || !lng) return;

						var rating   = parseFloat(review.mk_rating) || 0;
						var name     = review.mk_restaurant_name || review.title.rendered;
						var thumb    = '';

						if (review._embedded && review._embedded['wp:featuredmedia'] &&
							review._embedded['wp:featuredmedia'][0]) {
							thumb = review._embedded['wp:featuredmedia'][0].source_url || '';
						}

						var imgHtml = thumb
							? '<img src="' + thumb + '" style="width:160px;height:100px;object-fit:cover;border-radius:4px;margin-bottom:8px;display:block;">'
							: '';

						var popup =
							'<div style="min-width:160px;font-family:inherit;">' +
							imgHtml +
							'<strong style="font-size:0.95rem;display:block;margin-bottom:6px;">' + name + '</strong>' +
							'<div style="display:flex;align-items:center;gap:8px;">' +
							'<span style="background:' + ratingColor(rating) + ';color:#fff;padding:2px 8px;border-radius:4px;font-size:0.85rem;font-weight:700;">' + rating.toFixed(1) + '</span>' +
							'<a href="' + review.link + '" style="font-size:0.82rem;font-weight:600;color:#2563eb;border:1.5px solid #2563eb;border-radius:20px;padding:2px 10px;text-decoration:none;">Detail</a>' +
							'</div>' +
							'</div>';

						var marker = L.marker([lat, lng], { icon: makeIcon(rating) })
							.bindPopup(popup);
						cluster.addLayer(marker);
						bounds.push([lat, lng]);
					});

					if (page < res.total) {
						fetchPage(page + 1);
					} else {
						map.addLayer(cluster);
						if (bounds.length) {
							map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
						}
					}
				})
				.catch(function (err) {
					console.error('MK map fetch error:', err);
				});
		}

		fetchPage(1);
	});
});
