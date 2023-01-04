<?php

namespace Entrepids\StorePickup\Observer\Quote;

use Entrepids\StoresLocator\Model\StoresFactory;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;

class SubmitBefore implements ObserverInterface
{
	/** @var Entrepids\StoresLocator\Model\StoresFactory */
    private $_stores;

    /**
     * ShippingInformationManagement constructor.
     *
     * @param CartRepositoryInterface $quoteRepository
     * @param StoresFactory           $stores
     */
    public function __construct(
        StoresFactory $stores
    ){
        $this->_stores = $stores;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
        $quote = $observer->getQuote();
        $order = $observer->getOrder();
    	$carrierCode = $quote->getShippingAddress()->getShippingMethod();
    	if($carrierCode == 'bopis_bopis'){
	        $address = $order->getShippingAddress();
	        $billingAddress = $order->getBillingAddress();
	        $stores = $this->_stores->create()->load($quote->getBopisJdaStoreCode(),'jda_store_code');
	        $address->setCountryId('MX');
            $address->setCity($stores->getName());
            $address->setPostcode($stores->getZipCode());
            $address->setStreet($stores->getStreet()." ".$stores->getNumber());
            $address->setRegionId(569);
            $address->setRegionCode('EM');
            $address->setRegion($stores->getState());
            $billingAddress->setCountryId('MX');
            $billingAddress->setCity($stores->getName());
            $billingAddress->setPostcode($stores->getZipCode());
            $billingAddress->setStreet($stores->getStreet()." ".$stores->getNumber());
            $billingAddress->setRegionId(569);
            $billingAddress->setRegionCode('EM');
            $billingAddress->setRegion($stores->getState());
    	}
        $order->setData('bopis_jda_store_code', $quote->getData('bopis_jda_store_code'));
    
    }
}
