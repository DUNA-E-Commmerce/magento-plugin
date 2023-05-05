<?php

namespace DUna\Payments\Helper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\HTTP\Adapter\Curl;
use DUna\Payments\Helper\Data;
use Monolog\Logger;
use Zend_Http_Client;

class RequestHelper extends \Magento\Framework\App\Helper\AbstractHelper
{
    const URL_PRODUCTION = 'https://apigw.getduna.com';
    const URL_STAGING = 'https://api.stg.deuna.io';
    const URL_DEVELOPMENT = 'https://api.dev.deuna.io';
    const CONTENT_TYPE = 'application/json';
    const PRIVATE_KEY_PRODUCTION = 'private_key_production';
    const PRIVATE_KEY_STAGING = 'private_key_stage';
    const LOGTAIL_SOURCE = 'plataformas_magento';
    const LOGTAIL_SOURCE_TOKEN = 'DB8ad3bQCZPAshmAEkj9hVLM';
    const DEV_PRIVATE_KEY = 'd09ae647fceb2a30e6fb091e512e7443b092763a13f17ed15e150dc362586afd92571485c24f77a4a3121bc116d8083734e27079a25dc44493496198b84f';

    /**
     * @var Json
     */
    private $json;

    /**
     * @var Curl
     */
    protected $curl;

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
        Json $json,
        Curl $curl,
        Data $helper,
        Logger $logger,
        EncryptorInterface $encryptor,
    ) {
        parent::__construct($context);
        $this->curl = $curl;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->encryptor = $encryptor;
        $this->json = $json;
    }

    /**
     * @param $body
     * @return mixed
     * @throws LocalizedException
     */
    public function request($endpoint, $method = 'GET', $body = null, $headers = false)
    {
        switch ($method) {
            case 'POST':
                $method = Zend_Http_Client::POST;
                break;
            case 'PUT':
                $method = Zend_Http_Client::PUT;
                break;
            case 'DELETE':
                $method = Zend_Http_Client::DELETE;
                break;
            case 'HEAD':
                $method = Zend_Http_Client::HEAD;
                break;
            case 'OPTIONS':
                $method = Zend_Http_Client::OPTIONS;
                break;
            default:
                $method = Zend_Http_Client::GET;
                break;
        }

        $url = $this->getUrl() . $endpoint;
        $http_ver = '1.1';
        $headers = $this->getHeaders();

        if ($this->getEnvironment() !== 'prod') {
            $this->logger->debug("Environment", [
                'environment' => $this->getEnvironment(),
                'apikey' => $this->getPrivateKey(),
                'request' => $url,
                'body' => $body,
            ]);
        }

        $configuration['header'] = $headers;

        if ($this->getEnvironment() !== 'prod') {
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

        if ($this->getEnvironment() !== 'prod') {
            $this->logger->debug("Response", [
                'data' => $response,
            ]);
        }

        if (!empty($response['error'])) {
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
            'X-Api-Key: ' . $this->getPrivateKey(),
            'Content-Type: ' . self::CONTENT_TYPE
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
