<?php
// Actions for the authenticated import detail page.
if (isset($_SESSION['import_saved'])) {
    $success_message = $_SESSION['import_saved'];
    unset($_SESSION['import_saved']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf_token'] ?? '')) $_POST['csrf_token'] = '';
    requireCSRFToken();
    $json = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    $action = $_POST['action'] ?? '';
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM import_bill WHERE id = ? FOR UPDATE');
        $stmt->execute([$billId]);
        $lockedBill = $stmt->fetch();
        if (!$lockedBill) throw new DomainException('Phiếu nhập không còn tồn tại.');
        $stmt = $pdo->prepare('SELECT d.*, p.so_luong_con_lai FROM import_bill_details d JOIN products p ON p.id = d.product_id WHERE d.import_bill_id = ? ORDER BY p.id FOR UPDATE');
        $stmt->execute([$billId]);
        $lines = $stmt->fetchAll();
        $byProduct = [];
        foreach ($lines as $line) $byProduct[(int) $line['product_id']] = $line;
        if ($action === 'save') {
            $text = static function ($value, int $max, bool $required = false): string {
                if (!is_string($value)) throw new DomainException('Thông tin nhập không hợp lệ.');
                $value = trim($value);
                if (($required && $value === '') || mb_strlen($value) > $max) throw new DomainException('Vui lòng điền đủ thông tin và kiểm tra độ dài các trường.');
                return $value;
            };
            $supplier = $text($_POST['supplier'] ?? '', 200, true);
            $receiver = $text($_POST['receiver-name'] ?? '', 200);
            $unit = $text($_POST['import-unit'] ?? '', 200, true);
            $number = $text($_POST['invoice-number'] ?? '', 100, true);
            $serial = $text($_POST['serial'] ?? '', 100);
            $date = $text($_POST['import-date'] ?? '', 10, true);
            $dt = DateTime::createFromFormat('!Y-m-d', $date);
            if (!$dt || $dt->format('Y-m-d') !== $date) throw new DomainException('Ngày nhập không hợp lệ.');
            $money = static function ($value): float {
                if (!is_string($value) || !preg_match('/^\d[\d., ]*$/D', trim($value))) throw new DomainException('Số tiền không hợp lệ.');
                $n = round(parseMoneyStringToFloat($value), 3);
                if (!is_finite($n) || $n < 0 || $n >= 1e17) throw new DomainException('Số tiền vượt phạm vi cho phép.');
                return $n;
            };
            $billAmount = $money($_POST['total-amount'] ?? '');
            $submitted = $_POST['products'] ?? [];
            if (!is_array($submitted) || count($submitted) !== count($byProduct) || count($lines) !== count($byProduct)) throw new DomainException('Danh sách hàng hóa đã thay đổi hoặc không đầy đủ. Vui lòng tải lại trang.');
            $stmt = $pdo->prepare('SELECT id FROM import_bill WHERE so_hoa_don = ? AND id <> ?');
            $stmt->execute([$number, $billId]);
            if ($stmt->fetch()) throw new DomainException('Số hóa đơn đã tồn tại. Vui lòng kiểm tra lại.');
            $deltaTotal = 0;
            foreach ($submitted as $id => $data) {
                if (!ctype_digit((string) $id) || !isset($byProduct[(int) $id]) || !is_array($data)) throw new DomainException('Hàng hóa không thuộc phiếu nhập này.');
                $old = $byProduct[(int) $id];
                $qty = filter_var($data['so_luong_nhap'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
                if (!$qty) throw new DomainException('Số lượng nhập phải là số nguyên dương.');
                $price = $money($data['don_gia'] ?? '');
                $vatText = $data['vat'] ?? '';
                if (!is_string($vatText) || !is_numeric(str_replace(',', '.', $vatText))) throw new DomainException('VAT không hợp lệ.');
                $vat = (float) str_replace(',', '.', $vatText);
                if ($vat < 0 || $vat > 100) throw new DomainException('VAT phải từ 0 đến 100%.');
                $oldVat = (float) $old['don_gia'] > 0.00001 ? max(0, (int) round(((float) $old['thanh_tien'] / max(1, (int) $old['so_luong_nhap']) / (float) $old['don_gia'] - 1) * 100)) : 0;
                $changed = $qty !== (int) $old['so_luong_nhap'] || abs($price - (float) $old['don_gia']) > 0.0001 || abs($vat - $oldVat) > 0.0001;
                // Preserve original rounding and invoice adjustments when prices are unchanged.
                $before = round($qty * $price, 3);
                $after = $changed ? round($before * (1 + $vat / 100), 3) : (float) $old['thanh_tien'];
                if ($after >= 1e17 || $before >= 1e17) throw new DomainException('Thành tiền vượt phạm vi cho phép.');
                $deltaTotal += $after - (float) $old['thanh_tien'];
                $remaining = (int) $old['so_luong_con_lai'] + $qty - (int) $old['so_luong_nhap'];
                if ($remaining < 0) throw new DomainException('Không thể giảm số lượng nhập vì hàng hóa đã được xuất.');
                $category = $text($data['loai'] ?? '', 50, true);
                if (!in_array($category, ['Công cụ dụng cụ','Vật tư','Tài sản cố định','Phụ tùng thay thế','Khác'], true)) throw new DomainException('Nhóm hàng không hợp lệ.');
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM import_bill_details WHERE product_id = ? AND import_bill_id <> ?');
                $stmt->execute([$id, $billId]);
                if ((int) $stmt->fetchColumn() > 0) throw new DomainException('Hàng hóa được dùng trên nhiều phiếu nhập. Cần kiểm tra nghiệp vụ trước khi sửa.');
                $stmt = $pdo->prepare('UPDATE products SET ten_san_pham=?, loai=?, don_vi=?, ghi_chu=?, serial=?, so_luong_nhap=?, don_gia=?, thanh_tien=?, so_luong_con_lai=?, ngay_nhap=? WHERE id=?');
                $stmt->execute([$text($data['ten_san_pham'] ?? '', 200, true), $category, $text($data['don_vi'] ?? '', 50, true), $text($data['ghi_chu'] ?? '', 16000), $text($data['serial'] ?? '', 100), $qty, $price, $before, $remaining, $date, $id]);
                $stmt = $pdo->prepare('UPDATE import_bill_details SET so_luong_nhap=?, don_gia=?, thanh_tien=? WHERE id=? AND import_bill_id=?');
                $stmt->execute([$qty, $price, $after, $old['id'], $billId]);
            }
            if (abs($billAmount - (float) $lockedBill['tong_tien']) < 0.0001) $billAmount = round($billAmount + $deltaTotal, 3);
            if ($billAmount < 0 || $billAmount >= 1e17) throw new DomainException('Tổng tiền hóa đơn không hợp lệ.');
            $stmt = $pdo->prepare('UPDATE import_bill SET nha_cung_cap=?, nguoi_nhan_hang=?, nhap_vao_don_vi=?, ngay_nhap=?, so_hoa_don=?, serial=?, tong_tien=?, so_luong_mat_hang=? WHERE id=?');
            $stmt->execute([$supplier, $receiver, $unit, $date, $number, $serial, $billAmount, count($lines), $billId]);
            $pdo->commit();
            $_SESSION['import_saved'] = 'Đã lưu thông tin phiếu nhập và hàng hóa.';
            if ($json) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success' => true]); exit; }
            header('Location: details.php?id=' . $billId); exit;
        } elseif ($action === 'delete') {
            foreach (array_keys($byProduct) as $id) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM export_bill_details WHERE product_id = ?');
                $stmt->execute([$id]);
                if ((int) $stmt->fetchColumn() > 0) throw new DomainException('Không thể xóa phiếu vì có hàng hóa đã được xuất.');
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM import_bill_details WHERE product_id = ? AND import_bill_id <> ?');
                $stmt->execute([$id, $billId]);
                if ((int) $stmt->fetchColumn() > 0) throw new DomainException('Không thể xóa vì hàng hóa còn thuộc phiếu nhập khác.');
            }
            $stmt = $pdo->prepare('DELETE FROM import_bill_details WHERE import_bill_id = ?'); $stmt->execute([$billId]);
            foreach (array_keys($byProduct) as $id) {
                $stmt = $pdo->prepare('DELETE FROM product_images WHERE product_id = ?'); $stmt->execute([$id]);
                $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?'); $stmt->execute([$id]);
            }
            $stmt = $pdo->prepare('DELETE FROM import_bill WHERE id = ?'); $stmt->execute([$billId]);
            $pdo->commit();
            // Files are retained: a filesystem deletion cannot be rolled back with the database.
            header('Location: index.php?deleted=1'); exit;
        } else { throw new DomainException('Thao tác không hợp lệ.'); }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Import bill action: ' . $e->getMessage());
        $error_message = $e instanceof DomainException ? $e->getMessage() : 'Không thể lưu thay đổi. Vui lòng thử lại.';
        if ($json) { http_response_code(422); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success' => false, 'message' => $error_message]); exit; }
    }
}
