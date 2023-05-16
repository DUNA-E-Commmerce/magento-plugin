<?php
namespace DUna\Payments\Plugin\Block\Widget\Button;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Backend\Block\Widget\Button\ButtonList;
use Magento\Backend\Block\Widget\Button\Toolbar as ToolbarContext;

class CapturePayment
{
    protected $orderId;

    /**
     * @param ToolbarContext $toolbar
     * @param AbstractBlock $context
     * @param ButtonList $buttonList
     * @return array
     */
    public function beforePushButtons(
        ToolbarContext $toolbar,
        AbstractBlock $context,
        ButtonList $buttonList
    ) {
        $order = $context->getOrder();

        // if ($order && $order->getPayment()->getAdditionalInformation('deuna_payment_status') === 'authorized') {
            $buttonList->add(
                'capture_payment',
                [
                    'label' => __('Capture Payment'),
                    'class' => 'primary',
                    'id' => 'capture_payment'
                ]
            );
        // }
        return [$context, $buttonList];
    }

}
