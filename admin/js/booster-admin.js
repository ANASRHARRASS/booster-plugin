document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.getElementById('booster-provider-rows');
    const addButton = document.getElementById('booster-add-provider');

    if (!tableBody || !addButton) return;

    // Function to renumber all indices after changes
    function renumberRows() {
        const rows = tableBody.querySelectorAll('tr');
        rows.forEach((row, index) => {
            row.querySelectorAll('input, select').forEach(field => {
                const name = field.getAttribute('name');
                if (name) {
                    const updatedName = name.replace(/\[\d+\]/, `[${index}]`);
                    field.setAttribute('name', updatedName);
                }
            });
        });
    }

    // Function to add a new provider row
    function addProviderRow() {
        const index = tableBody.querySelectorAll('tr').length;

        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td><input type="text" name="booster_provider_list[${index}][api]" placeholder="API Group ID" required /></td>
            <td><input type="text" name="booster_provider_list[${index}][endpoint]" placeholder="Endpoint ID" required /></td>
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
            <td><button type="button" class="button booster-remove-row" aria-label="Remove Row">×</button></td>
        `;

        tableBody.appendChild(newRow);
        newRow.querySelector('input').focus(); // Focus on the first input for accessibility
    }

    // Event Listener: Add Row Button
    addButton.addEventListener('click', function () {
        addProviderRow();
    });

    // Event Listener: Remove Row
    tableBody.addEventListener('click', function (e) {
        if (e.target.classList.contains('booster-remove-row')) {
            const row = e.target.closest('tr');
            if (row) {
                row.remove();
                renumberRows(); // Renumber rows after removal
            }
        }
    });

    // Validation before form submission (example for future extensibility)
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function (e) {
            const rows = tableBody.querySelectorAll('tr');
            let isValid = true;

            rows.forEach(row => {
                const apiField = row.querySelector('input[name*="[api]"]');
                const endpointField = row.querySelector('input[name*="[endpoint]"]');

                if (!apiField.value.trim() || !endpointField.value.trim()) {
                    isValid = false;
                    apiField.classList.add('error');
                    endpointField.classList.add('error');
                } else {
                    apiField.classList.remove('error');
                    endpointField.classList.remove('error');
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill out all required fields (API Group ID and Endpoint ID).');
            }
        });
    }
});