<?php
namespace DUna\Payments\Plugin;

class ModifyDiscounts
{
    public function aroundSetSubtotalWithDiscount(\Magento\Quote\Model\Quote $subject, callable $proceed, $subtotalWithDiscount)
    {
        $totalDiscountPercentage = 0;
        foreach ($subject->getAllItems() as $item) {
            $totalDiscountPercentage += $item->getDiscountPercent();
        }

        $totalDiscountAmount = ($totalDiscountPercentage / 100) * $subject->getSubtotal();

        $modifiedSubtotalWithDiscount = $subtotalWithDiscount - $totalDiscountAmount;

        return $proceed($modifiedSubtotalWithDiscount);
    }
}
