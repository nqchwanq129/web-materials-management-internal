<?php
function createExportBill(PDO $pdo, array $input): int {
    $text = static function ($value, int $limit, bool $required = true): string {
        if (!is_string($value)) throw new DomainException('Thông tin phiếu không hợp lệ.');
        $value = trim($value);
        if (($required && $value === '') || mb_strlen($value) > $limit) throw new DomainException('Vui lòng điền đủ thông tin phiếu và kiểm tra độ dài các trường.');
        return $value;
    };
    $receiver = $text(($input['receiver'] ?? '') === 'custom' ? ($input['receiver_custom'] ?? '') : ($input['receiver'] ?? ''), 200);
    $warehouse = $text($input['warehouse'] ?? '', 200);
    $number = $text($input['invoice_number'] ?? '', 100);
    $reason = $text($input['reason'] ?? '', 500, false);
    $date = $text($input['export_date'] ?? '', 10);
    $dt = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) throw new DomainException('Ngày xuất không hợp lệ.');
    $selected = $input['selected'] ?? [];
    $quantities = $input['quantities'] ?? [];
    if (!is_array($selected) || !$selected || !is_array($quantities)) throw new DomainException('Vui lòng chọn ít nhất một mặt hàng để xuất.');
    $items = [];
    foreach ($selected as $id => $chosen) {
        $productId = filter_var($id, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $qty = filter_var($quantities[$id] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>2147483647]]);
        if (!$productId || !$qty) throw new DomainException('Số lượng xuất phải là số nguyên dương cho từng mặt hàng đã chọn.');
        $items[$productId] = $qty;
    }
    ksort($items);
    try {
        $pdo->beginTransaction();
        $lines = []; $total = 0;
        foreach ($items as $id => $qty) {
            $stmt = $pdo->prepare('SELECT id, ten_san_pham, don_gia, so_luong_con_lai FROM products WHERE id=? FOR UPDATE');
            $stmt->execute([$id]); $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) throw new DomainException('Một mặt hàng không còn tồn tại. Vui lòng tải lại danh sách.');
            if ($qty > (int)$product['so_luong_con_lai']) throw new DomainException('Số lượng xuất vượt tồn kho của ' . $product['ten_san_pham'] . '. Vui lòng kiểm tra lại.');
            $amount = round($qty * (float)$product['don_gia'], 3);
            if (!is_finite($amount) || $amount < 0 || $amount >= 1e17) throw new DomainException('Giá trị hàng hóa không hợp lệ.');
            $total += $amount;
            $lines[] = [$id, $qty, $product['don_gia'], $amount];
        }
        if ($total >= 1e17) throw new DomainException('Tổng tiền vượt phạm vi cho phép.');
        $stmt = $pdo->prepare('SELECT id FROM export_bill WHERE so_hd_xuat=?'); $stmt->execute([$number]);
        if ($stmt->fetchColumn()) throw new DomainException('Số hóa đơn xuất đã tồn tại. Vui lòng nhập số khác.');
        $stmt = $pdo->prepare('INSERT INTO export_bill (nguoi_nhan,ten_kho_xuat,ngay_nhan,ly_do_xuat,tong_tien,so_hd_xuat) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$receiver,$warehouse,$date,$reason,round($total,3),$number]);
        $billId = (int)$pdo->lastInsertId();
        foreach ($lines as [$id,$qty,$price,$amount]) {
            $stmt = $pdo->prepare('INSERT INTO export_bill_details (export_bill_id,product_id,so_luong_xuat,don_gia,thanh_tien) VALUES (?,?,?,?,?)');
            $stmt->execute([$billId,$id,$qty,$price,$amount]);
            $stmt = $pdo->prepare('UPDATE products SET so_luong_con_lai=so_luong_con_lai-? WHERE id=?'); $stmt->execute([$qty,$id]);
        }
        $pdo->commit(); return $billId;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
