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
$template = __DIR__ . '/../../resources/excel/replacement-parts.xlsx';
if (!file_exists($template)) { 
    die('Không tìm thấy template: ' . $template); 
}

// Lấy tháng/năm từ form - hỗ trợ xuất theo khoảng thời gian
$fromMonth = isset($_POST['from_month']) ? intval($_POST['from_month']) : intval(date('n'));
$fromYear = isset($_POST['from_year']) ? intval($_POST['from_year']) : intval(date('Y'));
$toMonth = isset($_POST['to_month']) ? intval($_POST['to_month']) : intval(date('n'));
$toYear = isset($_POST['to_year']) ? intval($_POST['to_year']) : intval(date('Y'));

// Chuẩn hóa giá trị
if ($fromMonth < 1 || $fromMonth > 12) { $fromMonth = intval(date('n')); }
if ($fromYear < 2000 || $fromYear > 2100) { $fromYear = intval(date('Y')); }
if ($toMonth < 1 || $toMonth > 12) { $toMonth = intval(date('n')); }
if ($toYear < 2000 || $toYear > 2100) { $toYear = intval(date('Y')); }

// Đảm bảo from <= to
if ($fromYear > $toYear || ($fromYear == $toYear && $fromMonth > $toMonth)) {
    $fromMonth = $toMonth;
    $fromYear = $toYear;
}

// Mốc thời gian phục vụ truy vấn - tính từ đầu tháng đầu đến cuối tháng cuối
$periodStart = sprintf('%04d-%02d-01', $fromYear, $fromMonth);
$nextMonth = $toMonth + 1;
$nextYear = $toYear;
if ($nextMonth === 13) { $nextMonth = 1; $nextYear = $toYear + 1; }
$periodEnd = sprintf('%04d-%02d-01', $nextYear, $nextMonth);

$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($template);
$sheet = $spreadsheet->getActiveSheet();

// Cập nhật tiêu đề A5 thành 3 dòng với khoảng thời gian được chọn
if ($fromMonth == $toMonth && $fromYear == $toYear) {
    $titleA5 = "SỔ THEO DÕI PHỤ TÙNG THAY THẾ\n(Phục vụ hoạt động cung cấp dịch vụ sự nghiệp công TTDH)\nTháng " . $fromMonth . " năm " . $fromYear;
} else {
    $titleA5 = "SỔ THEO DÕI PHỤ TÙNG THAY THẾ\n(Phục vụ hoạt động cung cấp dịch vụ sự nghiệp công TTDH)\nTừ tháng " . $fromMonth . "/" . $fromYear . " đến tháng " . $toMonth . "/" . $toYear;
}
$sheet->setCellValue('A5', $titleA5);
// Merge & Center A5:F5
$sheet->mergeCells('A5:F5');
$sheet->getStyle('A5')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A5')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

// Lấy danh sách phụ tùng thay thế theo tên (gộp cùng tên)
$stmt = $pdo->prepare("
    SELECT DISTINCT ten_san_pham
    FROM products 
    WHERE loai = 'Phụ tùng thay thế' 
    ORDER BY ten_san_pham ASC
");
$stmt->execute();
$productNames = $stmt->fetchAll();

// Vị trí bắt đầu bảng trong template (giống vat_tu)
$startRow = 8;
$numItems = count($productNames);
if ($numItems > 1) {
    $sheet->insertNewRowBefore($startRow + 1, $numItems - 1);
}

// Đổ dữ liệu theo cấu trúc giống vật tư: A=STT, B=Tên, C=Tồn đầu kỳ, D=Nhập trong kỳ, E=Xuất trong kỳ, F=Tồn cuối kỳ
for ($i = 0; $i < $numItems; $i++) {
    $rowIndex = $startRow + $i;
    $name = $productNames[$i]['ten_san_pham'];

    // STT
    $sheet->setCellValue('A' . $rowIndex, $i + 1);
    // Tên
    $sheet->setCellValue('B' . $rowIndex, $name);

    // Tồn đầu kỳ = Nhập lũy kế đến trước khoảng thời gian được chọn - Xuất lũy kế đến trước khoảng thời gian được chọn
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ibd.so_luong_nhap), 0) FROM import_bill_details ibd
        JOIN import_bill ib ON ibd.import_bill_id = ib.id
        JOIN products p ON p.id = ibd.product_id
        WHERE p.ten_san_pham = ? AND ib.ngay_nhap < ?
    ");
    $stmt->execute([$name, $periodStart]);
    $nhapDenCuoiThangTruoc = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ebd.so_luong_xuat), 0) FROM export_bill_details ebd
        JOIN export_bill eb ON ebd.export_bill_id = eb.id
        JOIN products p ON p.id = ebd.product_id
        WHERE p.ten_san_pham = ? AND eb.ngay_nhan < ?
    ");
    $stmt->execute([$name, $periodStart]);
    $xuatDenCuoiThangTruoc = $stmt->fetchColumn();
    $tonDauKy = $nhapDenCuoiThangTruoc - $xuatDenCuoiThangTruoc;
    $sheet->setCellValue('C' . $rowIndex, $tonDauKy);

    // Nhập trong khoảng thời gian được chọn
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ibd.so_luong_nhap), 0)
        FROM import_bill_details ibd
        JOIN import_bill ib ON ibd.import_bill_id = ib.id
        JOIN products p ON p.id = ibd.product_id
        WHERE p.ten_san_pham = ? AND ib.ngay_nhap >= ? AND ib.ngay_nhap < ?
    ");
    $stmt->execute([$name, $periodStart, $periodEnd]);
    $sheet->setCellValue('D' . $rowIndex, $stmt->fetchColumn());

    // Xuất trong khoảng thời gian được chọn
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ebd.so_luong_xuat), 0)
        FROM export_bill_details ebd
        JOIN export_bill eb ON ebd.export_bill_id = eb.id
        JOIN products p ON p.id = ebd.product_id
        WHERE p.ten_san_pham = ? AND eb.ngay_nhan >= ? AND eb.ngay_nhan < ?
    ");
    $stmt->execute([$name, $periodStart, $periodEnd]);
    $sheet->setCellValue('E' . $rowIndex, $stmt->fetchColumn());

    // Tồn cuối kỳ = Nhập lũy kế đến cuối khoảng thời gian được chọn - Xuất lũy kế đến cuối khoảng thời gian được chọn
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ibd.so_luong_nhap), 0)
        FROM import_bill_details ibd
        JOIN import_bill ib ON ibd.import_bill_id = ib.id
        JOIN products p ON p.id = ibd.product_id
        WHERE p.ten_san_pham = ? AND ib.ngay_nhap < ?
    ");
    $stmt->execute([$name, $periodEnd]);
    $nhapDenCuoiThang = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ebd.so_luong_xuat), 0)
        FROM export_bill_details ebd
        JOIN export_bill eb ON ebd.export_bill_id = eb.id
        JOIN products p ON p.id = ebd.product_id
        WHERE p.ten_san_pham = ? AND eb.ngay_nhan < ?
    ");
    $stmt->execute([$name, $periodEnd]);
    $xuatDenCuoiThang = $stmt->fetchColumn();
    $sheet->setCellValue('F' . $rowIndex, $nhapDenCuoiThang - $xuatDenCuoiThang);
}

// Dòng tổng cộng
if ($numItems === 0) {
    $totalRow = $startRow;
    $sheet->setCellValue('A' . $totalRow, 'Tổng cộng');
    $sheet->mergeCells('A' . $totalRow . ':B' . $totalRow);
    $sheet->setCellValue('C' . $totalRow, 0);
    $sheet->setCellValue('D' . $totalRow, 0);
    $sheet->setCellValue('E' . $totalRow, 0);
    $sheet->setCellValue('F' . $totalRow, 0);
} else {
    $totalRow = $startRow + $numItems;
    $sheet->setCellValue('A' . $totalRow, 'Tổng cộng');
    $sheet->mergeCells('A' . $totalRow . ':B' . $totalRow);
    $sheet->setCellValue('C' . $totalRow, '=SUM(C' . $startRow . ':C' . ($totalRow - 1) . ')');
    $sheet->setCellValue('D' . $totalRow, '=SUM(D' . $startRow . ':D' . ($totalRow - 1) . ')');
    $sheet->setCellValue('E' . $totalRow, '=SUM(E' . $startRow . ':E' . ($totalRow - 1) . ')');
    $sheet->setCellValue('F' . $totalRow, '=SUM(F' . $startRow . ':F' . ($totalRow - 1) . ')');
}

// Ngày tháng năm (cột D, cách bảng 2 dòng giống vật tư)
$dateRow = $totalRow + 2;
$sheet->setCellValue('D' . $dateRow, 'Ngày ' . date('d') . ' tháng ' . date('m') . ' năm ' . date('Y'));

// Xuất file
if ($fromMonth == $toMonth && $fromYear == $toYear) {
    $exportFilename = 'So_Phu_Tung_Thay_The_Thang_' . $fromMonth . '_' . date('Y-m-d_H-i-s') . '.xlsx';
} else {
    $exportFilename = 'So_Phu_Tung_Thay_The_Tu_' . $fromMonth . '_' . $fromYear . '_Den_' . $toMonth . '_' . $toYear . '_' . date('Y-m-d_H-i-s') . '.xlsx';
}
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
