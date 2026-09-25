<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/money_parse.php';
require_once __DIR__ . '/../../app/helpers/csrf_helper.php';

// Kiểm tra quyền truy cập (Admin và Thủ kho)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Thủ kho')) {
    header('Location: ../auth/sign-in.php');
    exit;
}

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($productId <= 0) {
    header('Location: ../products/index.php');
    exit;
}

// Lấy thông tin sản phẩm
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$productId]);
$p = $stmt->fetch();
if (!$p) {
    echo 'Sản phẩm không tồn tại';
    exit;
}

$successMessage = '';
$errorMessage = '';

// Xử lý lưu form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();
    try {
        $pdo->beginTransaction();
        
        $ten_san_pham = trim($_POST['ten_san_pham'] ?? '');
        $loai = trim($_POST['loai'] ?? '');
        $don_vi = trim($_POST['don_vi'] ?? '');
        $ngay_nhap = trim($_POST['ngay_nhap'] ?? '');
        $so_luong_nhap = (int)($_POST['so_luong_nhap'] ?? 0);
        $don_gia = round(parseMoneyStringToFloat($_POST['don_gia'] ?? '0'), 3);
        $so_luong_con_lai = (int)($_POST['so_luong_con_lai'] ?? 0);
        $serial = trim($_POST['serial'] ?? '');
        $ghi_chu = trim($_POST['ghi_chu'] ?? '');
        $thanh_tien = round($so_luong_nhap * $don_gia, 3);
        
        if (empty($ten_san_pham) || empty($loai) || empty($don_vi) || empty($ngay_nhap)) {
            throw new Exception("Vui lòng điền đầy đủ các thông tin bắt buộc.");
        }
        
        // 1. Cập nhật bảng products
        $stmt = $pdo->prepare("UPDATE products SET 
            ten_san_pham = ?, 
            loai = ?, 
            don_vi = ?, 
            ngay_nhap = ?, 
            so_luong_nhap = ?, 
            don_gia = ?, 
            thanh_tien = ?, 
            so_luong_con_lai = ?, 
            serial = ?, 
            ghi_chu = ?
            WHERE id = ?");
        $stmt->execute([
            $ten_san_pham,
            $loai,
            $don_vi,
            $ngay_nhap,
            $so_luong_nhap,
            $don_gia,
            $thanh_tien,
            $so_luong_con_lai,
            $serial,
            $ghi_chu,
            $productId
        ]);
        
        // 2. Cập nhật chi tiết hóa đơn nhập (nếu có) trong bảng import_bill_details
        $stmt = $pdo->prepare("UPDATE import_bill_details SET 
            so_luong_nhap = ?, 
            don_gia = ?, 
            thanh_tien = ? 
            WHERE product_id = ?");
        $stmt->execute([$so_luong_nhap, $don_gia, $thanh_tien, $productId]);
        
        // 3. Tính toán lại tổng tiền của các hóa đơn nhập liên quan
        $stmt = $pdo->prepare("SELECT DISTINCT import_bill_id FROM import_bill_details WHERE product_id = ?");
        $stmt->execute([$productId]);
        $billIds = array_column($stmt->fetchAll(), 'import_bill_id');
        foreach ($billIds as $bId) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(thanh_tien), 0) as total FROM import_bill_details WHERE import_bill_id = ?");
            $stmt->execute([$bId]);
            $total = $stmt->fetch()['total'] ?? 0;
            
            $stmt = $pdo->prepare("UPDATE import_bill SET tong_tien = ? WHERE id = ?");
            $stmt->execute([$total, $bId]);
        }
        
        $uploadDir = 'uploads/products/' . $productId . '/';
        
        // 4. Xử lý xóa các ảnh được chọn
        if (isset($_POST['delete_image_ids']) && is_array($_POST['delete_image_ids'])) {
            foreach ($_POST['delete_image_ids'] as $imgId) {
                $imgId = (int)$imgId;
                // Lấy đường dẫn file để xóa
                $stmt = $pdo->prepare("SELECT file_path FROM product_images WHERE id = ? AND product_id = ?");
                $stmt->execute([$imgId, $productId]);
                $imgFile = $stmt->fetch();
                if ($imgFile) {
                    $physicalPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $imgFile['file_path']);
                    if (file_exists($physicalPath)) {
                        @unlink($physicalPath);
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
                    $stmt->execute([$imgId]);
                }
            }
        }
        
        // 5. Xử lý xóa toàn bộ ảnh cũ nếu checkbox chọn
        $deleteAllImages = isset($_POST['delete_all_images']) && $_POST['delete_all_images'] == '1';
        if ($deleteAllImages) {
            $stmt = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
            $stmt->execute([$productId]);
            
            if (is_dir($uploadDir)) {
                $files = glob($uploadDir . '*');
                if ($files) {
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            @unlink($file);
                        }
                    }
                }
            }
            
            $stmt = $pdo->prepare("UPDATE products SET image_path = NULL WHERE id = ?");
            $stmt->execute([$productId]);
        }
        
        // 6. Xử lý tải ảnh mới lên
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $images = $_FILES['images'];
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            
            // Tìm vị trí tối đa hiện tại để chèn tiếp
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(position), 0) as max_pos FROM product_images WHERE product_id = ?");
            $stmt->execute([$productId]);
            $currentMaxPos = (int)$stmt->fetch()['max_pos'];
            
            $firstUploadedPath = null;
            
            for ($i = 0; $i < count($images['name']); $i++) {
                if ($images['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($images['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, $allowedExts, true) && $images['size'][$i] <= $maxSize) {
                        $nextPos = $currentMaxPos + $i + 1;
                        $fileName = $productId . '_' . time() . '_' . $nextPos . '.' . $ext;
                        $filePath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($images['tmp_name'][$i], $filePath)) {
                            $stmt = $pdo->prepare("INSERT INTO product_images (product_id, file_path, position) VALUES (?, ?, ?)");
                            $stmt->execute([$productId, $filePath, $nextPos]);
                            
                            if ($firstUploadedPath === null) {
                                $firstUploadedPath = $filePath;
                            }
                        }
                    }
                }
            }
            
            // Cập nhật ảnh đại diện nếu ban đầu chưa có hoặc vừa bị xóa
            if ($firstUploadedPath !== null) {
                $stmt = $pdo->prepare("SELECT image_path FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                $currPath = $stmt->fetch()['image_path'] ?? null;
                if ($currPath === null || $deleteAllImages) {
                    $stmt = $pdo->prepare("UPDATE products SET image_path = ? WHERE id = ?");
                    $stmt->execute([$firstUploadedPath, $productId]);
                }
            }
        }
        
        // Nếu ảnh đại diện trống nhưng vẫn còn ảnh trong product_images, cập nhật lại ảnh đại diện bằng ảnh đầu tiên
        $stmt = $pdo->prepare("SELECT image_path FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $pInfo = $stmt->fetch();
        if (empty($pInfo['image_path'])) {
            $stmt = $pdo->prepare("SELECT file_path FROM product_images WHERE product_id = ? ORDER BY position ASC LIMIT 1");
            $stmt->execute([$productId]);
            $firstImg = $stmt->fetch();
            if ($firstImg) {
                $stmt = $pdo->prepare("UPDATE products SET image_path = ? WHERE id = ?");
                $stmt->execute([$firstImg['file_path'], $productId]);
            }
        }
        
        $pdo->commit();
        $successMessage = "Đã cập nhật thông tin hàng hóa thành công!";
        
        // Load lại thông tin sau khi cập nhật
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $p = $stmt->fetch();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Product update failed: ' . $e->getMessage());
        $errorMessage = $e instanceof PDOException ? 'Chưa thể lưu hàng hóa. Vui lòng thử lại.' : $e->getMessage();
    }
}

// Lấy danh sách ảnh hiện tại của sản phẩm
$stmt = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY position ASC, id ASC');
$stmt->execute([$productId]);
$images = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <base href="../">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh Sửa Hàng Hóa - Admin</title>
    <link rel="stylesheet" href="assets/css/dashboard/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f6f6f9 0%, #cbc7de 100%);
            min-height: 100vh;
        }
        
        .main-content h2 {
            color: #36314b;
            font-weight: 700;
            margin-bottom: 25px;
            font-size: 26px;
        }

        .section {
            background: white;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            border: 1px solid #e4e3eb;
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, #8a78d8 0%, #764ba2 100%);
            color: white;
            padding: 16px 20px;
            margin: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: white;
        }

        .section-content {
            padding: 24px;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .form-group {
            flex: 1;
            min-width: 240px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #36314b;
            font-size: 14px;
        }

        .form-group label span {
            color: #dc3545;
        }

        .form-group input, 
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e4e3eb;
            border-radius: 8px;
            font-size: 14px;
            background: #fbfafc;
            color: #36314b;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-group input:focus, 
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #5339c6;
            background: white;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .form-group textarea {
            height: 90px;
            resize: vertical;
        }

        .alert {
            padding: 14px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            border-left: 4px solid;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: #6c757d;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            background: #5a6268;
            transform: translateX(-2px);
        }

        .btn-submit {
            padding: 12px 24px;
            background: linear-gradient(135deg, #5339c6 0%, #3a288b 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #3a288b 0%, #2b1e67 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        /* Image Grid & Upload styles */
        .image-gallery-section {
            border: 1px solid #e4e3eb;
            border-radius: 8px;
            padding: 16px;
            background: #fbfafc;
            margin-top: 10px;
        }

        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .image-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            padding: 8px;
            text-align: center;
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .image-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .image-card img {
            width: 100%;
            aspect-ratio: 1/1;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #eee;
        }

        .image-card .delete-btn {
            display: block;
            margin-top: 8px;
            font-size: 12px;
            color: #dc3545;
            cursor: pointer;
            font-weight: 600;
        }

        .image-card .delete-checkbox {
            margin-right: 4px;
            cursor: pointer;
        }

        .upload-area {
            border: 2px dashed #e0dfe5;
            border-radius: 8px;
            padding: 24px;
            text-align: center;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-area:hover {
            border-color: #5339c6;
            background: #f9f8fa;
        }

        .upload-icon {
            font-size: 28px;
            color: #6c757d;
            margin-bottom: 8px;
        }

        .upload-text {
            color: #4d4b55;
            font-size: 14px;
            font-weight: 600;
        }
        
        .upload-subtext {
            color: #6c757d;
            font-size: 12px;
            margin-top: 4px;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            font-size: 14px;
            font-weight: 600;
            color: #dc3545;
        }

        .checkbox-container input {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
    </style>
    <link rel="stylesheet" href="assets/css/shared/icons.css">
    <link rel="stylesheet" href="assets/css/shared/theme.css">
</head>
<body class="migrated-page">
<a class="skip-link" href="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? basename(__DIR__) . '/' . basename(__FILE__), ENT_QUOTES, 'UTF-8') ?>#main-content">Đến nội dung chính</a>
<?php $shellTitle = 'Chỉnh sửa hàng hóa'; $shellActive = 'products/index.php'; require __DIR__ . '/../../app/views/shell-start.php'; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Chỉnh Sửa Hàng Hóa</h2>
                <a href="javascript:history.back()" class="btn-back"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#arrow-left"></use></svg> Quay lại</a>
            </div>

            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#check-circle"></use></svg> <?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>

            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#error"></use></svg> <?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <div class="section">
                <div class="section-header">
                    <h3>Thông tin sản phẩm ID: #<?php echo $p['id']; ?></h3>
                </div>
                
                <div class="section-content">
                    <form method="POST" enctype="multipart/form-data" id="edit-form">
                        <?php echo csrfTokenField(); ?>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="ten_san_pham">Tên hàng hóa: <span>*</span></label>
                                <input type="text" id="ten_san_pham" name="ten_san_pham" value="<?php echo htmlspecialchars($p['ten_san_pham']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="loai">Nhóm hàng / Loại: <span>*</span></label>
                                <select id="loai" name="loai" required>
                                    <option value="Công cụ dụng cụ" <?php echo $p['loai'] === 'Công cụ dụng cụ' ? 'selected' : ''; ?>>Công cụ dụng cụ</option>
                                    <option value="Vật tư" <?php echo $p['loai'] === 'Vật tư' ? 'selected' : ''; ?>>Vật tư</option>
                                    <option value="Tài sản cố định" <?php echo $p['loai'] === 'Tài sản cố định' ? 'selected' : ''; ?>>Tài sản cố định</option>
                                    <option value="Phụ tùng thay thế" <?php echo $p['loai'] === 'Phụ tùng thay thế' ? 'selected' : ''; ?>>Phụ tùng thay thế</option>
                                    <option value="Khác" <?php echo $p['loai'] === 'Khác' ? 'selected' : ''; ?>>Khác</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="don_vi">Đơn vị tính: <span>*</span></label>
                                <input type="text" id="don_vi" name="don_vi" value="<?php echo htmlspecialchars($p['don_vi']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="ngay_nhap">Ngày nhập: <span>*</span></label>
                                <input type="text" id="ngay_nhap" name="ngay_nhap" class="date-picker" placeholder="dd/mm/yyyy" value="<?php echo htmlspecialchars($p['ngay_nhap']); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="so_luong_nhap">Số lượng nhập:</label>
                                <input type="number" id="so_luong_nhap" name="so_luong_nhap" value="<?php echo (int)$p['so_luong_nhap']; ?>" min="0">
                            </div>
                            
                            <div class="form-group">
                                <label for="don_gia">Đơn giá (VNĐ):</label>
                                <input type="text" id="don_gia" name="don_gia" value="<?php echo htmlspecialchars(formatVnAmount($p['don_gia'])); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="so_luong_con_lai">Số lượng còn lại:</label>
                                <input type="number" id="so_luong_con_lai" name="so_luong_con_lai" value="<?php echo (int)$p['so_luong_con_lai']; ?>" min="0">
                            </div>
                            
                            <div class="form-group">
                                <label for="serial">Số Serial:</label>
                                <input type="text" id="serial" name="serial" value="<?php echo htmlspecialchars($p['serial'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 100%;">
                                <label for="ghi_chu">Ghi chú:</label>
                                <textarea id="ghi_chu" name="ghi_chu"><?php echo htmlspecialchars($p['ghi_chu'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <!-- Quản lý hình ảnh -->
                        <div class="form-row" style="margin-top: 15px;">
                            <div class="form-group" style="flex: 100%;">
                                <label>Hình ảnh sản phẩm:</label>
                                <div class="image-gallery-section">
                                    <?php if (!empty($images)): ?>
                                        <div class="image-grid">
                                            <?php foreach ($images as $img): ?>
                                                <div class="image-card">
                                                    <img src="<?php echo htmlspecialchars($img['file_path']); ?>?t=<?php echo time(); ?>" alt="Ảnh sản phẩm">
                                                    <label class="delete-btn">
                                                        <input type="checkbox" name="delete_image_ids[]" value="<?php echo $img['id']; ?>" class="delete-checkbox"> Xóa ảnh này
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p style="color:#6c757d; font-style:italic; margin-top:0; margin-bottom:15px;">Sản phẩm này chưa có hình ảnh nào.</p>
                                    <?php endif; ?>

                                    <div class="upload-area" onclick="document.getElementById('image-upload').click()">
                                        <div class="upload-icon"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#upload"></use></svg></div>
                                        <div class="upload-text">Chọn hoặc kéo thả các ảnh mới tại đây</div>
                                        <div class="upload-subtext">Hỗ trợ JPG, JPEG, PNG, GIF dưới 5MB. Có thể chọn nhiều tệp.</div>
                                        <input type="file" id="image-upload" name="images[]" multiple accept="image/*" style="display:none" onchange="updateFileCountLabel(this)">
                                        <div id="file-count-label" style="margin-top:8px; font-weight:600; color:#5339c6; display:none;"></div>
                                    </div>
                                    
                                    <?php if (!empty($images)): ?>
                                        <label class="checkbox-container">
                                            <input type="checkbox" name="delete_all_images" value="1"> <svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#warning"></use></svg> Xóa toàn bộ ảnh cũ trước khi lưu ảnh mới
                                        </label>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div style="text-align: right; margin-top: 25px;">
                            <button type="submit" class="btn-submit"><svg class="ui-icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#save"></use></svg> Lưu Thay Đổi</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/vn.js"></script>
    <script>
        // Khởi tạo Flatpickr tiếng Việt cho ô Ngày nhập
        (function(){
            if (window.flatpickr) {
                flatpickr('.date-picker', {
                    locale: flatpickr.l10ns.vn,
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'd/m/Y',
                    allowInput: true
                });
            }
        })();

        // Cập nhật nhãn đếm số lượng tệp đã chọn
        function updateFileCountLabel(input) {
            const label = document.getElementById('file-count-label');
            if (input.files && input.files.length > 0) {
                label.textContent = `Đã chọn ${input.files.length} ảnh mới chuẩn bị tải lên.`;
                label.style.display = 'block';
            } else {
                label.style.display = 'none';
            }
        }

        // Tự động định dạng tiền tệ VNĐ cho ô nhập đơn giá
        const donGiaInput = document.getElementById('don_gia');
        
        function parseCurrencyValue(value) {
            if (value === undefined || value === null) return 0;
            let s = String(value).trim().replace(/\u00A0/g, '').replace(/\s/g, '');
            if (!s) return 0;
            if (/^\d{1,3}(,\d{3})+$/.test(s)) {
                return parseFloat(s.replace(/,/g, '')) || 0;
            }
            if (/^\d{1,3}(\.\d{3})+$/.test(s)) {
                return parseFloat(s.replace(/\./g, '')) || 0;
            }
            if (/^\d+\.\d{1,6}$/.test(s) && (s.match(/\./g) || []).length === 1) {
                return parseFloat(s) || 0;
            }
            const m = s.match(/^(.+),(\d{1,3})$/);
            if (m) {
                const intPart = m[1].replace(/\./g, '');
                const d = m[2].length;
                return (parseInt(intPart, 10) || 0) + (parseInt(m[2], 10) || 0) / Math.pow(10, d);
            }
            const digitsOnly = s.replace(/\./g, '').replace(/,/g, '');
            return parseFloat(digitsOnly) || 0;
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

        donGiaInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^\d.,]/g, '').replace(/,(?=.*,)/g, '');
        });

        donGiaInput.addEventListener('blur', function() {
            const v = parseCurrencyValue(this.value);
            if (v !== 0 || this.value.trim() !== '') {
                this.value = formatNumber(v);
            }
        });
    </script>
<script src="assets/js/shared/theme.js" defer></script>
</body>
</html>
