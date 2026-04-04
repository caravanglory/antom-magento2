<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway;

class AmountConverter
{
    private const ZERO_DECIMAL_CURRENCIES = [
        'JPY', 'KRW', 'VND', 'BIF', 'CLP', 'GNF', 'ISK', 'PYG', 'RWF', 'UGX', 'XAF', 'XOF',
    ];

    public static function toMinorUnits(float $amount, string $currencyCode): string
    {
        if (in_array(strtoupper($currencyCode), self::ZERO_DECIMAL_CURRENCIES, true)) {
            return (string)(int)round($amount);
        }

        return (string)(int)round($amount * 100);
    }
}
