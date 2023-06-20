<?php

namespace DUna\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;

class CheckoutErrorHandler implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        $request = $observer->getEvent()->getRequest();
        $response = $observer->getEvent()->getResponse();
        
        if ($response->getContent() === 'Please correct the country code: %1.' || $response->getContent() === 'Invalid Country Code') {
            $errorMessage = __('Verifica tu información de entrega y código postal.');
            $response->setContent($errorMessage);
            $response->setHttpResponseCode(400);
        }
    }
}
