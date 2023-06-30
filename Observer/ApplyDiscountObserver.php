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
        $this->logger->debug('Coupon Code: ' . $couponCode);

        if (!empty($couponCode)) {
            
            $appliedRuleIds = $quote->getAppliedRuleIds();
            $appliedRules = explode(',', $appliedRuleIds);

            $ruleModel = \Magento\Framework\App\ObjectManager::getInstance()->create(Rule::class);

            foreach ($appliedRules as $ruleId) {
                $ruleModel->load($ruleId);

                $existingDiscountPercentage = $ruleModel->getDiscountAmount();

                $this->logger->debug('Rule Id: ' . $ruleId);
                $this->logger->debug('Exiting Percentage: ' . $existingDiscountPercentage);
             
            }
        }
    }
}
