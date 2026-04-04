<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Model\Order;

use Magento\Sales\Model\Order;

class StatusResolver
{
    /** @return array{state: string, status: string} */
    public function resolvePaymentNotification(string $resultStatus, string $captureMode): array
    {
        if ($resultStatus === 'S') {
            return [
                'state' => Order::STATE_PROCESSING,
                'status' => Order::STATE_PROCESSING,
            ];
        }

        if ($resultStatus === 'F') {
            return [
                'state' => Order::STATE_CANCELED,
                'status' => Order::STATE_CANCELED,
            ];
        }

        return [
            'state' => Order::STATE_PAYMENT_REVIEW,
            'status' => Order::STATE_PENDING_PAYMENT,
        ];
    }

    public function shouldCreateInvoice(string $resultStatus, string $captureMode): bool
    {
        return $resultStatus === 'S' && $captureMode === 'AUTOMATIC';
    }

    public function shouldMarkAuthorized(string $resultStatus, string $captureMode): bool
    {
        return $resultStatus === 'S' && $captureMode === 'MANUAL';
    }

    public function isTerminalState(string $orderState): bool
    {
        return in_array($orderState, [
            Order::STATE_COMPLETE,
            Order::STATE_CLOSED,
            Order::STATE_CANCELED,
        ], true);
    }
}
