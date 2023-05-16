<?php
namespace DUna\Payments\Model;

use Magento\Framework\Webapi\Rest\Request;
use DUna\Payments\Model\OrderTokens;
use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteFactory as Quote;
use Magento\Quote\Api\CartRepositoryInterface as CRI;
use DUna\Payments\Helper\Data;
use DUna\Payments\Model\Order\ShippingMethods;
use Exception;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Monolog\Logger;
use Logtail\Monolog\LogtailHandler;

class PostManagement {

    const LOGTAIL_SOURCE = 'plataformas_magento';
    const LOGTAIL_SOURCE_TOKEN = 'DB8ad3bQCZPAshmAEkj9hVLM';
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'deunacheckout';
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var OrderTokens
     */
    private $orderTokens;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var \Magento\Quote\Model\QuoteFactory
     */
    protected $quoteFactory;

    protected $quoteModel;

    /**
     * @var CRI
     */
    protected $cri;

    /**
     * @var Data
     */
    protected $helper;

    protected $customerFactory;

    protected $customerRepository;

    protected $storeManager;

    protected $orderRepository;

    protected $deunaShipping;

    public function __construct(
        Request $request,
        QuoteManagement $quoteManagement,
        QuoteFactory $quoteFactory,
        OrderTokens $orderTokens,
        Quote $quoteModel,
        CRI $cri,
        Data $helper,
        CustomerFactory $customerFactory,
        CustomerRepositoryInterface $customerRepository,
        StoreManagerInterface $storeManager,
        OrderRepositoryInterface $orderRepository,
        ShippingMethods $deunaShipping,
    ) {
        $this->request = $request;
        $this->quoteManagement = $quoteManagement;
        $this->quoteFactory = $quoteFactory;
        $this->orderTokens = $orderTokens;
        $this->quoteModel = $quoteModel;
        $this->cri = $cri;
        $this->helper = $helper;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->storeManager = $storeManager;
        $this->orderRepository = $orderRepository;
        $this->deunaShipping = $deunaShipping;
        $this->logger = new Logger(self::LOGTAIL_SOURCE);
        $this->logger->pushHandler(new LogtailHandler(self::LOGTAIL_SOURCE_TOKEN));
        $this->logger->debug('Function called: '.__CLASS__.'\\'.__FUNCTION__);
    }

    /**
     * @return false|string
     */
    public function notify()
    {
        try {
            $bodyReq = $this->request->getBodyParams();
            $output = [];

            $this->logger->debug('Notify Payload: ', $bodyReq);

            $order = $bodyReq['order'];
            $orderId = $order['order_id'];
            $payment_status = $order['payment_status'];
            $paymentData = $order['payment']['data'];
            $email = $paymentData['customer']['email'];
            $token = $order['token'];
            $paymentProcessor = $paymentData['processor'];
            $paymentMethod = $order['payment_method'];
            $userComment = $order['user_instructions'];
            $shippingAmount = $order['shipping_amount']/100;
            $totalAmount = $order['total_amount']/100;

            $quote = $this->quotePrepare($order, $email);

            $active = $quote->getIsActive();

            $output = [];

            if ($active) {
                $this->logger->debug("Quote ({$quote->getId()}) is active", [
                    'paymentStatus' => $payment_status,
                    'paymentMethod' => $paymentMethod,
                ]);

                if($paymentMethod!='cash') {
                    if($payment_status!='processed')
                        return;
                }

                $mgOrder = $this->quoteManagement->submit($quote);

                $this->logger->debug("Order created with status {$mgOrder->getState()}");

                if(!empty($userComment)) {
                    $mgOrder->addStatusHistoryComment(
                        "Comentario de cliente<br>
                        <i>{$userComment}</i>"
                    )->setIsVisibleOnFront(true);
                }

                $mgOrder->setShippingAmount($shippingAmount);
                $mgOrder->setBaseShippingAmount($shippingAmount);
                $mgOrder->setGrandTotal($totalAmount);
                $mgOrder->setBaseGrandTotal($totalAmount);

                $this->updatePaymentState($mgOrder, $payment_status, $totalAmount);

                $payment = $mgOrder->getPayment();

                $payment->setAdditionalInformation('processor', $paymentProcessor);
                $payment->setAdditionalInformation('card_type', $paymentData['from_card']['card_brand']);
                $payment->setAdditionalInformation('card_bin', $paymentData['from_card']['first_six']);
                $payment->setAdditionalInformation('auth_code', $paymentData['external_transaction_id']);
                $payment->setAdditionalInformation('payment_method', $paymentMethod);
                $payment->setAdditionalInformation('number_of_installment', $paymentData['installments']);
                $payment->setAdditionalInformation('deuna_payment_status', $payment_status);
                $payment->setAdditionalInformation('token', $token);
                $payment->save();

                $mgOrder->save();

                $newOrderId = $mgOrder->getIncrementId();

                $this->logger->debug("Order ({$newOrderId}) saved");

                $output = [
                    'status' => $order['status'],
                    'data' => [
                        'order_id' => $newOrderId,
                    ]
                ];

                $this->logger->info("Pedido ({$newOrderId}) notificado satisfactoriamente", [
                    'response' => $output,
                ]);

                echo json_encode($output);

                die();
            } else {
                $output['status'] = $order['status'];

                $this->logger->warning("Pedido ({$orderId}) no se pudo notificar", [
                    'data' => $output,
                ]);

                return json_encode($output);
            }
        } catch(Exception $e) {
            $err = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTrace(),
            ];

            $this->logger->error('Critical error in '.__CLASS__.'\\'.__FUNCTION__, $err);

            return json_encode($err);
        }
    }

    /**
     * @param $order
     * @return \Magento\Quote\Api\Data\CartInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function quotePrepare($order, $email)
    {
        $quoteId = $order['order_id'];

        $quote = $this->cri->get($quoteId);

        $quote->getPayment()->setMethod('deunacheckout');

        $quote->setCustomerFirstname($order['shipping_address']['first_name']);
        $quote->setCustomerLastname($order['shipping_address']['last_name']);
        $quote->setCustomerEmail($email);

        $this->updateAddresses($quote, $order);

        $this->setCustomer($order, $email);

        return $quote;
    }

    private function setCustomer($order, $email)
    {
        if(!empty($email)) {
            $store = $this->storeManager->getStore();
            $websiteId = $store->getStoreId();

            $customer = $this->customerFactory->create();

            $customer->setWebsiteId($websiteId)->loadByEmail($email);

            if (!$customer->getId()) {
                // If not avilable then create this customer
                $customer->setWebsiteId($websiteId)
                         ->setStore($store)
                         ->setFirstname($order['shipping_address']['first_name'])
                         ->setLastname($order['shipping_address']['last_name'])
                         ->setEmail($email)
                         ->setPassword($email);
                $customer->save();
            }

            $customer = $this->customerRepository->getById($customer->getEntityId());

            return $customer;
        } else {
            return null;
        }
    }

    /**
     * @return false|string
     */
    public function getToken()
    {
        $tokenResponse = $this->orderTokens->getToken();

        $json = [
            'orderToken' => $tokenResponse['token'],
            'order_id' => $tokenResponse['order']['order_id'],
        ];

        return json_encode($json);
    }

    public function updatePaymentState($order, $payment_status, $totalAmount)
    {
        if ($payment_status == 'processed') {
            $orderState = \Magento\Sales\Model\Order::STATE_PROCESSING;
            $order->setState($orderState)
                  ->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)
                  ->setTotalPaid($totalAmount)
                  ->setPaymentMethod('deunacheckout');

            $this->logger->info("Order ({$order->getIncrementId()}) change status to PROCESSING");
        }
    }

    public function updateAddresses($quote, $data)
    {
        $shippingData = $data['shipping_address'];
        $billingData = $data['billing_address'];

        //  Billing Address
        $billingRegionId = $this->deunaShipping->getRegionId($billingData['state_name']);

        $billing_address = [
            'firstname' => $billingData['first_name'],
            'lastname' => $billingData['last_name'],
            'street' => $billingData['address1'].', '.$billingData['address2'],
            'city' => $billingData['city'],
            'country_id' => $billingData['country_code'],
            'region' => $billingRegionId,
            'postcode' => $billingData['zipcode'],
            'telephone' => $billingData['phone'],
        ];

        $quote->getBillingAddress()->addData($billing_address);

        // Shipping Address
        $shippingRegionId = $this->deunaShipping->getRegionId($shippingData['state_name']);

        $shipping_address = [
            'firstname' => (empty($shippingData['first_name']) ? $billingData['first_name'] : $billingData['first_name']),
            'lastname' => (empty($shippingData['last_name']) ? $billingData['last_name'] : $billingData['last_name']),
            'street' => (empty($shippingData['address1']) ? $billingData['address1'] : $shippingData['address1']).', '.(empty($shippingData['address2']) ? $billingData['address2'] : $shippingData['address2']),
            'city' => (empty($shippingData['city']) ? $billingData['city'] : $shippingData['city']),
            'country_id' => (empty($shippingData['country_code']) ? $billingData['country_code'] : $shippingData['country_code']),
            'region' => (empty($shippingRegionId) ? $billingRegionId : $shippingRegionId),
            'postcode' => (empty($shippingData['zipcode']) ? $billingData['zipcode'] : $shippingData['zipcode']),
            'telephone' => (empty($shippingData['phone']) ? $billingData['zipcode'] : $shippingData['phone']),
        ];

        $quote->getShippingAddress()->addData($shipping_address);
    }

    public function sendOrderId($orderId, $status = 'succeeded')
    {
        $body = array(
            "status" => $status,
            "data" => array(
                "order_id" => $orderId
            )
        );

        $response = $this->orderTokens->request(json_encode($body));

        return json_encode($response);
    }

    public function captureTransaction($orderId)
    {
        try {
            $order = $this->orderRepository->get($orderId);
            $payment = $order->getPayment();
            $amount = $payment->getAmountAuthorized();

            $objectManager = ObjectManager::getInstance();
            $paymentMethod = $objectManager->create(\DUna\Payments\Model\PaymentMethod::class);
            return $this->capturePayment($payment, $amount);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Capture Payment
     *
     * @param \Magento\Payment\Model\InfoInterface $payment Payment object
     * @param $amount Amount to capture
     */
    public function capturePayment($payment, $amount)
    {
        $this->logger = new Logger(self::LOGTAIL_SOURCE);
        $this->logger->pushHandler(new LogtailHandler(self::LOGTAIL_SOURCE_TOKEN));

        if ($amount <= 0) {
            $this->logger->error('Invalid amount for capture.');
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid amount for capture.'));
        }

        try {
            $this->logger->info('Capture payment. In Proccess...');
        //    $resp = $this->captureDeuna($payment);

            $this->logger->info('Updating order state.');
            // Generate the transaction ID for the capture
            $transactionId = $payment->getId() . '-capture';

            $this->logger->info('Register Capturing payment.', ['transactionId' => $transactionId]);

            // Set the capture data on the Payment object
            $payment->setTransactionId($transactionId);
            $payment->setIsTransactionClosed(0);
            $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);
            $payment->setParentTransactionId(null);
            $payment->setShouldCloseParentTransaction(false);
            $payment->save();

            $this->logger->info('Generating capture transaction.');

            // Create a new capture transaction
            $transaction = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);
            $transaction->setParentTxnId(null);
            $transaction->setIsClosed(1);
            $transaction->setAdditionalInformation(
                \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,
                [
                    'payment_method' => $this->_code,
                    'captured_amount' => $amount,
                    'processor' => $payment->getAdditionalInformation('processor'),
                    'card_type' => $payment->getAdditionalInformation('card_type'),
                    'card_bin' => $payment->getAdditionalInformation('card_bin'),
                    'auth_code' => $payment->getAdditionalInformation('auth_code'),
                    'payment_method' => $payment->getAdditionalInformation('payment_method'),
                    'number_of_installment' => $payment->getAdditionalInformation('number_of_installment'),
                    'deuna_payment_status' => $payment->getAdditionalInformation('deuna_payment_status'),
                    'token' => $payment->getAdditionalInformation('token'),
                ]
            );

            $transaction->save();

            $this->logger->info('Updating order state.');

            $order = $payment->getOrder();

            $totalPaid = $order->getTotalPaid() + $amount;
            $order->setTotalPaid($totalPaid);

            $totalDue = $order->getGrandTotal() - $totalPaid;
            $order->setTotalDue($totalDue);

            // Update the order state to "Processing"
            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
                  ->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)
                  ->addStatusToHistory(
                    \Magento\Sales\Model\Order::STATE_PROCESSING, __('Payment captured successfully.')
                  )->save();

            return true;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->logger->error($errorMessage);

            return false;
        }
    }

    public function captureDeuna($payment){

        $orderToken = $payment->getAdditionalInformation('token');

        $endpoint = "/merchants/orders/{$orderToken}/capture";

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $requestHelper = $objectManager->get(\DUna\Payments\Helper\RequestHelper::class);

        return $requestHelper->request($endpoint, 'POST', '', $headers);
    }
}
