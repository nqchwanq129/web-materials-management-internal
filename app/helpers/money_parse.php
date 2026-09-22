<?php
/**
 * Chuẩn hoá chuỗi tiền VN (dấu chấm hàng nghìn, dấu phẩy thập phân tối đa 3 số) và EN (một dấu chấm thập phân).
 */

function parseMoneyStringToFloat($value) {
	if ($value === null || $value === '') {
		return 0.0;
	}
	if (is_int($value) || is_float($value)) {
		return (float)$value;
	}
	$s = trim((string)$value);
	$s = str_replace(["\xC2\xA0", ' '], '', $s);
	if ($s === '') {
		return 0.0;
	}
	if (is_numeric($s) && strpos($s, ',') === false && substr_count($s, '.') <= 1) {
		return (float)$s;
	}
	// Thousand grouping (EN-style comma): 276,852 or 1,234,567
	if (preg_match('/^\d{1,3}(,\d{3})+$/', $s)) {
		return (float)str_replace(',', '', $s);
	}
	// Thousand grouping (VN-style dot): 276.852 or 1.234.567
	if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
		return (float)str_replace('.', '', $s);
	}
	// EN: 123.45 (một dấu chấm, phần thập phân 1–6 chữ số; XML thường có 6 số)
	if (preg_match('/^\d+\.\d{1,6}$/', $s) && substr_count($s, '.') === 1) {
		return (float)$s;
	}
	// VN: ... ,xx (1–3 chữ số sau phẩy)
	// Lưu ý: nếu chuỗi đúng pattern nhóm 3 số bằng dấu phẩy thì đã được bắt ở trên.
	if (preg_match('/^(.+),(\d{1,3})$/u', $s, $m)) {
		$intPart = str_replace(['.', ','], '', $m[1]);
		if ($intPart === '' || !ctype_digit($intPart)) {
			return 0.0;
		}
		$digits = strlen($m[2]);
		$frac = (int)$m[2] / pow(10, $digits);
		return (float)((int)$intPart + $frac);
	}
	$s2 = str_replace(['.', ','], '', $s);
	if ($s2 === '' || !is_numeric($s2)) {
		return 0.0;
	}
	return (float)$s2;
}

function formatVnAmount($n, $maxDecimals = 3, $padDecimals = false) {
	$n = round((float)$n, $maxDecimals);
	$millis = (int)round($n * 1000);
	$sign = $millis < 0 ? '-' : '';
	$millis = abs($millis);
	$intPart = intdiv($millis, 1000);
	$frac = $millis % 1000;
	$intStr = $sign . number_format($intPart, 0, ',', '.');
	if ($frac === 0) {
		return $intStr;
	}
	$fs = str_pad((string)$frac, 3, '0', STR_PAD_LEFT);
	if (!$padDecimals) {
		$fs = rtrim($fs, '0');
	}
	return $intStr . ',' . $fs;
}
