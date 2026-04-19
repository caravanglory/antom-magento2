<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Controller\Payment;

use CaravanGlory\Antom\Model\Ui\ConfigProvider;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Cancels the most recent Antom-backed order if it is still in pending_payment
 * and restores the corresponding quote so the customer can retry without
 * leaving an orphan order behind.
 */
class RestoreOrder implements HttpPostActionInterface
{
    private const ALLOWED_METHODS = [
        ConfigProvider::CODE_CC,
        ConfigProvider::CODE_GOOGLEPAY,
        ConfigProvider::CODE_APPLEPAY,
        ConfigProvider::CODE_HOSTED,
    ];

    private JsonFactory $jsonFactory;
    private CheckoutSession $checkoutSession;
    private OrderRepositoryInterface $orderRepository;
    private OrderManagementInterface $orderManagement;
    private LoggerInterface $logger;

    public function __construct(
        JsonFactory $jsonFactory,
        CheckoutSession $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        OrderManagementInterface $orderManagement,
        LoggerInterface $logger
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->orderManagement = $orderManagement;
        $this->logger = $logger;
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        try {
            $order = $this->checkoutSession->getLastRealOrder();

            if (!$order || !$order->getEntityId()) {
                return $result->setData(['status' => 'noop', 'reason' => 'no_last_order']);
            }

            $payment = $order->getPayment();
            $method = $payment ? (string)$payment->getMethod() : '';

            if (!in_array($method, self::ALLOWED_METHODS, true)) {
                return $result->setData(['status' => 'noop', 'reason' => 'not_antom_order']);
            }

            // Only cancel orders still pending payment. If the async notification
            // already flipped state to processing/complete/canceled we leave the
            // order alone so we don't fight the authoritative backend state.
            if ($order->getState() !== Order::STATE_PENDING_PAYMENT) {
                return $result->setData([
                    'status' => 'noop',
                    'reason' => 'terminal_state',
                    'state' => $order->getState(),
                ]);
            }

            $this->orderManagement->cancel((int)$order->getEntityId());
            $order = $this->orderRepository->get((int)$order->getEntityId());
            $order->addCommentToStatusHistory(
                'Cancelled by customer retry after Antom payment failure.'
            );
            $this->orderRepository->save($order);

            $this->checkoutSession->restoreQuote();

            $this->logger->info('Antom restoreOrder: order cancelled and quote restored', [
                'order_id' => $order->getIncrementId(),
            ]);

            return $result->setData([
                'status' => 'restored',
                'order_id' => $order->getIncrementId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Antom restoreOrder failed', [
                'message' => $e->getMessage(),
            ]);

            return $result->setHttpResponseCode(500)->setData([
                'status' => 'error',
                'message' => (string)__('Unable to restore the order. Please refresh and try again.'),
            ]);
        }
    }
}
