<?php

namespace DUna\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Monolog\Logger;
use Logtail\Monolog\LogtailHandler;

class OrderUpdateObserver implements ObserverInterface
{
    const LOGTAIL_SOURCE = 'magento-bedbath-mx';
    const LOGTAIL_SOURCE_TOKEN = 'DB8ad3bQCZPAshmAEkj9hVLM';

    protected $logger;
    
    public function __construct() {
        $this->logger = new Logger(self::LOGTAIL_SOURCE);
        $this->logger->pushHandler(new LogtailHandler(self::LOGTAIL_SOURCE_TOKEN));
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $state = $order->getState();
        $status = $order->getStatus();

        $this->logger->debug('OrderUpdateObserver: ' . $state . ' - ' . $status);

        if ($state === 'canceled' || $status === 'canceled'){
            $orderId = $order->getId();
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $orderRepository = $objectManager->get(\Magento\Sales\Api\OrderRepositoryInterface::class);

            $orderId = $order->getId();

            $order = $orderRepository->get($orderId);
            $payment = $order->getPayment();
            $orderToken = $payment->getAdditionalInformation('token');
            
            $this->logger->debug('Cancel Order', [
                'orderId' => $orderId,
                'orderToken' => $orderToken,
            ]);

            try {
                $resp = $this->cancelOrder($orderToken);
                $this->logger->debug("Order {$orderId} has been canceled successfully", [
                    'orderId' => $orderId,
                    'orderToken' => $orderToken,
                    'response' => $resp,
                ]);
            } catch (\Exception $e) {
                $this->logger->critical("Error canceling order ID: {$orderId}", [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'trace' => $e->getTrace(),
                ]);
            }
        }
    }

    private function cancelOrder($orderToken)
    {
        $endpoint = "/merchants/orders/{$orderToken}/cancel";
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

