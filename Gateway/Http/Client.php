<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway\Http;

use Client\DefaultAlipayClient;
use CaravanGlory\Antom\Gateway\Config;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Psr\Log\LoggerInterface;
use Request\AlipayRequest;

class Client implements ClientInterface
{
    private const GATEWAY_URL = 'https://open-sea-global.alipay.com';

    private Config $config;
    private LoggerInterface $logger;

    public function __construct(
        Config $config,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function placeRequest(TransferInterface $transferObject): array
    {
        $request = $transferObject->getBody();

        if (!$request instanceof AlipayRequest) {
            throw new ClientException(
                __('Invalid request: expected AlipayRequest instance, got %1', get_debug_type($request))
            );
        }

        $storeId = $transferObject->getHeaders()['store_id'] ?? null;

        try {
            $client = $this->createSdkClient($storeId);
            $request->setClientId($this->config->getClientId($storeId));

            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('Antom API request', [
                    'path' => $request->getPath(),
                    'body' => $this->redactSensitiveData(
                        $this->toAssocArray($request)
                    ),
                ]);
            }

            $response = $client->execute($request);
            $responseArray = $this->toAssocArray($response);

            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('Antom API response', [
                    'path' => $request->getPath(),
                    'result' => $responseArray['result'] ?? [],
                ]);
            }

            return $responseArray;
        } catch (\Exception $e) {
            $this->logger->error('Antom API error', [
                'path' => $request->getPath(),
                'message' => $e->getMessage(),
            ]);

            throw new ClientException(__('Antom API error: %1', $e->getMessage()), $e);
        }
    }

    private function createSdkClient(?int $storeId): DefaultAlipayClient
    {
        return new DefaultAlipayClient(
            self::GATEWAY_URL,
            $this->config->getMerchantPrivateKey($storeId),
            $this->config->getAlipayPublicKey($storeId),
            $this->config->getClientId($storeId)
        );
    }

    /**
     * Convert SDK request/response objects to a clean associative array via the
     * SDK's own JsonSerializable implementation. Avoids the null-byte keys that
     * a raw (array) cast produces for protected/private properties.
     */
    private function toAssocArray(mixed $data): array
    {
        if ($data === null) {
            return [];
        }

        $encoded = json_encode($data);
        if ($encoded === false) {
            return [];
        }

        $decoded = json_decode($encoded, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function redactSensitiveData(array $data): array
    {
        $sensitiveKeys = [
            'merchantPrivateKey',
            'alipayPublicKey',
            'cardNo',
            'cvv',
            'expMonth',
            'expYear',
        ];

        foreach ($data as $key => &$value) {
            if (in_array($key, $sensitiveKeys, true)) {
                $value = '***REDACTED***';
            } elseif (is_array($value)) {
                $value = $this->redactSensitiveData($value);
            }
        }

        return $data;
    }
}