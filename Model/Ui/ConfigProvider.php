<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Model\Ui;

use CaravanGlory\Antom\Gateway\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE_CC = 'antom_cc';
    public const CODE_GOOGLEPAY = 'antom_googlepay';
    public const CODE_APPLEPAY = 'antom_applepay';
    public const CODE_HOSTED = 'antom_hosted';

    private Config $config;
    private StoreManagerInterface $storeManager;

    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    public function getConfig(): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();

        return [
            'payment' => [
                self::CODE_CC => [
                    'isActive' => $this->config->isMethodActive(self::CODE_CC, $storeId),
                    'sdkEnvironment' => $this->config->getSdkEnvironment($storeId),
                    'sdkUrl' => $this->config->getSdkUrl(),
                ],
                self::CODE_GOOGLEPAY => [
                    'isActive' => $this->config->isMethodActive(self::CODE_GOOGLEPAY, $storeId),
                    'sdkEnvironment' => $this->config->getSdkEnvironment($storeId),
                    'buttonColor' => $this->config->getGooglePayButtonColor($storeId),
                    'buttonType' => $this->config->getGooglePayButtonType($storeId),
                ],
                self::CODE_APPLEPAY => [
                    'isActive' => $this->config->isMethodActive(self::CODE_APPLEPAY, $storeId),
                    'sdkEnvironment' => $this->config->getSdkEnvironment($storeId),
                    'buttonColor' => $this->config->getApplePayButtonColor($storeId),
                    'buttonType' => $this->config->getApplePayButtonType($storeId),
                ],
                self::CODE_HOSTED => [
                    'isActive' => $this->config->isMethodActive(self::CODE_HOSTED, $storeId),
                    'isRedirect' => true,
                ],
            ],
        ];
    }
}
