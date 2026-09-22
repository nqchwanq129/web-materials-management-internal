<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Thủ kho')) {
    header('Location: ../auth/sign-in.php');
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$sql = "SELECT p.id, p.ten_san_pham, p.loai, p.don_vi, p.ngay_nhap, p.so_luong_con_lai, p.serial, p.ghi_chu,
    COALESCE(SUM(ibd.so_luong_nhap), 0) as tong_nhap,
    COALESCE(SUM(ebd.so_luong_xuat), 0) as tong_xuat,
    GROUP_CONCAT(DISTINCT ib.so_hoa_don ORDER BY ib.so_hoa_don SEPARATOR ', ') AS so_hoa_don_list
FROM products p
LEFT JOIN import_bill_details ibd ON p.id = ibd.product_id
LEFT JOIN import_bill ib ON ibd.import_bill_id = ib.id
LEFT JOIN export_bill_details ebd ON p.id = ebd.product_id
WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND p.ten_san_pham LIKE ?"; $params[] = "%$search%"; }
if ($type_filter) {
    $type_mapping = [ 'cong-cu' => 'Công cụ dụng cụ', 'vat-tu' => 'Vật tư', 'tai-san' => 'Tài sản cố định', 'phu-tung' => 'Phụ Tùng Thay Thế', 'khac' => 'Khác' ];
    if (isset($type_mapping[$type_filter])) { $sql .= " AND p.loai = ?"; $params[] = $type_mapping[$type_filter]; }
}
if ($start_date) { $sql .= " AND p.ngay_nhap >= ?"; $params[] = $start_date; }
if ($end_date) { $sql .= " AND p.ngay_nhap <= ?"; $params[] = $end_date; }
$sql .= " GROUP BY p.id ORDER BY p.ngay_nhap DESC, p.ten_san_pham ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $products = $stmt->fetchAll();

$type_names = [ 'cong-cu' => 'Công cụ dụng cụ', 'vat-tu' => 'Vật tư', 'tai-san' => 'Tài sản cố định', 'phu-tung' => 'Phụ Tùng Thay Thế', 'khac' => 'Khác' ];
$current_type_name = $type_filter ? ($type_names[$type_filter] ?? 'Tất Cả Hàng Hóa') : 'Tất Cả Hàng Hóa';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh Sách Hàng Hóa - Admin</title>
    <link rel="stylesheet" href="assets/css/shared/layout.css">
    <link rel="stylesheet" href="assets/css/products/index.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border:1px solid #eee; cursor: pointer; }
        .thumb-wrap { position: relative; display: inline-block; }
        .thumb-count { position:absolute; right:0; bottom:0; background:rgba(0,0,0,0.6); color:#fff; font-size:11px; padding:1px 4px; border-radius:3px; }
        .lightbox-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.7); display:none; align-items:center; justify-content:center; z-index:9999; }
        .lightbox { background:#fff; max-width:90vw; max-height:90vh; padding:10px; border-radius:6px; }
        .lightbox img { max-width:86vw; max-height:80vh; display:block; margin:0 auto; }
        .lightbox-nav { display:flex; justify-content:space-between; margin-top:8px; }
        .lightbox-btn { padding:6px 10px; border:1px solid #ddd; border-radius:4px; cursor:pointer; background:#f7f7f7; }
    </style>
</head>
<body>
    <script src="assets/js/shared/sidebar-toggle.js" defer></script>
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
                <li>Nhập hàng hóa
                    <ul>
                        <li><a href="imports/create.php">Nhập hóa đơn</a></li>
                        <li><a href="imports/index.php">DS phiếu nhập kho</a></li>
                    </ul>
                </li>
                <li>Xuất hàng hóa
                    <ul>
                        <li><a href="exports/create.php">Xuất hóa đơn</a></li>
                        <li><a href="exports/index.php">DS phiếu xuất kho</a></li>
                    </ul>
                </li>
                <li class="active">Danh Sách Hàng Hóa
                    <ul>
                        <li class="<?php echo !$type_filter ? 'active' : ''; ?>"><a href="products/index.php">Tất Cả Hàng Hóa</a></li>
                        <li class="<?php echo $type_filter === 'cong-cu' ? 'active' : ''; ?>"><a href="products/index.php?type=cong-cu">Công Cụ Dụng Cụ</a></li>
                        <li class="<?php echo $type_filter === 'vat-tu' ? 'active' : ''; ?>"><a href="products/index.php?type=vat-tu">Vật Tư</a></li>
                        <li class="<?php echo $type_filter === 'tai-san' ? 'active' : ''; ?>"><a href="products/index.php?type=tai-san">Tài Sản Cố Định</a></li>
                        <li class="<?php echo $type_filter === 'phu-tung' ? 'active' : ''; ?>"><a href="products/index.php?type=phu-tung">Phụ Tùng Thay Thế</a></li>
                        <li class="<?php echo $type_filter === 'khac' ? 'active' : ''; ?>"><a href="products/index.php?type=khac">Khác</a></li>
                    </ul>
                </li>
            </ul>
        </div>

        <div class="main-content">
            <!-- Form lọc theo ngày tháng năm -->
            <div class="filter-section" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e9ecef;">
                <form method="GET" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($type_filter); ?>">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600; color: #495057;">Từ ngày:</label>
                        <input type="text" name="start_date" class="date-picker" placeholder="dd/mm/yyyy" value="<?php echo htmlspecialchars($start_date); ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: 600; color: #495057;">Đến ngày:</label>
                        <input type="text" name="end_date" class="date-picker" placeholder="dd/mm/yyyy" value="<?php echo htmlspecialchars($end_date); ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <button type="submit" class="btn btn-primary">🔍 Lọc</button>
                    <a href="products/index.php<?php echo $type_filter ? '?type=' . urlencode($type_filter) : ''; ?>" class="btn btn-secondary" style="background: #6c757d; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none;">🔄 Xóa lọc</a>
                </form>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                <h2><?php echo htmlspecialchars($current_type_name); ?></h2>
                <div>
                    <?php if ($type_filter === 'cong-cu'): ?>
                        <a href="reports/tools-excel.php<?php echo ($start_date || $end_date) ? '?start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date) : ''; ?>" class="btn btn-success">📊 Xuất Excel CCDC</a>
                    <?php elseif ($type_filter === 'vat-tu'): ?>
                        <button type="button" class="btn btn-success" onclick="exportByTopDates('vat_tu')">📊 Xuất Excel Vật Tư</button>
                    <?php elseif ($type_filter === 'tai-san'): ?>
                        <a href="reports/fixed-assets-excel.php<?php echo ($start_date || $end_date) ? '?start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date) : ''; ?>" class="btn btn-success">📊 Xuất Excel TSCĐ</a>
                    <?php elseif ($type_filter === 'phu-tung'): ?>
                        <button type="button" class="btn btn-success" onclick="exportByTopDates('phu_tung')">📊 Xuất Excel Phụ Tùng</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section">
                <div class="section-header">
                    <h3>Danh sách hàng hóa</h3>
                    <span class="product-count">Tổng cộng: <?php echo count($products); ?> sản phẩm</span>
                </div>
                <div class="section-content">
                    <div class="table-container">
                        <table class="goods-table" id="goods-table">
                            <thead>
                                <tr>
                                    <th class="center-header">Hàng hóa</th>
                                    <th class="center">Loại</th>
                                    <th class="center">Đơn vị tính</th>
                                    <th class="center">Ngày nhập</th>
                                    <th class="center">Số HĐ nhập</th>
                                    <th class="center">SL nhập</th>
                                    <th class="center">SL xuất</th>
                                    <th class="center">SL còn lại</th>
                                    <th class="center-header">Ghi chú</th>
                                    <th class="center">Serial</th>
                                    <th class="center">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($products) > 0): ?>
                                    <?php foreach ($products as $p): ?>
                                        <?php
                                        // Lấy tất cả ảnh của sản phẩm này
                                        $stmt = $pdo->prepare('SELECT file_path, position FROM product_images WHERE product_id = ? ORDER BY position ASC, id ASC');
                                        $stmt->execute([$p['id']]);
                                        $productImages = $stmt->fetchAll();
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($p['ten_san_pham']); ?></td>
                                            <td class="center"><?php echo htmlspecialchars($p['loai']); ?></td>
                                            <td class="center"><?php echo htmlspecialchars($p['don_vi']); ?></td>
                                            <td class="center"><?php echo date('d/m/Y', strtotime($p['ngay_nhap'])); ?></td>
                                            <td class="center"><?php echo htmlspecialchars($p['so_hoa_don_list'] ?: '—'); ?></td>
                                            <td class="center"><?php echo (int)$p['tong_nhap']; ?></td>
                                            <td class="center"><?php echo (int)$p['tong_xuat']; ?></td>
                                            <td class="center"><?php echo (int)$p['so_luong_con_lai']; ?></td>
                                            <td><?php echo htmlspecialchars($p['ghi_chu'] ?? ''); ?></td>
                                            <td class="center"><?php echo htmlspecialchars($p['serial'] ?? ''); ?></td>
                                            <td class="center" style="white-space: nowrap;">
                                                <a class="btn btn-primary btn-sm" href="products/details.php?id=<?php echo (int)$p['id']; ?>">Xem chi tiết</a>
                                                <a class="btn btn-sm" href="products/edit.php?id=<?php echo (int)$p['id']; ?>" style="background: linear-gradient(135deg, #ff9800 0%, #e65100 100%); color: white; margin-left: 5px;">Sửa</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11" class="no-data">Không tìm thấy hàng hóa nào phù hợp</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="lightbox-backdrop" id="lb">
        <div class="lightbox">
            <img id="lb-img" src="" alt="preview">
            <div class="lightbox-nav">
                <button class="lightbox-btn" id="lb-prev">← Trước</button>
                <button class="lightbox-btn" id="lb-next">Tiếp →</button>
                <button class="lightbox-btn" id="lb-close">Đóng</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/vn.js"></script>
    <script>
        // Xuất Excel Vật tư/Phụ tùng dựa trên bộ lọc ngày phía trên
        function exportByTopDates(type) {
            const start = document.querySelector('input[name="start_date"]').value || '';
            const end = document.querySelector('input[name="end_date"]').value || '';
            // Suy ra tháng/năm từ ngày
            const toDate = end ? new Date(end) : new Date();
            const fromDate = start ? new Date(start) : new Date(toDate.getFullYear(), toDate.getMonth(), 1);
            const from_month = (fromDate.getMonth() + 1).toString();
            const from_year = fromDate.getFullYear().toString();
            const to_month = (toDate.getMonth() + 1).toString();
            const to_year = toDate.getFullYear().toString();

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = type === 'vat_tu' ? 'reports/materials-excel.php' : 'reports/replacement-parts-excel.php';

            [['from_month', from_month], ['from_year', from_year], ['to_month', to_month], ['to_year', to_year]].forEach(([k,v]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = k;
                input.value = v;
                form.appendChild(input);
            });
            document.body.appendChild(form);
            form.submit();
        }
        // Khởi tạo Flatpickr tiếng Việt cho bộ lọc ngày
        (function(){
            if (window.flatpickr) {
                flatpickr('.date-picker', {
                    locale: flatpickr.l10ns.vn,
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'd/m/Y',
                    allowInput: true
                });
            }
        })();

        // Load số ảnh và mở lightbox khi click
        const wraps = document.querySelectorAll('.thumb-wrap');
        wraps.forEach(w => {
            const productId = w.getAttribute('data-product-id');
            fetch('products/images.php?product_id=' + productId)
              .then(r => r.json())
              .then(data => {
                  if (data.success && data.images && data.images.length > 1) {
                      const c = w.querySelector('.thumb-count');
                      c.textContent = '+' + (data.images.length - 1);
                      c.style.display = 'inline-block';
                  }
                  let idx = 0; let list = (data.success ? data.images : []);
                  w.addEventListener('click', () => {
                      if (!list || list.length === 0) { return; }
                      idx = 0; document.getElementById('lb-img').src = list[idx].file_path; document.getElementById('lb').style.display = 'flex';
                      document.getElementById('lb-prev').onclick = () => { idx = (idx - 1 + list.length) % list.length; document.getElementById('lb-img').src = list[idx].file_path; };
                      document.getElementById('lb-next').onclick = () => { idx = (idx + 1) % list.length; document.getElementById('lb-img').src = list[idx].file_path; };
                      document.getElementById('lb-close').onclick = () => { document.getElementById('lb').style.display = 'none'; };
                  });
              });
        });
    </script>
</body>
</html>