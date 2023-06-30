<?php
namespace DUna\Payments\Plugin;

class CartDiscount
{
    public function afterGetBaseSubtotalWithDiscount(\Magento\Quote\Model\Quote $subject, $result)
    {
        $discounts = $subject->getBaseDiscountAmount();

        $totalDiscount = 0;
        foreach ($discounts as $discount) {
            $totalDiscount += $discount;
        }

        $subtotalWithDiscount = $result - $totalDiscount;

        return $subtotalWithDiscount;
    }
}
