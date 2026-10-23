/* FireAI CRM: ghi nhận lượt bấm nút Gọi / Zalo kèm nguồn quảng cáo. */
(function () {
	var KEY = 'fireai_crm_utm';
	var FIELDS = ['utm_source', 'utm_campaign', 'utm_content', 'fbclid', 'gclid', 'ttclid'];
	var MAX_AGE = 30 * 24 * 3600 * 1000;

	function read() {
		try {
			var data = JSON.parse(localStorage.getItem(KEY) || 'null');
			return data && Date.now() - data.t < MAX_AGE ? data.p : {};
		} catch (e) {
			return {};
		}
	}

	// Lưu nguồn quảng cáo khi khách vào từ link có utm / fbclid / gclid, để các trang sau vẫn biết.
	var query = new URLSearchParams(window.location.search);
	var found = {};
	FIELDS.forEach(function (f) {
		if (query.get(f)) {
			found[f] = query.get(f).slice(0, 190);
		}
	});
	if (Object.keys(found).length) {
		try {
			localStorage.setItem(KEY, JSON.stringify({ t: Date.now(), p: found }));
		} catch (e) {}
	}

	document.addEventListener('click', function (e) {
		var link = e.target.closest ? e.target.closest('a[href]') : null;
		if (!link || !window.FireAICRMTrack) {
			return;
		}
		var href = link.getAttribute('href') || '';
		var type = /^tel:/i.test(href) ? 'call' : (/zalo\.me|zaloapp|zalo:/i.test(href) ? 'zalo' : '');
		if (!type) {
			return;
		}
		var body = new FormData();
		var params = read();
		body.append('type', type);
		body.append('page', window.location.href.split('#')[0]);
		Object.keys(params).forEach(function (k) {
			body.append(k, params[k]);
		});
		if (navigator.sendBeacon) {
			navigator.sendBeacon(FireAICRMTrack.url, body);
		} else {
			fetch(FireAICRMTrack.url, { method: 'POST', body: body, keepalive: true });
		}
	}, true);
})();
