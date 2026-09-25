<details class="exec-panel disclosure product-catalogue-entry">
    <summary><div><p class="card-label">Product catalogue</p><strong>Add an item to be priced</strong></div></summary>
    <div class="disclosure__body">
        <form method="POST" action="{{ route('sales.products.store') }}" class="form-grid" data-product-catalogue-form>
            @csrf
            <div class="field"><label>Item code</label><input name="code" readonly placeholder="Generated automatically"><small>Created automatically from the category and item name.</small></div>
            <div class="field"><label>Item name</label><input name="name" required placeholder="e.g. Rabbit kit"></div>
            <div class="field"><label>Category</label><select name="category_choice" required><option value="" selected disabled>Select category</option>@foreach($categories as $category)<option value="{{ $category }}">{{ ucfirst($category) }}</option>@endforeach<option value="custom">Other category</option></select></div>
            <div class="field" data-custom-category><label>Other category</label><input name="custom_category"></div>
            <div class="field"><label>Standard unit</label><select name="unit" required>@foreach(['tray','egg','bird','chick','animal','head','kg','bag','litre','crate','bunch','bundle','piece'] as $unit)<option value="{{ $unit }}">{{ ucfirst($unit) }}</option>@endforeach</select></div>
            <div class="field"><label>Standard selling price (UGX)</label><input type="number" name="unit_price" min="0" step="0.01" required></div>
            <div class="field" data-loose-price><label>Individual / loose price (UGX)</label><input type="number" name="loose_unit_price" min="0" step="0.01"><small>Only needed when this packaged item is also sold separately.</small></div>
            <div class="field"><label>Availability</label><select name="availability" required><option value="in_stock">In stock now</option><option value="out_of_stock">Currently out of stock</option><option value="untracked">Quantity will come from farm records</option></select><small>Choose how the system should know whether this item can be sold.</small></div>
            <div class="field" data-stock-quantity><label>Quantity available now</label><input type="number" name="stock_quantity" min="0" step="0.01"><small data-stock-help>Enter the quantity currently ready for sale.</small></div>
            <div class="field field--wide"><label>Notes</label><textarea name="notes"></textarea></div>
            <button class="btn">Add priced item</button>
        </form>
    </div>
</details>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-product-catalogue-form]');
    if (!form) return;

    const name = form.elements.name;
    const category = form.elements.category_choice;
    const customCategory = form.elements.custom_category;
    const code = form.elements.code;
    const unit = form.elements.unit;
    const availability = form.elements.availability;
    const stockField = form.querySelector('[data-stock-quantity]');
    const stock = form.elements.stock_quantity;
    const stockHelp = form.querySelector('[data-stock-help]');
    const looseField = form.querySelector('[data-loose-price]');
    const loose = form.elements.loose_unit_price;
    const customField = form.querySelector('[data-custom-category]');
    const prefixes = { eggs: 'EGG', rabbits: 'RABBIT', goats: 'GOAT', poultry: 'BIRD', dogs: 'DOG', garden: 'GARDEN' };

    const slug = value => value.toUpperCase().trim().replace(/[^A-Z0-9]+/g, '-').replace(/^-|-$/g, '');
    const refreshCode = () => {
        if (!name.value.trim()) {
            code.value = '';
            return;
        }
        const categoryName = category.value === 'custom' ? customCategory.value : category.value;
        const words = slug(name.value).split('-');
        const inferred = words.includes('RABBIT') ? 'RABBIT'
            : words.includes('GOAT') ? 'GOAT'
            : words.some(word => ['EGG', 'EGGS'].includes(word)) ? 'EGG'
            : words.some(word => ['DOG', 'PUPPY', 'PUPPIES'].includes(word)) ? 'DOG'
            : null;
        const prefix = inferred || prefixes[categoryName.toLowerCase()] || slug(categoryName);
        let item = words.filter(word => ![prefix, `${prefix}S`].includes(word)).join('-');
        code.value = prefix && item ? `${prefix}-${item}` : (item || prefix);
    };
    const refreshCategory = () => {
        const custom = category.value === 'custom';
        customField.hidden = !custom;
        customCategory.required = custom;
        refreshCode();
    };
    const refreshUnit = () => {
        const wholeUnits = ['animal', 'head', 'bird', 'chick', 'egg', 'piece'];
        const packagedUnits = ['tray', 'bag', 'crate', 'bunch', 'bundle'];
        stock.step = wholeUnits.includes(unit.value) ? '1' : '0.01';
        stockHelp.textContent = wholeUnits.includes(unit.value)
            ? 'Enter a whole number currently ready for sale.'
            : 'Enter the quantity currently ready for sale.';
        looseField.hidden = !packagedUnits.includes(unit.value);
        if (looseField.hidden) loose.value = '';
    };
    const refreshAvailability = () => {
        const needsQuantity = availability.value === 'in_stock';
        const wholeUnits = ['animal', 'head', 'bird', 'chick', 'egg', 'piece'];
        stockField.hidden = !needsQuantity;
        stock.required = needsQuantity;
        stock.min = needsQuantity ? (wholeUnits.includes(unit.value) ? '1' : '0.01') : '0';
        if (!needsQuantity) stock.value = '';
    };

    name.addEventListener('input', refreshCode);
    category.addEventListener('change', refreshCategory);
    customCategory.addEventListener('input', refreshCode);
    unit.addEventListener('change', () => {
        refreshUnit();
        refreshAvailability();
    });
    availability.addEventListener('change', refreshAvailability);
    refreshCategory();
    refreshUnit();
    refreshAvailability();
});
</script>
