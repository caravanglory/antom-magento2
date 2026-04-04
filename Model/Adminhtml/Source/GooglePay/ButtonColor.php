<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Model\Adminhtml\Source\GooglePay;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonColor implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'default', 'label' => __('Default')],
            ['value' => 'black', 'label' => __('Black')],
            ['value' => 'white', 'label' => __('White')],
        ];
    }
}
