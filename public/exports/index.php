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
$sorts = ['newest' => 'id DESC', 'date_desc' => 'ngay_nhan DESC, id DESC', 'date_asc' => 'ngay_nhan ASC, id ASC', 'amount_desc' => 'tong_tien DESC, id DESC', 'amount_asc' => 'tong_tien ASC, id DESC', 'number' => 'so_hd_xuat ASC, id DESC'];
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
    $error = 'Khoảng ngày không hợp lệ. Vui lòng kiểm tra lại ngày xuất.';
}
$where = []; $params = []; $bills = []; $total = 0; $amount = 0; $suppliers = 0; $pages = 1;
if ($search !== '') {
    $where[] = '(so_hd_xuat LIKE ? OR ly_do_xuat LIKE ? OR nguoi_nhan LIKE ? OR ten_kho_xuat LIKE ?)';
    $literalSearch = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
    $where[0] = str_replace('LIKE ?', "LIKE ? ESCAPE '!'", $where[0]);
    $params = array_fill(0, 4, '%' . $literalSearch . '%');
}
if ($start !== '') { $where[] = 'ngay_nhan >= ?'; $params[] = $start; }
if ($end !== '') { $where[] = 'ngay_nhan <= ?'; $params[] = $end; }
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
if ($error === '') {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(tong_tien), 0) AS amount, COUNT(DISTINCT NULLIF(TRIM(nguoi_nhan), \'\')) AS suppliers FROM export_bill' . $sqlWhere);
        $stmt->execute($params);
        $summary = $stmt->fetch();
        $total = (int) $summary['total']; $amount = $summary['amount']; $suppliers = (int) $summary['suppliers'];
        $pages = max(1, (int) ceil($total / $size)); $page = min($page, $pages);
        $offset = ($page - 1) * $size;
        $stmt = $pdo->prepare('SELECT id, so_hd_xuat, nguoi_nhan, ten_kho_xuat, ngay_nhan, tong_tien, ly_do_xuat FROM export_bill' . $sqlWhere . ' ORDER BY ' . $sorts[$sort] . ' LIMIT ' . $size . ' OFFSET ' . $offset);
        $stmt->execute($params); $bills = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Export list: ' . $e->getMessage());
        $error = 'Không thể tải danh sách phiếu xuất. Vui lòng thử lại sau.';
    }
}
function importPageUrl(int $page): string {
    global $search, $start, $end, $sort, $size;
    return 'exports/index.php?' . http_build_query(['search' => $search, 'start' => $start, 'end' => $end, 'sort' => $sort, 'size' => $size, 'page' => $page]);
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
    <title>Danh sách hóa đơn xuất | VISHIPEL</title>
    <link rel="stylesheet" href="assets/css/dashboard/admin.css">
    <link rel="stylesheet" href="assets/css/exports/index.css">
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
            <a href="imports/index.php"><span aria-hidden="true">↙</span>Phiếu nhập kho</a>
            <a href="imports/create.php"><span aria-hidden="true">＋</span>Tạo phiếu nhập</a>
            <a class="active" aria-current="page" href="exports/index.php"><span aria-hidden="true">↗</span>Phiếu xuất kho</a>
            <a href="exports/create.php"><span aria-hidden="true">＋</span>Tạo phiếu xuất</a>
            <?php if ($_SESSION['role'] === 'Admin'): ?><div class="nav-label">HỆ THỐNG</div><a href="accounts/index.php"><span aria-hidden="true">♙</span>Tài khoản</a><?php endif; ?>
        </nav>
        <div class="sidebar-user"><span class="avatar" aria-hidden="true">V</span><span><strong><?= importEsc($name) ?></strong><small><?= importEsc($_SESSION['role']) ?></small></span><a href="auth/log-out.php" aria-label="Đăng xuất" title="Đăng xuất">⇥</a></div>
    </aside>
    <div class="content-shell">
        <header class="topbar"><div class="topbar-left"><button class="menu-toggle" type="button" aria-controls="dashboard-sidebar" aria-expanded="false" aria-label="Mở menu">☰</button><div><span class="breadcrumb">Quản lý kho / Phiếu xuất kho</span><h1>Phiếu xuất kho</h1></div></div><div class="topbar-actions"><span><?= date('d/m/Y') ?></span><span class="top-avatar" aria-hidden="true">V</span></div></header>
        <main class="main-content">
            <div class="page-intro"><div><h2>Danh sách hóa đơn xuất</h2><p>Tra cứu hóa đơn và theo dõi hàng hóa xuất khỏi kho.</p></div><a class="import-btn primary" href="exports/create.php">＋ Tạo phiếu xuất</a></div>
            <?php if ($error === ''): ?>
            <section class="metrics import-metrics" aria-label="Thống kê theo bộ lọc hiện tại">
                <article class="metric"><div class="metric-top"><span>Phiếu xuất kho</span><i class="blue" aria-hidden="true">↗</i></div><strong><?= number_format($total, 0, ',', '.') ?></strong><small>Theo bộ lọc hiện tại</small></article>
                <article class="metric"><div class="metric-top"><span>Tổng giá trị xuất (VNĐ)</span><i class="green" aria-hidden="true">₫</i></div><strong><?= importEsc(formatVnAmount($amount)) ?></strong><small>Theo tổng tiền đã lưu trên phiếu</small></article>
                <article class="metric"><div class="metric-top"><span>Người nhận</span><i class="orange" aria-hidden="true">▣</i></div><strong><?= number_format($suppliers, 0, ',', '.') ?></strong><small>Theo tên trên các phiếu phù hợp</small></article>
            </section>
            <?php endif; ?>
            <section class="panel list-panel unified-list" aria-labelledby="list-title">
                <div class="panel-heading"><div><h3 id="list-title">Tra cứu hóa đơn xuất</h3><p>Tìm nhanh theo số hóa đơn, người nhận, lý do hoặc kho xuất.</p></div><span class="result-badge"><?= $error === '' ? number_format($total, 0, ',', '.') . ' phiếu' : 'Chưa có kết quả' ?></span></div>
                <form method="get" action="exports/index.php" class="import-filters">
                    <div class="search-row"><div class="search-field"><label for="search">Bạn muốn tìm phiếu nào?</label><input type="search" id="search" name="search" placeholder="Số hóa đơn, người nhận, lý do hoặc kho…" value="<?= importEsc($search) ?>"></div><button class="import-btn primary" type="submit">Tìm phiếu</button></div>
                    <details class="advanced-filters" <?= $start !== '' || $end !== '' || $sort !== 'newest' || $size !== 10 ? 'open' : '' ?>><summary>Bộ lọc & sắp xếp <span>Ngày xuất · Thứ tự · Số dòng</span></summary><div class="advanced-grid">
                    <div><label for="start">Từ ngày</label><input type="date" id="start" name="start" value="<?= importEsc($start) ?>"></div>
                    <div><label for="end">Đến ngày</label><input type="date" id="end" name="end" value="<?= importEsc($end) ?>"></div>
                    <div><label for="sort">Sắp xếp</label><select id="sort" name="sort"><?php foreach (['newest' => 'Mới tạo trước', 'date_desc' => 'Ngày xuất mới nhất', 'date_asc' => 'Ngày xuất cũ nhất', 'amount_desc' => 'Giá trị cao nhất', 'amount_asc' => 'Giá trị thấp nhất', 'number' => 'Số hóa đơn A → Z'] as $key => $label): ?><option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
                    <div><label for="size">Số dòng / trang</label><select id="size" name="size"><?php foreach ([10,25,50] as $n): ?><option <?= $size === $n ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></div>
                    <div class="filter-actions"><button class="import-btn primary" type="submit">Áp dụng bộ lọc</button><a class="import-btn secondary" href="exports/index.php">Đặt lại</a></div>
                    </div></details>
                    <?php if ($search !== '' || $start !== '' || $end !== ''): ?><div class="active-filters" aria-label="Bộ lọc đang áp dụng"><span>Đang lọc:</span><?php if ($search !== ''): ?><span class="filter-chip">Từ khóa: <?= importEsc($search) ?></span><?php endif; ?><?php if ($start !== ''): ?><span class="filter-chip">Từ <?= importEsc($start) ?></span><?php endif; ?><?php if ($end !== ''): ?><span class="filter-chip">Đến <?= importEsc($end) ?></span><?php endif; ?><a href="exports/index.php">Xóa bộ lọc</a></div><?php endif; ?>
                </form><div class="table-caption"><span>Danh sách hóa đơn</span><span>Đơn vị tiền: VNĐ</span></div>
                <?php if ($error !== ''): ?><div class="import-error" role="alert"><?= importEsc($error) ?></div>
                <?php elseif (!$bills): ?><div class="empty-state"><span aria-hidden="true">▤</span><h3><?= $where ? 'Không tìm thấy phiếu xuất phù hợp' : 'Chưa có phiếu xuất kho' ?></h3><p><?= $where ? 'Thử thay đổi từ khóa hoặc khoảng ngày để tìm lại.' : 'Tạo phiếu xuất đầu tiên để theo dõi hàng hóa xuất khỏi kho.' ?></p><a class="import-btn secondary" href="<?= $where ? 'exports/index.php' : 'exports/create.php' ?>"><?= $where ? 'Xóa bộ lọc' : 'Tạo phiếu xuất' ?></a></div>
                <?php else: ?>
                <div class="import-table-wrap" tabindex="0" role="region" aria-label="Bảng phiếu xuất, có thể cuộn ngang">
                    <table class="import-table"><thead><tr><th scope="col">Hóa đơn / Ngày xuất</th><th scope="col">Người nhận / Lý do xuất</th><th scope="col">Kho xuất</th><th scope="col" class="numeric">Tổng tiền (VNĐ)</th><th scope="col">Thao tác</th></tr></thead><tbody>
                    <?php foreach ($bills as $bill): ?>
                    <tr>
                        <td class="invoice-cell" data-label="Hóa đơn"><a class="invoice-number" href="exports/details.php?id=<?= (int) $bill['id'] ?>"><?= importEsc($bill['so_hd_xuat'] ?: 'Phiếu #' . $bill['id']) ?></a><small class="invoice-date">Ngày xuất: <?= $validDate($bill['ngay_nhan']) ? date('d/m/Y', strtotime($bill['ngay_nhan'])) : '—' ?></small></td>
                        <td class="recipient-cell" data-label="Người nhận"><div><strong><?= importEsc($bill['nguoi_nhan']) ?></strong><small>Lý do: <?= importEsc($bill['ly_do_xuat'] ?: 'Chưa ghi lý do') ?></small></div></td>
                        <td data-label="Kho xuất"><span class="warehouse-name"><?= importEsc($bill['ten_kho_xuat']) ?></span></td>
                        <td class="numeric amount" data-label="Tổng tiền (VNĐ)"><?= importEsc(formatVnAmount($bill['tong_tien'])) ?></td>
                        <td class="action-cell"><a class="detail-link" href="exports/details.php?id=<?= (int) $bill['id'] ?>" aria-label="<?= importEsc('Xem chi tiết phiếu ' . ($bill['so_hd_xuat'] ?: $bill['id'])) ?>">Xem phiếu →</a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </div>
                <div class="list-footer"><span>Hiển thị <?= ($page - 1) * $size + 1 ?>–<?= min($page * $size, $total) ?> / <?= $total ?> phiếu</span><nav class="pagination" aria-label="Phân trang phiếu xuất">
                    <?php if ($page > 1): ?><a href="<?= importEsc(importPageUrl($page - 1)) ?>" aria-label="Trang trước">← Trước</a><?php endif; ?>
                    <?php $last = 0; foreach (array_unique([1, max(1, $page - 1), $page, min($pages, $page + 1), $pages]) as $p): ?><?php if ($p > $last + 1): ?><span>…</span><?php endif; ?><a href="<?= importEsc(importPageUrl($p)) ?>" <?= $p === $page ? 'aria-current="page"' : '' ?> aria-label="Trang <?= $p ?>"><?= $p ?></a><?php $last = $p; endforeach; ?>
                    <?php if ($page < $pages): ?><a href="<?= importEsc(importPageUrl($page + 1)) ?>" aria-label="Trang sau">Sau →</a><?php endif; ?>
                </nav></div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>
<script src="assets/js/exports/index.js" defer></script>
</body>
</html>
