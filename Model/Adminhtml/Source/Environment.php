<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Model\Adminhtml\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Environment implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'sandbox', 'label' => __('Sandbox')],
            ['value' => 'live', 'label' => __('Live (Production)')],
        ];
    }
}
