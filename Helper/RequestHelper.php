<?php

namespace DUna\Payments\Helper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\Client\Curl;
use Monolog\Logger;
use Logtail\Monolog\LogtailHandler;
use DUna\Payments\Helper\Data;

class RequestHelper extends \Magento\Framework\App\Helper\AbstractHelper
{
    const URL_PRODUCTION = 'https://apigw.getduna.com';
    const URL_STAGING = 'https://api.stg.deuna.io';
    const URL_DEVELOPMENT = 'https://api.stg.deuna.io';
    const CONTENT_TYPE = 'application/json';
    const PRIVATE_KEY_PRODUCTION = 'private_key_production';
    const PRIVATE_KEY_STAGING = 'private_key_stage';
    const LOGTAIL_SOURCE = 'plataformas_magento';
    const LOGTAIL_SOURCE_TOKEN = 'DB8ad3bQCZPAshmAEkj9hVLM';
    const DEV_PRIVATE_KEY = 'ab88c4b4866150ebbce7599c827d00f9f238c34e42baa095c9b0b6233e812ba54ef13d1b5ce512e7929eb4804b0218365c1071a35a85311ff3053c5e23a6';

    /**
     * @var curl
     */
    private $curl;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        Data $helper,
        Curl $curl,
        EncryptorInterface $encryptor,
    ) {
        parent::__construct($context);
        $this->curl = $curl;
        $this->helper = $helper;
        $this->encryptor = $encryptor;
        $this->logger = new Logger(self::LOGTAIL_SOURCE);
        $this->logger->pushHandler(new LogtailHandler(self::LOGTAIL_SOURCE_TOKEN));
    }

    /**
     * @param $body
     * @return mixed
     * @throws LocalizedException
     */
    public function request($endpoint, $method = 'GET', $body = null, $headers = [])
    {
        try {
            $response = [];

            $body = json_encode($body);

            $urlEndpoint = $this->getUrl() . $endpoint;
            $headers = $this->getHeaders();

            $this->curl->setHeaders($headers);

            if ($method == 'GET') {
                $this->curl->get($urlEndpoint);
            } else if ($method == 'POST') {
                $this->curl->post($urlEndpoint, $body);
            }

            $result = json_decode($this->curl->getBody(), true);

            if($this->curl->getStatus() != 200) {
                $response = [
                    'statusCode' => $this->curl->getStatus(),
                    'success' => false,
                    'errorCode' => $result['error']['code'],
                    'errorMessage' => $result['error']['description'],
                ];
            } else {
                $response = [
                    'statusCode' => $this->curl->getStatus(),
                    'data' => $result,
                    'success' => true,
                ];
            }

            $this->logger->debug('Request Response', [
                'endpoint' => $endpoint,
                'method' => $method,
                'payload' => $body,
                'headers' => $headers,
                'response' => $result,
            ]);

            return $response;
        } catch (\Exception $e) {
            $err = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTrace(),
            ];
            $this->logger->critical('Error on request', $err);

            return $err;
        }

    }

    /**
     * @return string
     */
    private function getUrl(): string
    {
        $env = $this->getEnvironment();

        switch ($env) {
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
     * @return string[]
     */
    private function getHeaders(): array
    {
        return [
            'X-Api-Key' => $this->getPrivateKey(),
            'Content-Type' => self::CONTENT_TYPE,
        ];
    }

    /**
     * @return string
     */
    public function getPrivateKey(): string
    {
        $env = $this->getEnvironment();

        /**
         * Merchant Dev: MAGENTO
         * Used for local development
         */
        $devPrivateKey = self::DEV_PRIVATE_KEY;

        if ($env == 'develop') {
            return $devPrivateKey;
        } else if ($env == 'staging') {
            $privateKey = $this->helper->getGeneralConfig(self::PRIVATE_KEY_STAGING);
        } else {
            $privateKey = $this->helper->getGeneralConfig(self::PRIVATE_KEY_PRODUCTION);
        }

        return $this->encryptor->decrypt($privateKey);
    }

    public function getEnvironment()
    {
        return $this->helper->getEnv();
    }
}
