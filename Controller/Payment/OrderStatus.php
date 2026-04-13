<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Controller\Payment;

use CaravanGlory\Antom\Gateway\AmountConverter;
use CaravanGlory\Antom\Gateway\Config;
use CaravanGlory\Antom\Model\Order\StatusResolver;
use CaravanGlory\Antom\Model\Ui\ConfigProvider;
use Client\DefaultAlipayClient;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;
use Request\pay\AlipayPayQueryRequest;

class OrderStatus implements HttpPostActionInterface
{
    private const GATEWAY_URL = 'https://open-sea-global.alipay.com';

    private const RESULT_STATUS_SUCCESS = 'S';
    private const RESULT_STATUS_UNKNOWN = 'U';
    private const RESULT_CODE_IN_PROCESS = 'PAYMENT_IN_PROCESS';

    private const PAYMENT_STATUS_SUCCESS = 'SUCCESS';
    private const PAYMENT_STATUS_FAIL = 'FAIL';
    private const PAYMENT_STATUS_PENDING = 'PENDING';
    private const PAYMENT_STATUS_PROCESSING = 'PROCESSING';

    private const ALLOWED_METHODS = [
        ConfigProvider::CODE_CC,
        ConfigProvider::CODE_GOOGLEPAY,
        ConfigProvider::CODE_APPLEPAY,
    ];

    private JsonFactory $jsonFactory;
    private CheckoutSession $checkoutSession;
    private CartRepositoryInterface $quoteRepository;
    private OrderRepositoryInterface $orderRepository;
    private OrderFactory $orderFactory;
    private InvoiceService $invoiceService;
    private TransactionFactory $transactionFactory;
    private StatusResolver $statusResolver;
    private Config $config;
    private LoggerInterface $logger;
    private ResourceConnection $resourceConnection;

    public function __construct(
        JsonFactory $jsonFactory,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $quoteRepository,
        OrderRepositoryInterface $orderRepository,
        OrderFactory $orderFactory,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        StatusResolver $statusResolver,
        Config $config,
        LoggerInterface $logger,
        ResourceConnection $resourceConnection
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->statusResolver = $statusResolver;
        $this->config = $config;
        $this->logger = $logger;
        $this->resourceConnection = $resourceConnection;
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        try {
            $order = $this->checkoutSession->getLastRealOrder();

            if (!$order || !$order->getEntityId()) {
                return $result->setHttpResponseCode(404)->setData([
                    'status' => 'error',
                    'message' => (string)__('Order not found in current session.'),
                ]);
            }

            $payment = $order->getPayment();
            if (!$payment || !in_array((string)$payment->getMethod(), self::ALLOWED_METHODS, true)) {
                return $result->setHttpResponseCode(400)->setData([
                    'status' => 'error',
                    'message' => (string)__('Invalid Antom payment method for order status inquiry.'),
                ]);
            }

            $knownStatus = $this->getKnownOrderStatus($order);
            if ($knownStatus !== null) {
                return $result->setData($knownStatus);
            }

            $response = $this->queryPayment($order);

            return $result->setData($this->handleInquiryResponse($order, $response));
        } catch (\Exception $e) {
            $this->logger->error('Antom order status inquiry error', [
                'message' => $e->getMessage(),
            ]);

            return $result->setHttpResponseCode(500)->setData([
                'status' => 'error',
                'message' => (string)__('Unable to determine payment status right now.'),
            ]);
        }
    }

    private function getKnownOrderStatus(Order $order): ?array
    {
        if ($order->getState() === Order::STATE_CANCELED) {
            return [
                'status' => 'failed',
                'message' => (string)__('Payment failed.'),
            ];
        }

        if (in_array($order->getState(), [
            Order::STATE_PROCESSING,
            Order::STATE_COMPLETE,
            Order::STATE_CLOSED,
        ], true)) {
            return ['status' => 'success'];
        }

        return null;
    }

    private function queryPayment(Order $order): array
    {
        [$paymentId, $paymentRequestId] = $this->getPaymentReferences($order);

        if ($paymentId === '' && $paymentRequestId === '') {
            throw new \RuntimeException('Missing Antom payment reference on order.');
        }

        $storeId = (int)$order->getStoreId();
        $clientId = $this->config->getClientId($storeId);

        $client = new DefaultAlipayClient(
            self::GATEWAY_URL,
            $this->config->getMerchantPrivateKey($storeId),
            $this->config->getAlipayPublicKey($storeId),
            $clientId
        );

        $request = new AlipayPayQueryRequest();
        $request->setClientId($clientId);

        if ($paymentId !== '') {
            $request->setPaymentId($paymentId);
        } else {
            $request->setPaymentRequestId($paymentRequestId);
        }

        return $this->objectToArray($client->execute($request));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function getPaymentReferences(Order $order): array
    {
        $payment = $order->getPayment();
        $paymentId = (string)$payment->getAdditionalInformation('antom_payment_id');
        $paymentRequestId = (string)$payment->getAdditionalInformation('payment_request_id');

        if ($paymentRequestId !== '' || !$order->getQuoteId()) {
            return [$paymentId, $paymentRequestId];
        }

        try {
            $quote = $this->quoteRepository->get((int)$order->getQuoteId());
            $quotePayment = $quote->getPayment();

            if ($quotePayment) {
                $paymentRequestId = (string)$quotePayment->getAdditionalInformation('payment_request_id');

                if ($paymentRequestId !== '') {
                    $payment->setAdditionalInformation('payment_request_id', $paymentRequestId);
                    $this->orderRepository->save($order);
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('Antom inquiry could not restore quote payment metadata', [
                'order_id' => $order->getIncrementId(),
                'message' => $e->getMessage(),
            ]);
        }

        return [$paymentId, $paymentRequestId];
    }

    private function handleInquiryResponse(Order $order, array $response): array
    {
        $result = is_array($response['result'] ?? null) ? $response['result'] : [];
        $resultStatus = (string)($result['resultStatus'] ?? '');
        $resultCode = (string)($result['resultCode'] ?? '');
        $message = (string)($response['paymentResultMessage'] ?? $result['resultMessage'] ?? '');
        $paymentStatus = strtoupper((string)($response['paymentStatus'] ?? ''));

        if ($resultStatus === self::RESULT_STATUS_SUCCESS) {
            if ($paymentStatus === self::PAYMENT_STATUS_SUCCESS) {
                $this->applyTerminalInquiryResult($order, $response, self::RESULT_STATUS_SUCCESS, self::PAYMENT_STATUS_SUCCESS);
                return ['status' => 'success'];
            }

            if ($paymentStatus === self::PAYMENT_STATUS_FAIL) {
                $this->applyTerminalInquiryResult($order, $response, 'F', self::PAYMENT_STATUS_FAIL);
                return [
                    'status' => 'failed',
                    'message' => $message !== '' ? $message : (string)__('Payment failed.'),
                ];
            }

            if (in_array($paymentStatus, [self::PAYMENT_STATUS_PENDING, self::PAYMENT_STATUS_PROCESSING], true)) {
                $this->persistIntermediateMetadata($order, $response, $paymentStatus);

                return [
                    'status' => 'processing',
                    'message' => $message !== '' ? $message : (string)__('Payment is still processing.'),
                ];
            }
        }

        if ($resultStatus === self::RESULT_STATUS_UNKNOWN && $resultCode === self::RESULT_CODE_IN_PROCESS) {
            $this->persistIntermediateMetadata($order, $response, self::PAYMENT_STATUS_PROCESSING);

            return [
                'status' => 'processing',
                'message' => $message !== '' ? $message : (string)__('Payment is still processing.'),
            ];
        }

        $this->logger->warning('Antom inquiry returned unexpected result', [
            'order_id' => $order->getIncrementId(),
            'response' => $response,
        ]);

        return [
            'status' => 'error',
            'message' => $message !== '' ? $message : (string)__('Unable to determine payment status right now.'),
        ];
    }

    private function persistIntermediateMetadata(Order $order, array $response, string $paymentStatus): void
    {
        if ($this->applyPaymentMetadata($order->getPayment(), $response, $paymentStatus)) {
            $this->orderRepository->save($order);
        }
    }

    private function applyTerminalInquiryResult(
        Order $order,
        array $response,
        string $resultStatus,
        string $paymentStatus
    ): void {
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        try {
            $connection->fetchRow(
                $connection->select()
                    ->from($this->resourceConnection->getTableName('sales_order'), ['entity_id'])
                    ->where('entity_id = ?', $order->getEntityId())
                    ->forUpdate(true)
            );

            $order = $this->orderFactory->create()->load($order->getEntityId());

            if ($this->getKnownOrderStatus($order) !== null) {
                $connection->commit();
                return;
            }

            $payment = $order->getPayment();
            $this->applyPaymentMetadata($payment, $response, $paymentStatus);

            if ($resultStatus === self::RESULT_STATUS_SUCCESS && !$this->verifyPaymentAmount($order, $response)) {
                $paymentId = (string)($response['paymentId'] ?? '');

                $order->setState(Order::STATE_PAYMENT_REVIEW);
                $order->setStatus(Order::STATE_PAYMENT_REVIEW);
                $order->addCommentToStatusHistory(
                    sprintf(
                        'Antom inquiry amount mismatch: notified %s %s, expected %s %s (paymentId: %s)',
                        $response['paymentAmount']['value'] ?? 'N/A',
                        $response['paymentAmount']['currency'] ?? 'N/A',
                        AmountConverter::toMinorUnits((float)$order->getBaseGrandTotal(), $order->getBaseCurrencyCode()),
                        $order->getBaseCurrencyCode(),
                        $paymentId !== '' ? $paymentId : 'N/A'
                    )
                );

                $this->orderRepository->save($order);
                $connection->commit();
                return;
            }

            $captureMode = $this->config->getCaptureMode($payment->getMethod(), (int)$order->getStoreId());
            $stateData = $this->statusResolver->resolvePaymentNotification($resultStatus, $captureMode);
            $paymentId = (string)($response['paymentId'] ?? '');

            if ($resultStatus === self::RESULT_STATUS_SUCCESS) {
                if ($this->statusResolver->shouldCreateInvoice($resultStatus, $captureMode)) {
                    $this->createInvoiceForOrder($order, $paymentId !== '' ? $paymentId : $order->getIncrementId());
                }

                if ($this->statusResolver->shouldMarkAuthorized($resultStatus, $captureMode)) {
                    $payment->setIsTransactionClosed(false);
                    $payment->setTransactionId($paymentId !== '' ? $paymentId : $order->getIncrementId());
                }
            } else {
                $order->cancel();
            }

            $order->setState($stateData['state']);
            $order->setStatus($stateData['status']);
            $order->addCommentToStatusHistory(
                sprintf(
                    'Antom inquiry result: %s%s',
                    $paymentStatus,
                    $paymentId !== '' ? ' (paymentId: ' . $paymentId . ')' : ''
                )
            );

            $this->orderRepository->save($order);
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    private function applyPaymentMetadata(Payment $payment, array $response, string $paymentStatus): bool
    {
        $hasChanges = false;

        if ($payment->getAdditionalInformation('antom_payment_status') !== $paymentStatus) {
            $payment->setAdditionalInformation('antom_payment_status', $paymentStatus);
            $hasChanges = true;
        }

        $paymentId = (string)($response['paymentId'] ?? '');
        if ($paymentId !== '' && $payment->getAdditionalInformation('antom_payment_id') !== $paymentId) {
            $payment->setAdditionalInformation('antom_payment_id', $paymentId);
            $payment->setData('antom_payment_id', $paymentId);
            $hasChanges = true;
        }

        $paymentAmount = $response['paymentAmount'] ?? null;
        if (is_array($paymentAmount)) {
            $value = (string)($paymentAmount['value'] ?? '');
            $currency = (string)($paymentAmount['currency'] ?? '');

            if ($value !== '' && $payment->getAdditionalInformation('antom_payment_amount') !== $value) {
                $payment->setAdditionalInformation('antom_payment_amount', $value);
                $hasChanges = true;
            }

            if ($currency !== '' && $payment->getAdditionalInformation('antom_payment_currency') !== $currency) {
                $payment->setAdditionalInformation('antom_payment_currency', $currency);
                $hasChanges = true;
            }
        }

        return $hasChanges;
    }

    private function verifyPaymentAmount(Order $order, array $response): bool
    {
        $paymentAmount = $response['paymentAmount'] ?? null;
        if (!is_array($paymentAmount)) {
            return true;
        }

        $notifiedAmount = (string)($paymentAmount['value'] ?? '');
        $notifiedCurrency = (string)($paymentAmount['currency'] ?? '');

        if ($notifiedAmount === '' || $notifiedCurrency === '') {
            return true;
        }

        $expectedAmount = AmountConverter::toMinorUnits(
            (float)$order->getBaseGrandTotal(),
            $order->getBaseCurrencyCode()
        );

        return $notifiedAmount === $expectedAmount
            && $notifiedCurrency === $order->getBaseCurrencyCode();
    }

    private function createInvoiceForOrder(Order $order, string $transactionId): void
    {
        if (!$order->canInvoice()) {
            return;
        }

        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
        $invoice->setTransactionId($transactionId);
        $invoice->register();

        $transaction = $this->transactionFactory->create();
        $transaction->addObject($invoice)
            ->addObject($order)
            ->save();
    }

    private function objectToArray(mixed $data): array
    {
        if (is_object($data)) {
            $data = (array)$data;
        }

        if (is_array($data)) {
            return array_map(
                fn ($item) => is_object($item) || is_array($item) ? $this->objectToArray($item) : $item,
                $data
            );
        }

        return [$data];
    }
}
