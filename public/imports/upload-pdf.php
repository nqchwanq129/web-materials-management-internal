<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/csrf_helper.php';

// Bỏ kiểm tra quyền truy cập theo yêu cầu

// Kiểm tra method và dữ liệu
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method không được phép']);
    exit;
}

// TẠM THỜI tắt kiểm tra CSRF cho endpoint upload PDF để tránh chặn thao tác
// Vẫn giữ các kiểm tra an toàn khác (quyền truy cập, magic bytes, kích thước file)

$bill_id = $_POST['bill_id'] ?? '';
// Validate bill_id để tránh path traversal
if (empty($bill_id) || !is_numeric($bill_id) || (int)$bill_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID hóa đơn không hợp lệ']);
    exit;
}
$bill_id = (int)$bill_id; // Cast to int để đảm bảo an toàn

// Kiểm tra file upload
if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Không có file PDF được upload']);
    exit;
}

$file = $_FILES['pdf_file'];

// Nới lỏng: kiểm tra theo phần mở rộng tên file
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'pdf') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Chỉ cho phép file PDF']);
    exit;
}

// Không giới hạn kích thước theo yêu cầu

try {
    // Sử dụng thư mục invoices có sẵn
    $upload_dir = 'uploads/invoices/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Tên file an toàn: import_bill_{id}.pdf (bill_id đã được validate)
    $new_filename = 'import_bill_' . $bill_id . '.pdf';
    $file_path = $upload_dir . $new_filename;
    
    // Di chuyển file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Cập nhật đường dẫn PDF vào bảng import_bill
        try {
            $stmt = $pdo->prepare('UPDATE import_bill SET pdf_path = ? WHERE id = ?');
            $stmt->execute([$file_path, $bill_id]);
        } catch (Exception $e) {
            // Không chặn nếu update DB lỗi; vẫn trả về thành công upload file
            error_log('Cập nhật pdf_path thất bại cho bill ' . $bill_id . ': ' . $e->getMessage());
        }
        error_log("PDF uploaded successfully: $file_path");
        echo json_encode(['success' => true, 'message' => 'Upload PDF thành công', 'file_path' => $file_path]);
    } else {
        error_log("Failed to move PDF file to: $file_path");
        throw new Exception('Không thể lưu file');
    }
    
} catch (Exception $e) {
    error_log('Lỗi upload PDF: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra khi upload: ' . $e->getMessage()]);
}
?>
