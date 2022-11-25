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
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
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
        StoreManagerInterface $storeManager
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
        $token = $order['token'];
        $paymentProcessor = $order['payment']['data']['processor'];
        $metadata = $order['payment']['data']['metadata'];
        $paymentMethod = $order['payment_method'];
        $userComment = $order['user_instructions'];
        $shippingAmount = $order['shipping_amount']/100;
        $totalAmount = $order['total_amount']/100;
        $authCode = isset($metadata['authorization_code']) ? $metadata['authorization_code'] : 'N/A';

        $quote = $this->quotePrepare($order);

        $active = $quote->getIsActive();

        $output = [
            'active' => boolval($active),
            'orderId' => $orderId,
            'token' => $token
        ];

        if ($active) {
            $order = $this->quoteManagement->submit($quote);

            if ($payment_status == 'processed') {
                $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING, true)
                      ->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)
                      ->setTotalPaid($totalAmount);
            }

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
    private function quotePrepare($order)
    {
        $store = $this->storeManager->getStore();
        $websiteId = $store->getStoreId();
        $quoteId = $order['order_id'];

        $email = $order['payment']['data']['customer']['email'];

        $quote = $this->cri->get($quoteId);

        $quote->getPayment()->setMethod('deuna_payments');

        $customer = $this->customerFactory->create();

        $customer->getCustomerByEmail($email,$websiteId);

        if (!$customer->getEntityId()) {
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
        $quote->assignCustomer($customer);

        $quote->setCustomerEmail($email);

        return $quote;
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

}
