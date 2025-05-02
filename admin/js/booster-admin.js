document.addEventListener('DOMContentLoaded', function () {
	const tableBody = document.getElementById('booster-provider-rows');
	const addButton = document.getElementById('booster-add-provider');

	if (!tableBody || !addButton) return;

	addButton.addEventListener('click', function () {
		const index = tableBody.querySelectorAll('tr').length;

		const newRow = document.createElement('tr');
		newRow.innerHTML = `
			<td><input type="text" name="booster_provider_list[${index}][api]" /></td>
			<td><input type="text" name="booster_provider_list[${index}][endpoint]" /></td>
			<td>
				<select name="booster_provider_list[${index}][type]">
					<option value="news">News</option>
					<option value="product">Product</option>
					<option value="crypto">Crypto</option>
				</select>
			</td>
			<td>
			    <label>
			        <input type="checkbox" name="booster_provider_list[${index}][rewrite]" value="1" checked />
			        <span>Rewrite</span>
			    </label>
			</td>
			<td><button type="button" class="button booster-remove-row">×</button></td>
		`;

		tableBody.appendChild(newRow);
	});

	tableBody.addEventListener('click', function (e) {
		if (e.target.classList.contains('booster-remove-row')) {
			const row = e.target.closest('tr');
			if (row) row.remove();
		}
	});
});
