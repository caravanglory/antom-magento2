<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway;

class AmountConverter
{
    private const ZERO_DECIMAL_CURRENCIES = [
        'JPY', 'KRW', 'VND', 'BIF', 'CLP', 'GNF', 'ISK', 'PYG', 'RWF', 'UGX', 'XAF', 'XOF',
    ];

    private const THREE_DECIMAL_CURRENCIES = [
        'BHD', 'KWD', 'OMR',
    ];

    public static function toMinorUnits(float $amount, string $currencyCode): string
    {
        $code = strtoupper($currencyCode);

        if (in_array($code, self::ZERO_DECIMAL_CURRENCIES, true)) {
            return (string)(int)round($amount);
        }

        if (in_array($code, self::THREE_DECIMAL_CURRENCIES, true)) {
            return (string)(int)round($amount * 1000);
        }

        return (string)(int)round($amount * 100);
    }
}
