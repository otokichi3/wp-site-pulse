(function() {
	var tbody = document.getElementById('wpsp-url-rows');
	var addBtn = document.getElementById('wpsp-add-url');
	var maxRows = 10;

	function getNextIndex() {
		var inputs = tbody.querySelectorAll('input[name="wpsp_url_auth[]"]');
		var max = -1;
		inputs.forEach(function(el) { max = Math.max(max, parseInt(el.value, 10) || 0); });
		return max + 1;
	}

	addBtn.addEventListener('click', function() {
		if (tbody.rows.length >= maxRows) return;
		var idx = getNextIndex();
		var tr = document.createElement('tr');
		tr.innerHTML =
			'<td><input type="text" name="wpsp_urls[]" value="" class="large-text code" placeholder="https://example.com/" /></td>' +
			'<td style="text-align:center;"><input type="checkbox" name="wpsp_url_auth[]" value="' + idx + '" /></td>' +
			'<td><button type="button" class="button-link wpsp-remove-row" title="&#10005;">&times;</button></td>';
		tbody.appendChild(tr);
		if (tbody.rows.length >= maxRows) addBtn.disabled = true;
	});

	tbody.addEventListener('click', function(e) {
		if (e.target.classList.contains('wpsp-remove-row')) {
			e.target.closest('tr').remove();
			addBtn.disabled = false;
		}
	});
})();
