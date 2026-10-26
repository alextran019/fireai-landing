/* FireAI CRM: đổi trạng thái nhanh và cảnh báo trùng số điện thoại. */
(function () {
	document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
		select.addEventListener('change', function () {
			select.form.submit();
		});
	});

	// Màn hình lớn: mở sẵn "Bộ lọc" để các ô lọc nằm cùng hàng như trước.
	var moreFilters = document.querySelector('.crm-more-filters');
	if (moreFilters && window.matchMedia('(min-width: 783px)').matches) {
		moreFilters.open = true;
	}

	// Chọn nhiều khách để xóa.
	var checkAll = document.getElementById('crm-check-all');
	var bulkButton = document.getElementById('crm-bulk-delete');
	var checks = document.querySelectorAll('.crm-check');
	function refreshBulk() {
		var n = document.querySelectorAll('.crm-check:checked').length;
		if (bulkButton) {
			bulkButton.disabled = n === 0;
			bulkButton.textContent = n ? 'Xóa ' + n + ' khách đã chọn' : 'Xóa khách đã chọn';
		}
	}
	checks.forEach(function (c) { c.addEventListener('change', refreshBulk); });
	if (checkAll) {
		checkAll.addEventListener('change', function () {
			checks.forEach(function (c) { c.checked = checkAll.checked; });
			refreshBulk();
		});
	}

	var phone = document.getElementById('crm-phone');
	var box = document.getElementById('crm-phone-warning');
	if (!phone || !box || !window.FireAICRM) {
		return;
	}

	var timer;
	function check() {
		var value = phone.value.replace(/\D+/g, '');
		if (value.length < 8) {
			box.hidden = true;
			return;
		}
		var url = FireAICRM.ajax + '?action=fireai_crm_check_phone&_ajax_nonce=' + encodeURIComponent(FireAICRM.nonce) +
			'&phone=' + encodeURIComponent(phone.value) + '&exclude=' + encodeURIComponent(phone.dataset.lead || 0);
		fetch(url, { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res.success || !res.data.length) {
					box.hidden = true;
					return;
				}
				box.textContent = '';
				var title = document.createElement('strong');
				title.textContent = 'Số này đã có trong CRM:';
				box.appendChild(title);
				res.data.forEach(function (item) {
					box.appendChild(document.createElement('br'));
					var a = document.createElement('a');
					a.href = item.url;
					a.target = '_blank';
					a.textContent = item.label;
					box.appendChild(a);
				});
				box.hidden = false;
			})
			.catch(function () { box.hidden = true; });
	}

	phone.addEventListener('input', function () {
		clearTimeout(timer);
		timer = setTimeout(check, 400);
	});
	if (phone.value) {
		check();
	}
})();
