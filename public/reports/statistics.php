<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Thủ kho')) {
    header('Location: ../auth/sign-in.php');
    exit;
}

// Lấy khoảng thời gian lọc từ GET parameters
$has_date_filter = isset($_GET['start_date']) && isset($_GET['end_date']) && $_GET['start_date'] !== '' && $_GET['end_date'] !== '';
$start_date = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : '';
$start_datetime = $start_date ? ($start_date . ' 00:00:00') : null; // Từ đầu ngày bắt đầu
$end_datetime = $end_date ? ($end_date . ' 23:59:59') : null; // Đến cuối ngày kết thúc

// Lấy thống kê tài khoản
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$userStats = $stmt->fetchAll();

$totalUsers = 0;
$adminCount = 0;
$thuKhoCount = 0;
$nguoiNhanCount = 0;

foreach ($userStats as $stat) {
    $totalUsers += $stat['count'];
    switch ($stat['role']) {
        case 'Admin':
            $adminCount = $stat['count'];
            break;
        case 'Thủ kho':
            $thuKhoCount = $stat['count'];
            break;
        case 'Người nhận hàng':
            $nguoiNhanCount = $stat['count'];
            break;
    }
}

// Lấy thống kê hàng hóa theo nhóm (trong khoảng thời gian được chọn)
if ($has_date_filter) {
    $stmt = $pdo->prepare("SELECT p.loai, COUNT(*) as count 
        FROM products p 
        JOIN import_bill_details ibd ON p.id = ibd.product_id 
        JOIN import_bill ib ON ibd.import_bill_id = ib.id 
        WHERE ib.ngay_nhap BETWEEN ? AND ? 
        GROUP BY p.loai");
    $stmt->execute([$start_datetime, $end_datetime]);
} else {
    $stmt = $pdo->query("SELECT p.loai, COUNT(*) as count 
        FROM products p 
        JOIN import_bill_details ibd ON p.id = ibd.product_id 
        JOIN import_bill ib ON ibd.import_bill_id = ib.id 
        GROUP BY p.loai");
    $stmt->execute();
}
$productStats = $stmt->fetchAll();

$totalProducts = 0;
$congCuCount = 0;
$vatTuCount = 0;
$taiSanCount = 0;
$phuTungCount = 0;

foreach ($productStats as $stat) {
    $totalProducts += $stat['count'];
    switch ($stat['loai']) {
        case 'Công cụ dụng cụ':
            $congCuCount = $stat['count'];
            break;
        case 'Vật tư':
            $vatTuCount = $stat['count'];
            break;
        case 'Tài sản cố định':
            $taiSanCount = $stat['count'];
            break;
        case 'Phụ tùng thay thế':
            $phuTungCount = $stat['count'];
            break;
    }
}

// Lấy thống kê hóa đơn (trong khoảng thời gian được chọn)
if ($has_date_filter) {
    $stmt = $pdo->prepare("SELECT 
        (SELECT COUNT(*) FROM import_bill WHERE ngay_nhap BETWEEN ? AND ?) as import_count,
        (SELECT COUNT(*) FROM export_bill WHERE ngay_nhan BETWEEN ? AND ?) as export_count");
    $stmt->execute([$start_datetime, $end_datetime, $start_datetime, $end_datetime]);
} else {
    $stmt = $pdo->query("SELECT 
        (SELECT COUNT(*) FROM import_bill) as import_count,
        (SELECT COUNT(*) FROM export_bill) as export_count");
    $stmt->execute();
}
$invoiceStats = $stmt->fetch();

$totalInvoices = $invoiceStats['import_count'] + $invoiceStats['export_count'];
$importInvoices = $invoiceStats['import_count'];
$exportInvoices = $invoiceStats['export_count'];

// Lấy thống kê số lượng hàng hóa - tính chính xác từ export_bill_details (trong khoảng thời gian được chọn)
if ($has_date_filter) {
    $stmt = $pdo->prepare("SELECT 
        SUM(p.so_luong_con_lai) as remaining_qty
    FROM products p
    JOIN import_bill_details ibd ON p.id = ibd.product_id
    JOIN import_bill ib ON ibd.import_bill_id = ib.id
    WHERE ib.ngay_nhap BETWEEN ? AND ?");
    $stmt->execute([$start_datetime, $end_datetime]);
} else {
    $stmt = $pdo->query("SELECT 
        SUM(p.so_luong_con_lai) as remaining_qty
    FROM products p");
    $stmt->execute();
}
$remainingStats = $stmt->fetch();

if ($has_date_filter) {
    $stmt = $pdo->prepare("SELECT 
        SUM(ebd.so_luong_xuat) as exported_qty
    FROM export_bill_details ebd
    JOIN export_bill eb ON ebd.export_bill_id = eb.id
    WHERE eb.ngay_nhan BETWEEN ? AND ?");
    $stmt->execute([$start_datetime, $end_datetime]);
} else {
    $stmt = $pdo->query("SELECT 
        SUM(ebd.so_luong_xuat) as exported_qty
    FROM export_bill_details ebd");
    $stmt->execute();
}
$exportedStats = $stmt->fetch();

$remainingQuantity = $remainingStats['remaining_qty'] ?? 0;
$exportedQuantity = $exportedStats['exported_qty'] ?? 0;
$totalQuantity = $remainingQuantity + $exportedQuantity;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Số liệu thống kê - Admin</title>
    <link rel="stylesheet" href="assets/css/shared/layout.css">
    <link rel="stylesheet" href="assets/css/reports/statistics.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <li class="active"><a href="reports/statistics.php">Số liệu thống kê</a></li>
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
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                <h2>Số liệu thống kê</h2>
                <div class="date-filter">
                    <label for="start-date">Từ ngày:</label>
                    <input type="text" id="start-date" name="start-date" class="date-picker" placeholder="dd/mm/yyyy" value="<?php echo $start_date; ?>">
                    <label for="end-date">Đến ngày:</label>
                    <input type="text" id="end-date" name="end-date" class="date-picker" placeholder="dd/mm/yyyy" value="<?php echo $end_date; ?>">
                    <button onclick="updateStatistics()" class="btn-filter">Cập nhật</button>
                </div>
            </div>
            
            <div class="stats-grid">
                <!-- Thống kê số lượng tài khoản -->
                <div class="stat-card">
                    <h3>Thống kê số lượng tài khoản</h3>
                    <div class="chart-container">
                        <canvas id="userChart"></canvas>
                    </div>
                    <div class="stats-details">
                        <p><strong>Tổng số tài khoản:</strong> <?php echo $totalUsers; ?></p>
                        <p><strong>Số tài khoản Admin:</strong> <?php echo $adminCount; ?> tài khoản (chiếm <?php echo $totalUsers > 0 ? number_format(($adminCount / $totalUsers) * 100, 2) : 0; ?>%)</p>
                        <p><strong>Số tài khoản Thủ Kho:</strong> <?php echo $thuKhoCount; ?> tài khoản (chiếm <?php echo $totalUsers > 0 ? number_format(($thuKhoCount / $totalUsers) * 100, 2) : 0; ?>%)</p>
                        <p><strong>Số tài khoản Người nhận:</strong> <?php echo $nguoiNhanCount; ?> tài khoản (chiếm <?php echo $totalUsers > 0 ? number_format(($nguoiNhanCount / $totalUsers) * 100, 2) : 0; ?>%)</p>
                        <p><em>Debug: Admin = <?php echo $adminCount; ?>, Thủ kho = <?php echo $thuKhoCount; ?>, Người nhận = <?php echo $nguoiNhanCount; ?></em></p>
                    </div>
                </div>

                <!-- Thống kê số lượng hàng hóa trong từng nhóm -->
                <div class="stat-card">
                    <h3>Thống kê số lượng hàng hóa trong từng nhóm</h3>
                    <div class="chart-container">
                        <canvas id="productChart"></canvas>
                    </div>
                    <div class="stats-details">
                        <p><strong>Tổng số hàng hóa:</strong> <?php echo $totalProducts; ?></p>
                        <p><strong>Số hàng hóa thuộc nhóm Công cụ dụng cụ:</strong> <?php echo $congCuCount; ?> hàng (chiếm <?php echo $totalProducts > 0 ? number_format(($congCuCount / $totalProducts) * 100, 2) : 0; ?>%)</p>
                        <p><strong>Số hàng hóa thuộc nhóm Vật tư:</strong> <?php echo $vatTuCount; ?> hàng (chiếm <?php echo $totalProducts > 0 ? number_format(($vatTuCount / $totalProducts) * 100, 2) : 0; ?>%)</p>
                        <p><strong>Số hàng hóa thuộc nhóm Phụ tùng thay thế:</strong> <?php echo $phuTungCount; ?> hàng (chiếm <?php echo $totalProducts > 0 ? number_format(($phuTungCount / $totalProducts) * 100, 2) : 0; ?>%)</p>
                        <p><strong>Số hàng hóa thuộc nhóm Tài sản cố định:</strong> <?php echo $taiSanCount; ?> hàng (chiếm <?php echo $totalProducts > 0 ? number_format(($taiSanCount / $totalProducts) * 100, 2) : 0; ?>%)</p>
                        <p><em>Debug: Công cụ = <?php echo $congCuCount; ?>, Vật tư = <?php echo $vatTuCount; ?>, Phụ tùng = <?php echo $phuTungCount; ?>, Tài sản = <?php echo $taiSanCount; ?></em></p>
                    </div>
                </div>

                <!-- Thống kê số lượng nhóm hóa đơn -->
                <div class="stat-card">
                    <h3>Thống kê số lượng nhóm hóa đơn</h3>
                    <div class="chart-container">
                        <canvas id="invoiceChart"></canvas>
                    </div>
                    <div class="stats-details">
                        <p><strong>Tổng số hóa đơn:</strong> <?php echo $totalInvoices; ?></p>
                        <p><strong>Số hóa đơn nhập:</strong> <?php echo $importInvoices; ?> (chiếm <?php echo $totalInvoices > 0 ? number_format(($importInvoices / $totalInvoices) * 100, 2) : 0; ?>%)</p>
                        <p><strong>Số hóa đơn xuất:</strong> <?php echo $exportInvoices; ?> (chiếm <?php echo $totalInvoices > 0 ? number_format(($exportInvoices / $totalInvoices) * 100, 2) : 0; ?>%)</p>
                        <p><em>Debug: Import bills = <?php echo $importInvoices; ?>, Export bills = <?php echo $exportInvoices; ?></em></p>
                    </div>
                </div>

                <!-- Thống kê số lượng hàng hóa -->
                <div class="stat-card">
                    <h3>Thống kê số lượng hàng hóa</h3>
                    <div class="chart-container">
                        <canvas id="quantityChart"></canvas>
                    </div>
                    <div class="stats-details">
                        <p><strong>Tổng số lượng hàng hóa:</strong> <?php echo $totalQuantity; ?></p>
                        <p><strong>Số lượng đã xuất:</strong> <?php echo $exportedQuantity; ?> (chiếm <?php echo $totalQuantity > 0 ? number_format(($exportedQuantity / $totalQuantity) * 100, 2) : 0; ?>%)</p>
                        <p><strong>Số lượng còn lại:</strong> <?php echo $remainingQuantity; ?> (chiếm <?php echo $totalQuantity > 0 ? number_format(($remainingQuantity / $totalQuantity) * 100, 2) : 0; ?>%)</p>
                        <p><em>Debug: Số lượng còn lại từ products = <?php echo $remainingQuantity; ?>, Số lượng đã xuất từ export_bill_details = <?php echo $exportedQuantity; ?></em></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/vn.js"></script>
    <script>
        // Khởi tạo Flatpickr tiếng Việt cho filter ngày
        (function(){
            if (window.flatpickr) {
                flatpickr('#start-date', {
                    locale: flatpickr.l10ns.vn,
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'd/m/Y',
                    allowInput: true
                });
                flatpickr('#end-date', {
                    locale: flatpickr.l10ns.vn,
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'd/m/Y',
                    allowInput: true
                });
            }
        })();
        // Hàm cập nhật thống kê khi thay đổi khoảng thời gian
        function updateStatistics() {
            const startDateInput = document.getElementById('start-date');
            const endDateInput = document.getElementById('end-date');
            // Lấy giá trị từ input gốc (Flatpickr tự động cập nhật với format Y-m-d)
            const startDate = startDateInput.value.trim();
            const endDate = endDateInput.value.trim();
            
            let url = 'reports/statistics.php';
            const params = [];
            if (startDate) {
                params.push('start_date=' + encodeURIComponent(startDate));
            }
            if (endDate) {
                params.push('end_date=' + encodeURIComponent(endDate));
            }
            if (params.length > 0) {
                url += '?' + params.join('&');
            }
            window.location.href = url;
        }

        // Biểu đồ thống kê tài khoản
        const userCtx = document.getElementById('userChart').getContext('2d');
        new Chart(userCtx, {
            type: 'pie',
            data: {
                labels: ['Admin', 'Thủ Kho', 'Người Nhận Hàng'],
                datasets: [{
                    data: [<?php echo $adminCount; ?>, <?php echo $thuKhoCount; ?>, <?php echo $nguoiNhanCount; ?>],
                    backgroundColor: ['#ff6b6b', '#ffd93d', '#6bcf7f'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Biểu đồ thống kê hàng hóa theo nhóm
        const productCtx = document.getElementById('productChart').getContext('2d');
        new Chart(productCtx, {
            type: 'pie',
            data: {
                labels: ['Công cụ dụng cụ', 'Vật tư', 'Phụ tùng thay thế', 'Tài sản cố định'],
                datasets: [{
                    data: [<?php echo $congCuCount; ?>, <?php echo $vatTuCount; ?>, <?php echo $phuTungCount; ?>, <?php echo $taiSanCount; ?>],
                    backgroundColor: ['#ff6b6b', '#ffd93d', '#4ecdc4', '#45b7d1'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Biểu đồ thống kê hóa đơn
        const invoiceCtx = document.getElementById('invoiceChart').getContext('2d');
        new Chart(invoiceCtx, {
            type: 'pie',
            data: {
                labels: ['Hóa đơn nhập', 'Hóa đơn xuất'],
                datasets: [{
                    data: [<?php echo $importInvoices; ?>, <?php echo $exportInvoices; ?>],
                    backgroundColor: ['#4ecdc4', '#45b7d1'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Biểu đồ thống kê số lượng hàng hóa
        const quantityCtx = document.getElementById('quantityChart').getContext('2d');
        new Chart(quantityCtx, {
            type: 'pie',
            data: {
                labels: ['Đã xuất', 'Còn lại'],
                datasets: [{
                    data: [<?php echo $exportedQuantity; ?>, <?php echo $remainingQuantity; ?>],
                    backgroundColor: ['#ffd93d', '#6bcf7f'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
<script src="assets/js/shared/sidebar-toggle.js" defer></script>
</body>
</html>