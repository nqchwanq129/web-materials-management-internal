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

$stmt = $pdo->query("SELECT COUNT(*) as low_stock FROM products WHERE so_luong_con_lai > 0 AND so_luong_con_lai < 10");
$low_stock = $stmt->fetch()['low_stock'];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thủ kho - Phần mềm Quản Lý Kho</title>
    <link rel="stylesheet" href="assets/css/dashboard/admin.css">

    <link rel="stylesheet" href="assets/css/shared/icons.css">
    <link rel="stylesheet" href="assets/css/shared/theme.css">
</head>
<body class="migrated-page">
<a class="skip-link" href="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? basename(__DIR__) . '/' . basename(__FILE__), ENT_QUOTES, 'UTF-8') ?>#main-content">Đến nội dung chính</a>
<?php $shellTitle = 'Bảng điều khiển'; $shellActive = 'dashboard/warehouse.php'; require __DIR__ . '/../../app/views/shell-start.php'; ?>

            <section class="warehouse-intro"><div><h2>Công việc kho hôm nay</h2><p>Theo dõi tồn kho, tiếp nhận hàng hóa và lập phiếu xuất từ một nơi.</p></div><a class="btn" href="imports/create.php">Tạo phiếu nhập</a></section>
            <section class="metrics warehouse-metrics" aria-label="Tổng quan kho">
                <article class="metric"><div class="metric-top"><span>Hàng hóa</span></div><strong><?= number_format((int) $total_products, 0, ',', '.') ?></strong><small>Danh mục đang quản lý</small></article>
                <article class="metric"><div class="metric-top"><span>Sắp hết hàng</span></div><strong><?= number_format((int) $low_stock, 0, ',', '.') ?></strong><small>Tồn từ 1 đến 9 đơn vị</small></article>
            </section>
            <section class="warehouse-shortcuts" aria-label="Thao tác nhanh">
                <a href="products/index.php"><svg class="ui-icon" aria-hidden="true"><use href="assets/icons.svg#package"></use></svg><strong>Tra cứu hàng hóa</strong><p>Xem số lượng tồn, hình ảnh và lịch sử nhập xuất.</p></a>
                <a href="imports/index.php"><svg class="ui-icon" aria-hidden="true"><use href="assets/icons.svg#import"></use></svg><strong>Phiếu nhập kho</strong><p>Kiểm tra và quản lý các đợt tiếp nhận hàng.</p></a>
                <a href="exports/create.php"><svg class="ui-icon" aria-hidden="true"><use href="assets/icons.svg#export"></use></svg><strong>Tạo phiếu xuất</strong><p>Chọn hàng hóa, số lượng và người nhận.</p></a>
            </section>

        </div>
    </div>
</div>
<script src="assets/js/shared/theme.js" defer></script>
</body>
</html>