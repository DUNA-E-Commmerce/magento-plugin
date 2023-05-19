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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Helper\Image;
use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Monolog\Logger;
use Logtail\Monolog\LogtailHandler;

class OrderTokens
{

    const URL_PRODUCTION = 'https://apigw.getduna.com/merchants/orders';
    const URL_STAGING = 'https://api.stg.deuna.io/merchants/orders';
    const URL_DEVELOPMENT = 'https://api.stg.deuna.io/merchants/orders';
    const CONTENT_TYPE = 'application/json';
    const PRIVATE_KEY_PRODUCTION = 'private_key_production';
    const PRIVATE_KEY_STAGING = 'private_key_stage';
    const LOGTAIL_SOURCE = 'plataformas_magento';
    const LOGTAIL_SOURCE_TOKEN = 'DB8ad3bQCZPAshmAEkj9hVLM';

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

    /**
     * @var Image
     */
    protected $imageHelper;

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
        Image $imageHelper,
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
        $this->logger->debug('Function called: '.__CLASS__.'\\'.__FUNCTION__);
        $this->imageHelper = $imageHelper;
    }

    /**
     * Returns the URL based on the current environment.
     *
     * @return string The URL for the current environment.
     */
    private function getUrl(): string
    {
        switch ($this->getEnvironment()) {
            case 'develop':
                return self::URL_DEVELOPMENT;
            case 'staging':
                return self::URL_STAGING;
            default:
                return self::URL_PRODUCTION;
        }
    }

    /**
     * Returns the private key for the current environment.
     *
     * @return string The private key for the current environment.
     */
    public function getPrivateKey(): string
    {
        $env = $this->getEnvironment();

        /**
         * Merchant Dev: MAGENTO
         * Used for local development
         */
        $devPrivateKey = 'ab88c4b4866150ebbce7599c827d00f9f238c34e42baa095c9b0b6233e812ba54ef13d1b5ce512e7929eb4804b0218365c1071a35a85311ff3053c5e23a6';

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
     * Returns the headers required for API requests.
     *
     * @return array The headers required for API requests.
     */
    private function getHeaders(): array
    {
        return [
            'X-Api-Key: ' . $this->getPrivateKey(),
            'Content-Type: ' . self::CONTENT_TYPE
        ];
    }

    /**
     * Returns the address data for the specified address ID.
     *
     * @param int $addressId The ID of the address.
     *
     * @return \Magento\Customer\Api\Data\AddressInterface|null The address data or null if the address could not be found.
     */
    public function getAddressData(int $addressId): ?\Magento\Customer\Api\Data\AddressInterface
    {
        try {
            return $this->addressRepository->getById($addressId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $this->logger->error('Could not find address with ID ' . $addressId);

            return null;
        } catch (\Exception $e) {
            $this->logger->error('An error occurred while retrieving address with ID ' . $addressId, [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTrace(),
            ]);

            return null;
        }
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
        $this->logger->debug('AfterCalculate Method', [
            'cartId' => $cartId,
            'subject' => $subject,
            'addressInformation' => $addressInformation,
        ]);

        return null;
    }

    /**
     * @param $body
     * @return mixed
     * @throws LocalizedException
     */
    public function request($body)
    {
        $method = Zend_Http_Client::POST;
        $url = $this->getUrl();
        $http_ver = '1.1';
        $headers = $this->getHeaders();

        if($this->getEnvironment()!=='prod') {
            $this->logger->debug("Environment", [
                'environment' => $this->getEnvironment(),
                'apikey' => $this->getPrivateKey(),
                'request' => $url,
                'body' => $body,
            ]);
        }

        $configuration['header'] = false;

        if($this->getEnvironment()!=='prod') {
            $this->logger->debug('CURL Configuration sent', [
                'config' => $configuration,
            ]);
        }

        $this->curl->setConfig($configuration);
        $this->curl->write($method, $url, $http_ver, $headers, $body);

        $response = $this->curl->read();

        if (!$response) {
            $msg = "No response from request to {$url}";
            $this->logger->warning($msg);
            throw new LocalizedException(__($msg));
        }

        $response = $this->json->unserialize($response);

        if($this->getEnvironment()!=='prod') {
            $this->logger->debug("Response", [
                'data' => $response,
            ]);
        }

        if(!empty($response['error'])) {
            $error = $response['error'];
            $msg = "Error on DEUNA Token ({$error['code']} | {$url})";

            $this->logger->debug('Error on DEUNA Token', [
                'url' => $url,
                'error' => $error,
            ]);

            throw new LocalizedException(__('Error returned with request to ' . $url . '. Code: ' . $error['code'] . ' Error: ' . $error['description']));
        }

        if (!empty($response['code'])) {
            throw new LocalizedException(__('Error returned with request to ' . $url . '. Code: ' . $response['code'] . ' Error: ' . $response['message']));
        }

        $this->logger->debug('Token Response', [
            'token' => $response,
        ]);

        return $response;
    }

    /**
     * @return array
     */
    public function getBody($quote): array
    {
        $totals = $quote->getSubtotalWithDiscount();
        $domain = $this->storeManager->getStore()->getBaseUrl();
        $stores = [];

        $discounts = $this->getDiscounts($quote);

        $tax_amount = $quote->getShippingAddress()->getBaseTaxAmount();

        /**
         * Initial Data for Delivery Methods
         */
        $shippingAddress = $quote->getShippingAddress();
        $shippingMethod =  $shippingAddress->getShippingMethod();

        $shippingMethodSelected = "delivery";
        $nameStore = "";
        $addressStore = "";
        $lat = 0;
        $long = 0;

        $this->logger->debug("Shipping method {$shippingMethod} selected");

        $discount_amount = $this->getDiscountAmount($quote);
        $subtotal_amount = $quote->getSubtotal();
        $subtotal_amount -= $discount_amount;
        $totals += $tax_amount;

        $body = [
            'order' => [
                'order_id' => $quote->getId(),
                'currency' => $quote->getCurrency()->getQuoteCurrencyCode(),
                'tax_amount' => $this->priceFormat($tax_amount),
                'total_tax_amount' => $this->priceFormat($tax_amount),
                'items_total_amount' => $this->priceFormat($totals),
                'sub_total' => $this->priceFormat($subtotal_amount),
                'total_amount' => $this->priceFormat($totals),
                'total_discount' => $this->priceFormat($discount_amount),
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
                        ],
                        'contact' => [
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

        return $this->getShippingData($body, $quote, $stores);
    }

    /**
     * This function retrieves the discounts related to a quote object.
     *
     * @param $quote The quote object to get discounts for.
     * @return array|null Returns an array containing discount information if a coupon code is applied, otherwise null.
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
        return null;
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
        return $couponAmount;
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
    private function getShippingData($order, $quote, $storeObj)
    {
        $shippingOptions = $order['order']['shipping_options'];

        if($shippingOptions['type'] === 'pickup') {
            $order['order']['shipping_address'] = [
                'id' => 0,
                'user_id' => (string) 0,
                'first_name' => 'N/A',
                'last_name' => 'N/A',
                'phone' => $storeObj->getPhone(),
                'identity_document' => '-',
                'address_1' => "Tienda: {$storeObj->getStreet()}, {$storeObj->getNumber()}",
                'address_2' => $storeObj->getColony(),
                'city' => ($storeObj->getTown()==='-') ? "Ciudad de México" : $storeObj->getTown(),
                'zipcode' => $storeObj->getZipCode(),
                'state_code' => 'CDMX',
                'state_name' => $storeObj->getState(),
                'country_code' => (empty($storeObj->getCountry())) ? "MX" : $storeObj->getCountry(),
                'additional_description' => 'Recoger en tienda',
                'address_type' => 'home',
                'is_default' => true,
                'lat' => (float) $storeObj->getLat(),
                'lng' => (float) $storeObj->getLon(),
            ];
        } else {
            $shippingAddress = $quote->getShippingAddress();
            $shippingAmount = $this->priceFormat($shippingAddress->getShippingAmount());
            $order['order']['shipping_address'] = [
                'id' => 0,
                'user_id' => (string) 0,
                'first_name' => '-',
                'last_name' => '-',
                'phone' => '-',
                'identity_document' => '',
                'lat' => 0,
                'lng' => 0,
                'address_1' => '-',
                'address_2' => '-',
                'city' => '-',
                'zipcode' => '-',
                'state_name' => '-',
                'country_code' => 'MX',
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
        }

        return $order;
    }

    /**
     * This function formats a price to a fixed point representation with two decimal places and returns it as an integer.
     *
     * @param $price The price to format.
     * @return int Returns the formatted price as an integer.
     */
    public function priceFormat($price): int
    {
        $priceFix = number_format(is_null($price) ? 0 : $price, 2, '.', '');

        return (int) round($priceFix * 100, 1 , PHP_ROUND_HALF_UP);;
    }

    /**
     * This function returns the weight unit of the store as configured in the system configuration.
     *
     * @return string Returns the weight unit as a string.
     */
    private function getWeightUnit(): string
    {
        return $this->helper->getConfigValue('general/locale/weight_unit');
    }

    /**
     * This function returns the URL for the thumbnail image of the specified item.
     *
     * @param $item The item for which to retrieve the thumbnail image URL.
     * @return string Returns the URL for the thumbnail image as a string.
     */
    private function getImageUrl($item): string
    {
        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = ObjectManager::getInstance()->get(ProductRepositoryInterface::class);
        $product = $productRepository->get($item->getProduct()->getSku());

        $image = $product->getMediaGalleryImages()->getFirstItem();

        if ($image->getMediaType() === 'image') {
            return $this->imageHelper
                ->init($product, 'product_page_image_small')
                ->setImageFile($image->getFile())
                ->getUrl();
        }

        return $this->imageHelper->init($product, 'product_page_image_small')->getUrl();;
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

        $body = $this->json->serialize($this->getBody($quote));

        $body = json_encode($this->getBody($quote));

        return $this->request($body);
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function getToken()
    {
        try {
            $this->logger->info('Starting tokenization');

            $this->getPaymentMethodList();

            $token = $this->tokenize();

            $this->logger->info("Token Generated ({$token['token']})", [
                'token' => $token,
            ]);

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

    private function getPaymentMethodList()
    {
        $objectManager = ObjectManager::getInstance();
        $scope = $objectManager->create('\Magento\Framework\App\Config\ScopeConfigInterface');
        $methodList = $scope->getValue('payment');

        $output = [];

        foreach( $methodList as $code => $_method )
        {
            if( isset($_method['active']) && $_method['active'] == 1 ) {
                $output[] = [
                    'code' => $code,
                    'method' => $_method,
                ];
            }
        }

        $this->logger->debug('Payment Method List', $output);
    }

    private function replace_null($value, $replace) {
        if (is_null($value)) return $replace;
        return $value;
    }
}
