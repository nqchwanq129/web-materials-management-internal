<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/csrf_helper.php';
header('Content-Type: application/json; charset=utf-8');

// Kiểm tra quyền truy cập
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Thủ kho')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

// Kiểm tra method và dữ liệu
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method không được phép']);
    exit;
}

if (!is_string($_POST['csrf_token'] ?? null) || !validateCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang rồi thử lại.']);
    exit;
}

$export_bill_id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (empty($export_bill_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID hóa đơn không hợp lệ']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Lấy thông tin chi tiết hóa đơn xuất
    $stmt = $pdo->prepare('SELECT product_id, so_luong_xuat FROM export_bill_details WHERE export_bill_id = ?');
    $stmt->execute([$export_bill_id]);
    $export_details = $stmt->fetchAll();
    
    if (empty($export_details)) {
        throw new Exception('Không tìm thấy chi tiết hóa đơn xuất');
    }
    
    // Cộng trả số lượng vào tồn kho cho từng sản phẩm
    foreach ($export_details as $detail) {
        $stmt = $pdo->prepare('UPDATE products SET so_luong_con_lai = so_luong_con_lai + ? WHERE id = ?');
        $stmt->execute([$detail['so_luong_xuat'], $detail['product_id']]);
    }
    
    // Xóa chi tiết hóa đơn xuất
    $stmt = $pdo->prepare('DELETE FROM export_bill_details WHERE export_bill_id = ?');
    $stmt->execute([$export_bill_id]);
    
    // Xóa hóa đơn xuất
    $stmt = $pdo->prepare('DELETE FROM export_bill WHERE id = ?');
    $stmt->execute([$export_bill_id]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Thu hồi hóa đơn xuất thành công']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Lỗi thu hồi hóa đơn xuất: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Chưa thể thu hồi phiếu xuất. Vui lòng thử lại.']);
}
?>
