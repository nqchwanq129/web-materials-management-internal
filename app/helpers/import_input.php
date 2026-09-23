<?php
function validateImportInput(array $input): array {
    $text = static function ($v, int $length, bool $required = false): string {
        if (!is_string($v)) throw new DomainException('Thông tin nhập không hợp lệ.');
        $v = trim($v);
        if (($required && $v === '') || mb_strlen($v) > $length) throw new DomainException('Vui lòng điền đủ thông tin và kiểm tra độ dài các trường.');
        return $v;
    };
    foreach (['invoiceNumber'=>100,'serial'=>100,'supplier'=>200,'receiverName'=>200,'importUnit'=>200] as $key=>$length) {
        $input[$key] = $text($input[$key] ?? '', $length, in_array($key,['invoiceNumber','supplier','importUnit'],true));
    }
    $date = $text($input['importDate'] ?? '', 10, true);
    $dt = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) throw new DomainException('Ngày nhập không hợp lệ.');
    $goods = $input['goodsList'] ?? null;
    if (!is_array($goods) || !$goods || !array_is_list($goods)) throw new DomainException('Vui lòng thêm ít nhất một mặt hàng hợp lệ.');
    $total = 0;
    foreach ($goods as &$item) {
        if (!is_array($item)) throw new DomainException('Dòng hàng hóa không hợp lệ.');
        foreach (['name'=>200,'type'=>50,'unit'=>50,'ghiChu'=>16000,'serial'=>100] as $key=>$length) $item[$key] = $text($item[$key] ?? '', $length, in_array($key,['name','type','unit'],true));
        if (!in_array($item['type'],['Công cụ dụng cụ','Vật tư','Tài sản cố định','Phụ tùng thay thế','Khác'],true)) throw new DomainException('Nhóm hàng không hợp lệ.');
        $qty = filter_var($item['quantity'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
        if (!$qty) throw new DomainException('Số lượng phải là số nguyên dương.');
        foreach (['priceBeforeVAT','vatPercent'] as $key) {
            if (!isset($item[$key]) || !is_scalar($item[$key]) || !is_numeric($item[$key]) || !is_finite((float)$item[$key]) || (float)$item[$key] < 0) throw new DomainException('Đơn giá hoặc VAT không hợp lệ.');
        }
        if ((float)$item['vatPercent'] > 100) throw new DomainException('VAT phải từ 0 đến 100%.');
        $item['quantity']=$qty;
        $item['priceBeforeVAT']=round((float)$item['priceBeforeVAT'],3);
        $item['totalAmount']=round(round($qty*$item['priceBeforeVAT'],3)*(1+(float)$item['vatPercent']/100),3);
        $total += $item['totalAmount'];
        if (!is_finite($total) || $total >= 1e17) throw new DomainException('Tổng tiền vượt phạm vi cho phép.');
    }
    unset($item);
    $input['goodsList']=$goods; $input['goodsCount']=count($goods); $input['totalAfterVAT']=round($total,3);
    return $input;
}
