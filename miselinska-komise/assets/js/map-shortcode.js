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
			var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="36" viewBox="0 0 28 36">' +
				'<path fill="' + color + '" stroke="#fff" stroke-width="1.5" d="M14 1C7.4 1 2 6.4 2 13c0 8.5 12 22 12 22S26 21.5 26 13C26 6.4 20.6 1 14 1z"/>' +
				'<circle fill="#fff" cx="14" cy="13" r="5"/>' +
				'</svg>';
			return L.divIcon({
				html: svg,
				className: '',
				iconSize: [28, 36],
				iconAnchor: [14, 36],
				popupAnchor: [0, -36]
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
						var mapsUrl  = review.mk_google_maps_url || '';

						if (review._embedded && review._embedded['wp:featuredmedia'] &&
							review._embedded['wp:featuredmedia'][0]) {
							thumb = review._embedded['wp:featuredmedia'][0].source_url || '';
						}

						var imgHtml = thumb
							? '<img src="' + thumb + '" style="width:160px;height:100px;object-fit:cover;border-radius:4px;margin-bottom:6px;display:block;">'
							: '';

						var popup =
							'<div style="min-width:160px;font-family:inherit;">' +
							imgHtml +
							'<strong style="font-size:0.95rem;">' + name + '</strong><br>' +
							'<span style="background:' + ratingColor(rating) + ';color:#fff;padding:2px 8px;border-radius:4px;font-size:0.85rem;font-weight:700;">' +
							rating.toFixed(1) + '</span><br>' +
							(mapsUrl ? '<a href="' + mapsUrl + '" target="_blank" rel="noopener" style="font-size:0.82rem;">Google Maps</a><br>' : '') +
							'<a href="' + review.link + '" style="font-size:0.82rem;">Číst recenzi →</a>' +
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
