<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Block\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;

class Processing extends Template
{
    private CheckoutSession $checkoutSession;

    public function __construct(
        Template\Context $context,
        CheckoutSession $checkoutSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
    }

    public function getOrderIncrementId(): string
    {
        return (string)$this->checkoutSession->getLastRealOrder()?->getIncrementId();
    }

    public function getCustomerEmail(): string
    {
        return (string)$this->checkoutSession->getLastRealOrder()?->getCustomerEmail();
    }

    public function getOrderStatusUrl(): string
    {
        return $this->getUrl('antom/payment/orderstatus');
    }

    public function getSuccessUrl(): string
    {
        return $this->getUrl('checkout/onepage/success');
    }

    public function getContinueShoppingUrl(): string
    {
        return $this->getUrl('');
    }
}
