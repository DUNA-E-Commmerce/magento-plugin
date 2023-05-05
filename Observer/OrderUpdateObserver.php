<?php

namespace DUna\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use DUna\Payments\Helper\RequestHelper;

class OrderUpdateObserver implements ObserverInterface
{
    protected $curl;
    protected $logger;
    protected $requestHelper;

    public function __construct(
        LoggerInterface $logger,
        RequestHelper $requestHelper
    ) {
        $this->logger = $logger;
        $this->requestHelper = $requestHelper;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $state = $order->getState();
        $status = $order->getStatus();

        if ($state === 'canceled' || $status === 'canceled'){
            $orderToken = $order->getOrderToken();
            $resp = $this->cancelOrder($orderToken);
            $this->logger->info('La orden con ID ' . $order->getId() . ' ha sido cancelado.');
        }

    }

    private function cancelOrder($orderToken)
    {
        $endpoint = '/merchants/orders/'. $orderToken . '/cancel';
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $response = $this->requestHelper->request($endpoint, 'POST', '', $headers);
        
        return $response;
    }

}


