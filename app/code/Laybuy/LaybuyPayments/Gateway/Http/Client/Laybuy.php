<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Laybuy\LaybuyPayments\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use Laybuy\LaybuyPayments\Gateway\Config\Config;
use Magento\Payment\Gateway\Http\Client\Zend as httpClient;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Framework\HTTP\ZendClient;
use Magento\Payment\Gateway\Http\ConverterInterface;
use GuzzleHttp\Client;

class Laybuy implements ClientInterface
{
    const SUCCESS = 1;
    const FAILURE = 0;
    
    const LAYBUY_LIVE_URL       = 'https://api.laybuy.com';
    const LAYBUY_SANDBOX_URL    = 'https://sandbox-api.laybuy.com';
    const LAYBUY_RETURN_SUCCESS = 'laybuypayments/payment/process?result=success';
    const LAYBUY_RETURN_FAIL    = 'laybuypayments/payment/process?result=fail';
    
    /**
     * @var bool
     */
    protected $laybuy_sandbox = TRUE;
    
    /**
     * @var string
     */
    protected $laybuy_merchantid;
    
    /**
     * @var string
     */
    protected $laybuy_apikey;
    
    /**
     * @var string
     */
    protected $endpoint;
    
    /**
     * @var \Zend_Rest_Client
     */
    protected $restClient;
    
    /**
     * @var Config
     */
    private $config;
    
    /**
     * @var array
     */
    private $results = [
        self::SUCCESS,
        self::FAILURE
    ];

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(
        Config $config,
        Logger $logger
    ) {
        $this->logger = $logger;
        $this->config = $config;
    
        $this->logger->debug([__METHOD__ . ' TEST sandbox? ' => $this->config->getUseSandbox()]);
        $this->logger->debug([__METHOD__ . ' TEST sandbox_merchantid? ' => $this->config->getSandboxMerchantId()]);
        
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param TransferInterface $transferObject
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject)
    {
    
        $this->logger->debug(['transferObject'  => $transferObject->getBody()]);
      
        $client = $this->getRestClient();
      
        // check if we are returning
        // TODO: find better way to get teh redirect url to KO frontend, rather than shortcurcit
    
        /* @var $urlInterface \Magento\Framework\UrlInterface */
        $urlInterface = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\UrlInterface');
        
        /*
        echo "<pre>" . print_r($urlInterface->getCurrentUrl(), 1) . "</pre>";
        echo "<pre>" . print_r($urlInterface->getRouteUrl(), 1) . "</pre>";
        echo "<pre>" . print_r($urlInterface->getQueryParam('status'), 1) . "</pre>";
        echo "<pre>" . print_r($urlInterface->, 1) . "</pre>";
        */
        $path = parse_url($urlInterface->getCurrentUrl(), PHP_URL_PATH);
    
        $this->logger->debug([' URL PATH ' => $path]);
        
        // yes I know
        if($path == '/laybuypayments/payment/process') {
            $data = [];
            parse_str(parse_url($urlInterface->getCurrentUrl(), PHP_URL_QUERY), $data);
            $this->logger->debug(['PHP_URL_QUERY process payment ' => $data]);
            $result = $data['status'];
          
            $this->logger->debug(['token form process payment ' => $data['token']]);
            
            $laybuy = new \stdClass();
            $laybuy->token = $data['token'];
            $response = $client->restPost('/order/confirm', json_encode($laybuy));

            $body = json_decode($response->getBody());
            $this->logger->debug(['confirm reposnse body' => $body]);
            
            
            if ($body->result == 'SUCCESS') {
    
                $this->logger->debug(['reposnse body' => $body]);
 
                return [
                    'ACTION'      => 'process',
                    'TXN_ID'       => $body->orderId
                ];
            }
            else {
    
                // $this->noLaybuyRedirectError($body);
                $this->logger->debug(['FAILED TO GET returnURL' => $body]);
    
            }
            
            
            // TODO: fix fail
        }
        else {
            $response = $client->restPost('/order/create', json_encode($transferObject->getBody()));
    
            $body = json_decode($response->getBody());
            $this->logger->debug(['redirct reposnse body' => $body]);
    
            /* stdClass Object
                    (
                        [result] => SUCCESS
                        [token] => a5MbmNdAU6WNFIT4smlr45BiuM4Nrb4CiFRPmj0f
                        [paymentUrl] => https://sandbox-payment.laybuy.com/pay/a5MbmNdAU6WNFIT4smlr45BiuM4Nrb4CiFRPmj0f
                    )
             */
    
            if ($body->result == 'SUCCESS') {
        
                $this->logger->debug(['reposnse body' => $body]);
        
                if (!$body->paymentUrl) {
                    // $this->noLaybuyRedirectError($body);
                }
        
                return [
                    'ACTION'      =>  'redirect',
                    'TOKEN'       => $body->token,
                    'RETURN_URL'  => $body->paymentUrl,
                ];
            }
            else {
        
                // $this->noLaybuyRedirectError($body);
                $this->logger->debug(['FAILED TO GET returnURL' => $body]);
        
            }
            
        }
        
        
    
    
       
        
        
    
    }

    /**
     * Returns response fields for result code
     *
     * @param int $resultCode
     *
     * @return \Zend_Rest_Client
     */
    private function getRestClient() {
    
        if (is_null($this->laybuy_merchantid)) { // ?? just do it anyway?
            $this->setupLaybuy();
        }
        
        try {
            
            $this->restClient = new \Zend_Rest_Client($this->endpoint);
            $this->restClient->getHttpClient()->setAuth($this->laybuy_merchantid, $this->laybuy_apikey, \Zend_Http_Client::AUTH_BASIC);
            
            //$this->restClient->getHttpClient()->setAuth('100000' , 'Kaz1xe5WwpOvl3pJL4FqqX1vrnJGrcxghJRKQqZddKBLg23DxsQA2qRhK6QJcVus', \Zend_Http_Client::AUTH_BASIC);
            // 'auth' => ['100000', 'Kaz1xe5WwpOvl3pJL4FqqX1vrnJGrcxghJRKQqZddKBLg23DxsQA2qRhK6QJcVus'],
    
        } catch (\Exception $e) {
           
            // Mage::logException($e);
            // Mage::helper('checkout')->sendPaymentFailedEmail($this->getOnepage()->getQuote(), $e->getMessage());
    
            $this->logger->debug([__METHOD__ . ' CLIENT FAILED: ' => $this->laybuy_merchantid . ":" . $this->laybuy_apikey]);
            
            $result['success']        = FALSE;
            $result['error']          = TRUE;
            $result['error_messages'] = $this->__('[Laybuy connect] There was an error processing your order. Please contact us or try again later.');
            // TODOD this error needs to go back to the user
        }
        
        return $this->restClient;
        
    }
    
    
    private function setupLaybuy() {
        $this->logger->debug([__METHOD__ . ' sandbox? ' => $this->config->getUseSandbox() ]);
        $this->logger->debug([__METHOD__ . ' sandbox_merchantid? '  => $this->config->getSandboxMerchantId() ]);
        
        $this->laybuy_sandbox = $this->config->getUseSandbox() == 1;
        
        if ($this->laybuy_sandbox) {
            $this->endpoint          = self::LAYBUY_SANDBOX_URL;
            $this->laybuy_merchantid = $this->config->getSandboxMerchantId();
            $this->laybuy_apikey     = $this->config->getSandboxApiKey();
        }
        else {
            $this->endpoint          = self::LAYBUY_LIVE_URL;
            $this->laybuy_merchantid = $this->config->getMerchantId();
            $this->laybuy_apikey     = $this->config->getApiKey();
        }
        
        $this->logger->debug([__METHOD__ . ' CLIENT INIT: ' => $this->laybuy_merchantid . ":" . $this->laybuy_apikey]);
        $this->logger->debug([__METHOD__ . ' INITIALISED' => '' ]);
    }
    
    
}
