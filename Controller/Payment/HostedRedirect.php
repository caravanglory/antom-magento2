<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Controller\Payment;

use CaravanGlory\Antom\Model\Ui\ConfigProvider;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class HostedRedirect implements HttpGetActionInterface
{
    private RedirectFactory $redirectFactory;
    private CheckoutSession $checkoutSession;
    private OrderRepositoryInterface $orderRepository;
    private CommandPoolInterface $commandPool;
    private PaymentDataObjectFactoryInterface $paymentDataObjectFactory;
    private LoggerInterface $logger;
    private ManagerInterface $messageManager;

    public function __construct(
        RedirectFactory $redirectFactory,
        CheckoutSession $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        CommandPoolInterface $commandPool,
        PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
        LoggerInterface $logger,
        ManagerInterface $messageManager
    ) {
        $this->redirectFactory = $redirectFactory;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->commandPool = $commandPool;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->logger = $logger;
        $this->messageManager = $messageManager;
    }

    public function execute(): ResultInterface
    {
        $redirect = $this->redirectFactory->create();

        try {
            $order = $this->checkoutSession->getLastRealOrder();

            if (!$order || !$order->getEntityId()) {
                $this->messageManager->addErrorMessage((string)__('Order not found in current session'));
                return $redirect->setPath('checkout/cart');
            }

            if ($order->getPayment()->getMethod() !== ConfigProvider::CODE_HOSTED) {
                $this->messageManager->addErrorMessage((string)__('Invalid payment method'));
                return $redirect->setPath('checkout/cart');
            }

            $payment = $order->getPayment();
            $payment->setAdditionalInformation('payment_method_type', 'HOSTED');

            $paymentDataObject = $this->paymentDataObjectFactory->create($payment);

            $this->commandPool->get('create_session')->execute([
                'payment' => $paymentDataObject,
                'amount' => $order->getBaseGrandTotal(),
            ]);

            $this->orderRepository->save($order);

            $normalUrl = $payment->getAdditionalInformation('normal_url');

            if (empty($normalUrl)) {
                $this->messageManager->addErrorMessage((string)__('Failed to create payment session'));
                return $redirect->setPath('checkout/cart');
            }

            $this->logger->info('Antom hosted redirect', [
                'order_id' => $order->getIncrementId(),
                'redirect_url' => $normalUrl,
            ]);

            return $redirect->setUrl($normalUrl);
        } catch (\Exception $e) {
            $this->logger->error('Antom HostedRedirect error', [
                'message' => $e->getMessage(),
            ]);

            $this->messageManager->addErrorMessage((string)__('Payment session creation failed. Please try again.'));
            return $redirect->setPath('checkout/cart');
        }
    }
}
