<?php
chdir(dirname(__DIR__));
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'Thủ kho'], true)) {
    header('Location: ../auth/sign-in.php');
    exit;
}
require_once __DIR__ . '/../../app/config/database.php';

function productEsc($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
$types = [
    'cong-cu' => ['name' => 'Công cụ dụng cụ', 'icon' => '<svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#tools"></use></svg>', 'tone' => 'blue'],
    'vat-tu' => ['name' => 'Vật tư', 'icon' => '<svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#package"></use></svg>', 'tone' => 'green'],
    'tai-san' => ['name' => 'Tài sản cố định', 'icon' => '<svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#building"></use></svg>', 'tone' => 'purple'],
    'phu-tung' => ['name' => 'Phụ tùng thay thế', 'icon' => '<svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#settings"></use></svg>', 'tone' => 'orange'],
    'khac' => ['name' => 'Khác', 'icon' => '<svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#dashboard"></use></svg>', 'tone' => 'gray'],
];
$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
if (!isset($types[$type])) $type = '';
$startDate = is_string($_GET['start_date'] ?? null) ? $_GET['start_date'] : '';
$endDate = is_string($_GET['end_date'] ?? null) ? $_GET['end_date'] : '';
$validDate = static function (string $date): bool {
    if ($date === '') return true;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
    [$year, $month, $day] = array_map('intval', explode('-', $date));
    return checkdate($month, $day, $year);
};
$filterError = '';
if (!$validDate($startDate) || !$validDate($endDate) || ($startDate !== '' && $endDate !== '' && $startDate > $endDate)) {
    $filterError = 'Khoảng ngày không hợp lệ. Bộ lọc ngày chưa được áp dụng.';
    $startDate = $endDate = '';
}

$typeCounts = array_fill_keys(array_keys($types), 0);
$countRows = $pdo->query('SELECT loai, COUNT(*) AS total FROM products GROUP BY loai')->fetchAll();
$allCount = 0;
foreach ($countRows as $row) {
    $allCount += (int) $row['total'];
    foreach ($types as $key => $meta) {
        if ($row['loai'] === $meta['name']) $typeCounts[$key] = (int) $row['total'];
    }
}

// Tổng nhập/xuất được tính riêng theo product_id để tránh nhân bản dòng khi có nhiều phiếu.
$sql = "SELECT p.id, p.ten_san_pham, p.loai, p.don_vi, p.ngay_nhap, p.so_luong_con_lai, p.serial, p.ghi_chu,
    COALESCE(imp.total_qty, 0) AS tong_nhap,
    COALESCE(exp.total_qty, 0) AS tong_xuat,
    inv.invoice_numbers AS so_hoa_don_list
FROM products p
LEFT JOIN (SELECT product_id, SUM(so_luong_nhap) AS total_qty FROM import_bill_details GROUP BY product_id) imp ON imp.product_id = p.id
LEFT JOIN (SELECT product_id, SUM(so_luong_xuat) AS total_qty FROM export_bill_details GROUP BY product_id) exp ON exp.product_id = p.id
LEFT JOIN (
    SELECT ibd.product_id, GROUP_CONCAT(DISTINCT ib.so_hoa_don ORDER BY ib.so_hoa_don SEPARATOR ', ') AS invoice_numbers
    FROM import_bill_details ibd JOIN import_bill ib ON ib.id = ibd.import_bill_id
    GROUP BY ibd.product_id
) inv ON inv.product_id = p.id
WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= ' AND (p.ten_san_pham LIKE ? OR p.serial LIKE ? OR inv.invoice_numbers LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term);
}
if ($type !== '') {
    $sql .= ' AND p.loai = ?';
    $params[] = $types[$type]['name'];
}
if ($startDate !== '') { $sql .= ' AND p.ngay_nhap >= ?'; $params[] = $startDate; }
if ($endDate !== '') { $sql .= ' AND p.ngay_nhap <= ?'; $params[] = $endDate; }
$sql .= ' ORDER BY p.ngay_nhap DESC, p.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$quantityRemaining = array_sum(array_map(static fn($p) => (int) $p['so_luong_con_lai'], $products));
$totalFiltered = count($products);
$pageSize = 10;
$pageCount = max(1, (int) ceil($totalFiltered / $pageSize));
$page = filter_var($_GET['page'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$page = min($page, $pageCount);
$products = array_slice($products, ($page - 1) * $pageSize, $pageSize);
$firstRow = $totalFiltered ? ($page - 1) * $pageSize + 1 : 0;
$lastRow = min($page * $pageSize, $totalFiltered);
$name = productEsc($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Người dùng');
$home = $_SESSION['role'] === 'Admin' ? 'dashboard/admin.php' : 'dashboard/warehouse.php';
$categoryUrl = static function (string $category) use ($search, $startDate, $endDate): string {
    $query = array_filter(['type' => $category, 'search' => $search, 'start_date' => $startDate, 'end_date' => $endDate], static fn($value) => $value !== '');
    return 'products/index.php' . ($query ? '?' . http_build_query($query) : '');
};
$exportQuery = http_build_query(array_filter(['start_date' => $startDate, 'end_date' => $endDate], static fn($value) => $value !== ''));
$pageUrl = static function (int $target) use ($type, $search, $startDate, $endDate): string {
    $query = array_filter(['type' => $type, 'search' => $search, 'start_date' => $startDate, 'end_date' => $endDate, 'page' => $target], static fn($value) => $value !== '');
    return 'products/index.php?' . http_build_query($query) . '#goods-table';
};
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hàng hóa | VISHIPEL</title>
    <link rel="stylesheet" href="assets/css/dashboard/admin.css">
    <link rel="stylesheet" href="assets/css/products/index.css">
    <link rel="stylesheet" href="assets/css/shared/icons.css">
    <link rel="stylesheet" href="assets/css/shared/theme.css">
</head>
<body>
<a class="skip-link" href="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? basename(__DIR__) . '/' . basename(__FILE__), ENT_QUOTES, 'UTF-8') ?>#main-content">Đến nội dung chính</a>
<div class="dashboard">
    <aside class="sidebar" id="dashboard-sidebar">
        <a class="brand" href="<?= $home ?>"><img src="assets/images/company-logo.png" alt="VISHIPEL" width="500" height="500"></a>
        <div class="nav-label">TỔNG QUAN</div>
        <nav class="nav" aria-label="Điều hướng chính">
            <a href="<?= $home ?>"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#dashboard"></use></svg></span>Bảng điều khiển</a>
            <a href="reports/statistics.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#chart"></use></svg></span>Thống kê</a>
            <div class="nav-label">QUẢN LÝ KHO</div>
            <a class="active" href="products/index.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#package"></use></svg></span>Hàng hóa</a>
            <a href="imports/index.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#import"></use></svg></span>Phiếu nhập kho</a>
            <a href="imports/create.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#file-plus"></use></svg></span>Tạo phiếu nhập</a>
            <a href="exports/index.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#export"></use></svg></span>Phiếu xuất kho</a>
            <a href="exports/create.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#file-plus"></use></svg></span>Tạo phiếu xuất</a>
            <?php if ($_SESSION['role'] === 'Admin'): ?><div class="nav-label">HỆ THỐNG</div><a href="accounts/index.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#users"></use></svg></span>Tài khoản</a><?php endif; ?>
        </nav>
        <div class="sidebar-user"><span class="avatar"><?= productEsc(mb_substr($_SESSION['full_name'] ?? 'N', 0, 1)) ?></span><span><strong><?= $name ?></strong><small><?= productEsc($_SESSION['role']) ?></small></span><a href="auth/log-out.php" title="Đăng xuất" aria-label="Đăng xuất"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#logout"></use></svg></a></div>
    </aside>
    <div class="content-shell">
        <header class="topbar"><div class="topbar-left"><button class="menu-toggle" type="button" aria-controls="dashboard-sidebar" aria-expanded="false" aria-label="Mở menu"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#menu"></use></svg></button><div><span class="breadcrumb">Quản lý kho / Hàng hóa</span><h1>Tất cả hàng hóa</h1></div></div><div class="topbar-actions"><span><?= date('d/m/Y') ?></span><span class="top-avatar"><?= productEsc(mb_substr($_SESSION['full_name'] ?? 'N', 0, 1)) ?></span></div></header>
        <main class="main-content" id="main-content" tabindex="-1">
            <div class="page-intro"><div><h2>Danh sách hàng hóa</h2><p>Tra cứu hàng hóa, theo dõi nhập xuất và số lượng còn lại</p></div><span class="result-pill"><?= number_format($allCount, 0, ',', '.') ?> hàng hóa</span></div>
            <nav class="category-grid" aria-label="Phân loại hàng hóa">
                <a class="category-card all <?= $type === '' ? 'selected' : '' ?>" href="<?= productEsc($categoryUrl('')) ?>" <?= $type === '' ? 'aria-current="page"' : '' ?>><span class="category-icon blue"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#package"></use></svg></span><span class="category-copy"><strong>Tất cả hàng hóa</strong><small>Toàn bộ danh mục</small></span><b><?= number_format($allCount, 0, ',', '.') ?></b></a>
                <?php foreach ($types as $key => $meta): ?>
                    <a class="category-card <?= $type === $key ? 'selected' : '' ?>" href="<?= productEsc($categoryUrl($key)) ?>" <?= $type === $key ? 'aria-current="page"' : '' ?>><span class="category-icon <?= $meta['tone'] ?>"><?= $meta['icon'] ?></span><span class="category-copy"><strong><?= productEsc($meta['name']) ?></strong><small>Nhóm hàng hóa</small></span><b><?= number_format($typeCounts[$key], 0, ',', '.') ?></b></a>
                <?php endforeach; ?>
            </nav>
            <section class="panel filter-panel" aria-label="Bộ lọc hàng hóa">
                <form method="get" action="products/index.php">
                    <?php if ($type !== ''): ?><input type="hidden" name="type" value="<?= productEsc($type) ?>"><?php endif; ?>
                    <label class="search-field">Tìm hàng hóa<input type="search" name="search" value="<?= productEsc($search) ?>" placeholder="Tên hàng, serial hoặc số hóa đơn"></label>
                    <label>Từ ngày<input type="date" name="start_date" value="<?= productEsc($startDate) ?>"></label>
                    <label>Đến ngày<input type="date" name="end_date" value="<?= productEsc($endDate) ?>"></label>
                    <button type="submit">Áp dụng</button>
                    <?php if ($search !== '' || $startDate !== '' || $endDate !== ''): ?><a class="clear-filter" href="products/index.php<?= $type !== '' ? '?type=' . rawurlencode($type) : '' ?>">Xóa lọc</a><?php endif; ?>
                </form>
            </section>
            <?php if ($filterError): ?><p class="filter-error" role="alert"><?= productEsc($filterError) ?></p><?php endif; ?>
            <section class="panel inventory-panel">
                <div class="panel-heading"><div><h3><?= $type ? productEsc($types[$type]['name']) : 'Tất cả hàng hóa' ?></h3><p><?= number_format($totalFiltered, 0, ',', '.') ?> hàng hóa phù hợp · Tồn kho <?= number_format($quantityRemaining, 0, ',', '.') ?> đơn vị</p></div><div class="export-actions">
                    <?php if ($type === 'cong-cu'): ?><a href="reports/tools-excel.php<?= $exportQuery ? '?' . productEsc($exportQuery) : '' ?>">Xuất Excel</a><?php elseif ($type === 'tai-san'): ?><a href="reports/fixed-assets-excel.php<?= $exportQuery ? '?' . productEsc($exportQuery) : '' ?>">Xuất Excel</a><?php elseif ($type === 'vat-tu' || $type === 'phu-tung'): ?><button type="button" id="export-months" data-export-type="<?= productEsc($type) ?>" title="Báo cáo Excel theo tháng; ngày bắt đầu và kết thúc sẽ được quy về tháng">Xuất Excel theo tháng</button><?php endif; ?>
                </div></div>
                <div class="table-scroll"><table id="goods-table"><thead><tr><th>Hàng hóa</th><th>Loại</th><th>Ngày nhập</th><th>Hóa đơn nhập</th><th>SL nhập</th><th>SL xuất</th><th>Tồn kho</th><th>Thao tác</th></tr></thead><tbody>
                    <?php foreach ($products as $p): ?><tr>
                        <td><span class="product-name" title="<?= productEsc($p['ten_san_pham']) ?>" tabindex="0"><?= productEsc($p['ten_san_pham']) ?></span><small class="product-meta" title="<?= productEsc($p['serial'] ?: $p['don_vi']) ?>"><?= productEsc($p['don_vi']) ?><?= $p['serial'] ? ' · Serial: ' . productEsc($p['serial']) : '' ?></small></td>
                        <td><span class="type-tag"><?= productEsc($p['loai']) ?></span></td>
                        <td data-order="<?= productEsc($p['ngay_nhap']) ?>"><?= date('d/m/Y', strtotime($p['ngay_nhap'])) ?></td>
                        <td class="invoice-cell" title="<?= productEsc($p['so_hoa_don_list'] ?: '') ?>"><?= productEsc($p['so_hoa_don_list'] ?: '—') ?></td>
                        <td class="number-cell"><?= number_format((int) $p['tong_nhap'], 0, ',', '.') ?></td>
                        <td class="number-cell"><?= number_format((int) $p['tong_xuat'], 0, ',', '.') ?></td>
                        <td class="number-cell"><span class="stock-tag <?= (int) $p['so_luong_con_lai'] > 0 ? 'available' : 'empty' ?>"><?= number_format((int) $p['so_luong_con_lai'], 0, ',', '.') ?></span></td>
                        <td><div class="row-actions"><a href="products/details.php?id=<?= (int) $p['id'] ?>">Chi tiết</a><a href="products/edit.php?id=<?= (int) $p['id'] ?>">Sửa</a></div></td>
                    </tr><?php endforeach; ?>
                    <?php if (!$products): ?><tr><td colspan="8" class="empty-products">Không tìm thấy hàng hóa phù hợp.</td></tr><?php endif; ?>
                </tbody></table></div>
                <nav class="table-pagination" aria-label="Phân trang hàng hóa">
                    <span>Hiển thị <?= $firstRow ?>–<?= $lastRow ?> trong <?= number_format($totalFiltered, 0, ',', '.') ?> hàng hóa · Tối đa 10 dòng/trang</span>
                    <?php if ($pageCount > 1): ?><div class="page-links">
                        <?php if ($page > 1): ?><a href="<?= productEsc($pageUrl($page - 1)) ?>" aria-label="Trang trước">‹ Trước</a><?php endif; ?>
                        <?php for ($number = max(1, $page - 2); $number <= min($pageCount, $page + 2); $number++): ?>
                            <a href="<?= productEsc($pageUrl($number)) ?>" <?= $number === $page ? 'aria-current="page"' : '' ?>><?= $number ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $pageCount): ?><a href="<?= productEsc($pageUrl($page + 1)) ?>" aria-label="Trang tiếp">Tiếp ›</a><?php endif; ?>
                    </div><?php endif; ?>
                </nav>
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
document.getElementById('export-months')?.addEventListener('click', (event) => {
    const type = event.currentTarget.dataset.exportType;
    const start = document.querySelector('[name="start_date"]').value;
    const end = document.querySelector('[name="end_date"]').value;
    const toDate = end ? new Date(end + 'T00:00:00') : new Date();
    const fromDate = start ? new Date(start + 'T00:00:00') : new Date(toDate.getFullYear(), toDate.getMonth(), 1);
    const form = document.createElement('form');
    form.method = 'post';
    form.action = type === 'vat-tu' ? 'reports/materials-excel.php' : 'reports/replacement-parts-excel.php';
    const values = {from_month: fromDate.getMonth() + 1, from_year: fromDate.getFullYear(), to_month: toDate.getMonth() + 1, to_year: toDate.getFullYear()};
    Object.entries(values).forEach(([key, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = key; input.value = value;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
});
</script>
<script src="assets/js/shared/theme.js" defer></script>
</body>
</html>
