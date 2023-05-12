<?php

namespace DUna\Payments\Model;

/**
 * DEUNA Checkout payment method model
 */
class PaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'deunacheckout';

    /**
     * Can Authorize
     *
     * @var string
     */
    protected $_canAuthorize = 'true';

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
        // Check if payment can be captured
        if (!$this->canCapture()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The capture action is not available.'));
        }

        // Get order
        $order = $payment->getOrder();

        // Get payment method instance
        $methodInstance = $payment->getMethodInstance();

        // Get authorization transaction ID
        $authTransactionId = $payment->getParentTransactionId();

        // If no authorization transaction ID, then it is a direct capture
        if (!$authTransactionId) {
            // Authorize and capture payment
            $result = $this->authorizeCapturePayment($order, $payment, $methodInstance, $amount);

            // Check if result is successful
            if ($result->getSuccess()) {
                // Set transaction ID and transaction type
                $payment->setTransactionId($result->getTransactionId());
                $payment->setTransactionType(self::REQUEST_TYPE_CAPTURE);

                // Save payment and transaction
                $payment->save();
                $this->_saveTransaction($payment, $result->getTransactionId(), self::REQUEST_TYPE_CAPTURE);
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(__('The capture action is not available.'));
            }
        } else { // Otherwise, it is a delayed capture
            // Capture payment
            $result = $this->capturePayment($order, $payment, $methodInstance, $authTransactionId, $amount);

            // Check if result is successful
            if ($result->getSuccess()) {
                // Set transaction ID and transaction type
                $payment->setTransactionId($result->getTransactionId());
                $payment->setTransactionType(self::REQUEST_TYPE_CAPTURE);

                // Save payment and transaction
                $payment->save();
                $this->_saveTransaction($payment, $result->getTransactionId(), self::REQUEST_TYPE_CAPTURE);
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
        $payment->setTransactionId($this->generateTransactionId());
        $payment->setIsTransactionClosed(0);

        // Perform authorization
        $this->authorizePayment($order, $payment, $methodInstance, $amount);

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
        if (!$payment->getParentTransactionId()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid transaction id.'));
        }

        $payment->setAmount($amount)
            ->setCurrencyCode($order->getBaseCurrencyCode());

        $request = $this->_buildBasicRequest($payment);
        $request->setTransactionId($payment->getParentTransactionId());
        $request->setAmount($amount);
        $request->setIsFinalCapture(true);

        // Send the capture request to the payment gateway
        $response = $methodInstance->capture($request);

        if ($response->isSuccessful()) {
            // Payment was successfully captured
            $payment->setTransactionId($response->getTransactionReference());
            $payment->setIsTransactionClosed(1);
            $payment->capture();
        } else {
            // Capture request failed
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Payment capture error.') . ' ' . $response->getMessage()
            );
        }

        return $this;
    }
}
