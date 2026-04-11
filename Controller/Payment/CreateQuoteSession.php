<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Controller\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

class CreateQuoteSession implements HttpPostActionInterface
{
    private const ALLOWED_PAYMENT_TYPES = ['CARD', 'GOOGLEPAY', 'APPLEPAY'];

    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private CheckoutSession $checkoutSession;
    private CartRepositoryInterface $quoteRepository;
    private CommandPoolInterface $commandPool;
    private PaymentDataObjectFactoryInterface $paymentDataObjectFactory;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $quoteRepository,
        CommandPoolInterface $commandPool,
        PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->commandPool = $commandPool;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->logger = $logger;
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        try {
            $body = json_decode((string)$this->request->getContent(), true);
            $paymentMethodType = strtoupper(trim($body['payment_method_type'] ?? ''));

            if (!in_array($paymentMethodType, self::ALLOWED_PAYMENT_TYPES, true)) {
                return $result->setHttpResponseCode(400)->setData([
                    'error' => true,
                    'message' => (string)__('Invalid payment_method_type'),
                ]);
            }

            $quote = $this->checkoutSession->getQuote();

            if (!$quote || !$quote->getId()) {
                return $result->setHttpResponseCode(400)->setData([
                    'error' => true,
                    'message' => (string)__('No active quote found'),
                ]);
            }

            if (!$quote->getReservedOrderId()) {
                $quote->reserveOrderId();
                $this->quoteRepository->save($quote);
            }

            $payment = $quote->getPayment();
            $payment->setAdditionalInformation('payment_method_type', $paymentMethodType);

            $paymentDataObject = $this->paymentDataObjectFactory->create($payment);

            $this->commandPool->get('create_session')->execute([
                'payment' => $paymentDataObject,
                'amount' => $quote->getGrandTotal(),
            ]);

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
            $this->logger->error('Antom CreateQuoteSession error', [
                'message' => $e->getMessage(),
            ]);

            return $result->setHttpResponseCode(500)->setData([
                'error' => true,
                'message' => (string)__('Payment session creation failed. Please try again.'),
            ]);
        }
    }
}
