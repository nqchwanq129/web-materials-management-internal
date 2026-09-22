<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/money_parse.php';
require_once __DIR__ . '/../../app/helpers/csrf_helper.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Thủ kho')) {
    header('Location: ../auth/sign-in.php');
    exit;
}

// Lấy ID hóa đơn từ URL
$billId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$billId) {
    header('Location: ../imports/index.php');
    exit;
}

// Lấy thông tin hóa đơn nhập
$stmt = $pdo->prepare("SELECT * FROM import_bill WHERE id = ?");
$stmt->execute([$billId]);
$importBill = $stmt->fetch();

if (!$importBill) {
    header('Location: ../imports/index.php');
    exit;
}

// Xử lý lưu hóa đơn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    requireCSRFToken(); // Validate CSRF token
    try {
        $pdo->beginTransaction();
        
        // Cập nhật thông tin hóa đơn
        $stmt = $pdo->prepare("UPDATE import_bill SET 
            nha_cung_cap = ?, 
            nguoi_nhan_hang = ?,
            nhap_vao_don_vi = ?, 
            ngay_nhap = ?, 
            so_hoa_don = ?, 
            serial = ?, 
            tong_tien = ?
            WHERE id = ?");
        
        // Xử lý so_hoa_don để tránh duplicate empty string
        $soHoaDon = trim($_POST['invoice-number'] ?? '');
        
        if (empty($soHoaDon)) {
            $soHoaDon = 'HD' . date('Ymd') . '_' . $billId; // Tạo số hóa đơn tự động nếu rỗng
        }
        
        try {
            $stmt->execute([
                $_POST['supplier'] ?? '',
                $_POST['receiver-name'] ?? '',
                $_POST['import-unit'] ?? '',
                $_POST['import-date'] ?? '',
                $soHoaDon,
                $_POST['serial'] ?? '',
                round(parseMoneyStringToFloat($_POST['total-amount'] ?? '0'), 3),
                $billId
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate key error
                $soHoaDon = 'HD' . date('YmdHis') . '_' . $billId; // Tạo số hóa đơn unique
                $stmt->execute([
                    $_POST['supplier'] ?? '',
                    $_POST['receiver-name'] ?? '',
                    $_POST['import-unit'] ?? '',
                    $_POST['import-date'] ?? '',
                    $soHoaDon,
                    $_POST['serial'] ?? '',
                    round(parseMoneyStringToFloat($_POST['total-amount'] ?? '0'), 3),
                    $billId
                ]);
            } else {
                throw $e; // Re-throw nếu không phải lỗi duplicate
            }
        }
        
        // Cập nhật thông tin hàng hóa nếu có
        if (isset($_POST['products']) && is_array($_POST['products'])) {
            $totalAmount = 0;
            
            foreach ($_POST['products'] as $productId => $productData) {
                // Tính lại thành tiền dựa trên số lượng và đơn giá
                $soLuong = (int)($productData['so_luong_nhap'] ?? 0);
                $donGia = round(parseMoneyStringToFloat($productData['don_gia'] ?? '0'), 3);
                $thanhTien = round($soLuong * $donGia, 3);
                
                // Xử lý upload ảnh sản phẩm nếu có
                $anhSanPham = null;
                if (isset($_FILES['products'][$productId]['image']) && $_FILES['products'][$productId]['image']['error'] === UPLOAD_ERR_OK) {
                    $imageFile = $_FILES['products'][$productId]['image'];
                    
                    // Kiểm tra loại file
                    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                    if (in_array($imageFile['type'], $allowedTypes)) {
                        // Tạo thư mục upload cho sản phẩm cụ thể
                        $uploadDir = 'uploads/products/' . $productId . '/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        // Tạo tên file duy nhất theo cấu trúc database
                        $fileExtension = pathinfo($imageFile['name'], PATHINFO_EXTENSION);
                        $fileName = $productId . '_' . time() . '_1.' . $fileExtension;
                        $filePath = $uploadDir . $fileName;
                        
                        // Di chuyển file
                        if (move_uploaded_file($imageFile['tmp_name'], $filePath)) {
                            $anhSanPham = $filePath;
                        }
                    }
                }
                
                // Cập nhật thông tin sản phẩm
                if ($anhSanPham) {
                    $stmt = $pdo->prepare("UPDATE products SET 
                        ten_san_pham = ?, 
                        loai = ?, 
                        don_vi = ?, 
                        ghi_chu = ?, 
                        serial = ?,
                        so_luong_nhap = ?,
                        don_gia = ?,
                        thanh_tien = ?,
                        image_path = ?
                        WHERE id = ?");
                    
                    $updateData = [
                        $productData['ten_san_pham'] ?? '',
                        $productData['loai'] ?? '',
                        $productData['don_vi'] ?? '',
                        $productData['ghi_chu'] ?? '',
                        $productData['serial'] ?? '',
                        $soLuong,
                        $donGia,
                        $thanhTien,
                        $anhSanPham,
                        $productId
                    ];
                } else {
                    $stmt = $pdo->prepare("UPDATE products SET 
                        ten_san_pham = ?, 
                        loai = ?, 
                        don_vi = ?, 
                        ghi_chu = ?, 
                        serial = ?,
                        so_luong_nhap = ?,
                        don_gia = ?,
                        thanh_tien = ?
                        WHERE id = ?");
                    
                    $updateData = [
                        $productData['ten_san_pham'] ?? '',
                        $productData['loai'] ?? '',
                        $productData['don_vi'] ?? '',
                        $productData['ghi_chu'] ?? '',
                        $productData['serial'] ?? '',
                        $soLuong,
                        $donGia,
                        $thanhTien,
                        $productId
                    ];
                }
                
                $stmt->execute($updateData);
                
                // Cập nhật chi tiết nhập kho
                $stmt = $pdo->prepare("UPDATE import_bill_details SET 
                    so_luong_nhap = ?, 
                    don_gia = ?, 
                    thanh_tien = ?
                    WHERE product_id = ? AND import_bill_id = ?");
                
                $stmt->execute([
                    $soLuong,
                    $donGia,
                    $thanhTien,
                    $productId,
                    $billId
                ]);
                
                // Tính số lượng còn lại = số lượng nhập - số lượng đã xuất
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(so_luong_xuat), 0) as tong_xuat FROM export_bill_details WHERE product_id = ?");
                $stmt->execute([$productId]);
                $tongXuat = $stmt->fetch()['tong_xuat'] ?? 0;
                $soLuongConLai = $soLuong - $tongXuat;
                
                // Cập nhật số lượng còn lại
                $stmt = $pdo->prepare("UPDATE products SET so_luong_con_lai = ? WHERE id = ?");
                $stmt->execute([$soLuongConLai, $productId]);
                
                // Cộng dồn tổng tiền
                $totalAmount += $thanhTien;
            }
            
            // Cập nhật lại tổng tiền của hóa đơn
            $stmt = $pdo->prepare("UPDATE import_bill SET tong_tien = ? WHERE id = ?");
            $stmt->execute([$totalAmount, $billId]);
        }
        
        $pdo->commit();
        $success_message = 'Đã cập nhật thông tin hóa đơn và hàng hóa thành công!';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = 'Lỗi khi cập nhật hóa đơn: ' . $e->getMessage();
    }
}

// Xử lý xóa hóa đơn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        $pdo->beginTransaction();

        // Lấy thông tin hóa đơn để xóa file PDF nếu có
        $stmt = $pdo->prepare('SELECT pdf_path FROM import_bill WHERE id = ?');
        $stmt->execute([$billId]);
        $billInfo = $stmt->fetch();
        
        // Xóa file PDF nếu có
        if ($billInfo && !empty($billInfo['pdf_path'])) {
            $pdfPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . $billInfo['pdf_path'];
            if (file_exists($pdfPath)) {
                @unlink($pdfPath);
            }
        }

        // Lấy danh sách product_id gắn với hóa đơn này
        $stmt = $pdo->prepare('SELECT product_id FROM import_bill_details WHERE import_bill_id = ?');
        $stmt->execute([$billId]);
        $productIds = array_column($stmt->fetchAll(), 'product_id');

        // Nếu có sản phẩm, kiểm tra xem đã được xuất chưa
        if (!empty($productIds)) {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));

            // Kiểm tra tồn tại trong export_bill_details
            $checkSql = "SELECT COUNT(*) AS cnt FROM export_bill_details WHERE product_id IN ($placeholders)";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute($productIds);
            $exportCount = (int)$checkStmt->fetch()['cnt'];

            if ($exportCount > 0) {
                $pdo->rollBack();
                $error_message = 'Không thể xóa hóa đơn vì có sản phẩm trong hóa đơn này đã được xuất. Vui lòng thu hồi các phiếu xuất liên quan hoặc xóa thủ công trước.';
            } else {
                // Xóa chi tiết hóa đơn
                $stmt = $pdo->prepare('DELETE FROM import_bill_details WHERE import_bill_id = ?');
                $stmt->execute([$billId]);

                // Xóa ảnh + thư mục ảnh từng sản phẩm
                foreach ($productIds as $pid) {
                    // Xóa thư mục uploads/products/{id} và tất cả file trong đó
                    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . $pid;
                    if (is_dir($dir)) {
                        $files = glob($dir . DIRECTORY_SEPARATOR . '*');
                        if ($files) { 
                            foreach ($files as $f) { 
                                if (is_file($f)) {
                                    @unlink($f); 
                                }
                            } 
                        }
                        @rmdir($dir);
                    }
                    
                    // Xóa bản ghi product_images
                    $stmt = $pdo->prepare('DELETE FROM product_images WHERE product_id = ?');
                    $stmt->execute([$pid]);
                }

                // Xóa các sản phẩm được tạo từ hóa đơn này
                $delSql = "DELETE FROM products WHERE id IN ($placeholders)";
                $delStmt = $pdo->prepare($delSql);
                $delStmt->execute($productIds);

                // Xóa hóa đơn
                $stmt = $pdo->prepare('DELETE FROM import_bill WHERE id = ?');
                $stmt->execute([$billId]);

                $pdo->commit();
                header('Location: ../imports/index.php?deleted=1');
                exit;
            }
        } else {
            // Không có sản phẩm gắn với hóa đơn -> chỉ xóa chi tiết và hóa đơn
            $stmt = $pdo->prepare('DELETE FROM import_bill_details WHERE import_bill_id = ?');
            $stmt->execute([$billId]);

            $stmt = $pdo->prepare('DELETE FROM import_bill WHERE id = ?');
            $stmt->execute([$billId]);

            $pdo->commit();
            header('Location: ../imports/index.php?deleted=1');
            exit;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = 'Lỗi khi xóa hóa đơn: ' . $e->getMessage();
    }
}

// Lấy danh sách hàng hóa của hóa đơn này
$stmt = $pdo->prepare("
    SELECT p.*, ibd.so_luong_nhap, ibd.don_gia, ibd.thanh_tien
    FROM products p
    INNER JOIN import_bill_details ibd ON p.id = ibd.product_id
    WHERE ibd.import_bill_id = ?
    ORDER BY ibd.id ASC
");
$stmt->execute([$billId]);
$products = $stmt->fetchAll();

// Lấy ảnh cho từng sản phẩm từ bảng product_images
foreach ($products as &$product) {
    $stmt = $pdo->prepare("
        SELECT file_path, position 
        FROM product_images 
        WHERE product_id = ?
        ORDER BY position ASC
    ");
    $stmt->execute([$product['id']]);
    $product['images'] = $stmt->fetchAll();
}
// Quan trọng: hủy tham chiếu để tránh làm hỏng dữ liệu khi foreach lần 2
unset($product);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết phiếu nhập kho - Admin</title>
    <link rel="stylesheet" href="assets/css/shared/layout.css">
    <link rel="stylesheet" href="assets/css/imports/details.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
    <button class="sidebar-toggle" id="sidebarToggle">☰</button>
    <div class="header">
        <div class="logo">
            <img src="assets/images/company-logo.png" alt="Vishipel Logo">
            <div class="logo-text">
                <h1>PHẦN MỀM QUẢN LÝ KHO VISHIPEL</h1>
                <p>CÔNG TY TNHH MTV THÔNG TIN ĐIỆN TỬ HÀNG HẢI VIỆT NAM</p>
            </div>
        </div>
        <div class="user-info">
            <span class="greeting">Xin chào <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="auth/log-out.php" class="logout-btn">Đăng xuất</a>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <ul class="menu">
                <li><a href="reports/statistics.php">Số liệu thống kê</a></li>
                <?php if ($_SESSION['role'] === 'Admin'): ?>
                <li><a href="accounts/index.php">Quản lý tài khoản</a></li>
                <?php endif; ?>
                <li class="active">Nhập hàng hóa
                    <ul>
                        <li><a href="imports/create.php">Nhập hóa đơn</a></li>
                        <li class="active"><a href="imports/index.php">DS phiếu nhập kho</a></li>
                    </ul>
                </li>
                <li>Xuất hàng hóa
                    <ul>
                        <li><a href="exports/create.php">Xuất hóa đơn</a></li>
                        <li><a href="exports/index.php">DS phiếu xuất kho</a></li>
                    </ul>
                </li>
                <li>Danh Sách Hàng Hóa
                    <ul>
                        <li><a href="products/index.php">Tất Cả Hàng Hóa</a></li>
                        <li><a href="products/index.php?type=cong-cu">Công Cụ Dụng Cụ</a></li>
                        <li><a href="products/index.php?type=vat-tu">Vật Tư</a></li>
                        <li><a href="products/index.php?type=tai-san">Tài Sản Cố Định</a></li>
                        <li><a href="products/index.php?type=phu-tung">Phụ Tùng Thay Thế</a></li>
                        <li><a href="products/index.php?type=khac">Khác</a></li>
                    </ul>
                </li>
            </ul>
        </div>
        
        <div class="main-content">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                <h2>Chi Tiết Phiếu Nhập Kho</h2>
                <div>
                    <a href="imports/export-excel.php?id=<?php echo htmlspecialchars($billId); ?>" class="btn btn-success">📊 Xuất Excel</a>
                </div>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                    ✅ <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
                    ❌ <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Phần Thông Tin Hóa Đơn -->
            <div class="section">
                <div class="section-header">
                    <h3>HĐ nhập số <?php echo htmlspecialchars($importBill['so_hoa_don'] ?? ''); ?></h3>
                </div>
                <div class="section-content">
                    <form class="invoice-form" method="POST">
                        <input type="hidden" name="action" value="save">
                        <div class="form-row">
                            <div class="form-column">
                                <div class="form-group">
                                    <label for="supplier">Nhà Cung Cấp:</label>
                                    <input type="text" id="supplier" name="supplier" 
                                           value="<?php echo htmlspecialchars($importBill['nha_cung_cap'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="import-unit">Nhập vào kho:</label>
                                    <input type="text" id="import-unit" name="import-unit" 
                                           value="<?php echo htmlspecialchars($importBill['nhap_vao_don_vi'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="import-date">Ngày Nhập:</label>
                                    <div class="date-input-container">
                                        <input type="text" id="import-date" name="import-date" class="date-picker" placeholder="dd/mm/yyyy"
                                               value="<?php echo date('Y-m-d', strtotime($importBill['ngay_nhap'] ?? date('Y-m-d'))); ?>" required>
                                        <span class="calendar-icon">📅</span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="invoice-number">Số Hóa Đơn:</label>
                                    <input type="text" id="invoice-number" name="invoice-number" 
                                           value="<?php echo htmlspecialchars($importBill['so_hoa_don'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="form-column">
                                <div class="form-group">
                                    <label for="receiver-name">Họ và tên người nhập hàng:</label>
                                    <input type="text" id="receiver-name" name="receiver-name" 
                                           value="<?php echo htmlspecialchars($importBill['nguoi_nhan_hang'] ?? 'Dương Mạnh Tuấn'); ?>" 
                                           placeholder="Nhập họ tên người nhập hàng">
                                </div>
                                <div class="form-group">
                                    <label for="serial">Số Serial:</label>
                                    <input type="text" id="serial" name="serial" 
                                           value="<?php echo htmlspecialchars($importBill['serial'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="total-amount">Tổng Tiền (VNĐ):</label>
                                    <input type="text" id="total-amount" name="total-amount" 
                                           value="<?php echo htmlspecialchars(formatVnAmount($importBill['tong_tien'] ?? 0)); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                    </form>
                </div>
            </div>

            <!-- Phần Hóa Đơn PDF -->
            <div class="section">
                <div class="section-header">
                    <h3>Hóa Đơn PDF</h3>
                </div>
                <div class="section-content">
                    <?php 
                    // Ưu tiên đường dẫn PDF trong database; chỉ hiển thị nếu có lưu đường dẫn
                    $pdf_file_path = isset($importBill['pdf_path']) ? $importBill['pdf_path'] : null;
                    $pdf_exists = $pdf_file_path && file_exists($pdf_file_path);
                    ?>
                    <!-- Form upload PDF mới - luôn hiển thị để có thể thay thế PDF cũ -->
                    <div class="pdf-upload-section" style="margin-bottom: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 2px dashed #dee2e6;">
                        <h4 style="margin-top: 0; color: #495057;">
                            <?php if ($pdf_exists): ?>
                                📄 Thay thế hóa đơn PDF hiện tại
                            <?php else: ?>
                                📄 Tải lên hóa đơn PDF
                            <?php endif; ?>
                        </h4>
                        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                            <input type="file" name="pdf_file" accept=".pdf" id="pdf-file" style="flex: 1; min-width: 200px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <span style="color: #666; font-size: 14px;">Chọn PDF để thay thế (sẽ lưu khi bấm "Lưu Hóa Đơn")</span>
                        </div>
                        <div id="pdf-preview" style="margin-top: 15px;"></div>
                    </div>

                    <?php if ($pdf_exists): ?>
                        <div class="pdf-info">
                            <iframe src="<?php echo htmlspecialchars($pdf_file_path); ?>?t=<?php echo time(); ?>" 
                                    style="width:100%; height:600px; border:1px solid #e0e0e0; border-radius:6px;"
                                    title="Hóa đơn PDF"></iframe>
                            <div style="margin-top:12px; text-align:right; display:flex; justify-content:flex-end; gap:10px;">
                                <a class="btn btn-secondary" download="hoa_don_<?php echo htmlspecialchars($importBill['so_hoa_don'] ?? ''); ?>.pdf" href="<?php echo htmlspecialchars($pdf_file_path); ?>?t=<?php echo time(); ?>">Tải xuống PDF</a>
                                <a class="btn btn-primary" target="_blank" href="<?php echo htmlspecialchars($pdf_file_path); ?>?t=<?php echo time(); ?>">Mở PDF trong tab mới</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="pdf-placeholder">
                            <i class="fas fa-file-pdf"></i>
                            <p>Chưa có hóa đơn PDF</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Phần Danh Sách Hàng Hóa -->
            <div class="section">
                <div class="section-header">
                    <h3>Danh Sách Hàng Hóa</h3>
                </div>
                <div class="section-content">
                    <?php if (empty($products)): ?>
                        <div class="no-data">
                            <p>Không có hàng hóa nào trong hóa đơn này</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="goods-table">
                                <thead>
                                    <tr>
                                        <th>Hàng Hóa</th>
                                        <th>Nhóm hàng</th>
                                        <th>Đơn vị tính</th>
                                        <th>Số Lượng</th>
                                        <th>Giá Trước Thuế (VNĐ)</th>
                                        <th>VAT (%)</th>
                                        <th>Giá Sau Thuế (VNĐ)</th>
                                        <th>Tổng Giá Trước Thuế (VNĐ)</th>
                                        <th>Tổng Giá Sau Thuế (VNĐ)</th>
                                        <th>Ghi chú</th>
                                        <th>Serial</th>
                                        <th>Ảnh</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td>
                                                <input type="text" name="products[<?php echo $product['id']; ?>][ten_san_pham]" 
                                                       value="<?php echo htmlspecialchars($product['ten_san_pham']); ?>" 
                                                       class="form-input" required>
                                            </td>
                                            <td>
                                                <select name="products[<?php echo $product['id']; ?>][loai]" class="form-input" required>
                                                    <option value="Công cụ dụng cụ" <?php echo $product['loai'] === 'Công cụ dụng cụ' ? 'selected' : ''; ?>>Công cụ dụng cụ</option>
                                                    <option value="Vật tư" <?php echo $product['loai'] === 'Vật tư' ? 'selected' : ''; ?>>Vật tư</option>
                                                    <option value="Tài sản cố định" <?php echo $product['loai'] === 'Tài sản cố định' ? 'selected' : ''; ?>>Tài sản cố định</option>
                                                    <option value="Phụ tùng thay thế" <?php echo $product['loai'] === 'Phụ tùng thay thế' ? 'selected' : ''; ?>>Phụ tùng thay thế</option>
                                                    <option value="Khác" <?php echo $product['loai'] === 'Khác' ? 'selected' : ''; ?>>Khác</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="products[<?php echo $product['id']; ?>][don_vi]" 
                                                       value="<?php echo htmlspecialchars($product['don_vi']); ?>" 
                                                       class="form-input" required>
                                            </td>
                                            <td>
                                                <input type="number" name="products[<?php echo $product['id']; ?>][so_luong_nhap]" 
                                                       value="<?php echo $product['so_luong_nhap']; ?>" 
                                                       class="form-input center" min="1" required>
                                            </td>
                                            <td>
                                                <input type="text" name="products[<?php echo $product['id']; ?>][don_gia]" 
                                                       value="<?php echo htmlspecialchars(formatVnAmount($product['don_gia'])); ?>" 
                                                       class="form-input amount" required>
                                            </td>
                                            <?php 
                                                // Suy ra VAT% từ dữ liệu: dùng tổng tiền sau thuế ở chi tiết (ibd.thanh_tien)
                                                $soLuong = max(1, (int)$product['so_luong_nhap']);
                                                $donGiaTruocThue = (float)$product['don_gia'];
                                                $tongSauThue = (float)$product['thanh_tien'];
                                                $donGiaSauThue = $soLuong > 0 ? ($tongSauThue / $soLuong) : $donGiaTruocThue;
                                                $vatPercentInferred = $donGiaTruocThue > 0.00001
                                                    ? max(0, (int)round(($donGiaSauThue / $donGiaTruocThue - 1) * 100))
                                                    : 0;
                                            ?>
                                            <td>
                                                <input type="text" name="products[<?php echo $product['id']; ?>][vat]" 
                                                       value="<?php echo $vatPercentInferred; ?>" 
                                                       class="form-input center" required>
                                            </td>
                                            <td class="amount"><?php echo htmlspecialchars(formatVnAmount($donGiaSauThue)); ?></td>
                                            <td class="amount"><?php echo htmlspecialchars(formatVnAmount($soLuong * $donGiaTruocThue)); ?></td>
                                            <td class="amount"><?php echo htmlspecialchars(formatVnAmount($tongSauThue)); ?></td>
                                            <td>
                                                <input type="text" name="products[<?php echo $product['id']; ?>][ghi_chu]" 
                                                       value="<?php echo htmlspecialchars($product['ghi_chu'] ?? ''); ?>" 
                                                       class="form-input">
                                            </td>
                                            <td>
                                                <input type="text" name="products[<?php echo $product['id']; ?>][serial]" 
                                                       value="<?php echo htmlspecialchars($product['serial'] ?? ''); ?>" 
                                                       class="form-input">
                                            </td>
                                            <td>
                                                <div class="image-upload-container">
                                                    <input type="file" name="products[<?php echo $product['id']; ?>][image]" 
                                                           accept="image/*" class="image-upload-input" 
                                                           multiple
                                                           onchange="uploadProductImages(<?php echo $product['id']; ?>, this)"
                                                           title="Chọn một hoặc nhiều ảnh">
                                                    <div class="image-preview" id="preview_<?php echo $product['id']; ?>">
                                                        <span class="thumb-wrap" data-product-id="<?php echo (int)$product['id']; ?>">
                                                            <?php if (!empty($product['images']) && count($product['images']) > 0): ?>
                                                                <img class="thumb" loading="lazy" src="<?php echo htmlspecialchars($product['images'][0]['file_path']); ?>?t=<?php echo time(); ?>" alt="thumb">
                                                                <?php if (count($product['images']) > 1): ?>
                                                                    <span class="thumb-count">+<?php echo count($product['images']) - 1; ?></span>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span style="color:#aaa;">—</span>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Nút Lưu và Xóa Hóa Đơn -->
            <div class="action-section">
                <form id="save-form" method="POST" enctype="multipart/form-data" style="display: inline-block; margin-right: 15px;" onsubmit="return confirm('Bạn có chắc chắn muốn cập nhật hóa đơn này?')">
                    <input type="hidden" name="action" value="save">
                    <?php echo csrfTokenField(); ?>
                    <button type="submit" class="btn btn-primary">💾 Lưu Hóa Đơn</button>
                </form>
                
                <form method="POST" style="display: inline-block;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa hóa đơn này? Hành động này không thể hoàn tác!')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="bill_id" value="<?php echo $billId; ?>">
                    <?php echo csrfTokenField(); ?>
                    <button type="submit" class="btn btn-danger">🗑️ Xóa Hóa Đơn</button>
                </form>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>© 2024 - Phần mềm quản lý kho</p>
    </div>

    <!-- Lightbox for images -->
    <div class="lightbox-backdrop" id="lb">
        <div class="lightbox">
            <img id="lb-img" src="" alt="preview">
            <div class="lightbox-nav">
                <button class="lightbox-btn" id="lb-prev">← Trước</button>
                <button class="lightbox-btn" id="lb-next">Tiếp →</button>
                <button class="lightbox-btn" id="lb-close">Đóng</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/vn.js"></script>
    <script src="assets/js/shared/sidebar-toggle.js" defer></script>
    <script>
        // Khởi tạo Flatpickr tiếng Việt cho ô Ngày nhập
        (function(){
            if (window.flatpickr) {
                flatpickr('#import-date', {
                    locale: flatpickr.l10ns.vn,
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'd/m/Y',
                    allowInput: true
                });
            }
        })();

        // Toggle sidebar (dùng chung cơ chế)
        (function(){
            var btn = document.getElementById('sidebarToggle');
            if (btn) {
                btn.addEventListener('click', function(){
                    document.body.classList.toggle('sidebar-collapsed');
                });
            }
        })();

        function parseCurrencyValue(value) {
            if (value === undefined || value === null) return 0;
            let s = String(value).trim().replace(/\u00A0/g, '').replace(/\s/g, '');
            if (!s) return 0;
            // Thousand grouping with comma (EN-style): 276,852 / 1,234,567
            if (/^\d{1,3}(,\d{3})+$/.test(s)) {
                const n = parseFloat(s.replace(/,/g, ''));
                return Number.isFinite(n) ? n : 0;
            }
            // Thousand grouping with dot (VN-style): 276.852 / 1.234.567
            if (/^\d{1,3}(\.\d{3})+$/.test(s)) {
                const n = parseFloat(s.replace(/\./g, ''));
                return Number.isFinite(n) ? n : 0;
            }
            // EN decimal (XML hay trả về 6 chữ số sau dấu chấm)
            if (/^\d+\.\d{1,6}$/.test(s) && (s.match(/\./g) || []).length === 1) {
                const n = parseFloat(s);
                return Number.isFinite(n) ? n : 0;
            }
            const m = s.match(/^(.+),(\d{1,3})$/);
            if (m) {
                const intPart = m[1].replace(/\./g, '');
                const d = m[2].length;
                const n = parseInt(intPart, 10) + parseInt(m[2], 10) / Math.pow(10, d);
                return Number.isFinite(n) ? n : 0;
            }
            const digitsOnly = s.replace(/\./g, '').replace(/,/g, '');
            const n = parseFloat(digitsOnly);
            return Number.isFinite(n) ? n : 0;
        }
        function formatNumber(num) {
            if (!Number.isFinite(num)) return '0';
            const millis = Math.round(num * 1000);
            const intPart = Math.trunc(millis / 1000);
            let frac = Math.abs(millis % 1000);
            let intStr = String(intPart).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            if (frac === 0) return intStr;
            let fracStr = String(frac).padStart(3, '0').replace(/0+$/, '');
            return intStr + ',' + fracStr;
        }

        document.getElementById('total-amount').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^\d.,]/g, '').replace(/,(?=.*,)/g, '');
        });
        document.getElementById('total-amount').addEventListener('blur', function(e) {
            const v = parseCurrencyValue(e.target.value);
            if (v !== 0 || e.target.value.trim() !== '') e.target.value = formatNumber(v);
        });

        document.querySelectorAll('input[name*="[don_gia]"]').forEach(input => {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/[^\d.,]/g, '').replace(/,(?=.*,)/g, '');
            });
            input.addEventListener('blur', function() {
                const v = parseCurrencyValue(this.value);
                if (v !== 0 || this.value.trim() !== '') this.value = formatNumber(v);
            });
        });

        document.querySelectorAll('input[name*="[so_luong_nhap]"], input[name*="[don_gia]"], input[name*="[vat]"]').forEach(input => {
            input.addEventListener('input', function() {
                const row = this.closest('tr');
                const soLuongInput = row.querySelector('input[name*="[so_luong_nhap]"]');
                const donGiaInput = row.querySelector('input[name*="[don_gia]"]');
                const vatInput = row.querySelector('input[name*="[vat]"]');
                if (soLuongInput && donGiaInput && vatInput) {
                    const soLuong = parseInt(soLuongInput.value, 10) || 0;
                    const donGia = parseCurrencyValue(donGiaInput.value);
                    const vat = parseFloat(vatInput.value) || 0;
                    const giaSauThue = Math.round((donGia * (1 + vat / 100)) * 1000) / 1000;
                    const tongGiaTruocThue = Math.round(soLuong * donGia * 1000) / 1000;
                    const tongGiaSauThue = Math.round((tongGiaTruocThue + (tongGiaTruocThue * vat / 100)) * 1000) / 1000;
                    const giaSauThueCell = row.querySelector('td:nth-child(7)');
                    const thanhTienCell = row.querySelector('td:nth-child(8)');
                    const tongTienCell = row.querySelector('td:nth-child(9)');
                    if (giaSauThueCell) giaSauThueCell.textContent = formatNumber(giaSauThue);
                    if (thanhTienCell) thanhTienCell.textContent = formatNumber(tongGiaTruocThue);
                    if (tongTienCell) tongTienCell.textContent = formatNumber(tongGiaSauThue);
                }
            });
        });

        // Xử lý hiển thị tên file được chọn
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const fileName = this.files[0] ? this.files[0].name : 'No file chosen';
                this.parentElement.querySelector('.file-chosen').textContent = fileName;
            });
        });

        // Load số ảnh và mở lightbox khi click
        const wraps = document.querySelectorAll('.thumb-wrap');
        wraps.forEach(w => {
            const productId = w.getAttribute('data-product-id');
            
            // Lấy dữ liệu ảnh từ PHP thay vì gọi API
            const productRow = w.closest('tr');
            const productImages = <?php echo json_encode(array_column($products, 'images', 'id')); ?>;
            const images = productImages[productId] || [];
            
            if (images && images.length > 1) {
                const c = w.querySelector('.thumb-count');
                if (c) {
                    c.textContent = '+' + (images.length - 1);
                    c.style.display = 'inline-block';
                }
            }
            
            let idx = 0; 
            let list = images.map(img => ({ file_path: img.file_path }));
            
            w.addEventListener('click', () => {
                if (!list || list.length === 0) { return; }
                idx = 0; 
                document.getElementById('lb-img').src = list[idx].file_path; 
                document.getElementById('lb').style.display = 'flex';
                document.getElementById('lb-prev').onclick = () => { 
                    idx = (idx - 1 + list.length) % list.length; 
                    document.getElementById('lb-img').src = list[idx].file_path; 
                };
                document.getElementById('lb-next').onclick = () => { 
                    idx = (idx + 1) % list.length; 
                    document.getElementById('lb-img').src = list[idx].file_path; 
                };
                document.getElementById('lb-close').onclick = () => { 
                    document.getElementById('lb').style.display = 'none'; 
                };
            });
        });

        // Lưu trữ ảnh tạm thời để upload khi bấm lưu
        let pendingImages = {};
        
        // Xử lý chọn ảnh sản phẩm (chỉ preview, chưa upload)
        function uploadProductImages(productId, input) {
            const files = input.files;
            if (!files || files.length === 0) return;
            
            // Lưu files vào biến tạm
            pendingImages[productId] = files;
            
            // Preview ảnh đầu tiên ngay lập tức
            const preview = document.getElementById('preview_' + productId);
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = '<span class="thumb-wrap" data-product-id="' + productId + '"><img class="thumb" loading="lazy" src="' + e.target.result + '" alt="thumb"><span class="thumb-count" style="display: none;">+0</span></span>';
            };
            reader.readAsDataURL(files[0]);
        }

        // Lưu trữ PDF tạm thời để upload khi bấm lưu
        let pendingPDF = null;
        
        // Xử lý chọn PDF (chỉ preview, chưa upload)
        const pdfInput = document.getElementById('pdf-file');
        if (pdfInput) {
            pdfInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    pendingPDF = file;
                    
                    // Preview PDF
                    const pdfPreview = document.getElementById('pdf-preview');
                    if (pdfPreview) {
                        const url = URL.createObjectURL(file);
                        pdfPreview.innerHTML = `
                            <iframe src="${url}" width="100%" height="400px" style="border: 1px solid #ddd; border-radius: 4px;"></iframe>
                            <p style="margin-top: 10px; color: #666;">PDF đã chọn: ${file.name}</p>
                        `;
                    }
                } else {
                    pendingPDF = null;
                }
            });
        }

        // Xử lý lưu hóa đơn
        const saveForm = document.getElementById('save-form');
        if (saveForm) {
            saveForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Đang lưu...';
                submitBtn.disabled = true;
                
                // Thu thập dữ liệu từ form
                const formData = new FormData();
                formData.append('action', 'save');
                
                // Thông tin hóa đơn
                const supplier = document.getElementById('supplier').value;
                const receiverName = document.getElementById('receiver-name').value;
                const importUnit = document.getElementById('import-unit').value;
                const importDate = document.getElementById('import-date').value;
                const invoiceNumber = document.getElementById('invoice-number').value;
                const serial = document.getElementById('serial').value;
                const totalAmount = document.getElementById('total-amount').value;
                
                // Thêm CSRF token
                const csrfToken = document.querySelector('input[name="csrf_token"]').value;
                formData.append('csrf_token', csrfToken);
                
                formData.append('supplier', supplier);
                formData.append('receiver-name', receiverName);
                formData.append('import-unit', importUnit);
                formData.append('import-date', importDate);
                formData.append('invoice-number', invoiceNumber);
                formData.append('serial', serial);
                formData.append('total-amount', totalAmount);
                
                // Thông tin hàng hóa
                const productRows = document.querySelectorAll('tbody tr');
                productRows.forEach((row, index) => {
                    const productIdMatch = row.querySelector('input[name*="[so_luong_nhap]"]')?.name.match(/\[(\d+)\]/);
                    if (productIdMatch) {
                        const productId = productIdMatch[1];
                        formData.append(`products[${productId}][ten_san_pham]`, row.querySelector('input[name*="[ten_san_pham]"]')?.value || '');
                        formData.append(`products[${productId}][loai]`, row.querySelector('select[name*="[loai]"]')?.value || '');
                        formData.append(`products[${productId}][don_vi]`, row.querySelector('input[name*="[don_vi]"]')?.value || '');
                        formData.append(`products[${productId}][so_luong_nhap]`, row.querySelector('input[name*="[so_luong_nhap]"]')?.value || '0');
                        formData.append(`products[${productId}][don_gia]`, row.querySelector('input[name*="[don_gia]"]')?.value || '0');
                        formData.append(`products[${productId}][vat]`, row.querySelector('input[name*="[vat]"]')?.value || '0');
                        formData.append(`products[${productId}][ghi_chu]`, row.querySelector('input[name*="[ghi_chu]"]')?.value || '');
                        formData.append(`products[${productId}][serial]`, row.querySelector('input[name*="[serial]"]')?.value || '');
                    }
                });
                
                // Upload ảnh trước
                const uploadPromises = [];
                
                // Upload ảnh sản phẩm
                Object.keys(pendingImages).forEach(productId => {
                    const files = pendingImages[productId];
                    if (files && files.length > 0) {
                        const imageFormData = new FormData();
                        imageFormData.append('action', 'upload_multiple_product_images');
                        imageFormData.append('product_id', productId);
                        imageFormData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                        
                        for (let i = 0; i < files.length; i++) {
                            imageFormData.append('images[]', files[i]);
                        }
                        
                        uploadPromises.push(
                            fetch('products/upload-images.php', {
                                method: 'POST',
                                body: imageFormData
                            }).then(response => response.json())
                        );
                    }
                });
                
                // Upload PDF nếu có
                if (pendingPDF) {
                    const pdfFormData = new FormData();
                    pdfFormData.append('bill_id', '<?php echo $billId; ?>');
                    pdfFormData.append('pdf_file', pendingPDF);
                    pdfFormData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                    
                    uploadPromises.push(
                        fetch('imports/upload-pdf.php', {
                            method: 'POST',
                            body: pdfFormData
                        }).then(response => response.json())
                    );
                }
                
                // Thực hiện tất cả upload
                Promise.all(uploadPromises)
                .then(results => {
                    // Kiểm tra kết quả upload
                    const failedUploads = results.filter(result => !result.success);
                    let uploadMessage = '';
                    if (failedUploads.length > 0) {
                        const errorMessages = failedUploads.map(result => result.message || 'Upload thất bại').join('\n');
                        // Phân biệt lỗi upload ảnh và PDF
                        if (errorMessages.includes('ảnh')) {
                            uploadMessage = 'upload ảnh thất bại';
                        } else if (errorMessages.includes('PDF')) {
                            uploadMessage = 'upload PDF thất bại';
                        } else {
                            uploadMessage = 'upload file thất bại';
                        }
                    }
                    
                    // Lưu thông tin hóa đơn ngay cả khi upload thất bại
                    return fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    }).then(response => {
                        return { response, uploadMessage };
                    });
                })
                .then(({ response, uploadMessage }) => {
                    if (response.ok) {
                        if (uploadMessage) {
                            alert('Lưu thông tin thành công nhưng ' + uploadMessage.toLowerCase());
                        } else {
                            alert('Cập nhật hóa đơn thành công!');
                        }
                        // Xóa pending images và PDF sau khi lưu thành công
                        pendingImages = {};
                        pendingPDF = null;
                        location.reload();
                    } else {
                        alert('Có lỗi xảy ra khi cập nhật hóa đơn!');
                    }
                })
                .catch(error => {
                    alert('Có lỗi xảy ra khi cập nhật hóa đơn: ' + error.message);
                })
                .finally(() => {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                });
            });
        }
    </script>
</body>
</html>
