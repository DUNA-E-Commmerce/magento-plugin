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
            return 'ab88c4b4866150ebbce7599c827d00f9f238c34e42baa095c9b0b6233e812ba54ef13d1b5ce512e7929eb4804b0218365c1071a35a85311ff3053c5e23a6';
        } else if ($env == 'production') {
            $publicKey = $this->helper->getGeneralConfig(self::PUBLIC_KEY_PRODUCTION);
        } else if ($env == 'staging') {
            $publicKey = $this->helper->getGeneralConfig(self::PUBLIC_KEY_STAGING);
        }
        return $this->encryptor->decrypt($publicKey);
    }
}
