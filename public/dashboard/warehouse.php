<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';

// Kiểm tra đăng nhập và role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Thủ kho') {
    header('Location: ../auth/sign-in.php');
    exit;
}

// Lấy thống kê cơ bản
$stmt = $pdo->query("SELECT COUNT(*) as total_products FROM products");
$total_products = $stmt->fetch()['total_products'];

$stmt = $pdo->query("SELECT SUM(so_luong_con_lai) as total_quantity FROM products");
$total_quantity = $stmt->fetch()['total_quantity'] ?: 0;

$stmt = $pdo->query("SELECT COUNT(*) as low_stock FROM products WHERE so_luong_con_lai < 10");
$low_stock = $stmt->fetch()['low_stock'];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thủ kho - Phần mềm Quản Lý Kho</title>
    <link rel="stylesheet" href="assets/css/shared/layout.css">
               <link rel="stylesheet" href="assets/css/dashboard/warehouse.css">
</head>
<body>
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
                    </ul>
                </li>
            </ul>
        </div>

        <div class="main-content">
            <div class="welcome-card">
                <div class="role-badge">THỦ KHO</div>
                <h2>Chào mừng Thủ kho!</h2>
                <p>Bạn đã đăng nhập với quyền quản lý kho hàng.</p>
            </div>
        </div>
    </div>
</body>
</html>