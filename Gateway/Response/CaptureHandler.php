<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway\Response;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class CaptureHandler implements HandlerInterface
{
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();

        if (!empty($response['captureId'])) {
            $payment->setTransactionId($response['captureId']);
            $payment->setAdditionalInformation('antom_capture_id', $response['captureId']);
        }

        if (!empty($response['captureRequestId'])) {
            $payment->setAdditionalInformation('antom_capture_request_id', $response['captureRequestId']);
        }

        $payment->setIsTransactionClosed(true);
    }
}
