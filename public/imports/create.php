<?php
chdir(dirname(__DIR__));
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'Thủ kho'], true)) {
    header('Location: ../auth/sign-in.php');
    exit;
}
require_once __DIR__ . '/../../app/helpers/csrf_helper.php';
function importEsc($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8'); }
$home = $_SESSION['role'] === 'Admin' ? 'dashboard/admin.php' : 'dashboard/warehouse.php';
$name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Người dùng';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhập hàng hóa | VISHIPEL</title>
    <link rel="stylesheet" href="assets/css/dashboard/admin.css">
    <link rel="stylesheet" href="assets/css/imports/create.css">
    <link rel="stylesheet" href="assets/css/shared/icons.css">
    <link rel="stylesheet" href="assets/css/shared/theme.css">
</head>
<body>
<a class="skip-link" href="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? basename(__DIR__) . '/' . basename(__FILE__), ENT_QUOTES, 'UTF-8') ?>#main-content">Đến nội dung chính</a>
<div class="dashboard">
    <aside class="sidebar" id="dashboard-sidebar">
        <a class="brand" href="<?= $home ?>"><img src="assets/images/company-logo.png" alt="VISHIPEL" width="500" height="500"></a>
        <div class="nav-label">TỔNG QUAN</div>
        <nav class="nav" aria-label="Điều hướng chính">
            <a href="<?= $home ?>"><span aria-hidden="true"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#dashboard"></use></svg></span>Bảng điều khiển</a>
            <a href="reports/statistics.php"><span aria-hidden="true"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#chart"></use></svg></span>Thống kê</a>
            <div class="nav-label">QUẢN LÝ KHO</div>
            <a href="products/index.php"><span aria-hidden="true"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#package"></use></svg></span>Hàng hóa</a>
            <a href="imports/index.php"><span aria-hidden="true"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#import"></use></svg></span>Phiếu nhập kho</a>
            <a class="active" aria-current="page" href="imports/create.php"><span aria-hidden="true"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#file-plus"></use></svg></span>Tạo phiếu nhập</a>
            <a href="exports/index.php"><span aria-hidden="true"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#export"></use></svg></span>Phiếu xuất kho</a>
            <a href="exports/create.php"><span aria-hidden="true"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#file-plus"></use></svg></span>Tạo phiếu xuất</a>
            <?php if ($_SESSION['role'] === 'Admin'): ?><div class="nav-label">HỆ THỐNG</div><a href="accounts/index.php"><span aria-hidden="true"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#users"></use></svg></span>Tài khoản</a><?php endif; ?>
        </nav>
        <div class="sidebar-user"><span class="avatar" aria-hidden="true">V</span><span><strong><?= importEsc($name) ?></strong><small><?= importEsc($_SESSION['role']) ?></small></span><a href="auth/log-out.php" aria-label="Đăng xuất" title="Đăng xuất"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#logout"></use></svg></a></div>
    </aside>
    <div class="content-shell">
        <header class="topbar"><div class="topbar-left"><button class="menu-toggle" type="button" aria-controls="dashboard-sidebar" aria-expanded="false" aria-label="Mở menu"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#menu"></use></svg></button><div><span class="breadcrumb">Quản lý kho / Phiếu nhập kho</span><h1>Nhập hàng hóa</h1></div></div><div class="topbar-actions"><span><?= date('d/m/Y') ?></span><span class="top-avatar" aria-hidden="true">V</span></div></header>
<main class="main-content" id="main-content" tabindex="-1">
<div class="page-intro"><div><h2>Nhập hàng hóa</h2><p>Tạo phiếu nhập, thêm hàng hóa và lưu chứng từ trong cùng một nơi.</p></div><a class="btn btn-secondary" href="imports/index.php"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#arrow-left"></use></svg> Danh sách phiếu nhập</a></div>
<div class="flow-guide"><span><b>1</b> Thông tin hóa đơn</span><span><b>2</b> Hàng hóa nhập kho</span><span><b>3</b> Kiểm tra & lưu</span></div>
<div id="create-notice" role="status" aria-live="polite"></div>
<noscript><p>Vui lòng bật JavaScript để thêm hàng hóa và lưu phiếu nhập.</p></noscript>
<div class="entry-layout"><div class="entry-main">
            <!-- Phần Import Hóa Đơn XML -->
            <details class="section xml-shortcut">
                <summary><span class="xml-mark" aria-hidden="true"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#upload"></use></svg></span><span><strong>Điền nhanh từ hóa đơn XML</strong><small>Tùy chọn · Mở để chọn tệp hóa đơn điện tử</small></span></summary>
                <div class="section-header">
                    <h3>Có sẵn hóa đơn điện tử?</h3><p>Chọn XML để điền nhanh thông tin, hoặc nhập trực tiếp bên dưới.</p>
                </div>
                <div class="section-content">
                    <div class="form-group">
                        <label for="xml-file">Chọn file hóa đơn (XML):</label>
                        <div class="file-input-container">
                            <input type="file" id="xml-file" name="xml-file" accept=".xml">
                            <span class="file-chosen">Chưa chọn tệp</span>
                        </div>
                        <!-- Tự động import khi chọn file, không cần nút -->
                    </div>
                </div>
            </details>

            <!-- Phần Thông Tin Nhập Hàng -->
            <div class="section">
                <div class="section-header">
                    <h3><span class="step-number">1</span> Thông tin hóa đơn</h3><p>Kiểm tra nhà cung cấp và kho nhận trước khi lưu.</p>
                </div>
                <div class="section-content">
                    <div class="form-row">
                        <div class="form-column">

                            <div class="form-group">
                                <label for="import-date">Ngày nhập:</label>
                                <div class="date-input-container">
                                    <input type="date" id="import-date" name="import-date" value="<?= date('Y-m-d') ?>" required>

                                </div>
                            </div>
                            <div class="form-group">
                                <label for="serial">Số Serial:</label>
                                <input type="text" id="serial" name="serial">
                            </div>
                        </div>
                        <div class="form-column">
                            <div class="form-group">
                                <label for="supplier">Nhà cung cấp:</label>
                                <input type="text" id="supplier" name="supplier">
                            </div>
                            <div class="form-group">
                                <label for="receiver-name">Họ và tên người nhập hàng:</label>
                                <input type="text" id="receiver-name" name="receiver-name" value="<?= importEsc($name) ?>" placeholder="Nhập họ tên người nhập hàng">
                            </div>
                            <div class="form-group">
                                <label for="import-unit">Nhập vào đơn vị:</label>
                                <select id="import-unit" name="import-unit">
                                    <option value="Đài TTXLTTHH Hà Nội">Đài TTXLTTHH Hà Nội - 34 ngõ 60 Dương Khuê</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="invoice-number">Số hóa đơn:</label>
                                <input type="text" id="invoice-number" name="invoice-number">
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- Phần Danh sách hàng hóa -->
            <div class="section">
                <div class="section-header">
                    <h3><span class="step-number">2</span> Hàng hóa nhập kho <span id="goods-count">0 mặt hàng</span></h3><p>Điền đủ tên, nhóm hàng, số lượng, đơn vị và đơn giá cho từng mặt hàng.</p>
                </div>
                <div class="section-content">
                    <div id="goods-empty" class="empty-state">Chưa có mặt hàng. Bấm “Thêm hàng hóa” hoặc chọn hóa đơn XML để bắt đầu.</div><table class="goods-table">
                        <thead>
                            <tr>
                                <th>Hàng hóa</th>
                                <th>Loại hàng hóa</th>
                                <th>Số lượng</th>
                                <th>Đơn vị tính</th>
                                <th>Đơn giá trước thuế</th>
                                <th>VAT%</th>
                                <th>Đơn giá sau thuế</th>
                                <th>Thành tiền trước thuế</th>
                                <th>Thành tiền sau thuế</th>
                                <th>Ghi chú</th>
                                <th>Serial</th>
                                <th>Ảnh</th>
                                <th>Đóng</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dữ liệu hàng hóa sẽ được thêm vào đây -->
                        </tbody>
                    </table>

                    <div class="add-item-bar">
                        <button type="button" id="btn-add-row" class="btn btn-primary"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#plus"></use></svg> Thêm hàng hóa</button>

                    </div>
            </div>
        </div>
</div><aside class="review-panel" aria-labelledby="review-title"><div class="review-heading"><span class="step-number">3</span><h3 id="review-title">Kiểm tra phiếu nhập</h3></div><p class="review-note">Tổng tiền tự cập nhật theo các mặt hàng bạn nhập.</p><div class="review-count"><span>Số mặt hàng</span><strong id="review-count">0</strong></div><div class="form-group">
                                <label for="total-before-vat">Tiền hàng trước thuế</label>
                                <input type="text" id="total-before-vat" name="total-before-vat" value="0" readonly class="currency-input">
                            </div><div class="form-group">
                                <label for="total-after-vat">Tổng thanh toán</label>
                                <input type="text" id="total-after-vat" name="total-after-vat" value="0" readonly class="currency-input">
                            </div><p class="currency-note">Đơn vị tiền: VNĐ · Đã bao gồm VAT</p><div class="review-documents"><h4>Chứng từ đính kèm</h4>                            <!-- Hóa đơn PDF: chuyển sang cột trái, đặt sau phần tổng tiền -->
                            <div class="form-group pdf-upload">
                                <label for="pdf-file" >Hóa đơn PDF:</label>
                                <div class="file-input-container">
                                    <input type="file" id="pdf-file" name="pdf-file" accept=".pdf">
                                    <span class="file-chosen">Chưa chọn tệp</span>
                                </div>
                            </div>
</div><div class="review-actions"><span id="save-hint">Phiếu chưa được lưu.</span><button type="button" id="btn-save" class="btn btn-primary">Lưu phiếu nhập</button><p>Sau khi lưu, bạn sẽ được chuyển đến chi tiết phiếu.</p></div></aside></div>
        </main></div></div>
    <script>
        const csrfToken = <?= json_encode(generateCSRFToken()) ?>;
        const menu = document.querySelector('.menu-toggle');
        menu.addEventListener('click', () => { const open = document.body.classList.toggle('menu-open'); menu.setAttribute('aria-expanded', String(open)); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') { document.body.classList.remove('menu-open'); menu.setAttribute('aria-expanded', 'false'); } });
        function notify(message, error = false) { const box = document.getElementById('create-notice'); box.textContent = message; box.className = error ? 'notice error' : 'notice success'; box.scrollIntoView({block:'center', behavior:'smooth'}); }
        // Xử lý hiển thị tên file được chọn (chung)
        document.addEventListener('change', function(e){
            const input = e.target;
            if (input && input.type === 'file' && input.closest('.file-input-container')) {
                const fileName = input.files[0] ? input.files[0].name : 'Chưa chọn tệp';
                const span = input.closest('.file-input-container').querySelector('.file-chosen');
                if (span) span.textContent = fileName;
            }
        });

        // Date picker
        document.getElementById('import-date').addEventListener('click', function() {
            this.type = 'date';
            this.focus();
        });

        // Tự động import XML ngay khi chọn file
        (function(){
            const xmlInput = document.getElementById('xml-file');
            if (!xmlInput) return;
            xmlInput.addEventListener('change', function() {
                const xmlFile = this.files && this.files[0] ? this.files[0] : null;
                if (!xmlFile) { return; }
                if (document.querySelector('.goods-table tbody tr') && !confirm('Nhập XML sẽ thay thế danh sách hàng hóa và thông tin hóa đơn đang nhập. Tiếp tục?')) { this.value=''; return; }
                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        const xmlContent = e.target.result;
                        const parser = new DOMParser();
                        const xmlDoc = parser.parseFromString(xmlContent, 'text/xml');
                        if (xmlDoc.getElementsByTagName('parsererror').length > 0) { notify('File XML không hợp lệ!',true); return; }
                        importXMLData(xmlDoc);
                    } catch (error) { notify('Không thể đọc XML. Vui lòng chọn lại tệp.',true); }
                };
                reader.readAsText(xmlFile);
            });
        })();

        // Gắn sự kiện cho 2 nút chính (tránh lệ thuộc textContent)
        document.getElementById('btn-add-row').addEventListener('click', function(){ addNewGoodsRow(); document.querySelector('.goods-table tbody tr:last-child input').focus(); });
        document.getElementById('btn-save').addEventListener('click', function(){ saveImportInfo(); });

        // Danh sách loại
        const goodsTypes = ['Công cụ dụng cụ','Vật tư','Tài sản cố định','Phụ tùng thay thế','Khác'];

        function parseCurrencyValue(value) {
            if (value === undefined || value === null) return 0;
            let s = String(value).trim().replace(/\u00A0/g, '').replace(/\s/g, '');
            if (!s) return 0;
            // Thousand grouping with comma (EN-style): 276,852 / 1,234,567
            if (/^\d{1,3}(,\d{3})+$/.test(s)) {
                const n = parseFloat(s.replace(/,/g, ''));
                return Number.isFinite(n) ? n : 0;
            }
            // Thousand grouping with dot (VN-style): 276.852 / 1.234.567
            if (/^\d{1,3}(\.\d{3})+$/.test(s)) {
                const n = parseFloat(s.replace(/\./g, ''));
                return Number.isFinite(n) ? n : 0;
            }
            // EN decimal (XML hay trả về 6 chữ số sau dấu chấm)
            if (/^\d+\.\d{1,6}$/.test(s) && (s.match(/\./g) || []).length === 1) {
                const n = parseFloat(s);
                return Number.isFinite(n) ? n : 0;
            }
            const m = s.match(/^(.+),(\d{1,3})$/);
            if (m) {
                const intPart = m[1].replace(/\./g, '');
                const d = m[2].length;
                const n = parseInt(intPart, 10) + parseInt(m[2], 10) / Math.pow(10, d);
                return Number.isFinite(n) ? n : 0;
            }
            const digitsOnly = s.replace(/\./g, '').replace(/,/g, '');
            const n = parseFloat(digitsOnly);
            return Number.isFinite(n) ? n : 0;
        }
        function formatNumber(num) {
            if (!Number.isFinite(num)) return '0';
            const millis = Math.round(num * 1000);
            const intPart = Math.trunc(millis / 1000);
            let frac = Math.abs(millis % 1000);
            let intStr = String(intPart).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            if (frac === 0) return intStr;
            let fracStr = String(frac).padStart(3, '0').replace(/0+$/, '');
            return intStr + ',' + fracStr;
        }

        function addNewGoodsRow() {
            const tbody = document.querySelector('.goods-table tbody');
            const newRow = document.createElement('tr');

            const typeSelect = document.createElement('select');
            typeSelect.innerHTML = '<option value="">Chọn loại</option>' + goodsTypes.map(t=>`<option value="${t}">${t}</option>`).join('');

            const unitInput = document.createElement('input');
            unitInput.type = 'text'; unitInput.placeholder = 'Nhập đơn vị tính'; unitInput.className = 'form-control';

            newRow.innerHTML = `
                <td><input type="text" placeholder="Tên hàng"></td>
                <td></td>
                <td><input type="number" placeholder="Số lượng" min="1" step="1" required></td>
                <td></td>
                <td><input type="text" placeholder="Giá trước thuế" class="currency-input"></td>
                <td><input type="number" placeholder="VAT%" min="0" max="100"></td>
                <td><input type="text" value="0" readonly class="currency-input"></td>
                <td><input type="text" value="0" readonly class="currency-input"></td>
                <td><input type="text" value="0" readonly class="currency-input"></td>
                <td><input type="text" placeholder="Ghi chú"></td>
                <td><input type="text" placeholder="Serial"></td>
                <td><input type="file" accept="image/*" multiple></td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">Xóa</button></td>
            `;

            newRow.cells[1].appendChild(typeSelect);
            newRow.cells[3].appendChild(unitInput);
            tbody.appendChild(newRow);
            const headings = [...document.querySelectorAll('.goods-table th')];
            [...newRow.cells].forEach((cell, i) => { cell.dataset.label = headings[i].textContent; const field = cell.querySelector('input, select'); if (field) field.setAttribute('aria-label', headings[i].textContent); });
            [0,1,2,3,4].forEach(i => newRow.cells[i].querySelector('input,select').required = true);
            newRow.cells[5].querySelector('input').value = '0';

            const quantityInput = newRow.cells[2].querySelector('input');
            const priceInput = newRow.cells[4].querySelector('input');
            const vatInput = newRow.cells[5].querySelector('input');

            quantityInput.addEventListener('input', function(){ calculateRow(this); });
            priceInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^\d.,]/g, '').replace(/,(?=.*,)/g, '');
            });
            vatInput.addEventListener('input', function(){ calculateRow(this); });
            priceInput.addEventListener('blur', function() {
                const v = parseCurrencyValue(this.value);
                if (v !== 0 || this.value.trim() !== '') this.value = formatNumber(v);
                calculateRow(this);
            });
            priceInput.addEventListener('input', function(){ calculateRow(this); });
            updateTotalAmounts();
        }

        function removeRow(button) { if (!confirm('Xóa mặt hàng này khỏi phiếu đang nhập?')) return; button.closest('tr').remove(); updateTotalAmounts(); }

        function calculateRow(input) {
            const row = input.closest('tr');
            const quantity = parseFloat(row.cells[2].querySelector('input').value) || 0;
            const priceBeforeVAT = parseCurrencyValue(row.cells[4].querySelector('input').value) || 0;
            const vatPercent = parseFloat(row.cells[5].querySelector('input').value) || 0;
            const priceAfterVAT = Math.round((priceBeforeVAT + (priceBeforeVAT * vatPercent / 100)) * 1000) / 1000;
            const itemAmount = Math.round(priceBeforeVAT * quantity * 1000) / 1000;
            const totalAmount = Math.round((itemAmount + (itemAmount * vatPercent / 100)) * 1000) / 1000;
            row.cells[6].querySelector('input').value = formatNumber(priceAfterVAT);
            row.cells[7].querySelector('input').value = formatNumber(itemAmount);
            row.cells[8].querySelector('input').value = formatNumber(totalAmount);
            updateTotalAmounts();
        }

        function updateTotalAmounts() {
            const rows = document.querySelectorAll('.goods-table tbody tr');
            document.getElementById('goods-count').textContent = rows.length + ' mặt hàng';
            document.getElementById('review-count').textContent = rows.length;
            document.getElementById('goods-empty').hidden = rows.length > 0;
            let totalBeforeVAT = 0, totalAfterVAT = 0;
            rows.forEach(row => {
                const itemAmount = parseCurrencyValue(row.cells[7].querySelector('input').value) || 0;
                const totalAmount = parseCurrencyValue(row.cells[8].querySelector('input').value) || 0;
                totalBeforeVAT += itemAmount; totalAfterVAT += totalAmount;
            });
            const totalBeforeVATElement = document.getElementById('total-before-vat');
            const totalAfterVATElement = document.getElementById('total-after-vat');
            if (totalBeforeVATElement && totalAfterVATElement) {
                totalBeforeVATElement.value = formatNumber(totalBeforeVAT);
                totalAfterVATElement.value = formatNumber(totalAfterVAT);
            }
        }

        let saving = false;
        let savedBillId = null;
        async function saveImportInfo() {
            if (saving) return;
            if (savedBillId) { location.href = 'imports/details.php?id=' + savedBillId; return; }
            const required = ['supplier','import-unit','invoice-number','import-date'];
            for (const id of required) { const field = document.getElementById(id); field.required = true; if (!field.reportValidity()) return; }
            const rows = [...document.querySelectorAll('.goods-table tbody tr')];
            if (!rows.length) { notify('Vui lòng thêm ít nhất một mặt hàng.', true); return; }
            for (const row of rows) for (const field of row.querySelectorAll('input,select')) if (!field.reportValidity()) return;
            const goodsList = rows.map(row => {
                const val = i => row.cells[i].querySelector('input,select').value;
                return {name:val(0),type:val(1),quantity:val(2),unit:val(3),priceBeforeVAT:parseCurrencyValue(val(4)),vatPercent:val(5)||'0',ghiChu:val(9),serial:val(10)};
            });
            const value = id => document.getElementById(id).value;
            const data = {csrf_token:csrfToken, serial:value('serial'),supplier:value('supplier'),receiverName:value('receiver-name'),importUnit:value('import-unit'),invoiceNumber:value('invoice-number'),importDate:value('import-date'),goodsList};
            const button = document.getElementById('btn-save');
            saving = true; button.disabled = true; button.textContent = 'Đang lưu phiếu…';
            const send = async (url, options) => { const response = await fetch(url, options); const result = await response.json(); if (!response.ok || !result.success) throw new Error(result.message || 'Không thể hoàn tất thao tác.'); return result; };
            try {
                const result = await send('imports/save.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
                savedBillId = Number(result.import_bill_id);
                const failures = [];
                const pdf = document.getElementById('pdf-file').files[0];
                if (pdf) {
                    const form = new FormData(); form.append('pdf_file',pdf); form.append('bill_id',savedBillId); form.append('csrf_token',csrfToken);
                    try { await send('imports/upload-pdf.php',{method:'POST',body:form}); } catch(e) { failures.push('PDF'); }
                }
                const images = new FormData(); let imageCount = 0;
                rows.forEach((row,index) => { for (const file of row.cells[11].querySelector('input').files) { images.append('images[]',file); images.append('row_indices[]',index); imageCount++; } });
                if (imageCount) {
                    images.append('import_bill_id',savedBillId); images.append('csrf_token',csrfToken);
                    try { await send('imports/upload-images.php',{method:'POST',body:images}); } catch(e) { failures.push('ảnh sản phẩm'); }
                }
                if (failures.length) {
                    notify('Phiếu đã lưu, nhưng chưa tải đủ ' + failures.join(' và ') + '. Bấm “Mở phiếu đã lưu” để kiểm tra và tải lại tệp.',true);
                    button.textContent = 'Mở phiếu đã lưu';
                    document.querySelectorAll('.main-content input,.main-content select,#btn-add-row,.goods-table button').forEach(el => el.disabled=true);
                } else { location.href = 'imports/details.php?id=' + savedBillId; }
            } catch(e) { notify(e.message || 'Không thể lưu phiếu. Vui lòng thử lại.',true); }
            finally { saving=false; button.disabled=false; if (!savedBillId) button.textContent='Lưu phiếu nhập'; }
        }

        function importXMLData(xmlDoc) {
            try {
                const getText = (node, tag) => { const el = node.getElementsByTagName(tag)[0]; return el ? el.textContent : ''; };
                if (!xmlDoc.getElementsByTagName('HHDVu').length) { notify('XML không có hàng hóa phù hợp. Dữ liệu hiện tại được giữ nguyên.',true); return; }
                const kHHDon = getText(xmlDoc, 'KHHDon');
                const sHDon = getText(xmlDoc, 'SHDon');
                const nLap = getText(xmlDoc, 'NLap');
                const nBanNode = xmlDoc.getElementsByTagName('NBan')[0];
                const nBanTen = nBanNode ? getText(nBanNode, 'Ten') : '';
                const tgTCThue = getText(xmlDoc, 'TgTCThue') || '0';
                const tgTTTBSo = getText(xmlDoc, 'TgTTTBSo') || '0';
                document.getElementById('serial').value = kHHDon;
                document.getElementById('invoice-number').value = sHDon;
                document.getElementById('supplier').value = nBanTen;
                if (nLap) {
                    const match = nLap.match(/^(\d{4})-(\d{2})-(\d{2})/);
                    const vn = nLap.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
                    document.getElementById('import-date').value = match ? match[0] : (vn ? `${vn[3]}-${vn[2]}-${vn[1]}` : '');
                }
                const hhDVuList = xmlDoc.getElementsByTagName('HHDVu');
                const tbody = document.querySelector('.goods-table tbody'); tbody.innerHTML = '';
                for (let i = 0; i < hhDVuList.length; i++) {
                    const hhDVu = hhDVuList[i]; const tenHang = getText(hhDVu, 'THHDVu'); const soLuong = getText(hhDVu, 'SLuong') || '0'; const donVi = getText(hhDVu, 'DVTinh'); const donGia = getText(hhDVu, 'DGia') || '0'; const tSuat = getText(hhDVu, 'TSuat') || '0';
                    addNewGoodsRow(); const newRow = tbody.rows[tbody.rows.length - 1];
                    newRow.cells[0].querySelector('input').value = tenHang; newRow.cells[1].querySelector('select').value = 'Công cụ dụng cụ'; newRow.cells[2].querySelector('input').value = soLuong; newRow.cells[3].querySelector('input').value = donVi; newRow.cells[9].querySelector('input').value = ''; newRow.cells[10].querySelector('input').value = '';
                    // Giá trước thuế (cột 4) và VAT% (cột 5)
                    newRow.cells[4].querySelector('input').value = formatNumber(Number(donGia));
                    // Chuẩn hóa VAT từ XML: KCT/blank => 0
                    let vatPercent = (tSuat || '').toString().trim();
                    if (vatPercent === '' || /^KCT$/i.test(vatPercent) || /khong\s*chiu\s*thue/i.test(vatPercent)) {
                        vatPercent = '0';
                    } else {
                        vatPercent = vatPercent.replace('%', '');
                    }
                    newRow.cells[5].querySelector('input').value = vatPercent;
                    // Tính toán tự động các cột readonly
                    calculateRow(newRow.cells[4].querySelector('input'));
                }
                updateTotalAmounts(); notify('Đã đọc XML. Vui lòng kiểm tra nhóm hàng, số lượng và giá trước khi lưu.');
            } catch (error) { notify('Không thể đọc đầy đủ dữ liệu XML. Vui lòng kiểm tra thông tin.',true); }
        }

        function resetForm() {
            document.getElementById('serial').value = ''; document.getElementById('supplier').value = ''; document.getElementById('invoice-number').value = ''; document.getElementById('total-before-vat').value = '0'; document.getElementById('total-after-vat').value = '0';
            const tbody = document.querySelector('.goods-table tbody'); tbody.innerHTML = '';
            document.getElementById('pdf-file').value = ''; document.getElementById('xml-file').value = ''; document.querySelectorAll('.file-chosen').forEach(span => { span.textContent = 'Chưa chọn tệp'; });
        }
    </script>
<script src="assets/js/shared/theme.js" defer></script>
</body>
</html>
