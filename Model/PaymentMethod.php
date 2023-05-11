<?php

namespace DUna\Payments\Model;

use Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\Order\Payment\Transaction;

/**
 * DEUNA Checkout payment method model
 */
class PaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod
{
    const REQUEST_TYPE_CAPTURE = 'DEUNA_CAPTURE';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'deunacheckout';

    /**
     * @var string
     */
    protected $_authTransactionId;

    /**
     * Authorize the payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        // authorize the payment
        //$result = parent::authorize($payment, $amount);

        // save the authorization transaction ID
        //$this->_authTransactionId = $payment->getTransactionId();

        //var_dump($this->_authTransactionId);die;
        $result = [
            'status' => 'success',
            'transaction_id' => 'AUTH12345',
            'fraud_score' => 0.8,
            'message' => 'Payment authorized successfully.'
        ];

        $this->_authTransactionId = $result['transaction_id'];

        // return the authorization result
        return $result;
    }

    /**
     * Capture payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        // Get order
        $order = $payment->getOrder();

        // Get payment method instance
        $methodInstance = $payment->getMethodInstance();

        // Get authorization transaction ID
        $authTransactionId = $payment->getTransactionId();

        // If no authorization transaction ID, then it is a direct capture
        if (!$authTransactionId) {
            // Authorize and capture payment

            $result = $this->authorizeCapturePayment($order, $payment, $methodInstance, $amount);

            // Check if result is successful
            if ($result) {

                // Set transaction ID and transaction type
                $payment->setTransactionId($payment->getTransactionId());


                $payment->setTransactionType(self::REQUEST_TYPE_CAPTURE);

                // Save payment and transaction
                $payment->save();
              
                $this->_saveTransaction($payment, $payment->getTransactionId());
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(__('The capture action is not available.'));
            }
        } else { // Otherwise, it is a delayed capture
            // Capture payment
            var_dump('2');die();

            $result = $this->capturePayment($order, $payment, $methodInstance, $authTransactionId, $amount);

            // Check if result is successful
            if ($result->getSuccess()) {
                // Set transaction ID and transaction type
                $payment->setTransactionId($payment->getTransactionId());
                $payment->setTransactionType(self::REQUEST_TYPE_CAPTURE);
                // Save payment and transaction
                $payment->save();
                $this->_saveTransaction($payment, $payment->getTransactionId(), self::REQUEST_TYPE_CAPTURE);
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(__('The capture action is not available.'));
            }
        }

        return $this;
    }

    /**
     * Authorize and capture payment method
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param \Magento\Payment\Model\MethodInterface $methodInstance
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function authorizeCapturePayment($order, $payment, $methodInstance, $amount)
    {
        $payment->setTransactionId($this->generateTransactionId($payment));
        $payment->setIsTransactionClosed(0);

        // Perform authorization
        $this->authorizePayment($payment, $amount);

        // Capture the authorized amount
        try {

            $this->captureAuthorizedAmount($order, $payment, $methodInstance, $amount);
        } catch (\Exception $e) {
            $this->voidAuthorization($payment, $methodInstance, $e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }

        return $this;
    }

    /**
     * Capture authorized amount
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param \Magento\Payment\Model\MethodInterface $methodInstance
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function captureAuthorizedAmount($order, $payment, $methodInstance, $amount)
    {

        if (!$payment->getTransactionId()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid transaction id.'));
        }

        $payment->setAmount($amount)
            ->setCurrencyCode($order->getBaseCurrencyCode());

        $this->_buildBasicRequest($payment);

        return $this;
    }

    /**
     * Generate a transaction ID for a payment.
     *
     * @param Payment $payment
     * @return string
     */
    function generateTransactionId($payment)
    {
        $orderId = $payment->getOrder()->getId();
        $incrementId = $payment->getOrder()->getIncrementId();
        $transactionId = $orderId . '-' . time() . '-' . $incrementId;
        
        return $transactionId;
    }

    /**
     * Authorize a payment.
     *
     * @param Payment $payment
     * @param float $amount
     * @return bool
     */
    function authorizePayment($payment, $amount)
    {
        try {
            $payment->authorize(true, $amount);
            $payment->setTransactionId($this->generateTransactionId($payment));
            $payment->setIsTransactionClosed(0);
            $payment->addTransaction('authorize', null, false);
            $payment->setParentTransactionId(null);
            $payment->save();
            
            return true;
        } catch (\Exception $e) {
            // Handle the exception or log the error message
            return false;
        }
    }

    /**
     * Build a basic request.
     *
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @return array
     */
    protected function _buildBasicRequest($payment)
    {
        // Build the basic request object
        $request = new \Magento\Framework\DataObject();

        // Set the transaction ID
        $transactionId = $this->generateTransactionId($payment);
        $request->setTransactionId($transactionId);

        // Set other request parameters
        // ...

        return $request;
    }

    /**
     * Save the transaction.
     *
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param string $transactionId
     */
    protected function _saveTransaction($payment, $transactionId)
    {
       // var_dump($transactionId);die();

        // Example implementation:
        $payment->setTransactionId($transactionId)
            ->setParentTransactionId(null)
            ->setIsTransactionClosed(1);


        $objectManager = ObjectManager::getInstance();
        $_transactionBuilder = $objectManager->create(\Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface::class);

        $transaction = $_transactionBuilder
            ->setPayment($payment)
            ->setOrder($payment->getOrder())
            ->setTransactionId($transactionId)
            ->build(Transaction::TYPE_AUTH);

        $transaction->setIsClosed(0);
        $transaction->save();


        var_dump($transaction->debug());die();
    }


}