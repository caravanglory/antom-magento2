<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Model\Adminhtml\Source\ApplePay;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonType implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'buy', 'label' => __('Buy')],
            ['value' => 'pay', 'label' => __('Pay')],
            ['value' => 'plain', 'label' => __('Plain (No text)')],
            ['value' => 'order', 'label' => __('Order')],
            ['value' => 'checkout', 'label' => __('Checkout')],
        ];
    }
}
