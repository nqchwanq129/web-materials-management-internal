<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../auth/sign-in.php'); exit; }

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$productId]);
$p = $stmt->fetch();
if (!$p) { echo 'Sản phẩm không tồn tại'; exit; }

// Lấy danh sách ảnh
$stmt = $pdo->prepare('SELECT file_path, position, alt_text FROM product_images WHERE product_id = ? ORDER BY position ASC, id ASC');
$stmt->execute([$productId]);
$images = $stmt->fetchAll();

// Lấy danh sách số hóa đơn nhập
$stmt = $pdo->prepare('SELECT DISTINCT ib.so_hoa_don, ib.ngay_nhap FROM import_bill_details ibd JOIN import_bill ib ON ibd.import_bill_id = ib.id WHERE ibd.product_id = ? ORDER BY ib.ngay_nhap DESC');
$stmt->execute([$productId]);
$bills = $stmt->fetchAll();

// Tính tổng số lượng nhập/xuất
$stmt = $pdo->prepare('SELECT COALESCE(SUM(so_luong_nhap),0) AS total_import FROM import_bill_details WHERE product_id = ?');
$stmt->execute([$productId]);
$total_import = (int)($stmt->fetch()['total_import'] ?? 0);

$stmt = $pdo->prepare('SELECT COALESCE(SUM(so_luong_xuat),0) AS total_export FROM export_bill_details WHERE product_id = ?');
$stmt->execute([$productId]);
$total_export = (int)($stmt->fetch()['total_export'] ?? 0);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
  <meta charset="UTF-8">
  <title>Chi tiết hàng hóa</title>
  <link rel="stylesheet" href="assets/css/shared/layout.css">
  <link rel="stylesheet" href="assets/css/products/details.css">
  <?php $firstDate = $p['ngay_nhap'] ? date('d/m/Y', strtotime($p['ngay_nhap'])) : ''; ?>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">
        <h2>Chi tiết hàng hóa</h2>
        <div style="display: flex; gap: 10px;">
          <a href="products/edit.php?id=<?php echo $productId; ?>" class="btn-edit" style="display:inline-flex; align-items:center; gap:8px; padding:8px 12px; background:linear-gradient(135deg, #ff9800 0%, #e65100 100%); color:#fff; border-radius:6px; text-decoration:none; font-weight:600;">✏️ Chỉnh sửa</a>
          <a href="javascript:history.back()" class="btn-back">← Quay lại</a>
        </div>
      </div>
      <div class="content">
        <div class="grid">
          <div>
            <div class="info">
              <div class="label">Tên hàng hóa:</div><div class="value"><?php echo htmlspecialchars($p['ten_san_pham']); ?></div>
              <div class="label">Loại:</div><div class="value"><?php echo htmlspecialchars($p['loai']); ?></div>
              <div class="label">Đơn vị tính:</div><div class="value"><?php echo htmlspecialchars($p['don_vi']); ?></div>
              <div class="label">Ngày nhập đầu tiên:</div><div class="value"><?php echo htmlspecialchars($firstDate); ?></div>
              <div class="label">Số lượng nhập:</div><div class="value"><?php echo $total_import; ?></div>
              <div class="label">Số lượng xuất:</div><div class="value"><?php echo $total_export; ?></div>
              <div class="label">Số lượng còn lại:</div><div class="value"><?php echo (int)$p['so_luong_con_lai']; ?></div>
              <div class="label">Serial:</div><div class="value"><?php echo htmlspecialchars($p['serial'] ?? ''); ?></div>
              <div class="label">Ghi chú:</div><div class="value"><?php echo nl2br(htmlspecialchars($p['ghi_chu'] ?? '')); ?></div>
            </div>

            <div class="section-title">Các hóa đơn nhập liên quan</div>
            <table>
              <thead><tr><th>Số hóa đơn</th><th>Ngày nhập</th></tr></thead>
              <tbody>
                <?php if ($bills): foreach ($bills as $b): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($b["so_hoa_don"]); ?></td>
                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($b['ngay_nhap']))); ?></td>
                  </tr>
                <?php endforeach; else: ?>
                  <tr><td colspan="2">Không có dữ liệu</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div>
            <div class="section-title">Ảnh sản phẩm</div>
            <div class="gallery" id="gallery">
              <?php if ($images): foreach ($images as $img): ?>
                <img src="<?php echo htmlspecialchars($img['file_path']); ?>" alt="<?php echo htmlspecialchars($img['alt_text'] ?? ''); ?>">
              <?php endforeach; else: ?>
                <span>Chưa có ảnh</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="lb-backdrop" id="lb">
    <div class="lb-content"><img id="lb-img" src="" alt="preview"></div>
  </div>

  <script>
    // Chọn phóng to ảnh khi click
    (function(){
      const lb = document.getElementById('lb');
      const lbImg = document.getElementById('lb-img');
      const gallery = document.getElementById('gallery');
      if (!gallery) return;
      gallery.querySelectorAll('img').forEach(img => {
        img.addEventListener('click', () => {
          lbImg.src = img.src;
          lb.style.display = 'flex';
        });
      });
      lb.addEventListener('click', () => { lb.style.display = 'none'; });
    })();
  </script>
</body>
</html>


