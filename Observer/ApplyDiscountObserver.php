<?php
namespace DUna\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\SalesRule\Model\Rule;
use Monolog\Logger;
use Logtail\Monolog\LogtailHandler;

class ApplyDiscountObserver implements ObserverInterface
{
    const LOGTAIL_SOURCE = 'magento-bedbath-mx';
    const LOGTAIL_SOURCE_TOKEN = 'DB8ad3bQCZPAshmAEkj9hVLM';

    protected $logger;

    public function __construct() {
        $this->logger = new Logger(self::LOGTAIL_SOURCE);
        $this->logger->pushHandler(new LogtailHandler(self::LOGTAIL_SOURCE_TOKEN));
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $quote = $observer->getEvent()->getQuote();
        $couponCode = $quote->getCouponCode();

        if (!empty($couponCode)) {
            $TotalDiscountPercentage = 0;
            $appliedRuleIds = $quote->getAppliedRuleIds();
            $appliedRules = explode(',', $appliedRuleIds);

            $ruleModel = \Magento\Framework\App\ObjectManager::getInstance()->create(Rule::class);

            foreach ($appliedRules as $ruleId) {
                $ruleModel->load($ruleId);
                $this->logger->debug('rule discount amount: ' . $ruleModel->getDiscountAmount());

                $TotalDiscountPercentage = $TotalDiscountPercentage + $ruleModel->getDiscountAmount();
            }

            $ruleId2 = $ruleModel->getIdByCode($couponCode);
            
            $ruleModel->load($ruleId2);

            $discountPercentage = $ruleModel->getDiscountAmount();

            $this->logger->debug('rule 2 discount amount: ' . $discountPercentage);
            $this->logger->debug('rule model 2: ' , [
                "data" => $ruleModel,
            ]);

            $TotalDiscountPercentage = $TotalDiscountPercentage + $ruleModel->getDiscountAmount();

            $this->logger->debug('Rule Id: ' . $ruleId);
            $this->logger->debug('Total Percentage: ' . $TotalDiscountPercentage);
            $this->logger->debug('Coupon Code: ' . $couponCode);
        }
    }
}
