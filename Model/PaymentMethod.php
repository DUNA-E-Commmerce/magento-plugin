<?php

namespace DUna\Payments\Model;

/**
 * DEUNA Checkout payment method model
 */
class PaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'deunacheckout';
}