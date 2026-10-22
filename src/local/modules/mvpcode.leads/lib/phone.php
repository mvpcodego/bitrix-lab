<?php
namespace Mvpcode\Leads;

class Phone
{
    /** 8 (926) 123-45-67, +7 926 1234567, 9261234567 → 79261234567; остальное — только цифры. */
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7' . substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            $digits = '7' . $digits;
        }
        return $digits;
    }
}
