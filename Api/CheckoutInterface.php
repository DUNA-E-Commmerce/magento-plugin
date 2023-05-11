<?php


namespace DUna\Payments\Api;
/**
 * CheckoutInterface
 *
 * @category Interface
 * @package  CheckoutInterface
 */
interface CheckoutInterface
{
    /**
     * @param int $cartId
     * @return array|\Magento\Framework\Controller\Result\Json
     * @throws NoSuchEntityException
     */
    public function applycoupon(int $cartId);

    /**
     * @param int $cartId
     * @return array|\Magento\Framework\Controller\Result\Json
     * @throws NoSuchEntityException
     */
    public function removecoupon(int $cartId);
}
