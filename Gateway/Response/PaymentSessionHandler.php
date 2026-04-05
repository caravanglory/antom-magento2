<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway\Response;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class PaymentSessionHandler implements HandlerInterface
{
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();

        if (!empty($response['paymentSessionData'])) {
            $payment->setAdditionalInformation(
                'payment_session_data',
                $response['paymentSessionData']
            );
        }

        if (!empty($response['paymentSessionId'])) {
            $payment->setAdditionalInformation(
                'payment_session_id',
                $response['paymentSessionId']
            );
        }

        if (!empty($response['paymentSessionExpiryTime'])) {
            $payment->setAdditionalInformation(
                'payment_session_expiry_time',
                $response['paymentSessionExpiryTime']
            );
        }

        if (!empty($response['normalUrl'])) {
            $payment->setAdditionalInformation(
                'normal_url',
                $response['normalUrl']
            );
        }
    }
}
