<?php declare(strict_types=1);

namespace DUna\Payments\Plugin\Block\Widget\Button;

use Magento\Sales\Block\Adminhtml\Order\Create;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Backend\Block\Widget\Button\ButtonList;
use Magento\Backend\Block\Widget\Button\Toolbar as ToolbarContext;

class CapturePayment
{
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
    ): array {
        $order = false;
        $nameInLayout = $context->getNameInLayout();
        if ('sales_order_edit' == $nameInLayout) {
            $order = $context->getOrder();
        }

        if ($order) {
	        $buttonList->add(
	            'capture_payment',
	            [
	                'label' => __('Capture Payment'),
	                'class' => 'primary',
	                'id' => 'capture_payment'
	            ]
	        );
        }

        return [$context, $buttonList];        
    }
}