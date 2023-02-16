<?php

namespace DUna\Payments\Model;

use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Session;
use Magento\Shipping\Model\Config as shippingConfig;
use Magento\Framework\HTTP\Adapter\Curl;
use Magento\Framework\Exception\LocalizedException;
use Zend_Http_Client;
use Magento\Framework\Serialize\Serializer\Json;
use DUna\Payments\Helper\Data;
use Exception;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Catalog\Model\Category;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Quote\Api\ShippingMethodManagementInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Checkout\Api\Data\TotalsInformationInterface;
use Magento\Checkout\Api\TotalsInformationManagementInterface;
use Monolog\Logger;
use Logtail\Monolog\LogtailHandler;
use Magento\Framework\Exception\NoSuchEntityException;

class OrderTokens
{

    const URL_PRODUCTION = 'https://apigw.getduna.com/merchants/orders';
    const URL_STAGING = 'https://api.stg.deuna.io/merchants/orders';
    const URL_DEVELOPMENT = 'https://api.dev.deuna.io/merchants/orders';
    const CONTENT_TYPE = 'application/json';
    const PRIVATE_KEY_PRODUCTION = 'private_key_production';
    const PRIVATE_KEY_STAGING = 'private_key_stage';
    const LOGTAIL_SOURCE_TOKEN = 'DB8ad3bQCZPAshmAEkj9hVLM';
    const LOGTAIL_SOURCE = 'deuna-magento-checkout';

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var Json
     */
    private $json;
    /**
     * @var Observer
     */
    private $observer;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var ShippingAssignmentInterface
     */
    private $shippingAssignment;

    /**
     * @var ShippingMethodManagementInterface
     */
    private $shippingMethodManager;

    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;
    /**
     * @var AddressRepositoryInterface
     */
    private $addressRepository;

    /**
     * @var Category
     */
    private $category;
    /**
     * @var TotalsInformationInterface
     */
    private $totalsInformationInterface;
    /**
     * @var TotalsInformationManagementInterface
     */
    private $totalsInformationManagementInterface;
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(
        Session $checkoutSession,
        Curl $curl,
        Json $json,
        Data $helper,
        StoreManagerInterface $storeManager,
        PriceCurrencyInterface $priceCurrency,
        Category $category,
        EncryptorInterface $encryptor,
        \Magento\SalesRule\Model\Coupon $coupon,
        \Magento\SalesRule\Model\Rule $saleRule,
        \Magento\Framework\Event\Observer $observer,
        \Magento\Quote\Api\ShippingMethodManagementInterface $shippingMethodManagement,
        \Magento\Quote\Model\ShippingMethodManagement $shippingMethodManager,
        AddressRepositoryInterface $addressRepository,
        ShippingAssignmentInterface $shippingAssignment,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        TotalsInformationInterface $totalsInformationInterface,
        TotalsInformationManagementInterface $totalsInformationManagementInterface,
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->curl = $curl;
        $this->json = $json;
        $this->helper = $helper;
        $this->storeManager = $storeManager;
        $this->priceCurrency = $priceCurrency;
        $this->category = $category;
        $this->encryptor = $encryptor;
        $this->coupon = $coupon;
        $this->saleRule = $saleRule;
        $this->observer = $observer;
        $this->shippingMethodManagement = $shippingMethodManagement;
        $this->shippingMethodManager = $shippingMethodManager;
        $this->addressRepository = $addressRepository;
        $this->shippingAssignment = $shippingAssignment;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->totalsInformationInterface = $totalsInformationInterface;
        $this->totalsInformationManagementInterface = $totalsInformationManagementInterface;
        $this->logger = new Logger(self::LOGTAIL_SOURCE);
        $this->logger->pushHandler(new LogtailHandler(self::LOGTAIL_SOURCE_TOKEN));
    }

    /**
     * @return string
     */
    private function getUrl(): string
    {
        $env = $this->getEnvironment();

        switch($env) {
            case 'develop':
                return self::URL_DEVELOPMENT;
                break;
            case 'staging':
                return self::URL_STAGING;
                break;
            default:
                return self::URL_PRODUCTION;
                break;
        }
    }

    /**
     * @return string
     */
    public function getPrivateKey(): string
    {
        $env = $this->getEnvironment();

        /**
         * Comercio Dev: MAGENTO
         */
        $devPrivateKey = 'd09ae647fceb2a30e6fb091e512e7443b092763a13f17ed15e150dc362586afd92571485c24f77a4a3121bc116d8083734e27079a25dc44493496198b84f';

        if ($env == 'develop') {
            return $devPrivateKey;
        } else if ($env == 'staging') {
            $privateKey = $this->helper->getGeneralConfig(self::PRIVATE_KEY_STAGING);
        } else {
            $privateKey = $this->helper->getGeneralConfig(self::PRIVATE_KEY_PRODUCTION);
        }

        return $this->encryptor->decrypt($privateKey);
    }

    /**
     * @return string[]
     */
    private function getHeaders(): array
    {
        return [
            'X-Api-Key: ' . $this->getPrivateKey(),
            'Content-Type: ' . self::CONTENT_TYPE
        ];
    }

        /**
     * @param $addressId
     *
     * @return \Magento\Customer\Api\Data\AddressInterface
     */
    public function getAddressData($addressId)
    {
        $addressData = null;
        try {
            $addressData = $this->addressRepository->getById($addressId);
        } catch (\Exception $exception) {
            $this->helper->log('debug', 'getAddressDataById', [$exception->getMessage()]);

        }
        return $addressData;
    }

      /**
     * @param TotalsInformationManagementInterface $subject
     * @param int                                  $cartId
     * @param TotalsInformationInterface           $addressInformation
     *
     * @return mixed[]|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function AfterCalculate(
        TotalsInformationManagementInterface $subject,
        int $cartId,
        TotalsInformationInterface $addressInformation
    ) {
        $this->helper->log('debug', 'Environment', ["soy el metodo AfterCalculate"]);

        return null;
    }

    /**
     * @param $body
     * @return mixed
     * @throws LocalizedException
     */
    private function request($body)
    {
        //log
       // $this->helper->log('debug', 'request body', [$body]);

        $method = Zend_Http_Client::POST;
        $url = $this->getUrl();
        $http_ver = '1.1';
        $headers = $this->getHeaders();

        if($this->getEnvironment()!=='prod') {
            $this->logger->debug('Request debugger', [
                'url' => $url,
                'env' => $this->getEnvironment(),
                'api-key' => $this->getPrivateKey(),
            ]);
        }

        $configuration['header'] = false;

        $this->curl->setConfig($configuration);

        $this->curl->write($method, $url, $http_ver, $headers, $body);

        $response = $this->curl->read();

        if (!$response) {
            $msg = 'No response from request to ' . $url;

            $this->logger->notice($msg, [
                'response' => $response,
            ]);

            throw new LocalizedException(__($msg));
        }

        $response = $this->json->unserialize($response);

        if(!empty($response['error'])) {
            $error = $response['error'];
            $msg = "Error on DEUNA Token ({$error['code']} | {$url})";

            $this->logger->error($msg, [
                'response' => $response,
            ]);

            throw new LocalizedException(__('Error returned with request to ' . $url . '. Code: ' . $error['code'] . ' Error: ' . $error['description']));
        }

        if (!empty($response['code'])) {
            throw new LocalizedException(__('Error returned with request to ' . $url . '. Code: ' . $response['code'] . ' Error: ' . $response['message']));
        }

        return $response;
    }

    /**
     * @return array
     */
    public function getBody($quote): array
    {
        $totals = $quote->getSubtotalWithDiscount();
        $domain = $this->storeManager->getStore()->getBaseUrl();

        $discounts = $this->getDiscounts($quote);

        $tax_amount = $quote->getShippingAddress()->getBaseTaxAmount();

        $shippingMethodSelected = "delivery";
        $nameStore = "";
        $addressStore = "";
        $lat = 0;
        $long = 0;

        $totals += $tax_amount;

        $body = [
            'order' => [
                'order_id' => $quote->getId(),
                'currency' => $quote->getCurrency()->getQuoteCurrencyCode(),
                'tax_amount' => $this->priceFormat($tax_amount),
                'total_tax_amount' => $this->priceFormat($tax_amount),
                'items_total_amount' => $this->priceFormat($totals),
                'sub_total' => $this->priceFormat($quote->getSubtotal()),
                'total_amount' => $this->priceFormat($totals),
                'total_discount' => $this->getDiscountAmount($quote),
                'store_code' => 'all', //$this->storeManager->getStore()->getCode(),
                'items' => $this->getItems($quote),
                'discounts' => $discounts ? [$discounts] : [],
                'shipping_options' => [
                    'type' => $shippingMethodSelected,
                    'details' => [
                        'store_name' => $nameStore,
                        'address' =>  $addressStore,
                        'address_coordinates' => [
                            'lat' => $lat,
                            'lng' => $long
                        ],	'contact' => [
                            'name' => $nameStore

                        ],
                    ]
                ],
                'redirect_url' => $domain . 'checkout/onepage/success',
                'webhook_urls' => [
                    'notify_order' => $domain . 'rest/V1/orders/notify',
                    'apply_coupon' => $domain . 'duna/set/coupon/order/{order_id}',
                    'remove_coupon' => $domain . 'duna/remove/coupon/order/{order_id}/coupon/{coupon_code}',
                    'get_shipping_methods' => $domain . 'rest/V1/orders/{order_id}/shipping-methods',
                    'update_shipping_method' => $domain . 'duna/set/shippingmethod/order/{order_id}/method'
                ]
            ]
        ];

        return $this->getShippingData($body, $quote);
    }

    /**
     * @param $quote
     * @return array|void
     */
    private function getDiscounts($quote)
    {
        $coupon = $quote->getCouponCode();
        if ($coupon) {
            $subTotalWithDiscount = $quote->getSubtotalWithDiscount();
            $subTotal = $quote->getSubtotal();
            $couponAmount = $subTotal - $subTotalWithDiscount;

            $ruleId = $this->coupon->loadByCode($coupon)->getRuleId();
            $rule = $this->saleRule->load($ruleId);
            $freeShipping = $rule->getSimpleFreeShipping();

            $discount = [
                'amount' => $this->priceFormat($couponAmount),
                'code' => $coupon,
                'reference' => $coupon,
                'description' => '',
                'details_url' => '',
                'free_shipping' => [
                    'is_free_shipping' => (bool) $freeShipping,
                    'maximum_cost_allowed' => 100
                ],
                'discount_category' => 'coupon'
            ];
            return $discount;
        }
    }

    /**
     * Get Discount Amount
     * @param $quote
     * @return int
     */
    private function getDiscountAmount($quote)
    {
        $subTotalWithDiscount = $quote->getSubtotalWithDiscount();
        $subTotal = $quote->getSubtotal();
        $couponAmount = $subTotal - $subTotalWithDiscount;
        return $this->priceFormat($couponAmount);
    }

    /**
     * @param $items
     * @return array
     */
    private function getItems($quote): array
    {
        $currencyCode = $quote->getCurrency()->getQuoteCurrencyCode();
        $currencySymbol = $this->priceCurrency->getCurrencySymbol();
        $items = $quote->getItemsCollection();
        $itemsList = [];
        foreach ($items as $item) {
            if ($item->getParentItem()) continue;
            $qtyItem = (int) $item->getQty();
            $totalSpecialItemPrice = $item->getPrice('special_price')*$qtyItem;
            $totalRegularItemPrice = $item->getProduct()->getPrice('regular_price')*$qtyItem;
            $itemsList[] = [
                'id' => $item->getProductId(),
                'name' => $item->getName(),
                'description' => $item->getDescription(),
                'options' => '',
                'total_amount' => [
                    'amount' => $this->priceFormat($totalSpecialItemPrice),
                    'original_amount' => $this->priceFormat($totalRegularItemPrice),
                    'currency' => $currencyCode,
                    'currency_symbol' => $currencySymbol
                ],
                'unit_price' => [
                    'amount' => $this->priceFormat($item->getProduct()->getPrice('regular_price')),
                    'currency' => $currencyCode,
                    'currency_symbol' => $currencySymbol
                ],
                'tax_amount' => [
                    'amount' => $this->priceFormat($item->getTaxAmount()),
                    'currency' => $currencyCode,
                    'currency_symbol' => $currencySymbol
                ],
                'quantity' => $qtyItem,
                'uom' => '',
                'upc' => '',
                'sku' => $item->getProduct()->getSku(),
                'isbn' => '',
                'brand' => '',
                'manufacturer' => '',
                'category' => $this->getCategory($item),
                'color' => '',
                'size' => '',
                'weight' => [
                    'weight' => $this->priceFormat($item->getWeight(), 2, '.', ''),
                    'unit' => $this->getWeightUnit()
                ],
                'image_url' => $this->getImageUrl($item),
                'type' => ($item->getIsVirtual() ? 'virtual' : 'physical'),
                'taxable' => true
            ];
        }

        return $itemsList;
    }

    /**
     * @param $order
     * @param $shippingAmount
     * @return array
     */
    private function getShippingData($order, $quote)
    {
        $shippingAddress = $quote->getShippingAddress();
        $shippingAmount = $this->priceFormat($shippingAddress->getShippingAmount());
        $order['order']['shipping_address'] = [
            'id' => 0,
            'user_id' => (string) 0,
            'first_name' => 'test',
            'last_name' => 'test',
            'phone' => '8677413045',
            'identity_document' => '',
            'lat' => 0,
            'lng' => 0,
            'address_1' => 'test',
            'address_2' => 'test',
            'city' => 'test',
            'zipcode' => 'test',
            'state_name' => 'test',
            'country_code' => 'CL',
            'additional_description' => '',
            'address_type' => '',
            'is_default' => false,
            'created_at' => '',
            'updated_at' => '',
        ];
        $order['order']['status'] = 'pending';
        $order['order']['shipping_amount'] = $shippingAmount;
        $order['order']['sub_total'] += $shippingAmount;
        $order['order']['total_amount'] += $shippingAmount;
        return $order;
    }

    /**
     * @param $price
     * @return int
     */
    public function priceFormat($price): int
    {
        $priceFix = number_format(is_null($price) ? 0 : $price, 2, '.', '');

        return (int) round($priceFix * 100, 1 , PHP_ROUND_HALF_UP);;
    }

    /**
     * @return string
     */
    private function getWeightUnit(): string
    {
        return $this->helper->getConfigValue('general/locale/weight_unit');
    }

    /**
     * @param $item
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getImageUrl($item): string
    {
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $thumbnail = $item->getProduct()->getThumbnail();
        return $mediaUrl . 'catalog/product' . $thumbnail;
    }

    /**
     * @param $item
     * @return string
     */
    private function getCategory($item): string
    {
        $categoriesIds = $item->getProduct()->getCategoryIds();
        foreach ($categoriesIds as $categoryId) {
            $category = $this->category->load($categoryId)->getName();
        }
        return $category;
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    private function tokenize(): array
    {
        $quote = $this->checkoutSession->getQuote();

        /** IMPROVISED CODE */
        $objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->create("\Magento\Store\Model\StoreManagerInterface");
        $stores = $storeManager->getStores(true, false);

        $this->logger->debug('Store Manager:', $stores);

        foreach($stores as $store){
            $storeName = $store->getName();
            $this->helper->log('debug','storeName:', [ $storeName ]);
            $this->helper->log('debug','storeID:', [ $store->getId() ]);
        }
        $billingAddress = $quote->getBillingAddress();

        $this->helper->log('debug','billingAddress->getData:', [ $billingAddress->getData()]);
        $this->helper->log('debug','tokenize-quote-getShippingAddress-getData:', [ $quote->getShippingAddress()->getData() ]);
        $this->helper->log('debug','tokenize-quote-getData:', [ $quote->getData() ]);

        $body = $this->json->serialize($this->getBody($quote));

        $body = json_encode($this->getBody($quote));

        $this->helper->log('debug', 'Json to Tokenize:', [$body]);

        return $this->request($body);
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function getToken()
    {
        $this->logger->info('Starting tokenization');

        try {
            $token = $this->tokenize();

            if($this->getEnvironment()!=='prod') {
                $this->logger->debug("Token generated ({$token['token']})", [
                    'token' => $token,
                ]);
            }

            return $token;
        } catch(NoSuchEntityException $e) {
            $this->logger->error('Critical error in '.__FUNCTION__, [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTrace(),
            ]);

            return false;
        } catch(Exception $e) {
            $this->logger->error('Critical error in '.__FUNCTION__, [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTrace(),
            ]);

            return false;
        }
    }

    public function getEnvironment() {
        return $this->helper->getEnv();
    }
}
