<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Gateway\Http;

use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransferFactory implements TransferFactoryInterface
{
    private TransferBuilder $transferBuilder;

    public function __construct(TransferBuilder $transferBuilder)
    {
        $this->transferBuilder = $transferBuilder;
    }

    public function create(array $request): TransferInterface
    {
        return $this->transferBuilder
            ->setBody($request['request'] ?? null)
            ->setHeaders(['store_id' => $request['store_id'] ?? null])
            ->build();
    }
}
