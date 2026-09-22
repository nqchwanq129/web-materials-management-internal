<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/money_parse.php';

// Kiểm tra quyền truy cập
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Thủ kho')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

// Kiểm tra method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method không được hỗ trợ']);
    exit;
}

// Lấy dữ liệu JSON từ request
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
    exit;
}

try {
    // Bắt đầu transaction
    $pdo->beginTransaction();
    
    // 1. Kiểm tra số hóa đơn đã tồn tại chưa
    $checkStmt = $pdo->prepare("SELECT id FROM import_bill WHERE so_hoa_don = ?");
    $checkStmt->execute([$input['invoiceNumber']]);
    $existingBill = $checkStmt->fetch();
    
    if ($existingBill) {
        throw new Exception("Số hóa đơn '{$input['invoiceNumber']}' đã tồn tại trong hệ thống. Vui lòng sử dụng số hóa đơn khác.");
    }
    
    // 2. Lưu thông tin vào bảng import_bill
    $stmt = $pdo->prepare("
        INSERT INTO import_bill (
            so_hoa_don, 
            serial, 
            nha_cung_cap, 
            nguoi_nhan_hang,
            nhap_vao_don_vi, 
            ngay_nhap, 
            tong_tien, 
            so_luong_mat_hang
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    // Xử lý ngày nhập từ form
    $importDate = $input['importDate'] ?? date('Y-m-d');
    
    // Chuyển đổi format ngày từ dd/mm/yyyy (ưu tiên) hoặc yyyy-mm-dd sang yyyy-mm-dd
    if ($importDate && $importDate !== date('Y-m-d')) {
        // Thử parse các format khác nhau
        $formats = ['d/m/Y', 'Y-m-d'];
        $parsedDate = null;
        
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $importDate);
            if ($dt && $dt->format($format) === $importDate) {
                $parsedDate = $dt->format('Y-m-d');
                break;
            }
        }
        
        if ($parsedDate) {
            $importDate = $parsedDate;
        }
    }
    
    $tongTien = round(parseMoneyStringToFloat($input['totalAfterVAT'] ?? 0), 3);

    $stmt->execute([
        $input['invoiceNumber'],
        $input['serial'],
        $input['supplier'],
        $input['receiverName'] ?? '',
        $input['importUnit'],
        $importDate, // Sử dụng ngày từ form thay vì CURDATE()
        $tongTien,
        $input['goodsCount']
    ]);
    
    $importBillId = $pdo->lastInsertId();
    
    // 3. Lưu từng hàng hóa vào bảng products
    foreach ($input['goodsList'] as $goods) {
        $priceBefore = round(parseMoneyStringToFloat($goods['priceBeforeVAT'] ?? 0), 3);
        $qty = (float)($goods['quantity'] ?? 0);
        $itemAmount = round($priceBefore * $qty, 3);
        $lineTotal = round(parseMoneyStringToFloat($goods['totalAmount'] ?? 0), 3);

        $stmt = $pdo->prepare("
            INSERT INTO products (
                ten_san_pham,
                don_vi,
                ngay_nhap,
                so_luong_nhap,
                don_gia,
                thanh_tien,
                so_luong_da_xuat,
                so_luong_con_lai,
                loai,
                ghi_chu,
                serial
            ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $goods['name'],
            $goods['unit'],
            $importDate, // Sử dụng ngày từ form thay vì CURDATE()
            $goods['quantity'],
            $priceBefore,
            $itemAmount,
            $goods['quantity'], // Số lượng còn lại = số lượng nhập (chưa xuất)
            $goods['type'],
            $goods['ghiChu'],
            $goods['serial']
        ]);
        
        $productId = $pdo->lastInsertId();
        
        // 4. Lưu chi tiết vào bảng import_bill_details (nếu có)
        $stmt = $pdo->prepare("
            INSERT INTO import_bill_details (
                import_bill_id,
                product_id,
                so_luong_nhap,
                don_gia,
                thanh_tien
            ) VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $importBillId,
            $productId,
            $goods['quantity'],
            $priceBefore,
            $lineTotal
        ]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Đã lưu thông tin nhập hàng thành công',
        'import_bill_id' => $importBillId
    ]);
    
} catch (PDOException $e) {
    // Rollback nếu có lỗi
    $pdo->rollBack();
    
    echo json_encode([
        'success' => false, 
        'message' => 'Lỗi database: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // Rollback nếu có lỗi
    $pdo->rollBack();
    
    echo json_encode([
        'success' => false, 
        'message' => 'Lỗi: ' . $e->getMessage()
    ]);
}
?>
