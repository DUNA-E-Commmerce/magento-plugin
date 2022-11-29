<?php
namespace DUna\Payments\Model;

use Magento\Framework\Webapi\Rest\Request;
use Psr\Log\LoggerInterface;
use DUna\Payments\Model\OrderTokens;
use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteFactory as Quote;
use Magento\Quote\Api\CartRepositoryInterface as CRI;
use DUna\Payments\Helper\Data;
use DUna\Payments\Model\Order\ShippingMethods;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

class PostManagement {

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var LoggerInterface
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
        LoggerInterface $logger,
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
        ShippingMethods $deunaShipping
    ) {
        $this->request = $request;
        $this->logger = $logger;
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
    }

    /**
     * @return false|string
     */
    public function notify()
    {
        $bodyReq = $this->request->getBodyParams();
        $output = [];

        $this->helper->log('debug', 'Notify New Order:', $bodyReq);

        $order = $bodyReq['order'];
        $orderId = $order['order_id'];
        $payment_status = $order['payment_status'];
        $email = $order['shipping_address']['email'];
        $token = $order['token'];
        $paymentProcessor = $order['payment']['data']['processor'];
        $metadata = $order['payment']['data']['metadata'];
        $paymentMethod = $order['payment_method'];
        $userComment = $order['user_instructions'];
        $shippingAmount = $order['shipping_amount']/100;
        $totalAmount = $order['total_amount']/100;
        $authCode = isset($metadata['authorization_code']) ? $metadata['authorization_code'] : 'N/A';

        $quote = $this->quotePrepare($order, $email);

        $active = $quote->getIsActive();

        $output = [
            'active' => boolval($active),
            'orderId' => $orderId,
            'token' => $token
        ];

        if ($active) {
            $order = $this->quoteManagement->submit($quote);

            if(!empty($userComment)) {
                $order->addStatusHistoryComment(
                    "Comentario de cliente<br>
                    <i>{$userComment}</i>"
                )->setIsVisibleOnFront(true);
            }

            $order->setShippingAmount($shippingAmount);
            $order->setBaseShippingAmount($shippingAmount);
            $order->setGrandTotal($totalAmount);
            $order->setBaseGrandTotal($totalAmount);

            $this->updatePaymentState($order, $payment_status, $totalAmount);

            $order->addStatusHistoryComment(
                "Payment Processed by <strong>DEUNA Checkout</strong><br>
                <strong>Token:</strong> {$token}<br>
                <strong>OrderID:</strong> {$orderId}<br>
                <strong>Auth Code:</strong> {$authCode}<br>
                <strong>Payment Method:</strong> {$paymentMethod}<br>
                <strong>Processor:</strong> {$paymentProcessor}"
            );

            $order->save();

            $output['status'] = 'saved';
        } else {
            $output['status'] = 'failed';
        }

        return json_encode($output);
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

        $quote->getPayment()->setMethod('deuna_payments');

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
                  ->setPaymentMethod('checkmo');
        }
    }

    public function updateAddresses($quote, $data)
    {
        $shippingData = $data['shipping_address'];

        $regionId = $this->deunaShipping->getRegionId($shippingData['state_name']);

        $shipping_address = [
            'firstname' => $shippingData['first_name'],
            'lastname' => $shippingData['last_name'],
            'street' => $shippingData['address1'].' '.$shippingData['address2'],
            'city' => $shippingData['city'],
            'country_id' => $shippingData['country_code'],
            'region' => $regionId,
            'postcode' => $shippingData['zipcode'],
            'telephone' => $shippingData['phone'],
        ];

        $quote->getShippingAddress()->addData($shipping_address);

        $billingData = $data['billing_address'];

        $regionId = $this->deunaShipping->getRegionId($billingData['state_name']);

        $billing_address = [
            'firstname' => $billingData['first_name'],
            'lastname' => $billingData['last_name'],
            'street' => $billingData['address1'].' '.$billingData['address2'],
            'city' => $billingData['city'],
            'country_id' => $billingData['country_code'],
            'region' => $regionId,
            'postcode' => $billingData['zipcode'],
            'telephone' => $billingData['phone'],
        ];

        $quote->getBillingAddress()->addData($billing_address);
    }

    // public function updateOrderCustomer($orderId)
    // {
    //     $order = $this->orderRepository->get($orderId);

    //     $order->setCustomerId($customer->getId());
    //     $order->setCustomerIsGuest(0);
    //     $order->save();
    // }

}
