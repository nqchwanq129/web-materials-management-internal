<?php
chdir(dirname(__DIR__));
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin','Thủ kho'], true)) {
    header('Location: ../auth/sign-in.php');
    exit;
}

require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/csrf_helper.php';
require_once __DIR__ . '/../../app/helpers/money_parse.php';
require_once __DIR__ . '/../../app/helpers/export_create.php';
function importEsc($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8'); }
function exportOld(string $key, string $default = ''): string { return is_string($_POST[$key] ?? null) ? $_POST[$key] : $default; }
$message = ''; $message_type = 'error';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf_token'] ?? null) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Phiên xác nhận hết hạn. Vui lòng kiểm tra thông tin và gửi lại.';
    } else {
        try {
            $billId = createExportBill($pdo, $_POST);
            header('Location: details.php?id=' . $billId); exit;
        } catch (Throwable $e) {
            error_log('Create export: ' . $e->getMessage());
            $message = $e instanceof DomainException ? $e->getMessage() : 'Không thể lưu phiếu xuất. Vui lòng thử lại.';
        }
    }
}
$stmt = $pdo->query("SELECT p.*, (SELECT GROUP_CONCAT(DISTINCT ib.so_hoa_don SEPARATOR ', ') FROM import_bill_details d JOIN import_bill ib ON ib.id=d.import_bill_id WHERE d.product_id=p.id) AS so_hoa_don FROM products p WHERE p.so_luong_con_lai > 0 ORDER BY p.ngay_nhap DESC, p.ten_san_pham ASC");
$products = $stmt->fetchAll();
$home = $_SESSION['role'] === 'Admin' ? 'dashboard/admin.php' : 'dashboard/warehouse.php';
$name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Người dùng';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Xuất Hàng Hóa - Admin</title>
	<link rel="stylesheet" href="assets/css/dashboard/admin.css">
	<link rel="stylesheet" href="assets/css/exports/create.css">
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
            <a href="exports/index.php"><span aria-hidden="true">↗</span>Phiếu xuất kho</a>
            <a class="active" aria-current="page" href="exports/create.php"><span aria-hidden="true">＋</span>Tạo phiếu xuất</a>
            <?php if ($_SESSION['role'] === 'Admin'): ?><div class="nav-label">HỆ THỐNG</div><a href="accounts/index.php"><span aria-hidden="true">♙</span>Tài khoản</a><?php endif; ?>
        </nav>
        <div class="sidebar-user"><span class="avatar" aria-hidden="true">V</span><span><strong><?= importEsc($name) ?></strong><small><?= importEsc($_SESSION['role']) ?></small></span><a href="auth/log-out.php" aria-label="Đăng xuất" title="Đăng xuất">⇥</a></div>
    </aside>
    <div class="content-shell">
        <header class="topbar"><div class="topbar-left"><button class="menu-toggle" type="button" aria-controls="dashboard-sidebar" aria-expanded="false" aria-label="Mở menu">☰</button><div><span class="breadcrumb">Quản lý kho / Xuất hàng hóa</span><h1>Xuất hàng hóa</h1></div></div><div class="topbar-actions"><span><?= date('d/m/Y') ?></span><span class="top-avatar" aria-hidden="true">V</span></div></header>
<main class="main-content"><div class="page-intro"><div><h2>Xuất hàng hóa</h2><p>Chọn hàng trong kho, nhập số lượng và kiểm tra phiếu trước khi xuất.</p></div><a class="btn secondary" href="exports/index.php">← Danh sách phiếu xuất</a></div>
			<?php if ($message): ?>
				<div role="alert" class="message <?php echo $message_type; ?>">
					<?php echo htmlspecialchars($message); ?>
				</div>
			<?php endif; ?>

			<form class="invoice-form" method="POST"><?= csrfTokenField() ?>
				<!-- Thông tin xuất hàng -->
				<div class="section">
					<div class="section-header">
						<h3>Thông tin xuất hàng</h3>
					</div>
					<div class="section-content">
						<div class="form-row">
							<div class="form-column">
								<div class="form-group">
									<label for="warehouse">Kho xuất:</label>
									<select id="warehouse" name="warehouse" required>
										<option value="Đài TTXLTTH Hà Nội - 34 ngõ 60 Dương Khuê">Đài TTXLTTH Hà Nội - 34 ngõ 60 Dương Khuê</option>
									</select>
								</div>
								<div class="form-group">
									<label for="receiver">Người nhận:</label>
									<select id="receiver" name="receiver" required onchange="handleReceiverChange()">
										<option value="Vishipel - Cục Hàng Hải Việt Nam" <?= exportOld('receiver') === 'Vishipel - Cục Hàng Hải Việt Nam' ? 'selected' : '' ?>>Vishipel - Cục Hàng Hải Việt Nam</option>
										<option value="Nguyễn Văn Dũng" <?= exportOld('receiver') === 'Nguyễn Văn Dũng' ? 'selected' : '' ?>>Nguyễn Văn Dũng</option>
										<option value="Nguyễn Thị Hảo" <?= exportOld('receiver') === 'Nguyễn Thị Hảo' ? 'selected' : '' ?>>Nguyễn Thị Hảo</option>
										<option value="Dương Mạnh Tuấn" <?= exportOld('receiver') === 'Dương Mạnh Tuấn' ? 'selected' : '' ?>>Dương Mạnh Tuấn</option>
										<option value="custom" <?= exportOld('receiver') === 'custom' ? 'selected' : '' ?>>Khác...</option>
									</select>
									<input type="text" id="receiver-custom" name="receiver_custom" placeholder="Nhập tên người nhận" value="<?= importEsc(exportOld('receiver_custom')) ?>">
								</div>
							</div>
							<div class="form-column">
								<div class="form-group">
									<label for="export-date">Ngày xuất:</label>
									<input type="date" id="export-date" name="export_date" class="date-picker" placeholder="dd/mm/yyyy" value="<?= importEsc(exportOld('export_date', date('Y-m-d'))) ?>" required>
								</div>
                        <div class="form-group">
									<label for="reason">Lý do xuất:</label>
									<input type="text" id="reason" name="reason" placeholder="Nhập lý do xuất hàng" value="<?= importEsc(exportOld('reason', 'Phục vụ sản xuất kinh doanh')) ?>">
								</div>
                        <div class="form-group">
                            <label for="invoice-number">Số HĐ xuất:</label>
                            <input type="text" id="invoice-number" name="invoice_number" placeholder="Nhập số hóa đơn xuất" value="<?= importEsc(exportOld('invoice_number')) ?>" maxlength="100" required>
                        </div>
							</div>
						</div>
					</div>
				</div>

				<div class="export-workspace"><div class="stock-column">
<!-- Danh sách hàng hóa -->
				<div class="section">
					<div class="section-header">
						<h3>Chọn hàng hóa xuất kho</h3><p>Tích chọn mặt hàng và nhập số lượng cần xuất. Tồn kho được kiểm tra lại khi lưu.</p>
					</div>
					<div class="section-content">
						<div class="goods-tools"><div><label for="goods-search">Tìm hàng hóa</label><input type="search" id="goods-search" placeholder="Tên hàng, nhóm hàng hoặc số hóa đơn nhập…"></div><label class="selected-filter"><input type="checkbox" id="only-selected"> Chỉ xem hàng đã chọn</label></div>
<div id="filter-status" role="status"></div>
<?php if (!$products): ?><div class="empty-state">Kho chưa có hàng hóa còn tồn để xuất.</div><?php endif; ?>
<div class="table-container" tabindex="0" role="region" aria-label="Hàng hóa còn tồn">
							<table class="goods-table">
                                <thead>
                                    <tr>
                                        <th class="center">Chọn</th>
                                        <th class="center-header">Hàng hóa</th>
										<th class="center">Nhóm hàng</th>
										<th class="center">Đơn vị tính</th>
                                        <th class="center">Ngày nhập</th>
                                        <th class="center">Số HĐ</th>
										<th class="amount">Đơn giá (VNĐ)</th>
										<th class="center">SL còn lại</th>
										<th class="center">SL xuất</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($products as $p): ?>
										<tr data-name="<?= importEsc($p['ten_san_pham']) ?>" data-unit="<?= importEsc($p['don_vi']) ?>" data-price="<?= importEsc($p['don_gia']) ?>">
                                            <td data-label="Chọn xuất" class="center">
                                                <input type="checkbox" name="selected[<?php echo $p['id']; ?>]" value="1" <?= isset($_POST['selected'][$p['id']]) ? 'checked' : '' ?> aria-label="<?= importEsc('Chọn ' . $p['ten_san_pham']) ?>">
                                            </td>
											<td data-label="Hàng hóa"><?php echo htmlspecialchars($p['ten_san_pham']); ?></td>
											<td data-label="Nhóm hàng" class="center"><?php echo htmlspecialchars($p['loai']); ?></td>
											<td data-label="Đơn vị tính" class="center"><?php echo htmlspecialchars($p['don_vi']); ?></td>
                                            <td data-label="Ngày nhập" class="center"><?php echo date('d/m/Y', strtotime($p['ngay_nhap'])); ?></td>
                                            <td data-label="Số hóa đơn nhập" class="center"><?php echo htmlspecialchars($p['so_hoa_don'] ?? ''); ?></td>
											<td data-label="Đơn giá (VNĐ)" class="amount"><?php echo importEsc(formatVnAmount($p['don_gia'])); ?></td>
											<td data-label="Còn trong kho" class="center"><?php echo (int)$p['so_luong_con_lai']; ?></td>
											<td data-label="Số lượng xuất" class="center">
												<input type="number" name="quantities[<?php echo $p['id']; ?>]" class="qty-input" min="1" max="<?php echo $p['so_luong_con_lai']; ?>" step="1" value="<?= importEsc(is_scalar($_POST['quantities'][$p['id']] ?? null) ? $_POST['quantities'][$p['id']] : 1) ?>" aria-label="<?= importEsc('Số lượng xuất ' . $p['ten_san_pham']) ?>">
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>

				</div><aside class="dispatch-review" aria-labelledby="review-title"><div class="review-heading"><span aria-hidden="true">↗</span><div><h3 id="review-title">Phiếu xuất của bạn</h3><p>Kiểm tra hàng đã chọn trước khi xuất.</p></div></div><div id="selected-items"><p class="selection-empty">Chưa chọn hàng hóa. Tích chọn mặt hàng trong danh sách để thêm vào phiếu.</p></div><noscript><p>Bật JavaScript để xem tổng kết tự động. Bạn vẫn có thể chọn hàng và gửi phiếu.</p></noscript><div class="form-actions"><div class="export-summary"><span>Đã chọn <strong id="selected-count">0</strong> mặt hàng</span><span>Tổng tiền dự kiến <strong id="selected-total">0</strong> VNĐ</span></div>
					<button type="submit" class="btn btn-primary">Xuất hàng hóa</button>
				</div>
<p class="review-footnote">Tồn kho sẽ được cập nhật sau khi xuất thành công.</p></aside></div>
			</form>
		</main>
	</div></div>

<script src="assets/js/exports/create.js" defer></script>
</body></html>
