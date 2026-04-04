<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway\Response;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class RefundHandler implements HandlerInterface
{
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();

        if (!empty($response['refundId'])) {
            $payment->setTransactionId($response['refundId']);
            $payment->setIsTransactionClosed(true);
            $payment->setAdditionalInformation(
                'antom_refund_id',
                $response['refundId']
            );
        }

        if (!empty($response['refundRequestId'])) {
            $payment->setAdditionalInformation(
                'antom_refund_request_id',
                $response['refundRequestId']
            );
        }
    }
}
