<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway\Validator;

use Client\SignatureTool;
use CaravanGlory\Antom\Gateway\Config;
use Psr\Log\LoggerInterface;

class NotificationValidator
{
    private Config $config;
    private LoggerInterface $logger;

    public function __construct(
        Config $config,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function validate(
        string $httpMethod,
        string $path,
        string $requestTime,
        string $requestBody,
        string $signature,
        ?int $storeId = null
    ): bool {
        $clientId = $this->config->getClientId($storeId);
        $alipayPublicKey = $this->config->getAlipayPublicKey($storeId);

        if (empty($clientId) || empty($alipayPublicKey) || empty($signature)) {
            $this->logger->warning('Antom notification validation failed: missing credentials or signature');
            return false;
        }

        try {
            $result = SignatureTool::verify(
                $httpMethod,
                $path,
                $clientId,
                $requestTime,
                $requestBody,
                $signature,
                $alipayPublicKey
            );

            if ($result !== 1) {
                $this->logger->warning('Antom notification signature verification failed', [
                    'path' => $path,
                    'client_id' => $clientId,
                ]);
            }

            return $result === 1;
        } catch (\Exception $e) {
            $this->logger->error('Antom notification validation exception', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
