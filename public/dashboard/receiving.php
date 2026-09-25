<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';

// Kiểm tra đăng nhập và role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Người nhận hàng') {
    header('Location: ../auth/sign-in.php');
    exit;
}

// Lấy danh sách hàng đã xuất
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT 
            eb.id as export_bill_id,
            eb.nguoi_nhan,
            eb.ngay_nhan,
            eb.ten_kho_xuat,
            eb.ly_do_xuat,
            ebd.product_id,
            ebd.so_luong_xuat,
            ebd.don_gia,
            ebd.thanh_tien,
            p.ten_san_pham,
            p.loai,
            p.don_vi
        FROM export_bill eb
        LEFT JOIN export_bill_details ebd ON eb.id = ebd.export_bill_id
        LEFT JOIN products p ON ebd.product_id = p.id
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND p.ten_san_pham LIKE ?";
    $params[] = "%$search%";
}

$sql .= " ORDER BY eb.ngay_nhan DESC, eb.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$exported_goods = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Người Nhận Hàng - Phần mềm Quản Lý Kho</title>
    <link rel="stylesheet" href="assets/css/dashboard/admin.css">
               <link rel="stylesheet" href="assets/css/dashboard/receiving.css">
    <link rel="stylesheet" href="assets/css/shared/icons.css">
    <link rel="stylesheet" href="assets/css/shared/theme.css">
</head>
<body class="migrated-page">
<a class="skip-link" href="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? basename(__DIR__) . '/' . basename(__FILE__), ENT_QUOTES, 'UTF-8') ?>#main-content">Đến nội dung chính</a>
<?php $shellTitle = 'Hàng đã nhận'; $shellActive = 'dashboard/receiving.php'; require __DIR__ . '/../../app/views/shell-start.php'; ?>

            <h2>Danh Sách Hàng Nhận</h2>

            <!-- Tìm kiếm hàng hóa -->
            <div class="section">
                <div class="section-header">
                    <h3>Tìm kiếm hàng hóa</h3>
                </div>
                <div class="section-content">
                    <form method="GET" class="search-form">
                        <div class="search-row">
                            <div class="search-column">
                                <div class="form-group">
                                    <label for="search">Tên hàng hóa:</label>
                                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nhập tên hàng hóa">
                                </div>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Tìm kiếm</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Danh sách hàng hóa -->
            <div class="section">
                <div class="section-header">
                    <h3>Danh sách hàng hóa đã xuất</h3>
                    <span class="goods-count">Tổng cộng: <?php echo count($exported_goods); ?> mặt hàng</span>
                </div>
                <div class="section-content">
                    <?php if (count($exported_goods) > 0): ?>
                        <div class="table-container">
                            <table class="goods-table">
                                <thead>
                                    <tr>
                                        <th>Mặt hàng</th>
                                        <th>Đơn vị tính</th>
                                        <th>Ngày nhận</th>
                                        <th>Số lượng</th>
                                        <th>Đơn giá</th>
                                        <th>Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exported_goods as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="product-info">
                                                    <strong><?php echo htmlspecialchars($item['ten_san_pham']); ?></strong>
                                                    <small class="product-type"><?php echo htmlspecialchars($item['loai']); ?></small>
                                                </div>
                                            </td>
                                            <td class="center"><?php echo htmlspecialchars($item['don_vi']); ?></td>
                                            <td class="center"><?php echo date('d/m/Y', strtotime($item['ngay_nhan'])); ?></td>
                                            <td class="center"><?php echo number_format($item['so_luong_xuat']); ?></td>
                                            <td class="amount"><?php echo number_format($item['don_gia'], 0, ',', '.'); ?> VNĐ</td>
                                            <td class="amount"><?php echo number_format($item['thanh_tien'], 0, ',', '.'); ?> VNĐ</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="no-data">Không tìm thấy hàng hóa nào phù hợp</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="assets/js/shared/theme.js" defer></script>
</body>
</html>
