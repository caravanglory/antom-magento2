<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Observer\Frontend;

use CaravanGlory\Antom\Gateway\Config;
use CaravanGlory\Antom\Model\Ui\ConfigProvider;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class HyvaCheckoutConfigGenerateBefore implements ObserverInterface
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function execute(Observer $observer): void
    {
        $config = $observer->getData('config');

        if (!$config || !$this->config->isActive()) {
            return;
        }

        $antomConfig = [
            'sdkEnvironment' => $this->config->getSdkEnvironment(),
            'sdkUrl' => $this->config->getSdkUrl(),
            'createSessionUrl' => 'antom/payment/createsession',
            'methods' => [
                ConfigProvider::CODE_CC => [
                    'active' => $this->config->isCcActive(),
                    'sdkPaymentMethodType' => 'CARD',
                ],
                ConfigProvider::CODE_GOOGLEPAY => [
                    'active' => $this->config->isGooglePayActive(),
                    'sdkPaymentMethodType' => 'GOOGLEPAY',
                    'buttonColor' => $this->config->getGooglePayButtonColor(),
                    'buttonType' => $this->config->getGooglePayButtonType(),
                    'merchantId' => $this->config->getGooglePayMerchantId(),
                ],
                ConfigProvider::CODE_APPLEPAY => [
                    'active' => $this->config->isApplePayActive(),
                    'sdkPaymentMethodType' => 'APPLEPAY',
                    'buttonColor' => $this->config->getApplePayButtonColor(),
                    'buttonType' => $this->config->getApplePayButtonType(),
                ],
            ],
        ];

        $config->addData(['antom' => $antomConfig]);
    }
}
