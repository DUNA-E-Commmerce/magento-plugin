<?php
namespace DUna\Payments\Plugin;

use Magento\Sales\Block\Adminhtml\Order\View as OrderViewBlock;

class OrderView
{
    /**
     * @param OrderViewBlock $subject
     * @param string $html
     * @return string
     */
    public function afterToHtml(OrderViewBlock $subject, $html)
    {
        $orderId = $subject->getOrderId();
        $hiddenInputHtml = '<input type="hidden" id="order_id_to_capture" name="order_id_to_capture" value="' . $orderId . '">';

        $html .= $hiddenInputHtml;

        return $html;
    }
}
