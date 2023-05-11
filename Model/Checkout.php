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
use Magento\Framework\Exception\StateException;
use Magento\SalesRule\Model\Coupon;

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

    /**
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Quote\Model\Cart\ShippingMethodConverter $converter
     */
    public function __construct(
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Model\Cart\ShippingMethodConverter $converter,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Directory\Model\Currency $currency,
        \Magento\Quote\Api\ShippingMethodManagementInterface $shippingMethodManagementInterface,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        Coupon $coupon,
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
        $this->helper = $helper;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->json = $json;
        $this->orderTokens = $orderTokens;
    }

    /**
     * @param int $cartId
     * @return array|\Magento\Framework\Controller\Result\Json
     * @throws NoSuchEntityException
     */
    public function applycoupon(int $cartId)
    {
        /** @var Quote $quote */
        $quote = $this->quoteRepository->getActive($cartId);

        $body = $this->request->getBodyParams();
        $couponCode = $body['coupon_code'];

        $ruleId = $this->_coupon->loadByCode($couponCode)->getRuleId();

        if(!empty($ruleId)) {
            $quote->getShippingAddress()->setCollectShippingRates(true);
            $quote->setCouponCode($couponCode)->collectTotals();
            $quote->save();

            $order = $this->orderTokens->getBody($quote);

            return $this->getJson($order);
        } else {
            return $this->getJson([
                'code' => 'EM-6001',
                'message' => 'No se encontro cupón válido'
            ], '406');
        }
    }

    /**
     * @param int $cartId
     * @return array|\Magento\Framework\Controller\Result\Json
     * @throws NoSuchEntityException
     */
    public function removecoupon(int $cartId)
    {
        /** @var Quote $quote */
        $quote = $this->quoteRepository->getActive($cartId);
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setCouponCode('')->collectTotals();
        $quote->save();
        $order = $this->orderTokens->getBody($quote);
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
