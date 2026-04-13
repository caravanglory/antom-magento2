<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway\Command;

use Magento\Framework\DataObject;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Model\Order;

class InitializeCommand implements CommandInterface
{
    public function execute(array $commandSubject): ?ResultInterface
    {
        $paymentDO = SubjectReader::readPayment($commandSubject);
        $payment = $paymentDO->getPayment();
        $order = $payment->getOrder();
        $stateObject = $commandSubject['stateObject'] ?? null;

        if (!$stateObject instanceof DataObject) {
            return null;
        }

        $payment->setIsTransactionPending(true);

        $stateObject->setData('state', Order::STATE_PENDING_PAYMENT);
        $stateObject->setData(
            'status',
            $order->getConfig()->getStateDefaultStatus(Order::STATE_PENDING_PAYMENT)
        );
        $stateObject->setData('is_notified', false);

        return null;
    }
}
