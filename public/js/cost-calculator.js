(function () {
    function number(input) {
        var value = parseFloat(input && input.value);
        return Number.isFinite(value) ? value : null;
    }

    function rounded(value) {
        return Math.round((value + Number.EPSILON) * 100) / 100;
    }

    document.querySelectorAll('[data-cost-calculator]').forEach(function (calculator) {
        var quantity = calculator.querySelector('[data-cost-quantity]');
        var unit = calculator.querySelector('[data-cost-unit]');
        var total = calculator.querySelector('[data-cost-total]');
        if (!quantity || !unit || !total) return;

        function fromUnit() {
            var q = number(quantity), u = number(unit);
            total.value = q !== null && u !== null ? rounded(q * u) : '';
        }

        function fromTotal() {
            var q = number(quantity), t = number(total);
            unit.value = q && t !== null ? rounded(t / q) : '';
        }

        unit.addEventListener('input', fromUnit);
        total.addEventListener('input', fromTotal);
        quantity.addEventListener('input', function () {
            if (document.activeElement === total || (total.value !== '' && unit.value === '')) fromTotal();
            else fromUnit();
        });
    });
})();
