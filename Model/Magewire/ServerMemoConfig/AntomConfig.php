<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Model\Magewire\ServerMemoConfig;

use CaravanGlory\Antom\Gateway\Config;
use CaravanGlory\Antom\Model\Ui\ConfigProvider;
use Hyva\Checkout\Model\Magewire\ServerMemoConfig\AbstractConfigSection;

class AntomConfig extends AbstractConfigSection
{
    private Config $config;

    public function __construct(
        Config $config,
        array $data = []
    ) {
        parent::__construct($data);
        $this->config = $config;
    }

    public function getData(): array
    {
        return [
            'sdkEnvironment' => $this->config->getSdkEnvironment(),
            'sdkUrl' => $this->config->getSdkUrl(),
            'createSessionUrl' => 'antom/payment/createsession',
            'createQuoteSessionUrl' => 'antom/payment/createquotesession',
            'orderStatusUrl' => 'antom/payment/orderstatus',
            'processingUrl' => 'antom/payment/processing',
            'restoreOrderUrl' => 'antom/payment/restoreorder',
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
    }
}
