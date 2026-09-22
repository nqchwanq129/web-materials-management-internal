<?php
/**
 * Chuyển đổi số thành chữ Việt Nam
 * Sử dụng cho việc viết số tiền bằng chữ
 */

function numberToVietnameseWords($number) {
    $ones = ['', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
    $teens = ['mười', 'mười một', 'mười hai', 'mười ba', 'mười bốn', 'mười lăm', 'mười sáu', 'mười bảy', 'mười tám', 'mười chín'];
    $tens = ['', '', 'hai mươi', 'ba mươi', 'bốn mươi', 'năm mươi', 'sáu mươi', 'bảy mươi', 'tám mươi', 'chín mươi'];
    
    if ($number == 0) return 'không';
    if ($number < 10) return $ones[$number];
    if ($number < 20) return $teens[$number - 10];
    if ($number < 100) {
        $ten = intval($number / 10);
        $one = $number % 10;
        if ($one == 0) return $tens[$ten];
        if ($one == 1) return $tens[$ten] . ' mốt';
        if ($one == 5) return $tens[$ten] . ' lăm';
        return $tens[$ten] . ' ' . $ones[$one];
    }
    if ($number < 1000) {
        $hundred = intval($number / 100);
        $remainder = $number % 100;
        if ($remainder == 0) return $ones[$hundred] . ' trăm';
        return $ones[$hundred] . ' trăm ' . numberToVietnameseWords($remainder);
    }
    if ($number < 1000000) {
        $thousand = intval($number / 1000);
        $remainder = $number % 1000;
        if ($remainder == 0) return numberToVietnameseWords($thousand) . ' nghìn';
        return numberToVietnameseWords($thousand) . ' nghìn ' . numberToVietnameseWords($remainder);
    }
    if ($number < 1000000000) {
        $million = intval($number / 1000000);
        $remainder = $number % 1000000;
        if ($remainder == 0) return numberToVietnameseWords($million) . ' triệu';
        return numberToVietnameseWords($million) . ' triệu ' . numberToVietnameseWords($remainder);
    }
    
    return 'số quá lớn';
}

/**
 * Số tiền (có thể có phần thập phân tối đa $maxDecimals) sang chữ tiếng Việt.
 * Phần sau dấu phẩy đọc từng chữ số (vd: ,25 -> phẩy hai năm).
 */
function amountToVietnameseWordsFloat($amount, $currency = 'đồng', $maxDecimals = 3) {
	$amount = round(abs((float)$amount), $maxDecimals);
	$s = sprintf('%.' . $maxDecimals . 'f', $amount);
	$parts = explode('.', $s, 2);
	$intPart = (int)$parts[0];
	$fracRaw = isset($parts[1]) ? rtrim($parts[1], '0') : '';
	$words = numberToVietnameseWords($intPart);
	if ($fracRaw !== '') {
		$digitWords = [
			'0' => 'không',
			'1' => 'một',
			'2' => 'hai',
			'3' => 'ba',
			'4' => 'bốn',
			'5' => 'năm',
			'6' => 'sáu',
			'7' => 'bảy',
			'8' => 'tám',
			'9' => 'chín',
		];
		$syllables = [];
		$len = strlen($fracRaw);
		for ($i = 0; $i < $len; $i++) {
			$ch = $fracRaw[$i];
			$syllables[] = $digitWords[$ch] ?? '';
		}
		$words .= ' phẩy ' . implode(' ', $syllables);
	}
	$words = mb_strtoupper(mb_substr($words, 0, 1, 'UTF-8'), 'UTF-8')
		. mb_substr($words, 1, mb_strlen($words, 'UTF-8'), 'UTF-8');
	return $words . ' ' . $currency . ' ./.';
}

/**
 * Chuyển đổi số tiền thành chữ với đơn vị tiền tệ
 * Chữ đầu tiên sẽ được viết hoa
 */
function amountToVietnameseWords($amount, $currency = 'đồng') {
	return amountToVietnameseWordsFloat((float)$amount, $currency, 3);
}
?>
