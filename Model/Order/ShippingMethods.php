<?php

namespace DUna\Payments\Model\Order;

use Magento\Framework\Exception\NoSuchEntityException;
use DUna\Payments\Api\ShippingMethodsInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Webapi\Rest\Request;
use DUna\Payments\Helper\Data;
use Magento\Framework\Controller\Result\JsonFactory;
use DUna\Payments\Model\OrderTokens;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Directory\Model\Country;

/**
 * Class ShippingMethods
 */
class ShippingMethods implements ShippingMethodsInterface
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

    /**
    * @var DirectoryHelper
    */
    protected $directoryHelper;


    protected $shippingMethod;

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
     * @var Collection
     */
    private $regionCollectionFactory;

    /**
     * @var Json
     */
    private $json;

    /**
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Quote\Model\Cart\ShippingMethodConverter $converter
     */
    public function __construct(
        \Magento\Quote\Api\Data\ShippingMethodInterface $shippingMethod,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Model\Cart\ShippingMethodConverter $converter,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Directory\Model\Currency $currency,
        \Magento\Quote\Api\ShippingMethodManagementInterface $shippingMethodManagementInterface,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        Data $helper,
        JsonFactory $resultJsonFactory,
        Request $request,
        Json $json,
        OrderTokens $orderTokens,
        CollectionFactory $regionCollectionFactory,
        DirectoryHelper $directoryHelper
    ) {
        $this->shippingMethod = $shippingMethod;
        $this->quoteRepository = $quoteRepository;
        $this->converter = $converter;
        $this->storeManager = $storeManager;
        $this->_currency = $currency;
        $this->shippingMethodManagementInterface = $shippingMethodManagementInterface;
        $this->productRepository = $productRepository;
        $this->_scopeConfig = $scopeConfig;
        $this->helper = $helper;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->json = $json;
        $this->orderTokens = $orderTokens;
        $this->regionCollectionFactory = $regionCollectionFactory;
        $this->directoryHelper = $directoryHelper;
    }

    /**
     * @param int $cartId
     * @return array|void
     * @throws NoSuchEntityException
     */
    public function get(int $cartId)
    {
        /** @var Quote $quote */
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->quoteRepository->getActive($cartId);

        // Set Shipping Address
        $this->setShippingInfo($quote);

        // no methods applicable for empty carts or carts with virtual products
        if ($quote->isVirtual() || 0 == $quote->getItemsCount()) {
            return [];
        }
        //log
        $shippingMethod1 = $quote->getShippingAddress()->getShippingMethod();
        $shippingMethod2 = $quote->getShippingAddress()->getShippingMethod();

        $this->helper->log('debug','shippingMethod-selected1:', [$shippingMethod1]);
        $this->helper->log('debug','shippingMethod-selected2:', [$shippingMethod2]);
        $this->helper->log('debug','shippingMethod-getMethodTitle:', [$this->shippingMethod]);
        $this->helper->log('debug','shippingMethod-selected-getShippingDescription:', [$quote->getData()]);

        // Get Shipping Rates
        $shippingRates = $this->getShippingRates($quote);

        $shippingMethods = [
            'shipping_methods' => []
        ];

        $freeShippingMinAmount = $this->getFreeShippingSubtotal();

        $this->helper->log('debug','Free Shipping Min Amount:', [$freeShippingMinAmount]);
        $this->helper->log('debug','shippingRates:', [$shippingRates]);

        foreach ($shippingRates as $method) {

            if($method->getMethodCode() == 'freeshipping') {

                if($freeShippingMinAmount <= $quote->getSubtotal()) {
                    $shippingMethods['shipping_methods'][] = [
                        'code' => $method->getMethodCode(),
                        'name' => $method->getMethodTitle(),
                        'cost' => $this->orderTokens->priceFormat($method->getAmount()),
                        'tax_amount' => $method->getPriceInclTax(),
                        'min_delivery_date' => '',
                        'max_delivery_date' => ''
                    ];
                }
            } else {

                if(!is_null($method->getMethodCode())) {
                    $shippingMethods['shipping_methods'][] = [
                        'code' => $method->getMethodCode(),
                        'name' => $method->getMethodTitle(),
                        'cost' => $this->orderTokens->priceFormat($method->getAmount()),
                        'tax_amount' => $method->getPriceInclTax(),
                        'min_delivery_date' => '',
                        'max_delivery_date' => ''
                    ];
                }
            }
        }


        $this->helper->log('debug', 'Shipping Methods:', $shippingMethods);

        die($this->json->serialize($shippingMethods));
    }



    /**
     * @param int $cartId
     * @param string $code
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function set(int $cartId, string $code) {

        /** @var Quote $quote */
        $quote = $this->quoteRepository->getActive($cartId);

        // Get Shipping Rates
        $shippingRates = $this->getShippingRates($quote);

        $shippingAmount = 0;

        foreach ($shippingRates as $shippingMethod) {
            if ($shippingMethod->getMethodCode() == $code) {
                $shippingAddress = $quote->getShippingAddress();
                $shippingAddress->setShippingMethod($shippingMethod->getCarrierCode() . '_' . $shippingMethod->getMethodCode());
                $shippingAddress->setShippingDescription($shippingMethod->getCarrierTitle() . ' - ' . $shippingMethod->getMethodTitle());
                $shippingAddress->setShippingAmount($shippingMethod->getAmount());
                $shippingAddress->setCollectShippingRates(true);
                $shippingAddress->save();

                $quote->setShippingAddress($shippingAddress);

                // $shippingAmount = $this->orderTokens->priceFormat($shippingAddress->getShippingAmount());
                $shippingAmount = $this->orderTokens->priceFormat($shippingMethod->getAmount());

                break ;
            }
        }

        $order = $this->orderTokens->getBody($quote);

        $this->helper->log('debug','Shipping Amount:',[$shippingAmount]);

        if(
            $order['order']['shipping_amount'] !== $shippingAmount ||
            $order['order']['shipping_amount'] > 0
        ) {
            $order['order']['total_amount'] -= $order['order']['shipping_amount'];

            $order['order']['shipping_amount'] = $shippingAmount;

            $order['order']['total_amount'] += $shippingAmount;
        }

        $this->helper->log('debug','Order | Tax Amount:', [$order['order']['tax_amount']]);
        $this->helper->log('debug','Order | Shipping Amount:', [$order['order']['shipping_amount']]);
        $this->helper->log('debug','Order | Total Amount:', [$order['order']['total_amount']]);

        return $this->getJson($order);
    }

    /**
    * Allowed Countries Getter.
    */
   public function getAllowedCountries()
   {
       $countries = [];

       /* @var Country $country */
       foreach ($this->directoryHelper->getCountryCollection() as $country) {
           $countries[] = [
               'value' => $country->getId(),
               'label' => $country->getName()
           ];
       }

       return $countries;
   }

    /**
     * @param $quote
     * @return array
     */
    public function getShippingRates($quote)
    {
        $quote->collectTotals();
        $output = [];

        $shippingAddress = $quote->getShippingAddress();

        if (!$shippingAddress->getCountryId()) {
            throw new StateException(__('The shipping address is missing. Set the address and try again.'));
        }

        $countriesAllowed = $this->getAllowedCountries();
        $this->helper->log('debug','countriesAllowed:', [$countriesAllowed]);
        $isCountryAllowed = false;


        foreach ($countriesAllowed as $country) {
            if ($shippingAddress->getCountryId() == $country['value']) {
                $isCountryAllowed = true;
            }
        }

        if($isCountryAllowed){
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

        return $output;
    }

    private function setShippingInfo($quote)
    {
        $body = $this->request->getBodyParams();

        $regionId = $this->getRegionId($body['state_name']);

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setFirstname($body['first_name']);
        $shippingAddress->setLastname($body['last_name']);
        $shippingAddress->setTelephone($body['phone']);
        $shippingAddress->setStreet($body['address1']);
        $shippingAddress->setCity($body['city']);
        $shippingAddress->setPostcode($body['zipcode']);
        $shippingAddress->setCountryId($body['country_iso']);
        $shippingAddress->setRegionId($regionId);
        $shippingAddress->save();

        $billingAddress = $quote->getBillingAddress();
        $billingAddress->setFirstname($body['first_name']);
        $billingAddress->setLastname($body['last_name']);
        $billingAddress->setTelephone($body['phone']);
        $billingAddress->setStreet($body['address1']);
        $billingAddress->setCity($body['city']);
        $billingAddress->setPostcode($body['zipcode']);
        $billingAddress->setCountryId($body['country_iso']);
        $billingAddress->setRegionId($regionId);
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
    private function getJson($data)
    {
        $json = $this->resultJsonFactory->create();

        $json->setData($data);

        return $json;
    }

    private function getFreeShippingSubtotal()
    {
        return $this->_scopeConfig->getValue('carriers/freeshipping/free_shipping_subtotal', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getRegionId($stateName)
    {
        $region = $this->regionCollectionFactory->create()
                  ->addRegionNameFilter($stateName)
                  ->getFirstItem()
                  ->toArray();

        return empty($region) ? 0 : $region['region_id'];
    }
}
