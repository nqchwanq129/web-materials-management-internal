<?php
chdir(dirname(__DIR__));
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'Thủ kho'], true)) {
    header('Location: ../auth/sign-in.php');
    exit;
}
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/money_parse.php';
function importEsc($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8'); }
function importParam(string $key): string { return is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : ''; }
$search = importParam('search');
$start = importParam('start');
$end = importParam('end');
$sorts = ['newest' => 'id DESC', 'date_desc' => 'ngay_nhap DESC, id DESC', 'date_asc' => 'ngay_nhap ASC, id ASC', 'amount_desc' => 'tong_tien DESC, id DESC', 'amount_asc' => 'tong_tien ASC, id DESC', 'number' => 'so_hoa_don ASC, id DESC'];
$sort = array_key_exists(importParam('sort'), $sorts) ? importParam('sort') : 'newest';
$size = in_array(importParam('size'), ['10', '25', '50'], true) ? (int) importParam('size') : 10;
$page = max(1, (int) importParam('page'));
$error = '';
$validDate = static function (string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
    [$y, $m, $d] = array_map('intval', explode('-', $date));
    return checkdate($m, $d, $y);
};
if (($start !== '' && !$validDate($start)) || ($end !== '' && !$validDate($end)) || ($start !== '' && $end !== '' && $start > $end)) {
    $error = 'Khoảng ngày không hợp lệ. Vui lòng kiểm tra lại ngày nhập.';
}
$where = []; $params = []; $bills = []; $total = 0; $amount = 0; $suppliers = 0; $pages = 1;
if ($search !== '') {
    $where[] = '(so_hoa_don LIKE ? OR serial LIKE ? OR nha_cung_cap LIKE ? OR nhap_vao_don_vi LIKE ?)';
    $literalSearch = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
    $where[0] = str_replace('LIKE ?', "LIKE ? ESCAPE '!'", $where[0]);
    $params = array_fill(0, 4, '%' . $literalSearch . '%');
}
if ($start !== '') { $where[] = 'ngay_nhap >= ?'; $params[] = $start; }
if ($end !== '') { $where[] = 'ngay_nhap <= ?'; $params[] = $end; }
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
if ($error === '') {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(tong_tien), 0) AS amount, COUNT(DISTINCT NULLIF(TRIM(nha_cung_cap), \'\')) AS suppliers FROM import_bill' . $sqlWhere);
        $stmt->execute($params);
        $summary = $stmt->fetch();
        $total = (int) $summary['total']; $amount = $summary['amount']; $suppliers = (int) $summary['suppliers'];
        $pages = max(1, (int) ceil($total / $size)); $page = min($page, $pages);
        $offset = ($page - 1) * $size;
        $stmt = $pdo->prepare('SELECT id, so_hoa_don, serial, nha_cung_cap, nhap_vao_don_vi, ngay_nhap, tong_tien, so_luong_mat_hang FROM import_bill' . $sqlWhere . ' ORDER BY ' . $sorts[$sort] . ' LIMIT ' . $size . ' OFFSET ' . $offset);
        $stmt->execute($params); $bills = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Import list: ' . $e->getMessage());
        $error = 'Không thể tải danh sách phiếu nhập. Vui lòng thử lại sau.';
    }
}
function importPageUrl(int $page): string {
    global $search, $start, $end, $sort, $size;
    return 'imports/index.php?' . http_build_query(['search' => $search, 'start' => $start, 'end' => $end, 'sort' => $sort, 'size' => $size, 'page' => $page]);
}
$home = $_SESSION['role'] === 'Admin' ? 'dashboard/admin.php' : 'dashboard/warehouse.php';
$name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Người dùng';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Danh sách phiếu nhập kho | VISHIPEL</title>
    <link rel="stylesheet" href="assets/css/dashboard/admin.css">
    <link rel="stylesheet" href="assets/css/imports/index.css">
</head>
<body>
<div class="dashboard">
    <aside class="sidebar" id="dashboard-sidebar">
        <a class="brand" href="<?= $home ?>"><span class="brand-mark">V</span><span>VISHIPEL</span></a>
        <div class="nav-label">TỔNG QUAN</div>
        <nav class="nav" aria-label="Điều hướng chính">
            <a href="<?= $home ?>"><span aria-hidden="true">▦</span>Bảng điều khiển</a>
            <a href="reports/statistics.php"><span aria-hidden="true">▥</span>Thống kê</a>
            <div class="nav-label">QUẢN LÝ KHO</div>
            <a href="products/index.php"><span aria-hidden="true">◫</span>Hàng hóa</a>
            <a class="active" aria-current="page" href="imports/index.php"><span aria-hidden="true">↙</span>Phiếu nhập kho</a>
            <a href="imports/create.php"><span aria-hidden="true">＋</span>Tạo phiếu nhập</a>
            <a href="exports/index.php"><span aria-hidden="true">↗</span>Phiếu xuất kho</a>
            <a href="exports/create.php"><span aria-hidden="true">＋</span>Tạo phiếu xuất</a>
            <?php if ($_SESSION['role'] === 'Admin'): ?><div class="nav-label">HỆ THỐNG</div><a href="accounts/index.php"><span aria-hidden="true">♙</span>Tài khoản</a><?php endif; ?>
        </nav>
        <div class="sidebar-user"><span class="avatar" aria-hidden="true">V</span><span><strong><?= importEsc($name) ?></strong><small><?= importEsc($_SESSION['role']) ?></small></span><a href="auth/log-out.php" aria-label="Đăng xuất" title="Đăng xuất">⇥</a></div>
    </aside>
    <div class="content-shell">
        <header class="topbar"><div class="topbar-left"><button class="menu-toggle" type="button" aria-controls="dashboard-sidebar" aria-expanded="false" aria-label="Mở menu">☰</button><div><span class="breadcrumb">Quản lý kho / Phiếu nhập kho</span><h1>Phiếu nhập kho</h1></div></div><div class="topbar-actions"><span><?= date('d/m/Y') ?></span><span class="top-avatar" aria-hidden="true">V</span></div></header>
        <main class="main-content">
            <div class="page-intro"><div><h2>Danh sách phiếu nhập kho</h2><p>Tra cứu hóa đơn và theo dõi hàng hóa nhập vào kho.</p></div><a class="import-btn primary" href="imports/create.php">＋ Tạo phiếu nhập</a></div>
            <?php if ($error === ''): ?>
            <section class="metrics import-metrics" aria-label="Thống kê theo bộ lọc hiện tại">
                <article class="metric"><div class="metric-top"><span>Phiếu nhập kho</span><i class="blue" aria-hidden="true">↙</i></div><strong><?= number_format($total, 0, ',', '.') ?></strong><small>Theo bộ lọc hiện tại</small></article>
                <article class="metric"><div class="metric-top"><span>Tổng giá trị nhập (VNĐ)</span><i class="green" aria-hidden="true">₫</i></div><strong><?= importEsc(formatVnAmount($amount)) ?></strong><small>Theo tổng tiền đã lưu trên phiếu</small></article>
                <article class="metric"><div class="metric-top"><span>Nhà cung cấp</span><i class="orange" aria-hidden="true">▣</i></div><strong><?= number_format($suppliers, 0, ',', '.') ?></strong><small>Theo tên trên các phiếu phù hợp</small></article>
            </section>
            <?php endif; ?>
            <section class="panel filter-panel" aria-labelledby="filter-title">
                <div class="panel-heading"><div><h3 id="filter-title">Tra cứu phiếu nhập</h3><p>Tìm theo số hóa đơn, serial, nhà cung cấp hoặc kho nhập.</p></div></div>
                <form method="get" action="imports/index.php" class="import-filters">
                    <div class="search-row"><div class="search-field"><label for="search">Bạn muốn tìm phiếu nào?</label><input type="search" id="search" name="search" placeholder="Số hóa đơn, nhà cung cấp, serial hoặc kho…" value="<?= importEsc($search) ?>"></div><button class="import-btn primary" type="submit">Tìm phiếu</button></div>
                    <details class="advanced-filters" <?= $start !== '' || $end !== '' || $sort !== 'newest' || $size !== 10 ? 'open' : '' ?>><summary>Bộ lọc & sắp xếp <span>Ngày nhập · Thứ tự · Số dòng</span></summary><div class="advanced-grid">
                    <div><label for="start">Từ ngày</label><input type="date" id="start" name="start" value="<?= importEsc($start) ?>"></div>
                    <div><label for="end">Đến ngày</label><input type="date" id="end" name="end" value="<?= importEsc($end) ?>"></div>
                    <div><label for="sort">Sắp xếp</label><select id="sort" name="sort"><?php foreach (['newest' => 'Mới tạo trước', 'date_desc' => 'Ngày nhập mới nhất', 'date_asc' => 'Ngày nhập cũ nhất', 'amount_desc' => 'Giá trị cao nhất', 'amount_asc' => 'Giá trị thấp nhất', 'number' => 'Số hóa đơn A → Z'] as $key => $label): ?><option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
                    <div><label for="size">Số dòng / trang</label><select id="size" name="size"><?php foreach ([10,25,50] as $n): ?><option <?= $size === $n ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></div>
                    <div class="filter-actions"><button class="import-btn primary" type="submit">Áp dụng bộ lọc</button><a class="import-btn secondary" href="imports/index.php">Đặt lại</a></div>
                    </div></details>
                    <?php if ($search !== '' || $start !== '' || $end !== ''): ?><div class="active-filters" aria-label="Bộ lọc đang áp dụng"><span>Đang lọc:</span><?php if ($search !== ''): ?><span class="filter-chip">Từ khóa: <?= importEsc($search) ?></span><?php endif; ?><?php if ($start !== ''): ?><span class="filter-chip">Từ <?= importEsc($start) ?></span><?php endif; ?><?php if ($end !== ''): ?><span class="filter-chip">Đến <?= importEsc($end) ?></span><?php endif; ?><a href="imports/index.php">Xóa bộ lọc</a></div><?php endif; ?>
                </form>
            </section>
            <section class="panel list-panel" aria-labelledby="list-title">
                <div class="panel-heading"><div><h3 id="list-title">Danh sách phiếu nhập</h3><p><?= $error === '' ? number_format($total, 0, ',', '.') . ' phiếu phù hợp' : 'Chưa thể hiển thị kết quả' ?></p></div><span class="unit-note">Đơn vị tiền: VNĐ</span></div>
                <?php if ($error !== ''): ?><div class="import-error" role="alert"><?= importEsc($error) ?></div>
                <?php elseif (!$bills): ?><div class="empty-state"><span aria-hidden="true">▤</span><h3><?= $where ? 'Không tìm thấy phiếu nhập phù hợp' : 'Chưa có phiếu nhập kho' ?></h3><p><?= $where ? 'Thử thay đổi từ khóa hoặc khoảng ngày để tìm lại.' : 'Tạo phiếu nhập đầu tiên để theo dõi hàng hóa vào kho.' ?></p><a class="import-btn secondary" href="<?= $where ? 'imports/index.php' : 'imports/create.php' ?>"><?= $where ? 'Xóa bộ lọc' : 'Tạo phiếu nhập' ?></a></div>
                <?php else: ?>
                <div class="import-table-wrap" tabindex="0" role="region" aria-label="Bảng phiếu nhập, có thể cuộn ngang">
                    <table class="import-table"><thead><tr><th scope="col">Số hóa đơn / Serial</th><th scope="col">Nhà cung cấp</th><th scope="col">Nhập vào kho</th><th scope="col">Ngày nhập</th><th scope="col" class="numeric">Tổng tiền (VNĐ)</th><th scope="col" class="numeric">Số mặt hàng</th><th scope="col">Thao tác</th></tr></thead><tbody>
                    <?php foreach ($bills as $bill): ?>
                    <tr><td class="invoice-cell" data-label="Số hóa đơn"><a class="invoice-number" href="imports/details.php?id=<?= (int) $bill['id'] ?>"><?= importEsc($bill['so_hoa_don']) ?></a><small><?= importEsc($bill['serial'] ?: 'Chưa có serial') ?></small></td><td class="supplier" data-label="Nhà cung cấp"><?= importEsc($bill['nha_cung_cap']) ?></td><td data-label="Kho nhập"><?= importEsc($bill['nhap_vao_don_vi']) ?></td><td class="nowrap" data-label="Ngày nhập"><?= $validDate($bill['ngay_nhap']) ? date('d/m/Y', strtotime($bill['ngay_nhap'])) : '—' ?></td><td class="numeric amount" data-label="Tổng tiền (VNĐ)"><?= importEsc(formatVnAmount($bill['tong_tien'])) ?></td><td class="numeric" data-label="Số mặt hàng"><?= (int) $bill['so_luong_mat_hang'] ?></td><td class="action-cell"><a class="detail-link" href="imports/details.php?id=<?= (int) $bill['id'] ?>" aria-label="<?= importEsc('Xem chi tiết phiếu ' . $bill['so_hoa_don']) ?>">Chi tiết →</a></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </div>
                <div class="list-footer"><span>Hiển thị <?= ($page - 1) * $size + 1 ?>–<?= min($page * $size, $total) ?> / <?= $total ?> phiếu</span><nav class="pagination" aria-label="Phân trang phiếu nhập">
                    <?php if ($page > 1): ?><a href="<?= importEsc(importPageUrl($page - 1)) ?>" aria-label="Trang trước">← Trước</a><?php endif; ?>
                    <?php $last = 0; foreach (array_unique([1, max(1, $page - 1), $page, min($pages, $page + 1), $pages]) as $p): ?><?php if ($p > $last + 1): ?><span>…</span><?php endif; ?><a href="<?= importEsc(importPageUrl($p)) ?>" <?= $p === $page ? 'aria-current="page"' : '' ?> aria-label="Trang <?= $p ?>"><?= $p ?></a><?php $last = $p; endforeach; ?>
                    <?php if ($page < $pages): ?><a href="<?= importEsc(importPageUrl($page + 1)) ?>" aria-label="Trang sau">Sau →</a><?php endif; ?>
                </nav></div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>
<script src="assets/js/imports/index.js" defer></script>
</body>
</html>
