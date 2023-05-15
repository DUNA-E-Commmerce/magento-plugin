<?php
namespace DUna\Payments\Block\Adminhtml\Order\View;

use Magento\Sales\Api\OrderRepositoryInterface;

class View extends \Magento\Backend\Block\Template
{
    protected $orderRepository;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        OrderRepositoryInterface $order,
        array $data = []
    ) {
        $this->orderRepository = $order;
        parent::__construct($context, $data);
    }

    public function _prepareLayout()
    {
        return parent::_prepareLayout();
    }

    public function getOrder($orderId)
    {
        $order = $this->orderRepository->get($orderId);

        return $order;
    }
}
