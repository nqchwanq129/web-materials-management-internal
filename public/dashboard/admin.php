<?php
chdir(dirname(__DIR__));
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header('Location: ../auth/sign-in.php');
    exit;
}
$name = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
require_once __DIR__ . '/../../app/config/database.php';

$stock = $pdo->query('SELECT COUNT(*) AS total,
    COALESCE(SUM(so_luong_con_lai >= 10), 0) AS available,
    COALESCE(SUM(so_luong_con_lai > 0 AND so_luong_con_lai < 10), 0) AS low,
    COALESCE(SUM(so_luong_con_lai <= 0), 0) AS empty FROM products')->fetch();
$recentProducts = $pdo->query('SELECT id, ten_san_pham, loai, don_vi, so_luong_con_lai
    FROM products ORDER BY ngay_nhap DESC, id DESC LIMIT 8')->fetchAll();
$today = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
$firstMonth = $today->modify('first day of this month')->modify('-6 months');
$endMonth = $today->modify('first day of next month');
$months = [];
for ($i = 0; $i < 7; $i++) {
    $month = $firstMonth->modify("+$i months");
    $months[$month->format('Y-m')] = ['label' => $month->format('m/Y'), 'imports' => 0, 'exports' => 0];
}
foreach (['imports' => ['import_bill', 'ngay_nhap'], 'exports' => ['export_bill', 'ngay_nhan']] as $kind => [$table, $dateColumn]) {
    $stmt = $pdo->prepare("SELECT DATE_FORMAT($dateColumn, '%Y-%m') AS month, COUNT(*) AS total
        FROM $table WHERE $dateColumn >= ? AND $dateColumn < ? GROUP BY month");
    $stmt->execute([$firstMonth->format('Y-m-d'), $endMonth->format('Y-m-d')]);
    foreach ($stmt as $row) {
        $months[$row['month']][$kind] = (int) $row['total'];
    }
}
$chartMax = max(1, max(array_column($months, 'imports')), max(array_column($months, 'exports')));
$availablePercent = $stock['total'] > 0 ? 100 * $stock['available'] / $stock['total'] : 0;
$lowEndPercent = $stock['total'] > 0 ? 100 * ($stock['available'] + $stock['low']) / $stock['total'] : 0;
$donutBackground = $stock['total'] > 0
    ? "conic-gradient(#60bc86 0 {$availablePercent}%, #f2b968 {$availablePercent}% {$lowEndPercent}%, #ee8792 {$lowEndPercent}% 100%)"
    : '#ecebf2';
$formatCount = static fn($value) => number_format((int) $value, 0, ',', '.');
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tổng quan | Quản lý vật tư VISHIPEL</title>
    <link rel="stylesheet" href="assets/css/dashboard/admin.css">
    <link rel="stylesheet" href="assets/css/shared/icons.css">
    <link rel="stylesheet" href="assets/css/shared/theme.css">
</head>
<body>
<a class="skip-link" href="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? basename(__DIR__) . '/' . basename(__FILE__), ENT_QUOTES, 'UTF-8') ?>#main-content">Đến nội dung chính</a>
<div class="dashboard">
    <aside class="sidebar" id="dashboard-sidebar">
        <a class="brand" href="dashboard/admin.php"><img src="assets/images/company-logo.png" alt="VISHIPEL" width="500" height="500"></a>
        <div class="nav-label">TỔNG QUAN</div>
        <nav class="nav" aria-label="Điều hướng chính">
            <a class="active" href="dashboard/admin.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#dashboard"></use></svg></span>Bảng điều khiển</a>
            <a href="reports/statistics.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#chart"></use></svg></span>Thống kê</a>
            <div class="nav-label">QUẢN LÝ KHO</div>
            <a href="products/index.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#package"></use></svg></span>Hàng hóa</a>
            <a href="imports/index.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#import"></use></svg></span>Phiếu nhập kho</a>
            <a href="imports/create.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#file-plus"></use></svg></span>Tạo phiếu nhập</a>
            <a href="exports/index.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#export"></use></svg></span>Phiếu xuất kho</a>
            <a href="exports/create.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#file-plus"></use></svg></span>Tạo phiếu xuất</a>
            <div class="nav-label">HỆ THỐNG</div>
            <a href="accounts/index.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#users"></use></svg></span>Tài khoản</a>
        </nav>
        <div class="sidebar-user"><span class="avatar">A</span><span><strong><?= $name ?></strong><small>Quản trị viên</small></span><a href="auth/log-out.php" title="Đăng xuất" aria-label="Đăng xuất"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#logout"></use></svg></a></div>
    </aside>
    <div class="content-shell">
        <header class="topbar">
            <div class="topbar-left"><button class="menu-toggle" type="button" aria-controls="dashboard-sidebar" aria-expanded="false" aria-label="Mở menu"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#menu"></use></svg></button><div><span class="breadcrumb">Tổng quan / Bảng điều khiển</span><h1>Xin chào, <?= $name ?> </h1></div></div>
            <div class="topbar-actions"><span><?= $today->format('d/m/Y') ?></span><span class="top-avatar">A</span></div>
        </header>
        <main class="main-content" id="main-content" tabindex="-1">
            <div class="page-intro"><div><h2>Bảng điều khiển</h2><p>Tổng quan hoạt động quản lý vật tư</p></div><span class="demo-badge">Dữ liệu thực tế</span></div>
            <section class="metrics" aria-label="Các chỉ số tổng quan">
                <article class="metric"><div class="metric-top"><span>Tổng hàng hóa</span><i class="blue"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#package"></use></svg></i></div><strong><?= $formatCount($stock['total']) ?></strong><small>Danh mục đang quản lý</small></article>
                <article class="metric"><div class="metric-top"><span>Tồn kho từ 10</span><i class="green"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#check"></use></svg></i></div><strong><?= $formatCount($stock['available']) ?></strong><small>Hàng hóa còn từ 10 đơn vị</small></article>
                <article class="metric"><div class="metric-top"><span>Sắp hết hàng</span><i class="orange"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#warning"></use></svg></i></div><strong><?= $formatCount($stock['low']) ?></strong><small>Tồn từ 1 đến 9 đơn vị</small></article>
                <article class="metric"><div class="metric-top"><span>Hết hàng</span><i class="red"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#error"></use></svg></i></div><strong><?= $formatCount($stock['empty']) ?></strong><small>Cần bổ sung</small></article>
            </section>
            <div class="visual-grid">
                <section class="panel activity-panel">
                    <div class="panel-heading"><div><h3>Hoạt động nhập xuất</h3><p>Số phiếu theo tháng trong 7 tháng gần đây</p></div><div class="legend"><span><i class="in"></i>Nhập</span><span><i class="out"></i>Xuất</span></div></div>
                    <div class="chart" role="img" aria-label="Số phiếu nhập xuất theo tháng; số liệu chi tiết ở bảng bên dưới">
                        <?php foreach ($months as $month): ?>
                        <div class="bar-group"><div><i style="height:<?= 100 * $month['imports'] / $chartMax ?>%" title="Nhập: <?= $month['imports'] ?> phiếu"></i><i style="height:<?= 100 * $month['exports'] / $chartMax ?>%" title="Xuất: <?= $month['exports'] ?> phiếu"></i></div><span><?= $month['label'] ?></span></div>
                        <?php endforeach; ?>
                    </div>
                    <details style="padding:0 20px 20px"><summary>Xem số phiếu từng tháng</summary><div class="table-scroll"><table style="min-width:0"><thead><tr><th>Tháng</th><th>Nhập</th><th>Xuất</th></tr></thead><tbody>
                        <?php foreach ($months as $month): ?>
                        <tr><td><?= $month['label'] ?></td><td><?= $formatCount($month['imports']) ?></td><td><?= $formatCount($month['exports']) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody></table></div></details>
                </section>
                <section class="panel status-panel">
                    <div class="panel-heading"><div><h3>Tình trạng tồn kho</h3><p>Phân bố hàng hóa hiện tại</p></div></div>
                    <div class="donut" style="background:<?= $escape($donutBackground) ?>" aria-hidden="true"><span><?= $formatCount($stock['total']) ?><small>hàng hóa</small></span></div>
                    <div class="status-list"><div><span><i class="green-dot"></i>Tồn từ 10</span><strong><?= $formatCount($stock['available']) ?></strong></div><div><span><i class="orange-dot"></i>Sắp hết (1–9)</span><strong><?= $formatCount($stock['low']) ?></strong></div><div><span><i class="red-dot"></i>Hết hàng</span><strong><?= $formatCount($stock['empty']) ?></strong></div></div>
                </section>
            </div>
            <section class="panel inventory-panel">
                <div class="panel-heading"><div><h3>Danh sách hàng hóa</h3><p>8 hàng hóa có ngày nhập mới nhất</p></div><a class="view-all" href="products/index.php">Xem tất cả <svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#arrow-right"></use></svg></a></div>
                <div class="table-scroll"><table><thead><tr><th>Tên hàng hóa</th><th>ID hàng</th><th>Loại</th><th>Đơn vị tính</th><th>Tồn kho</th><th>Trạng thái</th></tr></thead><tbody>
                    <?php foreach ($recentProducts as $product):
                        $quantity = (int) $product['so_luong_con_lai'];
                        $statusClass = $quantity <= 0 ? 'empty' : ($quantity < 10 ? 'low' : 'available');
                        $statusLabel = $quantity <= 0 ? 'Hết hàng' : ($quantity < 10 ? 'Sắp hết' : 'Còn hàng');
                    ?>
                    <tr><td><a href="products/details.php?id=<?= (int) $product['id'] ?>"><span class="product-icon"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#package"></use></svg></span><?= $escape($product['ten_san_pham']) ?></a></td><td>#<?= (int) $product['id'] ?></td><td><?= $escape($product['loai']) ?></td><td><?= $escape($product['don_vi']) ?></td><td><?= $formatCount($quantity) ?></td><td><span class="tag <?= $statusClass ?>"><?= $statusLabel ?></span></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$recentProducts): ?>
                    <tr><td colspan="6">Chưa có hàng hóa. Hãy tạo phiếu nhập để bổ sung dữ liệu.</td></tr>
                    <?php endif; ?>
                </tbody></table></div>
            </section>
        </main>
    </div>
</div>
<script>
const menu = document.querySelector('.menu-toggle');
menu.addEventListener('click', () => {
    const open = document.body.classList.toggle('menu-open');
    menu.setAttribute('aria-expanded', String(open));
    menu.setAttribute('aria-label', open ? 'Đóng menu' : 'Mở menu');
});
</script>
<script src="assets/js/shared/theme.js" defer></script>
</body>
</html>
