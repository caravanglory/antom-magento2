<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Model\Adminhtml\Source\ApplePay;

use Magento\Framework\Data\OptionSourceInterface;

class ButtonColor implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'black', 'label' => __('Black')],
            ['value' => 'white', 'label' => __('White')],
            ['value' => 'white-outline', 'label' => __('White with Outline')],
        ];
    }
}
