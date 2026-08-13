<script>
(() => {
    const toggle = document.getElementById('is-fuel-expense');
    const panel = document.getElementById('fuel-expense-fields');
    const type = document.getElementById('transaction-type');
    const category = document.getElementById('transaction-category');
    const amount = document.getElementById('transaction-amount');
    const maintenancePanel = document.getElementById('maintenance-expense-fields');
    const vehicle = panel.querySelector('[name="vehicle_id"]');
    const tanker = panel.querySelector('[name="tanker_id"]');
    const liters = panel.querySelector('[name="liters"]');
    const price = panel.querySelector('[name="unit_price"]');
    const meterFields = panel.querySelectorAll('.meter-field');
    const maintenanceVehicle = maintenancePanel.querySelector('[name="maintenance_vehicle_id"]');
    const maintenanceProvider = maintenancePanel.querySelector('[name="maintenance_service_provider"]');
    const filterCategories = () => {
        Array.from(category.options).forEach(option => {
            if (!option.dataset.type) return;
            const visible = option.dataset.type === type.value;
            option.hidden = !visible;
            option.disabled = !visible;
        });
        const selected = category.options[category.selectedIndex];
        if (selected?.dataset.type && selected.dataset.type !== type.value) category.value = '';
    };
    const refresh = () => {
        filterCategories();
        const isFuel = type.value === 'expense' && category.value === 'Yakıt';
        const categoryName = category.value.toLocaleLowerCase('tr-TR');
        const isMaintenance = type.value === 'expense'
            && categoryName.includes('bakım')
            && categoryName.includes('onarım');
        toggle.value = isFuel ? '1' : '0';
        panel.hidden = !isFuel;
        maintenancePanel.hidden = !isMaintenance;
        [vehicle, tanker, liters].forEach(field => field.required = isFuel);
        maintenanceVehicle.required = isMaintenance;
        maintenanceVehicle.disabled = !isMaintenance;
        maintenanceProvider.disabled = !isMaintenance;
        category.required = true;
        amount.required = !isFuel;
        amount.readOnly = isFuel;
        if (isFuel) {
            const selectedTanker = tanker.options[tanker.selectedIndex];
            price.value = selectedTanker?.dataset.unitCost || '';
            const availableStock = Number(selectedTanker?.dataset.stock || 0);
            liters.max = availableStock > 0 ? String(availableStock) : '';
            const total = Number(liters.value || 0) * Number(price.value || 0);
            amount.value = total > 0 ? total.toFixed(2) : '';
        }
        const tracksMeters = vehicle.options[vehicle.selectedIndex]?.dataset.tracksMeters !== '0';
        meterFields.forEach(field => {
            field.hidden = isFuel && !tracksMeters;
            field.querySelector('input').disabled = isFuel && !tracksMeters;
        });
    };
    category.addEventListener('change', refresh);
    tanker.addEventListener('change', refresh);
    vehicle.addEventListener('change', refresh);
    liters.addEventListener('input', refresh);
    price.addEventListener('input', refresh);
    type.addEventListener('change', () => {
        refresh();
    });
    refresh();
})();
</script>
