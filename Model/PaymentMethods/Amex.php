<?php

namespace DUna\Payments\Model\PaymentMethods;

use Magento\Payment\Model\Method\AbstractMethod;

/**
 * DEUNA Checkout payment method model
 */
class Amex extends AbstractMethod
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'amex_hpf';
}