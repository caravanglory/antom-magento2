<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Model\Magewire\Payment;

use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;
use Magento\Quote\Model\Quote;

class AntomHostedPlaceOrderService extends AbstractPlaceOrderService
{
    public function getRedirectUrl(Quote $quote, ?int $orderId = null): string
    {
        return 'antom/payment/hostedredirect';
    }

    public function canRedirect(): bool
    {
        return true;
    }
}
