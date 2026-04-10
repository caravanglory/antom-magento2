<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway\Request;

use CaravanGlory\Antom\Gateway\AmountConverter;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Model\Amount;
use Request\pay\AlipayRefundRequest;

class RefundBuilder implements BuilderInterface
{
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder();
        $payment = $paymentDO->getPayment();
        $storeId = (int)$order->getStoreId();

        $paymentId = $payment->getAdditionalInformation('antom_payment_id');
        $incrementId = $order->getOrderIncrementId();
        $refundRequestId = $incrementId . '_refund_' . bin2hex(random_bytes(8));

        $amount = new Amount();
        $amount->setCurrency($order->getCurrencyCode());
        $amount->setValue(AmountConverter::toMinorUnits(
            (float)SubjectReader::readAmount($buildSubject),
            $order->getCurrencyCode()
        ));

        $request = new AlipayRefundRequest();
        $request->setRefundRequestId($refundRequestId);
        $request->setPaymentId($paymentId);
        $request->setRefundAmount($amount);

        $creditmemo = $payment->getCreditmemo();
        if ($creditmemo && $creditmemo->getCustomerNote()) {
            $request->setRefundReason($creditmemo->getCustomerNote());
        }

        return [
            'request' => $request,
            'store_id' => $storeId,
        ];
    }
}
