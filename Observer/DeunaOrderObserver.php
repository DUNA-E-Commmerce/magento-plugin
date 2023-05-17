<?php
namespace DUna\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Monolog\Logger;
use Logtail\Monolog\LogtailHandler;

class CreateTransaction implements ObserverInterface {

    const LOGTAIL_SOURCE = 'plataformas_magento';
    const LOGTAIL_SOURCE_TOKEN = 'DB8ad3bQCZPAshmAEkj9hVLM';
    const AUTHORIZED = Transaction::TYPE_AUTH;

    protected $logger;

    public function __construct() {
        $this->logger = new Logger(self::LOGTAIL_SOURCE);
        $this->logger->pushHandler(new LogtailHandler(self::LOGTAIL_SOURCE_TOKEN));
    }

    public function execute(Observer $observer) {
        $observer_data = $observer->getData('custom_text');
        $order = $observer_data['order'];
        $payment = $order->getPayment();

        $this->logger->info('Create Transaction Observer', [
            'order' => $order->getId(),
        ]);

        $paymentStatus = $payment->getAdditionalInformation('deuna_payment_status');

        if ($paymentStatus === 'authorized') {
            $payment->setTransactionId("auth-{$payment->getId()}");
            $payment->setIsTransactionClosed(0);
            $payment->addTransaction(self::AUTHORIZED, null, true);
            $payment->setParentTransactionId(null);
            $payment->setShouldCloseParentTransaction(false);
            $payment->save();
        }

        return $this;
    }
}
