<?php

namespace DUna\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use DUna\Payments\Helper\RequestHelper;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class OrderUpdateObserver implements ObserverInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
     * @var RequestHelper
     */
    protected $requestHelper;
    
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    public function __construct(
        LoggerInterface $logger,
        RequestHelper $requestHelper,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->logger = $logger;
        $this->requestHelper = $requestHelper;
        $this->orderRepository = $orderRepository;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $state = $order->getState();
        $status = $order->getStatus();

        if ($state === 'canceled' || $status === 'canceled'){
            $orderId = $order->getId();
            $order = $this->orderRepository->get($orderId);
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

        $response = $this->requestHelper->request($endpoint, 'POST', '', $headers);
        
        return $response;
    }

}


