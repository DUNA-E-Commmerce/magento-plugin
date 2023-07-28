<?php

namespace DUna\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Monolog\Logger;
use Logtail\Monolog\LogtailHandler;

class RefundObserver implements ObserverInterface
{
    /**
     *  @var \Psr\Log\LoggerInterface $logger
     */
    const LOGTAIL_SOURCE = 'magento-bedbath-mx';
    const LOGTAIL_SOURCE_TOKEN = 'DB8ad3bQCZPAshmAEkj9hVLM';

    protected $logger;

    public function __construct() {
        $this->logger = new Logger(self::LOGTAIL_SOURCE);
        $this->logger->pushHandler(new LogtailHandler(self::LOGTAIL_SOURCE_TOKEN));
    }

    public function execute(Observer $observer)
    {
        $creditmemo = $observer->getEvent()->getCreditmemo();
        $order = $creditmemo->getOrder();
        $orderId = $creditmemo->getOrderId();
        $payment = $order->getPayment();
        $orderToken = $payment->getAdditionalInformation('token');


        $reason = '';
        $reason = $creditmemo->getCustomerNote();

        $creditmemoId = $creditmemo->getId();

        $creditmemo = $creditmemo->getData();

        $totalRefunded = $creditmemo["base_grand_total"];

        $this->logger->debug("Order {$orderId} in process Refund ...", [
            'creditmemoId' => $creditmemoId,
            'orderId' => $orderId,
            'orderToken' => $orderToken,
            'totalRefunded' => $totalRefunded,
            'reason' => $reason,
            'creditmemo' => $creditmemo,
        ]);

        try {
            $resp = $this->refundOrder($orderToken, $reason, $totalRefunded);


            if (isset($resp['error']) && $resp['error']['code'] === 'DP-6000') {
                
                $this->logger->debug("Order {$orderId} has been error in Refunded", [
                    'orderId' => $orderId,
                    'orderToken' => $orderToken,
                    'response' => $resp,
                    'response2' => $resp['response_json'],
                ]);

                $errorDescription = $resp['error']['description'];
    
                $creditmemo->addComment("Refund Error: $errorDescription");
                $creditmemo->save();

                return;
            }else{

                $response = json_decode($resp['response'], true);
                $bodyJson = isset($response['body_json']) ? $response['body_json'] : null;

                $this->logger->debug("Order {$orderId} has been Refunded successfully", [
                    'orderId' => $orderId,
                    'orderToken' => $orderToken,
                    'response' => $resp,
                    'response2' => $bodyJson,
                ]);
            }
            

        } catch (\Exception $e) {
            $this->logger->critical("Error canceling order ID: {$orderId}", [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTrace(),
            ]);
        }
       
    }

    private function refundOrder($orderToken, $reason, $amount)
    {
        $endpoint = "/merchants/orders/{$orderToken}/refund";

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $body = [
            'reason' => $reason,
            'amount' => $amount,
        ];

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $requestHelper = $objectManager->get(\DUna\Payments\Helper\RequestHelper::class);

        $response = $requestHelper->request($endpoint, 'POST', json_encode($body), $headers);
        
        return $response;
    }
}
