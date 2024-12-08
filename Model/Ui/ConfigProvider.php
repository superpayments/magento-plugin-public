<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Superpayments\SuperPayment\Gateway\Config\Config as SuperPaymentsConfig;

class ConfigProvider implements ConfigProviderInterface
{
    /** @var SuperPaymentsConfig */
    private $config;

    /** @var UrlInterface */
    private $urlBuilder;

    /** @var RequestInterface */
    private $request;

    /** @var Repository */
    private $assetRepo;

    public function __construct(
        SuperPaymentsConfig $config,
        RequestInterface $request,
        UrlInterface $urlBuilder,
        Repository $assetRepo
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->assetRepo = $assetRepo;
    }

    public function getPaymentLogo(): ?string
    {
        return $this->assetRepo->getUrl('Superpayments_SuperPayment::images/superbuttonicon.png');
    }

    public function getConfig(): array
    {
        return [
            'payment' => [
                SuperPaymentsConfig::PAYMENT_CODE => [
                    'isActive' => $this->config->isActive(),
                    'title' => __($this->config->getTitle()),
                    'mode' => $this->config->getEnvironment(),
                    'debug' => $this->config->isDebugEnabled(),
                    'paymentLogo' => $this->getPaymentLogo(),
                    'redirectUrl' => $this->urlBuilder->getUrl(
                        'superpayment/payment/redirect/',
                        ['_secure' => $this->request->isSecure()]
                    ),
                    'isDefault' => $this->config->isDefaultSelected(),
                ],
            ],
        ];
    }
}
