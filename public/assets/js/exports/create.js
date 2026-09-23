const menu = document.querySelector('.menu-toggle');
const setMenu = open => { document.body.classList.toggle('menu-open', open); menu.setAttribute('aria-expanded', String(open)); };
menu.addEventListener('click', () => setMenu(!document.body.classList.contains('menu-open')));
document.addEventListener('keydown', e => { if (e.key === 'Escape') setMenu(false); });
document.addEventListener('click', e => { if (!e.target.closest('.sidebar,.menu-toggle')) setMenu(false); });
function handleReceiverChange() {
    const custom = document.getElementById('receiver-custom');
    const active = document.getElementById('receiver').value === 'custom';
    custom.hidden = !active; custom.required = active;
}
handleReceiverChange();
const rows = [...document.querySelectorAll('.goods-table tbody tr')];
const search = document.getElementById('goods-search');
const onlySelected = document.getElementById('only-selected');
const normalize = text => text.toLocaleLowerCase('vi').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd');
const formatter = new Intl.NumberFormat('vi-VN', {maximumFractionDigits:3});
function refresh() {
    let count = 0, total = 0, visible = 0;
    const query = normalize(search.value.trim());
    const selectedItems = document.getElementById('selected-items');
    selectedItems.replaceChildren();
    rows.forEach(row => {
        const checked = row.querySelector('input[type=checkbox]').checked;
        const qty = row.querySelector('.qty-input');
        qty.disabled = !checked; qty.required = checked;
        row.classList.toggle('is-selected', checked);
        if (checked) {
            count++;
            const amount = Math.round(Number(row.dataset.price) * (Number(qty.value) || 0) * 1000) / 1000;
            total += amount;
            const item = document.createElement('div'); item.className = 'selected-item';
            const title = document.createElement('strong'); title.textContent = row.dataset.name;
            const detail = document.createElement('span'); detail.textContent = `${qty.value || 0} ${row.dataset.unit} · ${formatter.format(amount)} VNĐ`;
            const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Bỏ chọn';
            remove.setAttribute('aria-label', 'Bỏ chọn ' + row.dataset.name);
            remove.addEventListener('click', () => { row.querySelector('input[type=checkbox]').checked = false; refresh(); onlySelected.focus(); });
            item.append(title, detail, remove); selectedItems.append(item);
        }
        row.hidden = !normalize(row.textContent).includes(query) || (onlySelected.checked && !checked);
        if (!row.hidden) visible++;
    });
    if (!count) { const empty = document.createElement('p'); empty.className = 'selection-empty'; empty.textContent = 'Chưa chọn hàng hóa. Tích chọn mặt hàng trong danh sách để thêm vào phiếu.'; selectedItems.append(empty); }
    document.getElementById('selected-count').textContent = count;
    document.getElementById('selected-total').textContent = formatter.format(total);
    document.getElementById('filter-status').textContent = visible ? `Hiển thị ${visible} / ${rows.length} mặt hàng` : 'Không có hàng hóa phù hợp với bộ lọc.';
}
rows.forEach(row => {
    const labels = [...document.querySelectorAll('.goods-table th')];
    [...row.cells].forEach((cell,i) => { cell.dataset.label = labels[i].textContent; });
    row.addEventListener('input', refresh);
});
search.addEventListener('input', refresh);
onlySelected.addEventListener('change', refresh);
const form = document.querySelector('.invoice-form');
form.addEventListener('invalid', e => { const row=e.target.closest('tr'); if(row) row.hidden=false; }, true);
form.addEventListener('submit', e => {
    if (!rows.some(row => row.querySelector('input[type=checkbox]').checked)) {
        e.preventDefault(); document.getElementById('filter-status').textContent='Vui lòng chọn ít nhất một mặt hàng.';
        document.getElementById('filter-status').scrollIntoView({block:'center'}); return;
    }
    const button=form.querySelector('button[type=submit]'); button.disabled=true; button.textContent='Đang lưu phiếu xuất…';
});
window.addEventListener('pageshow', () => { const button=form.querySelector('button[type=submit]'); button.disabled=false; button.textContent='Xuất hàng hóa'; refresh(); });
refresh();
