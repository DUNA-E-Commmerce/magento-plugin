<?php

namespace DUna\Payments\Controller\Remove;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use DUna\Payments\Api\CheckoutInterface;

class Coupon extends Action implements CsrfAwareActionInterface
{
    /**
     * @var CheckoutInterface
     */
    private $checkoutInterface;

    public function __construct(
        Context $context,
        CheckoutInterface $checkoutInterface
    ) {
        parent::__construct($context);
        $this->checkoutInterface = $checkoutInterface;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute() {
        $orderId = $this->getRequest()->getParam('order');
        return $this->checkoutInterface->removecoupon($orderId);
    }
}
