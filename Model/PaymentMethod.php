<?php

namespace DUna\Payments\Model;

use Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\Order\Payment\Transaction;
use Monolog\Logger;
use Logtail\Monolog\LogtailHandler;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use Magento\Payment\Model\Method\AbstractMethod;
/**
 * DEUNA Checkout payment method model
 */
class PaymentMethod extends AbstractMethod
{
    const LOGTAIL_SOURCE = 'plataformas_magento';
    const LOGTAIL_SOURCE_TOKEN = 'DB8ad3bQCZPAshmAEkj9hVLM';

    /**
     * @var Logger
     */
    protected $logger;

    protected $_code = 'deunacheckout';

    public function authorize(InfoInterface $payment, $amount)
    {

        $this->logger = new Logger(self::LOGTAIL_SOURCE);
        $this->logger->pushHandler(new LogtailHandler(self::LOGTAIL_SOURCE_TOKEN));

        if ($amount <= 0) {
            $this->_logger->error('Invalid amount for authorization.');
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid amount for authorization.'));
        }
        $this->_logger->info('Authorizing payment. In Proccess...');

        $payment->setTransactionId('autorizacion_' . uniqid())
            ->setIsTransactionClosed(false);

        return $this;
    }

    public function capture(InfoInterface $payment, $amount)
    {
        $this->logger = new Logger(self::LOGTAIL_SOURCE);
        $this->logger->pushHandler(new LogtailHandler(self::LOGTAIL_SOURCE_TOKEN));

        if ($amount <= 0) {
            $this->_logger->error('Invalid amount for capture.');
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid amount for capture.'));
        }

        try {

            // Generate the transaction ID for the capture
            $transactionId = $payment->getId() . '-capture';

            $this->_logger->info('Capturing payment. In Proccess...', ['transactionId' => $transactionId]);

            // Set the capture data on the Payment object
            $payment->setTransactionId($transactionId);
            $payment->setIsTransactionClosed(0);
            $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);
            $payment->setParentTransactionId(null);
            $payment->setShouldCloseParentTransaction(false);

            // Set additional payment information
            $payment->setAdditionalInformation('payment_method', $this->_code);
            $payment->setAdditionalInformation('captured_amount', $amount);
            $payment->save();

            $this->_logger->info('Generating capture transaction.');

            // Create a new capture transaction
            $transaction = $payment->addTransaction(Transaction::TYPE_CAPTURE);
            $transaction->setParentTxnId(null);
            $transaction->setIsClosed(1);
            $transaction->setAdditionalInformation(
                Transaction::RAW_DETAILS,
                [
                    'payment_method' => $this->_code,
                    'captured_amount' => $amount,
                ]
            );
            $transaction->save();

            $this->_logger->info('Updating order state.');

            $order = $payment->getOrder();

            $totalPaid = $order->getTotalPaid() + $amount;
            $order->setTotalPaid($totalPaid);

            $totalDue = $order->getGrandTotal() - $totalPaid;
            $order->setTotalDue($totalDue);
            
            // Update the order state to "Processing"
            $order->setState(Order::STATE_PROCESSING)
                ->setStatus(Order::STATE_PROCESSING)
                ->addStatusToHistory(
                    Order::STATE_PROCESSING,
                    __('Payment captured successfully.')
                )
                ->save();

            return true;
        } catch (\Exception $e) {
            // Catch any exceptions and log them
            $errorMessage = $e->getMessage();
            $this->_logger->error($errorMessage);

            return false;
        }
    }
}
