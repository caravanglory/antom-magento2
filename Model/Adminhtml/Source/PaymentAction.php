<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Model\Adminhtml\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PaymentAction implements OptionSourceInterface
{
    public const ACTION_AUTHORIZE_CAPTURE = 'authorize_capture';
    public const ACTION_AUTHORIZE = 'authorize';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::ACTION_AUTHORIZE_CAPTURE, 'label' => __('Authorize and Capture')],
            ['value' => self::ACTION_AUTHORIZE, 'label' => __('Authorize Only')],
        ];
    }
}
