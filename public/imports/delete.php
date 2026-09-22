<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Thủ kho')) {
    header('Location: ../auth/sign-in.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../imports/index.php');
    exit;
}

$billId = isset($_POST['bill_id']) ? (int)$_POST['bill_id'] : 0;
if ($billId <= 0) {
    header('Location: ../imports/index.php');
    exit;
}

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

    // Lấy danh sách product_id thuộc hóa đơn này để xóa ảnh
    $stmt = $pdo->prepare('SELECT product_id FROM import_bill_details WHERE import_bill_id = ?');
    $stmt->execute([$billId]);
    $productIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Xóa chi tiết hóa đơn
    $stmt = $pdo->prepare('DELETE FROM import_bill_details WHERE import_bill_id = ?');
    $stmt->execute([$billId]);

    if (!empty($productIds)) {
        // Xóa file ảnh trên đĩa và bản ghi product_images
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

        // Xóa sản phẩm
        $in = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $pdo->prepare("DELETE FROM products WHERE id IN ($in)");
        $stmt->execute($productIds);
    }

    // Xóa hóa đơn
    $stmt = $pdo->prepare('DELETE FROM import_bill WHERE id = ?');
    $stmt->execute([$billId]);

    $pdo->commit();
    header('Location: ../imports/index.php?deleted=1');
    exit;
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log("Delete invoice error: " . $e->getMessage());
    header('Location: ../imports/index.php?deleted=0');
    exit;
}
?>


