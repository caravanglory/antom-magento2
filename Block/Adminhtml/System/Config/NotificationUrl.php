<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Backend\Block\Template\Context;

class NotificationUrl extends Field
{
    private StoreManagerInterface $storeManager;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $baseUrl = rtrim((string)$this->storeManager->getStore()->getBaseUrl(), '/');
        $notificationUrl = $baseUrl . '/antom/notification';

        $element->setComment('');

        return sprintf(
            '<div style="padding-top:7px"><strong>%s</strong>'
            . '<p class="note" style="margin-top:5px"><span>%s</span></p></div>',
            $this->escapeHtml($notificationUrl),
            $this->escapeHtml((string)__('Configure this URL in your Antom merchant portal as the notification endpoint.'))
        );
    }
}
