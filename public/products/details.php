<?php
chdir(dirname(__DIR__));
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/sign-in.php');
    exit;
}
require_once __DIR__ . '/../../app/config/database.php';
$canManage = in_array($_SESSION['role'] ?? '', ['Admin', 'Thủ kho'], true);
$home = $_SESSION['role'] === 'Admin' ? 'dashboard/admin.php' : ($_SESSION['role'] === 'Thủ kho' ? 'dashboard/warehouse.php' : 'dashboard/receiving.php');
$back = $canManage ? 'products/index.php' : $home;

function detailEsc($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
$productId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$productId) {
    header('Location: ../' . $back);
    exit;
}
$stmt = $pdo->prepare('SELECT id, ten_san_pham, loai, don_vi, ngay_nhap, so_luong_nhap, so_luong_con_lai, serial, ghi_chu, image_path FROM products WHERE id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();
if (!$product) {
    http_response_code(404);
    $notFound = true;
} else {
    $notFound = false;
    $stmt = $pdo->prepare('SELECT file_path, alt_text FROM product_images WHERE product_id = ? ORDER BY position ASC, id ASC');
    $stmt->execute([$productId]);
    $images = $stmt->fetchAll();
    if (!$images && !empty($product['image_path'])) {
        $images = [['file_path' => $product['image_path'], 'alt_text' => $product['ten_san_pham']]];
    }

    $stmt = $pdo->prepare('SELECT ib.id, ib.so_hoa_don, ib.ngay_nhap, SUM(ibd.so_luong_nhap) AS quantity
        FROM import_bill_details ibd JOIN import_bill ib ON ib.id = ibd.import_bill_id
        WHERE ibd.product_id = ? GROUP BY ib.id, ib.so_hoa_don, ib.ngay_nhap ORDER BY ib.ngay_nhap DESC, ib.id DESC');
    $stmt->execute([$productId]);
    $importBills = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT eb.id, eb.so_hd_xuat, eb.ngay_nhan, SUM(ebd.so_luong_xuat) AS quantity
        FROM export_bill_details ebd JOIN export_bill eb ON eb.id = ebd.export_bill_id
        WHERE ebd.product_id = ? GROUP BY eb.id, eb.so_hd_xuat, eb.ngay_nhan ORDER BY eb.ngay_nhan DESC, eb.id DESC');
    $stmt->execute([$productId]);
    $exportBills = $stmt->fetchAll();

    $totalImport = array_sum(array_map(static fn($row) => (int) $row['quantity'], $importBills));
    $totalExport = array_sum(array_map(static fn($row) => (int) $row['quantity'], $exportBills));
}
$name = detailEsc($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Người dùng');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $notFound ? 'Không tìm thấy hàng hóa' : detailEsc($product['ten_san_pham']) ?> | VISHIPEL</title>
    <link rel="stylesheet" href="assets/css/dashboard/admin.css">
    <link rel="stylesheet" href="assets/css/products/details.css">
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
            <?php if ($canManage): ?><a href="reports/statistics.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#chart"></use></svg></span>Thống kê</a><?php endif; ?>
            <?php if ($canManage): ?>
            <div class="nav-label">QUẢN LÝ KHO</div>
            <a class="active" href="products/index.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#package"></use></svg></span>Hàng hóa</a>
            <a href="imports/index.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#import"></use></svg></span>Phiếu nhập kho</a>
            <a href="imports/create.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#file-plus"></use></svg></span>Tạo phiếu nhập</a>
            <a href="exports/index.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#export"></use></svg></span>Phiếu xuất kho</a>
            <a href="exports/create.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#file-plus"></use></svg></span>Tạo phiếu xuất</a>
            <?php if ($_SESSION['role'] === 'Admin'): ?><div class="nav-label">HỆ THỐNG</div><a href="accounts/index.php"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#users"></use></svg></span>Tài khoản</a><?php endif; ?>
            <?php endif; ?>
        </nav>
        <div class="sidebar-user"><span class="avatar"><?= detailEsc(mb_substr($_SESSION['full_name'] ?? 'N', 0, 1)) ?></span><span><strong><?= $name ?></strong><small><?= detailEsc($_SESSION['role']) ?></small></span><a href="auth/log-out.php" title="Đăng xuất" aria-label="Đăng xuất"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#logout"></use></svg></a></div>
    </aside>
    <div class="content-shell">
        <header class="topbar"><div class="topbar-left"><button class="menu-toggle" type="button" aria-controls="dashboard-sidebar" aria-expanded="false" aria-label="Mở menu"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#menu"></use></svg></button><div><span class="breadcrumb">Quản lý kho / Hàng hóa / Chi tiết</span><h1>Chi tiết hàng hóa</h1></div></div><div class="topbar-actions"><span><?= date('d/m/Y') ?></span><span class="top-avatar"><?= detailEsc(mb_substr($_SESSION['full_name'] ?? 'N', 0, 1)) ?></span></div></header>
        <main class="main-content" id="main-content" tabindex="-1">
            <?php if ($notFound): ?>
                <div class="panel missing-product"><h2>Không tìm thấy hàng hóa</h2><p>Hàng hóa này có thể đã bị xóa hoặc đường dẫn không đúng.</p><a href="<?= $back ?>"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#arrow-left"></use></svg> Quay lại</a></div>
            <?php else: ?>
                <div class="detail-heading">
                    <div><a class="back-link" href="<?= $back ?>"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#arrow-left"></use></svg> <?= $canManage ? 'Danh sách hàng hóa' : 'Quay lại' ?></a><div class="heading-title"><span class="heading-icon"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#package"></use></svg></span><div><span class="detail-eyebrow">HÀNG HÓA #<?= (int) $productId ?></span><h2><?= detailEsc($product['ten_san_pham']) ?></h2><span class="category-pill"><?= detailEsc($product['loai']) ?></span></div></div></div>
                    <?php if ($canManage): ?><a class="edit-link" href="products/edit.php?id=<?= (int) $productId ?>"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#edit"></use></svg> Chỉnh sửa</a><?php endif; ?>
                </div>
                <section class="detail-metrics" aria-label="Số lượng hàng hóa">
                    <article class="metric"><div class="metric-top"><span>Đã nhập</span><i class="blue"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#import"></use></svg></i></div><strong><?= number_format($totalImport, 0, ',', '.') ?></strong><small><?= detailEsc($product['don_vi']) ?> · Theo phiếu nhập</small></article>
                    <article class="metric"><div class="metric-top"><span>Đã xuất</span><i class="orange"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#export"></use></svg></i></div><strong><?= number_format($totalExport, 0, ',', '.') ?></strong><small><?= detailEsc($product['don_vi']) ?> · Theo phiếu xuất</small></article>
                    <article class="metric"><div class="metric-top"><span>Còn trong kho</span><i class="green"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#warehouse"></use></svg></i></div><strong><?= number_format((int) $product['so_luong_con_lai'], 0, ',', '.') ?></strong><small><?= detailEsc($product['don_vi']) ?> · Số tồn hiện tại</small></article>
                </section>
                <div class="detail-grid">
                    <section class="panel detail-info">
                        <div class="panel-heading"><div><h3>Thông tin hàng hóa</h3><p>Thông tin cơ bản và mô tả</p></div></div>
                        <dl class="info-list">
                            <div><dt>Tên hàng hóa</dt><dd><?= detailEsc($product['ten_san_pham']) ?></dd></div>
                            <div><dt>Loại hàng hóa</dt><dd><?= detailEsc($product['loai']) ?></dd></div>
                            <div><dt>Đơn vị tính</dt><dd><?= detailEsc($product['don_vi']) ?></dd></div>
                            <div><dt>Ngày nhập</dt><dd><?= date('d/m/Y', strtotime($product['ngay_nhap'])) ?></dd></div>
                            <div><dt>Serial</dt><dd><?= $product['serial'] ? detailEsc($product['serial']) : 'Chưa có' ?></dd></div>
                            <div><dt>Ghi chú</dt><dd class="note"><?= $product['ghi_chu'] ? nl2br(detailEsc($product['ghi_chu'])) : 'Chưa có ghi chú' ?></dd></div>
                        </dl>
                    </section>
                    <section class="panel image-panel">
                        <div class="panel-heading"><div><h3>Hình ảnh hàng hóa</h3><p><?= count($images) ?> hình ảnh</p></div></div>
                        <?php if ($images): ?>
                            <button type="button" class="main-image" id="open-gallery" aria-label="Xem ảnh lớn"><img src="<?= detailEsc($images[0]['file_path']) ?>" alt="<?= detailEsc($images[0]['alt_text'] ?: $product['ten_san_pham']) ?>"></button>
                            <div class="image-thumbs"><?php foreach ($images as $index => $image): ?><button type="button" class="image-thumb <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>" aria-label="Xem ảnh <?= $index + 1 ?>"><img src="<?= detailEsc($image['file_path']) ?>" alt="<?= detailEsc($image['alt_text'] ?: $product['ten_san_pham']) ?>" loading="lazy"></button><?php endforeach; ?></div>
                        <?php else: ?><div class="no-image"><span><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#image"></use></svg></span><p>Chưa có hình ảnh cho hàng hóa này.</p></div><?php endif; ?>
                    </section>
                </div>
                <div class="history-grid">
                    <section class="panel history-panel"><div class="panel-heading"><div><h3>Phiếu nhập liên quan</h3><p><?= count($importBills) ?> phiếu nhập</p></div></div><div class="history-scroll"><table><thead><tr><th>Số hóa đơn</th><th>Ngày nhập</th><th>Số lượng</th></tr></thead><tbody>
                        <?php foreach ($importBills as $bill): ?><tr><td><a class="bill-link" href="imports/details.php?id=<?= (int) $bill['id'] ?>"><?= detailEsc($bill['so_hoa_don']) ?></a></td><td><?= date('d/m/Y', strtotime($bill['ngay_nhap'])) ?></td><td class="quantity">+<?= number_format((int) $bill['quantity'], 0, ',', '.') ?></td></tr><?php endforeach; ?>
                        <?php if (!$importBills): ?><tr><td colspan="3" class="empty-history">Chưa có phiếu nhập.</td></tr><?php endif; ?>
                    </tbody></table></div></section>
                    <section class="panel history-panel"><div class="panel-heading"><div><h3>Phiếu xuất liên quan</h3><p><?= count($exportBills) ?> phiếu xuất</p></div></div><div class="history-scroll"><table><thead><tr><th>Số phiếu</th><th>Ngày xuất</th><th>Số lượng</th></tr></thead><tbody>
                        <?php foreach ($exportBills as $bill): ?><tr><td><a class="bill-link" href="exports/details.php?id=<?= (int) $bill['id'] ?>"><?= detailEsc($bill['so_hd_xuat'] ?: 'Phiếu #' . $bill['id']) ?></a></td><td><?= date('d/m/Y', strtotime($bill['ngay_nhan'])) ?></td><td class="quantity out">−<?= number_format((int) $bill['quantity'], 0, ',', '.') ?></td></tr><?php endforeach; ?>
                        <?php if (!$exportBills): ?><tr><td colspan="3" class="empty-history">Chưa có phiếu xuất.</td></tr><?php endif; ?>
                    </tbody></table></div></section>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<?php if (!$notFound && $images): ?>
<dialog class="gallery-dialog" id="gallery-dialog" aria-label="Xem hình ảnh hàng hóa"><button type="button" class="gallery-close" id="close-gallery" aria-label="Đóng"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#close"></use></svg></button><img id="gallery-large-image" src="" alt=""><div class="gallery-controls"><button type="button" id="previous-image"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#arrow-left"></use></svg> Trước</button><span id="gallery-counter"></span><button type="button" id="next-image">Tiếp <svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#arrow-right"></use></svg></button></div></dialog>
<?php endif; ?>
<script>
const menu = document.querySelector('.menu-toggle');
menu.addEventListener('click', () => {
    const open = document.body.classList.toggle('menu-open');
    menu.setAttribute('aria-expanded', String(open));
    menu.setAttribute('aria-label', open ? 'Đóng menu' : 'Mở menu');
});
<?php if (!$notFound && $images): ?>
const imageList = <?= json_encode(array_map(static fn($image) => ['src' => $image['file_path'], 'alt' => $image['alt_text'] ?: $product['ten_san_pham']], $images), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const gallery = document.getElementById('gallery-dialog');
const mainImage = document.querySelector('.main-image img');
let selectedImage = 0;
function selectImage(index) {
    selectedImage = (index + imageList.length) % imageList.length;
    const image = imageList[selectedImage];
    mainImage.src = image.src;
    mainImage.alt = image.alt;
    document.querySelectorAll('.image-thumb').forEach((thumb, position) => thumb.classList.toggle('active', position === selectedImage));
    document.getElementById('gallery-large-image').src = image.src;
    document.getElementById('gallery-large-image').alt = image.alt;
    document.getElementById('gallery-counter').textContent = (selectedImage + 1) + ' / ' + imageList.length;
}
document.querySelectorAll('.image-thumb').forEach(thumb => thumb.addEventListener('click', () => selectImage(Number(thumb.dataset.index))));
document.getElementById('open-gallery').addEventListener('click', () => { selectImage(selectedImage); gallery.showModal(); });
document.getElementById('close-gallery').addEventListener('click', () => gallery.close());
document.getElementById('previous-image').addEventListener('click', () => selectImage(selectedImage - 1));
document.getElementById('next-image').addEventListener('click', () => selectImage(selectedImage + 1));
gallery.addEventListener('click', event => { if (event.target === gallery) gallery.close(); });
gallery.addEventListener('keydown', event => {
    if (event.key === 'ArrowLeft') selectImage(selectedImage - 1);
    if (event.key === 'ArrowRight') selectImage(selectedImage + 1);
});
<?php endif; ?>
</script>
<script src="assets/js/shared/theme.js" defer></script>
</body>
</html>
