<?php

namespace DUna\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Monolog\Logger;
use Logtail\Monolog\LogtailHandler;

class RefundObserver implements ObserverInterface
{
    /**
     *  @var \Psr\Log\LoggerInterface $logger
     */
    const LOGTAIL_SOURCE = 'magento-bedbath-mx';
    const LOGTAIL_SOURCE_TOKEN = 'DB8ad3bQCZPAshmAEkj9hVLM';

    protected $logger;

    public function __construct() {
        $this->logger = new Logger(self::LOGTAIL_SOURCE);
        $this->logger->pushHandler(new LogtailHandler(self::LOGTAIL_SOURCE_TOKEN));
    }

    public function execute(Observer $observer)
    {
        $creditmemo = $observer->getEvent()->getCreditmemo();
        $comments = $creditmemo->getCommentsCollection();
        $commentText = '';
        if (!empty($comments)) {
            foreach ($comments as $comment) {
                $commentText .= $comment->getComment() . "\n";
            }
        }

        $creditmemoId = $creditmemo->getId();

        $baseTotalRefunded = $creditmemo->getBaseTotalRefunded();
        $totalRefunded = $creditmemo->getTotalRefunded();

        $order = $creditmemo->getOrder();
        $orderId = $creditmemo->getOrderId();

        $payment = $order->getPayment();
        $orderToken = $payment->getAdditionalInformation('token');

        $this->logger->debug("Order {$orderId} in process Refund ...", [
            'creditmemoId' => $creditmemoId,
            'orderId' => $orderId,
            'orderToken' => $orderToken,
            'baseTotalRefunded' => $baseTotalRefunded,
            'totalRefunded' => $totalRefunded,
            'reason' => $commentText,
            'creditmemo' => $creditmemo,
        ]);
       
    }

    private function refundOrder($orderToken, $reason, $amount)
    {
        $endpoint = "/merchants/orders/{$orderToken}/refund";

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $body = [
            'reason' => $reason,
            'amount' => $amount,
        ];

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $requestHelper = $objectManager->get(\DUna\Payments\Helper\RequestHelper::class);

        $response = $requestHelper->request($endpoint, 'POST', json_encode($body), $headers);

    }
}
