<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Model\Notification;

use CaravanGlory\Antom\Gateway\Config;
use CaravanGlory\Antom\Model\Order\StatusResolver;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;

class Processor
{
    private OrderRepositoryInterface $orderRepository;
    private OrderFactory $orderFactory;
    private OrderCollectionFactory $orderCollectionFactory;
    private InvoiceService $invoiceService;
    private TransactionFactory $transactionFactory;
    private StatusResolver $statusResolver;
    private Config $config;
    private LoggerInterface $logger;
    private ResourceConnection $resourceConnection;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderFactory $orderFactory,
        OrderCollectionFactory $orderCollectionFactory,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        StatusResolver $statusResolver,
        Config $config,
        LoggerInterface $logger,
        ResourceConnection $resourceConnection
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->statusResolver = $statusResolver;
        $this->config = $config;
        $this->logger = $logger;
        $this->resourceConnection = $resourceConnection;
    }

    public function process(array $notification): void
    {
        $notifyType = $notification['notifyType'] ?? '';

        switch ($notifyType) {
            case 'PAYMENT_RESULT':
                $this->processPaymentResult($notification);
                break;
            case 'CAPTURE_RESULT':
                $this->processCaptureResult($notification);
                break;
            case 'REFUND_RESULT':
                $this->processRefundResult($notification);
                break;
            default:
                $this->logger->info('Antom notification type not handled', ['type' => $notifyType]);
        }
    }

    private function processPaymentResult(array $notification): void
    {
        $paymentRequestId = $notification['paymentRequestId'] ?? '';
        $paymentId = $notification['paymentId'] ?? '';
        $resultStatus = $notification['result']['resultStatus'] ?? '';

        if (empty($paymentRequestId) || empty($resultStatus)) {
            $this->logger->warning('Antom payment notification missing required fields');
            return;
        }

        $order = $this->findOrderByPaymentRequestId($paymentRequestId);
        if ($order === null) {
            $this->logger->warning('Antom notification: order not found', [
                'payment_request_id' => $paymentRequestId,
            ]);
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        try {
            // Lock the order row to prevent concurrent notification processing
            $connection->fetchRow(
                $connection->select()
                    ->from($this->resourceConnection->getTableName('sales_order'), ['entity_id'])
                    ->where('entity_id = ?', $order->getEntityId())
                    ->forUpdate(true)
            );

            // Reload order after acquiring lock to get fresh state
            $order = $this->orderFactory->create()->load($order->getEntityId());

            if ($this->statusResolver->isTerminalState($order->getState())) {
                $this->logger->info('Antom notification: order already in terminal state', [
                    'order_id' => $order->getIncrementId(),
                    'state' => $order->getState(),
                ]);
                $connection->commit();
                return;
            }

            $payment = $order->getPayment();
            $existingPaymentId = $payment->getAdditionalInformation('antom_payment_id');
            if (!empty($existingPaymentId) && $existingPaymentId === $paymentId) {
                $this->logger->info('Antom notification: duplicate, skipping', [
                    'order_id' => $order->getIncrementId(),
                    'payment_id' => $paymentId,
                ]);
                $connection->commit();
                return;
            }

            $captureMode = $this->config->getCaptureMode($order->getPayment()->getMethod(), (int)$order->getStoreId());
            $stateData = $this->statusResolver->resolvePaymentNotification($resultStatus, $captureMode);

            $payment->setAdditionalInformation('antom_payment_id', $paymentId);
            $payment->setData('antom_payment_id', $paymentId);

            if (!empty($notification['paymentAmount'])) {
                $payment->setAdditionalInformation(
                    'antom_payment_amount',
                    $notification['paymentAmount']['value'] ?? ''
                );
                $payment->setAdditionalInformation(
                    'antom_payment_currency',
                    $notification['paymentAmount']['currency'] ?? ''
                );
            }

            if ($this->statusResolver->shouldCreateInvoice($resultStatus, $captureMode)) {
                $this->createInvoiceForOrder($order, $paymentId);
            }

            if ($this->statusResolver->shouldMarkAuthorized($resultStatus, $captureMode)) {
                $payment->setIsTransactionClosed(false);
                $payment->setTransactionId($paymentId);
            }

            if ($resultStatus === 'F') {
                $order->cancel();
            }

            $order->setState($stateData['state']);
            $order->setStatus($stateData['status']);
            $order->addCommentToStatusHistory(
                sprintf('Antom payment notification: %s (paymentId: %s)', $resultStatus, $paymentId)
            );

            $this->orderRepository->save($order);

            $connection->commit();

            $this->logger->info('Antom payment notification processed', [
                'order_id' => $order->getIncrementId(),
                'result_status' => $resultStatus,
                'new_state' => $stateData['state'],
            ]);
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logger->error('Antom payment notification processing failed', [
                'order_id' => $order->getIncrementId(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function processCaptureResult(array $notification): void
    {
        $paymentId = $notification['paymentId'] ?? '';
        $resultStatus = $notification['result']['resultStatus'] ?? '';
        $captureId = $notification['captureId'] ?? '';

        if (empty($paymentId) || $resultStatus !== 'S') {
            return;
        }

        $order = $this->findOrderByAntomPaymentId($paymentId);
        if ($order === null) {
            $this->logger->warning('Antom capture notification: order not found', [
                'payment_id' => $paymentId,
            ]);
            return;
        }

        if ($order->hasInvoices()) {
            $this->logger->info('Antom capture notification: invoice already exists', [
                'order_id' => $order->getIncrementId(),
            ]);
            return;
        }

        $this->createInvoiceForOrder($order, $captureId ?: $paymentId);

        $order->setState(Order::STATE_PROCESSING);
        $order->setStatus(Order::STATE_PROCESSING);
        $order->addCommentToStatusHistory(
            sprintf('Antom capture notification: captured (captureId: %s)', $captureId)
        );

        $this->orderRepository->save($order);
    }

    private function processRefundResult(array $notification): void
    {
        $paymentId = $notification['paymentId'] ?? '';
        $resultStatus = $notification['result']['resultStatus'] ?? '';
        $refundId = $notification['refundId'] ?? '';
        $refundRequestId = $notification['refundRequestId'] ?? '';

        if (empty($paymentId)) {
            return;
        }

        $order = $this->findOrderByAntomPaymentId($paymentId);
        if ($order === null) {
            $this->logger->warning('Antom refund notification: order not found', [
                'payment_id' => $paymentId,
            ]);
            return;
        }

        $statusLabel = $resultStatus === 'S' ? 'success' : ($resultStatus === 'F' ? 'failed' : 'unknown');
        $order->addCommentToStatusHistory(
            sprintf(
                'Antom refund notification: %s (refundId: %s, refundRequestId: %s)',
                $statusLabel,
                $refundId,
                $refundRequestId
            )
        );

        if ($resultStatus === 'S' && !empty($refundId)) {
            $payment = $order->getPayment();
            $payment->setAdditionalInformation('antom_last_refund_id', $refundId);
        }

        $this->orderRepository->save($order);

        $this->logger->info('Antom refund notification processed', [
            'order_id' => $order->getIncrementId(),
            'result_status' => $resultStatus,
            'refund_id' => $refundId,
        ]);
    }

    private function createInvoiceForOrder(Order $order, string $transactionId): void
    {
        if (!$order->canInvoice()) {
            return;
        }

        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
        $invoice->setTransactionId($transactionId);
        $invoice->register();

        $transaction = $this->transactionFactory->create();
        $transaction->addObject($invoice)
            ->addObject($order)
            ->save();
    }

    /**
     * paymentRequestId format: "{increment_id}_{unique_suffix}"
     * Extracts increment_id by splitting at the last underscore.
     */
    private function findOrderByPaymentRequestId(string $paymentRequestId): ?Order
    {
        $lastUnderscorePos = strrpos($paymentRequestId, '_');
        if ($lastUnderscorePos === false) {
            return null;
        }

        $incrementId = substr($paymentRequestId, 0, $lastUnderscorePos);
        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);

        if ($order->getEntityId()) {
            return $order;
        }

        return null;
    }

    private function findOrderByAntomPaymentId(string $paymentId): ?Order
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->join(
            ['payment' => 'sales_order_payment'],
            'main_table.entity_id = payment.parent_id',
            []
        );
        $collection->addFieldToFilter(
            'payment.antom_payment_id',
            $paymentId
        );
        $collection->setPageSize(1);

        $order = $collection->getFirstItem();
        if (!$order || !$order->getEntityId()) {
            return null;
        }

        return $order;
    }
}
