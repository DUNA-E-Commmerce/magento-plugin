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
        $order = $creditmemo->getOrder();
        $orderId = $creditmemo->getOrderId();
        $this->logger->debug('Refund Order Id: ' . $orderId);
    }
}
