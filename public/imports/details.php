<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/helpers/money_parse.php';
require_once __DIR__ . '/../../app/helpers/csrf_helper.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'Thủ kho'], true)) {
    header('Location: ../auth/sign-in.php');
    exit;
}

require_once __DIR__ . '/../../app/config/database.php';
// Lấy ID hóa đơn từ URL
$billId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!$billId) {
    header('Location: ../imports/index.php');
    exit;
}

// Lấy thông tin hóa đơn nhập
$stmt = $pdo->prepare("SELECT * FROM import_bill WHERE id = ?");
$stmt->execute([$billId]);
$importBill = $stmt->fetch();

if (!$importBill) {
    header('Location: ../imports/index.php');
    exit;
}

require __DIR__ . '/../../app/helpers/import_bill_actions.php';

// Lấy danh sách hàng hóa của hóa đơn này
$stmt = $pdo->prepare("
    SELECT p.*, ibd.so_luong_nhap, ibd.don_gia, ibd.thanh_tien
    FROM products p
    INNER JOIN import_bill_details ibd ON p.id = ibd.product_id
    WHERE ibd.import_bill_id = ?
    ORDER BY ibd.id ASC
");
$stmt->execute([$billId]);
$products = $stmt->fetchAll();

// Lấy ảnh cho từng sản phẩm từ bảng product_images
foreach ($products as &$product) {
    $stmt = $pdo->prepare("
        SELECT file_path, position
        FROM product_images
        WHERE product_id = ?
        ORDER BY position ASC
    ");
    $stmt->execute([$product['id']]);
    $product['images'] = $stmt->fetchAll();
}
// Quan trọng: hủy tham chiếu để tránh làm hỏng dữ liệu khi foreach lần 2
unset($product);
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
    <title>Chi tiết phiếu nhập kho - Admin</title>
    <link rel="stylesheet" href="assets/css/dashboard/admin.css">
    <link rel="stylesheet" href="assets/css/imports/details.css">

</head>
<body>
<div class="dashboard">
    <aside class="sidebar" id="dashboard-sidebar">
        <a class="brand" href="<?= $home ?>"><span class="brand-mark">V</span><span>VISHIPEL</span></a>
        <div class="nav-label">TỔNG QUAN</div>
        <nav class="nav" aria-label="Điều hướng chính">
            <a href="<?= $home ?>"><span aria-hidden="true">▦</span>Bảng điều khiển</a>
            <a href="reports/statistics.php"><span aria-hidden="true">▥</span>Thống kê</a>
            <div class="nav-label">QUẢN LÝ KHO</div>
            <a href="products/index.php"><span aria-hidden="true">◫</span>Hàng hóa</a>
            <a class="active" aria-current="page" href="imports/index.php"><span aria-hidden="true">↙</span>Phiếu nhập kho</a>
            <a href="imports/create.php"><span aria-hidden="true">＋</span>Tạo phiếu nhập</a>
            <a href="exports/index.php"><span aria-hidden="true">↗</span>Phiếu xuất kho</a>
            <a href="exports/create.php"><span aria-hidden="true">＋</span>Tạo phiếu xuất</a>
            <?php if ($_SESSION['role'] === 'Admin'): ?><div class="nav-label">HỆ THỐNG</div><a href="accounts/index.php"><span aria-hidden="true">♙</span>Tài khoản</a><?php endif; ?>
        </nav>
        <div class="sidebar-user"><span class="avatar" aria-hidden="true">V</span><span><strong><?= importEsc($name) ?></strong><small><?= importEsc($_SESSION['role']) ?></small></span><a href="auth/log-out.php" aria-label="Đăng xuất" title="Đăng xuất">⇥</a></div>
    </aside>
    <div class="content-shell">
        <header class="topbar"><div class="topbar-left"><button class="menu-toggle" type="button" aria-controls="dashboard-sidebar" aria-expanded="false" aria-label="Mở menu">☰</button><div><span class="breadcrumb">Phiếu nhập kho / Chi tiết</span><h1>Chi tiết phiếu nhập kho</h1></div></div><div class="topbar-actions"><span><?= date('d/m/Y') ?></span><span class="top-avatar" aria-hidden="true">V</span></div></header>
        <main class="main-content">
            <a class="back-link" href="imports/index.php">← Danh sách phiếu nhập kho</a>
            <div class="page-intro"><div><span class="eyebrow">CHI TIẾT PHIẾU NHẬP</span><h2>Hóa đơn <?= importEsc($importBill['so_hoa_don']) ?></h2><p>Kiểm tra thông tin, hàng hóa và chứng từ của phiếu nhập.</p></div><a href="imports/export-excel.php?id=<?= (int) $billId ?>" class="btn btn-success">Xuất Excel</a></div>
            <div class="receipt-summary"><div><span>Ngày nhập</span><strong><?= date('d/m/Y', strtotime($importBill['ngay_nhap'])) ?></strong></div><div><span>Số mặt hàng</span><strong><?= count($products) ?></strong></div><div><span>Tổng tiền đã lưu (VNĐ)</span><strong><?= importEsc(formatVnAmount($importBill['tong_tien'])) ?></strong></div></div>
            <nav class="section-links" aria-label="Các phần của phiếu"><a href="imports/details.php?id=<?= (int) $billId ?>#invoice-info">Thông tin phiếu</a><a href="imports/details.php?id=<?= (int) $billId ?>#goods">Hàng hóa</a><a href="imports/details.php?id=<?= (int) $billId ?>#documents">Chứng từ PDF</a></nav>
            <noscript><p class="notice">Bạn có thể lưu thông tin phiếu và hàng hóa. Vui lòng bật JavaScript để tải ảnh, PDF và xem trước tệp.</p></noscript>
            <div id="save-notice" role="status" aria-live="polite"></div>
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                    ✅ <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
                    ❌ <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

<div class="receipt-overview">            <!-- Phần Thông Tin Hóa Đơn -->
            <div class="section" id="invoice-info">
                <div class="section-header">
                    <h3>Thông tin phiếu nhập</h3><p>Nhà cung cấp, nơi nhận và thông tin hóa đơn.</p>
                </div>
                <div class="section-content">
                    <div class="invoice-form">
                        <div class="form-row">
                            <div class="form-column">
                                <div class="form-group">
                                    <label for="supplier">Nhà Cung Cấp:</label>
                                    <input form="save-form" type="text" id="supplier" name="supplier"
                                           value="<?php echo htmlspecialchars($importBill['nha_cung_cap'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="import-unit">Nhập vào kho:</label>
                                    <input form="save-form" type="text" id="import-unit" name="import-unit"
                                           value="<?php echo htmlspecialchars($importBill['nhap_vao_don_vi'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="import-date">Ngày Nhập:</label>
                                    <div class="date-input-container">
                                        <input form="save-form" type="date" id="import-date" name="import-date" class="date-picker" placeholder="dd/mm/yyyy"
                                               value="<?php echo date('Y-m-d', strtotime($importBill['ngay_nhap'] ?? date('Y-m-d'))); ?>" required>

                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="invoice-number">Số Hóa Đơn:</label>
                                    <input form="save-form" type="text" id="invoice-number" name="invoice-number"
                                           value="<?php echo htmlspecialchars($importBill['so_hoa_don'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="form-column">
                                <div class="form-group">
                                    <label for="receiver-name">Họ và tên người nhập hàng:</label>
                                    <input form="save-form" type="text" id="receiver-name" name="receiver-name"
                                           value="<?php echo htmlspecialchars($importBill['nguoi_nhan_hang'] ?? ''); ?>"
                                           placeholder="Nhập họ tên người nhập hàng">
                                </div>
                                <div class="form-group">
                                    <label for="serial">Số Serial:</label>
                                    <input form="save-form" type="text" id="serial" name="serial"
                                           value="<?php echo htmlspecialchars($importBill['serial'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="total-amount">Tổng Tiền (VNĐ):</label>
                                    <input form="save-form" type="text" id="total-amount" name="total-amount"
                                           value="<?php echo htmlspecialchars(formatVnAmount($importBill['tong_tien'] ?? 0)); ?>" required>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Phần Hóa Đơn PDF -->
            <div class="section" id="documents">
                <div class="section-header">
                    <h3>Hóa Đơn PDF</h3>
                </div>
                <div class="section-content">
                    <?php
                    // Ưu tiên đường dẫn PDF trong database; chỉ hiển thị nếu có lưu đường dẫn
                    $pdf_file_path = isset($importBill['pdf_path']) ? $importBill['pdf_path'] : null;
                    $pdf_exists = is_string($pdf_file_path) && preg_match('~^uploads/invoices/[a-zA-Z0-9_.-]+\.pdf$~D', $pdf_file_path) && is_file($pdf_file_path);
                    ?>
                    <!-- Form upload PDF mới - luôn hiển thị để có thể thay thế PDF cũ -->
                    <div class="pdf-upload-section" style="margin-bottom: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 2px dashed #dee2e6;">
                        <h4 style="margin-top: 0; color: #495057;">
                            <?php if ($pdf_exists): ?>
                                📄 Thay thế hóa đơn PDF hiện tại
                            <?php else: ?>
                                📄 Tải lên hóa đơn PDF
                            <?php endif; ?>
                        </h4>
                        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                            <input type="file" name="pdf_file" accept=".pdf" id="pdf-file" style="flex: 1; min-width: 200px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <span style="color: #666; font-size: 14px;">Chọn PDF để thay thế (sẽ lưu khi bấm "Lưu Hóa Đơn")</span>
                        </div>
                        <div id="pdf-preview" style="margin-top: 15px;"></div>
                    </div>

                    <?php if ($pdf_exists): ?>
                        <details class="pdf-info"><summary>Xem hóa đơn PDF đã lưu</summary>
                            <iframe src="<?php echo htmlspecialchars($pdf_file_path); ?>?t=<?php echo time(); ?>"
                                    style="width:100%; height:600px; border:1px solid #e0e0e0; border-radius:6px;"
                                    title="Hóa đơn PDF"></iframe>
                            <div style="margin-top:12px; text-align:right; display:flex; justify-content:flex-end; gap:10px;">
                                <a class="btn btn-secondary" download="hoa_don_<?php echo htmlspecialchars($importBill['so_hoa_don'] ?? ''); ?>.pdf" href="<?php echo htmlspecialchars($pdf_file_path); ?>?t=<?php echo time(); ?>">Tải xuống PDF</a>
                                <a class="btn btn-primary" target="_blank" rel="noopener" href="<?php echo htmlspecialchars($pdf_file_path); ?>?t=<?php echo time(); ?>">Mở PDF trong tab mới</a>
                            </div>
                        </details>
                    <?php else: ?>
                        <div class="pdf-placeholder">
                            <i class="fas fa-file-pdf"></i>
                            <p>Chưa có hóa đơn PDF</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

</div>            <!-- Phần Danh Sách Hàng Hóa -->
            <div class="section" id="goods">
                <div class="section-header">
                    <h3>Hàng hóa trong phiếu <span class="count-badge"><?= count($products) ?></span></h3><p>Kiểm tra số lượng và giá từng mặt hàng. Mở phần bổ sung khi cần ghi chú hoặc thêm ảnh.</p>
                </div>
                <div class="section-content">
                    <?php if (empty($products)): ?>
                        <div class="no-data">
                            <p>Không có hàng hóa nào trong hóa đơn này</p>
                        </div>
                    <?php else: ?>
                        <div class="goods-container">
                            <div class="goods-cards">
                                    <?php foreach ($products as $productIndex => $product): ?>
                                        <article class="goods-card"><div class="goods-card-heading"><span class="item-index"><?= $productIndex + 1 ?></span><h4><?= importEsc($product['ten_san_pham']) ?></h4><a href="products/details.php?id=<?= (int) $product['id'] ?>">Xem hàng hóa ↗</a></div><div class="goods-fields">
                                            <div class="goods-field "><label class="field-label">Hàng hóa</label>
<input form="save-form" type="text" name="products[<?php echo $product['id']; ?>][ten_san_pham]"
                                                       value="<?php echo htmlspecialchars($product['ten_san_pham']); ?>"
                                                       class="form-input" required>
                                            </div>
                                            <div class="goods-field "><label class="field-label">Nhóm hàng</label>
<select form="save-form" name="products[<?php echo $product['id']; ?>][loai]" class="form-input" required>
                                                    <option value="Công cụ dụng cụ" <?php echo $product['loai'] === 'Công cụ dụng cụ' ? 'selected' : ''; ?>>Công cụ dụng cụ</option>
                                                    <option value="Vật tư" <?php echo $product['loai'] === 'Vật tư' ? 'selected' : ''; ?>>Vật tư</option>
                                                    <option value="Tài sản cố định" <?php echo $product['loai'] === 'Tài sản cố định' ? 'selected' : ''; ?>>Tài sản cố định</option>
                                                    <option value="Phụ tùng thay thế" <?php echo $product['loai'] === 'Phụ tùng thay thế' ? 'selected' : ''; ?>>Phụ tùng thay thế</option>
                                                    <option value="Khác" <?php echo $product['loai'] === 'Khác' ? 'selected' : ''; ?>>Khác</option>
                                                </select>
                                            </div>
                                            <div class="goods-field "><label class="field-label">Đơn vị tính</label>
<input form="save-form" type="text" name="products[<?php echo $product['id']; ?>][don_vi]"
                                                       value="<?php echo htmlspecialchars($product['don_vi']); ?>"
                                                       class="form-input" required>
                                            </div>
                                            <div class="goods-field "><label class="field-label">Số lượng</label>
<input form="save-form" type="number" name="products[<?php echo $product['id']; ?>][so_luong_nhap]"
                                                       value="<?php echo $product['so_luong_nhap']; ?>"
                                                       class="form-input center" min="1" required>
                                            </div>
                                            <div class="goods-field "><label class="field-label">Giá trước thuế (VNĐ)</label>
<input form="save-form" type="text" name="products[<?php echo $product['id']; ?>][don_gia]"
                                                       value="<?php echo htmlspecialchars(formatVnAmount($product['don_gia'])); ?>"
                                                       class="form-input amount" required>
                                            </div>
                                            <?php
                                                // Suy ra VAT% từ dữ liệu: dùng tổng tiền sau thuế ở chi tiết (ibd.thanh_tien)
                                                $soLuong = max(1, (int)$product['so_luong_nhap']);
                                                $donGiaTruocThue = (float)$product['don_gia'];
                                                $tongSauThue = (float)$product['thanh_tien'];
                                                $donGiaSauThue = $soLuong > 0 ? ($tongSauThue / $soLuong) : $donGiaTruocThue;
                                                $vatPercentInferred = $donGiaTruocThue > 0.00001
                                                    ? max(0, (int)round(($donGiaSauThue / $donGiaTruocThue - 1) * 100))
                                                    : 0;
                                            ?>
                                            <div class="goods-field "><label class="field-label">VAT (%)</label>
<input form="save-form" type="text" name="products[<?php echo $product['id']; ?>][vat]"
                                                       value="<?php echo $vatPercentInferred; ?>"
                                                       class="form-input center" required>
                                            </div>
                                            <div class="goods-field amount"><label>Giá sau thuế (VNĐ)</label><output data-value="unit-after"><?php echo htmlspecialchars(formatVnAmount($donGiaSauThue)); ?></output></div>
                                            <div class="goods-field amount"><label>Tổng trước thuế (VNĐ)</label><output data-value="total-before"><?php echo htmlspecialchars(formatVnAmount($soLuong * $donGiaTruocThue)); ?></output></div>
                                            <div class="goods-field amount"><label>Tổng sau thuế (VNĐ)</label><output data-value="total-after"><?php echo htmlspecialchars(formatVnAmount($tongSauThue)); ?></output></div>
                                            </div><details class="item-extras"><summary>Ghi chú, serial & ảnh sản phẩm <span><?= count($product['images']) ?> ảnh</span></summary><div class="extras-grid"><div class="goods-field "><label class="field-label">Ghi chú</label>
<input form="save-form" type="text" name="products[<?php echo $product['id']; ?>][ghi_chu]"
                                                       value="<?php echo htmlspecialchars($product['ghi_chu'] ?? ''); ?>"
                                                       class="form-input">
                                            </div>
                                            <div class="goods-field "><label class="field-label">Serial</label>
<input form="save-form" type="text" name="products[<?php echo $product['id']; ?>][serial]"
                                                       value="<?php echo htmlspecialchars($product['serial'] ?? ''); ?>"
                                                       class="form-input">
                                            </div>
                                            <div class="goods-field "><label>Ảnh</label>
                                                <div class="image-upload-container">
                                                    <input form="save-form" type="file" name="products[<?php echo $product['id']; ?>][image]"
                                                           accept="image/*" class="image-upload-input"
                                                           multiple
                                                           onchange="uploadProductImages(<?php echo $product['id']; ?>, this)"
                                                           title="Chọn một hoặc nhiều ảnh">
                                                    <div class="image-preview" id="preview_<?php echo $product['id']; ?>">
                                                        <span class="thumb-wrap" data-product-id="<?php echo (int)$product['id']; ?>">
                                                            <?php if (!empty($product['images']) && count($product['images']) > 0): ?>
                                                                <img class="thumb" loading="lazy" src="<?php echo htmlspecialchars($product['images'][0]['file_path']); ?>?t=<?php echo time(); ?>" alt="thumb">
                                                                <?php if (count($product['images']) > 1): ?>
                                                                    <span class="thumb-count">+<?php echo count($product['images']) - 1; ?></span>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span style="color:#aaa;">—</span>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div></details></article>
                                    <?php endforeach; ?>

                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Nút Lưu và Xóa Hóa Đơn -->
            <div class="action-section"><span id="edit-status" class="edit-status" role="status">Thay đổi sẽ được lưu khi bạn bấm Lưu phiếu.</span>
                <form id="save-form" method="POST" enctype="multipart/form-data" style="display: inline-block; margin-right: 15px;">
                    <input type="hidden" name="action" value="save">
                    <?php echo csrfTokenField(); ?>
                    <button type="submit" class="btn btn-primary">Lưu phiếu nhập</button>
                </form>

                <form method="POST" style="display: inline-block;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa hóa đơn này? Hành động này không thể hoàn tác!')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="bill_id" value="<?php echo $billId; ?>">
                    <?php echo csrfTokenField(); ?>
                    <button type="submit" class="btn btn-danger">Xóa phiếu</button>
                </form>
            </div>
        </main>
    </div>
</div>

    <!-- Lightbox for images -->
    <div class="lightbox-backdrop" id="lb" role="dialog" aria-label="Ảnh sản phẩm">
        <div class="lightbox">
            <img id="lb-img" src="" alt="preview">
            <div class="lightbox-nav">
                <button class="lightbox-btn" id="lb-prev">← Trước</button>
                <button class="lightbox-btn" id="lb-next">Tiếp →</button>
                <button class="lightbox-btn" id="lb-close">Đóng</button>
            </div>
        </div>
    </div>

    <script>
        const menu = document.querySelector('.menu-toggle');
        const setMenu = open => { document.body.classList.toggle('menu-open', open); menu.setAttribute('aria-expanded', String(open)); };
        menu.addEventListener('click', () => setMenu(!document.body.classList.contains('menu-open')));
        document.addEventListener('click', e => { if (!e.target.closest('.sidebar, .menu-toggle')) setMenu(false); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') { setMenu(false); document.getElementById('lb').style.display = 'none'; } });
        const notice = (message, error = false) => { const box = document.getElementById('save-notice'); box.textContent = message; box.className = error ? 'notice error' : 'notice success'; box.scrollIntoView({block: 'center', behavior: 'smooth'}); };
        document.querySelectorAll('.invoice-form input, .goods-card input:not([type="file"]), .goods-card select').forEach(input => input.setAttribute('form', 'save-form'));
        document.querySelectorAll('.goods-field').forEach((field, index) => {
            const input = field.querySelector('input, select');
            const label = field.querySelector('label');
            if (input && label) { input.id ||= 'goods-field-' + index; label.htmlFor = input.id; }
        });
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

        document.getElementById('total-amount').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^\d.,]/g, '').replace(/,(?=.*,)/g, '');
        });
        document.getElementById('total-amount').addEventListener('blur', function(e) {
            const v = parseCurrencyValue(e.target.value);
            if (v !== 0 || e.target.value.trim() !== '') e.target.value = formatNumber(v);
        });

        document.querySelectorAll('input[name*="[don_gia]"]').forEach(input => {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/[^\d.,]/g, '').replace(/,(?=.*,)/g, '');
            });
            input.addEventListener('blur', function() {
                const v = parseCurrencyValue(this.value);
                if (v !== 0 || this.value.trim() !== '') this.value = formatNumber(v);
            });
        });

        document.querySelectorAll('input[name*="[so_luong_nhap]"], input[name*="[don_gia]"], input[name*="[vat]"]').forEach(input => {
            input.addEventListener('input', function() {
                const row = this.closest('.goods-card');
                const soLuongInput = row.querySelector('input[name*="[so_luong_nhap]"]');
                const donGiaInput = row.querySelector('input[name*="[don_gia]"]');
                const vatInput = row.querySelector('input[name*="[vat]"]');
                if (soLuongInput && donGiaInput && vatInput) {
                    const soLuong = parseInt(soLuongInput.value, 10) || 0;
                    const donGia = parseCurrencyValue(donGiaInput.value);
                    const vat = parseFloat(vatInput.value) || 0;
                    const giaSauThue = Math.round((donGia * (1 + vat / 100)) * 1000) / 1000;
                    const tongGiaTruocThue = Math.round(soLuong * donGia * 1000) / 1000;
                    const tongGiaSauThue = Math.round((tongGiaTruocThue + (tongGiaTruocThue * vat / 100)) * 1000) / 1000;
                    const giaSauThueCell = row.querySelector('[data-value="unit-after"]');
                    const thanhTienCell = row.querySelector('[data-value="total-before"]');
                    const tongTienCell = row.querySelector('[data-value="total-after"]');
                    if (giaSauThueCell) giaSauThueCell.textContent = formatNumber(giaSauThue);
                    if (thanhTienCell) thanhTienCell.textContent = formatNumber(tongGiaTruocThue);
                    if (tongTienCell) tongTienCell.textContent = formatNumber(tongGiaSauThue);
                }
            });
        });

        // Xử lý hiển thị tên file được chọn
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const fileName = this.files[0] ? this.files[0].name : 'No file chosen';
                const label = this.parentElement.querySelector('.file-chosen'); if (label) label.textContent = fileName;
            });
        });

        // Load số ảnh và mở lightbox khi click
        const wraps = document.querySelectorAll('.thumb-wrap');
        wraps.forEach(w => {
            const productId = w.getAttribute('data-product-id');

            // Lấy dữ liệu ảnh từ PHP thay vì gọi API
            const productRow = w.closest('.goods-card');
            const productImages = <?php echo json_encode(array_column($products, 'images', 'id'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            const images = productImages[productId] || [];

            if (images && images.length > 1) {
                const c = w.querySelector('.thumb-count');
                if (c) {
                    c.textContent = '+' + (images.length - 1);
                    c.style.display = 'inline-block';
                }
            }

            let idx = 0;
            let list = images.map(img => ({ file_path: img.file_path }));

            w.tabIndex = 0; w.setAttribute('role', 'button'); w.setAttribute('aria-label', 'Xem ảnh sản phẩm'); w.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); w.click(); } });
            w.addEventListener('click', () => {
                if (!list || list.length === 0) { return; }
                idx = 0;
                document.getElementById('lb-img').src = list[idx].file_path;
                document.getElementById('lb').style.display = 'flex'; document.getElementById('lb-close').focus();
                document.getElementById('lb-prev').onclick = () => {
                    idx = (idx - 1 + list.length) % list.length;
                    document.getElementById('lb-img').src = list[idx].file_path;
                };
                document.getElementById('lb-next').onclick = () => {
                    idx = (idx + 1) % list.length;
                    document.getElementById('lb-img').src = list[idx].file_path;
                };
                document.getElementById('lb-close').onclick = () => {
                    document.getElementById('lb').style.display = 'none'; w.focus();
                };
            });
        });

        // Lưu trữ ảnh tạm thời để upload khi bấm lưu
        let pendingImages = {};

        // Xử lý chọn ảnh sản phẩm (chỉ preview, chưa upload)
        function uploadProductImages(productId, input) {
            const files = input.files;
            if (!files || files.length === 0) return;

            // Lưu files vào biến tạm
            pendingImages[productId] = files;

            // Preview ảnh đầu tiên ngay lập tức
            const preview = document.getElementById('preview_' + productId);
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = '<span class="thumb-wrap" data-product-id="' + productId + '"><img class="thumb" loading="lazy" src="' + e.target.result + '" alt="thumb"><span class="thumb-count" style="display: none;">+0</span></span>';
            };
            reader.readAsDataURL(files[0]);
        }

        // Lưu trữ PDF tạm thời để upload khi bấm lưu
        let pendingPDF = null;

        // Xử lý chọn PDF (chỉ preview, chưa upload)
        const pdfInput = document.getElementById('pdf-file');
        if (pdfInput) {
            pdfInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    pendingPDF = file;

                    // Preview PDF
                    const pdfPreview = document.getElementById('pdf-preview');
                    if (pdfPreview) {
                        const url = URL.createObjectURL(file);
                        pdfPreview.innerHTML = `
                            <iframe src="${url}" width="100%" height="400px" style="border: 1px solid #ddd; border-radius: 4px;"></iframe>
                            <p style="margin-top: 10px; color: #666;">PDF đã chọn: ${file.name.replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;", "'":"&#39;"}[c]))}</p>
                        `;
                    }
                } else {
                    pendingPDF = null;
                }
            });
        }

        // Xử lý lưu hóa đơn
        const saveForm = document.getElementById('save-form');
        document.querySelectorAll('.invoice-form input, .goods-card input, .goods-card select, #pdf-file').forEach(input => {
            input.addEventListener('input', () => {
                document.getElementById('edit-status').textContent = 'Có thay đổi chưa lưu';
                document.getElementById('edit-status').classList.add('is-dirty');
            });
        });
        if (saveForm) {
            saveForm.addEventListener('submit', function(e) {
                e.preventDefault();
                document.querySelectorAll('.item-extras').forEach(details => {
                    if ([...details.querySelectorAll('input')].some(input => !input.validity.valid)) details.open = true;
                });
                if (!this.reportValidity()) return;
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Đang lưu...';
                submitBtn.disabled = true;

                // Thu thập dữ liệu từ form
                const formData = new FormData();
                formData.append('action', 'save');

                // Thông tin hóa đơn
                const supplier = document.getElementById('supplier').value;
                const receiverName = document.getElementById('receiver-name').value;
                const importUnit = document.getElementById('import-unit').value;
                const importDate = document.getElementById('import-date').value;
                const invoiceNumber = document.getElementById('invoice-number').value;
                const serial = document.getElementById('serial').value;
                const totalAmount = document.getElementById('total-amount').value;

                // Thêm CSRF token
                const csrfToken = document.querySelector('input[name="csrf_token"]').value;
                formData.append('csrf_token', csrfToken);

                formData.append('supplier', supplier);
                formData.append('receiver-name', receiverName);
                formData.append('import-unit', importUnit);
                formData.append('import-date', importDate);
                formData.append('invoice-number', invoiceNumber);
                formData.append('serial', serial);
                formData.append('total-amount', totalAmount);

                // Thông tin hàng hóa
                const productRows = document.querySelectorAll('.goods-card');
                productRows.forEach((row, index) => {
                    const productIdMatch = row.querySelector('input[name*="[so_luong_nhap]"]')?.name.match(/\[(\d+)\]/);
                    if (productIdMatch) {
                        const productId = productIdMatch[1];
                        formData.append(`products[${productId}][ten_san_pham]`, row.querySelector('input[name*="[ten_san_pham]"]')?.value || '');
                        formData.append(`products[${productId}][loai]`, row.querySelector('select[name*="[loai]"]')?.value || '');
                        formData.append(`products[${productId}][don_vi]`, row.querySelector('input[name*="[don_vi]"]')?.value || '');
                        formData.append(`products[${productId}][so_luong_nhap]`, row.querySelector('input[name*="[so_luong_nhap]"]')?.value || '0');
                        formData.append(`products[${productId}][don_gia]`, row.querySelector('input[name*="[don_gia]"]')?.value || '0');
                        formData.append(`products[${productId}][vat]`, row.querySelector('input[name*="[vat]"]')?.value || '0');
                        formData.append(`products[${productId}][ghi_chu]`, row.querySelector('input[name*="[ghi_chu]"]')?.value || '');
                        formData.append(`products[${productId}][serial]`, row.querySelector('input[name*="[serial]"]')?.value || '');
                    }
                });

                // Upload ảnh trước
                const uploadPromises = [];

                // Upload ảnh sản phẩm
                Object.keys(pendingImages).forEach(productId => {
                    const files = pendingImages[productId];
                    if (files && files.length > 0) {
                        const imageFormData = new FormData();
                        imageFormData.append('action', 'upload_multiple_product_images');
                        imageFormData.append('product_id', productId);
                        imageFormData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

                        for (let i = 0; i < files.length; i++) {
                            imageFormData.append('images[]', files[i]);
                        }

                        uploadPromises.push(
                            fetch('products/upload-images.php', {
                                method: 'POST',
                                body: imageFormData
                            }).then(response => response.json())
                        );
                    }
                });

                // Upload PDF nếu có
                if (pendingPDF) {
                    const pdfFormData = new FormData();
                    pdfFormData.append('bill_id', '<?php echo $billId; ?>');
                    pdfFormData.append('pdf_file', pendingPDF);
                    pdfFormData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

                    uploadPromises.push(
                        fetch('imports/upload-pdf.php', {
                            method: 'POST',
                            body: pdfFormData
                        }).then(response => response.json())
                    );
                }

                // Thực hiện tất cả upload
                Promise.all(uploadPromises)
                .then(results => {
                    // Kiểm tra kết quả upload
                    const failedUploads = results.filter(result => !result.success);
                    let uploadMessage = '';
                    if (failedUploads.length > 0) {
                        const errorMessages = failedUploads.map(result => result.message || 'Upload thất bại').join('\n');
                        // Phân biệt lỗi upload ảnh và PDF
                        if (errorMessages.includes('ảnh')) {
                            uploadMessage = 'upload ảnh thất bại';
                        } else if (errorMessages.includes('PDF')) {
                            uploadMessage = 'upload PDF thất bại';
                        } else {
                            uploadMessage = 'upload file thất bại';
                        }
                    }

                    // Lưu thông tin hóa đơn ngay cả khi upload thất bại
                    return fetch(window.location.href, {
                        method: 'POST',
                        body: formData, headers: {'Accept': 'application/json'}
                    }).then(async response => {
                        const result = await response.json();
                        if (!response.ok || !result.success) throw new Error(result.message || 'Không thể lưu phiếu nhập.');
                        return { response, uploadMessage };
                    });
                })
                .then(({ response, uploadMessage }) => {
                    if (response.ok) {
                        if (uploadMessage) {
                            notice('Đã lưu thông tin nhưng ' + uploadMessage.toLowerCase() + '. Vui lòng chọn lại tệp và thử lại.', true);
                        } else {
                            notice('Đã lưu phiếu nhập thành công.');
                        }
                        // Xóa pending images và PDF sau khi lưu thành công
                        pendingImages = {};
                        pendingPDF = null;
                        if (!uploadMessage) location.reload();
                    } else {
                        notice('Không thể lưu phiếu nhập. Vui lòng thử lại.', true);
                    }
                })
                .catch(error => {
                    notice(error.message || 'Không thể lưu phiếu nhập. Vui lòng thử lại.', true);
                })
                .finally(() => {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                });
            });
        }
    </script>
</body>
</html>
