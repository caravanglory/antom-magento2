<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway\Response;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class InquiryHandler implements HandlerInterface
{
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();

        if (!empty($response['paymentStatus'])) {
            $payment->setAdditionalInformation('antom_payment_status', $response['paymentStatus']);
        }

        if (!empty($response['paymentId'])) {
            $payment->setAdditionalInformation('antom_payment_id', $response['paymentId']);
        }

        if (!empty($response['paymentAmount'])) {
            $payment->setAdditionalInformation('antom_inquiry_amount', $response['paymentAmount']);
        }
    }
}
