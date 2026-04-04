<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_PREFIX = 'payment/antom/';
    private const SDK_URL = 'https://sdk.marmot-cloud.com/package/ams-checkout/1.46.0/dist/umd/ams-checkout.min.js';

    private ScopeConfigInterface $scopeConfig;
    private EncryptorInterface $encryptor;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    public function isActive(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . 'active',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getTitle(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'title',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getEnvironment(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'environment',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isSandbox(?int $storeId = null): bool
    {
        return $this->getEnvironment($storeId) === 'sandbox';
    }

    public function getClientId(?int $storeId = null): string
    {
        $prefix = $this->isSandbox($storeId) ? 'sandbox' : 'live';
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . $prefix . '_client_id',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getMerchantPrivateKey(?int $storeId = null): string
    {
        $prefix = $this->isSandbox($storeId) ? 'sandbox' : 'live';
        $encrypted = (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . $prefix . '_merchant_private_key',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $this->encryptor->decrypt($encrypted);
    }

    public function getAlipayPublicKey(?int $storeId = null): string
    {
        $prefix = $this->isSandbox($storeId) ? 'sandbox' : 'live';
        $encrypted = (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . $prefix . '_alipay_public_key',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $this->encryptor->decrypt($encrypted);
    }

    public function getPaymentAction(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'payment_action',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isDebugEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . 'debug',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getSortOrder(?int $storeId = null): int
    {
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'sort_order',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getMinOrderTotal(?int $storeId = null): ?float
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'min_order_total',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $value !== null && $value !== '' ? (float)$value : null;
    }

    public function getMaxOrderTotal(?int $storeId = null): ?float
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'max_order_total',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $value !== null && $value !== '' ? (float)$value : null;
    }

    public function isCcActive(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . 'cc_active',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isGooglePayActive(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . 'googlepay_active',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getGooglePayMerchantId(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'googlepay_merchant_id',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getGooglePayButtonColor(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'googlepay_button_color',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getGooglePayButtonType(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'googlepay_button_type',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isApplePayActive(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . 'applepay_active',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getApplePayButtonColor(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'applepay_button_color',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getApplePayButtonType(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'applepay_button_type',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Antom captureMode: "AUTOMATIC" for authorize_capture, "MANUAL" for authorize-only.
     * Maps Magento payment_action config to the Antom API captureMode parameter.
     */
    public function getCaptureMode(?int $storeId = null): string
    {
        return $this->getPaymentAction($storeId) === 'authorize_capture' ? 'AUTOMATIC' : 'MANUAL';
    }

    /**
     * Web SDK environment string: "sandbox" or "production".
     * The Antom Web SDK AMSElement constructor expects this exact value.
     */
    public function getSdkEnvironment(?int $storeId = null): string
    {
        return $this->isSandbox($storeId) ? 'sandbox' : 'production';
    }

    public function getSdkUrl(): string
    {
        return self::SDK_URL;
    }
}
