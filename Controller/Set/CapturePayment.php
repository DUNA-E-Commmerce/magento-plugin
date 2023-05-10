<?php
namespace DUna\Payments\Controller\Set;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Sales\Api\OrderRepositoryInterface;

class CapturePayment extends \Magento\Backend\App\Action
{
    protected $_resultJsonFactory;
    protected $_orderRepository;
    protected $_registry;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        OrderRepositoryInterface $orderRepository,
        Registry $registry
    ) {
        parent::__construct($context);
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_orderRepository = $orderRepository;
        $this->_registry = $registry;
    }

    public function execute()
    {
        // Lógica de tu controlador aquí
        echo "Capture Payment Controller";
        exit;
        
        $resultJson = $this->_resultJsonFactory->create();
        $orderId = $this->getRequest()->getParam('order_id');

        try {
            $order = $this->_orderRepository->get($orderId);
            $payment = $order->getPayment();
            $paymentMethod = $payment->getMethod();

            // Add your custom logic for capturing payment here

            $result = ['success' => true];
        } catch (LocalizedException $e) {
            $result = ['success' => false, 'error' => $e->getMessage()];
        } catch (\Exception $e) {
            $result = ['success' => false, 'error' => __('An error occurred while capturing the payment.')];
        }

        return $resultJson->setData($result);
    }
}