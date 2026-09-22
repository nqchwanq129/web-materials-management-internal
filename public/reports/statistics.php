<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Thủ kho')) {
    header('Location: ../auth/sign-in.php');
    exit;
}

// Các cột ngày trong schema có kiểu DATE.
$start_date = is_string($_GET['start_date'] ?? null) ? $_GET['start_date'] : '';
$end_date = is_string($_GET['end_date'] ?? null) ? $_GET['end_date'] : '';
$validDate = static function (string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    [$year, $month, $day] = array_map('intval', explode('-', $date));
    return checkdate($month, $day, $year);
};
$filterError = '';
if (($start_date === '') !== ($end_date === '')) {
    $filterError = 'Chọn cả hai ngày để áp dụng bộ lọc.';
} elseif ($start_date !== '' && (!$validDate($start_date) || !$validDate($end_date) || $start_date > $end_date)) {
    $filterError = 'Khoảng thời gian không hợp lệ. Vui lòng kiểm tra ngày bắt đầu và kết thúc.';
}
$has_date_filter = $filterError === '' && $start_date !== '';

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

// Đếm mỗi sản phẩm đúng một lần theo ngày nhập của sản phẩm.
if ($has_date_filter) {
    $stmt = $pdo->prepare("SELECT loai, COUNT(*) as count FROM products WHERE ngay_nhap BETWEEN ? AND ? GROUP BY loai");
    $stmt->execute([$start_date, $end_date]);
} else {
    $stmt = $pdo->query("SELECT loai, COUNT(*) as count FROM products GROUP BY loai");
}
$productStats = $stmt->fetchAll();

$totalProducts = 0;
$congCuCount = 0;
$vatTuCount = 0;
$taiSanCount = 0;
$phuTungCount = 0;
$khacCount = 0;

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
        case 'Khác':
            $khacCount = $stat['count'];
            break;
    }
}

// Lấy thống kê hóa đơn (trong khoảng thời gian được chọn)
if ($has_date_filter) {
    $stmt = $pdo->prepare("SELECT 
        (SELECT COUNT(*) FROM import_bill WHERE ngay_nhap BETWEEN ? AND ?) as import_count,
        (SELECT COUNT(*) FROM export_bill WHERE ngay_nhan BETWEEN ? AND ?) as export_count");
    $stmt->execute([$start_date, $end_date, $start_date, $end_date]);
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

// Tồn kho là ảnh chụp hiện tại, không thể lọc hồi tố bằng ngày nhập.
$remainingQuantity = (int) $pdo->query("SELECT COALESCE(SUM(so_luong_con_lai), 0) FROM products")->fetchColumn();

// Số lượng nhập và xuất là các luồng phát sinh trong kỳ.
if ($has_date_filter) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(ibd.so_luong_nhap), 0) FROM import_bill_details ibd JOIN import_bill ib ON ib.id = ibd.import_bill_id WHERE ib.ngay_nhap BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
} else {
    $stmt = $pdo->query("SELECT COALESCE(SUM(so_luong_nhap), 0) FROM import_bill_details");
}
$importedQuantity = (int) $stmt->fetchColumn();

if ($has_date_filter) {
    $stmt = $pdo->prepare("SELECT 
        SUM(ebd.so_luong_xuat) as exported_qty
    FROM export_bill_details ebd
    JOIN export_bill eb ON ebd.export_bill_id = eb.id
    WHERE eb.ngay_nhan BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
} else {
    $stmt = $pdo->query("SELECT 
        SUM(ebd.so_luong_xuat) as exported_qty
    FROM export_bill_details ebd");
    $stmt->execute();
}
$exportedQuantity = (int) $stmt->fetchColumn();
$totalQuantity = $importedQuantity + $exportedQuantity;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Số liệu thống kê | VISHIPEL</title>
    <link rel="stylesheet" href="assets/css/dashboard/admin.css">
    <link rel="stylesheet" href="assets/css/reports/statistics.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php
$name = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Người dùng', ENT_QUOTES, 'UTF-8');
$home = $_SESSION['role'] === 'Admin' ? 'dashboard/admin.php' : 'dashboard/warehouse.php';
$format = static fn($number) => number_format((float) $number, 0, ',', '.');
?>
<div class="dashboard">
    <aside class="sidebar" id="dashboard-sidebar">
        <a class="brand" href="<?= $home ?>"><span class="brand-mark">V</span><span>VISHIPEL</span></a>
        <div class="nav-label">TỔNG QUAN</div>
        <nav class="nav" aria-label="Điều hướng chính">
            <a href="<?= $home ?>"><span>▦</span>Bảng điều khiển</a>
            <a class="active" href="reports/statistics.php"><span>▥</span>Thống kê</a>
            <div class="nav-label">QUẢN LÝ KHO</div>
            <a href="products/index.php"><span>◫</span>Hàng hóa</a>
            <a href="imports/index.php"><span>↙</span>Phiếu nhập kho</a>
            <a href="imports/create.php"><span>＋</span>Tạo phiếu nhập</a>
            <a href="exports/index.php"><span>↗</span>Phiếu xuất kho</a>
            <a href="exports/create.php"><span>＋</span>Tạo phiếu xuất</a>
            <?php if ($_SESSION['role'] === 'Admin'): ?>
                <div class="nav-label">HỆ THỐNG</div>
                <a href="accounts/index.php"><span>♙</span>Tài khoản</a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-user"><span class="avatar">A</span><span><strong><?= $name ?></strong><small><?= $_SESSION['role'] === 'Admin' ? 'Quản trị viên' : 'Thủ kho' ?></small></span><a href="auth/log-out.php" title="Đăng xuất" aria-label="Đăng xuất">⇥</a></div>
    </aside>
    <div class="content-shell">
        <header class="topbar">
            <div class="topbar-left"><button class="menu-toggle" type="button" aria-controls="dashboard-sidebar" aria-expanded="false" aria-label="Mở menu">☰</button><div><span class="breadcrumb">Tổng quan / Thống kê</span><h1>Số liệu thống kê</h1></div></div>
            <div class="topbar-actions"><span><?= date('d/m/Y') ?></span><span class="top-avatar">A</span></div>
        </header>
        <main class="main-content">
            <div class="page-intro"><div><h2>Báo cáo tổng quan</h2><p>Theo dõi tài khoản, hàng hóa và hoạt động nhập xuất</p></div></div>
            <form class="filter-panel" method="get" action="reports/statistics.php">
                <div class="filter-title"><strong>Khoảng thời gian</strong><span>Áp dụng cho hàng hóa, phiếu nhập xuất và số lượng</span></div>
                <label>Từ ngày<input type="date" name="start_date" value="<?= htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Đến ngày<input type="date" name="end_date" value="<?= htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8') ?>"></label>
                <button type="submit">Áp dụng</button>
                <?php if ($start_date || $end_date): ?><a class="clear-filter" href="reports/statistics.php">Xóa lọc</a><?php endif; ?>
            </form>
            <?php if ($filterError): ?><p class="filter-note" role="alert"><?= htmlspecialchars($filterError, ENT_QUOTES, 'UTF-8') ?> Số liệu đang hiển thị cho toàn bộ thời gian.</p><?php endif; ?>
            <section class="metrics" aria-label="Số liệu tổng quan">
                <article class="metric"><div class="metric-top"><span>Tài khoản</span><i class="blue">♙</i></div><strong><?= $format($totalUsers) ?></strong><small>Toàn hệ thống</small></article>
                <article class="metric"><div class="metric-top"><span>Hàng hóa</span><i class="green">◫</i></div><strong><?= $format($totalProducts) ?></strong><small>Theo khoảng thời gian</small></article>
                <article class="metric"><div class="metric-top"><span>Phiếu nhập / xuất</span><i class="orange">↗</i></div><strong><?= $format($totalInvoices) ?></strong><small>Theo khoảng thời gian</small></article>
                <article class="metric"><div class="metric-top"><span>Tồn kho hiện tại</span><i class="red">▣</i></div><strong><?= $format($remainingQuantity) ?></strong><small>Không áp dụng lọc ngày</small></article>
            </section>
            <div class="report-grid">
                <section class="panel report-card"><div class="report-heading"><div><h3>Tài khoản theo vai trò</h3><p>Dữ liệu toàn hệ thống</p></div><strong><?= $format($totalUsers) ?></strong></div><div class="report-chart"><canvas id="userChart"></canvas></div><div class="report-list"><div><span><i style="background:#5c8bea"></i>Admin</span><strong><?= $format($adminCount) ?></strong></div><div><span><i style="background:#65bd91"></i>Thủ kho</span><strong><?= $format($thuKhoCount) ?></strong></div><div><span><i style="background:#f0b862"></i>Người nhận hàng</span><strong><?= $format($nguoiNhanCount) ?></strong></div></div></section>
                <section class="panel report-card"><div class="report-heading"><div><h3>Hàng hóa theo nhóm</h3><p>Đếm theo ngày nhập của từng hàng hóa</p></div><strong><?= $format($totalProducts) ?></strong></div><div class="report-chart"><canvas id="productChart"></canvas></div><div class="report-list"><div><span><i style="background:#5c8bea"></i>Công cụ dụng cụ</span><strong><?= $format($congCuCount) ?></strong></div><div><span><i style="background:#65bd91"></i>Vật tư</span><strong><?= $format($vatTuCount) ?></strong></div><div><span><i style="background:#f0b862"></i>Phụ tùng thay thế</span><strong><?= $format($phuTungCount) ?></strong></div><div><span><i style="background:#e88791"></i>Tài sản cố định</span><strong><?= $format($taiSanCount) ?></strong></div><div><span><i style="background:#a88bd2"></i>Khác</span><strong><?= $format($khacCount) ?></strong></div></div></section>
                <section class="panel report-card"><div class="report-heading"><div><h3>Phiếu nhập và xuất</h3><p>Hoạt động kho trong kỳ</p></div><strong><?= $format($totalInvoices) ?></strong></div><div class="report-chart"><canvas id="invoiceChart"></canvas></div><div class="report-list"><div><span><i style="background:#5c8bea"></i>Phiếu nhập</span><strong><?= $format($importInvoices) ?></strong></div><div><span><i style="background:#65bd91"></i>Phiếu xuất</span><strong><?= $format($exportInvoices) ?></strong></div></div></section>
                <section class="panel report-card"><div class="report-heading"><div><h3>Số lượng nhập xuất</h3><p>Tổng phát sinh theo khoảng thời gian</p></div><strong><?= $format($totalQuantity) ?></strong></div><div class="report-chart"><canvas id="quantityChart"></canvas></div><div class="report-list"><div><span><i style="background:#5c8bea"></i>Đã nhập</span><strong><?= $format($importedQuantity) ?></strong></div><div><span><i style="background:#65bd91"></i>Đã xuất</span><strong><?= $format($exportedQuantity) ?></strong></div></div></section>
            </div>
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
if (window.Chart) {
    const charts = [
        ['userChart', ['Admin', 'Thủ kho', 'Người nhận hàng'], <?= json_encode([(int)$adminCount, (int)$thuKhoCount, (int)$nguoiNhanCount]) ?>],
        ['productChart', ['Công cụ dụng cụ', 'Vật tư', 'Phụ tùng thay thế', 'Tài sản cố định', 'Khác'], <?= json_encode([(int)$congCuCount, (int)$vatTuCount, (int)$phuTungCount, (int)$taiSanCount, (int)$khacCount]) ?>],
        ['invoiceChart', ['Phiếu nhập', 'Phiếu xuất'], <?= json_encode([(int)$importInvoices, (int)$exportInvoices]) ?>],
        ['quantityChart', ['Đã nhập', 'Đã xuất'], <?= json_encode([(int)$importedQuantity, (int)$exportedQuantity]) ?>]
    ];
    charts.forEach(([id, labels, values]) => {
        new Chart(document.getElementById(id), {
            type: 'doughnut',
            data: {labels, datasets: [{data: values, backgroundColor: ['#5c8bea','#65bd91','#f0b862','#e88791','#a88bd2'], borderWidth: 0, hoverOffset: 4}]},
            options: {responsive: true, maintainAspectRatio: false, cutout: '72%', plugins: {legend: {display: false}}}
        });
    });
}
</script>
</body>
</html>
