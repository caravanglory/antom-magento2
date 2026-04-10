<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway\Command;

use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;

/**
 * No-op authorize command for Antom's async payment flow.
 *
 * In Antom's architecture, authorization happens asynchronously:
 * 1. CreateSession creates the payment session with captureMode=MANUAL
 * 2. The customer completes payment via the SDK or hosted page
 * 3. Antom sends a PAYMENT_RESULT notification confirming authorization
 *
 * Magento calls this command when payment_action=authorize. We set the
 * transaction as open (not captured) so that the admin can later trigger
 * capture via the invoice action.
 */
class AuthorizeCommand implements CommandInterface
{
    public function execute(array $commandSubject): ?ResultInterface
    {
        $paymentDO = SubjectReader::readPayment($commandSubject);
        $payment = $paymentDO->getPayment();

        $payment->setIsTransactionClosed(false);

        return null;
    }
}
