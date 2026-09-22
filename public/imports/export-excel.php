<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/money_parse.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\DataType;

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Thủ kho')) {
	header('Location: ../auth/sign-in.php');
	exit;
}

// Lấy ID phiếu nhập
$import_bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($import_bill_id <= 0) {
	die('ID phiếu nhập không hợp lệ!');
}

// Lấy thông tin phiếu nhập
$stmt = $pdo->prepare('SELECT so_hoa_don, nhap_vao_don_vi, ngay_nhap, nguoi_nhan_hang, nha_cung_cap FROM import_bill WHERE id = ?');
$stmt->execute([$import_bill_id]);
$import_bill = $stmt->fetch();
if (!$import_bill) {
	die('Không tìm thấy phiếu nhập!');
}

// Lấy chi tiết hàng hóa thuộc phiếu nhập (kèm ghi chú)
$stmt = $pdo->prepare('
	SELECT p.ten_san_pham, p.don_vi, p.serial, p.ghi_chu, ibd.so_luong_nhap, ibd.don_gia, ibd.thanh_tien
	FROM import_bill_details ibd
	JOIN products p ON ibd.product_id = p.id
	WHERE ibd.import_bill_id = ?
	ORDER BY ibd.id
');
$stmt->execute([$import_bill_id]);
$items = $stmt->fetchAll();

// Tên file tải về
$export_filename = 'Phieu_nhap_kho_' . $import_bill_id . '_' . date('Y-m-d') . '.xlsx';

// Xóa output buffer
while (ob_get_level()) ob_end_clean();

// Template
$template = __DIR__ . '/../../resources/excel/import-receipt.xlsx';
if (!file_exists($template)) {
	die('Không tìm thấy template: ' . $template);
}

$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($template);
$sheet = $spreadsheet->getActiveSheet();

// 1) Tiêu đề C1: PHIẾU NHẬP KHO + dòng ngày theo ngày nhập kho và merge C1:D2
$day = date('d', strtotime($import_bill['ngay_nhap']));
$month = date('m', strtotime($import_bill['ngay_nhap']));
$year = date('Y', strtotime($import_bill['ngay_nhap']));
$sheet->setCellValue('C1', 'PHIẾU NHẬP KHO' . "\n" . 'Ngày ' . $day . ' tháng ' . $month . ' năm ' . $year);
try { $sheet->mergeCells('C1:D2'); } catch (\Throwable $e) { /* đã merge sẵn */ }
$sheet->getStyle('C1:D2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('C1:D2')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
$sheet->getStyle('C1:D2')->getAlignment()->setWrapText(true);

// 2) A5: -  Theo: Hoá đơn số <so_hoa_don>
$sheet->setCellValue('A5', '-  Theo: Hoá đơn số ' . $import_bill['so_hoa_don']);

// 3) A4: -  Họ và tên người giao hàng: <nguoi_nhan_hang>
$sheet->setCellValue('A4', '-  Họ và tên người giao hàng: ' . ($import_bill['nguoi_nhan_hang'] ?? ''));

// 4) A6: -  Nhập tại kho (ngăn lô): <nhap_vao_don_vi>
$sheet->setCellValue('A6', '-  Nhập tại kho (ngăn lô): ' . $import_bill['nhap_vao_don_vi']);

// 4) Bảng dữ liệu: giống cách làm của phiếu xuất kho nhưng theo lưới của template nhập kho
$start_row = 9; // dòng bắt đầu ghi dữ liệu trong template nhập kho
$item_count = count($items);
if ($item_count > 1) {
	$sheet->insertNewRowBefore($start_row + 1, $item_count - 1);
}

$total_amount = 0;
foreach ($items as $index => $it) {
	$row = $start_row + $index;
	$so_luong_nhap = (int)round(parseMoneyStringToFloat($it['so_luong_nhap'] ?? 0));
	$don_gia = round(parseMoneyStringToFloat($it['don_gia'] ?? 0), 3);
	// STT
	$sheet->setCellValue('A' . $row, $index + 1);
	// Tên hàng hóa: merge B:C của dòng (template đã merge sẵn, nếu chưa thì vẫn set B)
	try { $sheet->mergeCells('B' . $row . ':C' . $row); } catch (\Throwable $e) { /* bỏ qua nếu đã merge */ }
	$sheet->setCellValue('B' . $row, $it['ten_san_pham']);
	// Mã số (cột D) để trống
	$sheet->setCellValue('D' . $row, '');
	// Đơn vị tính (E)
	$sheet->setCellValue('E' . $row, $it['don_vi']);
	// Số lượng yêu cầu (F)
	$sheet->setCellValue('F' . $row, $so_luong_nhap);
	// Thực nhập (G)
	$sheet->setCellValue('G' . $row, $so_luong_nhap);
	// Đơn giá (H)
	$sheet->setCellValueExplicit('H' . $row, formatVnAmount($don_gia, 3, true), DataType::TYPE_STRING);
	// Thành tiền (I) - số tiền chưa bao gồm VAT
	$thanh_tien_chua_vat = round($so_luong_nhap * $don_gia, 3);
	$sheet->setCellValueExplicit('I' . $row, formatVnAmount($thanh_tien_chua_vat, 3, true), DataType::TYPE_STRING);
	// Serial (J)
	$sheet->setCellValue('J' . $row, isset($it['serial']) ? $it['serial'] : '');
	// Ghi chú (K) từ sản phẩm
	$sheet->setCellValue('K' . $row, isset($it['ghi_chu']) ? $it['ghi_chu'] : '');

	$total_amount += (float)$thanh_tien_chua_vat;
}

// 5) Dòng Cộng (ô tổng tiền dưới bảng): giống phiếu xuất kho
$total_row = $start_row + $item_count; // dòng "Cộng"
$sheet->setCellValueExplicit('I' . $total_row, formatVnAmount($total_amount, 3, true), DataType::TYPE_STRING);

// 6) Tính tổng tiền đã bao gồm VAT (10%)
$total_amount_with_vat = $total_amount * 1.1;

// Căn phải cho cột Đơn giá (H) và Thành tiền (I)
$sheet->getStyle('H' . $start_row . ':I' . $total_row)
	->getAlignment()
	->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

// Chỉ căn trái cho cột Tên sản phẩm (B)
$sheet->getStyle('B' . $start_row . ':B' . ($total_row - 1))
	->getAlignment()
	->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

// 6) Tổng số tiền (viết bằng chữ) - dùng tổng THÀNH TIỀN (không VAT) giống phiếu xuất
require_once __DIR__ . '/../../app/helpers/number_to_words.php';
$amount_in_words = amountToVietnameseWordsFloat($total_amount);
$sheet->setCellValue('A' . ($total_row + 2), '- Tổng số tiền (viết bằng chữ): ' . $amount_in_words);

// 7) Số chứng từ gốc kèm theo: Hoá đơn số <so_hoa_don>
$sheet->setCellValue('A' . ($total_row + 3), '- Số chứng từ gốc kèm theo: Hoá đơn số ' . $import_bill['so_hoa_don']);

// 8) Ngày tháng năm 2 dòng ở cột G (giống phiếu xuất kho)
$date_line_row = $total_row + 5;
// Dùng đúng ngày nhập của phiếu cho dòng ngày dưới
$sheet->setCellValue('G' . $date_line_row, 'Ngày ' . $day . ' tháng ' . $month . ' năm ' . $year);

// 9) Thêm tên người nhập hàng vào ô C - dưới bảng 12 ô
$signature_row = $total_row + 13; // Dưới bảng 12 ô
$sheet->setCellValue('C' . $signature_row, $import_bill['nguoi_nhan_hang'] ?? '');
// Dòng dưới để trống/giữ nguyên chữ ký theo template

// Xuất file
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('xlsx_', true) . '.xlsx';
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save($tmp);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $export_filename . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: must-revalidate');
header('Pragma: public');

readfile($tmp);
@unlink($tmp);
exit;
?>
