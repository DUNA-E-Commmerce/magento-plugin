<?php

namespace DUna\Payments\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use DUna\Payments\Helper\Data;
use Magento\Framework\Encryption\EncryptorInterface;

class Checkout extends Template
{
    const PUBLIC_KEY_PRODUCTION = 'public_key_production';
    const PUBLIC_KEY_STAGING = 'public_key_stage';

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    public function __construct(
        Data $helper,
        EncryptorInterface $encryptor,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper = $helper;
        $this->encryptor = $encryptor;
    }

    public function _prepareLayout(): Checkout
    {
        return parent::_prepareLayout();
    }

    public function getEnv(): string
    {
        return $this->helper->getEnv();
    }

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        $env = $this->helper->getEnv();

        if($env == 'develop') {
            return 'd09ae647fceb2a30e6fb091e512e7443b092763a13f17ed15e150dc362586afd92571485c24f77a4a3121bc116d8083734e27079a25dc44493496198b84f';
        } else if ($env == 'production') {
            $publicKey = $this->helper->getGeneralConfig(self::PUBLIC_KEY_PRODUCTION);
        } else if ($env == 'staging') {
            $publicKey = $this->helper->getGeneralConfig(self::PUBLIC_KEY_STAGING);
        }
        return $this->encryptor->decrypt($publicKey);
    }
}
