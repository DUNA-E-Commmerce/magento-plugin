<?php

namespace DUna\Payments\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Webapi\Rest\Request;
use DUna\Payments\Helper\Data;
use Magento\Framework\Controller\Result\JsonFactory;
use DUna\Payments\Model\OrderTokens;
use Magento\Framework\Serialize\Serializer\Json;
use DUna\Payments\Api\CheckoutInterface;
use Exception;
use Magento\Framework\Exception\StateException;
use Magento\SalesRule\Model\Coupon;
use Magento\SalesRule\Model\Rule;
use Monolog\Logger;
use Logtail\Monolog\LogtailHandler;

class Checkout implements CheckoutInterface
{
    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * Shipping method converter
     *
     * @var ShippingMethodConverter
     */
    protected $converter;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Directory\Model\Currency
     */
    protected $_currency;

    /**
     * @var \Magento\Quote\Api\ShippingMethodManagementInterface
     */
    protected $shippingMethodManagementInterface;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    protected $_scopeConfig;

    protected $_coupon;

    protected $saleRule;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    private $orderTokens;

    /**
     * @var Json
     */
    private $json;

    private $logger;

    /**
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Quote\Model\Cart\ShippingMethodConverter $converter
     */

    const LOGTAIL_SOURCE = 'magento-bedbath-mx';
    const LOGTAIL_SOURCE_TOKEN = 'DB8ad3bQCZPAshmAEkj9hVLM';

    public function __construct(
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Model\Cart\ShippingMethodConverter $converter,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Directory\Model\Currency $currency,
        \Magento\Quote\Api\ShippingMethodManagementInterface $shippingMethodManagementInterface,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        Coupon $coupon,
        Rule $saleRule,
        Data $helper,
        JsonFactory $resultJsonFactory,
        Request $request,
        Json $json,
        OrderTokens $orderTokens
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->converter = $converter;
        $this->storeManager = $storeManager;
        $this->_currency = $currency;
        $this->shippingMethodManagementInterface = $shippingMethodManagementInterface;
        $this->productRepository = $productRepository;
        $this->_scopeConfig = $scopeConfig;
        $this->_coupon = $coupon;
        $this->saleRule = $saleRule;
        $this->helper = $helper;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->json = $json;
        $this->orderTokens = $orderTokens;
        $this->logger = new Logger(self::LOGTAIL_SOURCE);
        $this->logger->pushHandler(new LogtailHandler(self::LOGTAIL_SOURCE_TOKEN));
    }

    /**
     * @param int $cartId
     * @return array|\Magento\Framework\Controller\Result\Json
     * @throws NoSuchEntityException
     */
    public function applycoupon(int $cartId)
    {
        try {
            $this->logger->debug('Aplicando Coupon');
            $quote = $this->quoteRepository->getActive($cartId);

            $body = $this->request->getBodyParams();
            $couponCode = $body['coupon_code'];

            $appliedRuleIds = $quote->getAppliedRuleIds();

            if (!empty($appliedRuleIds)) {
                $this->logger->debug('Ya se ha aplicado una regla de carro.');
            }

            $originalSubtotalAmount = $quote->getSubtotal();
            $originalSubtotalAmountWithDiscount = $quote->getSubtotalWithDiscount();

            $this->logger->debug("Cupon a aplicar: {$couponCode}", [
                'payload' => $body,
            ]);

            $ruleId = $this->_coupon->loadByCode($couponCode)->getRuleId();

            if(!empty($ruleId)) {
                $rule = $this->saleRule->load($ruleId);
                $couponType = $rule->getCouponType();
                $couponAmount = $rule->getDiscountAmount();

                $quote->getShippingAddress()->setCollectShippingRates(true);
                $quote->setCouponCode($couponCode)->collectTotals()->save();

                if($couponType=="2") {
                    $couponAmount = ($couponAmount / 100) * $quote->getSubtotal();
                }

                $freeShipping = $rule->getSimpleFreeShipping();
                $ruleCode = $rule->getCouponCode();
                $ruleName = $rule->getName();

                $discountData = [
                    'code' => $ruleCode,
                    'reference' => $ruleName,
                    'amount' => $this->orderTokens->priceFormat($couponAmount),
                    'type' => 'coupon',
                    'free_shipping' => [
                        'is_free_shipping' => (bool) $freeShipping
                    ],
                ];

                $this->logger->debug("Cupon aplicado", [
                    'data' => $discountData,
                    'discount_type' => $rule->getCouponType(),
                    'subtotal' => $quote->getSubtotal(),
                    'subtotalWithDiscount' => $quote->getSubtotalWithDiscount(),
                ]);

                $order = $this->orderTokens->getBody($quote);

                $order['order']['discounts'][0] = $discountData;

                $newSubtotalAmount = $quote->getSubtotal();
                $newSubtotalAmountWithDiscount = $quote->getSubtotalWithDiscount();

                $this->logger->debug("Response", [
                    'data' => $order,
                    'couponsApplied' => $quote->getAppliedRuleIds(),
                    'originalSubtotalAmount' => $originalSubtotalAmount,
                    'newSubtotalAmount' => $newSubtotalAmount,
                    'originalSubtotalAmountWithDiscount' => $originalSubtotalAmountWithDiscount,
                    'newSubtotalAmountWithDiscount' => $newSubtotalAmountWithDiscount,
                ]);

                if($newSubtotalAmountWithDiscount==$originalSubtotalAmountWithDiscount) {
                    $err = [
                        'code' => 'EM-6001',
                        'message' => "Cupón ({$couponCode}) inválido",
                        'status_code' => '406',
                    ];
    
                    $this->logger->warning("Cupon ({$couponCode}) inválido", $err);
    
                    return $this->getJson($err, $err['status_code']);
                }

                return $this->getJson($order);
            } else {
                $err = [
                    'code' => 'EM-6001',
                    'message' => 'No se encontro cupón válido',
                    'status_code' => '406',
                ];

                $this->logger->warning("Cupon ($couponCode) no encontrado", $err);

                return $this->getJson($err, $err['status_code']);
            }
        } catch(Exception $e) {
            $err = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTrace(),
            ];
            $this->logger->error('Critical error in '.__CLASS__.'\\'.__FUNCTION__, $err);

            return $this->getJson($err);
        }
    }

    /**
     * @param int $cartId
     * @return array|\Magento\Framework\Controller\Result\Json
     * @throws NoSuchEntityException
     */
    public function removecoupon(int $cartId, string $couponCode)
    {
        /** @var Quote $quote */
        $quote = $this->quoteRepository->getActive($cartId);
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setCouponCode('')->collectTotals()->save();

        $order = $this->orderTokens->getBody($quote);

        foreach($order['order']['discounts'] as $key => $discount) {
            unset($order['order']['discounts'][$key]);
        }

        return $this->getJson($order);
    }

    /**
     * @param $quote
     * @return array
     */
    private function getShippingRates($quote)
    {
        $quote->collectTotals();
        $shippingAddress = $quote->getShippingAddress();
        if (!$shippingAddress->getCountryId()) {
            throw new StateException(__('The shipping address is missing. Set the address and try again.'));
        }
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();
        $shippingAddress->save();
        $shippingRates = $shippingAddress->getGroupedAllShippingRates();
        foreach ($shippingRates as $carrierRates) {
            foreach ($carrierRates as $rate) {
                $output[] = $this->converter->modelToDataObject($rate, $quote->getQuoteCurrencyCode());
            }
        }
        return $output;
    }

    private function setShippingInfo($quote)
    {
        $body = $this->request->getBodyParams();

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setFirstname($body['first_name']);
        $shippingAddress->setLastname($body['last_name']);
        $shippingAddress->setTelephone($body['phone']);
        $shippingAddress->setStreet($body['address1']);
        $shippingAddress->setCity($body['city']);
        $shippingAddress->setPostcode($body['zipcode']);
        $shippingAddress->setCountryId($body['country_iso']);
        $shippingAddress->setRegionId(941);
        $shippingAddress->save();

        $billingAddress = $quote->getBillingAddress();
        $billingAddress->setFirstname($body['first_name']);
        $billingAddress->setLastname($body['last_name']);
        $billingAddress->setTelephone($body['phone']);
        $billingAddress->setStreet($body['address1']);
        $billingAddress->setCity($body['city']);
        $billingAddress->setPostcode($body['zipcode']);
        $billingAddress->setCountryId($body['country_iso']);
        $billingAddress->setRegionId(941);
        $billingAddress->save();

    }

    /**
     * @throws NoSuchEntityException
     */
    protected function getItems($quote)
    {
        $items = [];
        foreach ($quote->getItems() as $item) {
            try {
                $product = $this->productRepository->get($item->getSku());
                $items[] = [
                    "id" => $item->getItemId(),
                    "name" => $item->getName(),
                    "description" => $item->getDescription(),
                    "options" => "",
                    "total_amount" => [
                        "amount" => ($item->getPrice() * $item->getQty()),
                        "original_amount" => ($product->getPrice() * $item->getQty()),
                        "currency" => $quote->getQuoteCurrencyCode(),
                        "currency_symbol" => $this->_currency->getCurrencySymbol()
                    ],
                    "unit_price" => [
                        "amount" => $item->getPrice(),
                        "currency" => $quote->getQuoteCurrencyCode(),
                        "currency_symbol" => $this->_currency->getCurrencySymbol()
                    ],
                    "tax_amount" => [
                        "amount" => $quote->getStoreToQuoteRate(),
                        "currency" => $quote->getQuoteCurrencyCode(),
                        "currency_symbol" => $this->_currency->getCurrencySymbol()
                    ],
                    "quantity" => $item->getQty(),
                    "uom" => $product->getUom(),
                    "upc" => $product->getUpc(),
                    "sku" => $item->getSku(),
                    "isbn" => $product->getIsbn(),
                    "brand" => $product->getBrand(),
                    "manufacturer" => $product->getManufacturer(),
                    "category" => implode(', ', $product->getCategoryIds()),
                    "color" => $product->getColor(),
                    "size" => $product->getSize(),
                    "weight" => [
                        "weight" => $product->getWeight(),
                        "unit" => $this->_scopeConfig->getValue(
                            'general/locale/weight_unit',
                            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                        )
                    ],
                    "image_url" => $product->getProductUrl(),
                    "details_url" => "",
                    "type" => $item->getProductType(),
                    "taxable" => (bool)$quote->getStoreToQuoteRate()
                ];
            } catch (\Exception $e) {
                throw new CouldNotSaveException(__('The shipping method can\'t be set. %1', $e->getMessage()));
            }
        }

        return $items;
    }

    protected function getShippingAddress($shippingAddress)
    {
        return [
            "id" => $shippingAddress->getId(),
            "user_id" => $shippingAddress->getCustomerId(),
            "first_name" => $shippingAddress->getFirstName(),
            "last_name" => $shippingAddress->getLastName(),
            "phone" => $shippingAddress->getTelephone(),
            "identity_document" => $shippingAddress->getIdentityDocument(),
            "lat" => $shippingAddress->getLat(),
            "lng" => $shippingAddress->getLng(),
            "address1" => $shippingAddress->getStreetLine(1),
            "address2" => $shippingAddress->getStreetLine(2),
            "city" => $shippingAddress->getCity(),
            "zipcode" => $shippingAddress->getPostcode(),
            "state_name" => $shippingAddress->getRegion(),
            "country_code" => $shippingAddress->getCountryId(),
            "additional_description" => $shippingAddress->getAdditionalDescription(),
            "address_type" => $shippingAddress->getAddressType(),
            "is_default" => (bool)$shippingAddress->getIsDefaultShipping(),
            "created_at" => $shippingAddress->getCreatedAt(),
            "updated_at" => $shippingAddress->getUpdatedAt()
        ];
    }

    /**
     * @param $data
     * @return \Magento\Framework\Controller\Result\Json
     */
    private function getJson($data, $statusCode = 200)
    {
        $json = $this->resultJsonFactory->create();
        $json->setStatusHeader($statusCode);
        $json->setData($data);
        return $json;
    }
}
