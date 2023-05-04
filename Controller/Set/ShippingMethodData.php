<?php

namespace DUna\Payments\Controller\Set;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session;

class ShippingMethodData extends Action
{
    protected $checkoutSession;

    public function __construct(Context $context, Session $checkoutSession)
    {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
    }

    public function execute()
    {
        $shippingMethodCode = $this->getRequest()->getPost('shippingMethodCode');
        $this->checkoutSession->setData('shippingMethod', $shippingMethodCode);
    }
}
