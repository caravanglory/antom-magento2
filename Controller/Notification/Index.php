<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Controller\Notification;

use CaravanGlory\Antom\Gateway\Validator\NotificationValidator;
use CaravanGlory\Antom\Model\Notification\Processor;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;

class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private NotificationValidator $notificationValidator;
    private Processor $processor;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        NotificationValidator $notificationValidator,
        Processor $processor,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->notificationValidator = $notificationValidator;
        $this->processor = $processor;
        $this->logger = $logger;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        try {
            $rawBody = (string)$this->request->getContent();

            if (empty($rawBody)) {
                $this->logger->warning('Antom notification received with empty body');
                return $result->setData($this->buildErrorResponse('INVALID_REQUEST', 'Empty request body'));
            }

            $requestTime = (string)$this->request->getHeader('request-time');
            $signature = (string)$this->request->getHeader('signature');
            $clientId = (string)$this->request->getHeader('client-id');
            $requestPath = (string)$this->request->getPathInfo();

            $isValid = $this->notificationValidator->validate(
                'POST',
                $requestPath,
                $requestTime,
                $rawBody,
                $signature
            );

            if (!$isValid) {
                $this->logger->warning('Antom notification signature verification failed', [
                    'client_id' => $clientId,
                ]);
                return $result->setData($this->buildErrorResponse('INVALID_SIGNATURE', 'Signature verification failed'));
            }

            $body = json_decode($rawBody, true);

            if (!is_array($body)) {
                $this->logger->warning('Antom notification received with invalid JSON');
                return $result->setData($this->buildErrorResponse('INVALID_REQUEST', 'Invalid JSON body'));
            }

            $notifyType = $body['notifyType'] ?? '';

            $this->logger->info('Antom notification received', [
                'type' => $notifyType,
                'payment_request_id' => $body['paymentRequestId'] ?? '',
                'payment_id' => $body['paymentId'] ?? '',
            ]);

            $this->processor->process($body);

            return $result->setData($this->buildSuccessResponse());
        } catch (\Exception $e) {
            $this->logger->error('Antom notification processing error', [
                'message' => $e->getMessage(),
            ]);

            return $result->setData($this->buildErrorResponse('SYSTEM_ERROR', 'Internal processing error'));
        }
    }

    private function buildSuccessResponse(): array
    {
        return [
            'result' => [
                'resultCode' => 'SUCCESS',
                'resultStatus' => 'S',
                'resultMessage' => 'success',
            ],
        ];
    }

    private function buildErrorResponse(string $code, string $message): array
    {
        return [
            'result' => [
                'resultCode' => $code,
                'resultStatus' => 'F',
                'resultMessage' => $message,
            ],
        ];
    }
}
