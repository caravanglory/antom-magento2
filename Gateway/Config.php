<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_PREFIX = 'payment/antom/';
    private const SDK_URL = 'https://js.antom.com/v2/ams-checkout.js';

    private ScopeConfigInterface $scopeConfig;
    private EncryptorInterface $encryptor;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    // ---------------------------------------------------------------
    // Shared config (payment/antom/*)
    // ---------------------------------------------------------------

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

    public function isDebugEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . 'debug',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getSettlementCurrency(?int $storeId = null): ?string
    {
        $value = trim((string)$this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . 'settlement_currency',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        return $value === '' ? null : strtoupper($value);
    }

    // ---------------------------------------------------------------
    // Per-method config (payment/{methodCode}/*)
    // ---------------------------------------------------------------

    public function isMethodActive(string $methodCode, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            'payment/' . $methodCode . '/active',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Antom captureMode: "AUTOMATIC" for authorize_capture, "MANUAL" for authorize-only.
     * Maps Magento payment_action config to the Antom API captureMode parameter.
     */
    public function getCaptureMode(string $methodCode, ?int $storeId = null): string
    {
        $paymentAction = (string)$this->scopeConfig->getValue(
            'payment/' . $methodCode . '/payment_action',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $paymentAction === 'authorize_capture' ? 'AUTOMATIC' : 'MANUAL';
    }

    // ---------------------------------------------------------------
    // Google Pay config (payment/antom_googlepay/*)
    // ---------------------------------------------------------------

    public function getGooglePayMerchantId(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            'payment/antom_googlepay/merchant_id',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getGooglePayButtonColor(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            'payment/antom_googlepay/button_color',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getGooglePayButtonType(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            'payment/antom_googlepay/button_type',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    // ---------------------------------------------------------------
    // Apple Pay config (payment/antom_applepay/*)
    // ---------------------------------------------------------------

    public function getApplePayButtonColor(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            'payment/antom_applepay/button_color',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getApplePayButtonType(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            'payment/antom_applepay/button_type',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    // ---------------------------------------------------------------
    // SDK helpers
    // ---------------------------------------------------------------

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
