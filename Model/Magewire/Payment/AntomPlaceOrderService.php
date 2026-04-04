<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Model\Magewire\Payment;

use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;
use Magento\Quote\Model\Quote;

class AntomPlaceOrderService extends AbstractPlaceOrderService
{
    public function getRedirectUrl(Quote $quote, ?int $orderId = null): string
    {
        return self::REDIRECT_PATH;
    }

    public function canRedirect(): bool
    {
        return false;
    }
}
