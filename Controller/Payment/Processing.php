<?php

declare(strict_types=1);

namespace CaravanGlory\Antom\Controller\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Processing implements HttpGetActionInterface
{
    private CheckoutSession $checkoutSession;
    private PageFactory $pageFactory;
    private RedirectFactory $redirectFactory;

    public function __construct(
        CheckoutSession $checkoutSession,
        PageFactory $pageFactory,
        RedirectFactory $redirectFactory
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->pageFactory = $pageFactory;
        $this->redirectFactory = $redirectFactory;
    }

    public function execute(): ResultInterface
    {
        $order = $this->checkoutSession->getLastRealOrder();

        if (!$order || !$order->getEntityId()) {
            return $this->redirectFactory->create()->setPath('checkout/cart');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string)__('Confirming Payment'));

        return $page;
    }
}
