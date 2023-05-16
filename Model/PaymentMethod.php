<?php

namespace DUna\Payments\Model;

use Magento\Payment\Model\Method\AbstractMethod;

/**
 * DEUNA Checkout payment method model
 */
class PaymentMethod extends AbstractMethod
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'deunacheckout';
}
