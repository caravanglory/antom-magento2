<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway\Response;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class CancelHandler implements HandlerInterface
{
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();

        if (!empty($response['paymentId'])) {
            $payment->setAdditionalInformation('antom_cancel_payment_id', $response['paymentId']);
        }

        $payment->setIsTransactionClosed(true);
    }
}
