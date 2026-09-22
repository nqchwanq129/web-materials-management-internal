<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';

header('Content-Type: application/json');

// Kiểm tra quyền truy cập cơ bản
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

$importBillId = isset($_POST['import_bill_id']) ? (int)$_POST['import_bill_id'] : 0;
if ($importBillId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Thiếu import_bill_id']);
    exit;
}

// Nếu không có ảnh, coi như không cần upload → trả về success để không chặn luồng
if (!isset($_FILES['images']) || (is_array($_FILES['images']['name']) && count(array_filter((array)$_FILES['images']['name'])) === 0)) {
    echo json_encode(['success' => true, 'message' => 'Không có ảnh nào để upload']);
    exit;
}

$images = $_FILES['images'];
$allowedExts = ['jpg','jpeg','png','gif'];
$maxSize = 5 * 1024 * 1024; // 5MB

// Thư mục lưu ảnh theo hóa đơn
$uploadDir = 'uploads/imports/' . $importBillId . '/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$saved = 0;
$rowIndices = isset($_POST['row_indices']) ? $_POST['row_indices'] : [];

// Lấy danh sách sản phẩm của hóa đơn này để map với row indices
$stmt = $pdo->prepare("
    SELECT p.id 
    FROM products p 
    INNER JOIN import_bill_details ibd ON p.id = ibd.product_id 
    WHERE ibd.import_bill_id = ? 
    ORDER BY ibd.id ASC
");
$stmt->execute([$importBillId]);
$productIds = array_column($stmt->fetchAll(), 'id');

for ($i = 0; $i < count($images['name']); $i++) {
    if ($images['error'][$i] !== UPLOAD_ERR_OK) {
        continue;
    }
    $ext = strtolower(pathinfo($images['name'][$i], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        continue;
    }
    if ($images['size'][$i] > $maxSize) {
        continue;
    }
    
    $fileName = 'img_' . time() . '_' . ($i + 1) . '.' . $ext;
    $filePath = $uploadDir . $fileName;
    
    if (move_uploaded_file($images['tmp_name'][$i], $filePath)) {
        // Lưu vào database
        $rowIndex = isset($rowIndices[$i]) ? (int)$rowIndices[$i] : 0;
        $productId = isset($productIds[$rowIndex]) ? $productIds[$rowIndex] : null;
        
        if ($productId) {
            // Đếm số ảnh hiện tại của sản phẩm để set position
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM product_images WHERE product_id = ? AND import_bill_id = ?");
            $stmt->execute([$productId, $importBillId]);
            $position = $stmt->fetch()['count'] + 1;
            
            // Lưu vào bảng product_images
            $stmt = $pdo->prepare("INSERT INTO product_images (product_id, file_path, position, import_bill_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$productId, $filePath, $position, $importBillId]);
        }
        
        $saved++;
    }
}

echo json_encode(['success' => true, 'message' => 'Đã upload ' . $saved . ' ảnh']);
?>


