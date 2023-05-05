<?php

namespace DUna\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class OrderUpdateObserver implements ObserverInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;
    
    public function __construct(
        LoggerInterface $logger,
    ) {
        $this->logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $state = $order->getState();
        $status = $order->getStatus();

        if ($state === 'canceled' || $status === 'canceled'){
            $orderId = $order->getId();
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $orderRepository = $objectManager->get(\Magento\Sales\Api\OrderRepositoryInterface::class);

            $orderId = $order->getId();

            $order = $orderRepository->get($orderId);
            $payment = $order->getPayment();
            $orderToken = $payment->getAdditionalInformation('token');
            
            try {
                $resp = $this->cancelOrder($orderToken);
                $this->logger->info('La orden con ID ' . $orderId . ' ha sido cancelado.');
            } catch (\Exception $e) {
                $this->logger->critical('Error al cancelar la orden con ID ' . $orderId . ': ' . $e->getMessage());
            }
        }
    }

    private function cancelOrder($orderToken)
    {
        $endpoint = '/merchants/orders/'. $orderToken . '/cancel';
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $requestHelper = $objectManager->get(\DUna\Payments\Helper\RequestHelper::class);

        $response = $requestHelper->request($endpoint, 'POST', '', $headers);
        
        return $response;
    }

}


