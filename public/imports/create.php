<?php
chdir(dirname(__DIR__));
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Thủ kho')) {
    header('Location: ../auth/sign-in.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhập hóa đơn - Admin</title>
    <link rel="stylesheet" href="assets/css/shared/layout.css">
    <link rel="stylesheet" href="assets/css/imports/create.css">
</head>
<body>
    <button class="sidebar-toggle" id="sidebarToggle">☰</button>
    <div class="header">
        <div class="logo">
            <img src="assets/images/company-logo.png" alt="Vishipel Logo">
            <div class="logo-text">
                <h1>PHẦN MỀM QUẢN LÝ KHO VISHIPEL</h1>
                <p>CÔNG TY TNHH MTV THÔNG TIN ĐIỆN TỬ HÀNG HẢI VIỆT NAM</p>
            </div>
        </div>
        <div class="user-info">
            <span class="greeting">Xin chào <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="auth/log-out.php" class="logout-btn">Đăng xuất</a>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <ul class="menu">
                <li><a href="reports/statistics.php">Số liệu thống kê</a></li>
                <?php if ($_SESSION['role'] === 'Admin'): ?>
                <li><a href="accounts/index.php">Quản lý tài khoản</a></li>
                <?php endif; ?>
                <li class="active">Nhập hàng hóa
                    <ul>
                        <li class="active"><a href="imports/create.php">Nhập hóa đơn</a></li>
                        <li><a href="imports/index.php">DS phiếu nhập kho</a></li>
                    </ul>
                </li>
                <li>Xuất hàng hóa
                    <ul>
                        <li><a href="exports/create.php">Xuất hóa đơn</a></li>
                        <li><a href="exports/index.php">DS phiếu xuất kho</a></li>
                    </ul>
                </li>
                <li>Danh Sách Hàng Hóa
                    <ul>
                        <li><a href="products/index.php">Tất Cả Hàng Hóa</a></li>
                        <li><a href="products/index.php?type=cong-cu">Công Cụ Dụng Cụ</a></li>
                        <li><a href="products/index.php?type=vat-tu">Vật Tư</a></li>
                        <li><a href="products/index.php?type=tai-san">Tài Sản Cố Định</a></li>
                        <li><a href="products/index.php?type=phu-tung">Phụ Tùng Thay Thế</a></li>
                        <li><a href="products/index.php?type=khac">Khác</a></li>
                    </ul>
                </li>
            </ul>
        </div>
        
        <div class="main-content">
            <h2>Nhập Hàng Hóa</h2>
            
            <!-- Phần Import Hóa Đơn XML -->
            <div class="section">
                <div class="section-header">
                    <h3>Import Hóa Đơn (XML)</h3>
                </div>
                <div class="section-content">
                    <div class="form-group">
                        <label for="xml-file">Chọn file hóa đơn (XML):</label>
                        <div class="file-input-container">
                            <input type="file" id="xml-file" name="xml-file" accept=".xml">
                            <span class="file-chosen">No file chosen</span>
                        </div>
                        <!-- Tự động import khi chọn file, không cần nút -->
                    </div>
                </div>
            </div>

            <!-- Phần Thông Tin Nhập Hàng -->
            <div class="section">
                <div class="section-header">
                    <h3>Thông Tin Nhập Hàng</h3>
                </div>
                <div class="section-content">
                    <div class="form-row">
                        <div class="form-column">
                            
                            <div class="form-group">
                                <label for="import-date">Ngày nhập:</label>
                                <div class="date-input-container">
                                    <input type="text" id="import-date" name="import-date" placeholder="dd/mm/yyyy">
                                    <span class="calendar-icon">📅</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="serial">Số Serial:</label>
                                <input type="text" id="serial" name="serial">
                            </div>
                            <div class="form-group">
                                <label for="total-before-vat">Tổng số tiền (Chưa tính VAT):</label>
                                <input type="text" id="total-before-vat" name="total-before-vat" readonly class="currency-input">
                            </div>
                            <div class="form-group">
                                <label for="total-after-vat">Tổng số tiền (Đã tính VAT):</label>
                                <input type="text" id="total-after-vat" name="total-after-vat" readonly class="currency-input">
                            </div>
                            <!-- Hóa đơn PDF: chuyển sang cột trái, đặt sau phần tổng tiền -->
                            <div class="form-group" style="border: 2px dashed #ff9800; background: #fff8e1; padding: 12px; border-radius: 8px;">
                                <label for="pdf-file" style="font-weight: 600; color: #e65100;">Hóa đơn PDF:</label>
                                <div class="file-input-container">
                                    <input type="file" id="pdf-file" name="pdf-file" accept=".pdf">
                                    <span class="file-chosen">No file chosen</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-column">
                            <div class="form-group">
                                <label for="supplier">Nhà cung cấp:</label>
                                <input type="text" id="supplier" name="supplier">
                            </div>
                            <div class="form-group">
                                <label for="receiver-name">Họ và tên người nhập hàng:</label>
                                <input type="text" id="receiver-name" name="receiver-name" value="Dương Mạnh Tuấn" placeholder="Nhập họ tên người nhập hàng">
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
                    <h3>Danh sách hàng hóa</h3>
                </div>
                <div class="section-content">
                    <table class="goods-table">
                        <thead>
                            <tr>
                                <th>Hàng hóa</th>
                                <th>Loại hàng hóa</th>
                                <th>Số lượng</th>
                                <th>Đ.vị tính</th>
                                <th>Giá tr.thuế</th>
                                <th>VAT%</th>
                                <th>Giá s.thuế</th>
                                <th>Tiền hàng</th>
                                <th>Tổng tiền</th>
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
                    
                    <div class="table-actions">
                        <button type="button" id="btn-add-row" class="btn btn-primary">Thêm Hàng Hóa</button>
                        <button type="button" id="btn-save" class="btn btn-primary">Lưu thông tin</button>
                    </div>
            </div>
        </div>
    </div>

    <script src="assets/js/shared/sidebar-toggle.js" defer></script>
    <script>
        // Toggle sidebar (dùng chung cơ chế)
        (function(){
            var btn = document.getElementById('sidebarToggle');
            if (btn) {
                btn.addEventListener('click', function(){
                    document.body.classList.toggle('sidebar-collapsed');
                });
            }
        })();
        // Xử lý hiển thị tên file được chọn (chung)
        document.addEventListener('change', function(e){
            const input = e.target;
            if (input && input.type === 'file' && input.closest('.file-input-container')) {
                const fileName = input.files[0] ? input.files[0].name : 'No file chosen';
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
                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        const xmlContent = e.target.result;
                        const parser = new DOMParser();
                        const xmlDoc = parser.parseFromString(xmlContent, 'text/xml');
                        if (xmlDoc.getElementsByTagName('parsererror').length > 0) { alert('File XML không hợp lệ!'); return; }
                        importXMLData(xmlDoc);
                    } catch (error) { alert('Có lỗi khi đọc file XML!'); }
                };
                reader.readAsText(xmlFile);
            });
        })();

        // Gắn sự kiện cho 2 nút chính (tránh lệ thuộc textContent)
        document.getElementById('btn-add-row').addEventListener('click', function(){ addNewGoodsRow(); });
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
                <td><input type="number" placeholder="Số lượng" min="0"></td>
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
            updateTotalAmounts();
        }

        function removeRow(button) { button.closest('tr').remove(); updateTotalAmounts(); }

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
        
        function saveImportInfo() {
            // Thu thập dữ liệu form
            const serial = document.getElementById('serial').value;
            const supplier = document.getElementById('supplier').value;
            const receiverName = document.getElementById('receiver-name').value;
            const importUnit = document.getElementById('import-unit').value;
            const invoiceNumber = document.getElementById('invoice-number').value;
            const importDate = document.getElementById('import-date').value;
            const totalBeforeVAT = parseCurrencyValue(document.getElementById('total-before-vat').value) || 0;
            const totalAfterVAT = parseCurrencyValue(document.getElementById('total-after-vat').value) || 0;

            const rows = document.querySelectorAll('.goods-table tbody tr');
            const goodsList = [];
            rows.forEach(row => {
                const goodsName = row.cells[0].querySelector('input').value;
                const goodsType = row.cells[1].querySelector('select').value;
                const quantity = row.cells[2].querySelector('input').value;
                const unit = row.cells[3].querySelector('input').value;
                const priceBeforeVAT = parseCurrencyValue(row.cells[4].querySelector('input').value);
                // Nếu VAT trống thì mặc định 0
                const vatInputVal = row.cells[5].querySelector('input').value;
                const vatPercent = (vatInputVal === '' || vatInputVal === null || vatInputVal === undefined) ? '0' : vatInputVal;
                const priceAfterVAT = parseCurrencyValue(row.cells[6].querySelector('input').value) || 0;
                const itemAmount = parseCurrencyValue(row.cells[7].querySelector('input').value) || 0;
                const totalAmount = parseCurrencyValue(row.cells[8].querySelector('input').value) || 0;
                const ghiChu = row.cells[9].querySelector('input').value;
                const serial = row.cells[10].querySelector('input').value;
                // Chấp nhận VAT = 0 (bao gồm cả khi để trống)
        		if (goodsName && goodsType && Number(quantity) > 0 && unit && (priceBeforeVAT >= 0)) {
                    goodsList.push({ name: goodsName, type: goodsType, quantity, unit, ghiChu, serial, priceBeforeVAT, vatPercent, priceAfterVAT, itemAmount, totalAmount });
                }
            });
            if (!serial || !supplier || !importUnit || !invoiceNumber) { alert('Vui lòng điền đầy đủ thông tin bắt buộc!'); return; }
            if (goodsList.length === 0) { alert('Vui lòng thêm ít nhất một hàng hóa!'); return; }

            const importData = { serial, supplier, receiverName, importUnit, invoiceNumber, importDate, totalBeforeVAT, totalAfterVAT, goodsCount: goodsList.length, goodsList };

            fetch('imports/save.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(importData) })
            .then(r => r.json())
            .then(data => {
                if (!data.success) { alert('Lỗi lưu: ' + (data.message || '')); return; }
                
                // Upload PDF hóa đơn trước
                const pdfFile = document.getElementById('pdf-file').files[0];
                if (pdfFile) {
                    const pdfFormData = new FormData();
                    pdfFormData.append('pdf_file', pdfFile);
                    pdfFormData.append('bill_id', data.import_bill_id);
                    
                    fetch('imports/upload-pdf.php', { method: 'POST', body: pdfFormData })
                    .then(r => r.json())
                    .then(pdfResult => {
                        if (!pdfResult.success) {
                            console.log('Lỗi upload PDF:', pdfResult.message);
                        }
                        // Tiếp tục upload ảnh sản phẩm
                        uploadProductImages(data.import_bill_id);
                    })
                    .catch(err => {
                        console.log('Lỗi upload PDF:', err);
                        // Tiếp tục upload ảnh sản phẩm
                        uploadProductImages(data.import_bill_id);
                    });
                } else {
                    // Không có PDF, chỉ upload ảnh sản phẩm
                    uploadProductImages(data.import_bill_id);
                }
            })
            .catch(() => alert('Có lỗi xảy ra khi lưu thông tin!'));
            
            function uploadProductImages(importBillId) {
                // Gom ảnh theo từng hàng và upload theo thứ tự
                const formData = new FormData();
                const rows = document.querySelectorAll('.goods-table tbody tr');
                rows.forEach((row, rowIndex) => {
                    const fileInput = row.cells[11].querySelector('input[type="file"]');
                    if (fileInput && fileInput.files && fileInput.files.length > 0) {
                        // Bỏ giới hạn số lượng ảnh/hàng
                        const max = fileInput.files.length;
                        for (let i = 0; i < max; i++) { 
                            formData.append('images[]', fileInput.files[i]);
                            formData.append('row_indices[]', rowIndex); // Thêm index của dòng
                        }
                    }
                });
                formData.append('import_bill_id', importBillId);
                fetch('imports/upload-images.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(up => {
                    if (!up.success) { alert('Lưu ảnh thất bại: ' + (up.message || '')); }
                    alert('Đã lưu thông tin nhập hàng thành công!');
                    resetForm();
                })
                .catch(err => { alert('Lưu thông tin thành công nhưng upload ảnh thất bại.'); resetForm(); });
            }
        }

        function importXMLData(xmlDoc) {
            try {
                const getText = (node, tag) => { const el = node.getElementsByTagName(tag)[0]; return el ? el.textContent : ''; };
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
                // Xử lý ngày nhập từ XML (NLap) - luôn chuyển về DD/MM/YYYY (ngày/tháng/năm)
                if (nLap) {
                    let dateValue = '';
                    const datePart = nLap.split(' ')[0]; // Lấy phần ngày nếu có thời gian
                    
                    // Nếu là định dạng YYYY-MM-DD
                    if (datePart.includes('-')) {
                        const parts = datePart.split('-');
                        if (parts.length === 3) {
                            // Kiểm tra nếu phần đầu là 4 chữ số (năm) thì là YYYY-MM-DD
                            if (parts[0].length === 4) {
                                // Chuyển từ YYYY-MM-DD sang DD/MM/YYYY
                                dateValue = `${parts[2]}/${parts[1]}/${parts[0]}`;
                            } 
                            // Nếu không phải YYYY-MM-DD, có thể là DD-MM-YYYY
                            else {
                                // DD-MM-YYYY -> DD/MM/YYYY (chỉ đổi dấu)
                                dateValue = `${parts[0]}/${parts[1]}/${parts[2]}`;
                            }
                        }
                    } 
                    // Nếu là định dạng có dấu /
                    else if (datePart.includes('/')) {
                        const parts = datePart.split('/');
                        if (parts.length === 3) {
                            // Kiểm tra nếu phần cuối là 4 chữ số (năm)
                            if (parts[2].length === 4) {
                                // Có thể là DD/MM/YYYY hoặc MM/DD/YYYY
                                // Nếu phần đầu > 12 thì chắc chắn là DD/MM/YYYY, giữ nguyên
                                if (parseInt(parts[0]) > 12) {
                                    // DD/MM/YYYY -> giữ nguyên
                                    dateValue = datePart;
                                } else {
                                    // Có thể là MM/DD/YYYY, cần đảo thành DD/MM/YYYY
                                    dateValue = `${parts[1]}/${parts[0]}/${parts[2]}`;
                                }
                            } else {
                                // Có thể là YYYY/MM/DD
                                dateValue = `${parts[2]}/${parts[1]}/${parts[0]}`;
                            }
                        }
                    }
                    // Nếu không có dấu - hoặc /, thử parse bằng Date object
                    else {
                        const parsedDate = new Date(nLap);
                        if (!isNaN(parsedDate.getTime())) {
                            const day = String(parsedDate.getDate()).padStart(2, '0');
                            const month = String(parsedDate.getMonth() + 1).padStart(2, '0');
                            const year = parsedDate.getFullYear();
                            dateValue = `${day}/${month}/${year}`;
                        }
                    }
                    if (dateValue) {
                        document.getElementById('import-date').value = dateValue;
                    }
                }
                const hhDVuList = xmlDoc.getElementsByTagName('HHDVu');
                const tbody = document.querySelector('.goods-table tbody'); tbody.innerHTML = '';
                for (let i = 0; i < hhDVuList.length; i++) {
                    const hhDVu = hhDVuList[i]; const tenHang = getText(hhDVu, 'THHDVu'); const soLuong = getText(hhDVu, 'SLuong') || '0'; const donVi = getText(hhDVu, 'DVTinh'); const donGia = getText(hhDVu, 'DGia') || '0'; const tSuat = getText(hhDVu, 'TSuat') || '0';
                    addNewGoodsRow(); const newRow = tbody.rows[tbody.rows.length - 1];
                    newRow.cells[0].querySelector('input').value = tenHang; newRow.cells[1].querySelector('select').value = 'Công cụ dụng cụ'; newRow.cells[2].querySelector('input').value = soLuong; newRow.cells[3].querySelector('input').value = donVi; newRow.cells[9].querySelector('input').value = ''; newRow.cells[10].querySelector('input').value = '';
                    // Giá trước thuế (cột 4) và VAT% (cột 5)
                    newRow.cells[4].querySelector('input').value = formatNumber(parseCurrencyValue(donGia));
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
                updateTotalAmounts(); alert('Đã import XML thành công!');
            } catch (error) { alert('Có lỗi khi xử lý dữ liệu XML!'); }
        }
        
        function resetForm() {
            document.getElementById('serial').value = ''; document.getElementById('supplier').value = ''; document.getElementById('invoice-number').value = ''; document.getElementById('total-before-vat').value = '0'; document.getElementById('total-after-vat').value = '0';
            const tbody = document.querySelector('.goods-table tbody'); tbody.innerHTML = '';
            document.getElementById('pdf-file').value = ''; document.getElementById('xml-file').value = ''; document.querySelectorAll('.file-chosen').forEach(span => { span.textContent = 'No file chosen'; });
        }
    </script>
</body>
</html>