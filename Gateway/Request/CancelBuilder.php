<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Request\pay\AlipayPayCancelRequest;

class CancelBuilder implements BuilderInterface
{
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder();
        $payment = $paymentDO->getPayment();
        $storeId = (int)$order->getStoreId();

        $paymentId = $payment->getAdditionalInformation('antom_payment_id');
        $paymentRequestId = $payment->getAdditionalInformation('payment_request_id');

        $request = new AlipayPayCancelRequest();

        if ($paymentId) {
            $request->setPaymentId($paymentId);
        } else {
            $request->setPaymentRequestId($paymentRequestId);
        }

        return [
            'request' => $request,
            'store_id' => $storeId,
        ];
    }
}
