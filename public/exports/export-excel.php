<?php
chdir(dirname(__DIR__));
session_start();
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/money_parse.php';
require_once __DIR__ . '/../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Cell\DataType;

// Kiểm tra quyền truy cập
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Thủ kho')) {
    header('Location: ../auth/sign-in.php');
    exit;
}

// Lấy ID phiếu xuất từ URL
$export_bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($export_bill_id <= 0) {
    die('ID phiếu xuất không hợp lệ!');
}

// Lấy thông tin phiếu xuất
$stmt = $pdo->prepare('SELECT nguoi_nhan, ten_kho_xuat, ly_do_xuat, ngay_nhan, so_hd_xuat FROM export_bill WHERE id = ?');
$stmt->execute([$export_bill_id]);
$export_bill = $stmt->fetch();

if (!$export_bill) {
    die('Không tìm thấy phiếu xuất!');
}

// Lấy danh sách hàng hóa xuất (giữ đúng thứ tự như phiếu nhập gốc)
$stmt = $pdo->prepare('
    SELECT p.ten_san_pham, p.don_vi, ebd.so_luong_xuat, ebd.don_gia, ebd.thanh_tien
    FROM export_bill_details ebd
    JOIN products p ON ebd.product_id = p.id
    LEFT JOIN import_bill_details ibd ON ibd.product_id = ebd.product_id
    WHERE ebd.export_bill_id = ?
    ORDER BY COALESCE(ibd.id, ebd.id) ASC
');
$stmt->execute([$export_bill_id]);
$export_details = $stmt->fetchAll();

// Tạo tên file xuất
$export_filename = 'Phieu_xuat_kho_' . $export_bill_id . '_' . date('Y-m-d') . '.xlsx';

// Số HĐ ưu tiên lấy từ phiếu xuất
$so_hd_override = trim($export_bill['so_hd_xuat'] ?? '');

// Xóa output buffer
while (ob_get_level()) ob_end_clean();

// Mở template Excel
$template = __DIR__ . '/../../resources/excel/export-receipt.xlsx';
if (!file_exists($template)) die('Không tìm thấy template');

$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($template);
$sheet = $spreadsheet->getActiveSheet();


// Lấy ngày xuất kho từ database
$ngay_xuat = date('d', strtotime($export_bill['ngay_nhan']));
$thang_xuat = date('m', strtotime($export_bill['ngay_nhan']));
$nam_xuat = date('Y', strtotime($export_bill['ngay_nhan']));

// Đặt nội dung vào merged cells với xuống dòng
$sheet->setCellValue('C1', 'PHIẾU XUẤT KHO' . "\n" . 'Ngày ' . $ngay_xuat . ' tháng ' . $thang_xuat . ' năm ' . $nam_xuat);

// Merge & Center ô C1-D1-C2-D2
$sheet->mergeCells('C1:D2');

// Căn giữa và xuống dòng
$sheet->getStyle('C1:D2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('C1:D2')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
$sheet->getStyle('C1:D2')->getAlignment()->setWrapText(true);

// Cập nhật các ô thông tin
$sheet->setCellValue('A4', '-  Họ và tên người nhận hàng: ' . $export_bill['nguoi_nhan']);
$sheet->setCellValue('A6', '-  Nhập tại kho (ngăn lô): ' . $export_bill['ten_kho_xuat']);
$sheet->setCellValue('A5', '-  Lý do xuất kho: ' . $export_bill['ly_do_xuat']);

// CHỈ XỬ LÝ BẢNG - TỰ ĐỘNG THÊM HÀNG THEO SỐ LƯỢNG HÀNG HÓA
$start_data_row = 10; // Bắt đầu từ dòng 10
$num_items = count($export_details);

if ($num_items > 1) {
    // Nếu có nhiều hơn 1 hàng hóa, thêm hàng mới
    $rows_to_add = $num_items - 1;
    $sheet->insertNewRowBefore($start_data_row + 1, $rows_to_add);
}

// ĐIỀN THÔNG TIN VÀO BẢNG VÀ MERGE CELLS CHO TẤT CẢ DÒNG
foreach ($export_details as $index => $detail) {
    $current_row = $start_data_row + $index;
    $donGiaXuat = round(parseMoneyStringToFloat($detail['don_gia'] ?? 0), 3);
    $thanhTienXuat = round(parseMoneyStringToFloat($detail['thanh_tien'] ?? 0), 3);
    
    // STT
    $sheet->setCellValue('A' . $current_row, $index + 1);
    
    // Tên hàng hóa - MERGE CELLS B:C cho mỗi dòng
    $sheet->mergeCells('B' . $current_row . ':C' . $current_row);
    $sheet->setCellValue('B' . $current_row, $detail['ten_san_pham']);
    
    // Đơn vị tính
    $sheet->setCellValue('E' . $current_row, $detail['don_vi']);
    
    // Số lượng yêu cầu, thực xuất
    $sheet->setCellValue('F' . $current_row, $detail['so_luong_xuat']);
    $sheet->setCellValue('G' . $current_row, $detail['so_luong_xuat']);
    
    // Đơn giá
    $sheet->setCellValueExplicit('H' . $current_row, formatVnAmount($donGiaXuat, 3, true), DataType::TYPE_STRING);
    
    // Thành tiền
    $sheet->setCellValueExplicit('I' . $current_row, formatVnAmount($thanhTienXuat, 3, true), DataType::TYPE_STRING);
}

// CHỈ SỬA CÔNG THỨC SUM TRONG DÒNG CỘNG CÓ SẴN
// Dòng "Cộng" và VAT vẫn ở vị trí cũ trong template
$total_row = $start_data_row + $num_items; // Dòng tổng sau dòng dữ liệu cuối cùng

// Chỉ căn trái cho cột Tên sản phẩm (B)
$sheet->getStyle('B' . $start_data_row . ':B' . ($total_row - 1))
	->getAlignment()
	->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

// TÍNH TỔNG TIỀN ĐỂ CHUYỂN THÀNH CHỮ
$total_amount = 0;
foreach ($export_details as $detail) {
    $total_amount += round(parseMoneyStringToFloat($detail['thanh_tien'] ?? 0), 3);
}

// Điền tổng tiền sau khi đã tính xong (tránh Warning làm hỏng file download)
$sheet->setCellValueExplicit('I' . $total_row, formatVnAmount($total_amount, 3, true), DataType::TYPE_STRING);

// GỌI FILE XỬ LÝ CHUYỂN SỐ THÀNH CHỮ
require_once __DIR__ . '/../../app/helpers/number_to_words.php';

// CẬP NHẬT Ô "TỔNG SỐ TIỀN (VIẾT BẰNG CHỮ)" Ở DÒNG 23
// TÍNH TOÁN VỊ TRÍ Ô "TỔNG SỐ TIỀN (VIẾT BẰNG CHỮ)"
// Dòng cuối cùng của dữ liệu: $start_data_row + $num_items - 1
// Dòng "Cộng": $start_data_row + $num_items  
// Dòng "(Đơn giá chưa bao gồm VAT)": $start_data_row + $num_items + 1
// Dòng "Tổng số tiền (viết bằng chữ)": $start_data_row + $num_items + 2
$total_in_words_row = $start_data_row + $num_items + 2;

$amount_in_words = amountToVietnameseWordsFloat($total_amount);
$sheet->setCellValue('A' . $total_in_words_row, '- Tổng số tiền (viết bằng chữ): ' . $amount_in_words);

// Nếu không có tham số số HĐ, thử suy ra từ các dòng chi tiết (lấy số HĐ nhập)
if ($so_hd_override === '') {
    try {
        $st = $pdo->prepare('SELECT DISTINCT ib.so_hoa_don FROM export_bill_details ebd JOIN import_bill_details ibd ON ibd.product_id = ebd.product_id JOIN import_bill ib ON ib.id = ibd.import_bill_id WHERE ebd.export_bill_id = ?');
        $st->execute([$export_bill_id]);
        $nums = $st->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($nums)) {
            // Nếu chỉ có 1 số, dùng nó; nếu nhiều, ghép bằng dấu ", "
            $so_hd_override = count($nums) === 1 ? $nums[0] : implode(', ', $nums);
        }
    } catch (Exception $e) {
        // ignore
    }
}

// THÊM Ô "SỐ CHỨNG TỪ GỐC KÈM THEO: HOÁ ĐƠN SỐ" VỚI số HĐ ưu tiên từ UI hoặc suy ra; fallback sang id phiếu
$chung_tu_row = $start_data_row + $num_items + 3;
$so_ct = $so_hd_override !== '' ? $so_hd_override : (string)$export_bill_id;
$sheet->setCellValue('A' . $chung_tu_row, '- Số chứng từ gốc kèm theo: Hoá đơn số ' . $so_ct);

// THÊM NGÀY THÁNG NĂM VÀO Ô G
$date_row = $start_data_row + $num_items + 5;
$current_date = 'Ngày ' . $ngay_xuat . ' tháng ' . $thang_xuat . ' năm ' . $nam_xuat;
$sheet->setCellValue('G' . $date_row, $current_date);

// THÊM TÊN NGƯỜI NHẬN VÀO Ô C DƯỚI BẢNG 13 Ô
$signature_row = $start_data_row + $num_items + 13; // Dưới bảng 13 ô
$sheet->setCellValue('C' . $signature_row, $export_bill['nguoi_nhan']);


// Lưu file tạm
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('xlsx_', true) . '.xlsx';
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save($tmp);

// Download file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$export_filename.'"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: must-revalidate');
header('Pragma: public');

readfile($tmp);
@unlink($tmp);
exit;
?>