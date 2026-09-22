<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Thủ kho')) {
    header('Location: ../auth/sign-in.php');
    exit;
}

// Dọn output buffer để tránh hỏng file tải xuống
while (ob_get_level()) ob_end_clean();

// Template cố định
$template = __DIR__ . '/../../resources/excel/tools.xlsx';
if (!file_exists($template)) { die('Không tìm thấy template: ' . $template); }

// Lấy tham số lọc ngày
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Lấy dữ liệu CCDC với lọc ngày
$sql = "SELECT ten_san_pham, don_gia FROM products WHERE loai = 'Công cụ dụng cụ'";
$params = [];
if ($start_date) { $sql .= " AND ngay_nhap >= ?"; $params[] = $start_date; }
if ($end_date) { $sql .= " AND ngay_nhap <= ?"; $params[] = $end_date; }
$sql .= " ORDER BY ten_san_pham ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($template);
$sheet = $spreadsheet->getActiveSheet();

// Vị trí bắt đầu bảng trong template
$startRow = 12;
$numItems = max(1, count($rows));
if ($numItems > 1) {
    $sheet->insertNewRowBefore($startRow + 1, $numItems - 1);
}

// Đổ dữ liệu: A=STT, D=Tên CCDC, H=Nguyên giá
for ($i = 0; $i < $numItems; $i++) {
    $row = $startRow + $i;
    $sheet->setCellValue('A' . $row, $i + 1);
    $sheet->setCellValue('D' . $row, $rows[$i]['ten_san_pham'] ?? '');
    $sheet->setCellValue('H' . $row, $rows[$i]['don_gia'] ?? 0);
}

// Dòng Cộng
$totalRow = $startRow + $numItems;
$sheet->setCellValue('H' . $totalRow, '=SUM(H' . $startRow . ':H' . ($totalRow - 1) . ')');

// Ngày tháng năm (1 dòng), đặt sau phần bảng 5 dòng
$dateRow = $startRow + $numItems + 5;
$sheet->setCellValue('I' . $dateRow, 'Ngày ' . date('d') . ' tháng ' . date('m') . ' năm ' . date('Y'));

// Merge & center ô I20-K20
$sheet->mergeCells('I' . $dateRow . ':K' . $dateRow);
$sheet->getStyle('I' . $dateRow . ':K' . $dateRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);


// Xuất
$exportFilename = 'So_CCDC_' . date('Y-m-d_H-i-s') . '.xlsx';
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('xlsx_', true) . '.xlsx';
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save($tmp);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $exportFilename . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: max-age=0');
readfile($tmp);
@unlink($tmp);
exit;
?>
