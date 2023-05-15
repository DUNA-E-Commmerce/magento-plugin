<?php
namespace DUna\Payments\Block\Adminhtml\Order\View;

use Magento\Framework\View\Element\Template;

class CaptureButton extends Template
{
    /**
     * @var \Magento\Framework\AuthorizationInterface
     */
    protected $_authorization;

    /**
     * @param Template\Context $context
     * @param \Magento\Framework\AuthorizationInterface $authorization
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        \Magento\Framework\AuthorizationInterface $authorization,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_authorization = $authorization;
    }

    /**
     * @return bool
     */
    public function canShowButton()
    {
        return $this->_authorization->isAllowed('Magento_Sales::actions_edit');
    }
}
