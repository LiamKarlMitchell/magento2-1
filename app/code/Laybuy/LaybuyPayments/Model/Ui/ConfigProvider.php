<?php

namespace Laybuy\LaybuyPayments\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Laybuy\LaybuyPayments\Gateway\Config\Config;

final class ConfigProvider implements ConfigProviderInterface
{
    const CODE  = 'laybuy_laybuypayments';

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Config $config
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * Retrieve checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'isActive' => $this->config->isActive(),
                    'merchant_id' => $this->config->getMerchantId(),
                    'api_key' => $this->config->getApiKey(),
                    'sandbox_merchant_id' => $this->config->getSandboxMerchantId(),
                    'sandbox_api_key'     => $this->config->getSandboxApiKey(),
                    'use_sandbox' => $this->config->getUseSandbox()
                ],
            ]
        ];
    }
}
