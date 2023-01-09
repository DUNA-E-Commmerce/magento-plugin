<?php

namespace DUna\Payments\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use DUna\Payments\Logger\Logger;

class Data extends AbstractHelper
{
    /**
     * constant
     */
    const XML_PATH_DUNA = 'duna/';
    const MODE_PRODUCTION = 2;
    const MODE_STAGING = 1;

    /**
     * Logger instance
     * @var Logger
     */
    protected $logger;

    public function __construct(
        Context $context,
        Logger $logger
    ) {
        parent::__construct($context);
        $this->logger = $logger;
    }

    /**
     * @param $field
     * @param $storeId
     * @return mixed
     */
    public function getConfigValue($field, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            $field, ScopeInterface::SCOPE_STORE, $storeId
        );
    }

    /**
     * @param $code
     * @param $storeId
     * @return mixed
     */
    public function getGeneralConfig($code, $storeId = null)
    {
        return $this->getConfigValue(self::XML_PATH_DUNA .'config/'. $code, $storeId);
    }

    /**
     * @return string
     */
    public function getEnv(): string
    {
        // $mode = $this->getGeneralConfig('mode');
        // if ($mode == self::MODE_PRODUCTION) {
        //     $env = 'production';
        // }
        // if ($mode == self::MODE_STAGING) {
        //     $env = 'staging';
        // }
        // return $env;

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');

        $domain = $storeManager->getStore()->getBaseUrl();

        if(str_contains($domain, 'dev.')) {
            return 'develop';
        } else if(str_contains($domain, 'stg.') || str_contains($domain, 'mcstaging.')) {
            return 'staging';
        } else {
            return 'production';
        }
    }

    /**
     * Logger instance
     * @param $message
     * @param $type
     * @param array $context
     * @return void
     */
    public function log($type, $message, array $context = []) {
        $this->logger->{$type}($message, $context);
    }
}
