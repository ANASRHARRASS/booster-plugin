document.addEventListener('DOMContentLoaded', function () {
    // Check if we are on the booster settings page and if boosterAdminData is available
    if (typeof boosterAdminData === 'undefined' || !document.body.classList.contains('booster-settings-page')) {
        // console.log('Booster Admin JS: Not on the correct page or localized data missing.');
        // return; // Exit if not on the right page or data is missing. Consider if this check is needed or if elements are sufficient.
    }

    const tableBody = document.getElementById('booster-provider-rows');
    const addButton = document.getElementById('booster-add-provider');
    const settingsForm = document.getElementById('booster-settings-form'); // Target the correct form

    // Use localized strings if available, otherwise fallback
    const lang = (typeof boosterAdminData !== 'undefined' && boosterAdminData.lang) ? boosterAdminData.lang : {
        apiIdPlaceholder: 'API Group ID',
        endpointIdPlaceholder: 'Endpoint ID',
        removeRowLabel: 'Remove this provider row',
        rewriteLabel: 'Rewrite with AI',
        errorRequiredFields: 'Please fill out all required fields (API Group ID and Endpoint ID) for all provider rows.',
        confirmRemoveRow: 'Are you sure you want to remove this provider row?'
    };

    if (!tableBody || !addButton) {
        // console.warn('Booster Admin JS: Required elements for provider rows not found.');
        return;
    }

    // Function to renumber all indices after changes
    function renumberRows() {
        const rows = tableBody.querySelectorAll('tr.booster-provider-row');
        rows.forEach((row, index) => {
            row.querySelectorAll('input, select').forEach(field => {
                const name = field.getAttribute('name');
                if (name && name.includes('booster_provider_list')) {
                    // More robust replacement for the index
                    const updatedName = name.replace(/booster_provider_list\[\d+\]/, `booster_provider_list[${index}]`);
                    field.setAttribute('name', updatedName);

                    // Update IDs as well if they follow a similar pattern (optional, but good for label 'for' attributes)
                    const id = field.getAttribute('id');
                    if (id && id.includes('booster_provider_list_')) {
                        const updatedId = id.replace(/booster_provider_list_\d+_/, `booster_provider_list_${index}_`);
                        field.setAttribute('id', updatedId);
                        // Update corresponding label's 'for' attribute
                        const label = document.querySelector(`label[for="${id}"]`);
                        if (label) {
                            label.setAttribute('for', updatedId);
                        }
                    }
                }
            });
        });
    }

    // Function to add a new provider row
    function addProviderRow() {
        const index = tableBody.querySelectorAll('tr.booster-provider-row').length;

        const newRow = document.createElement('tr');
        newRow.classList.add('booster-provider-row');
        // Using textContent for translatable strings to prevent XSS if lang object was somehow compromised (unlikely here, but good habit)
        newRow.innerHTML = `
            <td><input type="text" name="booster_provider_list[${index}][api]" placeholder="${lang.apiIdPlaceholder}" class="widefat" aria-label="${lang.apiIdPlaceholder}" required /></td>
            <td><input type="text" name="booster_provider_list[${index}][endpoint]" placeholder="${lang.endpointIdPlaceholder}" class="widefat" aria-label="${lang.endpointIdPlaceholder}" required /></td>
            <td>
                <select name="booster_provider_list[${index}][type]" class="widefat" aria-label="Content Type">
                    <option value="news">News</option>
                    <option value="product">Product</option>
                    <option value="crypto">Crypto</option>
                </select>
            </td>
            <td>
                <label class="booster-rewrite-label">
                    <input type="checkbox" name="booster_provider_list[${index}][rewrite]" value="1" checked aria-label="${lang.rewriteLabel}" />
                    <span class="screen-reader-text">${lang.rewriteLabel}</span>
                </label>
            </td>
            <td><button type="button" class="button booster-remove-row" aria-label="${lang.removeRowLabel}">×</button></td>
        `;

        tableBody.appendChild(newRow);
        const firstInput = newRow.querySelector('input[type="text"]');
        if (firstInput) {
            firstInput.focus();
        }
        renumberRows(); // Renumber immediately after adding a new row to ensure consistency
    }

    // Event Listener: Add Row Button
    addButton.addEventListener('click', addProviderRow);

    // Event Listener: Remove Row (using event delegation)
    tableBody.addEventListener('click', function (e) {
        if (e.target.classList.contains('booster-remove-row')) {
            if (confirm(lang.confirmRemoveRow)) {
                const rowToRemove = e.target.closest('tr.booster-provider-row');
                if (rowToRemove) {
                    rowToRemove.remove();
                    renumberRows(); // Renumber rows after removal
                }
            }
        }
    });

    // Validation before form submission
    if (settingsForm) {
        settingsForm.addEventListener('submit', function (e) {
            const rows = tableBody.querySelectorAll('tr.booster-provider-row');
            let formIsValid = true;
            let firstInvalidField = null;

            rows.forEach(row => {
                const apiField = row.querySelector('input[name*="[api]"]');
                const endpointField = row.querySelector('input[name*="[endpoint]"]');

                let rowIsValid = true;

                if (apiField && endpointField) { // Check if fields exist
                    // Clear previous errors
                    apiField.classList.remove('booster-field-error');
                    endpointField.classList.remove('booster-field-error');
                    apiField.removeAttribute('aria-invalid');
                    endpointField.removeAttribute('aria-invalid');


                    if (!apiField.value.trim()) {
                        rowIsValid = false;
                        apiField.classList.add('booster-field-error');
                        apiField.setAttribute('aria-invalid', 'true');
                        if (!firstInvalidField) firstInvalidField = apiField;
                    }
                    if (!endpointField.value.trim()) {
                        rowIsValid = false;
                        endpointField.classList.add('booster-field-error');
                        endpointField.setAttribute('aria-invalid', 'true');
                        if (!firstInvalidField && apiField.value.trim()) firstInvalidField = endpointField; // only set if apiField was valid
                    }
                } else {
                    // This case should ideally not happen if rows are added correctly
                    console.warn('Booster Admin JS: API or Endpoint field missing in a provider row.');
                    rowIsValid = false;
                }

                if (!rowIsValid) {
                    formIsValid = false;
                }
            });

            if (!formIsValid) {
                e.preventDefault(); // Prevent form submission
                alert(lang.errorRequiredFields);
                if (firstInvalidField) {
                    firstInvalidField.focus(); // Focus on the first invalid field
                }
            }
        });
    } else {
        // console.warn('Booster Admin JS: Settings form not found.');
    }

    // Handle initial state: if there's one empty row and its fields are empty,
    // it might be the placeholder row. Consider if it should be removable or have different behavior.
    const initialRows = tableBody.querySelectorAll('tr.booster-provider-row');
    if (initialRows.length === 1) {
        const firstApiField = initialRows[0].querySelector('input[name*="[api]"]');
        const firstEndpointField = initialRows[0].querySelector('input[name*="[endpoint]"]');
        if (firstApiField && firstApiField.value === '' && firstEndpointField && firstEndpointField.value === '') {
            // This is likely the default empty row.
            // You could disable its remove button initially if desired:
            // const firstRemoveButton = initialRows[0].querySelector('.booster-remove-row');
            // if (firstRemoveButton) firstRemoveButton.disabled = true;
        }
    }
    // Initial renumbering just in case PHP outputs non-sequential (though unlikely with the PHP logic)
    renumberRows();
});