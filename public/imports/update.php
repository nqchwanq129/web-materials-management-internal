<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/money_parse.php';
require_once __DIR__ . '/../../app/helpers/csrf_helper.php';

header('Content-Type: application/json; charset=utf-8');

// Chỉ cho phép Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Thủ kho')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['success' => false, 'message' => 'Method không được hỗ trợ']);
	exit;
}

// Validate CSRF token for AJAX requests
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'CSRF token không hợp lệ']);
	exit;
}

// Input already decoded above for CSRF validation

$billId       = isset($input['bill_id']) ? (int)$input['bill_id'] : 0;
$supplier     = trim($input['supplier'] ?? '');
$importUnit   = trim($input['import_unit'] ?? '');
$invoiceNum   = trim($input['invoice_number'] ?? '');
$serial       = trim($input['serial'] ?? '');
$importDate   = trim($input['import_date'] ?? '');
$totalAmount  = trim($input['total_amount'] ?? '0');

if ($billId <= 0) {
	echo json_encode(['success' => false, 'message' => 'Thiếu bill_id']);
	exit;
}

$totalAmount = round(parseMoneyStringToFloat($totalAmount), 3);

// Parse ngày: chấp nhận dd/mm/yyyy hoặc yyyy-mm-dd (loại bỏ mm/dd/yyyy)
$parsedDate = null;
if ($importDate !== '') {
	$formats = ['d/m/Y', 'Y-m-d'];
	foreach ($formats as $fmt) {
		$dt = DateTime::createFromFormat($fmt, $importDate);
		if ($dt && $dt->format($fmt) === $importDate) {
			$parsedDate = $dt->format('Y-m-d');
			break;
		}
	}
}

try {
	$stmt = $pdo->prepare('UPDATE import_bill SET nha_cung_cap = ?, nhap_vao_don_vi = ?, ngay_nhap = COALESCE(?, ngay_nhap), so_hoa_don = ?, serial = ?, tong_tien = ? WHERE id = ?');
	$stmt->execute([
		$supplier,
		$importUnit,
		$parsedDate,
		$invoiceNum,
		$serial,
		$totalAmount,
		$billId,
	]);

	echo json_encode(['success' => true]);
} catch (PDOException $e) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
}
?>


