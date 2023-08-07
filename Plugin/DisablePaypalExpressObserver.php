<?php
/**
 * Copyright © Gigadesign. All rights reserved.
 */
declare(strict_types=1);

namespace DUna\Payments\Plugin;

class DisablePaypalExpressObserver
{
    public function aroundExecute(
        \Magento\Paypal\Model\Express $subject,
        \Closure $proceed,
        \Magento\Framework\Event\Observer $observer
    ) {
        $paymentMethodCode = $observer->getEvent()->getPayment()->getMethod();
        if ($paymentMethodCode === 'paypal_express') {
            return null;
        }

        return $proceed($observer);
    }
}
