<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway\Request;

use CaravanGlory\Antom\Gateway\AmountConverter;
use CaravanGlory\Antom\Gateway\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Model\Amount;
use Model\Env;
use Model\Order as AntomOrder;
use Model\PaymentFactor;
use Model\PaymentMethod;
use Model\ProductCodeType;
use Model\SettlementStrategy;
use Request\pay\AlipayPaymentSessionRequest;

class CreateSessionBuilder implements BuilderInterface
{
    private Config $config;
    private StoreManagerInterface $storeManager;
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
    }

    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder();
        $payment = $paymentDO->getPayment();
        $storeId = (int)$order->getStoreId();

        $incrementId = $order->getOrderIncrementId();
        $paymentRequestId = $incrementId . '_' . bin2hex(random_bytes(8));
        $paymentMethodType = $payment->getAdditionalInformation('payment_method_type') ?? 'CARD';

        $grandTotal = (float)SubjectReader::readAmount($buildSubject);
        $currencyCode = $order->getCurrencyCode();

        $amount = new Amount();
        $amount->setCurrency($currencyCode);
        $amount->setValue(AmountConverter::toMinorUnits($grandTotal, $currencyCode));

        $antomOrder = new AntomOrder();
        $antomOrder->setReferenceOrderId($incrementId);
        $antomOrder->setOrderAmount($amount);
        $antomOrder->setOrderDescription('Order #' . $incrementId);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setPaymentMethodType($paymentMethodType);

        $paymentFactor = new PaymentFactor();
        $paymentFactor->setCaptureMode($this->config->getCaptureMode($payment->getMethod(), $storeId));
        if ($paymentMethodType === 'CARD') {
            $paymentFactor->setIsAuthorization(true);
        }

        $baseUrl = $this->storeManager->getStore($storeId)->getBaseUrl();
        $notifyUrl = rtrim($baseUrl, '/') . '/antom/notification';
        $redirectUrl = rtrim($baseUrl, '/') . '/checkout/onepage/success';

        $request = new AlipayPaymentSessionRequest();
        $request->setProductCode(ProductCodeType::CASHIER_PAYMENT);
        $request->setProductScene($paymentMethodType === 'HOSTED' ? 'CASHIER' : 'ELEMENT_PAYMENT');
        $request->setPaymentRequestId($paymentRequestId);
        $request->setOrder($antomOrder);
        $request->setPaymentAmount($amount);
        $request->setPaymentMethod($paymentMethod);
        $request->setPaymentFactor($paymentFactor);
        $request->setPaymentNotifyUrl($notifyUrl);
        $request->setPaymentRedirectUrl($redirectUrl);

        $merchantRegion = (string)$this->scopeConfig->getValue(
            'general/country/default',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($merchantRegion !== '') {
            $request->setMerchantRegion($merchantRegion);
        }

        $settlementStrategy = new SettlementStrategy();
        $settlementStrategy->setSettlementCurrency(null);
        $request->setSettlementStrategy($settlementStrategy);

        $env = new Env();
        $env->setTerminalType('WEB');
        $request->setEnv($env);

        $payment->setAdditionalInformation('payment_request_id', $paymentRequestId);

        return [
            'request' => $request,
            'store_id' => $storeId,
        ];
    }
}
