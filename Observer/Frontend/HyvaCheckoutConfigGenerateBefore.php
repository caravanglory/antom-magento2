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
                    'active' => $this->config->isMethodActive(ConfigProvider::CODE_CC),
                    'sdkPaymentMethodType' => 'CARD',
                ],
                ConfigProvider::CODE_GOOGLEPAY => [
                    'active' => $this->config->isMethodActive(ConfigProvider::CODE_GOOGLEPAY),
                    'sdkPaymentMethodType' => 'GOOGLEPAY',
                    'buttonColor' => $this->config->getGooglePayButtonColor(),
                    'buttonType' => $this->config->getGooglePayButtonType(),
                    'merchantId' => $this->config->getGooglePayMerchantId(),
                ],
                ConfigProvider::CODE_APPLEPAY => [
                    'active' => $this->config->isMethodActive(ConfigProvider::CODE_APPLEPAY),
                    'sdkPaymentMethodType' => 'APPLEPAY',
                    'buttonColor' => $this->config->getApplePayButtonColor(),
                    'buttonType' => $this->config->getApplePayButtonType(),
                ],
                ConfigProvider::CODE_HOSTED => [
                    'active' => $this->config->isMethodActive(ConfigProvider::CODE_HOSTED),
                    'sdkPaymentMethodType' => 'HOSTED',
                ],
            ],
        ];

        $config->addData(['antom' => $antomConfig]);
    }
}
