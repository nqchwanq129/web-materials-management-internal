<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/money_parse.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Thủ kho')) {
    header('Location: ../auth/sign-in.php');
    exit;
}

// Input
$searchTerm = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searchTerm = trim($_POST['search'] ?? '');
}

// Build query: tìm theo số hóa đơn xuất; nếu trống -> tất cả
$whereSql = '';
$params = [];
if ($searchTerm !== '') {
    $whereSql = 'WHERE so_hd_xuat LIKE ?';
    $params[] = '%' . $searchTerm . '%';
}

$sql = "SELECT id, nguoi_nhan, ngay_nhan, ten_kho_xuat, tong_tien, ly_do_xuat FROM export_bill $whereSql ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bills = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DS phiếu xuất kho - Admin</title>
    <link rel="stylesheet" href="assets/css/shared/layout.css">
    <link rel="stylesheet" href="assets/css/exports/index.css">
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
                <li>Nhập hàng hóa
                    <ul>
                        <li><a href="imports/create.php">Nhập hóa đơn</a></li>
                        <li><a href="imports/index.php">DS phiếu nhập kho</a></li>
                    </ul>
                </li>
                <li class="active">Xuất hàng hóa
                    <ul>
                        <li><a href="exports/create.php">Xuất hóa đơn</a></li>
                        <li class="active"><a href="exports/index.php">DS phiếu xuất kho</a></li>
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
                <h2>Danh Sách Hóa Đơn Xuất</h2>
            </div>

            <div class="section">
                <div class="section-header">
                    <h3>Tìm kiếm hóa đơn xuất</h3>
                </div>
                <div class="section-content">
                    <form method="POST" class="search-form">
                        <div class="search-row">
                            <div class="search-group">
                                <label for="search">Tìm kiếm</label>
                                <input type="text" id="search" name="search" placeholder="Nhập số hóa đơn xuất" value="<?php echo htmlspecialchars($searchTerm); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">Tìm kiếm</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="section">
                <div class="section-header">
                    <h3>Danh sách hóa đơn xuất</h3>
                </div>
                <div class="section-content">
                    <?php if (empty($bills)): ?>
                        <div class="no-data">
                            <?php if ($searchTerm !== ''): ?>
                                <p>Không có phiếu xuất với số "<?php echo htmlspecialchars($searchTerm); ?>"</p>
                            <?php else: ?>
                                <p>Không có phiếu xuất nào</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="import-bills-table">
                                <thead>
                                    <tr>
                                        <th class="center-header">Người nhận</th>
                                        <th class="center">Ngày nhận</th>
                                        <th class="center-header">Kho xuất</th>
                                        <th class="amount">Tổng Tiền (VNĐ)</th>
                                        <th class="center-header">Lý do xuất</th>
                                        <th class="center">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bills as $bill): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($bill['nguoi_nhan']); ?></td>
                                            <td class="center"><?php echo date('d/m/Y', strtotime($bill['ngay_nhan'])); ?></td>
                                            <td><?php echo htmlspecialchars($bill['ten_kho_xuat']); ?></td>
                                            <td class="amount"><?php echo htmlspecialchars(formatVnAmount($bill['tong_tien'] ?? 0)); ?></td>
                                            <td><?php echo htmlspecialchars($bill['ly_do_xuat'] ?? ''); ?></td>
                                            <td class="center">
                                                <a href="exports/details.php?id=<?php echo $bill['id']; ?>" class="btn btn-info btn-sm">Xem chi tiết</a>
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
    document.getElementById('search').focus();
</script>
<script src="assets/js/shared/sidebar-toggle.js" defer></script>
</body>
</html>