<?php

namespace DUna\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;

class ApplyDiscount implements ObserverInterface
{
    protected $quoteRepository;

    public function __construct(
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
    ) {
        $this->quoteRepository = $quoteRepository;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $quote = $observer->getEvent()->getQuote();

        $totalDiscountPercentage = 0;
        foreach ($quote->getAllItems() as $item) {
            $totalDiscountPercentage += $item->getDiscountPercent();
        }

        $totalDiscountAmount = ($totalDiscountPercentage / 100) * $quote->getSubtotal();

        $quote->setSubtotal($quote->getSubtotal() - $totalDiscountAmount);
        $quote->setBaseSubtotal($quote->getBaseSubtotal() - $totalDiscountAmount);
    }
}
