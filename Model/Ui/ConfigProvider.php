<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Model\Ui;

use CaravanGlory\Antom\Gateway\Config;
use Magento\Checkout\Model\ConfigProviderInterface;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE_CC = 'antom_cc';
    public const CODE_GOOGLEPAY = 'antom_googlepay';
    public const CODE_APPLEPAY = 'antom_applepay';
    public const CODE_HOSTED = 'antom_hosted';

    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function getConfig(): array
    {
        return [
            'payment' => [
                self::CODE_CC => [
                    'isActive' => $this->config->isActive() && $this->config->isCcActive(),
                    'sdkEnvironment' => $this->config->getSdkEnvironment(),
                ],
                self::CODE_GOOGLEPAY => [
                    'isActive' => $this->config->isActive() && $this->config->isGooglePayActive(),
                    'sdkEnvironment' => $this->config->getSdkEnvironment(),
                    'buttonColor' => $this->config->getGooglePayButtonColor(),
                    'buttonType' => $this->config->getGooglePayButtonType(),
                ],
                self::CODE_APPLEPAY => [
                    'isActive' => $this->config->isActive() && $this->config->isApplePayActive(),
                    'sdkEnvironment' => $this->config->getSdkEnvironment(),
                    'buttonColor' => $this->config->getApplePayButtonColor(),
                    'buttonType' => $this->config->getApplePayButtonType(),
                ],
                self::CODE_HOSTED => [
                    'isActive' => $this->config->isActive() && $this->config->isHostedActive(),
                    'isRedirect' => true,
                ],
            ],
        ];
    }
}
