document.documentElement.classList.add('js-ready');

const customerSelect = document.querySelector('[data-customer-location-filter="customer"]');
const locationSelect = document.querySelector('[data-customer-location-filter="location"]');

if (customerSelect && locationSelect) {
    const updateLocationOptions = () => {
        const customerId = customerSelect.value;
        const selectedOption = locationSelect.options[locationSelect.selectedIndex] ?? null;

        Array.from(locationSelect.options).forEach((option, index) => {
            if (index === 0) {
                option.hidden = false;
                return;
            }

            const optionCustomerId = option.getAttribute('data-customer-id');
            const matches = customerId === '' || optionCustomerId === customerId;

            option.hidden = !matches;
        });

        if (selectedOption !== null && selectedOption.hidden) {
            locationSelect.value = '';
        }
    };

    customerSelect.addEventListener('change', updateLocationOptions);
    updateLocationOptions();
}
