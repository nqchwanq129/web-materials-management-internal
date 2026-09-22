<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/csrf_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Thủ kho')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method không được phép']);
    exit;
}

// Validate CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token không hợp lệ']);
    exit;
}

$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Thiếu product_id']);
    exit;
}

// Kiểm tra sản phẩm có tồn tại không
$stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
$stmt->execute([$productId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại']);
    exit;
}

// Nếu không có ảnh, coi như thành công
if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
    echo json_encode(['success' => true, 'message' => 'Không có ảnh nào để upload']);
    exit;
}

$images = $_FILES['images'];
$allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
$maxSize = 5 * 1024 * 1024; // 5MB

// Thư mục lưu ảnh cho sản phẩm
$uploadDir = 'uploads/products/' . $productId . '/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$saved = 0;
$errors = [];

try {
    $pdo->beginTransaction();
    
    // Xóa ảnh cũ của sản phẩm này
    $stmt = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
    $stmt->execute([$productId]);
    
    // Xóa file ảnh cũ
    $files = glob($uploadDir . '*');
    if ($files) {
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
    
    // Upload ảnh mới
    for ($i = 0; $i < count($images['name']); $i++) {
        if ($images['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = "Lỗi upload file " . ($i + 1);
            continue;
        }
        
        $ext = strtolower(pathinfo($images['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            $errors[] = "File " . ($i + 1) . " không đúng định dạng";
            continue;
        }
        
        if ($images['size'][$i] > $maxSize) {
            $errors[] = "File " . ($i + 1) . " quá lớn (>5MB)";
            continue;
        }
        
        $fileName = $productId . '_' . time() . '_' . ($i + 1) . '.' . $ext;
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($images['tmp_name'][$i], $filePath)) {
            // Lưu vào database
            $stmt = $pdo->prepare("INSERT INTO product_images (product_id, file_path, position) VALUES (?, ?, ?)");
            $stmt->execute([$productId, $filePath, $i + 1]);
            $saved++;
        } else {
            $errors[] = "Không thể lưu file " . ($i + 1);
        }
    }
    
    $pdo->commit();
    
    $message = "Đã upload " . $saved . " ảnh";
    if (!empty($errors)) {
        $message .= ". Lỗi: " . implode(', ', $errors);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'saved_count' => $saved,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
}
?>
