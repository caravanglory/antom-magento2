<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Controller\Payment;

use CaravanGlory\Antom\Model\Ui\ConfigProvider;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class CreateSession implements HttpPostActionInterface
{
    private const ALLOWED_PAYMENT_TYPES = ['CARD', 'GOOGLEPAY', 'APPLEPAY'];

    private const METHOD_TYPE_MAP = [
        'CARD' => ConfigProvider::CODE_CC,
        'GOOGLEPAY' => ConfigProvider::CODE_GOOGLEPAY,
        'APPLEPAY' => ConfigProvider::CODE_APPLEPAY,
    ];

    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private CheckoutSession $checkoutSession;
    private OrderRepositoryInterface $orderRepository;
    private CommandPoolInterface $commandPool;
    private PaymentDataObjectFactoryInterface $paymentDataObjectFactory;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        CheckoutSession $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        CommandPoolInterface $commandPool,
        PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->commandPool = $commandPool;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->logger = $logger;
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        try {
            $body = json_decode((string)$this->request->getContent(), true);
            $incrementId = $body['order_increment_id'] ?? '';
            $paymentMethodType = strtoupper(trim($body['payment_method_type'] ?? ''));

            if (empty($incrementId)) {
                return $result->setHttpResponseCode(400)->setData([
                    'error' => true,
                    'message' => (string)__('Missing order_increment_id'),
                ]);
            }

            if (!in_array($paymentMethodType, self::ALLOWED_PAYMENT_TYPES, true)) {
                return $result->setHttpResponseCode(400)->setData([
                    'error' => true,
                    'message' => (string)__('Invalid payment_method_type'),
                ]);
            }

            $order = $this->checkoutSession->getLastRealOrder();

            if (!$order || !$order->getEntityId() || $order->getIncrementId() !== $incrementId) {
                return $result->setHttpResponseCode(404)->setData([
                    'error' => true,
                    'message' => (string)__('Order not found or does not belong to current session'),
                ]);
            }

            $expectedMethodCode = self::METHOD_TYPE_MAP[$paymentMethodType];
            if ($order->getPayment()->getMethod() !== $expectedMethodCode) {
                return $result->setHttpResponseCode(400)->setData([
                    'error' => true,
                    'message' => (string)__('Payment method mismatch'),
                ]);
            }

            $payment = $order->getPayment();
            $payment->setAdditionalInformation('payment_method_type', $paymentMethodType);

            $paymentDataObject = $this->paymentDataObjectFactory->create($payment);

            $this->commandPool->get('create_session')->execute([
                'payment' => $paymentDataObject,
                'amount' => $order->getGrandTotal(),
            ]);

            $this->orderRepository->save($order);

            $sessionData = $payment->getAdditionalInformation('payment_session_data');

            if (empty($sessionData)) {
                return $result->setHttpResponseCode(500)->setData([
                    'error' => true,
                    'message' => (string)__('Failed to create payment session'),
                ]);
            }

            return $result->setData([
                'paymentSessionData' => $sessionData,
                'paymentSessionId' => $payment->getAdditionalInformation('payment_session_id'),
                'paymentSessionExpiryTime' => $payment->getAdditionalInformation('payment_session_expiry_time'),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Antom CreateSession error', [
                'message' => $e->getMessage(),
            ]);

            return $result->setHttpResponseCode(500)->setData([
                'error' => true,
                'message' => (string)__('Payment session creation failed. Please try again.'),
            ]);
        }
    }
}
