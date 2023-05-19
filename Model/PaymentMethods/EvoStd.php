<?php

namespace DUna\Payments\Model\PaymentMethods;

use Magento\Payment\Model\Method\AbstractMethod;

/**
 * DEUNA Checkout payment method model
 */
class EvoStd extends AbstractMethod
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'tns_hpf';
    protected $_canAuthorize = true;
}