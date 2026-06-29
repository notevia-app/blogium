<?php
function formatNumberShort($number) {
    if ($number >= 1_000_000_000) {
        return number_format($number / 1_000_000_000, 1, ',', '') . ' Mrd';
    } elseif ($number >= 1_000_000) {
        return number_format($number / 1_000_000, 1, ',', '') . ' Mn';
    } elseif ($number >= 1_000) {
        return number_format($number / 1_000, 1, ',', '') . ' Bin';
    } else {
        return $number;
    }
}

