<?php
class Money
{
    public static function parse($value)
    {
        if ($value === null) {
            return null;
        }
        if (is_numeric($value)) {
            return round((float)$value, 2);
        }
        $clean = trim($value);
        if ($clean === '') {
            return null;
        }
        $clean = str_replace(array('руб', 'Р', 'р', '₽'), '', $clean);
        $clean = str_replace(array(' ', "\t", "\n", "\xc2\xa0"), '', $clean);
        $clean = str_replace(',', '.', $clean);
        $clean = preg_replace('~[^0-9\.\-]~u', '', $clean);
        if ($clean === '' || $clean === '-' || $clean === '.' || $clean === '-.') {
            return null;
        }
        $float = (float)$clean;
        if (!is_finite($float)) {
            return null;
        }
        return round($float, 2);
    }
}
