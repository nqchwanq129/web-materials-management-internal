<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/money_parse.php';

// Kiểm tra quyền truy cập
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Thủ kho')) {
    header('Location: ../auth/sign-in.php');
    exit;
}

// Xử lý tìm kiếm
$searchTerm = '';
$importBills = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $searchTerm = trim($_POST['search']);
    
    if ($searchTerm !== '') {
        $stmt = $pdo->prepare("SELECT * FROM import_bill WHERE so_hoa_don LIKE ? ORDER BY id DESC");
        $stmt->execute(['%' . $searchTerm . '%']);
        $importBills = $stmt->fetchAll();
    } else {
        // Nếu không nhập gì mà bấm tìm, hiển thị tất cả
        $stmt = $pdo->query("SELECT * FROM import_bill ORDER BY id DESC");
        $importBills = $stmt->fetchAll();
    }
} else {
    // Hiển thị tất cả hóa đơn nhập
    $stmt = $pdo->query("SELECT * FROM import_bill ORDER BY id DESC");
    $importBills = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DS phiếu nhập kho - Admin</title>
    <link rel="stylesheet" href="assets/css/shared/layout.css">
    <link rel="stylesheet" href="assets/css/imports/index.css">
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
                        <li><a href="imports/create.php">Nhập hóa đơn</a></li>
                        <li class="active"><a href="imports/index.php">DS phiếu nhập kho</a></li>
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
                <h2>Danh Sách Phiếu Nhập Kho</h2>
                <!-- Nút Xuất Excel đã được xóa -->
            </div>
            
            <!-- Phần Tìm kiếm -->
            <div class="section">
                <div class="section-header">
                    <h3>Tìm kiếm hóa đơn nhập</h3>
                </div>
                <div class="section-content">
                    <form method="POST" class="search-form">
                        <div class="search-row">
                            <div class="search-group">
                                <label for="search">Tìm kiếm</label>
                                <input type="text" id="search" name="search" placeholder="Nhập số hóa đơn nhập" value="<?php echo htmlspecialchars($searchTerm); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">Tìm kiếm</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Phần Danh sách hóa đơn nhập -->
            <div class="section">
                <div class="section-header">
                    <h3>Danh sách hóa đơn nhập</h3>
                </div>
                <div class="section-content">
                    <?php if (empty($importBills)): ?>
                        <div class="no-data">
                            <?php if ($searchTerm !== ''): ?>
                                <p>Không tìm thấy hóa đơn nào với số "<?php echo htmlspecialchars($searchTerm); ?>"</p>
                            <?php else: ?>
                                <p>Chưa có hóa đơn nhập nào</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="import-bills-table">
                                <thead>
                                    <tr>
                                        <th class="center">Số Hóa Đơn</th>
                                        <th class="center">Serial</th>
                                        <th class="center-header">Nhà Cung Cấp</th>
                                        <th class="center-header">Nhập vào kho</th>
                                        <th class="center">Ngày Nhập</th>
                                        <th class="amount">Tổng Tiền (VNĐ)</th>
                                        <th class="center">Số Lượng Mặt hàng</th>
                                        <th class="center">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($importBills as $bill): ?>
                                        <tr>
                                            <td class="center"><?php echo htmlspecialchars($bill['so_hoa_don']); ?></td>
                                            <td class="center"><?php echo htmlspecialchars($bill['serial']); ?></td>
                                            <td><?php echo htmlspecialchars($bill['nha_cung_cap']); ?></td>
                                            <td><?php echo htmlspecialchars($bill['nhap_vao_don_vi']); ?></td>
                                            <td class="center"><?php echo date('d/m/Y', strtotime($bill['ngay_nhap'])); ?></td>
                                            <td class="amount"><?php echo htmlspecialchars(formatVnAmount($bill['tong_tien'] ?? 0)); ?></td>
                                            <td class="center"><?php echo $bill['so_luong_mat_hang']; ?></td>
                                            <td class="center">
                                                <a href="imports/details.php?id=<?php echo $bill['id']; ?>" class="btn btn-info btn-sm">
                                                    Xem chi tiết
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tự động focus vào ô tìm kiếm
        document.getElementById('search').focus();
    </script>
    <script src="assets/js/shared/sidebar-toggle.js" defer></script>
</body>
</html>