<?php

namespace DUna\Payments\Model\PaymentMethods;

use Magento\Payment\Model\Method\AbstractMethod;

/**
 * DEUNA Checkout payment method model
 */
class AdyenCc extends AbstractMethod
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'adyen_cc';
    protected $_canAuthorize = true;
}