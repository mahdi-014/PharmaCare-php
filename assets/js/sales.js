// PHP/JS cart. UI estimates are informational: all prices and stock are rechecked in the PHP transaction.
document.addEventListener('DOMContentLoaded', () => {
    const rows = document.getElementById('saleRows');
    const template = document.getElementById('saleRowTemplate');
    const discount = document.getElementById('discountInput');
    const form = document.getElementById('saleForm');
    if (!rows || !template || !discount) return;
    const money = n => '৳' + (Number(n) || 0).toFixed(2);
    const rowCalc = row => {
        const select = row.querySelector('.medicine-select');
        const opt = select.selectedOptions[0];
        const price = Number(opt?.dataset?.price || 0);
        const stock = Number(opt?.dataset?.stock || 0);
        const qty = Math.max(0, Number(row.querySelector('.quantity-input').value || 0));
        row.querySelector('.stock-cell').textContent = select.value ? String(stock) : '—';
        row.querySelector('.price-cell').textContent = money(price);
        row.querySelector('.line-total').textContent = money(price * qty);
        row.querySelector('.quantity-input').setCustomValidity(select.value && qty > stock ? 'Quantity exceeds available stock' : '');
        row.dataset.total = String(price * qty);
        totals();
    };
    const totals = () => {
        const subtotal = [...rows.querySelectorAll('.sale-row')].reduce((sum,r) => sum + Number(r.dataset.total||0),0);
        document.getElementById('subtotalText').textContent = money(subtotal);
        const d = Math.max(0,Number(discount.value||0));
        document.getElementById('grandTotalText').textContent = money(Math.max(0,subtotal-d));
        discount.setCustomValidity(d > subtotal ? 'Discount exceeds subtotal' : '');
    };
    const addRow = () => {
        const clone = template.content.cloneNode(true);
        const row = clone.querySelector('.sale-row');
        row.querySelector('.medicine-select').addEventListener('change', () => rowCalc(row));
        row.querySelector('.quantity-input').addEventListener('input', () => rowCalc(row));
        row.querySelector('.remove-row').addEventListener('click', () => {
            if (rows.children.length > 1) row.remove();
            else { row.querySelector('.medicine-select').value=''; row.querySelector('.quantity-input').value=1; rowCalc(row); }
            totals();
        });
        rows.appendChild(clone);
        rowCalc(row);
    };
    document.getElementById('addRow').addEventListener('click', () => { if(rows.children.length < 40) addRow(); });
    discount.addEventListener('input', totals);
    form.addEventListener('submit', event => {
        // Normalize duplicate selections to avoid confusing stock totals; server also merges duplicate medicine IDs.
        const qtyById = new Map();
        for (const row of rows.children) {
            const id = row.querySelector('.medicine-select').value;
            if (!id) continue;
            qtyById.set(id,(qtyById.get(id)||0)+Number(row.querySelector('.quantity-input').value));
            const stock = Number(row.querySelector('.medicine-select').selectedOptions[0]?.dataset.stock || 0);
            if (qtyById.get(id) > stock) {
                event.preventDefault(); alert('Not enough stock for one or more selected medicines.'); return;
            }
        }
    });
    addRow();
});
