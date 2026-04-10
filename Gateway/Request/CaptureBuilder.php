<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway\Request;

use CaravanGlory\Antom\Gateway\AmountConverter;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Model\Amount;
use Request\pay\AlipayCaptureRequest;

class CaptureBuilder implements BuilderInterface
{
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder();
        $payment = $paymentDO->getPayment();
        $storeId = (int)$order->getStoreId();

        $paymentId = $payment->getAdditionalInformation('antom_payment_id');
        $incrementId = $order->getOrderIncrementId();
        $captureRequestId = $incrementId . '_capture_' . bin2hex(random_bytes(8));

        $amount = new Amount();
        $amount->setCurrency($order->getCurrencyCode());
        $amount->setValue(AmountConverter::toMinorUnits(
            (float)SubjectReader::readAmount($buildSubject),
            $order->getCurrencyCode()
        ));

        $request = new AlipayCaptureRequest();
        $request->setCaptureRequestId($captureRequestId);
        $request->setPaymentId($paymentId);
        $request->setCaptureAmount($amount);

        return [
            'request' => $request,
            'store_id' => $storeId,
        ];
    }
}
